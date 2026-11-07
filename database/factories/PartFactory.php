<?php

namespace Database\Factories;

use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Catalogue\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    protected $model = Part::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['SG90 servo', 'TT gear motor', 'HC-SR04 sensor', 'L298N driver', 'Arduino Uno']).' '.fake()->unique()->numberBetween(1, 9999),
            'part_number' => strtoupper(fake()->bothify('??-###')),
            'status' => CatalogueStatus::Approved,
        ];
    }

    public function suggested(): static
    {
        return $this->state(fn () => ['status' => CatalogueStatus::Suggested]);
    }
}
