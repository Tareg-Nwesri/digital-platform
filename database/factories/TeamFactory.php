<?php

namespace Database\Factories;

use App\Domain\Access\Enums\WorkspaceType;
use App\Domain\Access\Models\Workspace;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' Robotics';

        return [
            'workspace_id' => fn () => Workspace::open(WorkspaceType::Team)->getKey(),
            'tier_id' => Tier::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => TeamStatus::Active,
            'location' => fake()->randomElement(['Tripoli', 'Benghazi', 'Misrata', 'Sabha']),
            'is_public' => false,
            'approved_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Team $team): void {
            app(TeamService::class)->createRoles($team->workspace);
        });
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => TeamStatus::Pending, 'approved_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => TeamStatus::Suspended]);
    }

    public function public(): static
    {
        return $this->state(fn () => ['is_public' => true]);
    }

    public function competition(): static
    {
        return $this->state(fn () => ['tier_id' => Tier::factory()->competition()]);
    }
}
