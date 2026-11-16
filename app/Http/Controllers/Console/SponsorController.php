<?php

namespace App\Http\Controllers\Console;

use App\Domain\Finance\Enums\SponsorshipKind;
use App\Domain\Finance\Enums\SponsorshipStatus;
use App\Domain\Finance\Enums\SponsorType;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\Sponsor;
use App\Domain\Finance\Models\SponsorContact;
use App\Domain\Finance\Models\Sponsorship;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\SponsorContactRequest;
use App\Http\Requests\Console\SponsorLogoRequest;
use App\Http\Requests\Console\SponsorRequest;
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
 * LLD 07 §5: sponsors and their contacts.
 */
class SponsorController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('view-finance');

        $sponsors = TableQuery::for(Sponsor::query()->with('media')->withCount('sponsorships'), $request)
            ->searchable(['name_en', 'name_ar'])
            ->sortable(['name_en', 'created_at'], default: 'name_en')
            ->filters([
                'active' => fn (Builder $query, mixed $value) => $query->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN)),
                'type' => fn (Builder $query, mixed $value) => $query->where('type', (string) $value),
            ])
            ->paginate(fn (Sponsor $sponsor): array => [
                'id' => $sponsor->getKey(),
                'name' => (string) $sponsor->localized('name'),
                'logo' => $sponsor->logoUrl(),
                'type' => $sponsor->type->value,
                'isActive' => $sponsor->is_active,
                'publicConsent' => $sponsor->public_consent,
                'sponsorships' => (int) $sponsor->sponsorships_count,
            ]);

        return Inertia::render('console/finance/Sponsors', [
            'sponsors' => $sponsors,
            'types' => array_column(SponsorType::cases(), 'value'),
            'can' => ['edit' => $request->user()->can('edit-finance')],
        ]);
    }

    public function store(SponsorRequest $request): RedirectResponse
    {
        $sponsor = Sponsor::query()->create($request->validated());

        return $this->done('sponsor_saved', to_route('console.finance.sponsors.show', $sponsor));
    }

    public function show(Request $request, Sponsor $sponsor): Response
    {
        Gate::authorize('view-finance');

        return Inertia::render('console/finance/SponsorShow', [
            'sponsor' => [
                'id' => $sponsor->getKey(),
                'name' => (string) $sponsor->localized('name'),
                'nameEn' => $sponsor->name_en,
                'nameAr' => $sponsor->name_ar,
                'logo' => $sponsor->logoUrl(),
                'type' => $sponsor->type->value,
                'website' => $sponsor->website,
                'notes' => $sponsor->notes,
                'publicConsent' => $sponsor->public_consent,
                'isActive' => $sponsor->is_active,
            ],
            'contacts' => $sponsor->contacts()->orderBy('name')->get()
                ->map(fn (SponsorContact $contact): array => $contact->only(['id', 'name', 'role', 'phone', 'email']))
                ->all(),
            'sponsorships' => $sponsor->sponsorships()->latest()->get()
                ->map(fn (Sponsorship $sponsorship): array => [
                    'id' => $sponsorship->getKey(),
                    'title' => $sponsorship->title,
                    'status' => $sponsorship->status->value,
                    'pledged' => MoneyFormatter::toArray($sponsorship->pledged()),
                    'received' => MoneyFormatter::toArray($sponsorship->received()),
                ])
                ->all(),
            'options' => fn (): array => self::sponsorshipOptions(),
            'can' => ['edit' => $request->user()->can('edit-finance')],
        ]);
    }

    public function update(SponsorRequest $request, Sponsor $sponsor): RedirectResponse
    {
        $sponsor->update($request->validated());

        return $this->done('sponsor_saved', back());
    }

    public function logo(SponsorLogoRequest $request, Sponsor $sponsor): RedirectResponse
    {
        $sponsor->addMediaFromRequest('logo')->toMediaCollection('logo');
        activity('finance')->performedOn($sponsor)->causedBy($request->user())->event('finance.sponsor.logo_changed')->log('Sponsor logo changed');

        return $this->done('logo_saved', back());
    }

    public function destroyLogo(Request $request, Sponsor $sponsor): RedirectResponse
    {
        Gate::authorize('edit-finance');
        $sponsor->clearMediaCollection('logo');
        activity('finance')->performedOn($sponsor)->causedBy($request->user())->event('finance.sponsor.logo_removed')->log('Sponsor logo removed');

        return $this->done('logo_removed', back());
    }

    public function storeContact(SponsorContactRequest $request, Sponsor $sponsor): RedirectResponse
    {
        $sponsor->contacts()->create($request->validated());

        return $this->done('contact_saved', back());
    }

    public function destroyContact(Request $request, Sponsor $sponsor, SponsorContact $contact): RedirectResponse
    {
        Gate::authorize('edit-finance');
        abort_unless($contact->sponsor_id === $sponsor->getKey(), 404);

        $contact->delete();

        return $this->done('contact_removed', back());
    }

    /**
     * Choices for the sponsorship form: kinds, statuses, currencies and the
     * open foundation accounts rules may target.
     *
     * @return array<string, mixed>
     */
    public static function sponsorshipOptions(): array
    {
        return [
            'kinds' => array_column(SponsorshipKind::cases(), 'value'),
            'statuses' => array_column(SponsorshipStatus::cases(), 'value'),
            'currencies' => Currency::values(),
            'accounts' => FinanceAccount::query()->internal()->where('is_archived', false)->orderBy('type')->orderBy('name_en')->get()
                ->map(fn (FinanceAccount $account): array => [
                    'id' => $account->getKey(),
                    'name' => (string) $account->localized('name'),
                    'currency' => $account->currency,
                ])
                ->all(),
        ];
    }

    private function done(string $key, RedirectResponse $response): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('finance.flash.'.$key)]);

        return $response;
    }
}
