<?php

use App\Domain\Events\Enums\RegistrationSource;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamMemberService;
use App\Domain\Work\Enums\ProjectStatus;
use App\Domain\Work\Enums\RobotStatus;
use App\Domain\Work\Models\Project;
use App\Domain\Work\Models\Robot;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 04 §3 and §10: the team's public profile and logo (captain and
 * coach), and the dashboard tiles every member sees.
 */

beforeEach(function () {
    Storage::fake('public');
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);

    $this->team = Team::factory()->create(['slug' => 'falcons', 'name' => 'Falcons', 'tier_id' => Tier::query()->where('slug', 'competition')->value('id')]);
    $members = app(TeamMemberService::class);
    foreach (['captain' => TeamRole::Captain, 'coach' => TeamRole::Coach, 'member' => TeamRole::Member] as $key => $role) {
        $user = User::factory()->create();
        $members->join($this->team, $user, $role);
        $this->{$key} = $user->fresh();
    }
});

function profile(array $overrides = []): array
{
    return array_replace([
        'location' => 'Tripoli',
        'focus_en' => 'Line followers',
        'focus_ar' => 'روبوتات تتبع الخط',
        'description_en' => 'A school team from Tripoli.',
        'description_ar' => 'فريق مدرسي من طرابلس.',
        'official_url' => 'https://falcons.example.com',
        'is_public' => true,
    ], $overrides);
}

it('shows the settings to captains and coaches only', function () {
    $this->actingAs($this->coach)
        ->get(route('workspace.settings.edit', $this->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/settings/Edit')
            ->where('team.name', 'Falcons')
            ->where('can.publish', true));

    $this->actingAs($this->member)->get(route('workspace.settings.edit', $this->team))->assertForbidden();
});

it('saves the public profile', function () {
    $this->actingAs($this->captain)
        ->patch(route('workspace.settings.update', $this->team), profile())
        ->assertRedirect();

    $team = $this->team->fresh();
    expect($team->location)->toBe('Tripoli')
        ->and($team->focus_ar)->toBe('روبوتات تتبع الخط')
        ->and($team->official_url)->toBe('https://falcons.example.com')
        ->and($team->is_public)->toBeTrue();
});

it('keeps the profile private when the tier has no public profile', function () {
    app(TeamFeatures::class)->override($this->team, TeamFeature::PublicProfile, false);

    $this->actingAs($this->captain)
        ->patch(route('workspace.settings.update', $this->team), profile())
        ->assertSessionHasErrors('is_public');

    $this->actingAs($this->captain)
        ->patch(route('workspace.settings.update', $this->team), profile(['is_public' => false]))
        ->assertRedirect();
    expect($this->team->fresh()->location)->toBe('Tripoli');
});

it('refuses unsafe links and members without the right', function () {
    $this->actingAs($this->captain)
        ->patch(route('workspace.settings.update', $this->team), profile(['official_url' => 'javascript:alert(1)']))
        ->assertSessionHasErrors('official_url');

    $this->actingAs($this->member)
        ->patch(route('workspace.settings.update', $this->team), profile())
        ->assertForbidden();
});

it('uploads, replaces and removes the logo', function () {
    $this->actingAs($this->captain)
        ->post(route('workspace.settings.logo', $this->team), ['logo' => UploadedFile::fake()->image('logo.png', 400, 400)])
        ->assertRedirect();
    $this->actingAs($this->captain)
        ->post(route('workspace.settings.logo', $this->team), ['logo' => UploadedFile::fake()->image('new.jpg', 300, 300)])
        ->assertRedirect();

    $logo = $this->team->fresh()->getMedia('logo');
    expect($logo)->toHaveCount(1)
        ->and($logo->first()->file_name)->toBe('new.jpg')
        ->and($logo->first()->hasGeneratedConversion('thumb'))->toBeTrue();

    $this->actingAs($this->member)
        ->post(route('workspace.settings.logo', $this->team), ['logo' => UploadedFile::fake()->image('x.png', 300, 300)])
        ->assertForbidden();

    $this->actingAs($this->captain)->delete(route('workspace.settings.logo.destroy', $this->team))->assertRedirect();
    expect($this->team->fresh()->getMedia('logo'))->toBeEmpty();
});

it('refuses a logo that is not an image', function () {
    $this->actingAs($this->captain)
        ->post(route('workspace.settings.logo', $this->team), ['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('logo');
});

it('shows projects, robots and upcoming entries on the dashboard', function () {
    Project::query()->create(['team_id' => $this->team->id, 'slug' => 'p1', 'title_en' => 'One', 'status' => ProjectStatus::Published, 'published_at' => now()]);
    Project::query()->create(['team_id' => $this->team->id, 'slug' => 'p2', 'title_en' => 'Two', 'status' => ProjectStatus::Draft]);
    Robot::query()->create(['team_id' => $this->team->id, 'name' => 'Falcon One', 'slug' => 'falcon-one', 'status' => RobotStatus::Ready]);
    Robot::query()->create(['team_id' => $this->team->id, 'name' => 'Old', 'slug' => 'old', 'status' => RobotStatus::Retired]);

    $event = Event::factory()->open()->create(['slug' => 'robot-cup', 'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(10)->addHours(5)]);
    $category = EventCategory::factory()->for($event)->create();
    EventRegistration::query()->create([
        'event_id' => $event->id, 'category_id' => $category->id, 'team_id' => $this->team->id,
        'source' => RegistrationSource::Applied, 'status' => RegistrationStatus::Pending,
    ]);
    $past = Event::factory()->open()->create(['starts_at' => now()->subDays(10), 'ends_at' => now()->subDays(10)->addHour()]);
    EventRegistration::query()->create([
        'event_id' => $past->id, 'category_id' => EventCategory::factory()->for($past)->create()->id, 'team_id' => $this->team->id,
        'source' => RegistrationSource::Applied, 'status' => RegistrationStatus::Approved,
    ]);

    $this->actingAs($this->member)
        ->get(route('workspace.dashboard', $this->team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.projects', ['published' => 1, 'drafts' => 1])
            ->where('stats.robots', 1)
            ->has('stats.partsCost')
            ->has('upcoming', 1)
            ->where('upcoming.0.slug', 'robot-cup')
            ->where('upcoming.0.status', 'pending')
            ->where('team.logo', null));
});
