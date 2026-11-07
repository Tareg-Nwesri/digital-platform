<?php

namespace App\Domain\Catalogue\Services;

use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Catalogue\Models\Part;
use App\Domain\Catalogue\Models\PartCategory;
use App\Domain\Teams\Models\Team;
use App\Support\LiteralLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Part search for teams and staff (LLD 05 §4). Text search uses escaped
 * LIKE on name, part number and description: MySQL fulltext indexes do not
 * see uncommitted rows, and the catalogue is small enough not to need them.
 */
final class CatalogueSearch
{
    /**
     * With a team: approved parts and the team's own suggestions. Without:
     * every status, optionally filtered by `status` (staff).
     *
     * @param  array{q?: string|null, category_id?: int|string|null, vendor_id?: int|string|null, price_min?: int|string|null, price_max?: int|string|null, in_stock?: bool|string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, Part>
     */
    public function search(?Team $team, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Part::query()
            ->with(['category', 'approvedOffers.vendor'])
            ->withCount('approvedOffers');

        if ($team !== null) {
            $query->visibleTo($team);
        } elseif (filled($filters['status'] ?? null) && CatalogueStatus::tryFrom((string) $filters['status']) !== null) {
            $query->where('status', (string) $filters['status']);
        }

        if (filled($term = trim((string) ($filters['q'] ?? '')))) {
            $query->where(function (Builder $matches) use ($term): void {
                LiteralLike::where($matches->getQuery(), 'name', $term);
                LiteralLike::where($matches->getQuery(), 'part_number', $term, 'or');
                LiteralLike::where($matches->getQuery(), 'description_en', $term, 'or');
            });
        }

        if (filled($filters['category_id'] ?? null) && ($category = PartCategory::query()->find((int) $filters['category_id'])) !== null) {
            $query->whereIn('category_id', $category->selfAndChildIds());
        }

        $offerFilters = array_filter([
            'vendor_id' => filled($filters['vendor_id'] ?? null) ? (int) $filters['vendor_id'] : null,
            'price_min' => filled($filters['price_min'] ?? null) ? (int) $filters['price_min'] : null,
            'price_max' => filled($filters['price_max'] ?? null) ? (int) $filters['price_max'] : null,
            'in_stock' => filter_var($filters['in_stock'] ?? false, FILTER_VALIDATE_BOOLEAN) ? true : null,
        ], fn (mixed $value): bool => $value !== null);

        if ($offerFilters !== []) {
            $query->whereHas('offers', function (Builder $offer) use ($offerFilters): void {
                $offer->where('status', CatalogueStatus::Approved);

                if (isset($offerFilters['vendor_id'])) {
                    $offer->where('vendor_id', $offerFilters['vendor_id']);
                }

                if (isset($offerFilters['price_min']) || isset($offerFilters['price_max'])) {
                    $offer->where('currency', 'LYD');
                }

                if (isset($offerFilters['price_min'])) {
                    $offer->where('price_minor', '>=', $offerFilters['price_min']);
                }

                if (isset($offerFilters['price_max'])) {
                    $offer->where('price_minor', '<=', $offerFilters['price_max']);
                }

                if (isset($offerFilters['in_stock'])) {
                    $offer->where('in_stock', true);
                }
            });
        }

        return $query->orderBy('name')->paginate($perPage)->withQueryString();
    }
}
