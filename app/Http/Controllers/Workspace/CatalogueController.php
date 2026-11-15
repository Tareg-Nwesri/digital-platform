<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Catalogue\Models\Part;
use App\Domain\Catalogue\Models\PartCategory;
use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Catalogue\Models\VendorOffer;
use App\Domain\Catalogue\Services\CatalogueSearch;
use App\Domain\Catalogue\Services\SuggestionService;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\SuggestCatalogueItemRequest;
use App\Support\Enums\Currency;
use App\Support\Money\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 05 §5: the catalogue from the team workspace — browse, compare
 * offers and suggest additions for the organisers to review.
 */
class CatalogueController extends Controller
{
    public function __construct(
        private readonly SuggestionService $suggestions,
        private readonly TeamFeatures $features,
    ) {}

    public function index(Request $request, Team $team, CatalogueSearch $search): Response
    {
        $this->features->assertActive($team, TeamFeature::PartsAccess);
        $filters = $request->only(['q', 'category_id', 'in_stock']);

        $parts = $search->search($team, $filters, 24)->through(fn (Part $part): array => [
            'id' => $part->getKey(),
            'name' => $part->name,
            'partNumber' => $part->part_number,
            'category' => $part->category?->localized('name'),
            'description' => app()->getLocale() === 'ar' ? ($part->description_ar ?? $part->description_en) : ($part->description_en ?? $part->description_ar),
            'status' => $part->status->value,
            'offers' => $part->approvedOffers->sortBy('price_minor')->values()->map(fn (VendorOffer $offer): array => [
                'id' => $offer->getKey(),
                'vendor' => $offer->vendor->name,
                'price' => MoneyFormatter::format($offer->price),
                'inStock' => $offer->in_stock,
                'url' => $offer->url,
            ])->all(),
        ]);

        return Inertia::render('workspace/catalogue/Index', [
            'team' => ['slug' => $team->slug, 'name' => $team->name],
            'parts' => $parts,
            'filters' => [
                'q' => (string) ($filters['q'] ?? ''),
                'category_id' => filled($filters['category_id'] ?? null) ? (int) $filters['category_id'] : null,
                'in_stock' => filter_var($filters['in_stock'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ],
            'categories' => PartCategory::query()->orderBy('name_en')->get()
                ->map(fn (PartCategory $category): array => ['id' => $category->getKey(), 'name' => (string) $category->localized('name')])->all(),
            'vendors' => Vendor::query()->visibleTo($team)->orderBy('name')->get(['id', 'name', 'status'])
                ->map(fn (Vendor $vendor): array => ['id' => $vendor->getKey(), 'name' => $vendor->name])->all(),
            'suggestions' => $this->suggestionsOf($team),
            'currencies' => Currency::values(),
            'can' => [
                'suggest' => $request->user()->can('catalogue.suggest') && $this->features->active($team, TeamFeature::PartsSuggest),
            ],
        ]);
    }

    public function storePart(SuggestCatalogueItemRequest $request, Team $team): RedirectResponse
    {
        $this->suggestions->suggestPart($team, $request->validated(), $request->user());

        return $this->done();
    }

    public function storeVendor(SuggestCatalogueItemRequest $request, Team $team): RedirectResponse
    {
        $this->suggestions->suggestVendor($team, $request->validated(), $request->user());

        return $this->done();
    }

    public function storeOffer(SuggestCatalogueItemRequest $request, Team $team): RedirectResponse
    {
        $this->suggestions->suggestOffer($team, $request->validated(), $request->user());

        return $this->done();
    }

    /**
     * The team's suggestions, newest first, with the organisers' decision.
     *
     * @return list<array{type: string, id: int, name: string, status: string, note: string|null, createdAt: string|null}>
     */
    private function suggestionsOf(Team $team): array
    {
        $own = fn (string $model): Collection => $model::query()
            ->where('suggested_by_team_id', $team->getKey())
            ->latest('id')
            ->limit(50)
            ->get();

        return collect()
            ->concat($own(Part::class)->map(fn (Part $part): array => $this->suggestion('part', $part->getKey(), $part->name, $part)))
            ->concat($own(Vendor::class)->map(fn (Vendor $vendor): array => $this->suggestion('vendor', $vendor->getKey(), $vendor->name, $vendor)))
            ->concat($own(VendorOffer::class)->load(['part', 'vendor'])->map(fn (VendorOffer $offer): array => $this->suggestion(
                'offer',
                $offer->getKey(),
                $offer->part->name.' · '.$offer->vendor->name.' · '.MoneyFormatter::format($offer->price),
                $offer,
            )))
            ->sortByDesc('createdAt')
            ->values()
            ->all();
    }

    /**
     * @return array{type: string, id: int, name: string, status: string, note: string|null, createdAt: string|null}
     */
    private function suggestion(string $type, int $id, string $name, Part|Vendor|VendorOffer $item): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'status' => $item->status->value,
            'note' => $item->review_note,
            'createdAt' => $item->created_at?->toIso8601String(),
        ];
    }

    private function done(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('catalogue.flash.suggested')]);

        return back();
    }
}
