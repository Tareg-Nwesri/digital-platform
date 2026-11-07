<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamService;
use App\Models\User;
use Database\Factories\TierFactory;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * LLD 03 §6/§9: tiers set every team's default features. Only the super
 * admin manages them (tiers.manage is reserved). Editing a tier re-resolves
 * its teams; overrides still win. A tier in use cannot be deleted, and
 * exactly one tier is the default for new teams.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);

    $this->superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $this->superAdmin->assignRole(User::SUPER_ADMIN));
    $this->superAdmin = $this->superAdmin->fresh();

    $this->starter = Tier::query()->where('slug', 'starter')->firstOrFail();
    $this->competition = Tier::query()->where('slug', 'competition')->firstOrFail();

    $this->team = app(TeamService::class)->create(['name' => 'Falcons', 'tier_id' => $this->starter->id], $this->superAdmin);
});

/**
 * @param  array<string, bool|int>  $changes
 * @return list<array{name: string, value: bool|int}>
 */
function tierFeaturePayload(array $changes = []): array
{
    return collect(array_merge(TierFactory::starterFeatures(), $changes))
        ->map(fn ($value, $name) => ['name' => $name, 'value' => $value])
        ->values()
        ->all();
}

it('lists the tiers with their features, team counts and the default', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('console.tiers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/tiers/Index')
            ->has('tiers', 2)
            ->where('tiers.0.slug', 'starter')
            ->where('tiers.0.isDefault', true)
            ->where('tiers.0.teamsCount', 1)
            ->where('tiers.0.features', fn ($features) => collect($features)->firstWhere('name', 'members.max')['value'] === 8)
            ->where('tiers.1.slug', 'competition')
            ->where('tiers.1.teamsCount', 0)
            ->has('featureNames', count(TeamFeature::cases())));
});

it('is for the super admin only, even for department admins', function () {
    $department = app(DepartmentService::class)->create(['name_en' => 'Events']);
    app(DepartmentPermissionService::class)->setCeiling($department, ['teams.view', 'teams.edit'], $this->superAdmin);
    $admin = User::factory()->create();
    app(DepartmentService::class)->addMember($department, $admin, isAdmin: true);

    $this->actingAs($admin)->get(route('console.tiers.index'))->assertForbidden();
    $this->actingAs($admin)->post(route('console.tiers.store'), ['name_en' => 'X', 'features' => tierFeaturePayload()])->assertForbidden();
    $this->actingAs($admin)->patch(route('console.tiers.update', $this->starter), ['name_en' => 'X', 'features' => tierFeaturePayload()])->assertForbidden();
    $this->actingAs($admin)->delete(route('console.tiers.destroy', $this->competition))->assertForbidden();
});

it('creates a tier at the end of the list with a unique slug', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('console.tiers.store'), [
            'name_en' => 'Academy',
            'name_ar' => 'الأكاديمية',
            'features' => tierFeaturePayload(['members.max' => 12, 'events.register' => true]),
        ])
        ->assertRedirect(route('console.tiers.index'));

    $tier = Tier::query()->where('slug', 'academy')->firstOrFail();

    expect($tier->name_ar)->toBe('الأكاديمية')
        ->and($tier->features['members.max'])->toBe(12)
        ->and($tier->features['events.register'])->toBeTrue()
        ->and($tier->is_default)->toBeFalse()
        ->and($tier->position)->toBe(3);
});

it('validates names, slugs and feature values', function (array $payload, string $error) {
    $this->actingAs($this->superAdmin)
        ->post(route('console.tiers.store'), array_merge([
            'name_en' => 'Academy',
            'features' => tierFeaturePayload(),
        ], $payload))
        ->assertSessionHasErrors($error);
})->with([
    'missing name' => [['name_en' => ''], 'name_en'],
    'taken slug' => [['slug' => 'starter'], 'slug'],
    'bad slug' => [['slug' => 'not a slug'], 'slug'],
    'unknown feature' => [['features' => [['name' => 'rockets.launch', 'value' => true]]], 'features.0.name'],
    'limit too high' => [['features' => tierFeaturePayload(['members.max' => 900])], 'features.0.value'],
    'limit zero' => [['features' => tierFeaturePayload(['members.max' => 0])], 'features.0.value'],
    'not a boolean' => [['features' => tierFeaturePayload(['projects.create' => 'perhaps'])], 'features.1.value'],
    'duplicate feature' => [['features' => [['name' => 'members.max', 'value' => 3], ['name' => 'members.max', 'value' => 4]]], 'features.1.name'],
]);

it('updates a tier and its teams pick up the new values, but overrides still win', function () {
    $features = app(TeamFeatures::class);
    $features->override($this->team, TeamFeature::PartsSuggest, false, $this->superAdmin);

    expect($features->value($this->team, TeamFeature::MembersMax))->toBe(8);

    $this->actingAs($this->superAdmin)
        ->patch(route('console.tiers.update', $this->starter), [
            'name_en' => 'Starter plus',
            'slug' => 'starter',
            'features' => tierFeaturePayload(['members.max' => 10, 'parts.suggest' => true]),
        ])
        ->assertRedirect(route('console.tiers.index'));

    $team = Team::query()->findOrFail($this->team->id);

    expect($this->starter->fresh()->name_en)->toBe('Starter plus')
        ->and($features->value($team, TeamFeature::MembersMax))->toBe(10)
        ->and($features->value($team, TeamFeature::PartsSuggest))->toBeFalse();

    expect(Activity::query()->where('event', 'tier.updated')->exists())->toBeTrue();
});

it('makes another tier the default, leaving exactly one', function () {
    $this->actingAs($this->superAdmin)
        ->patch(route('console.tiers.default', $this->competition))
        ->assertRedirect(route('console.tiers.index'));

    expect(Tier::query()->where('is_default', true)->pluck('slug')->all())->toBe(['competition']);
});

it('deletes an unused tier', function () {
    $this->actingAs($this->superAdmin)
        ->delete(route('console.tiers.destroy', $this->competition))
        ->assertRedirect(route('console.tiers.index'));

    expect(Tier::query()->whereKey($this->competition->id)->exists())->toBeFalse();
});

it('refuses to delete a tier that teams use or the default tier', function () {
    $spare = Tier::factory()->create();
    $this->competition->update(['is_default' => false]);

    $this->actingAs($this->superAdmin)
        ->from(route('console.tiers.index'))
        ->delete(route('console.tiers.destroy', $this->starter))
        ->assertSessionHasErrors('tier');

    $this->actingAs($this->superAdmin)
        ->patch(route('console.tiers.default', $spare));
    app(TeamService::class)->assignTier($this->team, $spare, $this->superAdmin);

    $this->actingAs($this->superAdmin)
        ->from(route('console.tiers.index'))
        ->delete(route('console.tiers.destroy', $this->starter))
        ->assertRedirect(route('console.tiers.index'));

    $this->actingAs($this->superAdmin)
        ->from(route('console.tiers.index'))
        ->delete(route('console.tiers.destroy', $spare))
        ->assertSessionHasErrors('tier');

    expect(Tier::query()->whereKey($spare->id)->exists())->toBeTrue()
        ->and(Tier::query()->whereKey($this->starter->id)->exists())->toBeFalse();
});

it('shows tiers in the console navigation for the super admin', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('console.tiers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav', fn ($items) => collect($items)->contains(fn ($item) => $item['key'] === 'tiers')));
});
