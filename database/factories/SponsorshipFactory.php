<?php

namespace Database\Factories;

use App\Domain\Finance\Enums\SponsorshipKind;
use App\Domain\Finance\Enums\SponsorshipStatus;
use App\Domain\Finance\Models\Sponsor;
use App\Domain\Finance\Models\Sponsorship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sponsorship>
 */
class SponsorshipFactory extends Factory
{
    protected $model = Sponsorship::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sponsor_id' => Sponsor::factory(),
            'title' => 'Season '.fake()->year(),
            'kind' => SponsorshipKind::Cash,
            'pledged_minor' => 5_000_000,
            'currency' => 'LYD',
            'status' => SponsorshipStatus::Active,
        ];
    }
}
