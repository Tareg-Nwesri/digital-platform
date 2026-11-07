<?php

namespace App\Http\Controllers\Console;

use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Catalogue\Models\Part;
use App\Domain\Catalogue\Models\PartCategory;
use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Catalogue\Models\VendorOffer;
use App\Domain\Catalogue\Services\CatalogueService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\PartRequest;
use App\Http\Requests\Console\SyncOffersRequest;
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
 * LLD 05 §6: parts and their offers in the console.
 */
class CataloguePartController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view-catalogue');

        $parts = TableQuery::for(Part::query()->with(['category', 'suggestingTeam', 'approvedOffers'])->withCount('approvedOffers'), $request)
            ->searchable(['name', 'part_number'])
            ->sortable(['name', 'created_at'], default: 'name')
            ->filters([
                'status' => fn (Builder $query, mixed $value) => $query->where('status', (string) $value),
                'category' => function (Builder $query, mixed $value): void {
                    $category = PartCategory::query()->where('slug', (string) $value)->first();
                    $query->whereIn('category_id', $category?->selfAndChildIds() ?? [0]);
                },
            ])
            ->paginate(function (Part $part): array {
                $best = $part->approvedOffers
                    ->where('currency', 'LYD')
                    ->where('in_stock', true)
                    ->sortBy('price_minor')
                    ->first();

                return [
                    'id' => $part->getKey(),
                    'name' => $part->name,
                    'partNumber' => $part->part_number,
                    'category' => $part->category?->localized('name'),
                    'status' => $part->status->value,
                    'team' => $part->suggestingTeam?->name,
                    'offers' => (int) $part->approved_offers_count,
                    'bestPrice' => $best === null ? null : MoneyFormatter::format($best->price),
                ];
            });

        return Inertia::render('console/catalogue/Parts', [
            'parts' => $parts,
            'filters' => [
                'statuses' => array_column(CatalogueStatus::cases(), 'value'),
                'categories' => $this->categoryOptions(),
            ],
            'categories' => $this->categoryOptions(),
            'suggestionsCount' => Part::query()->suggestions()->count() + Vendor::query()->suggestions()->count() + VendorOffer::query()->suggestions()->count(),
            'can' => [
                'manage' => $request->user()->can('manage-catalogue'),
                'review' => $request->user()->can('review-catalogue'),
            ],
        ]);
    }

    public function store(PartRequest $request): RedirectResponse
    {
        $part = $this->catalogue->createPart($request->validated(), $request->user());

        return $this->done('part_saved', $part);
    }

    public function show(Request $request, Part $part): Response
    {
        Gate::authorize('view-catalogue');

        $part->loadMissing(['category', 'suggestingTeam', 'suggester']);

        return Inertia::render('console/catalogue/PartShow', [
            'part' => [
                'id' => $part->getKey(),
                'name' => $part->name,
                'partNumber' => $part->part_number,
                'categoryId' => $part->category_id,
                'category' => $part->category?->localized('name'),
                'descriptionEn' => $part->description_en,
                'descriptionAr' => $part->description_ar,
                'specs' => $part->specs ?? [],
                'status' => $part->status->value,
                'team' => $part->suggestingTeam?->name,
                'suggestedBy' => $part->suggester?->name,
            ],
            'offers' => $part->offers()->with('vendor')->orderBy('price_minor')->get()
                ->map(fn (VendorOffer $offer): array => [
                    'id' => $offer->getKey(),
                    'vendor' => ['id' => $offer->vendor->getKey(), 'name' => $offer->vendor->name],
                    'price' => (string) $offer->price->getAmount(),
                    'currency' => $offer->currency,
                    'formatted' => MoneyFormatter::format($offer->price),
                    'url' => $offer->url,
                    'inStock' => $offer->in_stock,
                    'checkedAt' => $offer->checked_at?->toDateString(),
                    'status' => $offer->status->value,
                ])
                ->all(),
            'vendors' => Vendor::query()->approved()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Vendor $vendor): array => ['id' => $vendor->getKey(), 'name' => $vendor->name])
                ->all(),
            'categories' => $this->categoryOptions(),
            'currencies' => Currency::values(),
            'can' => ['manage' => $request->user()->can('manage-catalogue')],
        ]);
    }

    public function update(PartRequest $request, Part $part): RedirectResponse
    {
        $this->catalogue->updatePart($part, $request->validated(), $request->user());

        return $this->done('part_saved', $part);
    }

    public function offers(SyncOffersRequest $request, Part $part): RedirectResponse
    {
        $this->catalogue->syncOffers($part, $request->validated('offers'), $request->user());

        return $this->done('offers_saved', $part);
    }

    public function destroy(Request $request, Part $part): RedirectResponse
    {
        Gate::authorize('manage-catalogue');

        $this->catalogue->archivePart($part, $request->user());

        return $this->done('part_archived', $part);
    }

    public function restore(Request $request, Part $part): RedirectResponse
    {
        Gate::authorize('manage-catalogue');

        $this->catalogue->restorePart($part, $request->user());

        return $this->done('part_restored', $part);
    }

    /**
     * Categories as a flat list with children indented under their parent.
     *
     * @return list<array{id: int, slug: string, name: string, depth: int}>
     */
    private function categoryOptions(): array
    {
        $options = [];

        foreach (PartCategory::query()->whereNull('parent_id')->with('children')->orderBy('position')->orderBy('name_en')->get() as $parent) {
            $options[] = ['id' => $parent->getKey(), 'slug' => $parent->slug, 'name' => $parent->localized('name'), 'depth' => 0];

            foreach ($parent->children as $child) {
                $options[] = ['id' => $child->getKey(), 'slug' => $child->slug, 'name' => $child->localized('name'), 'depth' => 1];
            }
        }

        return $options;
    }

    private function done(string $key, Part $part): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('catalogue.flash.'.$key)]);

        return to_route('console.catalogue.parts.show', $part);
    }
}
