<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Catalogue\Models\Part;
use App\Domain\Catalogue\Models\PartCategory;
use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Catalogue\Models\VendorOffer;
use App\Domain\Catalogue\Services\CatalogueService;
use App\Domain\Catalogue\Services\SuggestionService;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 05 §5–8: the catalogue area of the console. The catalogue is global:
 * catalogue.view, .edit or .approve in any department is enough.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);

    $superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $superAdmin->assignRole(User::SUPER_ADMIN));

    $departments = app(DepartmentService::class);
    $permissions = app(DepartmentPermissionService::class);
    $this->procurement = $departments->create(['name_en' => 'Procurement']);
    $permissions->setCeiling($this->procurement, ['catalogue.view', 'catalogue.edit'], $superAdmin->fresh());

    $this->admin = User::factory()->create();
    $this->editor = User::factory()->create();
    $this->viewer = User::factory()->create();
    $this->stranger = User::factory()->create();
    $departments->addMember($this->procurement, $this->admin, isAdmin: true);
    $departments->addMember($this->procurement, $this->editor);
    $departments->addMember($this->procurement, $this->viewer);
    $permissions->syncMember($this->procurement, $this->editor, ['catalogue.view', 'catalogue.edit'], $this->admin);
    $permissions->syncMember($this->procurement, $this->viewer, ['catalogue.view'], $this->admin);

    $this->catalogue = app(CatalogueService::class);
    $this->motors = PartCategory::factory()->create(['name_en' => 'Motors', 'slug' => 'motors']);
    $this->part = $this->catalogue->createPart(['name' => 'SG90 servo', 'part_number' => 'SG-90', 'category_id' => $this->motors->id]);
    $this->vendor = $this->catalogue->createVendor(['name' => 'Tripoli Electronics', 'city' => 'Tripoli']);

    $this->team = Team::factory()->create(['name' => 'Falcons', 'tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
    $this->captain = User::factory()->create(['name_en' => 'Captain Ali']);
});

function partPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'TT gear motor',
        'part_number' => 'TT-130',
        'category_id' => test()->motors->id,
        'description_en' => 'Yellow gear motor, 3–6 V.',
        'specs' => [['key' => 'Voltage', 'value' => '3–6', 'unit' => 'V']],
    ], $overrides);
}

it('lists parts for anyone with catalogue.view, with search and status filter', function () {
    app(SuggestionService::class)->suggestPart($this->team, ['name' => 'ESP32 DevKit'], $this->captain);

    $this->actingAs($this->viewer)
        ->get(route('console.catalogue.parts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/catalogue/Parts')
            ->has('parts.data', 2)
            ->where('can.manage', false)
            ->where('suggestionsCount', 1));

    $this->actingAs($this->viewer)
        ->get(route('console.catalogue.parts.index', ['filter' => ['status' => 'suggested']]))
        ->assertInertia(fn (Assert $page) => $page->has('parts.data', 1)->where('parts.data.0.name', 'ESP32 DevKit'));

    $this->actingAs($this->viewer)
        ->get(route('console.catalogue.parts.index', ['q' => 'SG-9']))
        ->assertInertia(fn (Assert $page) => $page->has('parts.data', 1)->where('parts.data.0.name', 'SG90 servo'));
});

it('keeps the catalogue from staff without catalogue permissions', function () {
    $this->actingAs($this->stranger)->get(route('console.catalogue.parts.index'))->assertForbidden();
});

it('creates and updates parts for editors only', function () {
    $this->actingAs($this->viewer)->post(route('console.catalogue.parts.store'), partPayload())->assertForbidden();

    $this->actingAs($this->editor)->post(route('console.catalogue.parts.store'), partPayload())->assertRedirect();

    $part = Part::query()->where('name', 'TT gear motor')->sole();
    expect($part->status)->toBe(CatalogueStatus::Approved)
        ->and($part->specs)->toEqual([['key' => 'Voltage', 'value' => '3–6', 'unit' => 'V']]);

    $this->actingAs($this->editor)->patch(route('console.catalogue.parts.update', $part), partPayload(['name' => 'TT gear motor 1:48']))->assertRedirect();
    expect($part->fresh()->name)->toBe('TT gear motor 1:48');
});

it('validates parts', function (array $payload, string $error) {
    $this->actingAs($this->editor)->post(route('console.catalogue.parts.store'), partPayload($payload))->assertSessionHasErrors($error);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'unknown category' => [['category_id' => 99999], 'category_id'],
    'spec without value' => [['specs' => [['key' => 'Weight', 'value' => '']]], 'specs.0.value'],
]);

