<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

final class LiteralLike
{
    /**
     * Escape LIKE wildcards so the term is matched literally.
     */
    public static function escape(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    /**
     * Add a literal "contains" condition for the given column.
     */
    public static function where(Builder $query, string $column, string $term, string $boolean = 'and'): Builder
    {
        return $query->where($column, 'like', '%'.self::escape($term).'%', $boolean);
    }
}
