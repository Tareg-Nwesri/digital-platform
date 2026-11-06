<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamMemberService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;

/*
 * SRS FR-ACC-04 / NFR-SEC-03: 2FA is mandatory for the super admin and department admins.
 * Until they enable it, every app page sends them to the security settings.
 */

beforeEach(fn () => $this->seed(PermissionCatalogueSeeder::class));

function superAdmin(bool $withTwoFactor): User
{
    $factory = User::factory();
    $user = ($withTwoFactor ? $factory->withTwoFactor() : $factory)->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $user->assignRole(User::SUPER_ADMIN));

    return $user->fresh();
}

it('sends a super admin without 2fa to the security settings', function () {
    $this->actingAs(superAdmin(false))
        ->get(route('console.dashboard'))
        ->assertRedirect(route('security.edit'));
});

it('lets a super admin with confirmed 2fa through', function () {
    $this->actingAs(superAdmin(true))->get(route('console.dashboard'))->assertOk();
});

it('sends a department admin without 2fa to the security settings', function () {
    $department = app(DepartmentService::class)->create(['name_en' => 'Events']);
    $admin = User::factory()->create();
    app(DepartmentService::class)->addMember($department, $admin, isAdmin: true);

    $this->actingAs($admin)->get(route('hub'))->assertRedirect(route('security.edit'));
});

it('does not require 2fa from ordinary team members', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    app(TeamMemberService::class)->join($team, $user, TeamRole::Captain);

    $this->actingAs($user)->get(route('workspace.dashboard', $team->slug))->assertOk();
});

it('still lets admins reach security settings and log out while 2fa is missing', function () {
    $admin = superAdmin(false);

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk();

    $this->actingAs($admin)->post(route('logout'))->assertRedirect();
});
