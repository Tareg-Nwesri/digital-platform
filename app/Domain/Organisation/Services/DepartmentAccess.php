<?php

namespace App\Domain\Organisation\Services;

use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Read helper for department-scoped console permissions (LLD 02 §4).
 * Console screens that span departments (teams, events, ...) use it to find
 * where a user holds a permission. Results are memoised per request.
 */
final class DepartmentAccess
{
    /**
     * @var array<int, Collection<int, Department>>
     */
    private array $memberships = [];

    /**
     * @var array<string, bool>
     */
    private array $checks = [];

    public function __construct(private readonly WorkspaceContext $context) {}

    /**
     * With no department, true when any of the user's departments grants the permission.
     */
    public function can(User $user, string $permission, ?Department $department = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($department !== null) {
            return $this->holds($user, $permission, $department);
        }

        return $this->departmentsWith($user, $permission)->isNotEmpty();
    }

    /**
     * The user's departments that grant the permission (empty for the super admin,
     * who is never filtered).
     *
     * @return Collection<int, Department>
     */
    public function departmentsWith(User $user, string $permission): Collection
    {
        return $this->membershipsOf($user)
            ->filter(fn (Department $department): bool => $this->holds($user, $permission, $department))
            ->values();
    }

    /**
     * @return list<int>
     */
    public function departmentIdsWith(User $user, string $permission): array
    {
        return $this->departmentsWith($user, $permission)->map(fn (Department $department): int => $department->getKey())->all();
    }

    /**
     * @return Collection<int, Department>
     */
    public function membershipsOf(User $user): Collection
    {
        return $this->memberships[$user->getKey()] ??= Department::query()
            ->whereHas('members', fn ($query) => $query->whereKey($user->getKey()))
            ->with('workspace')
            ->orderBy('name_en')
            ->get();
    }

    public function forget(): void
    {
        $this->memberships = [];
        $this->checks = [];
    }

    private function holds(User $user, string $permission, Department $department): bool
    {
        $key = $user->getKey().'|'.$department->getKey().'|'.$permission;

        return $this->checks[$key] ??= $department->hasMember($user) && $this->context->within(
            $department->workspace,
            fn (): bool => $user->unsetRelation('permissions')->unsetRelation('roles')->checkPermissionTo($permission),
        );
    }
}
