<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamMemberService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 10 §2-3: every page receives the active workspace, the viewer's permissions
 * there, the team's features and a sidebar built on the server from both.
 * The client only hides things; the server still enforces every rule.
 */

beforeEach(function () {
    $this->seed(PermissionCatalogueSeeder::class);
    $this->members = app(TeamMemberService::class);
    $this->team = Team::factory()->create(['name' => 'Robo Lions']);
});

it('shares the team workspace, the viewer\'s permissions and the team features', function () {
    $captain = User::factory()->create();
    $this->members->join($this->team, $captain, TeamRole::Captain);

    $this->actingAs($captain)->get(route('workspace.dashboard', $this->team->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspace.type', 'team')
            ->where('workspace.slug', $this->team->slug)
            ->where('workspace.name', 'Robo Lions')
            ->where('can', fn ($values) => $values['members.invite'] === true)
            ->where('can', fn ($values) => $values['team.settings'] === true)
            ->where('features', fn ($values) => $values['members.max'] === 8)
            ->where('features', fn ($values) => $values['robots.create'] === true)
            ->where('features', fn ($values) => $values['events.register'] === false));
});

it('reflects lower team roles in the shared permissions', function () {
    $viewer = User::factory()->create();
    $this->members->join($this->team, $viewer, TeamRole::Viewer);

    $this->actingAs($viewer)->get(route('workspace.dashboard', $this->team->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can', fn ($values) => $values['team.view'] === true)
            ->where('can', fn ($values) => $values['members.invite'] === false)
            ->where('can', fn ($values) => $values['robots.edit'] === false));
});

it('shares no workspace outside one', function () {
    $this->actingAs(User::factory()->create())->get(route('hub'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspace', null)
            ->where('can', [])
            ->where('features', []));
});

it('shares a department member\'s granted permissions in that department', function () {
    $superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $superAdmin->assignRole(User::SUPER_ADMIN));
    $department = app(DepartmentService::class)->create(['name_en' => 'Events']);
    app(DepartmentPermissionService::class)->setCeiling($department, ['events.view', 'events.edit'], $superAdmin->fresh());
    $member = User::factory()->create();
    $admin = User::factory()->create();
    app(DepartmentService::class)->addMember($department, $member);
    app(DepartmentService::class)->addMember($department, $admin, isAdmin: true);
    app(DepartmentPermissionService::class)->syncMember($department, $member, ['events.view'], $admin);

    $this->actingAs($member)->get(route('console.departments.dashboard', $department->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspace.type', 'department')
            ->where('can', fn ($values) => $values['events.view'] === true)
            ->where('can', fn ($values) => $values['events.edit'] === false));
});

it('builds the team sidebar from routes, permissions and features', function () {
    Route::middleware(['web', 'auth', 'workspace:team'])->prefix('t/{team:slug}')->name('workspace.')->group(function () {
        Route::get('catalogue', fn (Team $team) => 'ok')->name('catalogue.index');
        Route::get('settings', fn (Team $team) => 'ok')->name('settings.edit');
    });
    Route::getRoutes()->refreshNameLookups();

    $captain = User::factory()->create();
    $member = User::factory()->create();
    $this->members->join($this->team, $captain, TeamRole::Captain);
    $this->members->join($this->team, $member, TeamRole::Member);

    $navKeys = fn (User $user) => collect(
        $this->actingAs($user)->get(route('workspace.dashboard', $this->team->slug))->inertiaProps('nav'),
    )->pluck('key')->all();

    expect($navKeys($captain))->toContain('dashboard', 'catalogue', 'settings')
        ->and($navKeys($member))->toContain('dashboard', 'catalogue')
        ->and($navKeys($member))->not->toContain('settings');

    app(TeamFeatures::class)->override($this->team, TeamFeature::PartsAccess, false);

    expect($navKeys($captain))->not->toContain('catalogue');
});

it('lists the viewer\'s workspaces for the switcher', function () {
    $user = User::factory()->create();
    $this->members->join($this->team, $user, TeamRole::Member);

    $this->actingAs($user)->get(route('hub'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('auth.workspaces', 1)
            ->where('auth.workspaces.0.type', 'team')
            ->where('auth.workspaces.0.name', 'Robo Lions'));
});
