<?php

namespace App\Domain\Work\Models;

use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Catalogue\Models\Part;
use App\Domain\Catalogue\Models\VendorOffer;
use App\Domain\Work\Enums\BomLineAvailability;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $robot_id
 * @property int $part_id
 * @property int|null $vendor_offer_id
 * @property int $quantity
 * @property string|null $notes
 * @property int $position
 * @property-read Part $part
 * @property-read VendorOffer|null $offer
 */
#[Fillable(['robot_id', 'part_id', 'vendor_offer_id', 'quantity', 'notes', 'position'])]
class BomLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'position' => 'integer'];
    }

    /**
     * @return BelongsTo<Robot, $this>
     */
    public function robot(): BelongsTo
    {
        return $this->belongsTo(Robot::class);
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * @return BelongsTo<VendorOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(VendorOffer::class, 'vendor_offer_id');
    }

    public function availability(): BomLineAvailability
    {
        return match (true) {
            $this->part->status === CatalogueStatus::Archived => BomLineAvailability::PartArchived,
            $this->offer === null => BomLineAvailability::NoOffer,
            $this->offer->status !== CatalogueStatus::Approved => BomLineAvailability::OfferRemoved,
            ! $this->offer->in_stock => BomLineAvailability::OfferOutOfStock,
            default => BomLineAvailability::Available,
        };
    }

    public function unitPrice(): ?Money
    {
        return $this->offer?->price;
    }

    public function lineTotal(): ?Money
    {
        return $this->unitPrice()?->multipliedBy($this->quantity);
    }
}
