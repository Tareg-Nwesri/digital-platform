<?php

namespace App\Domain\Catalogue\Services;

use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Catalogue\Models\Part;
use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Catalogue\Models\VendorOffer;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Team suggestions and their review (LLD 05 §4). A suggestion is visible
 * only to its team until an organiser approves, rejects or merges it.
 */
final class SuggestionService
{
    public function __construct(private readonly TeamFeatures $features) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function suggestPart(Team $team, array $data, User $by): Part
    {
        $this->assertCanSuggest($team);

        $part = Part::query()->create([
            ...array_intersect_key($data, array_flip(CatalogueService::PART_FIELDS)),
            ...$this->suggestionColumns($team, $by),
        ]);
        $this->log($part, $by, 'catalogue.suggested');

        return $part;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function suggestVendor(Team $team, array $data, User $by): Vendor
    {
        $this->assertCanSuggest($team);

        $vendor = Vendor::query()->create([
            ...array_intersect_key($data, array_flip(CatalogueService::VENDOR_FIELDS)),
            ...$this->suggestionColumns($team, $by),
        ]);
        $this->log($vendor, $by, 'catalogue.suggested');

        return $vendor;
    }

    /**
     * The part and vendor may be approved items or this team's own suggestions.
     *
     * @param  array<string, mixed>  $data
     */
    public function suggestOffer(Team $team, array $data, User $by): VendorOffer
    {
        $this->assertCanSuggest($team);

        $visiblePart = Part::query()->visibleTo($team)->whereKey($data['part_id'] ?? null)->exists();
        $visibleVendor = Vendor::query()->visibleTo($team)->whereKey($data['vendor_id'] ?? null)->exists();

        if (! $visiblePart || ! $visibleVendor) {
            throw DomainRuleViolation::with(['part_id' => __('catalogue.errors.not_visible')]);
        }

        $offer = VendorOffer::query()->create([
            ...CatalogueService::offerAttributes($data),
            'part_id' => (int) $data['part_id'],
            ...$this->suggestionColumns($team, $by),
        ]);
        $this->log($offer, $by, 'catalogue.suggested');

        return $offer;
    }

    /**
     * Suggestions that approving this offer also approves: its part and vendor
     * when they are still suggestions.
     *
     * @return Collection<int, Part|Vendor>
     */
    public function cascadeFor(Part|Vendor|VendorOffer $item): Collection
    {
        if (! $item instanceof VendorOffer) {
            return collect();
        }

        return collect([$item->part, $item->vendor])
            ->filter(fn (Part|Vendor|null $related): bool => $related !== null && $related->status === CatalogueStatus::Suggested)
            ->values();
    }

    /**
     * Approve a suggestion, applying the reviewer's edits first. An offer
     * also approves its suggested part and vendor.
     *
     * @param  array<string, mixed>|null  $edits
     */
    public function approve(Part|Vendor|VendorOffer $item, ?array $edits, User $by): Part|Vendor|VendorOffer
    {
        $this->assertSuggested($item);

        return DB::transaction(function () use ($item, $edits, $by): Part|Vendor|VendorOffer {
            foreach ($this->cascadeFor($item) as $related) {
                $this->markReviewed($related, CatalogueStatus::Approved, null, $by);
            }

            if ($edits !== null && $edits !== []) {
                $item->fill($this->editable($item, $edits));
            }

            $this->markReviewed($item, CatalogueStatus::Approved, null, $by);

            return $item;
        });
    }

    public function reject(Part|Vendor|VendorOffer $item, string $note, User $by): void
    {
        $this->assertSuggested($item);
        $this->markReviewed($item, CatalogueStatus::Rejected, $note, $by);
    }

    /**
     * Fold a duplicate suggestion into an approved item: its offers move to
     * the target (and BOM lines, once they exist), and it is rejected with
     * merged_into_id set.
     */
    public function merge(Part|Vendor $item, Part|Vendor $into, User $by): void
    {
        $this->assertSuggested($item);

        if ($item::class !== $into::class || $item->is($into) || $into->status !== CatalogueStatus::Approved) {
            throw DomainRuleViolation::with(['into_id' => __('catalogue.errors.merge_target')]);
        }

        DB::transaction(function () use ($item, $into, $by): void {
            $column = $item instanceof Part ? 'part_id' : 'vendor_id';
            VendorOffer::query()->where($column, $item->getKey())->update([$column => $into->getKey()]);

            $item->forceFill(['merged_into_id' => $into->getKey()]);
            $this->markReviewed($item, CatalogueStatus::Rejected, __('catalogue.merged_note', ['name' => $into->name]), $by, 'catalogue.merged');
        });
    }

    private function assertCanSuggest(Team $team): void
    {
        if ($team->status !== TeamStatus::Active) {
            throw DomainRuleViolation::with(['team' => __('catalogue.errors.team_inactive')]);
        }

        $this->features->assertActive($team, TeamFeature::PartsSuggest);
    }

    private function assertSuggested(Part|Vendor|VendorOffer $item): void
    {
        if ($item->status !== CatalogueStatus::Suggested) {
            throw DomainRuleViolation::with(['status' => __('catalogue.errors.not_suggested')]);
        }
    }

    private function markReviewed(Part|Vendor|VendorOffer $item, CatalogueStatus $status, ?string $note, User $by, ?string $event = null): void
    {
        $item->forceFill([
            'status' => $status,
            'review_note' => $note,
            'reviewed_by' => $by->getKey(),
            'reviewed_at' => now(),
        ])->save();

        $this->log($item, $by, $event ?? ($status === CatalogueStatus::Approved ? 'catalogue.approved' : 'catalogue.rejected'));
    }

    /**
     * @param  array<string, mixed>  $edits
     * @return array<string, mixed>
     */
    private function editable(Part|Vendor|VendorOffer $item, array $edits): array
    {
        return match (true) {
            $item instanceof Part => array_intersect_key($edits, array_flip(CatalogueService::PART_FIELDS)),
            $item instanceof Vendor => array_intersect_key($edits, array_flip(CatalogueService::VENDOR_FIELDS)),
            default => array_diff_key(
                CatalogueService::offerAttributes([...['vendor_id' => $item->vendor_id, 'price' => (string) $item->price->getAmount(), 'currency' => $item->currency, 'url' => $item->url, 'in_stock' => $item->in_stock], ...$edits]),
                ['vendor_id' => true],
            ),
        };
    }

    /**
     * @return array{status: CatalogueStatus, suggested_by_team_id: int, suggested_by: int}
     */
    private function suggestionColumns(Team $team, User $by): array
    {
        return [
            'status' => CatalogueStatus::Suggested,
            'suggested_by_team_id' => $team->getKey(),
            'suggested_by' => $by->getKey(),
        ];
    }

    private function log(Part|Vendor|VendorOffer $item, User $by, string $event): void
    {
        activity('catalogue')->performedOn($item)->causedBy($by)
            ->withProperties(['team_id' => $item->suggested_by_team_id, 'type' => $item::catalogueType()])
            ->event($event)
            ->log(ucfirst(str_replace(['catalogue.', '_'], ['', ' '], $event)));
    }
}
