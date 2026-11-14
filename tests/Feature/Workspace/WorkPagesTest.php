<?php

use App\Domain\Catalogue\Services\CatalogueService;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamMemberService;
use App\Domain\Work\Enums\ProjectStatus;
use App\Domain\Work\Models\Project;
use App\Domain\Work\Models\Robot;
use App\Domain\Work\Services\RobotService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 04 §5–8: projects and robots in the team workspace. Members edit;
 * captains and coaches publish; viewers only look.
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
    $this->part = $catalogue->createPart(['name' => 'SG90 servo', 'part_number' => 'SG-90']);
    $vendor = $catalogue->createVendor(['name' => 'Shop A']);
    $this->offer = $catalogue->upsertOffer($this->part, ['vendor_id' => $vendor->id, 'price' => '8.500', 'currency' => 'LYD']);
});

it('lets members create and edit projects but only captains publish', function () {
    $this->actingAs($this->viewer)->post(route('workspace.projects.store', $this->team), ['title_en' => 'X'])->assertForbidden();

    $this->actingAs($this->member)->post(route('workspace.projects.store', $this->team), ['title_en' => 'Line follower'])->assertRedirect();
    $project = Project::query()->sole();

    $this->actingAs($this->member)->patch(route('workspace.projects.update', [$this->team, $project]), ['title_en' => 'Line follower v2', 'body_en' => '<p>Ok</p>'])->assertRedirect();
    $this->actingAs($this->member)->post(route('workspace.projects.publish', [$this->team, $project]))->assertForbidden();
    $this->actingAs($this->captain)->post(route('workspace.projects.publish', [$this->team, $project]))->assertRedirect();

    expect($project->fresh())->title_en->toBe('Line follower v2')->status->toBe(ProjectStatus::Published);

    $this->actingAs($this->viewer)
        ->get(route('workspace.projects.index', $this->team))
        ->assertInertia(fn (Assert $page) => $page->component('workspace/projects/Index')->has('projects', 1)->where('can.create', false));

    $this->actingAs($this->member)
        ->get(route('workspace.projects.edit', [$this->team, $project]))
        ->assertInertia(fn (Assert $page) => $page->component('workspace/projects/Edit')->where('project.titleEn', 'Line follower v2')->where('can.publish', false));
});

it('keeps other teams’ projects out of reach', function () {
    $other = Team::factory()->create();
    $foreign = Project::query()->create(['team_id' => $other->id, 'slug' => 'foreign', 'title_en' => 'Foreign', 'status' => 'draft']);

    $this->actingAs($this->captain)->get(route('workspace.projects.edit', [$this->team, $foreign]))->assertNotFound();
});

it('creates robots and shows their parts list with totals', function () {
    $this->actingAs($this->member)->post(route('workspace.robots.store', $this->team), ['name' => 'Speedy', 'status' => 'design', 'weight_g' => 900])->assertRedirect();
    $robot = Robot::query()->sole();

    $this->actingAs($this->member)->put(route('workspace.robots.bom', [$this->team, $robot]), ['lines' => [
        ['part_id' => $this->part->id, 'vendor_offer_id' => $this->offer->id, 'quantity' => 4],
    ]])->assertRedirect();

    $this->actingAs($this->viewer)
        ->get(route('workspace.robots.show', [$this->team, $robot]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/robots/Show')
            ->where('robot.name', 'Speedy')
            ->has('bom.lines', 1)
            ->where('bom.lines.0.part', 'SG90 servo')
            ->where('bom.totals.0.formatted', '34.000 LYD')
            ->where('bom.byVendor.0.vendor', 'Shop A')
            ->where('can.edit', false));

    $this->actingAs($this->viewer)->put(route('workspace.robots.bom', [$this->team, $robot]), ['lines' => []])->assertForbidden();
});

it('validates robots and the parts list', function () {
    $this->actingAs($this->member)->post(route('workspace.robots.store', $this->team), ['name' => '', 'status' => 'flying'])->assertSessionHasErrors(['name', 'status']);

    $robot = app(RobotService::class)->create($this->team, ['name' => 'Speedy', 'status' => 'design'], $this->member);
    $this->actingAs($this->member)->put(route('workspace.robots.bom', [$this->team, $robot]), ['lines' => [
        ['part_id' => $this->part->id, 'quantity' => 0],
    ]])->assertSessionHasErrors('lines.0.quantity');
});

it('searches the catalogue for the parts list editor', function () {
    $this->actingAs($this->member)
        ->getJson(route('workspace.catalogue.search', [$this->team, 'q' => 'sg']))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'SG90 servo')
        ->assertJsonPath('data.0.offers.0.formatted', '8.500 LYD');
});

it('deletes robots and projects for editors', function () {
    $robot = app(RobotService::class)->create($this->team, ['name' => 'Speedy', 'status' => 'design'], $this->member);

    $this->actingAs($this->member)->delete(route('workspace.robots.destroy', [$this->team, $robot]))->assertRedirect(route('workspace.robots.index', $this->team));
    expect(Robot::query()->count())->toBe(0);
});
