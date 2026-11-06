<?php

namespace App\Domain\Teams\Services;

use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TeamMemberService
{
    public function __construct(
        private readonly WorkspaceContext $context,
        private readonly TeamFeatures $features,
    ) {}

    /**
     * Add a user to a team with a role, enforcing the members.max limit under a row lock.
     */
    public function join(Team $team, User $user, TeamRole $role, ?User $by = null): void
    {
        if (! $user->can_login && ! $role->allowedWithoutLogin()) {
            throw DomainRuleViolation::with(['role' => __('teams.errors.offline_role')]);
        }

        DB::transaction(function () use ($team, $user, $role, $by): void {
            $locked = Team::query()->lockForUpdate()->findOrFail($team->getKey());

            if ($locked->hasMember($user)) {
                throw DomainRuleViolation::with(['user' => __('teams.errors.already_member')]);
            }

            $limit = (int) $this->features->value($locked, TeamFeature::MembersMax);

            if ($locked->memberCount() >= $limit) {
                throw DomainRuleViolation::with(['members' => __('teams.errors.limit_reached', ['limit' => $limit])]);
            }

            if ($role === TeamRole::Captain && $this->captain($locked) !== null) {
                throw DomainRuleViolation::with(['role' => __('teams.errors.one_captain')]);
            }

            $locked->members()->attach($user->getKey(), ['joined_at' => now(), 'added_by' => $by?->getKey()]);
            $this->context->within($locked->workspace, fn () => $user->assignRole($role->value));

            activity('teams')->performedOn($locked)->causedBy($by)
                ->withProperties(['user_id' => $user->getKey(), 'role' => $role->value])
                ->event('member.joined')->log('Member joined team');
        });
    }

    /**
     * Record a member who has no login, then add them to the team.
     *
     * @param  array{name_en: string, name_ar?: string|null, birthdate: string}  $data
     */
    public function addWithoutLogin(Team $team, array $data, TeamRole $role, ?User $by = null): User
    {
        return DB::transaction(function () use ($team, $data, $role, $by): User {
            $user = User::query()->create([
                'name_en' => $data['name_en'],
                'name_ar' => $data['name_ar'] ?? null,
                'birthdate' => $data['birthdate'],
                'can_login' => false,
                'email' => null,
                'password' => null,
            ]);

            $this->join($team, $user, $role, $by);

            return $user;
        });
    }

    public function roleOf(Team $team, User $user): ?TeamRole
    {
        $name = $this->context->within($team->workspace, fn () => $user->unsetRelation('roles')->getRoleNames()->first());

        return $name !== null ? TeamRole::tryFrom($name) : null;
    }

    public function captain(Team $team): ?User
    {
        return $team->members()->get()->first(fn (User $member): bool => $this->roleOf($team, $member) === TeamRole::Captain);
    }

    public function changeRole(Team $team, User $user, TeamRole $role, ?User $by = null): void
    {
        $current = $this->roleOf($team, $user);

        if ($current === null) {
            throw DomainRuleViolation::with(['user' => __('teams.errors.not_member')]);
        }

        if ($role === TeamRole::Captain || $current === TeamRole::Captain) {
            throw DomainRuleViolation::with(['role' => __('teams.errors.use_transfer')]);
        }

        if (! $user->can_login && ! $role->allowedWithoutLogin()) {
            throw DomainRuleViolation::with(['role' => __('teams.errors.offline_role')]);
        }

        $this->context->within($team->workspace, fn () => $user->syncRoles([$role->value]));

        activity('teams')->performedOn($team)->causedBy($by)
            ->withProperties(['user_id' => $user->getKey(), 'from' => $current->value, 'to' => $role->value])
            ->event('member.role_changed')->log('Member role changed');
    }

    /**
     * Swap captaincy: the old captain becomes a coach.
     */
    public function transferCaptaincy(Team $team, User $to, ?User $by = null): void
    {
        DB::transaction(function () use ($team, $to, $by): void {
            $captain = $this->captain($team);

            if (! $team->hasMember($to) || ! $to->can_login) {
                throw DomainRuleViolation::with(['user' => __('teams.errors.captain_needs_login')]);
            }

            $this->context->within($team->workspace, function () use ($captain, $to): void {
                $captain?->syncRoles([TeamRole::Coach->value]);
                $to->syncRoles([TeamRole::Captain->value]);
            });

            activity('teams')->performedOn($team)->causedBy($by)
                ->withProperties(['from' => $captain?->getKey(), 'to' => $to->getKey()])
                ->event('captain.transferred')->log('Captaincy transferred');
        });
    }

    public function remove(Team $team, User $user, ?User $by = null): void
    {
        if ($this->roleOf($team, $user) === TeamRole::Captain) {
            throw DomainRuleViolation::with(['user' => __('teams.errors.remove_captain')]);
        }

        DB::transaction(function () use ($team, $user, $by): void {
            $this->context->within($team->workspace, fn () => $user->syncRoles([]));
            $team->members()->detach($user->getKey());

            activity('teams')->performedOn($team)->causedBy($by)
                ->withProperties(['user_id' => $user->getKey()])
                ->event('member.removed')->log('Member removed from team');
        });
    }
}
