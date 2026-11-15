<?php

namespace App\Domain\Finance\Services;

use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Enums\DocumentStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Organisation\Services\DepartmentAccess;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Work\Models\Project;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * LLD 07 §3: money a team spent from its budget. Finance approves the
 * expense, which posts an Expense transaction from the budget to the
 * vendor's external account; the ledger refuses overspending.
 */
final class ExpenseService
{
    public function __construct(
        private readonly FinanceAccounts $accounts,
        private readonly LedgerService $ledger,
        private readonly TeamFeatures $features,
    ) {}

    /**
     * @param  array{budget_account_id: int, vendor_id?: int|null, vendor_name?: string|null, date: string, amount: string, items: list<array{description: string, amount: string}>}  $data
     */
    public function draft(Team $team, array $data, UploadedFile $receipt, User $by): Expense
    {
        $this->assertCanSpend($team);
        $budget = $this->teamBudget($team, (int) $data['budget_account_id']);
        $amount = Money::of((string) $data['amount'], $budget->currency);

        if (! $amount->isPositive()) {
            throw DomainRuleViolation::with(['amount' => __('finance.errors.amount_positive')]);
        }

        if (filled($data['vendor_id'] ?? null) === filled($data['vendor_name'] ?? null)) {
            throw DomainRuleViolation::with(['vendor_name' => __('finance.errors.vendor_one')]);
        }

        $items = array_values(array_map(fn (array $item): array => [
            'description' => (string) $item['description'],
            'amount_minor' => Money::of((string) $item['amount'], $budget->currency)->getMinorAmount()->toInt(),
        ], $data['items']));

        if (array_sum(array_column($items, 'amount_minor')) !== $amount->getMinorAmount()->toInt()) {
            throw DomainRuleViolation::with(['items' => __('finance.errors.items_total')]);
        }

        return DB::transaction(function () use ($team, $data, $budget, $amount, $items, $receipt, $by): Expense {
            $expense = Expense::query()->create([
                'team_id' => $team->getKey(),
                'budget_account_id' => $budget->getKey(),
                'vendor_id' => filled($data['vendor_id'] ?? null) ? (int) $data['vendor_id'] : null,
                'vendor_name' => $data['vendor_name'] ?? null,
                'date' => $data['date'],
                'amount_minor' => $amount->getMinorAmount()->toInt(),
                'currency' => $budget->currency,
                'items' => $items,
                'status' => DocumentStatus::Draft,
                'created_by' => $by->getKey(),
            ]);
            $expense->addMedia($receipt)->toMediaCollection('receipt');

            return $expense;
        });
    }

    public function submit(Expense $expense, User $by): void
    {
        $this->assertStatus($expense, DocumentStatus::Draft);

        $expense->forceFill(['status' => DocumentStatus::Submitted, 'submitted_by' => $by->getKey()])->save();
        $this->log($expense, $by, 'finance.expense.submitted');
    }

    public function approve(Expense $expense, User $by): void
    {
        $this->assertStatus($expense, DocumentStatus::Submitted);

        if (! app(DepartmentAccess::class)->can($by, 'finance.approve')) {
            throw DomainRuleViolation::with(['approver' => __('finance.errors.not_approver')]);
        }

        if (in_array($by->getKey(), [$expense->created_by, $expense->submitted_by], true)) {
            throw DomainRuleViolation::with(['approver' => __('finance.errors.maker_checker')]);
        }

        DB::transaction(function () use ($expense, $by): void {
            $expense->loadMissing('vendor');
            $maker = User::query()->find($expense->submitted_by ?? $expense->created_by) ?? $by;
            $payee = $expense->vendor instanceof Vendor
                ? $this->accounts->external(AccountType::ExternalVendor, $expense->vendor->name, $expense->currency, $expense->vendor)
                : $this->accounts->external(AccountType::ExternalOther, (string) $expense->vendor_name, $expense->currency);

            $transaction = $this->ledger->draft([
                'from_account_id' => $expense->budget_account_id,
                'to_account_id' => $payee->getKey(),
                'amount' => $expense->amount,
                'date' => $expense->date->toDateString(),
                'type' => TransactionType::Expense,
                'description' => __('finance.expense_at', ['vendor' => $expense->payee()]),
                'source' => $expense,
            ], $maker);
            $this->ledger->submit($transaction, $maker);
            $this->ledger->post($transaction, $by);

            $expense->forceFill([
                'status' => DocumentStatus::Posted,
                'approved_by' => $by->getKey(),
                'transaction_id' => $transaction->getKey(),
            ])->save();

            $this->log($expense, $by, 'finance.expense.approved');
        });
    }

    public function reject(Expense $expense, string $reason, User $by): void
    {
        $this->assertStatus($expense, DocumentStatus::Submitted);

        if (! app(DepartmentAccess::class)->can($by, 'finance.edit')) {
            throw DomainRuleViolation::with(['approver' => __('finance.errors.not_approver')]);
        }

        $expense->forceFill(['status' => DocumentStatus::Rejected, 'rejection_reason' => $reason])->save();
        $this->log($expense, $by, 'finance.expense.rejected');
    }

    /**
     * Budgets this team may spend from: its own, and its projects'.
     *
     * @return list<FinanceAccount>
     */
    public function budgetsOf(Team $team): array
    {
        $projectIds = $team->projects()->pluck('id')->all();

        return FinanceAccount::query()
            ->where(fn ($query) => $query
                ->where(fn ($own) => $own->where('type', AccountType::TeamBudget)->where('owner_type', $team->getMorphClass())->where('owner_id', $team->getKey()))
                ->orWhere(fn ($projects) => $projects->where('type', AccountType::ProjectBudget)->where('owner_type', (new Project)->getMorphClass())->whereIn('owner_id', $projectIds)))
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function teamBudget(Team $team, int $accountId): FinanceAccount
    {
        $budget = collect($this->budgetsOf($team))->first(fn (FinanceAccount $account): bool => $account->getKey() === $accountId);

        if ($budget === null) {
            throw DomainRuleViolation::with(['budget_account_id' => __('finance.errors.not_team_budget')]);
        }

        return $budget;
    }

    private function assertCanSpend(Team $team): void
    {
        if ($team->status !== TeamStatus::Active) {
            throw DomainRuleViolation::with(['team' => __('finance.errors.budget_team_inactive')]);
        }

        $this->features->assertActive($team, TeamFeature::BudgetRequest);
    }

    private function assertStatus(Expense $expense, DocumentStatus $status): void
    {
        if ($expense->status !== $status) {
            throw DomainRuleViolation::with(['status' => __('finance.errors.wrong_status')]);
        }
    }

    private function log(Expense $expense, User $by, string $event): void
    {
        activity('finance')->performedOn($expense)->causedBy($by)
            ->withProperties(['team_id' => $expense->team_id, 'amount_minor' => $expense->amount_minor, 'currency' => $expense->currency])
            ->event($event)
            ->log(ucfirst(str_replace(['finance.expense.', '_'], ['Expense ', ' '], $event)));
    }
}
