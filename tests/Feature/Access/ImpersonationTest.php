<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * SRS FR-ACC-12..15: the super admin can act as another user for support,
 * with a visible banner, blocked sensitive actions and an audit trail.
 */

beforeEach(function () {
    $this->seed(PermissionCatalogueSeeder::class);

    $this->admin = User::factory()->withTwoFactor()->create(['name_en' => 'Platform Admin']);
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $this->admin->assignRole(User::SUPER_ADMIN));
    $this->admin = $this->admin->fresh();

    $this->target = User::factory()->create(['name_en' => 'Coach Sara']);
});

it('lets the super admin act as another user and shows the banner data', function () {
    $this->actingAs($this->admin)
        ->post(route('impersonate.take', $this->target))
        ->assertRedirect(route('hub'));

    $this->assertAuthenticatedAs($this->target);

    $this->get(route('hub'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.id', $this->target->id)
        ->where('auth.impersonating.originalName', 'Platform Admin'));
});

it('returns to the original account when leaving', function () {
    $this->actingAs($this->admin)->post(route('impersonate.take', $this->target));

    $this->post(route('impersonate.leave'))->assertRedirect();

    $this->assertAuthenticatedAs($this->admin);
});

it('refuses impersonation by anyone but the super admin', function () {
    $this->actingAs($this->target)
        ->post(route('impersonate.take', User::factory()->create()))
        ->assertForbidden();
});

it('never impersonates another super admin or a member without login', function () {
    $otherAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $otherAdmin->assignRole(User::SUPER_ADMIN));
    $offline = User::factory()->withoutLogin()->create();

    $this->actingAs($this->admin)->post(route('impersonate.take', $otherAdmin))->assertForbidden();
    $this->actingAs($this->admin)->post(route('impersonate.take', $offline))->assertForbidden();
});

it('blocks security settings, password changes and nested impersonation while impersonating', function () {
    $this->actingAs($this->admin)->post(route('impersonate.take', $this->target));

    $this->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))->assertForbidden();
    $this->put(route('user-password.update'), [
        'current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
    ])->assertForbidden();
    $this->post(route('impersonate.take', User::factory()->create()))->assertForbidden();
});

it('logs when impersonation starts and ends with both users', function () {
    $this->actingAs($this->admin)->post(route('impersonate.take', $this->target));
    $this->post(route('impersonate.leave'));

    $started = Activity::query()->where('event', 'impersonation.started')->sole();
    $ended = Activity::query()->where('event', 'impersonation.ended')->sole();

    expect($started->causer_id)->toBe($this->admin->id)
        ->and($started->subject_id)->toBe($this->target->id)
        ->and($started->properties['ip'])->not->toBeNull()
        ->and($ended->causer_id)->toBe($this->admin->id);
});
