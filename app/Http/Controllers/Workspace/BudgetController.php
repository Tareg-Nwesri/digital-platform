<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Finance\Enums\TransactionStatus;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\FundingRequest;
use App\Domain\Finance\Models\LedgerTransaction;
use App\Domain\Finance\Services\ExpenseService;
use App\Domain\Finance\Services\FundingRequestService;
use App\Domain\Finance\Services\LedgerService;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Work\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\BudgetDocumentRequest;
use App\Support\Enums\Currency;
use App\Support\Money\MoneyFormatter;
use Brick\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * LLD 07 §5/§7: the team's budgets, funding requests and expenses
 * (captain and coach).
 */
class BudgetController extends Controller
{
    public function __construct(
        private readonly FundingRequestService $requests,
        private readonly ExpenseService $expenses,
        private readonly LedgerService $ledger,
        private readonly TeamFeatures $features,
    ) {}

    public function index(Request $request, Team $team): Response
    {
        abort_unless($request->user()->can('budget.manage'), 403);
        $this->features->assertActive($team, TeamFeature::BudgetRequest);

        return Inertia::render('workspace/budget/Index', [
            'team' => ['slug' => $team->slug, 'name' => $team->name],
            'budgets' => array_map(fn (FinanceAccount $account): array => $this->budgetRow($account), $this->expenses->budgetsOf($team)),
            'requests' => FundingRequest::query()->where('team_id', $team->getKey())->with(['project', 'source'])->latest('id')->limit(50)->get()
                ->map(fn (FundingRequest $funding): array => [
                    'id' => $funding->getKey(),
                    'status' => $funding->status->value,
                    'amount' => MoneyFormatter::format($funding->amount),
                    'amountValue' => (string) $funding->amount->getAmount(),
                    'currency' => $funding->currency,
                    'approved' => ($approved = $funding->approvedAmount()) === null ? null : MoneyFormatter::format($approved),
                    'purpose' => $funding->purpose,
                    'project' => $funding->project?->localized('title'),
                    'projectId' => $funding->project_id,
                    'source' => (string) $funding->source->localized('name'),
                    'sourceId' => $funding->source_account_id,
                    'breakdown' => array_map(fn (array $line): array => [
                        'label' => $line['label'],
                        'amount' => (string) Money::ofMinor($line['amount_minor'], $funding->currency)->getAmount(),
                    ], $funding->breakdown ?? []),
                    'note' => $funding->review_note,
                    'createdAt' => $funding->created_at?->toIso8601String(),
                ])->all(),
            'expenses' => Expense::query()->where('team_id', $team->getKey())->with(['vendor', 'budget'])->latest('id')->limit(50)->get()
                ->map(fn (Expense $expense): array => [
                    'id' => $expense->getKey(),
                    'status' => $expense->status->value,
                    'amount' => MoneyFormatter::format($expense->amount),
                    'vendor' => $expense->payee(),
                    'budget' => (string) $expense->budget->localized('name'),
                    'date' => $expense->date->toDateString(),
                    'items' => count($expense->items),
                    'reason' => $expense->rejection_reason,
                ])->all(),
            'sources' => array_map(fn (FinanceAccount $source): array => [
                'id' => $source->getKey(),
                'name' => (string) $source->localized('name'),
                'currency' => $source->currency,
            ], $this->requests->sources()),
            'projects' => $team->projects()->orderBy('title_en')->get()
                ->map(fn (Project $project): array => ['id' => $project->getKey(), 'title' => (string) $project->localized('title')])->all(),
            'vendors' => Vendor::query()->approved()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Vendor $vendor): array => ['id' => $vendor->getKey(), 'name' => $vendor->name])->all(),
            'currencies' => Currency::values(),
            'can' => ['request' => true, 'spend' => $this->expenses->budgetsOf($team) !== []],
        ]);
    }

    public function storeRequest(BudgetDocumentRequest $request, Team $team): RedirectResponse
    {
        $funding = $this->requests->draft($team, $request->validated(), $request->user());
        $this->requests->submit($funding, $request->user());

        return $this->done('request_sent');
    }

    public function updateRequest(BudgetDocumentRequest $request, Team $team, FundingRequest $funding): RedirectResponse
    {
        $this->assertTeam($team, $funding->team_id);
        $this->requests->update($funding, $request->validated(), $request->user());
        $this->requests->submit($funding->fresh(), $request->user());

        return $this->done('request_sent');
    }

    public function cancelRequest(Request $request, Team $team, FundingRequest $funding): RedirectResponse
    {
        abort_unless($request->user()->can('budget.manage'), 403);
        $this->assertTeam($team, $funding->team_id);
        $this->requests->cancel($funding, $request->user());

        return $this->done('request_cancelled');
    }

    public function storeExpense(BudgetDocumentRequest $request, Team $team): RedirectResponse
    {
        $expense = $this->expenses->draft($team, $request->safe()->except('receipt'), $request->file('receipt'), $request->user());
        $this->expenses->submit($expense, $request->user());

        return $this->done('expense_sent');
    }

    public function receipt(Request $request, Team $team, Expense $expense): StreamedResponse
    {
        abort_unless($request->user()->can('budget.manage'), 403);
        $this->assertTeam($team, $expense->team_id);

        return self::download($expense);
    }

    public static function download(Expense $expense): StreamedResponse
    {
        $receipt = $expense->getFirstMedia('receipt');
        abort_if($receipt === null, 404);

        return response()->streamDownload(function () use ($receipt): void {
            $stream = $receipt->stream();
            fpassthru($stream);
            fclose($stream);
        }, $receipt->file_name, ['Content-Type' => $receipt->mime_type]);
    }

    /**
     * @return array{id: int, name: string, balance: string, funded: string, spent: string}
     */
    private function budgetRow(FinanceAccount $account): array
    {
        $posted = fn (string $column): int => (int) LedgerTransaction::query()
            ->where($column, $account->getKey())
            ->where('status', TransactionStatus::Posted)
            ->sum('amount_minor');

        return [
            'id' => $account->getKey(),
            'name' => (string) $account->localized('name'),
            'currency' => $account->currency,
            'balance' => MoneyFormatter::format($this->ledger->balance($account)),
            'funded' => MoneyFormatter::format(Money::ofMinor($posted('to_account_id'), $account->currency)),
            'spent' => MoneyFormatter::format(Money::ofMinor($posted('from_account_id'), $account->currency)),
        ];
    }

    private function assertTeam(Team $team, ?int $teamId): void
    {
        abort_unless($teamId === $team->getKey(), 404);
    }

    private function done(string $key): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('finance.flash.'.$key)]);

        return back();
    }
}