it('shows a part with its offers and the vendors to choose from', function () {
    $this->catalogue->upsertOffer($this->part, ['vendor_id' => $this->vendor->id, 'price' => '8.500', 'currency' => 'LYD']);

    $this->actingAs($this->viewer)
        ->get(route('console.catalogue.parts.show', $this->part))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/catalogue/PartShow')
            ->where('part.name', 'SG90 servo')
            ->has('offers', 1)
            ->where('offers.0.vendor.name', 'Tripoli Electronics')
            ->where('offers.0.price', '8.500')
            ->where('offers.0.formatted', '8.500 LYD')
            ->has('vendors', 1)
            ->has('currencies', 3));
});

it('syncs a part’s offers: updates, adds and removes', function () {
    $kept = $this->catalogue->upsertOffer($this->part, ['vendor_id' => $this->vendor->id, 'price' => '8', 'currency' => 'LYD']);
    $other = $this->catalogue->createVendor(['name' => 'Benghazi Robotics']);
    $dropped = $this->catalogue->upsertOffer($this->part, ['vendor_id' => $other->id, 'price' => '9', 'currency' => 'LYD']);
    $third = $this->catalogue->createVendor(['name' => 'Misrata Parts']);

    $this->actingAs($this->editor)
        ->put(route('console.catalogue.parts.offers', $this->part), ['offers' => [
            ['id' => $kept->id, 'vendor_id' => $this->vendor->id, 'price' => '7.250', 'currency' => 'LYD', 'in_stock' => false],
            ['vendor_id' => $third->id, 'price' => '6', 'currency' => 'LYD', 'in_stock' => true, 'url' => 'https://misrata.example/servo'],
        ]])
        ->assertRedirect();

    expect($kept->fresh())->price_minor->toBe(7250)->in_stock->toBeFalse()
        ->and(VendorOffer::query()->whereKey($dropped->id)->exists())->toBeFalse()
        ->and($this->part->bestOffer()->vendor->name)->toBe('Misrata Parts');
});

it('validates offers', function (array $row, string $error) {
    $this->actingAs($this->editor)
        ->put(route('console.catalogue.parts.offers', $this->part), ['offers' => [array_merge(['vendor_id' => $this->vendor->id, 'price' => '5', 'currency' => 'LYD'], $row)]])
        ->assertSessionHasErrors($error);
})->with([
    'too many decimals' => [['price' => '1.2345'], 'offers.0.price'],
    'negative' => [['price' => '-1'], 'offers.0.price'],
    'unknown currency' => [['currency' => 'GBP'], 'offers.0.currency'],
    'bad url' => [['url' => 'javascript:alert(1)'], 'offers.0.url'],
]);

it('archives and restores parts instead of deleting them', function () {
    $this->actingAs($this->editor)->delete(route('console.catalogue.parts.destroy', $this->part))->assertRedirect();
    expect($this->part->fresh()->status)->toBe(CatalogueStatus::Archived);

    $this->actingAs($this->editor)->patch(route('console.catalogue.parts.restore', $this->part))->assertRedirect();
    expect($this->part->fresh()->status)->toBe(CatalogueStatus::Approved);
});

it('manages vendors', function () {
    $this->actingAs($this->viewer)
        ->get(route('console.catalogue.vendors.index'))
        ->assertInertia(fn (Assert $page) => $page->component('console/catalogue/Vendors')->has('vendors.data', 1));

    $this->actingAs($this->editor)
        ->post(route('console.catalogue.vendors.store'), ['name' => 'Misrata Parts', 'website' => 'https://misrata.example', 'latitude' => 32.37, 'longitude' => 15.09])
        ->assertRedirect();
    $vendor = Vendor::query()->where('name', 'Misrata Parts')->sole();

    $this->actingAs($this->editor)->patch(route('console.catalogue.vendors.update', $vendor), ['name' => 'Misrata Parts Co.'])->assertRedirect();
    $this->actingAs($this->editor)->delete(route('console.catalogue.vendors.destroy', $vendor))->assertRedirect();

    expect($vendor->fresh())->name->toBe('Misrata Parts Co.')->status->toBe(CatalogueStatus::Archived);

    $this->actingAs($this->editor)
        ->post(route('console.catalogue.vendors.store'), ['name' => 'Bad', 'latitude' => 200])
        ->assertSessionHasErrors('latitude');
});

