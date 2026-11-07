<?php

use App\Domain\Access\Models\Invitation;
use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Notifications\InvitationNotification;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 02 §5–8: the departments console. The super admin creates departments
 * and sets their ceilings and admins; a department admin manages members and
 * delegates view/edit inside the ceiling; members only see the overview.
 */

beforeEach(function () {
    $this->seed(PermissionCatalogueSeeder::class);

    $this->superAdmin = User::factory()->withTwoFactor()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $this->superAdmin->assignRole(User::SUPER_ADMIN));
    $this->superAdmin = $this->superAdmin->fresh();

    $this->events = app(DepartmentService::class)->create(['name_en' => 'Events', 'name_ar' => 'الفعاليات']);
    app(DepartmentPermissionService::class)->setCeiling($this->events, ['events.view', 'events.edit', 'reports.view'], $this->superAdmin);

    $this->admin = User::factory()->withTwoFactor()->create(['name_en' => 'Events Lead']);
    $this->member = User::factory()->create(['name_en' => 'Salma Ali']);
    app(DepartmentService::class)->addMember($this->events, $this->admin, isAdmin: true);
    app(DepartmentService::class)->addMember($this->events, $this->member);
});

function memberCan(Department $department, User $user, string $permission): bool
{
    return app(WorkspaceContext::class)->within($department->workspace, fn () => $user->fresh()->can($permission));
}

