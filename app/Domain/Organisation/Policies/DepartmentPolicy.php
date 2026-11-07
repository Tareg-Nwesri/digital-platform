<?php

namespace App\Domain\Organisation\Policies;

use App\Domain\Organisation\Models\Department;
use App\Models\User;

/**
 * LLD 02 §5. The super admin passes every ability through Gate::before;
 * the rules below are for everyone else.
 */
class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Department $department): bool
    {
        return false;
    }

    public function setCeiling(User $user, Department $department): bool
    {
        return false;
    }

    public function view(User $user, Department $department): bool
    {
        return $department->hasMember($user);
    }

    public function manageMembers(User $user, Department $department): bool
    {
        return $department->isAdmin($user);
    }

    public function grant(User $user, Department $department, User $member): bool
    {
        return $this->manageMembers($user, $department) && ! $department->isAdmin($member);
    }
}
