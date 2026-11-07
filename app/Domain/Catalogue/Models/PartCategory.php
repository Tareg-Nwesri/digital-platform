<?php

namespace App\Domain\Catalogue\Models;

use App\Support\Concerns\HasLocalizedFields;
use Database\Factories\PartCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Two levels at most (LLD 05 §1).
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name_en
 * @property string|null $name_ar
 * @property string $slug
 * @property int $position
 */
#[Fillable(['parent_id', 'name_en', 'name_ar', 'slug', 'position'])]
#[UseFactory(PartCategoryFactory::class)]
class PartCategory extends Model
{
    /** @use HasFactory<PartCategoryFactory> */
    use HasFactory, HasLocalizedFields;

    /**
     * @return BelongsTo<PartCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<PartCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('name_en');
    }

    /**
     * @return HasMany<Part, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(Part::class, 'category_id');
    }

    /**
     * This category and its children.
     *
     * @return list<int>
     */
    public function selfAndChildIds(): array
    {
        return [$this->getKey(), ...$this->children()->pluck('id')->all()];
    }
}
