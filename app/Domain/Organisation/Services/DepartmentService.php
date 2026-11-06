<?php

namespace App\Domain\Organisation\Services;

use App\Domain\Access\Enums\WorkspaceType;
use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Models\Department;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Slugger;
use Illuminate\Support\Facades\DB;

final class DepartmentService
{
    public function __construct(
        private readonly WorkspaceContext $context,
        private readonly DepartmentPermissionService $permissions,
    ) {}

    /**
     * @param  array{name_en: string, name_ar?: string|null, slug?: string|null, description_en?: string|null, description_ar?: string|null, is_public?: bool}  $data
     */
    public function create(array $data, ?User $by = null): Department
    {
        return DB::transaction(function () use ($data): Department {
            $workspace = Workspace::open(WorkspaceType::Department);

            $department = Department::query()->create([
                'workspace_id' => $workspace->getKey(),
                'name_en' => $data['name_en'],
                'name_ar' => $data['name_ar'] ?? null,
                'slug' => Slugger::unique(Department::class, (string) ($data['slug'] ?? $data['name_en'])),
                'description_en' => $data['description_en'] ?? null,
                'description_ar' => $data['description_ar'] ?? null,
                'is_public' => (bool) ($data['is_public'] ?? false),
            ]);

            $this->permissions->syncAdminRole($department);

            return $department;
        });
    }

    public function addMember(Department $department, User $user, bool $isAdmin = false, ?User $by = null): void
    {
        if ($department->hasMember($user)) {
            throw DomainRuleViolation::with(['user' => __('organisation.errors.already_member')]);
        }

        DB::transaction(function () use ($department, $user, $isAdmin, $by): void {
            $department->members()->attach($user->getKey(), ['is_admin' => false, 'joined_at' => now()]);

            if ($isAdmin) {
                $this->setAdmin($department, $user, true, $by);
            }

            activity('organisation')->performedOn($department)->causedBy($by)
                ->withProperties(['user_id' => $user->getKey(), 'is_admin' => $isAdmin])
                ->event('department.member.added')->log('Department member added');
        });
    }

    public function setAdmin(Department $department, User $user, bool $isAdmin, ?User $by = null): void
    {
        DB::transaction(function () use ($department, $user, $isAdmin, $by): void {
            $department->members()->updateExistingPivot($user->getKey(), ['is_admin' => $isAdmin]);

            $this->context->within($department->workspace, function () use ($user, $isAdmin): void {
                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');

                if ($isAdmin) {
                    $user->syncPermissions([]);
                    $user->assignRole(Department::ADMIN_ROLE);
                } else {
                    $user->removeRole(Department::ADMIN_ROLE);
                }
            });

            $this->permissions->syncAdminRole($department);

            activity('organisation')->performedOn($department)->causedBy($by)
                ->withProperties(['user_id' => $user->getKey(), 'is_admin' => $isAdmin])
                ->event('department.admin.changed')->log('Department admin changed');
        });
    }

    public function removeMember(Department $department, User $user, ?User $by = null): void
    {
        DB::transaction(function () use ($department, $user, $by): void {
            $this->context->within($department->workspace, function () use ($user): void {
                $user->unsetRelation('roles')->unsetRelation('permissions');
                $user->syncRoles([]);
                $user->syncPermissions([]);
            });

            $department->members()->detach($user->getKey());

            activity('organisation')->performedOn($department)->causedBy($by)
                ->withProperties(['user_id' => $user->getKey()])
                ->event('department.member.removed')->log('Department member removed');
        });
    }
}
