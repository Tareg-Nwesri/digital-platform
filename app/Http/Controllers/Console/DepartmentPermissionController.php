<?php

namespace App\Http\Controllers\Console;

use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\SyncMemberPermissionsRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class DepartmentPermissionController extends Controller
{
    public function __invoke(SyncMemberPermissionsRequest $request, Department $department, User $user, DepartmentPermissionService $permissions): RedirectResponse
    {
        abort_unless($department->hasMember($user), 404);

        $permissions->syncMember($department, $user, $request->validated('permissions'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.permissions_saved', ['name' => $user->name])]);

        return back();
    }
}
