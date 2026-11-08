<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Content\Services\SettingsRepository;
use App\Domain\Events\Services\RegistrationService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 08 §1: platform settings live in the settings table, fall back to
 * config/platform.php, and only the super admin changes them
 * (settings.manage is reserved).
 */

beforeEach(function () {
    $this->seed(PermissionCatalogueSeeder::class);

    $this->superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $this->superAdmin->assignRole(User::SUPER_ADMIN));
    $this->superAdmin = $this->superAdmin->fresh();

    $this->settings = app(SettingsRepository::class);
});

it('falls back to the config defaults and stores overrides', function () {
    expect($this->settings->get('events.auto_promote_waitlist'))->toBeTrue()
        ->and($this->settings->get('retention.months'))->toBe(24);

    $this->settings->set('events.auto_promote_waitlist', false, $this->superAdmin);

    expect(app(SettingsRepository::class)->get('events.auto_promote_waitlist'))->toBeFalse()
        ->and(RegistrationService::autoPromotes())->toBeFalse();

    $this->assertDatabaseHas('settings', ['key' => 'events.auto_promote_waitlist', 'updated_by' => $this->superAdmin->id]);
});

it('shows and saves settings for the super admin only', function () {
    $media = app(DepartmentService::class)->create(['name_en' => 'Media']);

    $this->actingAs($this->superAdmin)
        ->get(route('console.settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/settings/Index')
            ->where('settings.events_auto_promote_waitlist', true)
            ->where('settings.retention_months', 24)
            ->has('departments', 1));

    $this->actingAs($this->superAdmin)
        ->put(route('console.settings.update'), [
            'inbox_department_id' => $media->id,
            'events_auto_promote_waitlist' => false,
            'public_contact_email_display' => 'hello@example.test',
            'retention_months' => 36,
        ])
        ->assertRedirect();

    expect($this->settings->get('inbox.department_id'))->toBe($media->id)
        ->and($this->settings->get('events.auto_promote_waitlist'))->toBeFalse()
        ->and($this->settings->get('public.contact_email_display'))->toBe('hello@example.test')
        ->and($this->settings->get('retention.months'))->toBe(36);

    $this->actingAs(User::factory()->create())->get(route('console.settings.index'))->assertForbidden();
});

it('validates settings', function (array $payload, string $error) {
    $this->actingAs($this->superAdmin)
        ->put(route('console.settings.update'), array_merge([
            'inbox_department_id' => null,
            'events_auto_promote_waitlist' => true,
            'public_contact_email_display' => null,
            'retention_months' => 24,
        ], $payload))
        ->assertSessionHasErrors($error);
})->with([
    'unknown department' => [['inbox_department_id' => 99999], 'inbox_department_id'],
    'retention too short' => [['retention_months' => 1], 'retention_months'],
    'bad email' => [['public_contact_email_display' => 'not-an-email'], 'public_contact_email_display'],
]);
