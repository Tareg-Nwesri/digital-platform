<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Events\Enums\EventStatus;
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
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 06 §6–9: the events area of the console. Staff work on events owned
 * by their department: events.view to see, events.edit to configure and
 * add teams, events.approve (department admins) to decide entries and
 * publish results. Other departments see nothing.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);
    Carbon::setTestNow('2027-02-01 10:00:00');

    $this->superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $this->superAdmin->assignRole(User::SUPER_ADMIN));
    $this->superAdmin = $this->superAdmin->fresh();

    $departments = app(DepartmentService::class);
    $permissions = app(DepartmentPermissionService::class);

    $this->events = $departments->create(['name_en' => 'Events']);
    $permissions->setCeiling($this->events, ['events.view', 'events.edit'], $this->superAdmin);
    $this->media = $departments->create(['name_en' => 'Media']);
    $permissions->setCeiling($this->media, ['events.view', 'events.edit'], $this->superAdmin);

    $this->admin = User::factory()->create(['name_en' => 'Events Lead']);
    $this->editor = User::factory()->create(['name_en' => 'Event Editor']);
    $this->viewer = User::factory()->create(['name_en' => 'Event Viewer']);
    $this->outsider = User::factory()->create(['name_en' => 'Media Editor']);
    $departments->addMember($this->events, $this->admin, isAdmin: true);
    $departments->addMember($this->events, $this->editor);
    $departments->addMember($this->events, $this->viewer);
    $departments->addMember($this->media, $this->outsider);
    $permissions->syncMember($this->events, $this->editor, ['events.view', 'events.edit'], $this->admin);
    $permissions->syncMember($this->events, $this->viewer, ['events.view'], $this->admin);
    $permissions->syncMember($this->media, $this->outsider, ['events.view', 'events.edit'], $this->superAdmin);

    $this->event = Event::factory()->for($this->events)->open()->create([
        'title_en' => 'Robot Cup',
        'slug' => 'robot-cup',
        'starts_at' => '2027-03-10 08:00:00',
        'ends_at' => '2027-03-10 18:00:00',
        'registration_opens_at' => '2027-01-15 00:00:00',
        'registration_closes_at' => '2027-03-01 00:00:00',
    ]);
    $this->category = EventCategory::factory()->for($this->event)->create(['name_en' => 'Sumo', 'members_min' => 1, 'members_max' => 3]);
    $this->mediaEvent = Event::factory()->for($this->media)->create(['title_en' => 'Media Day']);

    $this->team = Team::factory()->create(['name' => 'Falcons', 'tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
    $this->captain = User::factory()->create(['name_en' => 'Captain Ali', 'birthdate' => '2011-06-15']);
    app(TeamMemberService::class)->join($this->team, $this->captain, TeamRole::Captain);
});

function eventPayload(array $overrides = []): array
{
    return array_merge([
        'title_en' => 'Spring Challenge',
        'title_ar' => 'تحدي الربيع',
        'department_id' => test()->events->id,
        'venue' => 'Tripoli Expo Hall',
        'city' => 'Tripoli',
        'starts_at' => '2027-04-10 09:00',
        'ends_at' => '2027-04-10 17:00',
        'registration_opens_at' => '2027-02-01 00:00',
        'registration_closes_at' => '2027-04-01 00:00',
        'visibility' => 'public',
        'capacity' => 20,
    ], $overrides);
}

function applyAsTeam(): EventRegistration
{
    $test = test();

    return app(RegistrationService::class)->apply($test->event, $test->category, $test->team, [$test->captain->id], $test->captain);
}

it('lists only events from the user’s departments', function () {
    $this->actingAs($this->viewer)
        ->get(route('console.events.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/events/Index')
            ->has('events.data', 1)
            ->where('events.data.0.title', 'Robot Cup')
            ->where('events.data.0.status', 'open')
            ->where('can.create', false));

    $this->actingAs($this->superAdmin)
        ->get(route('console.events.index'))
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 2)->where('can.create', true));
});

