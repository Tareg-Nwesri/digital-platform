<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Events\Enums\RegistrationSource;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Services\RegistrationService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamMemberService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Honeypot\Honeypot;

/*
 * Guest registration: a visitor registers a new team for an open public
 * event; the entry waits for the organisers, and approving it activates
 * the team.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);
    Carbon::setTestNow('2027-02-01 10:00:00');

    $this->department = app(DepartmentService::class)->create(['name_en' => 'Events']);
    $this->event = Event::factory()->for($this->department)->open()->create([
        'slug' => 'robot-cup',
        'starts_at' => '2027-03-10 08:00:00',
        'ends_at' => '2027-03-10 18:00:00',
        'registration_opens_at' => '2027-01-15 00:00:00',
        'registration_closes_at' => '2027-03-01 00:00:00',
    ]);
    $this->category = EventCategory::factory()->for($this->event)->create([
        'name_en' => 'Junior',
        'members_min' => 2,
        'members_max' => 3,
        'age_min' => 10,
        'age_max' => 15,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function guestEntry(array $overrides = []): array
{
    return array_replace([
        'category_id' => test()->category->getKey(),
        'team_name' => 'Desert Foxes',
        'city' => 'Tripoli',
        'contact_name' => 'Salma Ali',
        'contact_email' => 'salma@example.com',
        'contact_phone' => '+218 91 123 4567',
        'robot_name' => 'Fox One',
        'members' => [
            ['name' => 'Omar Salem', 'birthdate' => '2014-05-01'],
            ['name' => 'Huda Salem', 'birthdate' => '2013-09-20'],
        ],
    ], $overrides);
}

it('shows the registration form for an open public event in the URL locale', function () {
    $this->get('/ar/events/robot-cup/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/events/Register')
            ->where('locale', 'ar')
            ->where('event.slug', 'robot-cup')
            ->where('event.isOpen', true)
            ->has('event.categories', 1)
            ->where('event.categories.0.membersMax', 3));

    $this->get('/en/events/robot-cup')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/events/Show')->where('locale', 'en'));
});

it('registers a new pending team with its contact, members and robot', function () {
    $this->post('/en/events/robot-cup/register', guestEntry())
        ->assertRedirect('/en/events/robot-cup/registered');

    $team = Team::query()->where('name', 'Desert Foxes')->sole();
    expect($team->status)->toBe(TeamStatus::Pending)
        ->and($team->location)->toBe('Tripoli')
        ->and($team->department_id)->toBe($this->department->getKey());

    $members = app(TeamMemberService::class);
    $captain = $members->captain($team);
    expect($captain->email)->toBe('salma@example.com')
        ->and($captain->phone)->toBe('+218 91 123 4567')
        ->and($captain->can_login)->toBeTrue()
        ->and($captain->password)->toBeNull();

    $registration = EventRegistration::query()->sole();
    expect($registration->source)->toBe(RegistrationSource::Public)
        ->and($registration->status)->toBe(RegistrationStatus::Pending)
        ->and($registration->members->pluck('name_en')->sort()->values()->all())->toBe(['Huda Salem', 'Omar Salem'])
        ->and($registration->robots->pluck('name')->all())->toBe(['Fox One']);

    $this->get('/en/events/robot-cup/registered')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/events/Registered')->where('teamName', 'Desert Foxes'));
});

it('reuses an existing account when the contact email is already known', function () {
    $existing = User::factory()->create(['email' => 'salma@example.com']);

    $this->post('/en/events/robot-cup/register', guestEntry())->assertRedirect();

    expect(User::query()->where('email', 'salma@example.com')->count())->toBe(1)
        ->and(app(TeamMemberService::class)->captain(Team::query()->sole())->is($existing))->toBeTrue();
});

it('refuses entries that break the category rules', function (array $overrides, string $field) {
    $this->post('/en/events/robot-cup/register', guestEntry($overrides))->assertSessionHasErrors($field);

    expect(Team::query()->count())->toBe(0)->and(EventRegistration::query()->count())->toBe(0);
})->with([
    'too few members' => [['members' => [['name' => 'Omar Salem', 'birthdate' => '2014-05-01']]], 'members'],
    'too many members' => [['members' => array_fill(0, 4, ['name' => 'Kid', 'birthdate' => '2014-05-01'])], 'members'],
    'too old' => [['members' => [['name' => 'Omar Salem', 'birthdate' => '2014-05-01'], ['name' => 'Adult', 'birthdate' => '2000-01-01']]], 'registration'],
    'missing birthdate' => [['members' => [['name' => 'Omar Salem', 'birthdate' => '2014-05-01'], ['name' => 'Huda', 'birthdate' => '']]], 'members.1.birthdate'],
    'missing contact phone' => [['contact_phone' => ''], 'contact_phone'],
    'bad email' => [['contact_email' => 'not-an-email'], 'contact_email'],
    'missing team name' => [['team_name' => ''], 'team_name'],
]);

it('refuses a category from another event', function () {
    $other = EventCategory::factory()->for(Event::factory()->for($this->department)->open())->create();

    $this->post('/en/events/robot-cup/register', guestEntry(['category_id' => $other->getKey()]))
        ->assertSessionHasErrors('category_id');
});

it('refuses a second entry under the same team name for the event', function () {
    $this->post('/en/events/robot-cup/register', guestEntry())->assertRedirect();
    $this->post('/en/events/robot-cup/register', guestEntry(['team_name' => ' desert foxes ', 'contact_email' => 'other@example.com']))
        ->assertSessionHasErrors('team_name');

    expect(EventRegistration::query()->count())->toBe(1);
});

it('shows the closed state and refuses entries outside the registration window', function () {
    Carbon::setTestNow('2027-03-02 10:00:00');

    $this->get('/en/events/robot-cup/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('event.isOpen', false));

    $this->post('/en/events/robot-cup/register', guestEntry())->assertSessionHasErrors('registration');
    expect(Team::query()->count())->toBe(0);
});

it('hides draft and invite-only events', function () {
    Event::factory()->for($this->department)->create(['slug' => 'draft-cup']);
    Event::factory()->for($this->department)->open()->inviteOnly()->create(['slug' => 'secret-cup']);

    $this->get('/en/events/draft-cup/register')->assertNotFound();
    $this->get('/en/events/secret-cup/register')->assertNotFound();
    $this->post('/en/events/secret-cup/register', guestEntry())->assertNotFound();
});

it('lists open public events', function () {
    Event::factory()->for($this->department)->create(['slug' => 'draft-cup']);

    $this->get('/ar/events')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/events/Index')
            ->has('events', 1)
            ->where('events.0.slug', 'robot-cup'));
});

it('activates the guest team when the organisers approve the entry', function () {
    $this->post('/en/events/robot-cup/register', guestEntry())->assertRedirect();

    $organiser = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $organiser->assignRole(User::SUPER_ADMIN));

    $registration = app(RegistrationService::class)->approve(EventRegistration::query()->sole(), null, $organiser->fresh());

    expect($registration->status)->toBe(RegistrationStatus::Approved)
        ->and($registration->team->fresh()->status)->toBe(TeamStatus::Active)
        ->and($registration->members_snapshot)->toHaveCount(2)
        ->and(app(TeamMemberService::class)->roleOf($registration->team, $registration->members->first()))->toBe(TeamRole::Member);
});

it('protects the form with the honeypot', function () {
    config(['honeypot.enabled' => true]);

    $this->post('/en/events/robot-cup/register', guestEntry())->assertSessionHasErrors('form');

    $honeypot = new Honeypot(config('honeypot'));
    $this->travel(5)->seconds();
    $fields = [$honeypot->nameFieldName() => '', $honeypot->validFromFieldName() => $honeypot->encryptedValidFrom()];
    $this->travel(5)->seconds();

    $this->post('/en/events/robot-cup/register', guestEntry($fields))->assertRedirect('/en/events/robot-cup/registered');
});

it('shows contact details of guest entries to the organisers', function () {
    $this->post('/en/events/robot-cup/register', guestEntry())->assertRedirect();

    $organiser = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $organiser->assignRole(User::SUPER_ADMIN));

    $this->actingAs($organiser)->get(route('console.events.show', $this->event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('registrations.0.source', 'public')
            ->where('registrations.0.contact.phone', '+218 91 123 4567')
            ->where('registrations.0.contact.email', 'salma@example.com')
            ->where('registrations.0.city', 'Tripoli')
            ->where('registrations.0.robots', ['Fox One']));
});
