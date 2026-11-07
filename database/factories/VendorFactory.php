<?php

namespace Database\Factories;

use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Catalogue\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Electronics',
            'city' => fake()->randomElement(['Tripoli', 'Benghazi', 'Misrata']),
            'status' => CatalogueStatus::Approved,
        ];
    }

    public function suggested(): static
    {
        return $this->state(fn () => ['status' => CatalogueStatus::Suggested]);
    }
}
