<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Catalogue\Services\SuggestionService;
use App\Domain\Content\Enums\MessageStatus;
use App\Domain\Content\Models\ContactMessage;
use App\Domain\Events\Enums\RegistrationSource;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamMemberService;
use App\Domain\Work\Enums\ProjectStatus;
use App\Domain\Work\Enums\RobotStatus;
use App\Domain\Work\Models\Project;
use App\Domain\Work\Models\Robot;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The super admin's dashboard answers the questions of a working week:
 * what waits for someone, how the next events are filling up, how
 * registrations are coming in, where the money stands and how the
 * community is growing.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);
    Carbon::setTestNow('2027-02-10 10:00:00');

    $this->admin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $this->admin->assignRole(User::SUPER_ADMIN));

    $competition = Tier::query()->where('slug', 'competition')->value('id');
    $this->team = Team::factory()->create(['tier_id' => $competition]);
    $captain = User::factory()->create();
    app(TeamMemberService::class)->join($this->team, $captain, TeamRole::Captain);
    $this->captain = $captain;
    $guest = Team::factory()->create(['status' => TeamStatus::Pending, 'tier_id' => $competition]);

    $department = app(DepartmentService::class)->create(['name_en' => 'Events']);
    $this->event = Event::factory()->for($department)->open()->create([
        'slug' => 'robot-cup',
        'capacity' => 20,
        'starts_at' => '2027-03-01 09:00:00',
        'ends_at' => '2027-03-01 17:00:00',
        'registration_closes_at' => '2027-02-20 00:00:00',
    ]);
    $category = EventCategory::factory()->for($this->event)->create();
    EventRegistration::query()->create(['event_id' => $this->event->id, 'category_id' => $category->id, 'team_id' => $this->team->id, 'source' => RegistrationSource::Applied, 'status' => RegistrationStatus::Approved]);
    $fromWebsite = EventRegistration::query()->create(['event_id' => $this->event->id, 'category_id' => $category->id, 'team_id' => $guest->id, 'source' => RegistrationSource::Public, 'status' => RegistrationStatus::Pending]);
    $fromWebsite->forceFill(['created_at' => now()->subDays(2)])->save();
    Event::factory()->for($department)->create(['slug' => 'past-cup', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subMonth()->addHours(5)]);

    ContactMessage::query()->create(['name' => 'Visitor', 'email' => 'v@example.com', 'subject' => 'Hi', 'message' => 'Hello', 'locale' => 'en', 'status' => MessageStatus::New]);
    app(SuggestionService::class)->suggestPart($this->team, ['name' => 'New sensor'], $captain);

    Project::query()->create(['team_id' => $this->team->id, 'slug' => 'p', 'title_en' => 'P', 'status' => ProjectStatus::Published, 'published_at' => now()->subDay()]);
    Robot::query()->create(['team_id' => $this->team->id, 'name' => 'R1', 'slug' => 'r1', 'status' => RobotStatus::Ready]);
});

it('lists what is waiting for someone, with links to each queue', function () {
    $this->actingAs($this->admin)
        ->get(route('console.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/Dashboard')
            ->where('attention', fn ($items) => collect($items)->pluck('count', 'key')->only(['teams', 'registrations', 'suggestions', 'messages'])->all() == [
                'teams' => 1,
                'registrations' => 1,
                'suggestions' => 1,
                'messages' => 1,
            ])
            ->where('attention', fn ($items) => collect($items)->every(fn ($item) => filled($item['href']) && filled($item['label']))));
});

it('shows how the next events are filling up', function () {
    $this->actingAs($this->admin)
        ->get(route('console.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.slug', 'robot-cup')
            ->where('events.0.approved', 1)
            ->where('events.0.pending', 1)
            ->where('events.0.fromWebsite', 1)
            ->where('events.0.capacity', 20)
            ->where('events.0.daysToStart', 19)
            ->where('events.0.daysToClose', 10));
});

it('charts registrations over the last two weeks', function () {
    $this->actingAs($this->admin)
        ->get(route('console.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('registrations.days', 14)
            ->where('registrations.days.13.date', '2027-02-10')
            ->where('registrations.days.11.count', 1)
            ->where('registrations.total', 2)
            ->where('registrations.fromWebsite', 1));
});

it('sums up the community and the recent activity', function () {
    $this->actingAs($this->admin)
        ->get(route('console.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('community.activeTeams', 1)
            ->where('community.pendingTeams', 1)
            ->where('community.members', 1)
            ->where('community.robots', 1)
            ->where('community.publishedProjects', 1)
            ->where('community.newTeamsThisMonth', 2)
            ->has('finance')
            ->has('activity'));
});

it('stays the super admin’s page', function () {
    $this->actingAs($this->captain)->get(route('console.dashboard'))->assertForbidden();
});
