<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Events\Enums\RegistrationSource;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Services\RegistrationService;
use App\Domain\Organisation\Services\DepartmentPermissionService;
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
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/*
 * LLD 06 §5: applying, organiser additions, approval with capacity and the
 * waitlist, overrides, withdrawals and the frozen members snapshot.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);
    Carbon::setTestNow('2027-02-01 10:00:00');

    $superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $superAdmin->assignRole(User::SUPER_ADMIN));

    $departments = app(DepartmentService::class);
    $this->department = $departments->create(['name_en' => 'Events']);
    app(DepartmentPermissionService::class)->setCeiling($this->department, ['events.view', 'events.edit'], $superAdmin->fresh());
    $this->admin = User::factory()->create();
    $this->editor = User::factory()->create();
    $departments->addMember($this->department, $this->admin, isAdmin: true);
    $departments->addMember($this->department, $this->editor);
    app(DepartmentPermissionService::class)->syncMember($this->department, $this->editor, ['events.view', 'events.edit'], $this->admin);

    $this->event = Event::factory()->for($this->department)->open()->create([
        'starts_at' => '2027-03-10 08:00:00',
        'ends_at' => '2027-03-10 18:00:00',
        'registration_opens_at' => '2027-01-15 00:00:00',
        'registration_closes_at' => '2027-03-01 00:00:00',
    ]);
    $this->category = EventCategory::factory()->for($this->event)->create(['members_min' => 1, 'members_max' => 3]);

    $this->teams = collect(range(1, 4))->map(function (int $i) {
        $team = Team::factory()->create(['name' => "Team {$i}", 'tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
        $captain = User::factory()->create(['name_en' => "Captain {$i}", 'birthdate' => '2011-06-15']);
        app(TeamMemberService::class)->join($team, $captain, TeamRole::Captain);
        $team->setRelation('captainUser', $captain);

        return $team;
    });
});

function enter(Team $team, ?EventCategory $category = null): EventRegistration
{
    $test = test();

    return app(RegistrationService::class)->apply(
        $test->event,
        $category ?? $test->category,
        $team,
        [$team->captainUser->id],
        $team->captainUser,
    );
}

it('creates a pending application with the chosen members', function () {
    $registration = enter($this->teams[0]);

    expect($registration->status)->toBe(RegistrationStatus::Pending)
        ->and($registration->source)->toBe(RegistrationSource::Applied)
        ->and($registration->members()->pluck('users.id')->all())->toBe([$this->teams[0]->captainUser->id])
        ->and($registration->applied_by)->toBe($this->teams[0]->captainUser->id);
});

it('refuses an application that breaks the rules', function () {
    Carbon::setTestNow('2027-03-05 10:00:00');

    expect(fn () => enter($this->teams[0]))->toThrow(DomainRuleViolation::class);
});

it('lets a team apply again after a rejection, but never hold two active entries', function () {
    $first = enter($this->teams[0]);

    expect(fn () => enter($this->teams[0]))->toThrow(DomainRuleViolation::class);

    app(RegistrationService::class)->reject($first, 'Incomplete documents', $this->admin);

    $second = enter($this->teams[0]);

    expect($second->status)->toBe(RegistrationStatus::Pending)
        ->and($first->fresh()->status)->toBe(RegistrationStatus::Rejected)
        ->and($first->fresh()->decision_note)->toBe('Incomplete documents');
});

it('blocks duplicate active entries in the database too', function () {
    $first = enter($this->teams[0]);

    expect(fn () => EventRegistration::query()->create([
        'event_id' => $this->event->id,
        'category_id' => $this->category->id,
        'team_id' => $this->teams[0]->id,
        'source' => RegistrationSource::Applied,
        'status' => RegistrationStatus::Pending,
    ]))->toThrow(QueryException::class);
});

it('sends approvals beyond the event capacity to the waitlist in order', function () {
    $this->event->update(['capacity' => 1]);
    $service = app(RegistrationService::class);

    $registrations = $this->teams->take(3)->map(fn (Team $team) => enter($team));
    $registrations->each(fn (EventRegistration $registration) => $service->approve($registration, null, $this->admin));

    expect($registrations->map(fn ($r) => $r->fresh()->status)->all())->toBe([
        RegistrationStatus::Approved, RegistrationStatus::Waitlisted, RegistrationStatus::Waitlisted,
    ])->and($registrations->map(fn ($r) => $r->fresh()->waitlist_position)->all())->toBe([null, 1, 2]);
});

it('respects a category capacity as well', function () {
    $this->category->update(['capacity' => 1]);
    $other = EventCategory::factory()->for($this->event)->create(['members_min' => 1, 'members_max' => 3]);
    $service = app(RegistrationService::class);

    $service->approve(enter($this->teams[0]), null, $this->admin);
    $full = $service->approve(enter($this->teams[1]), null, $this->admin);
    $elsewhere = $service->approve(enter($this->teams[2], $other), null, $this->admin);

    expect($full->status)->toBe(RegistrationStatus::Waitlisted)
        ->and($elsewhere->status)->toBe(RegistrationStatus::Approved);
});

it('counts organiser-added teams against capacity like approved applications', function () {
    $this->event->update(['capacity' => 1]);
    $service = app(RegistrationService::class);
    $team = $this->teams[0];

    $added = $service->addByOrganiser($this->event, $this->category, $team, [$team->captainUser->id], false, null, $this->editor);
    $applied = $service->approve(enter($this->teams[1]), null, $this->admin);

    expect($added->status)->toBe(RegistrationStatus::Approved)
        ->and($added->source)->toBe(RegistrationSource::Organiser)
        ->and($applied->status)->toBe(RegistrationStatus::Waitlisted);
});

it('sends an invitation the team accepts', function () {
    $service = app(RegistrationService::class);
    $team = $this->teams[0];

    $invited = $service->addByOrganiser($this->event, $this->category, $team, [$team->captainUser->id], true, null, $this->editor);

    expect($invited->status)->toBe(RegistrationStatus::Invited);

    $accepted = $service->acceptInvite($invited, [$team->captainUser->id], $team->captainUser);

    expect($accepted->status)->toBe(RegistrationStatus::Approved);
});

it('promotes the first eligible waitlisted team when an approved team withdraws', function () {
    $this->event->update(['capacity' => 1]);
    $service = app(RegistrationService::class);
    [$a, $b, $c] = $this->teams->take(3)->map(fn (Team $team) => $service->approve(enter($team), null, $this->admin))->all();

    $this->teams[1]->update(['status' => 'suspended']);

    $service->withdraw($a, $this->admin, byOrganiser: true);

    expect($a->fresh()->status)->toBe(RegistrationStatus::Withdrawn)
        ->and($a->fresh()->withdrawn_at)->not->toBeNull()
        ->and($b->fresh()->status)->toBe(RegistrationStatus::Waitlisted)
        ->and($c->fresh()->status)->toBe(RegistrationStatus::Approved)
        ->and($c->fresh()->waitlist_position)->toBeNull();
});

it('leaves the waitlist alone when auto-promotion is off', function () {
    config(['platform.settings' => array_merge(config('platform.settings'), ['events.auto_promote_waitlist' => false])]);
    $this->event->update(['capacity' => 1]);
    $service = app(RegistrationService::class);
    [$a, $b] = $this->teams->take(2)->map(fn (Team $team) => $service->approve(enter($team), null, $this->admin))->all();

    $service->withdraw($a, $this->admin, byOrganiser: true);

    expect($b->fresh()->status)->toBe(RegistrationStatus::Waitlisted);

    expect($service->promoteFromWaitlist($this->event, $this->admin)?->is($b))->toBeTrue()
        ->and($b->fresh()->status)->toBe(RegistrationStatus::Approved);
});

it('stops teams withdrawing after registration closes, but not organisers', function () {
    $registration = enter($this->teams[0]);
    Carbon::setTestNow('2027-03-02 10:00:00');

    expect(fn () => app(RegistrationService::class)->withdraw($registration, $this->teams[0]->captainUser))
        ->toThrow(DomainRuleViolation::class);

    app(RegistrationService::class)->withdraw($registration, $this->editor, byOrganiser: true);

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Withdrawn);
});

it('lets department admins override violations with a recorded reason, but not members', function () {
    $registration = enter($this->teams[0]);
    $this->category->update(['age_max' => 12]);
    $service = app(RegistrationService::class);

    expect(fn () => $service->approve($registration, null, $this->admin))->toThrow(DomainRuleViolation::class)
        ->and(fn () => $service->approve($registration, 'Coach confirmed', $this->editor))->toThrow(DomainRuleViolation::class);

    $approved = $service->approve($registration, 'Age waiver from the organising committee', $this->admin);

    expect($approved->status)->toBe(RegistrationStatus::Approved)
        ->and($approved->override_reason)->toBe('Age waiver from the organising committee')
        ->and($approved->override_violations)->toContain('member_age');
});

it('freezes the members snapshot on approval', function () {
    $registration = app(RegistrationService::class)->approve(enter($this->teams[0]), null, $this->admin);
    $captain = $this->teams[0]->captainUser;

    $captain->update(['name_en' => 'Renamed', 'birthdate' => '2000-01-01']);

    expect($registration->fresh()->members_snapshot)->toEqual([[
        'user_id' => $captain->id,
        'name' => 'Captain 1',
        'age' => 15,
        'role' => 'captain',
    ]]);
});

it('refuses transitions the state machine does not allow', function () {
    $registration = app(RegistrationService::class)->approve(enter($this->teams[0]), null, $this->admin);

    expect(fn () => app(RegistrationService::class)->reject($registration, 'Too late', $this->admin))
        ->toThrow(InvalidTransition::class);
});

it('logs every decision', function () {
    $registration = enter($this->teams[0]);
    app(RegistrationService::class)->approve($registration, null, $this->admin);

    expect(Activity::query()->where('event', 'registration.applied')->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'registration.approved')->where('causer_id', $this->admin->id)->exists())->toBeTrue();
});
