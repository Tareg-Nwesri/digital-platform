<?php

namespace App\Domain\Organisation\Services;

use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Models\Department;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The only writer of department permissions (LLD 02 §4).
 */
final class DepartmentPermissionService
{
    public function __construct(
        private readonly WorkspaceContext $context,
        private readonly PermissionCatalogue $catalogue,
    ) {}

    /**
     * @param  list<string>  $permissionNames
     */
    public function setCeiling(Department $department, array $permissionNames, User $by): void
    {
        if (! $by->isSuperAdmin()) {
            throw DomainRuleViolation::with(['ceiling' => __('organisation.errors.super_admin_only')]);
        }

        $names = collect($permissionNames)->unique()->values();

        foreach ($names as $name) {
            if ($this->catalogue->isReserved($name) || ! $this->catalogue->isDelegable($name)) {
                throw DomainRuleViolation::with(['ceiling' => __('organisation.errors.not_delegable', ['permission' => $name])]);
            }
        }

        DB::transaction(function () use ($department, $names, $by): void {
            $old = $department->ceilingNames();
            $removed = $old->diff($names);

            $department->ceiling()->sync($this->permissionIds($names));

            $this->context->within($department->workspace, function () use ($department, $removed): void {
                foreach ($department->members()->wherePivot('is_admin', false)->get() as $member) {
                    $member->unsetRelation('permissions');
                    foreach ($removed as $name) {
                        if ($member->hasDirectPermission($name)) {
                            $member->revokePermissionTo($name);
                        }
                    }
                }
            });

            $this->syncAdminRole($department->fresh());

            activity('organisation')->performedOn($department)->causedBy($by)
                ->withProperties(['ceiling' => $names->all(), 'removed' => $removed->values()->all()])
                ->event('department.ceiling.updated')->log('Department ceiling updated');
        });
    }

    /**
     * Replace a member's delegated permissions with the given list, limited to the ceiling.
     *
     * @param  list<string>  $permissionNames
     */
    public function syncMember(Department $department, User $member, array $permissionNames, User $admin): void
    {
        $this->assertCanDelegate($department, $member, $admin);

        $requested = $this->withImpliedViews(collect($permissionNames));
        $ceiling = $department->ceilingNames();

        foreach ($requested as $name) {
            $this->assertGrantable($name, $ceiling);
        }

        $this->context->within($department->workspace, function () use ($member, $requested): void {
            $member->unsetRelation('permissions')->syncPermissions($requested->all());
        });

        activity('organisation')->performedOn($department)->causedBy($admin)
            ->withProperties(['member_id' => $member->getKey(), 'permissions' => $requested->all()])
            ->event('department.permissions.synced')->log('Member permissions updated');
    }

    public function grant(Department $department, User $member, string $permission, User $admin): void
    {
        $current = $this->memberPermissions($department, $member);

        $this->syncMember($department, $member, $current->push($permission)->unique()->values()->all(), $admin);
    }

    public function revoke(Department $department, User $member, string $permission, User $admin): void
    {
        $remaining = $this->memberPermissions($department, $member)->reject(fn (string $name): bool => $name === $permission);

        if (Str::endsWith($permission, '.view')) {
            $area = Str::before($permission, '.');
            $remaining = $remaining->reject(fn (string $name): bool => $name === $area.'.edit');
        }

        $this->syncMember($department, $member, $remaining->values()->all(), $admin);
    }

    /**
     * @return Collection<int, string>
     */
    public function memberPermissions(Department $department, User $member): Collection
    {
        return $this->context->within(
            $department->workspace,
            fn () => $member->unsetRelation('permissions')->getDirectPermissions()->pluck('name')->values(),
        );
    }

    /**
     * Give department admins the ceiling plus the implied approvals and members.manage.
     */
    public function syncAdminRole(Department $department): void
    {
        $ceiling = $department->ceilingNames();
        $names = $ceiling
            ->merge($this->catalogue->approvalsFor($ceiling))
            ->merge(config('permissions.department_admin_only', []))
            ->unique()
            ->values();

        $this->context->within($department->workspace, function () use ($names): void {
            Role::findOrCreate(Department::ADMIN_ROLE, 'web')->syncPermissions($this->permissionModels($names));
        });
    }

    /**
     * Report members holding permissions outside the ceiling (nightly check).
     *
     * @return list<array{member_id: int, permission: string}>
     */
    public function assertConsistent(Department $department): array
    {
        $ceiling = $department->ceilingNames();
        $violations = [];

        foreach ($department->members()->wherePivot('is_admin', false)->get() as $member) {
            foreach ($this->memberPermissions($department, $member) as $name) {
                if (! $ceiling->contains($name)) {
                    $violations[] = ['member_id' => $member->getKey(), 'permission' => $name];
                }
            }
        }

        return $violations;
    }

    private function assertCanDelegate(Department $department, User $member, User $admin): void
    {
        if (! $admin->isSuperAdmin() && ! $department->isAdmin($admin)) {
            throw DomainRuleViolation::with(['permissions' => __('organisation.errors.admin_only')]);
        }

        if (! $department->hasMember($member) || $department->isAdmin($member)) {
            throw DomainRuleViolation::with(['permissions' => __('organisation.errors.member_only')]);
        }
    }

    /**
     * @param  Collection<int, string>  $ceiling
     */
    private function assertGrantable(string $name, Collection $ceiling): void
    {
        if ($this->catalogue->isReserved($name) || $this->catalogue->isApprove($name) || ! $this->catalogue->isDelegable($name)) {
            throw DomainRuleViolation::with(['permissions' => __('organisation.errors.not_delegable', ['permission' => $name])]);
        }

        if (! $ceiling->contains($name)) {
            throw DomainRuleViolation::with(['permissions' => __('organisation.errors.outside_ceiling', ['permission' => $name])]);
        }
    }

    /**
     * Granting <area>.edit (or view.all) also grants <area>.view.
     *
     * @param  Collection<int, string>  $names
     * @return Collection<int, string>
     */
    private function withImpliedViews(Collection $names): Collection
    {
        return $names
            ->flatMap(fn (string $name): array => Str::endsWith($name, ['.edit', '.view.all'])
                ? [$name, Str::before($name, '.').'.view']
                : [$name])
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, string>  $names
     * @return list<int>
     */
    private function permissionIds(Collection $names): array
    {
        return $this->permissionModels($names)->map(fn (Permission $permission): int => $permission->getKey())->all();
    }

    /**
     * @param  Collection<int, string>  $names
     * @return Collection<int, Permission>
     */
    private function permissionModels(Collection $names): Collection
    {
        return $names->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'))->values();
    }
}
