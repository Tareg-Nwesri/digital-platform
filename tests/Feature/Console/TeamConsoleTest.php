<?php

use App\Domain\Access\Models\Invitation;
use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Notifications\InvitationNotification;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamMemberService;
use App\Domain\Teams\Services\TeamService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 03 §6–9: the teams area of the console. Staff see and edit teams
 * supervised by their own departments (teams.view / teams.edit); approvals
 * and status changes need teams.approve (department admins); teams.view.all
 * and the super admin see every team.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);

    $this->superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $this->superAdmin->assignRole(User::SUPER_ADMIN));
    $this->superAdmin = $this->superAdmin->fresh();

    $departments = app(DepartmentService::class);
    $permissions = app(DepartmentPermissionService::class);

    $this->events = $departments->create(['name_en' => 'Events']);
    $permissions->setCeiling($this->events, ['teams.view', 'teams.view.all', 'teams.edit'], $this->superAdmin);
    $this->procurement = $departments->create(['name_en' => 'Procurement']);
    $permissions->setCeiling($this->procurement, ['teams.view'], $this->superAdmin);

    $this->eventsAdmin = User::factory()->create(['name_en' => 'Events Lead']);
    $this->editor = User::factory()->create(['name_en' => 'Team Editor']);
    $this->viewer = User::factory()->create(['name_en' => 'Team Viewer']);
    $this->outsider = User::factory()->create();
    $departments->addMember($this->events, $this->eventsAdmin, isAdmin: true);
    $departments->addMember($this->events, $this->editor);
    $departments->addMember($this->events, $this->viewer);
    $departments->addMember($this->procurement, $this->outsider);
    $permissions->syncMember($this->events, $this->editor, ['teams.edit'], $this->eventsAdmin);
    $permissions->syncMember($this->events, $this->viewer, ['teams.view'], $this->eventsAdmin);
    $permissions->syncMember($this->procurement, $this->outsider, ['teams.view'], $this->superAdmin);

    $teams = app(TeamService::class);
    $this->starter = Tier::query()->where('slug', 'starter')->firstOrFail();
    $this->competition = Tier::query()->where('slug', 'competition')->firstOrFail();

    $this->falcons = $teams->create(['name' => 'Falcons', 'tier_id' => $this->starter->getKey(), 'department_id' => $this->events->getKey()]);
    $this->hawks = $teams->create(['name' => 'Hawks', 'tier_id' => $this->competition->getKey(), 'department_id' => $this->procurement->getKey()]);
    $this->lone = $teams->create(['name' => 'Lone Wolves'], approved: false);

    $this->captain = User::factory()->create(['name_en' => 'Rami Captain']);
    app(TeamMemberService::class)->join($this->falcons, $this->captain, TeamRole::Captain);
});

function teamSlugs($page): array
{
    return collect($page->toArray()['props']['teams']['data'])->pluck('slug')->sort()->values()->all();
}

