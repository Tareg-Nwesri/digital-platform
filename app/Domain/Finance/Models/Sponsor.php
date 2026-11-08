<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\SponsorType;
use App\Support\Concerns\HasLocalizedFields;
use Database\Factories\SponsorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sponsors are deactivated, never deleted (LLD 07 §1).
 *
 * @property int $id
 * @property string $name_en
 * @property string|null $name_ar
 * @property SponsorType $type
 * @property string|null $website
 * @property string|null $notes
 * @property bool $public_consent
 * @property bool $is_active
 */
#[Fillable(['name_en', 'name_ar', 'type', 'website', 'notes', 'public_consent', 'is_active'])]
#[UseFactory(SponsorFactory::class)]
class Sponsor extends Model
{
    /** @use HasFactory<SponsorFactory> */
    use HasFactory, HasLocalizedFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SponsorType::class,
            'public_consent' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SponsorContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(SponsorContact::class);
    }

    /**
     * @return HasMany<Sponsorship, $this>
     */
    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class);
    }
}
