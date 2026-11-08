<?php

namespace App\Http\Controllers\Console;

use App\Domain\Finance\Enums\DocumentStatus;
use App\Domain\Finance\Enums\IncomeSource;
use App\Domain\Finance\Enums\PaymentMethod;
use App\Domain\Finance\Enums\SponsorshipStatus;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\LedgerTransaction;
use App\Domain\Finance\Models\Receipt;
use App\Domain\Finance\Models\Sponsorship;
use App\Domain\Finance\Services\ReceiptService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\FinanceReasonRequest;
use App\Http\Requests\Console\ReceiptRequest;
use App\Http\Requests\Console\SubmitReceiptRequest;
use App\Support\Enums\Currency;
use App\Support\Money\MoneyFormatter;
use App\Support\Tables\TableQuery;
use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 07 §5/§7: receipts, their split and their approval.
 */
class ReceiptController extends Controller
{
    public function __construct(private readonly ReceiptService $receipts) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view-finance');

        $receipts = TableQuery::for(Receipt::query()->with(['payer', 'sponsorship']), $request)
            ->searchable(['number', 'reference'])
            ->sortable(['date', 'created_at'], default: 'date', direction: 'desc')
            ->filters([
                'status' => fn (Builder $query, mixed $value) => $query->where('status', (string) $value),
                'source' => fn (Builder $query, mixed $value) => $query->where('source', (string) $value),
            ])
            ->paginate(fn (Receipt $receipt): array => self::row($receipt));

        return Inertia::render('console/finance/Receipts', [
            'receipts' => $receipts,
            'statuses' => array_column(DocumentStatus::cases(), 'value'),
            'sources' => array_column(IncomeSource::cases(), 'value'),
            'methods' => array_column(PaymentMethod::cases(), 'value'),
            'currencies' => Currency::values(),
            'sponsorships' => Sponsorship::query()->where('status', SponsorshipStatus::Active)->with('sponsor')->orderBy('title')->get()
                ->map(fn (Sponsorship $sponsorship): array => [
                    'id' => $sponsorship->getKey(),
                    'title' => $sponsorship->title,
                    'sponsor' => (string) $sponsorship->sponsor->localized('name'),
                    'currency' => $sponsorship->currency,
                ])
                ->all(),
            'can' => ['edit' => $request->user()->can('edit-finance')],
        ]);
    }

    public function store(ReceiptRequest $request): RedirectResponse
    {
        $receipt = $this->receipts->draft($request->validated(), $request->user());

        return $this->done('receipt_drafted', to_route('console.finance.receipts.show', $receipt));
    }

    public function show(Request $request, Receipt $receipt): Response
    {
        Gate::authorize('view-finance');

        $receipt->loadMissing(['payer', 'sponsorship.sponsor', 'creator', 'transactions.to']);

        return Inertia::render('console/finance/ReceiptShow', [
            'receipt' => [
                ...self::row($receipt),
                'method' => $receipt->method->value,
                'reference' => $receipt->reference,
                'inKindItems' => array_map(fn (array $item): array => [
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'value' => MoneyFormatter::format(Money::ofMinor($item['value_minor'], $receipt->currency)),
                ], $receipt->in_kind_items ?? []),
                'rejectionReason' => $receipt->rejection_reason,
                'createdBy' => $receipt->creator?->name,
                'sponsorshipId' => $receipt->sponsorship_id,
            ],
            'proposal' => $receipt->status === DocumentStatus::Draft
                ? array_map(fn (array $line): array => [
                    'accountId' => $line['account']->getKey(),
                    'account' => (string) $line['account']->localized('name'),
                    'amount' => (string) $line['amount']->getAmount(),
                ], $this->receipts->proposeSplit($receipt))
                : [],
            'lines' => $receipt->transactions->map(fn (LedgerTransaction $transaction): array => [
                'id' => $transaction->getKey(),
                'account' => (string) $transaction->to->localized('name'),
                'amount' => MoneyFormatter::toArray($transaction->amount),
                'status' => $transaction->status->value,
                'number' => $transaction->number,
            ])->all(),
            'accounts' => FinanceAccount::query()->internal()->where('is_archived', false)->where('currency', $receipt->currency)
                ->orderBy('type')->orderBy('name_en')->get()
                ->map(fn (FinanceAccount $account): array => ['id' => $account->getKey(), 'name' => (string) $account->localized('name')])
                ->all(),
            'can' => ['edit' => $request->user()->can('edit-finance')],
        ]);
    }

    public function submit(SubmitReceiptRequest $request, Receipt $receipt): RedirectResponse
    {
        $this->receipts->submit($receipt, $request->lines($receipt), $request->user());

        return $this->done('receipt_submitted', back());
    }

    public function post(Request $request, Receipt $receipt): RedirectResponse
    {
        Gate::authorize('approve-finance');

        $this->receipts->post($receipt, $request->user());

        return $this->done('receipt_posted', back());
    }

    public function reject(FinanceReasonRequest $request, Receipt $receipt): RedirectResponse
    {
        $this->receipts->reject($receipt, (string) $request->validated('reason'), $request->user());

        return $this->done('receipt_rejected', back());
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Receipt $receipt): array
    {
        $receipt->loadMissing(['payer', 'sponsorship']);

        return [
            'id' => $receipt->getKey(),
            'number' => $receipt->number,
            'date' => $receipt->date->toDateString(),
            'source' => $receipt->source->value,
            'payer' => (string) $receipt->payer->localized('name'),
            'sponsorship' => $receipt->sponsorship?->title,
            'amount' => MoneyFormatter::toArray($receipt->amount),
            'status' => $receipt->status->value,
        ];
    }

    private function done(string $key, RedirectResponse $response): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('finance.flash.'.$key)]);

        return $response;
    }
}
