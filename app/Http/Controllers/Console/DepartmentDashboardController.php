<?php

namespace App\Http\Controllers\Console;

use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentDashboardController extends Controller
{
    public function __construct(private readonly DepartmentPermissionService $permissions) {}

    public function __invoke(Request $request, Department $department): Response
    {
        $user = $request->user();

        return Inertia::render('console/departments/Dashboard', [
            'department' => [
                'slug' => $department->slug,
                'name' => $department->localized('name'),
                'isAdmin' => $department->isAdmin($user),
            ],
            'membersCount' => $department->members()->count(),
            'myPermissions' => $department->isAdmin($user) || $user->isSuperAdmin()
                ? $department->ceilingNames()->values()->all()
                : $this->permissions->memberPermissions($department, $user)->all(),
        ]);
    }
}
