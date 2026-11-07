<?php

namespace Database\Factories;

use App\Domain\Catalogue\Models\PartCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PartCategory>
 */
class PartCategoryFactory extends Factory
{
    protected $model = PartCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word());

        return [
            'name_en' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'position' => 0,
        ];
    }
}
