<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Access\Models\Invitation;
use App\Domain\Access\Services\InvitationService;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamMemberService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\AddOfflineMemberRequest;
use App\Http\Requests\Workspace\ChangeTeamRoleRequest;
use App\Http\Requests\Workspace\InviteTeamMemberRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 03 §7–8: the team's members page. The workspace middleware has set
 * the team's permission scope, so $user->can() reads team roles.
 */
class MemberController extends Controller
{
    public function __construct(
        private readonly TeamMemberService $members,
        private readonly InvitationService $invitations,
        private readonly TeamFeatures $features,
    ) {}

    public function index(Request $request, Team $team): Response
    {
        $user = $request->user();
        $order = array_flip(TeamRole::values());

        $members = $team->members()->with('roles')->get()
            ->map(fn (User $member): array => [
                'id' => $member->getKey(),
                'name' => $member->name,
                'role' => $member->roles->first()?->name,
                'canLogin' => (bool) $member->can_login,
                'age' => $member->ageOn(now()),
                'joinedAt' => $member->pivot->joined_at ? (string) $member->pivot->joined_at : null,
                'isMe' => $member->is($user),
            ])
            ->sortBy(fn (array $member): string => sprintf('%02d %s', $order[$member['role']] ?? 99, $member['name']))
            ->values()
            ->all();

        return Inertia::render('workspace/members/Index', [
            'team' => ['slug' => $team->slug, 'name' => $team->name],
            'members' => $members,
            'invitations' => $user->can('members.invite')
                ? Invitation::query()->pending()->where('workspace_id', $team->workspace_id)->latest()->get()
                    ->map(fn (Invitation $invitation): array => [
                        'id' => $invitation->getKey(),
                        'email' => $invitation->email,
                        'role' => $invitation->role,
                        'expiresAt' => $invitation->expires_at?->toIso8601String(),
                    ])
                    ->all()
                : [],
            'limit' => (int) $this->features->value($team, TeamFeature::MembersMax),
            'can' => [
                'invite' => $user->can('members.invite'),
                'manage' => $user->can('members.manage'),
                'transfer' => $user->can('team.transfer'),
            ],
        ]);
    }

    public function invite(InviteTeamMemberRequest $request, Team $team): RedirectResponse
    {
        $invitation = $this->invitations->invite($team->workspace, $request->validated('email'), $request->validated('role'), $request->user());

        return $this->done(__('teams.flash.invited', ['email' => $invitation->email]));
    }

    public function revokeInvitation(Request $request, Team $team, Invitation $invitation): RedirectResponse
    {
        abort_unless($request->user()->can('members.invite'), 403);
        abort_unless($invitation->workspace_id === $team->workspace_id, 404);

        $this->invitations->revoke($invitation, $request->user());

        return $this->done(__('teams.flash.invitation_revoked'));
    }

    public function offline(AddOfflineMemberRequest $request, Team $team): RedirectResponse
    {
        $data = $request->validated();
        $this->members->addWithoutLogin($team, $data, TeamRole::from($data['role']), $request->user());

        return $this->done(__('teams.flash.member_added'));
    }

    public function role(ChangeTeamRoleRequest $request, Team $team, User $user): RedirectResponse
    {
        $this->assertMember($team, $user);
        $this->members->changeRole($team, $user, TeamRole::from($request->validated('role')), $request->user());

        return $this->done(__('teams.flash.role_changed'));
    }

    public function captain(Request $request, Team $team, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('team.transfer'), 403);
        $this->assertMember($team, $user);

        $this->members->transferCaptaincy($team, $user, $request->user());

        return $this->done(__('teams.flash.captain_transferred', ['name' => $user->name]));
    }

    public function destroy(Request $request, Team $team, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('members.manage'), 403);
        $this->assertMember($team, $user);

        $this->members->remove($team, $user, $request->user());

        return $this->done(__('teams.flash.member_removed'));
    }

    public function leave(Request $request, Team $team): RedirectResponse
    {
        $this->members->remove($team, $request->user(), $request->user());

        $this->done(__('teams.flash.left', ['team' => $team->name]));

        return to_route('hub');
    }

    private function assertMember(Team $team, User $user): void
    {
        abort_unless($team->hasMember($user), 404);
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
