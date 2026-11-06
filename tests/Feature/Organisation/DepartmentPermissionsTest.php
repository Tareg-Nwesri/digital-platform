<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;

beforeEach(function () {
    $this->seed(PermissionCatalogueSeeder::class);

    $this->superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $this->superAdmin->assignRole(User::SUPER_ADMIN));

    $this->departments = app(DepartmentService::class);
    $this->permissions = app(DepartmentPermissionService::class);

    $this->events = $this->departments->create(['name_en' => 'Events']);
    $this->permissions->setCeiling($this->events, ['events.view', 'events.edit', 'reports.view'], $this->superAdmin);

    $this->admin = User::factory()->create();
    $this->member = User::factory()->create();
    $this->departments->addMember($this->events, $this->admin, isAdmin: true);
    $this->departments->addMember($this->events, $this->member);
});

function canIn(Department $department, User $user, string $permission): bool
{
    return app(WorkspaceContext::class)->within($department->workspace, fn () => $user->fresh()->can($permission));
}

it('lets a department admin grant view and edit inside the ceiling, implying view', function () {
    $this->permissions->syncMember($this->events, $this->member, ['events.edit'], $this->admin);

    expect(canIn($this->events, $this->member, 'events.edit'))->toBeTrue()
        ->and(canIn($this->events, $this->member, 'events.view'))->toBeTrue()
        ->and(canIn($this->events, $this->member, 'events.approve'))->toBeFalse();
});

it('gives department admins the ceiling plus approvals and member management', function () {
    expect(canIn($this->events, $this->admin, 'events.edit'))->toBeTrue()
        ->and(canIn($this->events, $this->admin, 'events.approve'))->toBeTrue()
        ->and(canIn($this->events, $this->admin, 'members.manage'))->toBeTrue()
        ->and(canIn($this->events, $this->admin, 'finance.view'))->toBeFalse();
});

it('refuses permissions outside the ceiling, approvals and reserved ones', function (string $permission) {
    $this->permissions->syncMember($this->events, $this->member, [$permission], $this->admin);
})->with(['finance.view', 'events.approve', 'users.impersonate', 'departments.manage'])
    ->throws(DomainRuleViolation::class);

it('never allows reserved or approve permissions in a ceiling', function (string $permission) {
    $this->permissions->setCeiling($this->events, [$permission], $this->superAdmin);
})->with(['tiers.manage', 'backups.run', 'events.approve'])
    ->throws(DomainRuleViolation::class);

it('only lets the super admin set the ceiling', function () {
    $this->permissions->setCeiling($this->events, ['events.view'], $this->admin);
})->throws(DomainRuleViolation::class);

it('only lets an admin of the department delegate', function () {
    $outsider = User::factory()->create();

    $this->permissions->syncMember($this->events, $this->member, ['events.view'], $outsider);
})->throws(DomainRuleViolation::class);

it('revokes member permissions immediately when the ceiling is lowered', function () {
    $this->permissions->syncMember($this->events, $this->member, ['events.edit', 'reports.view'], $this->admin);

    $this->permissions->setCeiling($this->events, ['events.view', 'reports.view'], $this->superAdmin);

    expect(canIn($this->events, $this->member, 'events.edit'))->toBeFalse()
        ->and(canIn($this->events, $this->member, 'events.view'))->toBeTrue()
        ->and(canIn($this->events, $this->admin, 'events.edit'))->toBeFalse()
        ->and($this->permissions->assertConsistent($this->events))->toBe([]);
});

it('removes edit when view is revoked', function () {
    $this->permissions->syncMember($this->events, $this->member, ['events.edit'], $this->admin);

    $this->permissions->revoke($this->events, $this->member, 'events.view', $this->admin);

    expect($this->permissions->memberPermissions($this->events, $this->member)->all())->toBe([]);
});

it('keeps permissions of one department out of another', function () {
    $finance = $this->departments->create(['name_en' => 'Finance']);
    $this->departments->addMember($finance, $this->member);
    $this->permissions->syncMember($this->events, $this->member, ['events.view'], $this->admin);

    expect(canIn($finance, $this->member, 'events.view'))->toBeFalse();
});

it('revokes every department permission when a member is removed', function () {
    $this->permissions->syncMember($this->events, $this->member, ['events.edit'], $this->admin);

    $this->departments->removeMember($this->events, $this->member);

    expect($this->events->hasMember($this->member))->toBeFalse()
        ->and(canIn($this->events, $this->member, 'events.view'))->toBeFalse();
});
