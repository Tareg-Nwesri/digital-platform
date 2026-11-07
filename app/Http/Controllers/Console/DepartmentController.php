<?php

namespace App\Http\Controllers\Console;

use App\Domain\Access\Models\Invitation;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Organisation\Services\PermissionCatalogue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\StoreDepartmentRequest;
use App\Http\Requests\Console\UpdateDepartmentRequest;
use App\Models\User;
use App\Support\Tables\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * LLD 02 §6–8: the departments list (super admin) and each department's page.
 * Members see the overview; admins also manage members and permissions.
 */
class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departments,
        private readonly DepartmentPermissionService $permissions,
        private readonly PermissionCatalogue $catalogue,
        private readonly WorkspaceContext $context,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Department::class);

        $departments = TableQuery::for(Department::query()->withCount(['members', 'admins']), $request)
            ->searchable(['name_en', 'name_ar', 'slug'])
            ->sortable(['name_en', 'members_count', 'created_at'], default: 'name_en')
            ->paginate(fn (Department $department): array => [
                'id' => $department->getKey(),
                'slug' => $department->slug,
                'name' => $department->localized('name'),
                'membersCount' => (int) $department->members_count,
                'adminsCount' => (int) $department->admins_count,
                'isPublic' => (bool) $department->is_public,
            ]);

        return Inertia::render('console/departments/Index', [
            'departments' => $departments,
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = $this->departments->create($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.created')]);

        return to_route('console.departments.show', $department->slug);
    }

    public function show(Request $request, Department $department): Response
    {
        $this->authorize('view', $department);

        $user = $request->user();
        $canManageMembers = $user->can('manageMembers', $department);
        $ceiling = $department->ceilingNames()->sort()->values()->all();

        return Inertia::render('console/departments/Show', [
            'department' => [
                'id' => $department->getKey(),
                'slug' => $department->slug,
                'name' => $department->localized('name'),
                'name_en' => $department->name_en,
                'name_ar' => $department->name_ar,
                'description_en' => $department->description_en,
                'description_ar' => $department->description_ar,
                'is_public' => (bool) $department->is_public,
            ],
            'isAdmin' => $department->isAdmin($user),
            'canEditCeiling' => $user->can('setCeiling', $department),
            'canEditDetails' => $user->can('update', $department),
            'canManageMembers' => $canManageMembers,
            'stats' => [
                'members' => $department->members()->count(),
                'admins' => $department->admins()->count(),
                'teams' => $department->supervisedTeams()->count(),
            ],
            'myPermissions' => $department->isAdmin($user) || $user->isSuperAdmin()
                ? $ceiling
                : $this->permissions->memberPermissions($department, $user)->sort()->values()->all(),
            'ceiling' => $ceiling,
            'catalogue' => $this->catalogue->matrix(),
            'activity' => fn (): array => $this->recentActivity($department),
            ...($canManageMembers ? [
                'members' => $this->members($department),
                'invitations' => $this->invitations($department),
                'inviteRoles' => $user->isSuperAdmin() ? ['department-member', 'department-admin'] : ['department-member'],
            ] : []),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = filled($data['slug'] ?? null) ? $data['slug'] : $department->slug;
        $data['is_public'] = (bool) ($data['is_public'] ?? false);

        $department->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.updated')]);

        return to_route('console.departments.show', $department->slug);
    }

    /**
     * Members with their direct permissions in this department's workspace,
     * loaded in one query.
     *
     * @return list<array{id: int, name: string, email: string|null, isAdmin: bool, canLogin: bool, permissions: list<string>}>
     */
    private function members(Department $department): array
    {
        return $this->context->within($department->workspace, fn (): array => $department->members()
            ->with('permissions')
            ->orderByDesc('department_members.is_admin')
            ->orderBy('name_en')
            ->get()
            ->map(fn (User $member): array => [
                'id' => $member->getKey(),
                'name' => $member->name,
                'email' => $member->email,
                'isAdmin' => (bool) $member->pivot->is_admin,
                'canLogin' => (bool) $member->can_login,
                'permissions' => $member->permissions->pluck('name')->sort()->values()->all(),
            ])
            ->all());
    }

    /**
     * @return list<array{id: int, email: string, role: string, expiresAt: string|null}>
     */
    private function invitations(Department $department): array
    {
        return Invitation::query()->pending()
            ->where('workspace_id', $department->workspace_id)
            ->latest('id')
            ->get()
            ->map(fn (Invitation $invitation): array => [
                'id' => $invitation->getKey(),
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expiresAt' => $invitation->expires_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, event: string|null, description: string, causer: string|null, at: string|null}>
     */
    private function recentActivity(Department $department): array
    {
        return Activity::query()->forSubject($department)->with('causer')->latest('id')->limit(10)->get()
            ->map(fn (Activity $activity): array => [
                'id' => $activity->getKey(),
                'event' => $activity->event,
                'description' => $activity->description,
                'causer' => $activity->causer?->name,
                'at' => $activity->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
