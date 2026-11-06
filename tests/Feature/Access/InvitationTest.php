<?php

use App\Domain\Access\Models\Invitation;
use App\Domain\Access\Notifications\InvitationNotification;
use App\Domain\Access\Services\InvitationService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamMemberService;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->service = app(InvitationService::class);
    $this->team = Team::factory()->create(['name' => 'Robo Lions']);
});

function inviteAndCaptureToken(Team $team, string $email, string $role = 'member'): string
{
    $token = null;
    app(InvitationService::class)->invite($team->workspace, $email, $role);

    Notification::assertSentTo(new AnonymousNotifiable, InvitationNotification::class, function (InvitationNotification $n) use (&$token, $email) {
        if ($n->invitation->email === strtolower($email)) {
            $token = $n->plainToken;
        }

        return true;
    });

    return $token;
}

it('stores only a hash of the token and mails the plain link', function () {
    $token = inviteAndCaptureToken($this->team, 'Sara@Example.com');
    $invitation = Invitation::query()->sole();

    expect($invitation->email)->toBe('sara@example.com')
        ->and($invitation->token_hash)->toBe(hash('sha256', $token))
        ->and($invitation->token_hash)->not->toBe($token)
        ->and($invitation->expires_at->toDateString())->toBe(now()->addDays(7)->toDateString());
});

it('lets a new person accept, creates their account and joins them to the team', function () {
    $token = inviteAndCaptureToken($this->team, 'new@example.com', TeamRole::Coach->value);

    $this->get(route('invitations.show', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Invitation')
            ->where('invitation.workspaceName', 'Robo Lions')
            ->where('existingAccount', false));

    $this->post(route('invitations.accept', $token), [
        'name_en' => 'New Coach',
        'password' => 'robotics2026',
        'password_confirmation' => 'robotics2026',
    ])->assertRedirect(route('workspace.dashboard', $this->team->slug));

    $user = User::query()->where('email', 'new@example.com')->sole();

    $this->assertAuthenticatedAs($user);
    expect($this->team->hasMember($user))->toBeTrue()
        ->and(app(TeamMemberService::class)->roleOf($this->team, $user))->toBe(TeamRole::Coach)
        ->and($user->email_verified_at)->not->toBeNull();
});

it('adds the membership for an existing account after confirming its password', function () {
    $existing = User::factory()->create(['email' => 'known@example.com']);
    $token = inviteAndCaptureToken($this->team, 'known@example.com');

    $this->post(route('invitations.accept', $token), ['current_password' => 'wrong'])
        ->assertSessionHasErrors('current_password');

    $this->post(route('invitations.accept', $token), ['current_password' => 'password'])
        ->assertRedirect();

    expect(User::query()->where('email', 'known@example.com')->count())->toBe(1)
        ->and($this->team->hasMember($existing))->toBeTrue();
});

it('can only be used once', function () {
    $token = inviteAndCaptureToken($this->team, 'once@example.com');
    $payload = ['name_en' => 'Once', 'password' => 'robotics2026', 'password_confirmation' => 'robotics2026'];

    $this->post(route('invitations.accept', $token), $payload)->assertRedirect();
    auth()->logout();

    $this->get(route('invitations.show', $token))->assertNotFound();
    $this->post(route('invitations.accept', $token), $payload)->assertNotFound();
});

it('expires after the configured number of days', function () {
    $token = inviteAndCaptureToken($this->team, 'late@example.com');

    $this->travel(8)->days();

    $this->get(route('invitations.show', $token))->assertNotFound();
});

it('replaces an older pending invitation and can be revoked', function () {
    $first = inviteAndCaptureToken($this->team, 'twice@example.com');
    $second = inviteAndCaptureToken($this->team, 'twice@example.com');

    $this->get(route('invitations.show', $first))->assertNotFound();
    $this->get(route('invitations.show', $second))->assertOk();

    $this->service->revoke(Invitation::query()->pending()->sole());
    $this->get(route('invitations.show', $second))->assertNotFound();
});

it('respects the team member limit when accepting', function () {
    app(TeamFeatures::class)->override($this->team, TeamFeature::MembersMax, 1);
    app(TeamMemberService::class)->join($this->team, User::factory()->create(), TeamRole::Captain);
    $token = inviteAndCaptureToken($this->team, 'full@example.com');

    $this->post(route('invitations.accept', $token), [
        'name_en' => 'Too Many', 'password' => 'robotics2026', 'password_confirmation' => 'robotics2026',
    ])->assertSessionHasErrors('members');

    $this->assertGuest();
    expect(User::query()->where('email', 'full@example.com')->exists())->toBeFalse();
});

it('refuses roles that do not fit the workspace and people who are already members', function () {
    $member = User::factory()->create(['email' => 'in@example.com']);
    app(TeamMemberService::class)->join($this->team, $member, TeamRole::Member);

    expect(fn () => $this->service->invite($this->team->workspace, 'x@example.com', 'department-admin'))
        ->toThrow(DomainRuleViolation::class)
        ->and(fn () => $this->service->invite($this->team->workspace, 'in@example.com', 'member'))
        ->toThrow(DomainRuleViolation::class);
});

it('joins department invitees, making admins when the role says so', function () {
    $department = app(DepartmentService::class)->create(['name_en' => 'Events']);
    $token = null;
    $this->service->invite($department->workspace, 'lead@example.com', 'department-admin');
    Notification::assertSentTo(new AnonymousNotifiable, InvitationNotification::class, function ($n) use (&$token) {
        $token = $n->plainToken;

        return true;
    });

    $this->post(route('invitations.accept', $token), [
        'name_en' => 'Lead', 'password' => 'robotics2026', 'password_confirmation' => 'robotics2026',
    ])->assertRedirect();

    expect($department->isAdmin(User::query()->where('email', 'lead@example.com')->sole()))->toBeTrue();
});