it('manages categories two levels deep', function () {
    $this->actingAs($this->editor)->post(route('console.catalogue.categories.store'), ['name_en' => 'Servos', 'parent_id' => $this->motors->id])->assertRedirect();
    $servos = PartCategory::query()->where('name_en', 'Servos')->sole();

    $this->actingAs($this->editor)
        ->post(route('console.catalogue.categories.store'), ['name_en' => 'Micro servos', 'parent_id' => $servos->id])
        ->assertSessionHasErrors('parent_id');

    $this->actingAs($this->viewer)
        ->get(route('console.catalogue.categories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/catalogue/Categories')
            ->where('categories.0.name', 'Motors')
            ->where('categories.0.children.0.name', 'Servos')
            ->where('categories.0.partsCount', 1));

    $this->actingAs($this->editor)
        ->from(route('console.catalogue.categories.index'))
        ->delete(route('console.catalogue.categories.destroy', $this->motors))
        ->assertSessionHasErrors('category');

    $this->actingAs($this->editor)->delete(route('console.catalogue.categories.destroy', $servos))->assertRedirect();
    expect(PartCategory::query()->whereKey($servos->id)->exists())->toBeFalse();
});

it('shows the suggestion queue with what an approval would also approve', function () {
    $suggestions = app(SuggestionService::class);
    $part = $suggestions->suggestPart($this->team, ['name' => 'ESP32 DevKit'], $this->captain);
    $suggestions->suggestOffer($this->team, ['part_id' => $part->id, 'vendor_id' => $this->vendor->id, 'price' => '30', 'currency' => 'LYD'], $this->captain);

    $this->actingAs($this->viewer)
        ->get(route('console.catalogue.suggestions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/catalogue/Suggestions')
            ->has('suggestions', 2)
            ->where('suggestions', fn ($rows) => collect($rows)->contains(fn ($row) => $row['type'] === 'offer'
                && $row['team'] === 'Falcons'
                && $row['suggestedBy'] === 'Captain Ali'
                && count($row['alsoApproves']) === 1))
            ->where('can.review', false));

    $this->actingAs($this->viewer)
        ->get(route('console.catalogue.suggestions.index', ['type' => 'part']))
        ->assertInertia(fn (Assert $page) => $page->has('suggestions', 1));
});

it('lets only catalogue approvers review suggestions', function () {
    $part = app(SuggestionService::class)->suggestPart($this->team, ['name' => 'esp 32'], $this->captain);

    $this->actingAs($this->editor)->post(route('console.catalogue.suggestions.approve', ['part', $part->id]))->assertForbidden();

    $this->actingAs($this->admin)
        ->post(route('console.catalogue.suggestions.approve', ['part', $part->id]), ['edits' => ['name' => 'ESP32 DevKit V1']])
        ->assertRedirect();

    expect($part->fresh())->status->toBe(CatalogueStatus::Approved)->name->toBe('ESP32 DevKit V1');
});

it('rejects with a note and merges into an approved item', function () {
    $suggestions = app(SuggestionService::class);
    $rejected = $suggestions->suggestVendor($this->team, ['name' => 'Cousin Ahmed'], $this->captain);
    $duplicate = $suggestions->suggestPart($this->team, ['name' => 'sg90'], $this->captain);

    $this->actingAs($this->admin)
        ->post(route('console.catalogue.suggestions.reject', ['vendor', $rejected->id]), ['note' => ''])
        ->assertSessionHasErrors('note');
    $this->actingAs($this->admin)
        ->post(route('console.catalogue.suggestions.reject', ['vendor', $rejected->id]), ['note' => 'Not a business.'])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('console.catalogue.suggestions.merge', ['part', $duplicate->id]), ['into_id' => $this->part->id])
        ->assertRedirect();

    expect($rejected->fresh()->status)->toBe(CatalogueStatus::Rejected)
        ->and($duplicate->fresh()->merged_into_id)->toBe($this->part->id);
});

it('refuses unknown suggestion types and offers as merge sources', function () {
    $part = app(SuggestionService::class)->suggestPart($this->team, ['name' => 'Anything'], $this->captain);

    $this->actingAs($this->admin)->post(route('console.catalogue.suggestions.approve', ['robot', $part->id]))->assertNotFound();
    $this->actingAs($this->admin)->post(route('console.catalogue.suggestions.merge', ['offer', 1]), ['into_id' => 1])->assertNotFound();
});
