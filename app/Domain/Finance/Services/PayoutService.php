<?php

namespace App\Domain\Finance\Services;

use App\Domain\Content\Services\SettingsRepository;
use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Enums\PayoutStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\Payout;
use App\Domain\Organisation\Services\DepartmentAccess;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Payouts to people (LLD 07 §3). Draft, submit, approve (twice, by two
 * different people, from the threshold), then mark paid: only then does a
 * Payout transaction leave the source account.
 */
final class PayoutService
{
    public function __construct(
        private readonly FinanceAccounts $accounts,
        private readonly LedgerService $ledger,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @param  array{user_id: int, type: string, amount: string, currency: string, rate_to_lyd?: string|null, source_account_id: int, reason: string, guardian_name?: string|null, guardian_contact?: string|null}  $data
     */
    public function draft(array $data, User $by): Payout
    {
        $payee = User::query()->findOrFail($data['user_id']);
        $source = FinanceAccount::query()->findOrFail($data['source_account_id']);
        $currency = $data['currency'];
        $amount = Money::of((string) $data['amount'], $currency);

        if (! $source->type->isInternal() || $source->currency !== $currency) {
            throw DomainRuleViolation::with(['source_account_id' => __('finance.errors.split_account')]);
        }

        if (! $amount->isPositive()) {
            throw DomainRuleViolation::with(['amount' => __('finance.errors.amount_positive')]);
        }

        $age = $payee->ageOn(now());

        if ($age !== null && $age < 18 && (! filled($data['guardian_name'] ?? null) || ! filled($data['guardian_contact'] ?? null))) {
            throw DomainRuleViolation::with(['guardian_name' => __('finance.errors.guardian_required')]);
        }

        $rate = $currency === 'LYD' ? '1' : (string) ($data['rate_to_lyd'] ?? '');

        if (! is_numeric($rate) || (float) $rate <= 0) {
            throw DomainRuleViolation::with(['rate_to_lyd' => __('finance.errors.rate_required')]);
        }

        return Payout::query()->create([
            'user_id' => $payee->getKey(),
            'guardian_name' => $data['guardian_name'] ?? null,
            'guardian_contact' => $data['guardian_contact'] ?? null,
            'type' => $data['type'],
            'amount_minor' => $amount->getMinorAmount()->toInt(),
            'currency' => $currency,
            'rate_to_lyd' => $rate,
            'source_account_id' => $source->getKey(),
            'reason' => $data['reason'],
            'status' => PayoutStatus::Draft,
            'needs_second_approval' => $this->lydMinor($amount, $rate) >= (int) $this->settings->get('finance.second_approval_threshold'),
            'created_by' => $by->getKey(),
        ]);
    }

    public function submit(Payout $payout, User $by): void
    {
        $this->assertStatus($payout, PayoutStatus::Draft);

        $payout->forceFill(['status' => PayoutStatus::Submitted, 'submitted_by' => $by->getKey()])->save();
        $this->log($payout, $by, 'finance.payout.submitted');
    }

    /**
     * The first approval; for payouts at or above the threshold, a second
     * approval by someone else completes it.
     */
    public function approve(Payout $payout, User $by): void
    {
        $this->assertStatus($payout, PayoutStatus::Submitted);
        $this->assertApprover($by);

        if (in_array($by->getKey(), [$payout->created_by, $payout->submitted_by], true)) {
            throw DomainRuleViolation::with(['approver' => __('finance.errors.maker_checker')]);
        }

        if ($payout->approved_by === null) {
            $payout->forceFill([
                'approved_by' => $by->getKey(),
                'status' => $payout->needs_second_approval ? PayoutStatus::Submitted : PayoutStatus::Approved,
            ])->save();
            $this->log($payout, $by, 'finance.payout.approved');

            return;
        }

        if ($payout->approved_by === $by->getKey()) {
            throw DomainRuleViolation::with(['approver' => __('finance.errors.second_approver')]);
        }

        $payout->forceFill(['second_approved_by' => $by->getKey(), 'status' => PayoutStatus::Approved])->save();
        $this->log($payout, $by, 'finance.payout.second_approved');
    }

    /**
     * Record the payment and post the Payout transaction to the payee's
     * external account. The transaction's maker is the payout's maker, so
     * ledger maker-checker still applies to whoever marks it paid.
     *
     * @param  array{method: string, reference: string|null, paid_on: string}  $data
     */
    public function markPaid(Payout $payout, array $data, User $by): void
    {
        $this->assertStatus($payout, PayoutStatus::Approved);

        if (! filled($data['reference'] ?? null)) {
            throw DomainRuleViolation::with(['reference' => __('finance.errors.reference_required')]);
        }

        DB::transaction(function () use ($payout, $data, $by): void {
            $payout->loadMissing(['payee', 'creator']);
            $maker = $payout->creator ?? $by;
            $payeeAccount = $this->accounts->external(AccountType::ExternalPerson, $payout->payee->name_en, $payout->currency, $payout->payee);

            $transaction = $this->ledger->draft([
                'from_account_id' => $payout->source_account_id,
                'to_account_id' => $payeeAccount->getKey(),
                'amount' => $payout->amount,
                'date' => $data['paid_on'],
                'type' => TransactionType::Payout,
                'description' => __('finance.payout_to', ['name' => $payout->payee->name_en, 'reason' => $payout->reason]),
                'reference' => $data['reference'],
                'rate_to_lyd' => $payout->rate_to_lyd,
                'source' => $payout,
            ], $maker);
            $this->ledger->submit($transaction, $maker);
            $this->ledger->post($transaction, $by);

            $payout->forceFill([
                'status' => PayoutStatus::Paid,
                'method' => $data['method'],
                'reference' => $data['reference'],
                'paid_on' => $data['paid_on'],
                'paid_by' => $by->getKey(),
                'transaction_id' => $transaction->getKey(),
            ])->save();

            $this->log($payout, $by, 'finance.payout.paid');
        });
    }

    public function reject(Payout $payout, string $reason, User $by): void
    {
        $this->assertStatus($payout, PayoutStatus::Submitted);
        $this->assertApprover($by);

        $payout->forceFill(['status' => PayoutStatus::Rejected, 'rejection_reason' => $reason])->save();
        $this->log($payout, $by, 'finance.payout.rejected');
    }

    public function cancel(Payout $payout, User $by): void
    {
        if (! in_array($payout->status, [PayoutStatus::Draft, PayoutStatus::Submitted, PayoutStatus::Approved], true)) {
            throw DomainRuleViolation::with(['status' => __('finance.errors.wrong_status')]);
        }

        $payout->forceFill(['status' => PayoutStatus::Cancelled])->save();
        $this->log($payout, $by, 'finance.payout.cancelled');
    }

    /**
     * Paid amounts per year and currency, for "My earnings".
     *
     * @return list<array{year: int, currency: string, amountMinor: int}>
     */
    public function totalsByYear(User $user): array
    {
        return Payout::query()
            ->where('user_id', $user->getKey())
            ->where('status', PayoutStatus::Paid)
            ->get(['paid_on', 'currency', 'amount_minor'])
            ->groupBy(fn (Payout $payout): string => $payout->paid_on->format('Y').'|'.$payout->currency)
            ->map(function ($group, string $key): array {
                [$year, $currency] = explode('|', $key);

                return ['year' => (int) $year, 'currency' => $currency, 'amountMinor' => (int) $group->sum('amount_minor')];
            })
            ->sortByDesc('year')
            ->values()
            ->all();
    }

    private function lydMinor(Money $amount, string $rate): int
    {
        if ($amount->getCurrency()->getCurrencyCode() === 'LYD') {
            return $amount->getMinorAmount()->toInt();
        }

        return Money::of($amount->getAmount()->multipliedBy($rate), 'LYD', roundingMode: RoundingMode::HalfUp)->getMinorAmount()->toInt();
    }

    private function assertApprover(User $user): void
    {
        if (! app(DepartmentAccess::class)->can($user, 'finance.approve')) {
            throw DomainRuleViolation::with(['approver' => __('finance.errors.not_approver')]);
        }
    }

    private function assertStatus(Payout $payout, PayoutStatus $status): void
    {
        if ($payout->status !== $status) {
            throw DomainRuleViolation::with(['status' => __('finance.errors.wrong_status')]);
        }
    }

    private function log(Payout $payout, User $by, string $event): void
    {
        activity('finance')->performedOn($payout)->causedBy($by)
            ->withProperties(['amount_minor' => $payout->amount_minor, 'currency' => $payout->currency, 'user_id' => $payout->user_id])
            ->event($event)
            ->log(ucfirst(str_replace(['finance.payout.', '_'], ['Payout ', ' '], $event)));
    }
}
