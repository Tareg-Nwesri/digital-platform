<?php

namespace App\Domain\Catalogue\Models;

use App\Domain\Catalogue\Concerns\IsCatalogueItem;
use App\Domain\Catalogue\Enums\CatalogueStatus;
use Database\Factories\PartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $category_id
 * @property string $name
 * @property string|null $part_number
 * @property string|null $description_en
 * @property string|null $description_ar
 * @property list<array{key: string, value: string, unit?: string|null}>|null $specs
 * @property int|null $merged_into_id
 * @property-read PartCategory|null $category
 */
#[Fillable([
    'category_id', 'name', 'part_number', 'description_en', 'description_ar', 'specs', 'status',
    'suggested_by_team_id', 'suggested_by', 'reviewed_by', 'reviewed_at', 'review_note', 'merged_into_id',
])]
#[UseFactory(PartFactory::class)]
class Part extends Model
{
    /** @use HasFactory<PartFactory> */
    use HasFactory, IsCatalogueItem;

    public static function catalogueType(): string
    {
        return 'part';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['specs' => 'array'];
    }

    /**
     * @return BelongsTo<PartCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class, 'category_id');
    }

    /**
     * Every offer, whatever its status.
     *
     * @return HasMany<VendorOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(VendorOffer::class);
    }

    /**
     * @return HasMany<VendorOffer, $this>
     */
    public function approvedOffers(): HasMany
    {
        return $this->offers()->where('vendor_offers.status', CatalogueStatus::Approved)->orderBy('price_minor');
    }

    /**
     * The lowest approved, in-stock LYD price.
     */
    public function bestOffer(): ?VendorOffer
    {
        return $this->approvedOffers()
            ->where('currency', 'LYD')
            ->where('in_stock', true)
            ->with('vendor')
            ->first();
    }
}
