<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamMemberService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PermissionCatalogueSeeder::class);
    $this->members = app(TeamMemberService::class);
});

function makeSuperAdmin(bool $withTwoFactor = false): User
{
    $user = ($withTwoFactor ? User::factory()->withTwoFactor() : User::factory())->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $user->assignRole(User::SUPER_ADMIN));

    return $user->fresh();
}

it('sends the super admin to the console after login', function () {
    $admin = makeSuperAdmin();

    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('console.dashboard'));
});

it('sends a member of exactly one team straight to that team', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $this->members->join($team, $user, TeamRole::Member);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('workspace.dashboard', $team->slug));
});

it('sends people with several workspaces to the hub and lists them', function () {
    $user = User::factory()->create();
    $teamA = Team::factory()->create(['name' => 'Alpha Bots']);
    $teamB = Team::factory()->create(['name' => 'Beta Bots']);
    $this->members->join($teamA, $user, TeamRole::Captain);
    $this->members->join($teamB, $user, TeamRole::Viewer);
    app(DepartmentService::class)->addMember(app(DepartmentService::class)->create(['name_en' => 'Events']), $user);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('hub'));

    $this->actingAs($user)->get(route('hub'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hub')
            ->has('teams', 2)
            ->where('teams.0.name', 'Alpha Bots')
            ->where('teams.0.role', 'captain')
            ->where('teams.1.role', 'viewer')
            ->has('departments', 1)
            ->where('isSuperAdmin', false));
});

it('hides other teams: a member of team A gets 404 on team B', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $user = User::factory()->create();
    $this->members->join($teamA, $user, TeamRole::Captain);

    $this->actingAs($user)->get(route('workspace.dashboard', $teamA->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('workspace/Dashboard')
            ->where('role', 'captain')
            ->where('stats.members.used', 1));

    $this->actingAs($user)->get(route('workspace.dashboard', $teamB->slug))->assertNotFound();
});

it('forbids department pages to people outside the department', function () {
    $department = app(DepartmentService::class)->create(['name_en' => 'Finance']);
    $outsider = User::factory()->create();
    $insider = User::factory()->create();
    app(DepartmentService::class)->addMember($department, $insider);

    $this->actingAs($outsider)->get(route('console.departments.show', $department->slug))->assertForbidden();
    $this->actingAs($insider)->get(route('console.departments.show', $department->slug))->assertOk();
});

it('keeps the platform console for the super admin only', function () {
    $this->actingAs(User::factory()->create())->get(route('console.dashboard'))->assertForbidden();
    $this->actingAs(makeSuperAdmin(withTwoFactor: true))->get(route('console.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('console/Dashboard'));
});

it('lets members read but not change a suspended team', function () {
    Route::middleware(['web', 'auth', 'workspace:team', 'team.active'])
        ->post('t/{team:slug}/write-probe', fn (Team $team) => 'written');

    $team = Team::factory()->suspended()->create();
    $user = User::factory()->create();
    $this->members->join($team, $user, TeamRole::Captain);

    $this->actingAs($user)->get(route('workspace.dashboard', $team->slug))->assertOk();
    $this->actingAs($user)->post("t/{$team->slug}/write-probe")->assertForbidden();

    $active = Team::factory()->create();
    $this->members->join($active, $user, TeamRole::Captain);
    $this->actingAs($user)->post("t/{$active->slug}/write-probe")->assertOk();
});

it('redirects the old dashboard url to the right home', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $this->members->join($team, $user, TeamRole::Member);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('workspace.dashboard', $team->slug));
});
