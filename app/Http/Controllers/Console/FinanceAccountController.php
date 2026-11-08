<?php

namespace App\Http\Controllers\Console;

use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Enums\TransactionStatus;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\LedgerTransaction;
use App\Domain\Finance\Services\FinanceAccounts;
use App\Domain\Finance\Services\LedgerService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\FinanceAccountRequest;
use App\Support\Enums\Currency;
use App\Support\Money\MoneyFormatter;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 07 §5/§7: accounts and their statements.
 */
class FinanceAccountController extends Controller
{
    private const STATEMENT_LIMIT = 500;

    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view-finance');

        return Inertia::render('console/finance/Accounts', [
            'accounts' => FinanceAccount::query()->orderBy('type')->orderBy('name_en')->get()
                ->map(fn (FinanceAccount $account): array => [
                    ...FinanceController::accountRowFor($account),
                    'internal' => $account->type->isInternal(),
                    'archived' => $account->is_archived,
                ])
                ->all(),
            'creatable' => array_map(fn (AccountType $type): string => $type->value, FinanceAccountRequest::CREATABLE),
            'currencies' => Currency::values(),
            'can' => ['edit' => $request->user()->can('edit-finance')],
        ]);
    }

    public function store(FinanceAccountRequest $request, FinanceAccounts $accounts): RedirectResponse
    {
        $data = $request->validated();

        $account = $accounts->create(
            AccountType::from($data['type']),
            $data['name_en'],
            $data['name_ar'] ?? null,
            $data['currency'],
            $data['purpose'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('finance.flash.account_created')]);

        return to_route('console.finance.accounts.show', $account);
    }

    /**
     * A statement: opening balance, posted movements in the range with a
     * running balance, closing balance.
     */
    public function show(Request $request, FinanceAccount $account): Response
    {
        Gate::authorize('view-finance');

        $from = $this->date($request->query('from'), now()->startOfMonth());
        $to = $this->date($request->query('to'), now());

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        $opening = $this->ledger->balance($account, $from->copy()->subDay());
        $running = $opening;

        $rows = LedgerTransaction::query()
            ->where('status', TransactionStatus::Posted)
            ->where(fn ($query) => $query->where('from_account_id', $account->getKey())->orWhere('to_account_id', $account->getKey()))
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->with(['from', 'to'])
            ->orderBy('date')
            ->orderBy('id')
            ->limit(self::STATEMENT_LIMIT)
            ->get()
            ->map(function (LedgerTransaction $transaction) use ($account, &$running): array {
                $incoming = $transaction->to_account_id === $account->getKey();
                $running = $incoming ? $running->plus($transaction->amount) : $running->minus($transaction->amount);
                $counterpart = $incoming ? $transaction->from : $transaction->to;

                return [
                    'id' => $transaction->getKey(),
                    'number' => $transaction->number,
                    'date' => $transaction->date->toDateString(),
                    'description' => $transaction->description,
                    'type' => $transaction->type->value,
                    'counterpart' => (string) $counterpart->localized('name'),
                    'direction' => $incoming ? 'in' : 'out',
                    'amount' => MoneyFormatter::toArray($transaction->amount),
                    'running' => MoneyFormatter::toArray($running),
                    'reversed' => $transaction->isReversed(),
                ];
            })
            ->all();

        return Inertia::render('console/finance/AccountShow', [
            'account' => [
                ...FinanceController::accountRowFor($account),
                'internal' => $account->type->isInternal(),
                'purpose' => $account->purpose,
                'archived' => $account->is_archived,
            ],
            'statement' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'opening' => MoneyFormatter::toArray($opening),
                'rows' => $rows,
                'closing' => MoneyFormatter::toArray($running),
                'truncated' => count($rows) === self::STATEMENT_LIMIT,
            ],
        ]);
    }

    private function date(mixed $value, CarbonInterface $fallback): CarbonInterface
    {
        return is_string($value) && strtotime($value) !== false ? Carbon::parse($value)->startOfDay() : $fallback->copy()->startOfDay();
    }
}
