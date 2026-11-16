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
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
class Sponsor extends Model implements HasMedia
{
    /** @use HasFactory<SponsorFactory> */
    use HasFactory, HasLocalizedFields, InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/png', 'image/jpeg', 'image/webp']);
    }

    /**
     * A small WebP that keeps transparency, for lists and the public site.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 320, 160)
            ->format('webp')
            ->nonQueued()
            ->performOnCollections('logo');
    }

    public function logoUrl(): ?string
    {
        $logo = $this->getFirstMedia('logo');

        return $logo?->getUrl($logo->hasGeneratedConversion('thumb') ? 'thumb' : '');
    }

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
