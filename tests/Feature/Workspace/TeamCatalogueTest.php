<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Catalogue\Models\Part;
use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Catalogue\Models\VendorOffer;
use App\Domain\Catalogue\Services\CatalogueService;
use App\Domain\Catalogue\Services\SuggestionService;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamMemberService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 05 §5: the catalogue from the team workspace. Teams browse approved
 * parts with their offers plus their own suggestions, and suggest parts,
 * vendors and offers for the organisers to review.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);

    $this->team = Team::factory()->create(['slug' => 'falcons', 'tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
    $members = app(TeamMemberService::class);
    foreach (['captain' => TeamRole::Captain, 'member' => TeamRole::Member, 'viewer' => TeamRole::Viewer] as $key => $role) {
        $user = User::factory()->create();
        $members->join($this->team, $user, $role);
        $this->{$key} = $user->fresh();
    }

    $catalogue = app(CatalogueService::class);
    $this->servo = $catalogue->createPart(['name' => 'SG90 servo', 'part_number' => 'SG-90']);
    $catalogue->createPart(['name' => 'Arduino Uno', 'part_number' => 'A000066']);
    $this->vendor = $catalogue->createVendor(['name' => 'Shop A']);
    $catalogue->upsertOffer($this->servo, ['vendor_id' => $this->vendor->id, 'price' => '8.500', 'currency' => 'LYD']);

    $this->otherTeam = Team::factory()->create(['tier_id' => $this->team->tier_id]);
    $otherCaptain = User::factory()->create();
    $members->join($this->otherTeam, $otherCaptain, TeamRole::Captain);
    app(SuggestionService::class)->suggestPart($this->otherTeam, ['name' => 'Secret sensor'], $otherCaptain);
});

it('lists approved parts with offers and hides other teams’ suggestions', function () {
    $this->actingAs($this->viewer)
        ->get(route('workspace.catalogue.index', $this->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/catalogue/Index')
            ->has('parts.data', 2)
            ->where('parts.data.1.name', 'SG90 servo')
            ->where('parts.data.1.offers.0.vendor', 'Shop A')
            ->where('can.suggest', false));
});

it('searches parts by name or number', function () {
    $this->actingAs($this->member)
        ->get(route('workspace.catalogue.index', [$this->team, 'q' => 'sg-90']))
        ->assertInertia(fn (Assert $page) => $page->has('parts.data', 1)->where('parts.data.0.name', 'SG90 servo'));
});

it('lets members suggest parts, vendors and offers', function () {
    $this->actingAs($this->member)
        ->post(route('workspace.catalogue.parts.store', $this->team), ['name' => 'TT gear motor', 'part_number' => 'TT-1'])
        ->assertRedirect();
    $part = Part::query()->where('name', 'TT gear motor')->sole();
    expect($part->status)->toBe(CatalogueStatus::Suggested)->and($part->suggested_by_team_id)->toBe($this->team->id);

    $this->actingAs($this->member)
        ->post(route('workspace.catalogue.vendors.store', $this->team), ['name' => 'Shop B', 'city' => 'Tripoli'])
        ->assertRedirect();
    $vendor = Vendor::query()->where('name', 'Shop B')->sole();

    $this->actingAs($this->member)
        ->post(route('workspace.catalogue.offers.store', $this->team), ['part_id' => $part->id, 'vendor_id' => $vendor->id, 'price' => '4.250', 'currency' => 'LYD', 'in_stock' => true])
        ->assertRedirect();
    expect(VendorOffer::query()->where('part_id', $part->id)->sole()->price_minor)->toBe(4250);

    $this->actingAs($this->member)
        ->get(route('workspace.catalogue.index', $this->team))
        ->assertInertia(fn (Assert $page) => $page
            ->has('parts.data', 3)
            ->has('suggestions', 3)
            ->where('can.suggest', true)
            ->has('vendors', 2));
});

it('shows the organisers’ note on rejected suggestions', function () {
    $part = app(SuggestionService::class)->suggestPart($this->team, ['name' => 'Cheap clone'], $this->member);
    $reviewer = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $reviewer->assignRole(User::SUPER_ADMIN));
    app(SuggestionService::class)->reject($part, 'Already listed as Arduino Uno.', $reviewer->fresh());

    $this->actingAs($this->member)
        ->get(route('workspace.catalogue.index', $this->team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('suggestions.0.status', 'rejected')
            ->where('suggestions.0.note', 'Already listed as Arduino Uno.'));
});

it('refuses suggestions from viewers and teams without the feature', function () {
    $this->actingAs($this->viewer)
        ->post(route('workspace.catalogue.parts.store', $this->team), ['name' => 'X'])
        ->assertForbidden();

    app(TeamFeatures::class)->override($this->team, TeamFeature::PartsSuggest, false);

    $this->actingAs($this->member)
        ->post(route('workspace.catalogue.parts.store', $this->team), ['name' => 'X'])
        ->assertForbidden();
});

it('refuses offers on parts the team cannot see', function () {
    $secret = Part::query()->where('name', 'Secret sensor')->sole();

    $this->actingAs($this->member)
        ->post(route('workspace.catalogue.offers.store', $this->team), ['part_id' => $secret->id, 'vendor_id' => $this->vendor->id, 'price' => '1', 'currency' => 'LYD'])
        ->assertSessionHasErrors('part_id');
});

it('closes the catalogue to teams without parts access', function () {
    app(TeamFeatures::class)->override($this->team, TeamFeature::PartsAccess, false);

    $this->actingAs($this->member)->get(route('workspace.catalogue.index', $this->team))->assertForbidden();
});
