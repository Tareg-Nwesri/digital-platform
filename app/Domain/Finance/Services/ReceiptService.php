<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Enums\DocumentStatus;
use App\Domain\Finance\Enums\PaymentMethod;
use App\Domain\Finance\Enums\TransactionStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\LedgerTransaction;
use App\Domain\Finance\Models\Receipt;
use App\Domain\Finance\Models\Sponsorship;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Incoming money (LLD 07 §3). A receipt becomes one Income transaction per
 * split line; posting approves all of them or none.
 */
final class ReceiptService
{
    public function __construct(
        private readonly FinanceAccounts $accounts,
        private readonly SplitCalculator $splits,
        private readonly LedgerService $ledger,
        private readonly NumberSequence $numbers,
    ) {}

    /**
     * @param  array{sponsorship_id?: int|null, source: string, payer_name?: string|null, date: string, amount?: string|null, currency: string, rate_to_lyd?: string|null, method: string, reference?: string|null, in_kind_items?: list<array{description: string, quantity: int, value: string, part_id?: int|null}>|null}  $data
     */
    public function draft(array $data, User $by): Receipt
    {
        $currency = $data['currency'];
        $sponsorship = filled($data['sponsorship_id'] ?? null) ? Sponsorship::query()->with('sponsor')->findOrFail($data['sponsorship_id']) : null;
        $items = null;

        if ($data['method'] === PaymentMethod::InKind->value) {
            $items = array_map(fn (array $item): array => [
                'description' => $item['description'],
                'quantity' => (int) ($item['quantity'] ?? 1),
                'value_minor' => Money::of((string) $item['value'], $currency)->getMinorAmount()->toInt(),
                'part_id' => $item['part_id'] ?? null,
            ], $data['in_kind_items'] ?? []);
            $amountMinor = array_sum(array_column($items, 'value_minor'));
        } else {
            $amountMinor = Money::of((string) ($data['amount'] ?? '0'), $currency)->getMinorAmount()->toInt();
        }

        if ($amountMinor <= 0) {
            throw DomainRuleViolation::with(['amount' => __('finance.errors.amount_positive')]);
        }

        $payer = $sponsorship !== null
            ? $this->accounts->external(AccountType::ExternalSponsor, $sponsorship->sponsor->name_en, $currency, $sponsorship->sponsor)
            : $this->accounts->external(AccountType::ExternalOther, (string) ($data['payer_name'] ?? __('finance.unknown_payer')), $currency);

        return Receipt::query()->create([
            'sponsorship_id' => $sponsorship?->getKey(),
            'source' => $data['source'],
            'payer_account_id' => $payer->getKey(),
            'date' => $data['date'],
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'rate_to_lyd' => $currency === 'LYD' ? '1' : (string) ($data['rate_to_lyd'] ?? '1'),
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'in_kind_items' => $items,
            'status' => DocumentStatus::Draft,
            'created_by' => $by->getKey(),
        ]);
    }

    /**
     * @return list<array{account: FinanceAccount, amount: Money}>
     */
    public function proposeSplit(Receipt $receipt): array
    {
        return $this->splits->propose($receipt->amount, $receipt->sponsorship);
    }

    /**
     * Turn the (possibly edited) split into submitted Income transactions.
     *
     * @param  list<array{account?: FinanceAccount, account_id?: int, amount: Money}>  $lines
     */
    public function submit(Receipt $receipt, array $lines, User $by): void
    {
        if ($receipt->status !== DocumentStatus::Draft) {
            throw DomainRuleViolation::with(['status' => __('finance.errors.wrong_status')]);
        }

        $total = Money::zero($receipt->currency);
        $resolved = [];

        foreach ($lines as $index => $line) {
            $account = $line['account'] ?? FinanceAccount::query()->find($line['account_id'] ?? null);

            if ($account === null || ! $account->type->isInternal() || $account->currency !== $receipt->currency || $account->is_archived) {
                throw DomainRuleViolation::with(["lines.{$index}.account_id" => __('finance.errors.split_account')]);
            }

            $total = $total->plus($line['amount']);
            $resolved[] = [$account, $line['amount']];
        }

        if ($resolved === [] || ! $total->isEqualTo($receipt->amount)) {
            throw DomainRuleViolation::with(['lines' => __('finance.errors.split_total', ['amount' => (string) $receipt->amount->getAmount()])]);
        }

        DB::transaction(function () use ($receipt, $resolved, $by): void {
            $receipt->loadMissing('sponsorship');
            $description = $receipt->sponsorship !== null
                ? __('finance.receipt_from', ['title' => $receipt->sponsorship->title])
                : __('finance.receipt_income', ['source' => $receipt->source->label()]);

            foreach ($resolved as [$account, $amount]) {
                $transaction = $this->ledger->draft([
                    'from_account_id' => $receipt->payer_account_id,
                    'to_account_id' => $account->getKey(),
                    'amount' => $amount,
                    'date' => $receipt->date->toDateString(),
                    'type' => TransactionType::Income,
                    'description' => $description,
                    'reference' => $receipt->reference,
                    'rate_to_lyd' => $receipt->rate_to_lyd,
                    'source' => $receipt,
                ], $by);

                $this->ledger->submit($transaction, $by);
            }

            $receipt->forceFill(['status' => DocumentStatus::Submitted])->save();
        });
    }

    /**
     * Approve every line or none, then number the receipt (R-2027-00001).
     */
    public function post(Receipt $receipt, User $approver): void
    {
        if ($receipt->status !== DocumentStatus::Submitted) {
            throw DomainRuleViolation::with(['status' => __('finance.errors.wrong_status')]);
        }

        DB::transaction(function () use ($receipt, $approver): void {
            $receipt->transactions()->where('status', TransactionStatus::Submitted)->get()
                ->each(fn (LedgerTransaction $transaction) => $this->ledger->post($transaction, $approver));

            $receipt->forceFill([
                'status' => DocumentStatus::Posted,
                'approved_by' => $approver->getKey(),
                'number' => $this->numbers->next('R', (int) $receipt->date->format('Y')),
            ])->save();
        });
    }

    public function reject(Receipt $receipt, string $reason, User $by): void
    {
        if ($receipt->status !== DocumentStatus::Submitted) {
            throw DomainRuleViolation::with(['status' => __('finance.errors.wrong_status')]);
        }

        DB::transaction(function () use ($receipt, $reason, $by): void {
            $receipt->transactions()->where('status', TransactionStatus::Submitted)->get()
                ->each(fn (LedgerTransaction $transaction) => $this->ledger->reject($transaction, $reason, $by));

            $receipt->forceFill(['status' => DocumentStatus::Rejected, 'rejection_reason' => $reason])->save();
        });
    }
}
