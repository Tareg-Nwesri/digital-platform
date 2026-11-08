<?php

namespace App\Domain\Finance\Services;

use App\Domain\Content\Services\SettingsRepository;
use App\Domain\Finance\Enums\TransactionStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\FiscalPeriod;
use App\Domain\Finance\Models\LedgerTransaction;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentAccess;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Brick\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of ledger_transactions and of every account's cached
 * balance (LLD 07 §3).
 */
final class LedgerService
{
    public function __construct(
        private readonly NumberSequence $numbers,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @param  array{from_account_id: int, to_account_id: int, amount: Money, date: string, type?: TransactionType, description: string, reference?: string|null, rate_to_lyd?: string|float|null, source?: Model|null}  $data
     */
    public function draft(array $data, User $by): LedgerTransaction
    {
        $from = FinanceAccount::query()->find($data['from_account_id']);
        $to = FinanceAccount::query()->find($data['to_account_id']);
        $amount = $data['amount'];
        $currency = $amount->getCurrency()->getCurrencyCode();

        if ($from === null || $to === null || $from->is($to)) {
            throw DomainRuleViolation::with(['to_account_id' => __('finance.errors.accounts')]);
        }

        if ($from->currency !== $currency || $to->currency !== $currency) {
            throw DomainRuleViolation::with(['currency' => __('finance.errors.currency_mismatch')]);
        }

        if (! $amount->isPositive()) {
            throw DomainRuleViolation::with(['amount' => __('finance.errors.amount_positive')]);
        }

        $rate = $currency === 'LYD' ? '1' : (string) ($data['rate_to_lyd'] ?? '');

        if (! is_numeric($rate) || (float) $rate <= 0) {
            throw DomainRuleViolation::with(['rate_to_lyd' => __('finance.errors.rate_required')]);
        }

        $source = $data['source'] ?? null;

        return LedgerTransaction::query()->create([
            'date' => $data['date'],
            'from_account_id' => $from->getKey(),
            'to_account_id' => $to->getKey(),
            'amount_minor' => $amount->getMinorAmount()->toInt(),
            'currency' => $currency,
            'rate_to_lyd' => $rate,
            'type' => $data['type'] ?? TransactionType::Adjustment,
            'status' => TransactionStatus::Draft,
            'description' => $data['description'],
            'reference' => $data['reference'] ?? null,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'created_by' => $by->getKey(),
        ]);
    }

    public function submit(LedgerTransaction $transaction, User $by): void
    {
        $this->assertStatus($transaction, TransactionStatus::Draft);

        $transaction->forceFill([
            'status' => TransactionStatus::Submitted,
            'submitted_by' => $by->getKey(),
            'submitted_at' => now(),
        ])->save();
    }

    /**
     * Post a submitted transaction (LLD 07 §3, steps 1–10).
     */
    public function post(LedgerTransaction $transaction, User $approver, ?string $overrideReason = null): void
    {
        DB::transaction(function () use ($transaction, $approver, $overrideReason): void {
            $locked = LedgerTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());
            $this->assertStatus($locked, TransactionStatus::Submitted);

            if (in_array($approver->getKey(), [$locked->created_by, $locked->submitted_by], true)) {
                throw DomainRuleViolation::with(['approver' => __('finance.errors.maker_checker')]);
            }

            $this->postLocked($locked, $approver, $overrideReason);
        });

        $transaction->refresh();
    }