describe('the team list', function () {
    it('shows the super admin every team with its tier, department, status and member count', function () {
        $this->actingAs($this->superAdmin)
            ->get(route('console.teams.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('console/teams/Index')
                ->has('teams.data', 3)
                ->where('teams.data', fn ($rows) => collect($rows)->firstWhere('slug', 'falcons') == [
                    'id' => $this->falcons->getKey(),
                    'slug' => 'falcons',
                    'name' => 'Falcons',
                    'status' => 'active',
                    'tier' => 'Starter',
                    'department' => 'Events',
                    'members' => 1,
                    'membersLimit' => 8,
                    'createdAt' => $this->falcons->created_at->toIso8601String(),
                ])
                ->has('filters.tiers', 2)
                ->has('filters.departments', 2)
                ->where('can', ['create' => true, 'unsupervised' => true]));
    });

    it('filters by status, tier, department and search', function (array $query, array $expected) {
        $this->actingAs($this->superAdmin)
            ->get(route('console.teams.index', $query))
            ->assertInertia(fn (Assert $page) => expect(teamSlugs($page))->toBe($expected));
    })->with([
        'pending' => [['filter' => ['status' => 'pending']], ['lone-wolves']],
        'competition tier' => [['filter' => ['tier' => 'competition']], ['hawks']],
        'events department' => [['filter' => ['department' => 'events']], ['falcons']],
        'search' => [['q' => 'haw'], ['hawks']],
    ]);

    it('limits staff to the teams their departments supervise', function () {
        $this->actingAs($this->viewer)
            ->get(route('console.teams.index'))
            ->assertInertia(fn (Assert $page) => expect(teamSlugs($page))->toBe(['falcons'])
                ->and($page->toArray()['props']['can']['create'])->toBeFalse());

        $this->actingAs($this->outsider)
            ->get(route('console.teams.index'))
            ->assertInertia(fn (Assert $page) => expect(teamSlugs($page))->toBe(['hawks']));
    });

    it('shows every team to holders of teams.view.all', function () {
        app(DepartmentPermissionService::class)->syncMember($this->events, $this->viewer, ['teams.view.all'], $this->eventsAdmin);

        $this->actingAs($this->viewer)
            ->get(route('console.teams.index'))
            ->assertInertia(fn (Assert $page) => expect(teamSlugs($page))->toBe(['falcons', 'hawks', 'lone-wolves']));
    });

    it('is closed to people without teams.view anywhere', function () {
        $this->actingAs(User::factory()->create())->get(route('console.teams.index'))->assertForbidden();
    });
});

describe('creating a team', function () {
    it('lets the super admin create an active team and invite its captain', function () {
        Notification::fake();

        $this->actingAs($this->superAdmin)
            ->post(route('console.teams.store'), [
                'name' => 'Desert Bots',
                'tier_id' => $this->competition->getKey(),
                'department_id' => $this->events->getKey(),
                'location' => 'Benghazi',
                'captain_email' => 'captain@example.com',
            ])
            ->assertRedirect(route('console.teams.show', 'desert-bots'));

        $team = Team::query()->where('slug', 'desert-bots')->firstOrFail();

        expect($team->status)->toBe(TeamStatus::Active)
            ->and($team->department_id)->toBe($this->events->getKey())
            ->and(Invitation::query()->where('workspace_id', $team->workspace_id)->value('role'))->toBe('captain');

        Notification::assertSentOnDemand(InvitationNotification::class);
    });

    it('creates a pending team when the staff member cannot approve', function () {
        $this->actingAs($this->editor)
            ->post(route('console.teams.store'), [
                'name' => 'Rookies',
                'tier_id' => $this->starter->getKey(),
                'department_id' => $this->events->getKey(),
            ])
            ->assertSessionHasNoErrors();

        expect(Team::query()->where('slug', 'rookies')->value('status'))->toBe(TeamStatus::Pending);
    });

    it('creates an active team when a department admin with teams.approve creates it', function () {
        $this->actingAs($this->eventsAdmin)
            ->post(route('console.teams.store'), [
                'name' => 'Rookies',
                'tier_id' => $this->starter->getKey(),
                'department_id' => $this->events->getKey(),
            ])
            ->assertSessionHasNoErrors();

        expect(Team::query()->where('slug', 'rookies')->value('status'))->toBe(TeamStatus::Active);
    });

    it('only lets staff place a team under a department where they can edit teams', function () {
        $this->actingAs($this->editor)
            ->post(route('console.teams.store'), ['name' => 'Rookies', 'tier_id' => $this->starter->getKey(), 'department_id' => $this->procurement->getKey()])
            ->assertSessionHasErrors('department_id');

        $this->actingAs($this->editor)
            ->post(route('console.teams.store'), ['name' => 'Rookies', 'tier_id' => $this->starter->getKey()])
            ->assertSessionHasErrors('department_id');
    });

    it('refuses people who can only view teams', function () {
        $this->actingAs($this->viewer)
            ->post(route('console.teams.store'), ['name' => 'Rookies', 'tier_id' => $this->starter->getKey(), 'department_id' => $this->events->getKey()])
            ->assertForbidden();
    });

    it('validates the team fields', function (array $input, string $field) {
        $this->actingAs($this->superAdmin)
            ->post(route('console.teams.store'), [...['name' => 'Rookies', 'tier_id' => $this->starter->getKey()], ...$input])
            ->assertSessionHasErrors($field);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'long name' => [['name' => str_repeat('a', 121)], 'name'],
        'taken slug' => [['slug' => 'falcons'], 'slug'],
        'unknown tier' => [['tier_id' => 999], 'tier_id'],
        'bad captain email' => [['captain_email' => 'not-an-email'], 'captain_email'],
    ]);
});

describe('the team page', function () {
    it('shows the team, its members and its feature breakdown to staff who can view it', function () {
        $this->actingAs($this->viewer)
            ->get(route('console.teams.show', 'falcons'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('console/teams/Show')
                ->where('team.name', 'Falcons')
                ->where('team.status', 'active')
                ->where('team.department.slug', 'events')
                ->where('members.0.name', 'Rami Captain')
                ->where('members.0.role', 'captain')
                ->has('features', count(TeamFeature::cases()))
                ->where('features', fn ($features) => collect($features)->firstWhere('name', 'members.max')['effective'] === 8)
                ->where('can', ['update' => false, 'changeStatus' => false, 'assignTier' => false, 'overrideFeatures' => false])
                ->where('transitions', ['suspended', 'archived']));
    });

    it('hides teams outside the viewer’s departments', function () {
        $this->actingAs($this->viewer)->get(route('console.teams.show', 'hawks'))->assertForbidden();
    });

    it('gives editors and admins the matching abilities', function () {
        $this->actingAs($this->editor)
            ->get(route('console.teams.show', 'falcons'))
            ->assertInertia(fn (Assert $page) => $page->where('can', ['update' => true, 'changeStatus' => false, 'assignTier' => true, 'overrideFeatures' => true]));

        $this->actingAs($this->eventsAdmin)
            ->get(route('console.teams.show', 'falcons'))
            ->assertInertia(fn (Assert $page) => $page->where('can.changeStatus', true));
    });
});

describe('editing a team', function () {
    it('lets an editor update the profile', function () {
        $this->actingAs($this->editor)
            ->patch(route('console.teams.update', 'falcons'), ['name' => 'Falcons Robotics', 'location' => 'Tripoli', 'department_id' => $this->events->getKey()])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($this->falcons->fresh())->name->toBe('Falcons Robotics')->location->toBe('Tripoli');

        $this->actingAs($this->viewer)
            ->patch(route('console.teams.update', 'falcons'), ['name' => 'Nope'])
            ->assertForbidden();
    });

    it('lets an editor change the tier, which changes the effective features', function () {
        $this->actingAs($this->editor)
            ->patch(route('console.teams.tier', 'falcons'), ['tier_id' => $this->competition->getKey()])
            ->assertSessionHasNoErrors();

        expect($this->falcons->fresh()->tier_id)->toBe($this->competition->getKey())
            ->and(app(TeamFeatures::class)->value($this->falcons->fresh(), TeamFeature::MembersMax))
            ->toBe($this->competition->value(TeamFeature::MembersMax));
    });

    it('lets an editor override features and clear an override with null', function () {
        $this->actingAs($this->editor)
            ->put(route('console.teams.features', 'falcons'), ['features' => [
                ['name' => 'members.max', 'value' => 20],
                ['name' => 'parts.suggest', 'value' => true],
            ]])
            ->assertSessionHasNoErrors();

        $features = app(TeamFeatures::class);

        expect($features->value($this->falcons->fresh(), TeamFeature::MembersMax))->toBe(20)
            ->and($features->active($this->falcons->fresh(), TeamFeature::PartsSuggest))->toBeTrue();

        $this->actingAs($this->editor)
            ->put(route('console.teams.features', 'falcons'), ['features' => [['name' => 'members.max', 'value' => null]]]);

        expect($features->value($this->falcons->fresh(), TeamFeature::MembersMax))->toBe($this->starter->value(TeamFeature::MembersMax));
    });

    it('validates feature overrides', function (array $feature, string $field) {
        $this->actingAs($this->editor)
            ->put(route('console.teams.features', 'falcons'), ['features' => [$feature]])
            ->assertSessionHasErrors($field);
    })->with([
        'unknown feature' => [['name' => 'flying', 'value' => true], 'features.0.name'],
        'limit too low' => [['name' => 'members.max', 'value' => 0], 'features.0.value'],
        'limit not a number' => [['name' => 'members.max', 'value' => 'many'], 'features.0.value'],
        'flag not boolean' => [['name' => 'parts.suggest', 'value' => 'maybe'], 'features.0.value'],
    ]);
});

describe('team status', function () {
    it('lets a department admin suspend a team with a reason', function () {
        $this->actingAs($this->eventsAdmin)
            ->patch(route('console.teams.status', 'falcons'), ['status' => 'suspended', 'reason' => 'Missed the safety review'])
            ->assertSessionHasNoErrors();

        expect($this->falcons->fresh())->status->toBe(TeamStatus::Suspended)->status_reason->toBe('Missed the safety review');
    });

    it('requires a reason to suspend or archive', function () {
        $this->actingAs($this->eventsAdmin)
            ->patch(route('console.teams.status', 'falcons'), ['status' => 'suspended'])
            ->assertSessionHasErrors('reason');
    });

    it('refuses a transition the status machine does not allow', function () {
        $this->actingAs($this->superAdmin)
            ->patch(route('console.teams.status', 'lone-wolves'), ['status' => 'suspended', 'reason' => 'x'])
            ->assertSessionHasErrors('status');
    });

    it('lets the super admin approve a pending team', function () {
        $this->actingAs($this->superAdmin)
            ->patch(route('console.teams.status', 'lone-wolves'), ['status' => 'active'])
            ->assertSessionHasNoErrors();

        expect($this->lone->fresh())->status->toBe(TeamStatus::Active)->approved_by->toBe($this->superAdmin->getKey());
    });

    it('is not available to editors without teams.approve', function () {
        $this->actingAs($this->editor)
            ->patch(route('console.teams.status', 'falcons'), ['status' => 'suspended', 'reason' => 'x'])
            ->assertForbidden();
    });
});
