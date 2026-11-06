<?php

namespace App\Domain\Teams\Models;

use App\Domain\Teams\Enums\TeamFeature;
use App\Support\Concerns\HasLocalizedFields;
use Database\Factories\TierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name_en
 * @property string|null $name_ar
 * @property string $slug
 * @property array<string, bool|int> $features
 * @property bool $is_default
 * @property int $position
 */
#[Fillable(['name_en', 'name_ar', 'slug', 'features', 'is_default', 'position'])]
#[UseFactory(TierFactory::class)]
class Tier extends Model
{
    /** @use HasFactory<TierFactory> */
    use HasFactory, HasLocalizedFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_default' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function value(TeamFeature $feature): bool|int
    {
        return $feature->normalize($this->features[$feature->value] ?? $feature->fallback());
    }

    public static function default(): ?self
    {
        return self::query()->where('is_default', true)->first();
    }
}
