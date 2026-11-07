<?php

use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Catalogue\Models\Part;
use App\Domain\Catalogue\Models\PartCategory;
use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Catalogue\Models\VendorOffer;
use App\Domain\Catalogue\Services\CatalogueSearch;
use App\Domain\Catalogue\Services\CatalogueService;
use App\Domain\Catalogue\Services\SuggestionService;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Exceptions\DomainRuleViolation;
use App\Exceptions\FeatureUnavailable;
use App\Models\User;
use Brick\Money\Money;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Spatie\Activitylog\Models\Activity;

/*
 * LLD 05 §1–4: the shared parts and vendor catalogue. Staff curate it;
 * teams with parts.suggest propose additions that only they see until an
 * organiser approves, rejects or merges them.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);

    $this->staff = User::factory()->create();
    $this->catalogue = app(CatalogueService::class);
    $this->suggestions = app(SuggestionService::class);

    $competition = Tier::query()->where('slug', 'competition')->value('id');
    $this->team = Team::factory()->create(['tier_id' => $competition]);
    $this->otherTeam = Team::factory()->create(['tier_id' => $competition]);
    $this->member = User::factory()->create();

    $this->motors = PartCategory::factory()->create(['name_en' => 'Motors']);
    $this->servos = PartCategory::factory()->create(['name_en' => 'Servos', 'parent_id' => $this->motors->id]);
    $this->sensors = PartCategory::factory()->create(['name_en' => 'Sensors']);
});

it('stores prices in minor units with three decimals for LYD', function () {
    $part = $this->catalogue->createPart(['name' => 'SG90 servo'], $this->staff);
    $vendor = $this->catalogue->createVendor(['name' => 'Tripoli Electronics'], $this->staff);

    $offer = $this->catalogue->upsertOffer($part, [
        'vendor_id' => $vendor->id,
        'price' => '12.345',
        'currency' => 'LYD',
        'in_stock' => true,
    ], $this->staff);

    expect($offer->price_minor)->toBe(12345)
        ->and($offer->price)->toBeInstanceOf(Money::class)
        ->and((string) $offer->price->getAmount())->toBe('12.345')
        ->and($offer->status)->toBe(CatalogueStatus::Approved);
});

it('keeps one approved offer per part, vendor and currency', function () {
    $part = $this->catalogue->createPart(['name' => 'SG90 servo'], $this->staff);
    $vendor = $this->catalogue->createVendor(['name' => 'Tripoli Electronics'], $this->staff);

    $this->catalogue->upsertOffer($part, ['vendor_id' => $vendor->id, 'price' => '10', 'currency' => 'LYD'], $this->staff);
    $this->catalogue->upsertOffer($part, ['vendor_id' => $vendor->id, 'price' => '9.5', 'currency' => 'LYD', 'in_stock' => false], $this->staff);
    $this->catalogue->upsertOffer($part, ['vendor_id' => $vendor->id, 'price' => '3', 'currency' => 'USD'], $this->staff);

    expect($part->offers()->count())->toBe(2)
        ->and($part->offers()->where('currency', 'LYD')->sole())
        ->price_minor->toBe(9500)
        ->in_stock->toBeFalse();
});

it('finds the best offer: lowest in-stock LYD price', function () {
    $part = $this->catalogue->createPart(['name' => 'Ultrasonic sensor'], $this->staff);
    foreach ([['A', '15', true], ['B', '11', false], ['C', '13', true]] as [$name, $price, $stock]) {
        $vendor = $this->catalogue->createVendor(['name' => "Vendor {$name}"], $this->staff);
        $this->catalogue->upsertOffer($part, ['vendor_id' => $vendor->id, 'price' => $price, 'currency' => 'LYD', 'in_stock' => $stock], $this->staff);
    }

    expect($part->bestOffer()->vendor->name)->toBe('Vendor C');
});

it('archives parts and vendors instead of deleting them, and can restore them', function () {
    $part = $this->catalogue->createPart(['name' => 'L298N driver'], $this->staff);
    $vendor = $this->catalogue->createVendor(['name' => 'Benghazi Robotics'], $this->staff);

    $this->catalogue->archivePart($part, $this->staff);
    $this->catalogue->archiveVendor($vendor, $this->staff);

    expect(Part::query()->find($part->id)->status)->toBe(CatalogueStatus::Archived)
        ->and(Vendor::query()->find($vendor->id)->status)->toBe(CatalogueStatus::Archived)
        ->and(Part::query()->approved()->exists())->toBeFalse();

    $this->catalogue->restorePart($part->fresh(), $this->staff);

    expect($part->fresh()->status)->toBe(CatalogueStatus::Approved);
});

it('lets teams see approved items plus only their own suggestions', function () {
    $approved = $this->catalogue->createPart(['name' => 'Arduino Uno'], $this->staff);
    $ours = $this->suggestions->suggestPart($this->team, ['name' => 'ESP32 DevKit'], $this->member);
    $theirs = $this->suggestions->suggestPart($this->otherTeam, ['name' => 'Raspberry Pi Pico'], User::factory()->create());

    expect(Part::query()->visibleTo($this->team)->pluck('name')->sort()->values()->all())->toBe(['Arduino Uno', 'ESP32 DevKit'])
        ->and($ours->status)->toBe(CatalogueStatus::Suggested)
        ->and($ours->suggested_by_team_id)->toBe($this->team->id)
        ->and($ours->isSuggestionOf($this->team))->toBeTrue()
        ->and($theirs->isSuggestionOf($this->team))->toBeFalse()
        ->and(Part::query()->suggestions()->count())->toBe(2);
});

it('needs the parts.suggest feature and an active team to suggest', function () {
    $starter = Team::factory()->create(['tier_id' => Tier::query()->where('slug', 'starter')->value('id')]);

    expect(fn () => $this->suggestions->suggestPart($starter, ['name' => 'Anything'], $this->member))
        ->toThrow(FeatureUnavailable::class);

    $this->team->update(['status' => TeamStatus::Suspended]);

    expect(fn () => $this->suggestions->suggestVendor($this->team->fresh(), ['name' => 'Anyone'], $this->member))
        ->toThrow(DomainRuleViolation::class);
});

it('lets a team suggest an offer for its own suggested part and vendor', function () {
    $part = $this->suggestions->suggestPart($this->team, ['name' => 'TT gear motor'], $this->member);
    $vendor = $this->suggestions->suggestVendor($this->team, ['name' => 'Misrata Parts'], $this->member);

    $offer = $this->suggestions->suggestOffer($this->team, ['part_id' => $part->id, 'vendor_id' => $vendor->id, 'price' => '4.250', 'currency' => 'LYD'], $this->member);

    expect($offer->status)->toBe(CatalogueStatus::Suggested)
        ->and($offer->price_minor)->toBe(4250);

    $foreign = $this->suggestions->suggestPart($this->otherTeam, ['name' => 'Foreign part'], User::factory()->create());

    expect(fn () => $this->suggestions->suggestOffer($this->team, ['part_id' => $foreign->id, 'vendor_id' => $vendor->id, 'price' => '1', 'currency' => 'LYD'], $this->member))
        ->toThrow(DomainRuleViolation::class);
});

it('approves an offer together with its suggested part and vendor', function () {
    $part = $this->suggestions->suggestPart($this->team, ['name' => 'TT gear motor'], $this->member);
    $vendor = $this->suggestions->suggestVendor($this->team, ['name' => 'Misrata Parts'], $this->member);
    $offer = $this->suggestions->suggestOffer($this->team, ['part_id' => $part->id, 'vendor_id' => $vendor->id, 'price' => '4.250', 'currency' => 'LYD'], $this->member);

    expect($this->suggestions->cascadeFor($offer)->map(fn ($item) => $item->getKey())->all())->toHaveCount(2);

    $this->suggestions->approve($offer, null, $this->staff);

    expect($offer->fresh()->status)->toBe(CatalogueStatus::Approved)
        ->and($part->fresh()->status)->toBe(CatalogueStatus::Approved)
        ->and($vendor->fresh()->status)->toBe(CatalogueStatus::Approved)
        ->and($part->fresh()->reviewed_by)->toBe($this->staff->id)
        ->and(Activity::query()->where('event', 'catalogue.approved')->count())->toBe(3);
});

it('applies the reviewer’s edits when approving', function () {
    $part = $this->suggestions->suggestPart($this->team, ['name' => 'esp 32'], $this->member);

    $this->suggestions->approve($part, ['name' => 'ESP32 DevKit V1', 'category_id' => $this->sensors->id], $this->staff);

    expect($part->fresh())
        ->name->toBe('ESP32 DevKit V1')
        ->category_id->toBe($this->sensors->id)
        ->status->toBe(CatalogueStatus::Approved);
});

it('rejects a suggestion with a note', function () {
    $vendor = $this->suggestions->suggestVendor($this->team, ['name' => 'Cousin Ahmed'], $this->member);

    $this->suggestions->reject($vendor, 'Not a registered business.', $this->staff);

    expect($vendor->fresh())
        ->status->toBe(CatalogueStatus::Rejected)
        ->review_note->toBe('Not a registered business.');

    expect(fn () => $this->suggestions->reject($vendor->fresh(), 'Again', $this->staff))->toThrow(DomainRuleViolation::class);
});

it('merges a duplicate suggestion into an approved part and moves its offers', function () {
    $existing = $this->catalogue->createPart(['name' => 'SG90 servo'], $this->staff);
    $duplicate = $this->suggestions->suggestPart($this->team, ['name' => 'sg-90 micro servo'], $this->member);
    $vendor = $this->catalogue->createVendor(['name' => 'Tripoli Electronics'], $this->staff);
    $offer = $this->suggestions->suggestOffer($this->team, ['part_id' => $duplicate->id, 'vendor_id' => $vendor->id, 'price' => '6', 'currency' => 'LYD'], $this->member);

    $this->suggestions->merge($duplicate, $existing, $this->staff);

    expect($duplicate->fresh())
        ->status->toBe(CatalogueStatus::Rejected)
        ->merged_into_id->toBe($existing->id)
        ->and($offer->fresh()->part_id)->toBe($existing->id);
});

it('only merges into an approved item of the same type', function () {
    $duplicate = $this->suggestions->suggestPart($this->team, ['name' => 'Dup'], $this->member);
    $other = $this->suggestions->suggestPart($this->otherTeam, ['name' => 'Other suggestion'], User::factory()->create());

    expect(fn () => $this->suggestions->merge($duplicate, $other, $this->staff))->toThrow(DomainRuleViolation::class)
        ->and(fn () => $this->suggestions->merge($duplicate, $duplicate, $this->staff))->toThrow(DomainRuleViolation::class);
});

it('searches by text, category with children, vendor, price range and stock', function () {
    $servo = $this->catalogue->createPart(['name' => 'SG90 servo', 'part_number' => 'SG-90', 'category_id' => $this->servos->id], $this->staff);
    $motor = $this->catalogue->createPart(['name' => 'TT gear motor', 'category_id' => $this->motors->id], $this->staff);
    $sensor = $this->catalogue->createPart(['name' => 'HC-SR04 ultrasonic', 'category_id' => $this->sensors->id], $this->staff);
    $cheap = $this->catalogue->createVendor(['name' => 'Cheap Parts'], $this->staff);
    $fancy = $this->catalogue->createVendor(['name' => 'Fancy Parts'], $this->staff);
    $this->catalogue->upsertOffer($servo, ['vendor_id' => $cheap->id, 'price' => '8', 'currency' => 'LYD'], $this->staff);
    $this->catalogue->upsertOffer($motor, ['vendor_id' => $fancy->id, 'price' => '25', 'currency' => 'LYD', 'in_stock' => false], $this->staff);
    $this->catalogue->upsertOffer($sensor, ['vendor_id' => $fancy->id, 'price' => '15', 'currency' => 'LYD'], $this->staff);
    $this->suggestions->suggestPart($this->otherTeam, ['name' => 'Secret servo', 'category_id' => $this->servos->id], User::factory()->create());

    $names = fn (array $filters) => app(CatalogueSearch::class)->search($this->team, $filters)->getCollection()->pluck('name')->sort()->values()->all();

    expect($names(['category_id' => $this->motors->id]))->toBe(['SG90 servo', 'TT gear motor'])
        ->and($names(['vendor_id' => $fancy->id]))->toBe(['HC-SR04 ultrasonic', 'TT gear motor'])
        ->and($names(['price_min' => 10000, 'price_max' => 20000]))->toBe(['HC-SR04 ultrasonic'])
        ->and($names(['in_stock' => true]))->toBe(['HC-SR04 ultrasonic', 'SG90 servo'])
        ->and($names(['q' => 'sg']))->toBe(['SG90 servo'])
        ->and($names(['q' => 'ultrasonic']))->toBe(['HC-SR04 ultrasonic']);
});

it('lets staff search every status with a status filter', function () {
    $this->catalogue->createPart(['name' => 'Approved part'], $this->staff);
    $this->suggestions->suggestPart($this->team, ['name' => 'Suggested part'], $this->member);

    $results = app(CatalogueSearch::class)->search(null, ['status' => 'suggested']);

    expect($results->getCollection()->pluck('name')->all())->toBe(['Suggested part'])
        ->and(app(CatalogueSearch::class)->search(null, [])->total())->toBe(2);
});

it('removes an offer', function () {
    $part = $this->catalogue->createPart(['name' => 'Battery 18650'], $this->staff);
    $vendor = $this->catalogue->createVendor(['name' => 'Power Shop'], $this->staff);
    $offer = $this->catalogue->upsertOffer($part, ['vendor_id' => $vendor->id, 'price' => '7', 'currency' => 'LYD'], $this->staff);

    $this->catalogue->removeOffer($offer, $this->staff);

    expect(VendorOffer::query()->whereKey($offer->id)->exists())->toBeFalse();
});
