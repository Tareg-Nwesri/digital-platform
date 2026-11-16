<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamMemberService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;

/*
 * The sidebar is grouped by domain: every item names its group and the
 * groups come out in a fixed order, each in one block.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);
});

/**
 * @param  list<array{group: string}>  $nav
 * @return list<string>
 */
function groupOrder(array $nav): array
{
    return array_values(array_unique(array_column($nav, 'group')));
}

it('groups the team workspace into team, work, and events and budget', function () {
    $team = Team::factory()->create(['tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
    $captain = User::factory()->create();
    app(TeamMemberService::class)->join($team, $captain, TeamRole::Captain);

    $nav = $this->actingAs($captain)->get(route('workspace.dashboard', $team->slug))->inertiaProps('nav');

    expect(groupOrder($nav))->toBe(['team', 'work', 'activities'])
        ->and(collect($nav)->where('group', 'team')->pluck('key')->all())->toBe(['dashboard', 'members', 'settings'])
        ->and(collect($nav)->where('group', 'activities')->pluck('key')->all())->toBe(['events', 'budget'])
        ->and($nav[0]['groupLabel'])->toBe('Team');
});

it('groups the console by domain for the super admin', function () {
    $admin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $admin->assignRole(User::SUPER_ADMIN));

    $nav = $this->actingAs($admin)->get(route('console.dashboard'))->inertiaProps('nav');

    expect(groupOrder($nav))->toBe(['platform', 'teams', 'catalogue', 'finance', 'communication'])
        ->and(collect($nav)->where('group', 'teams')->pluck('key')->all())->toBe(['teams', 'events'])
        ->and(collect($nav)->where('group', 'communication')->pluck('key')->all())->toBe(['content', 'inbox']);
});
