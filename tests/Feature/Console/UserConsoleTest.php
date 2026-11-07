<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamMemberService;
use App\Domain\Teams\Services\TeamService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 01 §6/§8: the super admin's users list, with the active switch and
 * the impersonate button. Deactivated users cannot sign in, and an open
 * session ends on their next request.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);

    $this->superAdmin = User::factory()->create(['name_en' => 'Platform Admin']);
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $this->superAdmin->assignRole(User::SUPER_ADMIN));
    $this->superAdmin = $this->superAdmin->fresh();

    $this->sara = User::factory()->create(['name_en' => 'Coach Sara', 'email' => 'sara@example.test']);
    $this->omar = User::factory()->inactive()->create(['name_en' => 'Omar Inactive']);
    $this->offline = User::factory()->withoutLogin()->create(['name_en' => 'Offline Member']);

    $events = app(DepartmentService::class)->create(['name_en' => 'Events']);
    app(DepartmentService::class)->addMember($events, $this->sara);

    $team = app(TeamService::class)->create([
        'name' => 'Falcons',
        'tier_id' => Tier::query()->where('slug', 'starter')->value('id'),
    ], $this->superAdmin, approved: true);
    app(TeamMemberService::class)->join($team, $this->sara, TeamRole::Coach, $this->superAdmin);
});

it('lists every user for the super admin with memberships and flags', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('console.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/users/Index')
            ->has('users.data', 4)
            ->where('users.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['name'] === 'Coach Sara'
                && $row['email'] === 'sara@example.test'
                && $row['memberships'] === 2
                && $row['isActive'] === true
                && $row['canImpersonate'] === true
                && $row['canToggleActive'] === true))
            ->where('users.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['name'] === 'Platform Admin'
                && $row['isSuperAdmin'] === true
                && $row['canImpersonate'] === false
                && $row['canToggleActive'] === false))
            ->where('users.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['name'] === 'Offline Member'
                && $row['canLogin'] === false
                && $row['canImpersonate'] === false)));
});

it('searches by name or email', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('console.users.index', ['q' => 'sara@']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Coach Sara'));
});

it('filters by active state', function (string $value, int $count) {
    $this->actingAs($this->superAdmin)
        ->get(route('console.users.index', ['filter' => ['active' => $value]]))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', $count));
})->with([
    'active' => ['1', 3],
    'inactive' => ['0', 1],
]);

it('is for the super admin only', function () {
    $this->actingAs($this->sara)->get(route('console.users.index'))->assertForbidden();
});

it('shows the users item in the console navigation', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('console.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav', fn ($items) => collect($items)->contains(fn ($item) => $item['key'] === 'users')));
});

it('deactivates and reactivates a user and logs it', function () {
    $this->actingAs($this->superAdmin)
        ->patch(route('console.users.active', $this->sara), ['active' => false])
        ->assertRedirect();

    expect($this->sara->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->superAdmin)
        ->patch(route('console.users.active', $this->sara), ['active' => true])
        ->assertRedirect();

    expect($this->sara->fresh()->is_active)->toBeTrue();
});

it('refuses to deactivate yourself or another super admin', function () {
    $other = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $other->assignRole(User::SUPER_ADMIN));

    $this->actingAs($this->superAdmin)
        ->patch(route('console.users.active', $this->superAdmin), ['active' => false])
        ->assertForbidden();
    $this->actingAs($this->superAdmin)
        ->patch(route('console.users.active', $other), ['active' => false])
        ->assertForbidden();

    expect($this->superAdmin->fresh()->is_active)->toBeTrue()
        ->and($other->fresh()->is_active)->toBeTrue();
});

it('validates the active flag', function () {
    $this->actingAs($this->superAdmin)
        ->patch(route('console.users.active', $this->sara), ['active' => 'maybe'])
        ->assertSessionHasErrors('active');
});

it('does not let other users toggle active', function () {
    $this->actingAs($this->sara)
        ->patch(route('console.users.active', $this->omar), ['active' => true])
        ->assertForbidden();
});

it('refuses sign-in for a deactivated user', function () {
    $this->post(route('login.store'), ['email' => $this->omar->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('signs out a deactivated user on their next request', function () {
    $this->actingAs($this->omar)->get(route('hub'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('returns to the users list after leaving impersonation', function () {
    $this->actingAs($this->superAdmin)->post(route('impersonate.take', $this->sara));

    $this->post(route('impersonate.leave'))->assertRedirect(route('console.users.index'));
});