describe('index and create', function () {
    it('lists departments with member and admin counts for the super admin', function () {
        $this->actingAs($this->superAdmin)
            ->get(route('console.departments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('console/departments/Index')
                ->has('departments.data', 1)
                ->where('departments.data.0', fn ($row) => $row['slug'] === 'events'
                    && $row['membersCount'] === 2
                    && $row['adminsCount'] === 1
                    && $row['isPublic'] === false));
    });

    it('searches departments by name', function () {
        app(DepartmentService::class)->create(['name_en' => 'Finance']);

        $this->actingAs($this->superAdmin)
            ->get(route('console.departments.index', ['q' => 'fin']))
            ->assertInertia(fn (Assert $page) => $page->has('departments.data', 1)->where('departments.data.0.slug', 'finance'));
    });

    it('is closed to everyone but the super admin', function () {
        $this->actingAs($this->admin)->get(route('console.departments.index'))->assertForbidden();
        $this->actingAs($this->admin)->post(route('console.departments.store'), ['name_en' => 'Nope'])->assertForbidden();
    });

    it('creates a department and opens it', function () {
        $this->actingAs($this->superAdmin)
            ->post(route('console.departments.store'), [
                'name_en' => 'Media',
                'name_ar' => 'الإعلام',
                'is_public' => true,
            ])
            ->assertRedirect(route('console.departments.show', 'media'));

        expect(Department::query()->where('slug', 'media')->first())
            ->name_ar->toBe('الإعلام')
            ->is_public->toBeTrue();
    });

    it('validates the department fields', function (array $input, string $field) {
        $this->actingAs($this->superAdmin)
            ->post(route('console.departments.store'), $input)
            ->assertSessionHasErrors($field);
    })->with([
        'missing name' => [['name_en' => ''], 'name_en'],
        'long name' => [['name_en' => str_repeat('a', 121)], 'name_en'],
        'taken slug' => [['name_en' => 'Other', 'slug' => 'events'], 'slug'],
        'bad slug' => [['name_en' => 'Other', 'slug' => 'not a slug'], 'slug'],
    ]);

    it('updates a department, keeping its own slug valid', function () {
        $this->actingAs($this->superAdmin)
            ->patch(route('console.departments.update', 'events'), [
                'name_en' => 'Events & Competitions',
                'slug' => 'events',
                'is_public' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($this->events->fresh())->name_en->toBe('Events & Competitions')->is_public->toBeTrue();

        $this->actingAs($this->admin)->patch(route('console.departments.update', 'events'), ['name_en' => 'X'])->assertForbidden();
    });
});

describe('the department page', function () {
    it('shows a plain member the overview only', function () {
        $this->actingAs($this->member)
            ->get(route('console.departments.show', 'events'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('console/departments/Show')
                ->where('department.name', 'Events')
                ->where('canManageMembers', false)
                ->where('canEditCeiling', false)
                ->missing('members')
                ->missing('invitations'));
    });

    it('shows an admin the members, their permissions and the matrix limited by the ceiling', function () {
        app(DepartmentPermissionService::class)->syncMember($this->events, $this->member, ['events.edit'], $this->admin);

        $this->actingAs($this->admin)
            ->get(route('console.departments.show', 'events'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageMembers', true)
                ->where('canEditCeiling', false)
                ->where('ceiling', ['events.edit', 'events.view', 'reports.view'])
                ->has('catalogue')
                ->has('members', 2)
                ->where('members', fn ($members) => collect($members)->firstWhere('name', 'Salma Ali')['permissions'] === ['events.edit', 'events.view']
                    && collect($members)->firstWhere('name', 'Events Lead')['isAdmin'] === true));
    });

    it('lets the super admin edit the ceiling from the page', function () {
        $this->actingAs($this->superAdmin)
            ->get(route('console.departments.show', 'events'))
            ->assertInertia(fn (Assert $page) => $page->where('canEditCeiling', true)->where('canManageMembers', true));
    });

    it('hides the page from people outside the department', function () {
        $this->actingAs(User::factory()->create())->get(route('console.departments.show', 'events'))->assertForbidden();
    });
});

describe('ceiling', function () {
    it('lets the super admin change the ceiling, revoking what falls outside it', function () {
        app(DepartmentPermissionService::class)->syncMember($this->events, $this->member, ['events.edit'], $this->admin);

        $this->actingAs($this->superAdmin)
            ->put(route('console.departments.ceiling', 'events'), ['permissions' => ['events.view', 'teams.view']])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($this->events->fresh()->ceilingNames()->sort()->values()->all())->toBe(['events.view', 'teams.view'])
            ->and(memberCan($this->events, $this->member, 'events.edit'))->toBeFalse()
            ->and(memberCan($this->events, $this->member, 'events.view'))->toBeTrue();
    });

    it('refuses reserved and approve permissions in a ceiling', function (string $permission) {
        $this->actingAs($this->superAdmin)
            ->put(route('console.departments.ceiling', 'events'), ['permissions' => [$permission]])
            ->assertSessionHasErrors('permissions.0');
    })->with(['departments.manage', 'users.impersonate', 'events.approve', 'members.manage', 'made.up']);

    it('is only for the super admin', function () {
        $this->actingAs($this->admin)
            ->put(route('console.departments.ceiling', 'events'), ['permissions' => ['events.view']])
            ->assertForbidden();
    });
});

describe('member permissions', function () {
    it('lets the admin grant view and edit inside the ceiling', function () {
        $this->actingAs($this->admin)
            ->put(route('console.departments.permissions.sync', ['events', $this->member]), ['permissions' => ['events.edit', 'reports.view']])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect(memberCan($this->events, $this->member, 'events.edit'))->toBeTrue()
            ->and(memberCan($this->events, $this->member, 'events.view'))->toBeTrue()
            ->and(memberCan($this->events, $this->member, 'reports.view'))->toBeTrue();
    });

    it('refuses permissions outside the ceiling as a validation error', function () {
        $this->actingAs($this->admin)
            ->put(route('console.departments.permissions.sync', ['events', $this->member]), ['permissions' => ['finance.edit']])
            ->assertSessionHasErrors('permissions');

        expect(memberCan($this->events, $this->member, 'finance.edit'))->toBeFalse();
    });

    it('is not open to plain members or while impersonating', function () {
        $other = User::factory()->create();
        app(DepartmentService::class)->addMember($this->events, $other);

        $this->actingAs($this->member)
            ->put(route('console.departments.permissions.sync', ['events', $other]), ['permissions' => ['events.view']])
            ->assertForbidden();

        $this->actingAs($this->superAdmin)->post(route('impersonate.take', $this->admin));

        $this->put(route('console.departments.permissions.sync', ['events', $other]), ['permissions' => ['events.view']])
            ->assertForbidden();
    });

    it('returns 404 for someone who is not in the department', function () {
        $this->actingAs($this->admin)
            ->put(route('console.departments.permissions.sync', ['events', User::factory()->create()]), ['permissions' => []])
            ->assertNotFound();
    });
});

describe('members', function () {
    it('lets the super admin promote and demote a department admin', function () {
        $this->actingAs($this->superAdmin)
            ->patch(route('console.departments.members.admin', ['events', $this->member]), ['is_admin' => true])
            ->assertRedirect();

        expect($this->events->fresh()->isAdmin($this->member))->toBeTrue();

        $this->actingAs($this->superAdmin)
            ->patch(route('console.departments.members.admin', ['events', $this->member]), ['is_admin' => false]);

        expect($this->events->fresh()->isAdmin($this->member))->toBeFalse();

        $this->actingAs($this->admin)
            ->patch(route('console.departments.members.admin', ['events', $this->member]), ['is_admin' => true])
            ->assertForbidden();
    });

    it('lets the admin remove a member, revoking every permission', function () {
        app(DepartmentPermissionService::class)->syncMember($this->events, $this->member, ['events.edit'], $this->admin);

        $this->actingAs($this->admin)
            ->delete(route('console.departments.members.destroy', ['events', $this->member]))
            ->assertRedirect();

        expect($this->events->fresh()->hasMember($this->member))->toBeFalse()
            ->and(memberCan($this->events, $this->member, 'events.view'))->toBeFalse();
    });

    it('does not let a plain member remove anyone', function () {
        $this->actingAs($this->member)
            ->delete(route('console.departments.members.destroy', ['events', $this->admin]))
            ->assertForbidden();
    });
});

describe('invitations', function () {
    it('lets the admin invite a member and lists it as pending', function () {
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(route('console.departments.invitations.store', 'events'), ['email' => 'new@example.com', 'role' => 'department-member'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        Notification::assertSentOnDemand(InvitationNotification::class);

        $this->actingAs($this->admin)
            ->get(route('console.departments.show', 'events'))
            ->assertInertia(fn (Assert $page) => $page->has('invitations', 1)->where('invitations.0.email', 'new@example.com'));
    });

    it('lets only the super admin invite another admin', function () {
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(route('console.departments.invitations.store', 'events'), ['email' => 'boss@example.com', 'role' => 'department-admin'])
            ->assertSessionHasErrors('role');

        $this->actingAs($this->superAdmin)
            ->post(route('console.departments.invitations.store', 'events'), ['email' => 'boss@example.com', 'role' => 'department-admin'])
            ->assertSessionHasNoErrors();
    });

    it('refuses to invite an existing member', function () {
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(route('console.departments.invitations.store', 'events'), ['email' => $this->member->email, 'role' => 'department-member'])
            ->assertSessionHasErrors('email');
    });

    it('lets the admin revoke a pending invitation', function () {
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(route('console.departments.invitations.store', 'events'), ['email' => 'new@example.com', 'role' => 'department-member']);

        $invitation = Invitation::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('console.departments.invitations.destroy', ['events', $invitation]))
            ->assertRedirect();

        expect($invitation->fresh()->revoked_at)->not->toBeNull();
    });
});