it('filters the list by status', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('console.events.index', ['filter' => ['status' => 'draft']]))
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.title', 'Media Day'));
});

it('refuses the events area to staff without events permissions', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('console.events.index'))->assertForbidden();
});

it('creates a draft event in one of the editor’s departments', function () {
    $this->actingAs($this->editor)
        ->post(route('console.events.store'), eventPayload())
        ->assertRedirect(route('console.events.show', 'spring-challenge'));

    $event = Event::query()->where('slug', 'spring-challenge')->firstOrFail();

    expect($event->status)->toBe(EventStatus::Draft)
        ->and($event->department_id)->toBe($this->events->id)
        ->and($event->created_by)->toBe($this->editor->id)
        ->and($event->capacity)->toBe(20)
        ->and($event->starts_at->utc()->format('Y-m-d H:i'))->toBe('2027-04-10 07:00');
});

it('refuses to create events for another department or without edit rights', function () {
    $this->actingAs($this->editor)
        ->post(route('console.events.store'), eventPayload(['department_id' => $this->media->id]))
        ->assertSessionHasErrors('department_id');

    $this->actingAs($this->viewer)
        ->post(route('console.events.store'), eventPayload())
        ->assertForbidden();
});

it('validates event dates', function (array $payload, string $error) {
    $this->actingAs($this->editor)
        ->post(route('console.events.store'), eventPayload($payload))
        ->assertSessionHasErrors($error);
})->with([
    'missing title' => [['title_en' => ''], 'title_en'],
    'ends before it starts' => [['ends_at' => '2027-04-09 09:00'], 'ends_at'],
    'closes after it starts' => [['registration_closes_at' => '2027-04-11 00:00'], 'registration_closes_at'],
    'opens after it closes' => [['registration_opens_at' => '2027-04-02 00:00'], 'registration_opens_at'],
    'bad visibility' => [['visibility' => 'secret'], 'visibility'],
    'capacity too high' => [['capacity' => 5000], 'capacity'],
]);

