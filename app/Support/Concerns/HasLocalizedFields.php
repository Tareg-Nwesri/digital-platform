<?php

namespace App\Support\Concerns;

use App\Support\LiteralLike;
use Illuminate\Database\Eloquent\Builder;

trait HasLocalizedFields
{
    /**
     * Get the value of a bilingual field in the current locale, falling back to English.
     */
    public function localized(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $arabic = $this->getAttribute($field.'_ar');

        if ($locale === 'ar' && filled($arabic)) {
            return $arabic;
        }

        return $this->getAttribute($field.'_en');
    }

    /**
     * Search both language columns of a bilingual field literally.
     *
     * @param  Builder<static>  $query
     */
    public function scopeSearchLocalized(Builder $query, string $field, string $term): void
    {
        $query->where(function (Builder $matches) use ($field, $term): void {
            LiteralLike::where($matches, $field.'_en', $term);
            LiteralLike::where($matches, $field.'_ar', $term, 'or');
        });
    }
}
