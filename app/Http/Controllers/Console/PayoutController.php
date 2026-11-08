<?php

namespace App\Http\Controllers\Console;

use App\Domain\Finance\Enums\PaymentMethod;
use App\Domain\Finance\Enums\PayoutStatus;
use App\Domain\Finance\Enums\PayoutType;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\Payout;
use App\Domain\Finance\Services\PayoutService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\FinanceReasonRequest;
use App\Http\Requests\Console\MarkPayoutPaidRequest;
use App\Http\Requests\Console\PayoutRequest;
use App\Models\User;
use App\Support\Enums\Currency;
use App\Support\Money\MoneyFormatter;
use App\Support\Tables\TableQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 07 §5/§7: payouts. Decisions (approve, reject, mark paid) happen on
 * the password-protected approvals page.
 */
class PayoutController extends Controller
{
    public function __construct(private readonly PayoutService $payouts) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view-finance');

        $payouts = TableQuery::for(Payout::query()->with(['payee', 'source']), $request)
            ->searchable(['reason', 'reference'])
            ->sortable(['created_at', 'paid_on'], default: 'created_at', direction: 'desc')
            ->filters([
                'status' => fn (Builder $query, mixed $value) => $query->where('status', (string) $value),
                'type' => fn (Builder $query, mixed $value) => $query->where('type', (string) $value),
            ])
            ->paginate(fn (Payout $payout): array => self::row($payout));

        return Inertia::render('console/finance/Payouts', [
            'payouts' => $payouts,
            'statuses' => array_column(PayoutStatus::cases(), 'value'),
            'types' => array_column(PayoutType::cases(), 'value'),
            'currencies' => Currency::values(),
            'sources' => fn (): array => FinanceAccount::query()->internal()->where('is_archived', false)->orderBy('type')->orderBy('name_en')->get()
                ->map(fn (FinanceAccount $account): array => ['id' => $account->getKey(), 'name' => (string) $account->localized('name'), 'currency' => $account->currency])
                ->all(),
            'people' => fn (): array => User::query()->where('is_active', true)->orderBy('name_en')->limit(1000)->get(['id', 'name_en', 'name_ar', 'birthdate'])
                ->map(fn (User $user): array => ['id' => $user->getKey(), 'name' => $user->name, 'minor' => ($user->ageOn(now()) ?? 99) < 18])
                ->all(),
            'can' => ['edit' => $request->user()->can('edit-finance')],
        ]);
    }

    public function store(PayoutRequest $request): RedirectResponse
    {
        $this->payouts->draft($request->validated(), $request->user());

        return $this->done('payout_saved');
    }

    public function submit(Request $request, Payout $payout): RedirectResponse
    {
        Gate::authorize('edit-finance');
        $this->payouts->submit($payout, $request->user());

        return $this->done('payout_submitted');
    }

    public function cancel(Request $request, Payout $payout): RedirectResponse
    {
        Gate::authorize('edit-finance');
        $this->payouts->cancel($payout, $request->user());

        return $this->done('payout_cancelled');
    }

    public function approve(Request $request, Payout $payout): RedirectResponse
    {
        Gate::authorize('approve-finance');
        $this->payouts->approve($payout, $request->user());

        return $this->done('payout_approved');
    }

    public function reject(FinanceReasonRequest $request, Payout $payout): RedirectResponse
    {
        $this->payouts->reject($payout, (string) $request->validated('reason'), $request->user());

        return $this->done('payout_rejected');
    }

    public function paid(MarkPayoutPaidRequest $request, Payout $payout): RedirectResponse
    {
        $this->payouts->markPaid($payout, $request->validated(), $request->user());

        return $this->done('payout_paid');
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Payout $payout): array
    {
        return [
            'id' => $payout->getKey(),
            'payee' => $payout->payee->name,
            'type' => $payout->type->value,
            'amount' => MoneyFormatter::toArray($payout->amount),
            'source' => (string) $payout->source->localized('name'),
            'reason' => $payout->reason,
            'status' => $payout->status->value,
            'needsSecondApproval' => $payout->needs_second_approval,
            'guardian' => $payout->guardian_name,
            'reference' => $payout->reference,
            'paidOn' => $payout->paid_on?->toDateString(),
            'rejectionReason' => $payout->rejection_reason,
        ];
    }

    /**
     * Methods offered when marking a payout paid.
     *
     * @return list<string>
     */
    public static function paymentMethods(): array
    {
        return array_values(array_filter(array_column(PaymentMethod::cases(), 'value'), fn (string $method): bool => $method !== PaymentMethod::InKind->value));
    }

    private function done(string $key): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('finance.flash.'.$key)]);

        return back();
    }
}
