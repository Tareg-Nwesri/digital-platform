<?php

namespace App\Http\Controllers\Console;

use App\Domain\Finance\Enums\TransactionStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\LedgerTransaction;
use App\Domain\Finance\Services\LedgerService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\FinanceReasonRequest;
use App\Http\Requests\Console\TransactionRequest;
use App\Support\Money\MoneyFormatter;
use App\Support\Tables\TableQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 07 §5: ledger transactions in the console.
 */
class TransactionController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view-finance');

        $transactions = TableQuery::for(LedgerTransaction::query()->with(['from', 'to']), $request)
            ->searchable(['number', 'description', 'reference'])
            ->sortable(['date', 'created_at'], default: 'date', direction: 'desc')
            ->filters([
                'status' => fn (Builder $query, mixed $value) => $value === 'reversed'
                    ? $query->whereNotNull('reversed_by_id')
                    : $query->where('status', (string) $value),
                'type' => fn (Builder $query, mixed $value) => $query->where('type', (string) $value),
                'account' => fn (Builder $query, mixed $value) => $query->where(fn ($match) => $match
                    ->where('from_account_id', (int) $value)->orWhere('to_account_id', (int) $value)),
            ])
            ->paginate(fn (LedgerTransaction $transaction): array => self::row($transaction));

        return Inertia::render('console/finance/Transactions', [
            'transactions' => $transactions,
            'statuses' => [...array_column(TransactionStatus::cases(), 'value'), 'reversed'],
            'types' => array_column(TransactionType::cases(), 'value'),
            'accounts' => FinanceAccount::query()->where('is_archived', false)->orderBy('type')->orderBy('name_en')->get()
                ->map(fn (FinanceAccount $account): array => [
                    'id' => $account->getKey(),
                    'name' => (string) $account->localized('name'),
                    'currency' => $account->currency,
                    'internal' => $account->type->isInternal(),
                ])
                ->all(),
            'can' => [
                'edit' => $request->user()->can('edit-finance'),
                'approve' => $request->user()->can('approve-finance'),
            ],
        ]);
    }

    public function store(TransactionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->ledger->draft([
            'from_account_id' => (int) $data['from_account_id'],
            'to_account_id' => (int) $data['to_account_id'],
            'amount' => $request->amount(),
            'date' => $data['date'],
            'type' => TransactionType::from($data['type'] ?? TransactionType::Adjustment->value),
            'description' => $data['description'],
            'reference' => $data['reference'] ?? null,
            'rate_to_lyd' => $data['rate_to_lyd'] ?? null,
        ], $request->user());

        return $this->done('drafted');
    }

    public function submit(Request $request, LedgerTransaction $transaction): RedirectResponse
    {
        Gate::authorize('edit-finance');

        $this->ledger->submit($transaction, $request->user());

        return $this->done('submitted');
    }

    public function approve(FinanceReasonRequest $request, LedgerTransaction $transaction): RedirectResponse
    {
        $this->ledger->post($transaction, $request->user(), $request->validated('reason'));

        return $this->done('posted');
    }

    public function reject(FinanceReasonRequest $request, LedgerTransaction $transaction): RedirectResponse
    {
        $this->ledger->reject($transaction, $request->validated('reason'), $request->user());

        return $this->done('rejected');
    }

    public function reverse(FinanceReasonRequest $request, LedgerTransaction $transaction): RedirectResponse
    {
        $this->ledger->reverse($transaction, $request->validated('reason'), $request->user());

        return $this->done('reversed');
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(LedgerTransaction $transaction): array
    {
        return [
            'id' => $transaction->getKey(),
            'number' => $transaction->number,
            'date' => $transaction->date->toDateString(),
            'description' => $transaction->description,
            'type' => $transaction->type->value,
            'status' => $transaction->isReversed() ? 'reversed' : $transaction->status->value,
            'from' => (string) $transaction->from->localized('name'),
            'to' => (string) $transaction->to->localized('name'),
            'amount' => MoneyFormatter::toArray($transaction->amount),
            'overrideReason' => $transaction->override_reason,
            'rejectionReason' => $transaction->rejection_reason,
        ];
    }

    private function done(string $key): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('finance.flash.'.$key)]);

        return back();
    }
}
