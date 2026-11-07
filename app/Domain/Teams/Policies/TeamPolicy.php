<?php

namespace App\Domain\Teams\Policies;

use App\Domain\Organisation\Services\DepartmentAccess;
use App\Domain\Teams\Models\Team;
use App\Models\User;

/**
 * Console abilities on teams (LLD 03 §6). Staff act on teams supervised by a
 * department where they hold the permission; teams.view.all sees every team.
 * The super admin passes through Gate::before.
 */
class TeamPolicy
{
    public function __construct(private readonly DepartmentAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'teams.view');
    }

    public function view(User $user, Team $team): bool
    {
        return $this->access->can($user, 'teams.view.all') || $this->inDepartment($user, $team, 'teams.view');
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'teams.edit');
    }

    public function update(User $user, Team $team): bool
    {
        return $this->inDepartment($user, $team, 'teams.edit');
    }

    public function assignTier(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }

    public function overrideFeatures(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }

    public function changeStatus(User $user, Team $team): bool
    {
        return $this->inDepartment($user, $team, 'teams.approve');
    }

    private function inDepartment(User $user, Team $team, string $permission): bool
    {
        return $team->department !== null && $this->access->can($user, $permission, $team->department);
    }
}
