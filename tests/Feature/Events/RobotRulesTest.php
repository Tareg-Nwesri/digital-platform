<?php

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Services\EntryContext;
use App\Domain\Events\Services\EntryRules;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamMemberService;
use App\Domain\Work\Enums\RobotStatus;
use App\Domain\Work\Models\Robot;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Support\Carbon;

/*
 * LLD 06 §4: robot entry rules. The number of robots, ownership, retired
 * robots, and the category's weight and size limits (missing measurements
 * fail when the category has limits).
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);
    Carbon::setTestNow('2027-02-01 10:00:00');

    $department = app(DepartmentService::class)->create(['name_en' => 'Events']);
    $this->event = Event::factory()->for($department)->open()->create([
        'starts_at' => '2027-03-10 08:00:00',
        'ends_at' => '2027-03-10 18:00:00',
        'registration_opens_at' => '2027-01-15 00:00:00',
        'registration_closes_at' => '2027-03-01 00:00:00',
    ]);
    $this->category = EventCategory::factory()->for($this->event)->create([
        'members_min' => 1,
        'members_max' => 3,
        'robots_per_team' => 1,
    ]);

    $this->team = Team::factory()->create(['tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
    $this->captain = User::factory()->create(['birthdate' => '2012-05-05']);
    app(TeamMemberService::class)->join($this->team, $this->captain, TeamRole::Captain);
});

function robotFor(Team $team, array $attributes = []): Robot
{
    return Robot::query()->create([
        'team_id' => $team->getKey(),
        'name' => $attributes['name'] ?? 'Bot '.fake()->unique()->numberBetween(1, 9999),
        'slug' => 'bot-'.fake()->unique()->numberBetween(1, 99999),
        'status' => RobotStatus::Ready,
        ...$attributes,
    ]);
}

function robotCodes(array $robots, string $mode = 'apply'): array
{
    return collect(app(EntryRules::class)->check(test()->event->fresh(), test()->category->fresh(), test()->team->fresh(), [test()->captain], EntryContext::{$mode}(), $robots))
        ->pluck('code')
        ->sort()
        ->values()
        ->all();
}

it('accepts a ready robot of the team within the limits', function () {
    $this->category->update(['max_weight_g' => 1000, 'max_length_mm' => 200, 'max_width_mm' => 200, 'max_height_mm' => 200]);

    expect(robotCodes([robotFor($this->team, ['weight_g' => 900, 'length_mm' => 200, 'width_mm' => 150, 'height_mm' => 100])]))->toBe([]);
});

it('needs between one robot and the category maximum when applying', function () {
    $this->category->update(['robots_per_team' => 2]);

    expect(robotCodes([]))->toBe(['robots_count'])
        ->and(robotCodes([robotFor($this->team), robotFor($this->team)]))->toBe([])
        ->and(robotCodes([robotFor($this->team), robotFor($this->team), robotFor($this->team)]))->toBe(['robots_count']);
});

it('only checks the robot count for organisers when robots were given', function () {
    expect(robotCodes([], 'add'))->toBe([])
        ->and(robotCodes([robotFor($this->team), robotFor($this->team)], 'add'))->toBe(['robots_count']);
});

it('refuses robots of another team and retired robots', function () {
    $other = Team::factory()->create();

    expect(robotCodes([robotFor($other)]))->toBe(['robot_not_in_team'])
        ->and(robotCodes([robotFor($this->team, ['status' => RobotStatus::Retired])]))->toBe(['robot_retired']);
});

it('refuses robots over the weight or size limits', function () {
    $this->category->update(['max_weight_g' => 1000, 'max_length_mm' => 200]);

    expect(robotCodes([robotFor($this->team, ['weight_g' => 1001, 'length_mm' => 150])]))->toBe(['robot_weight'])
        ->and(robotCodes([robotFor($this->team, ['weight_g' => 500, 'length_mm' => 201])]))->toBe(['robot_size']);
});

it('fails robots with missing measurements when the category has limits', function () {
    $this->category->update(['max_weight_g' => 1000, 'max_height_mm' => 300]);

    expect(robotCodes([robotFor($this->team, ['weight_g' => 900])]))->toBe(['robot_measurements'])
        ->and(robotCodes([robotFor($this->team)]))->toBe(['robot_measurements']);
});

it('skips robot rules for guest registrations', function () {
    $this->category->update(['max_weight_g' => 1000]);

    expect(robotCodes([robotFor($this->team)], 'guest'))->toBe([]);
});
