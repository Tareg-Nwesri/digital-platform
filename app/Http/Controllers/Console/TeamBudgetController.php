<?php

namespace App\Http\Controllers\Console;

use App\Domain\Finance\Enums\DocumentStatus;
use App\Domain\Finance\Enums\RequestStatus;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\FundingRequest;
use App\Domain\Finance\Services\ExpenseService;
use App\Domain\Finance\Services\FundingRequestService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\BudgetController;
use App\Http\Requests\Console\ReviewBudgetDocumentRequest;
use App\Support\Money\MoneyFormatter;
use Brick\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * LLD 07 §5: finance reviews team funding requests and expenses. Approving
 * posts to the ledger and sits behind password confirmation.
 */
class TeamBudgetController extends Controller
{
    public function __construct(
        private readonly FundingRequestService $requests,
        private readonly ExpenseService $expenses,
    ) {}

    public function index(Request $request): Response
    {
        return $this->page($request, confirmed: false);
    }

    /**
     * The same page after password confirmation, where approving is possible.
     */
    public function review(Request $request): Response
    {
        Gate::authorize('approve-finance');

        return $this->page($request, confirmed: true);
    }

    private function page(Request $request, bool $confirmed): Response
    {
        Gate::authorize('view-finance');
        $userId = $request->user()->getKey();

        return Inertia::render('console/finance/TeamBudgets', [
            'requests' => FundingRequest::query()->with(['team', 'project', 'source', 'requester'])
                ->orderByRaw('status = ? desc', [RequestStatus::Submitted->value])
                ->latest('id')->limit(100)->get()
                ->map(fn (FundingRequest $funding): array => [
                    'id' => $funding->getKey(),
                    'team' => $funding->team->name,
                    'project' => $funding->project?->localized('title'),
                    'status' => $funding->status->value,
                    'amount' => MoneyFormatter::format($funding->amount),
                    'amountValue' => (string) $funding->amount->getAmount(),
                    'approved' => ($approved = $funding->approvedAmount()) === null ? null : MoneyFormatter::format($approved),
                    'purpose' => $funding->purpose,
                    'breakdown' => array_map(fn (array $line): array => [
                        'label' => $line['label'],
                        'amount' => MoneyFormatter::format(Money::ofMinor($line['amount_minor'], $funding->currency)),
                    ], $funding->breakdown ?? []),
                    'source' => (string) $funding->source->localized('name'),
                    'requestedBy' => $funding->requester?->name,
                    'note' => $funding->review_note,
                    'mine' => in_array($userId, [$funding->requested_by, $funding->submitted_by], true),
                    'createdAt' => $funding->created_at?->toIso8601String(),
                ])->all(),
            'expenses' => Expense::query()->with(['team', 'vendor', 'budget', 'creator'])
                ->orderByRaw('status = ? desc', [DocumentStatus::Submitted->value])
                ->latest('id')->limit(100)->get()
                ->map(fn (Expense $expense): array => [
                    'id' => $expense->getKey(),
                    'team' => $expense->team?->name,
                    'status' => $expense->status->value,
                    'amount' => MoneyFormatter::format($expense->amount),
                    'vendor' => $expense->payee(),
                    'budget' => (string) $expense->budget->localized('name'),
                    'date' => $expense->date->toDateString(),
                    'items' => array_map(fn (array $item): array => [
                        'description' => $item['description'],
                        'amount' => MoneyFormatter::format(Money::ofMinor($item['amount_minor'], $expense->currency)),
                    ], $expense->items),
                    'createdBy' => $expense->creator?->name,
                    'reason' => $expense->rejection_reason,
                    'mine' => in_array($userId, [$expense->created_by, $expense->submitted_by], true),
                ])->all(),
            'can' => [
                'edit' => $request->user()->can('edit-finance'),
                'approve' => $request->user()->can('approve-finance'),
            ],
            'confirmed' => $confirmed,
        ]);
    }

    public function approveRequest(ReviewBudgetDocumentRequest $request, FundingRequest $funding): RedirectResponse
    {
        $this->requests->approve($funding, $request->validated('amount'), $request->user(), $request->validated('note'));

        return $this->done('request_approved');
    }

    public function rejectRequest(ReviewBudgetDocumentRequest $request, FundingRequest $funding): RedirectResponse
    {
        $this->requests->reject($funding, $request->validated('reason'), $request->user());

        return $this->done('request_rejected');
    }

    public function changesRequest(ReviewBudgetDocumentRequest $request, FundingRequest $funding): RedirectResponse
    {
        $this->requests->requestChanges($funding, $request->validated('note'), $request->user());

        return $this->done('request_returned');
    }

    public function approveExpense(ReviewBudgetDocumentRequest $request, Expense $expense): RedirectResponse
    {
        $this->expenses->approve($expense, $request->user());

        return $this->done('expense_approved');
    }

    public function rejectExpense(ReviewBudgetDocumentRequest $request, Expense $expense): RedirectResponse
    {
        $this->expenses->reject($expense, $request->validated('reason'), $request->user());

        return $this->done('expense_rejected');
    }

    public function receipt(Expense $expense): StreamedResponse
    {
        Gate::authorize('view-finance');

        return BudgetController::download($expense);
    }

    private function done(string $key): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('finance.flash.'.$key)]);

        return back();
    }
}