it('shows the event with its categories, entries and permissions', function () {
    applyAsTeam();

    $this->actingAs($this->viewer)
        ->get(route('console.events.show', $this->event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/events/Show')
            ->where('event.title', 'Robot Cup')
            ->where('event.status', 'open')
            ->where('transitions', ['closed'])
            ->has('categories', 1)
            ->where('categories.0.name', 'Sumo')
            ->has('registrations', 1)
            ->where('registrations.0.team.name', 'Falcons')
            ->where('registrations.0.status', 'pending')
            ->where('registrations.0.members.0.name', 'Captain Ali')
            ->where('can.edit', false)
            ->where('can.review', false));

    $this->actingAs($this->admin)
        ->get(route('console.events.show', $this->event))
        ->assertInertia(fn (Assert $page) => $page->where('can.edit', true)->where('can.review', true));
});

it('hides events of other departments', function () {
    $this->actingAs($this->outsider)->get(route('console.events.show', $this->event))->assertForbidden();
    $this->actingAs($this->outsider)->patch(route('console.events.update', $this->event), eventPayload(['department_id' => $this->media->id]))->assertForbidden();
});

it('updates the event details', function () {
    $this->actingAs($this->editor)
        ->patch(route('console.events.update', $this->event), eventPayload(['title_en' => 'Robot Cup 2027', 'starts_at' => '2027-03-10 08:00', 'ends_at' => '2027-03-10 18:00', 'registration_closes_at' => '2027-03-01 00:00', 'registration_opens_at' => '2027-01-15 00:00']))
        ->assertRedirect(route('console.events.show', 'robot-cup'));

    expect($this->event->fresh()->title_en)->toBe('Robot Cup 2027');
});

it('moves the event through its lifecycle', function () {
    $this->actingAs($this->editor)
        ->patch(route('console.events.status', $this->event), ['status' => 'closed'])
        ->assertRedirect();

    expect($this->event->fresh()->status)->toBe(EventStatus::Closed);

    $this->actingAs($this->editor)
        ->from(route('console.events.show', $this->event))
        ->patch(route('console.events.status', $this->event), ['status' => 'archived'])
        ->assertSessionHasErrors('status');
});

it('saves categories and validates their limits', function () {
    $this->actingAs($this->editor)
        ->put(route('console.events.categories', $this->event), ['categories' => [
            ['id' => $this->category->id, 'name_en' => 'Sumo', 'members_min' => 2, 'members_max' => 4, 'robots_per_team' => 1, 'age_min' => 10, 'age_max' => 16],
            ['name_en' => 'Line follower', 'members_min' => 1, 'members_max' => 3, 'robots_per_team' => 2, 'capacity' => 8],
        ]])
        ->assertRedirect();

    expect($this->event->categories()->pluck('name_en')->all())->toBe(['Sumo', 'Line follower'])
        ->and($this->category->fresh()->age_max)->toBe(16);

    $this->actingAs($this->editor)
        ->put(route('console.events.categories', $this->event), ['categories' => [
            ['name_en' => 'Sumo', 'members_min' => 4, 'members_max' => 2, 'robots_per_team' => 1, 'age_min' => 16, 'age_max' => 10],
        ]])
        ->assertSessionHasErrors(['categories.0.members_max', 'categories.0.age_max']);
});

it('invites teams and removes invitations', function () {
    $this->actingAs($this->editor)
        ->post(route('console.events.invitations.store', $this->event), ['team_ids' => [$this->team->id]])
        ->assertRedirect();

    expect($this->event->hasInvited($this->team))->toBeTrue();

    $this->actingAs($this->editor)
        ->delete(route('console.events.invitations.destroy', [$this->event, $this->team]))
        ->assertRedirect();

    expect($this->event->hasInvited($this->team))->toBeFalse();
});

it('lets editors add a team directly, approved straight away', function () {
    $this->actingAs($this->editor)
        ->post(route('console.events.registrations.store', $this->event), [
            'team_id' => $this->team->id,
            'category_id' => $this->category->id,
            'member_ids' => [$this->captain->id],
        ])
        ->assertRedirect();

    expect(EventRegistration::query()->sole()->status)->toBe(RegistrationStatus::Approved);
});

it('shows rule violations when adding a team breaks them', function () {
    $this->category->update(['age_max' => 12]);

    $this->actingAs($this->editor)
        ->from(route('console.events.show', $this->event))
        ->post(route('console.events.registrations.store', $this->event), [
            'team_id' => $this->team->id,
            'category_id' => $this->category->id,
            'member_ids' => [$this->captain->id],
            'override_reason' => 'Editors cannot override',
        ])
        ->assertSessionHasErrors('registration');

    $this->actingAs($this->admin)
        ->post(route('console.events.registrations.store', $this->event), [
            'team_id' => $this->team->id,
            'category_id' => $this->category->id,
            'member_ids' => [$this->captain->id],
            'override_reason' => 'Committee waiver',
        ])
        ->assertSessionHasNoErrors();

    expect(EventRegistration::query()->sole()->override_reason)->toBe('Committee waiver');
});

it('refuses a category from another event', function () {
    $other = EventCategory::factory()->for($this->mediaEvent)->create();

    $this->actingAs($this->editor)
        ->post(route('console.events.registrations.store', $this->event), [
            'team_id' => $this->team->id,
            'category_id' => $other->id,
            'member_ids' => [$this->captain->id],
        ])
        ->assertSessionHasErrors('category_id');
});

it('lets department admins approve, waitlist and reject, but not editors', function () {
    $registration = applyAsTeam();

    $this->actingAs($this->editor)->post(route('console.registrations.approve', $registration))->assertForbidden();
    $this->actingAs($this->editor)->post(route('console.registrations.reject', $registration), ['reason' => 'No'])->assertForbidden();

    $this->actingAs($this->admin)->post(route('console.registrations.waitlist', $registration))->assertRedirect();
    expect($registration->fresh()->status)->toBe(RegistrationStatus::Waitlisted);

    $this->actingAs($this->admin)->post(route('console.registrations.approve', $registration))->assertRedirect();
    expect($registration->fresh()->status)->toBe(RegistrationStatus::Approved);
});

it('needs a reason to reject', function () {
    $registration = applyAsTeam();

    $this->actingAs($this->admin)
        ->post(route('console.registrations.reject', $registration), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    $this->actingAs($this->admin)
        ->post(route('console.registrations.reject', $registration), ['reason' => 'Missing safety forms'])
        ->assertRedirect();

    expect($registration->fresh())
        ->status->toBe(RegistrationStatus::Rejected)
        ->decision_note->toBe('Missing safety forms');
});

it('lets editors withdraw an entry and admins promote from the waitlist', function () {
    $this->event->update(['capacity' => 1]);
    $first = applyAsTeam();
    app(RegistrationService::class)->approve($first, null, $this->admin);

    $second = Team::factory()->create(['tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
    $captain = User::factory()->create(['birthdate' => '2012-01-01']);
    app(TeamMemberService::class)->join($second, $captain, TeamRole::Captain);
    $waiting = app(RegistrationService::class)->approve(
        app(RegistrationService::class)->apply($this->event, $this->category, $second, [$captain->id], $captain),
        null,
        $this->admin,
    );

    config(['platform.settings' => array_merge(config('platform.settings'), ['events.auto_promote_waitlist' => false])]);

    $this->actingAs($this->editor)->post(route('console.registrations.withdraw', $first))->assertRedirect();
    expect($first->fresh()->status)->toBe(RegistrationStatus::Withdrawn);

    $this->actingAs($this->editor)->post(route('console.events.waitlist.promote', $this->event))->assertForbidden();
    $this->actingAs($this->admin)->post(route('console.events.waitlist.promote', $this->event))->assertRedirect();

    expect($waiting->fresh()->status)->toBe(RegistrationStatus::Approved);
});

it('records results after the event and lets the admin publish them', function () {
    $registration = app(RegistrationService::class)->approve(applyAsTeam(), null, $this->admin);
    $this->event->update(['status' => EventStatus::Finished]);

    $this->actingAs($this->editor)
        ->put(route('console.events.results', $this->event), ['results' => [
            ['registration_id' => $registration->id, 'placement' => 1, 'score' => '97.5', 'award_en' => 'Champion'],
        ]])
        ->assertRedirect();

    expect($registration->result()->first()->placement)->toBe(1);

    $this->actingAs($this->editor)->post(route('console.events.results.publish', $this->event))->assertForbidden();
    $this->actingAs($this->admin)->post(route('console.events.results.publish', $this->event))->assertRedirect();

    expect($this->event->fresh()->results_published_at)->not->toBeNull();
});

it('validates result rows', function () {
    $registration = app(RegistrationService::class)->approve(applyAsTeam(), null, $this->admin);
    $this->event->update(['status' => EventStatus::Finished]);

    $this->actingAs($this->editor)
        ->put(route('console.events.results', $this->event), ['results' => [
            ['registration_id' => $registration->id, 'placement' => 0],
        ]])
        ->assertSessionHasErrors('results.0.placement');
});

it('deletes draft events only', function () {
    $draft = Event::factory()->for($this->events)->create(['slug' => 'draft-cup']);

    $this->actingAs($this->editor)->delete(route('console.events.destroy', $draft))->assertRedirect(route('console.events.index'));
    expect(Event::query()->whereKey($draft->id)->exists())->toBeFalse();

    $this->actingAs($this->editor)
        ->from(route('console.events.show', $this->event))
        ->delete(route('console.events.destroy', $this->event))
        ->assertSessionHasErrors('event');
});

it('loads a team’s members on demand for the add-team form', function () {
    $this->actingAs($this->editor)
        ->get(route('console.events.show', [$this->event, 'team' => $this->team->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('teamMembers')
            ->reloadOnly('teamMembers', fn (Assert $reload) => $reload
                ->where('teamMembers.0.name', 'Captain Ali')
                ->where('teamMembers.0.id', $this->captain->id)));
});
