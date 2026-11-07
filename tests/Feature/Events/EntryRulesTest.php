<?php

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventVisibility;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Services\EntryContext;
use App\Domain\Events\Services\EntryRules;
use App\Domain\Events\Services\RegistrationService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamMemberService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Support\Carbon;

/*
 * LLD 06 §4: EntryRules is pure; it lists every rule a team breaks for a
 * category. Robot rules join once robots exist (phase 3).
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);
    Carbon::setTestNow('2027-02-01 10:00:00');

    $this->department = app(DepartmentService::class)->create(['name_en' => 'Events']);
    $this->event = Event::factory()->for($this->department)->open()->create([
        'starts_at' => '2027-03-10 08:00:00',
        'ends_at' => '2027-03-10 18:00:00',
        'registration_opens_at' => '2027-01-15 00:00:00',
        'registration_closes_at' => '2027-03-01 00:00:00',
    ]);
    $this->category = EventCategory::factory()->for($this->event)->create([
        'members_min' => 2,
        'members_max' => 3,
    ]);

    $this->team = Team::factory()->create(['tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
    $this->members = User::factory()->count(4)->create(['birthdate' => '2012-05-05'])->all();
    foreach ($this->members as $index => $member) {
        app(TeamMemberService::class)->join($this->team, $member, $index === 0 ? TeamRole::Captain : TeamRole::Member);
    }
});

function ruleCodes(Event $event, EventCategory $category, Team $team, array $members, string $mode = 'apply'): array
{
    return collect(app(EntryRules::class)->check($event->fresh(), $category->fresh(), $team->fresh(), $members, EntryContext::{$mode}()))
        ->pluck('code')
        ->sort()
        ->values()
        ->all();
}

it('passes an eligible team', function () {
    expect(ruleCodes($this->event, $this->category, $this->team, array_slice($this->members, 0, 2)))->toBe([]);
});

it('refuses a team that is not active', function () {
    $this->team->update(['status' => 'suspended']);

    expect(ruleCodes($this->event, $this->category, $this->team, array_slice($this->members, 0, 2), 'add'))->toContain('team_inactive');
});

it('needs the events.register feature to apply, but not for organiser additions', function () {
    $this->team->update(['tier_id' => Tier::query()->where('slug', 'starter')->value('id')]);
    app(TeamFeatures::class)->forgetAll($this->team);

    expect(ruleCodes($this->event, $this->category, $this->team, array_slice($this->members, 0, 2)))->toContain('feature_off')
        ->and(ruleCodes($this->event, $this->category, $this->team, array_slice($this->members, 0, 2), 'add'))->not->toContain('feature_off');
});

it('hides invite-only events from teams that were not invited', function () {
    $this->event->update(['visibility' => EventVisibility::InviteOnly]);

    expect(ruleCodes($this->event, $this->category, $this->team, array_slice($this->members, 0, 2)))->toContain('not_visible');

    $this->event->invitedTeams()->attach($this->team->id);

    expect(ruleCodes($this->event, $this->category, $this->team, array_slice($this->members, 0, 2)))->not->toContain('not_visible');
});

it('refuses applications outside the registration window', function (string $now) {
    Carbon::setTestNow($now);

    expect(ruleCodes($this->event, $this->category, $this->team, array_slice($this->members, 0, 2)))->toContain('window_closed');
})->with([
    'before it opens' => ['2027-01-10 10:00:00'],
    'after it closes' => ['2027-03-02 10:00:00'],
]);

it('refuses applications while the event is not open', function () {
    $this->event->update(['status' => EventStatus::Closed]);

    expect(ruleCodes($this->event, $this->category, $this->team, array_slice($this->members, 0, 2)))->toContain('window_closed');
});

it('lets organisers add teams while open, closed or running, but not in draft or after the event', function (EventStatus $status, bool $allowed) {
    $this->event->update(['status' => $status]);

    $codes = ruleCodes($this->event, $this->category, $this->team, array_slice($this->members, 0, 2), 'add');

    expect(in_array('event_closed', $codes, true))->toBe(! $allowed);
})->with([
    'draft' => [EventStatus::Draft, false],
    'open' => [EventStatus::Open, true],
    'closed' => [EventStatus::Closed, true],
    'running' => [EventStatus::Running, true],
    'finished' => [EventStatus::Finished, false],
]);

it('keeps the member count within the category limits', function (int $count, bool $ok) {
    $codes = ruleCodes($this->event, $this->category, $this->team, array_slice($this->members, 0, $count));

    expect(in_array('members_count', $codes, true))->toBe(! $ok);
})->with([
    'too few' => [1, false],
    'minimum' => [2, true],
    'maximum' => [3, true],
    'too many' => [4, false],
]);

it('refuses members who are not in the team', function () {
    $stranger = User::factory()->create(['birthdate' => '2012-01-01']);

    expect(ruleCodes($this->event, $this->category, $this->team, [$this->members[0], $stranger]))->toContain('member_not_in_team');
});

it('needs birthdates when the category has age limits', function () {
    $this->category->update(['age_min' => 10, 'age_max' => 16]);
    $this->members[1]->update(['birthdate' => null]);

    expect(ruleCodes($this->event, $this->category, $this->team, array_slice($this->members, 0, 2)))->toContain('member_no_birthdate');
});

it('checks age on the event start date in the event timezone', function () {
    // 00:30 on 1 March in Tripoli is still 28 February in UTC.
    $this->event->update(['starts_at' => '2027-02-28 22:30:00', 'ends_at' => '2027-03-01 18:00:00', 'registration_closes_at' => '2027-02-20 00:00:00', 'timezone' => 'Africa/Tripoli']);
    $this->category->update(['age_min' => 12]);
    $this->members[1]->update(['birthdate' => '2015-03-01']);

    expect(ruleCodes($this->event, $this->category, $this->team, array_slice($this->members, 0, 2)))->not->toContain('member_age');

    $this->members[1]->update(['birthdate' => '2015-03-02']);

    expect(ruleCodes($this->event, $this->category, $this->team, array_slice($this->members, 0, 2)))->toContain('member_age');
});

it('refuses a second active entry in the same category', function () {
    app(RegistrationService::class)->apply($this->event, $this->category, $this->team, [$this->members[0]->id, $this->members[1]->id], $this->members[0]);

    expect(ruleCodes($this->event, $this->category, $this->team, array_slice($this->members, 0, 2)))->toContain('duplicate');
});

it('reports every broken rule with a message', function () {
    $this->team->update(['status' => 'suspended']);

    $violations = app(EntryRules::class)->check($this->event->fresh(), $this->category, $this->team->fresh(), [$this->members[0]], EntryContext::apply());

    expect($violations)->toHaveCount(2)
        ->and(collect($violations)->every(fn ($violation) => filled($violation->message)))->toBeTrue();
});
