<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class Slugger
{
    /**
     * Build a slug from the source that is unique for the model, optionally within a scope.
     *
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $scope
     */
    public static function unique(string $model, string $source, array $scope = [], ?int $ignoreId = null, string $column = 'slug'): string
    {
        $base = Str::slug($source) ?: 'item-'.Str::lower(Str::random(6));
        $slug = $base;
        $suffix = 2;

        while (self::taken($model, $column, $slug, $scope, $ignoreId)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $scope
     */
    private static function taken(string $model, string $column, string $slug, array $scope, ?int $ignoreId): bool
    {
        $query = $model::query()->where($column, $slug)->where($scope);

        if (method_exists($model, 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
