<?php

namespace Database\Factories;

use App\Domain\Finance\Enums\SponsorType;
use App\Domain\Finance\Models\Sponsor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sponsor>
 */
class SponsorFactory extends Factory
{
    protected $model = Sponsor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_en' => fake()->unique()->company(),
            'type' => SponsorType::Company,
            'is_active' => true,
            'public_consent' => false,
        ];
    }
}
