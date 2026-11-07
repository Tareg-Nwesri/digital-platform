<?php

namespace Database\Factories;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventCategory>
 */
class EventCategoryFactory extends Factory
{
    protected $model = EventCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name_en' => fake()->randomElement(['Line follower', 'Sumo', 'Maze solver', 'Soccer']),
            'members_min' => 1,
            'members_max' => 5,
            'robots_per_team' => 1,
            'position' => 0,
        ];
    }
}
