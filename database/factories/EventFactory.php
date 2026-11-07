<?php

namespace Database\Factories;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventVisibility;
use App\Domain\Events\Models\Event;
use App\Domain\Organisation\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true)).' Cup';
        $starts = now()->addDays(30)->setTime(9, 0);

        return [
            'department_id' => Department::factory(),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'title_en' => $title,
            'venue' => fake()->randomElement(['Tripoli Expo Hall', 'Benghazi University', 'Misrata Tech Hub']),
            'city' => fake()->randomElement(['Tripoli', 'Benghazi', 'Misrata']),
            'timezone' => 'Africa/Tripoli',
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHours(8),
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => $starts->copy()->subDays(5),
            'status' => EventStatus::Draft,
            'visibility' => EventVisibility::Public,
            'capacity' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Open]);
    }

    public function inviteOnly(): static
    {
        return $this->state(fn () => ['visibility' => EventVisibility::InviteOnly]);
    }
}
