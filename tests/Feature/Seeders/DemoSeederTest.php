<?php

use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamMemberService;
use App\Models\User;
use Database\Seeders\DemoSeeder;

/*
 * LLD 11 §5: DemoSeeder gives a local or staging install a realistic
 * organisation to click through, and never runs in production.
 */

beforeEach(function () {
    config(['platform.super_admin.email' => 'admin@digital-platform.test']);
});

it('seeds the four departments, each with an admin, members and a ceiling matching its name', function () {
    $this->seed(DemoSeeder::class);

    $expectedAreas = [
        'events' => 'events.',
        'procurement' => 'catalogue.',
        'finance' => 'finance.',
        'media' => 'content.',
    ];

    foreach ($expectedAreas as $slug => $area) {
        $department = Department::query()->where('slug', $slug)->firstOrFail();

        expect($department->admins()->count())->toBe(1)
            ->and($department->members()->wherePivot('is_admin', false)->count())->toBeGreaterThanOrEqual(2)
            ->and($department->ceilingNames()->filter(fn (string $name) => str_starts_with($name, $area)))->not->toBeEmpty()
            ->and(app(DepartmentPermissionService::class)->assertConsistent($department))->toBe([]);
    }
});

it('delegates permissions to department members inside the ceiling', function () {
    $this->seed(DemoSeeder::class);

    $events = Department::query()->where('slug', 'events')->firstOrFail();
    $members = $events->members()->wherePivot('is_admin', false)->get();
    $granted = $members->map(fn (User $member) => app(DepartmentPermissionService::class)->memberPermissions($events, $member));

    expect($granted->flatten()->unique()->values()->all())->toContain('events.edit', 'events.view')
        ->and($granted->flatten())->not->toContain('events.approve');
});

it('seeds a starter team and a competition team with roles and members without a login', function () {
    $this->seed(DemoSeeder::class);

    $starter = Team::query()->whereHas('tier', fn ($tier) => $tier->where('slug', 'starter'))->firstOrFail();
    $competition = Team::query()->whereHas('tier', fn ($tier) => $tier->where('slug', 'competition'))->firstOrFail();
    $roles = app(TeamMemberService::class);

    expect($starter->isActive())->toBeTrue()
        ->and($competition->isActive())->toBeTrue()
        ->and($roles->captain($starter))->not->toBeNull()
        ->and($roles->captain($competition))->not->toBeNull()
        ->and($competition->department?->slug)->toBe('events');

    $withoutLogin = collect([$starter, $competition])
        ->flatMap(fn (Team $team) => $team->members()->where('can_login', false)->get()->map(fn (User $user) => [$team, $user]));

    expect($withoutLogin)->toHaveCount(2)
        ->and($withoutLogin->map(fn (array $pair) => $pair[1]->email)->filter())->toBeEmpty()
        ->and($withoutLogin->map(fn (array $pair) => $roles->roleOf(...$pair))->unique()->values()->all())
        ->toBe([TeamRole::Member]);
});

it('gives every demo login a verified email so it can sign in straight away', function () {
    $this->seed(DemoSeeder::class);

    $logins = User::query()->where('can_login', true)->where('email', 'like', '%@demo.test')->get();

    expect($logins)->not->toBeEmpty()
        ->and($logins->whereNull('email_verified_at'))->toBeEmpty()
        ->and($logins->whereNull('password'))->toBeEmpty();
});

it('can run twice without duplicating anything', function () {
    $this->seed(DemoSeeder::class);
    $counts = [Department::query()->count(), Team::query()->count(), User::query()->count()];

    $this->seed(DemoSeeder::class);

    expect([Department::query()->count(), Team::query()->count(), User::query()->count()])->toBe($counts);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->app->make(DemoSeeder::class)->setContainer($this->app)->__invoke();

    expect(Department::query()->count())->toBe(0)
        ->and(Team::query()->count())->toBe(0);
});
