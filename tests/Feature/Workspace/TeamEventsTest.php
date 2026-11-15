<?php

use App\Domain\Events\Enums\RegistrationSource;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamMemberService;
use App\Domain\Work\Enums\RobotStatus;
use App\Domain\Work\Models\Robot;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 04 §9: the team's events. Captains and coaches apply with members
 * and robots, accept invitations and withdraw; members and viewers look.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);
    Carbon::setTestNow('2027-02-01 10:00:00');

    $department = app(DepartmentService::class)->create(['name_en' => 'Events']);
    $this->event = Event::factory()->for($department)->open()->create([
        'slug' => 'robot-cup',
        'starts_at' => '2027-03-10 08:00:00',
        'ends_at' => '2027-03-10 18:00:00',
        'registration_opens_at' => '2027-01-15 00:00:00',
        'registration_closes_at' => '2027-03-01 00:00:00',
    ]);
    $this->category = EventCategory::factory()->for($this->event)->create([
        'members_min' => 1,
        'members_max' => 3,
        'robots_per_team' => 1,
        'age_min' => 10,
        'age_max' => 18,
    ]);

    $this->team = Team::factory()->create(['slug' => 'falcons', 'tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
    $members = app(TeamMemberService::class);
    foreach (['captain' => TeamRole::Captain, 'member' => TeamRole::Member, 'viewer' => TeamRole::Viewer] as $key => $role) {
        $user = User::factory()->create(['birthdate' => '2012-05-05']);
        $members->join($this->team, $user, $role);
        $this->{$key} = $user->fresh();
    }

    $this->robot = Robot::query()->create(['team_id' => $this->team->id, 'name' => 'Falcon One', 'slug' => 'falcon-one', 'status' => RobotStatus::Ready]);
});

function entryPayload(array $overrides = []): array
{
    return array_replace([
        'category_id' => test()->category->id,
        'member_ids' => [test()->captain->id, test()->member->id],
        'robot_ids' => [test()->robot->id],
    ], $overrides);
}

it('lists the events the team can see with its entries', function () {
    Event::factory()->create(['slug' => 'draft-cup']);
    Event::factory()->open()->inviteOnly()->create(['slug' => 'secret-cup']);

    $this->actingAs($this->viewer)
        ->get(route('workspace.events.index', $this->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/events/Index')
            ->has('events', 1)
            ->where('events.0.slug', 'robot-cup')
            ->where('events.0.isOpen', true)
            ->where('events.0.entries', []));
});

it('shows an event with the team members, robots and apply rights', function () {
    $this->actingAs($this->captain)
        ->get(route('workspace.events.show', [$this->team, 'robot-cup']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/events/Show')
            ->has('event.categories', 1)
            ->has('members', 3)
            ->where('robots.0.name', 'Falcon One')
            ->where('can.apply', true));

    $this->actingAs($this->member)
        ->get(route('workspace.events.show', [$this->team, 'robot-cup']))
        ->assertInertia(fn (Assert $page) => $page->where('can.apply', false));
});

it('lets the captain apply with members and robots', function () {
    $this->actingAs($this->captain)
        ->post(route('workspace.events.apply', [$this->team, 'robot-cup']), entryPayload())
        ->assertRedirect();

    $registration = EventRegistration::query()->sole();
    expect($registration->status)->toBe(RegistrationStatus::Pending)
        ->and($registration->source)->toBe(RegistrationSource::Applied)
        ->and($registration->members()->pluck('users.id')->sort()->values()->all())->toBe(collect([$this->captain->id, $this->member->id])->sort()->values()->all())
        ->and($registration->robots()->pluck('robots.id')->all())->toBe([$this->robot->id]);

    $this->actingAs($this->viewer)
        ->get(route('workspace.events.index', $this->team))
        ->assertInertia(fn (Assert $page) => $page->where('events.0.entries.0.status', 'pending'));
});

it('refuses applications from members without the right', function () {
    $this->actingAs($this->member)
        ->post(route('workspace.events.apply', [$this->team, 'robot-cup']), entryPayload())
        ->assertForbidden();
});

it('refuses applications when the tier has no event registration', function () {
    app(TeamFeatures::class)->override($this->team, TeamFeature::EventsRegister, false);

    $this->actingAs($this->captain)
        ->post(route('workspace.events.apply', [$this->team, 'robot-cup']), entryPayload())
        ->assertForbidden();
});

it('reports broken entry rules as form errors', function (Closure $overrides, string $field) {
    $this->actingAs($this->captain)
        ->post(route('workspace.events.apply', [$this->team, 'robot-cup']), entryPayload($overrides()))
        ->assertSessionHasErrors($field);

    expect(EventRegistration::query()->count())->toBe(0);
})->with([
    'no robot' => [fn () => ['robot_ids' => []], 'registration'],
    'no members' => [fn () => ['member_ids' => []], 'member_ids'],
    'robot of another team' => [fn () => ['robot_ids' => [Robot::query()->create(['team_id' => Team::factory()->create()->id, 'name' => 'X', 'slug' => 'x', 'status' => RobotStatus::Ready])->id]], 'registration'],
    'category of another event' => [fn () => ['category_id' => EventCategory::factory()->for(Event::factory()->open())->create()->id], 'category_id'],
]);

it('hides events the team cannot see', function () {
    Event::factory()->open()->inviteOnly()->create(['slug' => 'secret-cup']);

    $this->actingAs($this->captain)->get(route('workspace.events.show', [$this->team, 'secret-cup']))->assertNotFound();
});

it('lets the captain withdraw an entry before registration closes', function () {
    $this->actingAs($this->captain)->post(route('workspace.events.apply', [$this->team, 'robot-cup']), entryPayload());
    $registration = EventRegistration::query()->sole();

    $this->actingAs($this->member)->post(route('workspace.entries.withdraw', [$this->team, $registration]))->assertForbidden();
    $this->actingAs($this->captain)->post(route('workspace.entries.withdraw', [$this->team, $registration]))->assertRedirect();

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Withdrawn);
});

it('lets the captain accept an invitation with members and robots', function () {
    $registration = EventRegistration::query()->create([
        'event_id' => $this->event->id,
        'category_id' => $this->category->id,
        'team_id' => $this->team->id,
        'source' => RegistrationSource::Organiser,
        'status' => RegistrationStatus::Invited,
    ]);

    $this->actingAs($this->captain)
        ->post(route('workspace.entries.accept', [$this->team, $registration]), [
            'member_ids' => [$this->captain->id],
            'robot_ids' => [$this->robot->id],
        ])
        ->assertRedirect();

    $registration->refresh();
    expect($registration->status)->toBe(RegistrationStatus::Approved)
        ->and($registration->robots()->pluck('robots.id')->all())->toBe([$this->robot->id]);
});

it('keeps other teams’ entries out of reach', function () {
    $other = Team::factory()->create();
    $registration = EventRegistration::query()->create([
        'event_id' => $this->event->id,
        'category_id' => $this->category->id,
        'team_id' => $other->id,
        'source' => RegistrationSource::Applied,
        'status' => RegistrationStatus::Pending,
    ]);

    $this->actingAs($this->captain)->post(route('workspace.entries.withdraw', [$this->team, $registration]))->assertNotFound();
});
