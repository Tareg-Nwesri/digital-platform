<?php

use App\Domain\Access\Models\Invitation;
use App\Domain\Access\Notifications\InvitationNotification;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamMemberService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 03 §5–8: the team's own members page. Everyone in the team sees it;
 * captains and coaches invite, add members without a login, change roles
 * and remove people; only the captain hands over the captaincy; anyone but
 * the captain may leave.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);
    Notification::fake();

    $this->team = Team::factory()->create(['name' => 'Falcons', 'slug' => 'falcons', 'tier_id' => Tier::query()->where('slug', 'starter')->value('id')]);
    $members = app(TeamMemberService::class);

    $this->captain = User::factory()->create(['name_en' => 'Captain Ali']);
    $this->coach = User::factory()->create(['name_en' => 'Coach Sara']);
    $this->member = User::factory()->create(['name_en' => 'Member Omar']);
    $this->viewer = User::factory()->create(['name_en' => 'Viewer Huda']);
    $members->join($this->team, $this->captain, TeamRole::Captain);
    $members->join($this->team, $this->coach, TeamRole::Coach);
    $members->join($this->team, $this->member, TeamRole::Member);
    $members->join($this->team, $this->viewer, TeamRole::Viewer);

    // Requests load users fresh; drop roles cached while setting up in another scope.
    foreach (['captain', 'coach', 'member', 'viewer'] as $person) {
        $this->{$person} = $this->{$person}->fresh();
    }
});

it('shows the members to everyone in the team, with what each person may do', function () {
    $this->actingAs($this->viewer)
        ->get(route('workspace.members.index', $this->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/members/Index')
            ->has('members', 4)
            ->where('members.0.name', 'Captain Ali')
            ->where('members.0.role', 'captain')
            ->where('limit', 8)
            ->where('can.invite', false)
            ->where('can.manage', false));

    $this->actingAs($this->coach)
        ->get(route('workspace.members.index', $this->team))
        ->assertInertia(fn (Assert $page) => $page->where('can.invite', true)->where('can.manage', true)->where('can.transfer', false));

    $this->actingAs(User::factory()->create())->get(route('workspace.members.index', $this->team))->assertNotFound();
});

it('lets captains and coaches invite by email, but not as captain', function () {
    $this->actingAs($this->member)
        ->post(route('workspace.invitations.store', $this->team), ['email' => 'new@example.test', 'role' => 'member'])
        ->assertForbidden();

    $this->actingAs($this->coach)
        ->post(route('workspace.invitations.store', $this->team), ['email' => 'new@example.test', 'role' => 'captain'])
        ->assertSessionHasErrors('role');

    $this->actingAs($this->coach)
        ->post(route('workspace.invitations.store', $this->team), ['email' => 'New@Example.test', 'role' => 'member'])
        ->assertRedirect();

    $invitation = Invitation::query()->sole();
    expect($invitation->email)->toBe('new@example.test');
    Notification::assertSentOnDemand(InvitationNotification::class);

    $this->actingAs($this->captain)
        ->get(route('workspace.members.index', $this->team))
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 1)->where('invitations.0.email', 'new@example.test'));

    $this->actingAs($this->captain)->delete(route('workspace.invitations.destroy', [$this->team, $invitation]))->assertRedirect();
    expect($invitation->fresh()->revoked_at)->not->toBeNull();
});

it('adds members without a login, only as members or viewers', function () {
    $this->actingAs($this->coach)
        ->post(route('workspace.members.offline', $this->team), ['name_en' => 'Little Ahmed', 'birthdate' => '2015-04-01', 'role' => 'coach'])
        ->assertSessionHasErrors('role');

    $this->actingAs($this->coach)
        ->post(route('workspace.members.offline', $this->team), ['name_en' => 'Little Ahmed', 'name_ar' => 'أحمد', 'birthdate' => '2015-04-01', 'role' => 'member'])
        ->assertRedirect();

    $added = User::query()->where('name_en', 'Little Ahmed')->sole();
    expect($added->can_login)->toBeFalse()
        ->and($this->team->fresh()->hasMember($added))->toBeTrue();
});

it('respects the member limit', function () {
    $members = app(TeamMemberService::class);
    foreach (range(1, 4) as $i) {
        $members->join($this->team, User::factory()->create(), TeamRole::Member);
    }

    $this->actingAs($this->coach)
        ->post(route('workspace.members.offline', $this->team), ['name_en' => 'One too many', 'birthdate' => '2014-01-01', 'role' => 'member'])
        ->assertSessionHasErrors('members');
});

it('changes roles but never to or from captain', function () {
    $this->actingAs($this->coach)->patch(route('workspace.members.role', [$this->team, $this->member]), ['role' => 'coach'])->assertRedirect();
    expect(app(TeamMemberService::class)->roleOf($this->team, $this->member))->toBe(TeamRole::Coach);

    $this->actingAs($this->coach)->patch(route('workspace.members.role', [$this->team, $this->captain]), ['role' => 'member'])->assertSessionHasErrors('role');
    $this->actingAs($this->viewer)->patch(route('workspace.members.role', [$this->team, $this->member]), ['role' => 'viewer'])->assertForbidden();
});

it('lets only the captain hand over the captaincy', function () {
    $this->actingAs($this->coach)->post(route('workspace.members.captain', [$this->team, $this->coach]))->assertForbidden();

    $this->actingAs($this->captain)->post(route('workspace.members.captain', [$this->team, $this->coach]))->assertRedirect();

    $roles = app(TeamMemberService::class);
    expect($roles->roleOf($this->team, $this->coach))->toBe(TeamRole::Captain)
        ->and($roles->roleOf($this->team, $this->captain))->toBe(TeamRole::Coach);
});

it('removes members but never the captain', function () {
    $this->actingAs($this->coach)->delete(route('workspace.members.destroy', [$this->team, $this->viewer]))->assertRedirect();
    expect($this->team->fresh()->hasMember($this->viewer))->toBeFalse();

    $this->actingAs($this->coach)->delete(route('workspace.members.destroy', [$this->team, $this->captain]))->assertSessionHasErrors('user');
});

it('lets anyone but the captain leave', function () {
    $this->actingAs($this->member)->post(route('workspace.leave', $this->team))->assertRedirect(route('hub'));
    expect($this->team->fresh()->hasMember($this->member))->toBeFalse();

    $this->actingAs($this->captain)->post(route('workspace.leave', $this->team))->assertSessionHasErrors('user');
});

it('shows the members link in the team navigation', function () {
    $this->actingAs($this->viewer)
        ->get(route('workspace.dashboard', $this->team))
        ->assertInertia(fn (Assert $page) => $page->where('nav', fn ($items) => collect($items)->contains(fn ($item) => $item['key'] === 'members')));
});
