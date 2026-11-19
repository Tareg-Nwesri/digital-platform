<?php

use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamService;

beforeEach(function () {
    $this->features = app(TeamFeatures::class);
});

it('uses the override, else the tier value, else the fallback', function () {
    $tier = Tier::factory()->create(['features' => ['members.max' => 12, 'projects.publish' => true]]);
    $team = Team::factory()->for($tier)->create();

    expect($this->features->value($team, TeamFeature::MembersMax))->toBe(12)
        ->and($this->features->active($team, TeamFeature::ProjectsPublish))->toBeTrue()
        ->and($this->features->active($team, TeamFeature::EventsRegister))->toBeFalse()
        ->and($this->features->active($team, TeamFeature::RobotsCreate))->toBeTrue();

    $this->features->override($team, TeamFeature::MembersMax, 30);
    $this->features->override($team, TeamFeature::EventsRegister, true);

    expect($this->features->value($team, TeamFeature::MembersMax))->toBe(30)
        ->and($this->features->active($team, TeamFeature::EventsRegister))->toBeTrue();
});

it('falls back to the tier value when an override is cleared', function () {
    $team = Team::factory()->create();
    $this->features->override($team, TeamFeature::ProjectsPublish, true);

    $this->features->clearOverride($team, TeamFeature::ProjectsPublish);

    expect($this->features->active($team, TeamFeature::ProjectsPublish))->toBeFalse();
});

it('picks up new tier defaults but keeps overrides when the tier changes', function () {
    $starter = Tier::factory()->create();
    $competition = Tier::factory()->competition()->create();
    $plain = Team::factory()->for($starter)->create();
    $overridden = Team::factory()->for($starter)->create();
    $this->features->override($overridden, TeamFeature::MembersMax, 3);

    expect($this->features->value($plain, TeamFeature::MembersMax))->toBe(8);

    app(TeamService::class)->assignTier($plain, $competition);
    app(TeamService::class)->assignTier($overridden, $competition);

    expect($this->features->value($plain->fresh(), TeamFeature::MembersMax))->toBe(20)
        ->and($this->features->value($overridden->fresh(), TeamFeature::MembersMax))->toBe(3);
});

it('shows tier value, override and effective value for the console', function () {
    $team = Team::factory()->create();
    $this->features->override($team, TeamFeature::PartsSuggest, true);

    $row = collect($this->features->breakdown($team))->firstWhere('name', 'parts.suggest');

    expect($row)->toMatchArray(['type' => 'boolean', 'tierValue' => false, 'override' => true, 'effective' => true]);
});

it('treats a team as public only when active, marked public and the feature is on', function () {
    $competition = Tier::factory()->competition()->create();
    $visible = Team::factory()->for($competition)->public()->create();
    Team::factory()->for($competition)->create(['is_public' => false]);
    Team::factory()->for($competition)->public()->suspended()->create();
    $starterPublic = Team::factory()->public()->create();
    $overridden = Team::factory()->public()->create();
    $this->features->override($overridden, TeamFeature::PublicProfile, true);
    $switchedOff = Team::factory()->for($competition)->public()->create();
    $this->features->override($switchedOff, TeamFeature::PublicProfile, false);

    expect(Team::query()->public()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$visible->id, $overridden->id])->sort()->values()->all())
        ->and($starterPublic->exists)->toBeTrue();
});

it('keeps only http and https official websites', function (string $url, ?string $expected) {
    $team = Team::factory()->create(['official_url' => $url]);

    expect($team->fresh()->official_url)->toBe($expected);
})->with([
    'https' => ['https://robo.ly', 'https://robo.ly'],
    'javascript' => ['javascript:alert(1)', null],
    'data' => ['data:text/html,x', null],
]);
