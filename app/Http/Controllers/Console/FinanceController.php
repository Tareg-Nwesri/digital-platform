<?php

namespace App\Http\Controllers\Console;

use App\Domain\Finance\Enums\DocumentStatus;
use App\Domain\Finance\Enums\TransactionStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\FiscalPeriod;
use App\Domain\Finance\Models\LedgerTransaction;
use App\Domain\Finance\Models\Receipt;
use App\Domain\Finance\Services\LedgerService;
use App\Http\Controllers\Controller;
use App\Support\Money\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 07 §5/§7: the finance dashboard, the approvals queue and periods.
 */
class FinanceController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function dashboard(Request $request): Response
    {
        Gate::authorize('view-finance');

        $user = $request->user();

        return Inertia::render('console/finance/Dashboard', [
            'internal' => FinanceAccount::query()->internal()->where('is_archived', false)->orderBy('type')->orderBy('name_en')->get()
                ->map(fn (FinanceAccount $account): array => self::accountRowFor($account))
                ->all(),
            'pending' => [
                'transactions' => LedgerTransaction::query()->where('status', TransactionStatus::Submitted)->count(),
                'drafts' => LedgerTransaction::query()->where('status', TransactionStatus::Draft)->count(),
            ],
            'recent' => LedgerTransaction::query()->where('status', TransactionStatus::Posted)->with(['from', 'to'])
                ->latest('posted_at')->latest('id')->limit(8)->get()
                ->map(fn (LedgerTransaction $transaction): array => TransactionController::row($transaction))
                ->all(),
            'mismatches' => fn (): int => count($this->ledger->verifyAll()),
            'can' => [
                'edit' => $user->can('edit-finance'),
                'approve' => $user->can('approve-finance'),
            ],
        ]);
    }

    /**
     * Submitted transactions waiting for a decision. Reaching this page asks
     * for the password, so the approve actions that follow pass too.
     */
    public function approvals(Request $request): Response
    {
        Gate::authorize('approve-finance');

        $userId = $request->user()->getKey();

        return Inertia::render('console/finance/Approvals', [
            'transactions' => LedgerTransaction::query()->where('status', TransactionStatus::Submitted)
                ->where(fn ($query) => $query->whereNull('source_type')->orWhere('source_type', '!=', (new Receipt)->getMorphClass()))
                ->with(['from', 'to', 'creator'])->oldest('submitted_at')->get()
                ->map(fn (LedgerTransaction $transaction): array => [
                    ...TransactionController::row($transaction),
                    'createdBy' => $transaction->creator?->name,
                    'reference' => $transaction->reference,
                    'fromBalance' => MoneyFormatter::toArray($transaction->from->balance()),
                    'fromIsInternal' => $transaction->from->type->isInternal(),
                    'isOwn' => in_array($userId, [$transaction->created_by, $transaction->submitted_by], true),
                ])
                ->all(),
            'receipts' => Receipt::query()->where('status', DocumentStatus::Submitted)
                ->with(['payer', 'sponsorship', 'creator', 'transactions.to'])->oldest('date')->get()
                ->map(fn (Receipt $receipt): array => [
                    ...ReceiptController::row($receipt),
                    'createdBy' => $receipt->creator?->name,
                    'isOwn' => $receipt->created_by === $userId,
                    'lines' => $receipt->transactions->map(fn (LedgerTransaction $line): array => [
                        'account' => (string) $line->to->localized('name'),
                        'amount' => MoneyFormatter::toArray($line->amount),
                    ])->all(),
                ])
                ->all(),
            'reversible' => LedgerTransaction::query()
                ->where('status', TransactionStatus::Posted)
                ->whereNull('reversed_by_id')
                ->where('type', '!=', TransactionType::Reversal)
                ->with(['from', 'to'])
                ->latest('posted_at')->latest('id')->limit(25)->get()
                ->map(fn (LedgerTransaction $transaction): array => TransactionController::row($transaction))
                ->all(),
        ]);
    }

    public function periods(Request $request): Response
    {
        Gate::authorize('view-finance');

        return Inertia::render('console/finance/Periods', [
            'periods' => FiscalPeriod::query()->orderByDesc('starts_on')->get()
                ->map(fn (FiscalPeriod $period): array => [
                    'id' => $period->getKey(),
                    'startsOn' => $period->starts_on->toDateString(),
                    'endsOn' => $period->ends_on->toDateString(),
                    'closedAt' => $period->closed_at?->toIso8601String(),
                    'posted' => LedgerTransaction::query()->where('fiscal_period_id', $period->getKey())->count(),
                ])
                ->all(),
            'can' => ['close' => $request->user()->can('approve-finance')],
        ]);
    }

    public function closePeriod(Request $request, FiscalPeriod $period): RedirectResponse
    {
        Gate::authorize('approve-finance');

        $this->ledger->closePeriod($period, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('finance.flash.period_closed')]);

        return back();
    }

    /**
     * @return array{id: int, name: string, type: string, currency: string, restricted: bool, balance: array{amountMinor: int, currency: string, formatted: string}}
     */
    public static function accountRowFor(FinanceAccount $account): array
    {
        return [
            'id' => $account->getKey(),
            'name' => (string) $account->localized('name'),
            'type' => $account->type->value,
            'currency' => $account->currency,
            'restricted' => $account->is_restricted,
            'balance' => MoneyFormatter::toArray($account->balance()),
        ];
    }
}
