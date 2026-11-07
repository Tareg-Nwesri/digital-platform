<?php

namespace App\Domain\Catalogue\Models;

use App\Domain\Catalogue\Concerns\IsCatalogueItem;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $website
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $city
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $merged_into_id
 */
#[Fillable([
    'name', 'website', 'email', 'phone', 'city', 'address', 'latitude', 'longitude', 'status',
    'suggested_by_team_id', 'suggested_by', 'reviewed_by', 'reviewed_at', 'review_note', 'merged_into_id',
])]
#[UseFactory(VendorFactory::class)]
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory, IsCatalogueItem;

    public static function catalogueType(): string
    {
        return 'vendor';
    }

    /**
     * @return HasMany<VendorOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(VendorOffer::class);
    }
}