    public function reject(LedgerTransaction $transaction, string $reason, User $by): void
    {
        $this->assertApprover($by);
        $this->assertStatus($transaction, TransactionStatus::Submitted);

        $transaction->forceFill([
            'status' => TransactionStatus::Rejected,
            'rejected_by' => $by->getKey(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        $this->log($transaction, $by, 'finance.transaction.rejected', ['reason' => $reason]);
    }

    /**
     * Undo a posted transaction with an equal and opposite one, dated today.
     * There is no maker, so maker-checker does not apply; every other
     * posting rule does.
     */
    public function reverse(LedgerTransaction $transaction, string $reason, User $by): LedgerTransaction
    {
        return DB::transaction(function () use ($transaction, $reason, $by): LedgerTransaction {
            $original = LedgerTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());

            if ($original->status !== TransactionStatus::Posted || $original->isReversed() || $original->type === TransactionType::Reversal) {
                throw DomainRuleViolation::with(['transaction' => __('finance.errors.cannot_reverse')]);
            }

            $reversal = LedgerTransaction::query()->create([
                'date' => now()->toDateString(),
                'from_account_id' => $original->to_account_id,
                'to_account_id' => $original->from_account_id,
                'amount_minor' => $original->amount_minor,
                'currency' => $original->currency,
                'rate_to_lyd' => $original->rate_to_lyd,
                'type' => TransactionType::Reversal,
                'status' => TransactionStatus::Submitted,
                'description' => __('finance.reversal_of', ['number' => $original->number, 'reason' => $reason]),
                'reverses_id' => $original->getKey(),
                'created_by' => $by->getKey(),
            ]);

            $this->postLocked($reversal, $by, null);

            $original->forceFill(['reversed_by_id' => $reversal->getKey()])->save();

            return $reversal->refresh();
        });
    }

    /**
     * Balance from posted rows, optionally as of the end of a date.
     */
    public function balance(FinanceAccount $account, ?CarbonInterface $asOf = null): Money
    {
        $posted = fn (string $column) => (int) LedgerTransaction::query()
            ->where($column, $account->getKey())
            ->where('status', TransactionStatus::Posted)
            ->when($asOf !== null, fn ($query) => $query->whereDate('date', '<=', $asOf->toDateString()))
            ->sum('amount_minor');

        return Money::ofMinor($posted('to_account_id') - $posted('from_account_id'), $account->currency);
    }

    /**
     * Accounts whose cached balance differs from their posted rows.
     *
     * @return array<int, array{cached: int, computed: int}>
     */
    public function verifyAll(): array
    {
        $incoming = $this->postedTotals('to_account_id');
        $outgoing = $this->postedTotals('from_account_id');
        $mismatches = [];

        foreach (FinanceAccount::query()->get(['id', 'balance_minor']) as $account) {
            $computed = ($incoming[$account->getKey()] ?? 0) - ($outgoing[$account->getKey()] ?? 0);

            if ($computed !== $account->balance_minor) {
                $mismatches[$account->getKey()] = ['cached' => $account->balance_minor, 'computed' => $computed];
            }
        }

        return $mismatches;
    }

    public function closePeriod(FiscalPeriod $period, User $by): void
    {
        $this->assertApprover($by);

        $period->forceFill(['closed_at' => now(), 'closed_by' => $by->getKey()])->save();

        activity('finance')->performedOn($period)->causedBy($by)->event('finance.period.closed')->log('Fiscal period closed');
    }

    /**
     * Steps 3–10 of posting, on a transaction row already locked.
     */
    private function postLocked(LedgerTransaction $transaction, User $approver, ?string $overrideReason): void
    {
        $this->assertApprover($approver);

        /** @var Collection<int, FinanceAccount> $accounts */
        $accounts = FinanceAccount::query()
            ->whereIn('id', [$transaction->from_account_id, $transaction->to_account_id])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $from = $accounts->get($transaction->from_account_id);
        $to = $accounts->get($transaction->to_account_id);

        if ($from->currency !== $transaction->currency || $to->currency !== $transaction->currency) {
            throw DomainRuleViolation::with(['currency' => __('finance.errors.currency_mismatch')]);
        }

        if ($from->is_archived || $to->is_archived) {
            throw DomainRuleViolation::with(['account' => __('finance.errors.archived_account')]);
        }

        $period = FiscalPeriod::containing($transaction->date->toDateString());

        if ($period === null || $period->isClosed()) {
            throw DomainRuleViolation::with(['date' => __('finance.errors.period_closed')]);
        }

        $override = null;

        if ($from->type->isInternal() && $from->balance_minor - $transaction->amount_minor < 0) {
            if (! filled($overrideReason) || ! $this->canOverride($approver)) {
                throw DomainRuleViolation::with(['amount' => __('finance.errors.insufficient_balance', ['account' => $from->localized('name')])]);
            }

            $override = $overrideReason;
        }

        $transaction->forceFill([
            'status' => TransactionStatus::Posted,
            'approved_by' => $approver->getKey(),
            'posted_at' => now(),
            'fiscal_period_id' => $period->getKey(),
            'number' => $this->numbers->next('T', (int) $transaction->date->format('Y')),
            'override_reason' => $override,
        ])->save();

        FinanceAccount::query()->whereKey($from->getKey())->decrement('balance_minor', $transaction->amount_minor);
        FinanceAccount::query()->whereKey($to->getKey())->increment('balance_minor', $transaction->amount_minor);

        $this->log($transaction, $approver, 'finance.transaction.posted', array_filter([
            'number' => $transaction->number,
            'amount_minor' => $transaction->amount_minor,
            'currency' => $transaction->currency,
            'override_reason' => $override,
        ]));
    }

    /**
     * Admins of the finance department (the finance.department_id setting)
     * may let an internal account go negative, with a reason.
     */
    private function canOverride(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $department = Department::query()->find($this->settings->get('finance.department_id'));

        return $department !== null && $department->isAdmin($user);
    }

    private function assertApprover(User $user): void
    {
        if (! app(DepartmentAccess::class)->can($user, 'finance.approve')) {
            throw DomainRuleViolation::with(['approver' => __('finance.errors.not_approver')]);
        }
    }

    private function assertStatus(LedgerTransaction $transaction, TransactionStatus $status): void
    {
        if ($transaction->status !== $status) {
            throw DomainRuleViolation::with(['status' => __('finance.errors.wrong_status')]);
        }
    }

    /**
     * @return array<int, int>
     */
    private function postedTotals(string $column): array
    {
        return LedgerTransaction::query()
            ->where('status', TransactionStatus::Posted)
            ->groupBy($column)
            ->selectRaw("{$column} as account_id, SUM(amount_minor) as total")
            ->pluck('total', 'account_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(LedgerTransaction $transaction, User $by, string $event, array $properties = []): void
    {
        activity('finance')->performedOn($transaction)->causedBy($by)->withProperties($properties)->event($event)
            ->log(ucfirst(str_replace(['finance.transaction.', '_'], ['Transaction ', ' '], $event)));
    }
}
