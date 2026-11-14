<?php

use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Catalogue\Services\CatalogueService;
use App\Domain\Catalogue\Services\SuggestionService;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamMemberService;
use App\Domain\Work\Enums\BomLineAvailability;
use App\Domain\Work\Enums\ProjectStatus;
use App\Domain\Work\Models\Project;
use App\Domain\Work\Models\Robot;
use App\Domain\Work\Services\BomCalculator;
use App\Domain\Work\Services\BomService;
use App\Domain\Work\Services\ProjectService;
use App\Domain\Work\Services\RobotService;
use App\Exceptions\DomainRuleViolation;
use App\Exceptions\FeatureUnavailable;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;

/*
 * LLD 04 §1–4: projects, robots and their bill of materials. Publishing
 * needs the projects.publish feature; robots stay within their team; BOM
 * totals are per currency and never converted; unpriced lines are counted
 * but left out of the totals.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);

    $this->team = Team::factory()->create(['name' => 'Falcons', 'tier_id' => Tier::query()->where('slug', 'competition')->value('id'), 'is_public' => true]);
    $this->starterTeam = Team::factory()->create(['tier_id' => Tier::query()->where('slug', 'starter')->value('id')]);
    $this->otherTeam = Team::factory()->create(['tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
    $this->captain = User::factory()->create();
    app(TeamMemberService::class)->join($this->team, $this->captain, TeamRole::Captain);

    $this->projects = app(ProjectService::class);
    $this->robots = app(RobotService::class);
    $this->bom = app(BomService::class);

    $catalogue = app(CatalogueService::class);
    $this->servo = $catalogue->createPart(['name' => 'SG90 servo']);
    $this->board = $catalogue->createPart(['name' => 'Arduino Uno']);
    $this->wheel = $catalogue->createPart(['name' => 'Wheel set']);
    $this->shopA = $catalogue->createVendor(['name' => 'Shop A']);
    $this->shopB = $catalogue->createVendor(['name' => 'Shop B']);
    $this->servoA = $catalogue->upsertOffer($this->servo, ['vendor_id' => $this->shopA->id, 'price' => '8.500', 'currency' => 'LYD']);
    $this->boardB = $catalogue->upsertOffer($this->board, ['vendor_id' => $this->shopB->id, 'price' => '25', 'currency' => 'USD']);
    $this->wheelA = $catalogue->upsertOffer($this->wheel, ['vendor_id' => $this->shopA->id, 'price' => '12', 'currency' => 'LYD', 'in_stock' => false]);
});

it('creates projects with a unique slug and a sanitised body', function () {
    $project = $this->projects->create($this->team, ['title_en' => 'Line follower', 'body_en' => '<p>Hi<script>x()</script><img src=x onerror="steal()"></p>'], $this->captain);
    $again = $this->projects->create($this->team, ['title_en' => 'Line follower'], $this->captain);

    expect($project->status)->toBe(ProjectStatus::Draft)
        ->and($project->slug)->toBe('line-follower')
        ->and($again->slug)->toBe('line-follower-2')
        ->and($project->body_en)->not->toContain('script')->not->toContain('onerror');
});

it('needs the projects.create feature to create a project', function () {
    expect(fn () => $this->projects->create($this->starterTeam->fresh(), ['title_en' => 'X'], $this->captain))->not->toThrow(Throwable::class);

    $this->starterTeam->tier->update(['features' => [...$this->starterTeam->tier->features, 'projects.create' => false]]);
    app(TeamFeatures::class)->onTierChanged($this->starterTeam->tier);

    expect(fn () => $this->projects->create($this->starterTeam->fresh(), ['title_en' => 'Y'], $this->captain))->toThrow(FeatureUnavailable::class);
});

it('publishes only with the projects.publish feature, and unpublishes back to draft', function () {
    $project = $this->projects->create($this->team, ['title_en' => 'Sumo bot'], $this->captain);
    $this->projects->publish($project, $this->captain);

    expect($project->fresh()->status)->toBe(ProjectStatus::Published)
        ->and($project->fresh()->published_at)->not->toBeNull()
        ->and(Project::query()->public()->pluck('id')->all())->toBe([$project->id]);

    $this->projects->unpublish($project->fresh(), $this->captain);
    expect($project->fresh()->status)->toBe(ProjectStatus::Draft);

    $starterProject = $this->projects->create($this->starterTeam, ['title_en' => 'Starter project'], $this->captain);
    expect(fn () => $this->projects->publish($starterProject, $this->captain))->toThrow(FeatureUnavailable::class);
});

it('keeps robots within their own team’s projects', function () {
    $ours = $this->projects->create($this->team, ['title_en' => 'Ours'], $this->captain);
    $theirs = $this->projects->create($this->otherTeam, ['title_en' => 'Theirs'], $this->captain);

    $robot = $this->robots->create($this->team, ['name' => 'Speedy', 'status' => 'design', 'project_id' => $ours->id, 'weight_g' => 950], $this->captain);

    expect($robot->slug)->toBe('speedy')
        ->and($robot->project_id)->toBe($ours->id)
        ->and(fn () => $this->robots->create($this->team, ['name' => 'Rogue', 'status' => 'design', 'project_id' => $theirs->id], $this->captain))
        ->toThrow(DomainRuleViolation::class);
});

it('syncs the bom: upserts, removes missing lines and keeps order', function () {
    $robot = $this->robots->create($this->team, ['name' => 'Speedy', 'status' => 'building'], $this->captain);

    $this->bom->sync($robot, [
        ['part_id' => $this->servo->id, 'vendor_offer_id' => $this->servoA->id, 'quantity' => 4],
        ['part_id' => $this->board->id, 'vendor_offer_id' => $this->boardB->id, 'quantity' => 1],
    ], $this->captain);
    $this->bom->sync($robot, [
        ['part_id' => $this->board->id, 'vendor_offer_id' => $this->boardB->id, 'quantity' => 2, 'notes' => 'one spare'],
        ['part_id' => $this->wheel->id, 'vendor_offer_id' => null, 'quantity' => 1],
    ], $this->captain);

    $lines = $robot->bomLines()->get();

    expect($lines->pluck('part_id')->all())->toBe([$this->board->id, $this->wheel->id])
        ->and($lines->first()->quantity)->toBe(2)
        ->and($lines->first()->notes)->toBe('one spare');
});

it('refuses parts the team cannot see and offers of another part', function () {
    $robot = $this->robots->create($this->team, ['name' => 'Speedy', 'status' => 'building'], $this->captain);
    $foreign = app(SuggestionService::class)->suggestPart($this->otherTeam, ['name' => 'Secret part'], User::factory()->create());

    expect(fn () => $this->bom->sync($robot, [['part_id' => $foreign->id, 'quantity' => 1]], $this->captain))->toThrow(DomainRuleViolation::class)
        ->and(fn () => $this->bom->sync($robot, [['part_id' => $this->servo->id, 'vendor_offer_id' => $this->boardB->id, 'quantity' => 1]], $this->captain))->toThrow(DomainRuleViolation::class);
});

it('totals the bom per currency, leaves unpriced lines out and flags availability', function () {
    $robot = $this->robots->create($this->team, ['name' => 'Speedy', 'status' => 'building'], $this->captain);
    $this->bom->sync($robot, [
        ['part_id' => $this->servo->id, 'vendor_offer_id' => $this->servoA->id, 'quantity' => 4],
        ['part_id' => $this->board->id, 'vendor_offer_id' => $this->boardB->id, 'quantity' => 2],
        ['part_id' => $this->wheel->id, 'vendor_offer_id' => $this->wheelA->id, 'quantity' => 1],
        ['part_id' => $this->board->id, 'vendor_offer_id' => null, 'quantity' => 1],
    ], $this->captain);

    app(CatalogueService::class)->archivePart($this->servo);

    $result = app(BomCalculator::class)->forRobot($robot->fresh());

    expect(collect($result['totals'])->map(fn ($money) => (string) $money->getAmount())->all())->toBe(['LYD' => '46.000', 'USD' => '50.00'])
        ->and($result['unpriced'])->toBe(1)
        ->and(collect($result['lines'])->pluck('availability')->all())->toBe([
            BomLineAvailability::PartArchived,
            BomLineAvailability::Available,
            BomLineAvailability::OfferOutOfStock,
            BomLineAvailability::NoOffer,
        ]);
});

it('groups the shopping list by vendor', function () {
    $robot = $this->robots->create($this->team, ['name' => 'Speedy', 'status' => 'building'], $this->captain);
    $this->bom->sync($robot, [
        ['part_id' => $this->servo->id, 'vendor_offer_id' => $this->servoA->id, 'quantity' => 2],
        ['part_id' => $this->wheel->id, 'vendor_offer_id' => $this->wheelA->id, 'quantity' => 3],
        ['part_id' => $this->board->id, 'vendor_offer_id' => $this->boardB->id, 'quantity' => 1],
    ], $this->captain);

    $byVendor = collect(app(BomCalculator::class)->forRobot($robot)['byVendor'])
        ->mapWithKeys(fn ($group) => [$group['vendor']->name => (string) $group['total']->getAmount()]);

    expect($byVendor->all())->toBe(['Shop A' => '53.000', 'Shop B' => '25.00']);
});

it('keeps an archived part on the bom when its offer is removed', function () {
    $robot = $this->robots->create($this->team, ['name' => 'Speedy', 'status' => 'building'], $this->captain);
    $this->bom->sync($robot, [['part_id' => $this->servo->id, 'vendor_offer_id' => $this->servoA->id, 'quantity' => 1]], $this->captain);

    app(CatalogueService::class)->removeOffer($this->servoA);

    $line = $robot->bomLines()->sole();
    expect($line->vendor_offer_id)->toBeNull()
        ->and($this->servo->fresh()->status)->toBe(CatalogueStatus::Approved);
});

it('shows only public robots of public teams', function () {
    $this->robots->create($this->team, ['name' => 'Shown', 'status' => 'ready', 'is_public' => true], $this->captain);
    $this->robots->create($this->team, ['name' => 'Hidden', 'status' => 'ready', 'is_public' => false], $this->captain);
    $this->robots->create($this->otherTeam, ['name' => 'Private team', 'status' => 'ready', 'is_public' => true], $this->captain);

    expect(Robot::query()->public()->pluck('name')->all())->toBe(['Shown']);
});
