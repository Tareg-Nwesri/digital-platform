<?php

namespace App\Domain\Access\Services;

use App\Domain\Access\Enums\DepartmentRole;
use App\Domain\Access\Enums\WorkspaceType;
use App\Domain\Access\Models\Invitation;
use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Notifications\InvitationNotification;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamMemberService;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Invitation-only onboarding for teams and departments (LLD 01 §4).
 */
final class InvitationService
{
    public function __construct(
        private readonly TeamMemberService $teamMembers,
        private readonly DepartmentService $departments,
    ) {}

    /**
     * @return list<string>
     */
    public function rolesFor(Workspace $workspace): array
    {
        return match ($workspace->type) {
            WorkspaceType::Team => TeamRole::values(),
            WorkspaceType::Department => DepartmentRole::values(),
            default => [],
        };
    }

    public function invite(Workspace $workspace, string $email, string $role, ?User $by = null): Invitation
    {
        $email = Str::lower(trim($email));

        if (! in_array($role, $this->rolesFor($workspace), true)) {
            throw DomainRuleViolation::with(['role' => __('access.invitations.invalid_role')]);
        }

        if ($this->alreadyMember($workspace, $email)) {
            throw DomainRuleViolation::with(['email' => __('access.invitations.already_member')]);
        }

        [$invitation, $token] = DB::transaction(function () use ($workspace, $email, $role, $by): array {
            Invitation::query()->pending()
                ->where('workspace_id', $workspace->getKey())
                ->where('email', $email)
                ->update(['revoked_at' => now()]);

            $token = Str::random(48);

            $invitation = Invitation::query()->create([
                'workspace_id' => $workspace->getKey(),
                'email' => $email,
                'role' => $role,
                'token_hash' => Invitation::hashToken($token),
                'invited_by' => $by?->getKey(),
                'expires_at' => now()->addDays(config('platform.invitation_days')),
            ]);

            return [$invitation, $token];
        });

        $this->send($invitation, $token);

        activity('access')->performedOn($invitation)->causedBy($by)
            ->withProperties(['workspace_id' => $workspace->getKey(), 'email' => $email, 'role' => $role])
            ->event('invitation.sent')->log('Invitation sent');

        return $invitation;
    }

    public function resend(Invitation $invitation, ?User $by = null): Invitation
    {
        if ($invitation->accepted_at !== null || $invitation->revoked_at !== null) {
            throw DomainRuleViolation::with(['invitation' => __('access.invitations.not_pending')]);
        }

        $token = Str::random(48);
        $invitation->update([
            'token_hash' => Invitation::hashToken($token),
            'expires_at' => now()->addDays(config('platform.invitation_days')),
        ]);

        $this->send($invitation, $token);

        return $invitation;
    }

    public function revoke(Invitation $invitation, ?User $by = null): void
    {
        $invitation->update(['revoked_at' => now()]);

        activity('access')->performedOn($invitation)->causedBy($by)
            ->event('invitation.revoked')->log('Invitation revoked');
    }

    public function findPendingByToken(string $plainToken): ?Invitation
    {
        return Invitation::query()->pending()->where('token_hash', Invitation::hashToken($plainToken))->first();
    }

    /**
     * Accept for an existing account, or create one from $newUser (name_en, name_ar, password).
     *
     * @param  array{name_en?: string, name_ar?: string|null, password?: string}  $newUser
     */
    public function accept(Invitation $invitation, ?User $existing = null, array $newUser = []): User
    {
        return DB::transaction(function () use ($invitation, $existing, $newUser): User {
            $locked = Invitation::query()->lockForUpdate()->findOrFail($invitation->getKey());

            if (! $locked->isPending()) {
                throw DomainRuleViolation::with(['invitation' => __('access.invitations.not_pending')]);
            }

            $user = $existing ?? User::query()->where('email', $locked->email)->first() ?? User::query()->create([
                'name_en' => $newUser['name_en'],
                'name_ar' => $newUser['name_ar'] ?? null,
                'email' => $locked->email,
                'password' => $newUser['password'],
                'can_login' => true,
                'is_active' => true,
            ]);

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $this->addMembership($locked, $user);

            $locked->update(['accepted_at' => now(), 'accepted_user_id' => $user->getKey()]);

            activity('access')->performedOn($locked)->causedBy($user)
                ->withProperties(['workspace_id' => $locked->workspace_id, 'role' => $locked->role])
                ->event('invitation.accepted')->log('Invitation accepted');

            return $user;
        });
    }

    private function addMembership(Invitation $invitation, User $user): void
    {
        $owner = $invitation->owner();
        $inviter = $invitation->inviter;

        match (true) {
            $owner instanceof Team => $this->teamMembers->join($owner, $user, TeamRole::from($invitation->role), $inviter),
            $owner instanceof Department => $this->departments->addMember($owner, $user, $invitation->role === DepartmentRole::Admin->value, $inviter),
            default => throw DomainRuleViolation::with(['invitation' => __('access.invitations.not_pending')]),
        };
    }

    private function alreadyMember(Workspace $workspace, string $email): bool
    {
        $table = $workspace->type === WorkspaceType::Team ? 'team_members' : 'department_members';
        $ownerTable = $workspace->type === WorkspaceType::Team ? 'teams' : 'departments';
        $foreign = $workspace->type === WorkspaceType::Team ? 'team_id' : 'department_id';

        return DB::table($table)
            ->join($ownerTable, $ownerTable.'.id', '=', $table.'.'.$foreign)
            ->join('users', 'users.id', '=', $table.'.user_id')
            ->where($ownerTable.'.workspace_id', $workspace->getKey())
            ->where('users.email', $email)
            ->exists();
    }

    private function send(Invitation $invitation, string $token): void
    {
        Notification::route('mail', $invitation->email)->notifyNow(new InvitationNotification($invitation, $token));
    }
}
