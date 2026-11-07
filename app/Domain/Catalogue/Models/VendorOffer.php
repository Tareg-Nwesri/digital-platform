<?php

namespace App\Domain\Catalogue\Models;

use App\Domain\Catalogue\Concerns\IsCatalogueItem;
use App\Support\Money\MoneyCast;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $part_id
 * @property int $vendor_id
 * @property int $price_minor
 * @property string $currency
 * @property Money $price
 * @property string|null $url
 * @property bool $in_stock
 * @property Carbon|null $checked_at
 * @property-read Part $part
 * @property-read Vendor $vendor
 */
#[Fillable([
    'part_id', 'vendor_id', 'price_minor', 'currency', 'url', 'in_stock', 'checked_at', 'status',
    'suggested_by_team_id', 'suggested_by', 'reviewed_by', 'reviewed_at', 'review_note',
])]
class VendorOffer extends Model
{
    use IsCatalogueItem;

    public static function catalogueType(): string
    {
        return 'offer';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class.':price_minor,currency',
            'price_minor' => 'integer',
            'in_stock' => 'boolean',
            'checked_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
