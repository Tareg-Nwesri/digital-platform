<?php

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventVisibility;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Services\EventService;
use App\Domain\Events\Services\RegistrationService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamMemberService;
use App\Exceptions\DomainRuleViolation;
use App\Exceptions\InvalidTransition;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Support\Carbon;

/*
 * LLD 06 §3, §5: the event lifecycle, categories, invitations, results and
 * the public / team visibility scopes.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);
    Carbon::setTestNow('2027-02-01 10:00:00');

    $this->department = app(DepartmentService::class)->create(['name_en' => 'Events']);
    $this->user = User::factory()->create();
    $this->service = app(EventService::class);
});

it('creates a draft event with a unique slug', function () {
    $event = $this->service->create([
        'department_id' => $this->department->id,
        'title_en' => 'Robot Cup',
        'starts_at' => '2027-03-10 08:00:00',
        'ends_at' => '2027-03-10 18:00:00',
        'visibility' => EventVisibility::Public->value,
    ], $this->user);
    $again = $this->service->create([
        'department_id' => $this->department->id,
        'title_en' => 'Robot Cup',
        'starts_at' => '2027-04-10 08:00:00',
        'ends_at' => '2027-04-10 18:00:00',
        'visibility' => EventVisibility::Public->value,
    ], $this->user);

    expect($event->status)->toBe(EventStatus::Draft)
        ->and($event->slug)->toBe('robot-cup')
        ->and($again->slug)->toBe('robot-cup-2')
        ->and($event->timezone)->toBe('Africa/Tripoli')
        ->and($event->created_by)->toBe($this->user->id);
});

it('needs a category before opening and defaults the closing date to the start', function () {
    $event = Event::factory()->for($this->department)->create(['registration_closes_at' => null]);

    expect(fn () => $this->service->transition($event, EventStatus::Open, $this->user))->toThrow(DomainRuleViolation::class);

    EventCategory::factory()->for($event)->create();
    $this->service->transition($event->fresh(), EventStatus::Open, $this->user);

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Open)
        ->and($event->registration_closes_at->equalTo($event->starts_at))->toBeTrue();
});

it('follows the event lifecycle', function () {
    $event = Event::factory()->for($this->department)->open()->create();
    EventCategory::factory()->for($event)->create();

    expect(fn () => $this->service->transition($event, EventStatus::Finished, $this->user))->toThrow(InvalidTransition::class);

    foreach ([EventStatus::Closed, EventStatus::Open, EventStatus::Closed, EventStatus::Running, EventStatus::Finished, EventStatus::Archived] as $status) {
        $this->service->transition($event, $status, $this->user);
    }

    expect($event->fresh()->status)->toBe(EventStatus::Archived);
});

it('saves categories but keeps those with active registrations', function () {
    $event = Event::factory()->for($this->department)->open()->create();
    $kept = EventCategory::factory()->for($event)->create(['name_en' => 'Line follower', 'members_min' => 1]);
    $team = Team::factory()->create(['tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
    $captain = User::factory()->create(['birthdate' => '2012-01-01']);
    app(TeamMemberService::class)->join($team, $captain, TeamRole::Captain);
    app(RegistrationService::class)->apply($event, $kept, $team, [$captain->id], $captain);

    $this->service->saveCategories($event, [
        ['id' => $kept->id, 'name_en' => 'Line follower (junior)', 'members_min' => 1, 'members_max' => 4, 'robots_per_team' => 1],
        ['name_en' => 'Sumo', 'members_min' => 2, 'members_max' => 5, 'robots_per_team' => 1, 'age_min' => 10, 'age_max' => 18],
    ], $this->user);

    expect($event->categories()->pluck('name_en')->all())->toBe(['Line follower (junior)', 'Sumo']);

    expect(fn () => $this->service->saveCategories($event, [
        ['name_en' => 'Sumo', 'members_min' => 2, 'members_max' => 5, 'robots_per_team' => 1],
    ], $this->user))->toThrow(DomainRuleViolation::class);
});

it('invites and uninvites teams for invite-only events', function () {
    $event = Event::factory()->for($this->department)->open()->create(['visibility' => EventVisibility::InviteOnly]);
    $team = Team::factory()->create();
    $other = Team::factory()->create();

    $this->service->inviteTeams($event, [$team->id, $team->id], $this->user);

    expect(Event::query()->visibleToTeam($team)->pluck('id')->all())->toBe([$event->id])
        ->and(Event::query()->visibleToTeam($other)->exists())->toBeFalse();

    $this->service->uninviteTeam($event, $team, $this->user);

    expect(Event::query()->visibleToTeam($team)->exists())->toBeFalse();
});

it('hides invite-only events from the public until results are published', function () {
    $public = Event::factory()->for($this->department)->open()->create();
    $draft = Event::factory()->for($this->department)->create();
    $private = Event::factory()->for($this->department)->create(['visibility' => EventVisibility::InviteOnly, 'status' => EventStatus::Finished]);

    expect(Event::query()->public()->pluck('id')->all())->toBe([$public->id]);

    $this->service->publishResults($private, $this->user);

    expect(Event::query()->public()->pluck('id')->sort()->values()->all())->toBe([$public->id, $private->id])
        ->and($draft->fresh()->results_published_at)->toBeNull();
});

it('records results only after the event finishes', function () {
    $event = Event::factory()->for($this->department)->open()->create();
    $category = EventCategory::factory()->for($event)->create(['members_min' => 1]);
    $team = Team::factory()->create(['tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
    $captain = User::factory()->create(['birthdate' => '2012-01-01']);
    app(TeamMemberService::class)->join($team, $captain, TeamRole::Captain);
    $registration = app(RegistrationService::class)->approve(
        app(RegistrationService::class)->apply($event, $category, $team, [$captain->id], $captain),
        null,
        User::factory()->create(),
    );

    $rows = [['registration_id' => $registration->id, 'placement' => 1, 'score' => 98.5, 'award_en' => 'Best design']];

    expect(fn () => $this->service->recordResults($event, $rows, $this->user))->toThrow(DomainRuleViolation::class);

    $event->update(['status' => EventStatus::Finished]);
    $this->service->recordResults($event->fresh(), $rows, $this->user);
    $this->service->recordResults($event->fresh(), [['registration_id' => $registration->id, 'placement' => 2]], $this->user);

    expect($registration->result()->first())
        ->placement->toBe(2)
        ->award_en->toBeNull();
});

it('deletes only draft events', function () {
    $draft = Event::factory()->for($this->department)->create();
    $open = Event::factory()->for($this->department)->open()->create();

    $this->service->delete($draft, $this->user);

    expect(Event::query()->whereKey($draft->id)->exists())->toBeFalse()
        ->and(fn () => $this->service->delete($open, $this->user))->toThrow(DomainRuleViolation::class);
});
