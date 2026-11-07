<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Services\DepartmentService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;

/*
 * SRS FR-ACC-04 (revised 2026-11-07): two-factor authentication is optional
 * for every user, admins included.
 */

beforeEach(fn () => $this->seed(PermissionCatalogueSeeder::class));

it('lets a super admin without 2fa use the console', function () {
    $admin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $admin->assignRole(User::SUPER_ADMIN));

    $this->actingAs($admin->fresh())->get(route('console.dashboard'))->assertOk();
    $this->actingAs($admin->fresh())->get(route('console.departments.index'))->assertOk();
});

it('lets a department admin without 2fa open their department', function () {
    $department = app(DepartmentService::class)->create(['name_en' => 'Events']);
    $admin = User::factory()->create();
    app(DepartmentService::class)->addMember($department, $admin, isAdmin: true);

    $this->actingAs($admin)->get(route('console.departments.show', $department->slug))->assertOk();
    $this->actingAs($admin)->get(route('hub'))->assertOk();
});
