<?php

namespace App\Http\Controllers\Console;

use App\Domain\Finance\Models\Receipt;
use App\Domain\Finance\Models\SplitRule;
use App\Domain\Finance\Models\Sponsor;
use App\Domain\Finance\Models\Sponsorship;
use App\Domain\Finance\Services\SponsorshipService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\SponsorshipRequest;
use App\Support\Money\MoneyFormatter;
use Brick\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 07 §5/§7: sponsorships, their split rules and receipts.
 */
class SponsorshipController extends Controller
{
    public function __construct(private readonly SponsorshipService $sponsorships) {}

    public function store(SponsorshipRequest $request, Sponsor $sponsor): RedirectResponse
    {
        $sponsorship = $this->sponsorships->save($sponsor, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('finance.flash.sponsorship_saved')]);

        return to_route('console.finance.sponsorships.show', $sponsorship);
    }

    public function show(Request $request, Sponsorship $sponsorship): Response
    {
        Gate::authorize('view-finance');

        $sponsorship->loadMissing(['sponsor', 'splitRules.account', 'defaultAccount']);

        return Inertia::render('console/finance/SponsorshipShow', [
            'sponsorship' => [
                'id' => $sponsorship->getKey(),
                'title' => $sponsorship->title,
                'kind' => $sponsorship->kind->value,
                'status' => $sponsorship->status->value,
                'currency' => $sponsorship->currency,
                'pledged' => (string) $sponsorship->pledged()->getAmount(),
                'startsOn' => $sponsorship->starts_on?->toDateString(),
                'endsOn' => $sponsorship->ends_on?->toDateString(),
                'purpose' => $sponsorship->purpose,
                'restrictions' => $sponsorship->restrictions,
                'defaultAccountId' => $sponsorship->default_account_id,
                'defaultAccount' => $sponsorship->defaultAccount?->localized('name'),
                'sponsor' => ['id' => $sponsorship->sponsor->getKey(), 'name' => (string) $sponsorship->sponsor->localized('name')],
                'rules' => $sponsorship->splitRules->map(fn (SplitRule $rule): array => [
                    'accountId' => $rule->account_id,
                    'account' => (string) $rule->account->localized('name'),
                    'percent' => $rule->percent,
                    'fixed' => $rule->fixed_minor === null ? null : (string) Money::ofMinor($rule->fixed_minor, $sponsorship->currency)->getAmount(),
                ])->all(),
            ],
            'totals' => [
                'pledged' => MoneyFormatter::toArray($sponsorship->pledged()),
                'received' => MoneyFormatter::toArray($sponsorship->received()),
                'outstanding' => MoneyFormatter::toArray($sponsorship->outstanding()),
            ],
            'receipts' => $sponsorship->receipts()->latest('date')->latest('id')->get()
                ->map(fn (Receipt $receipt): array => ReceiptController::row($receipt))
                ->all(),
            'options' => fn (): array => SponsorController::sponsorshipOptions(),
            'can' => ['edit' => $request->user()->can('edit-finance')],
        ]);
    }

    public function update(SponsorshipRequest $request, Sponsorship $sponsorship): RedirectResponse
    {
        $this->sponsorships->save($sponsorship->sponsor, $request->validated(), $request->user(), $sponsorship);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('finance.flash.sponsorship_saved')]);

        return back();
    }
}
