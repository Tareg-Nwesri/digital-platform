<?php

namespace App\Http\Controllers\Console;

use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\SetDepartmentAdminRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentMemberController extends Controller
{
    public function __construct(private readonly DepartmentService $departments) {}

    public function setAdmin(SetDepartmentAdminRequest $request, Department $department, User $user): RedirectResponse
    {
        abort_unless($department->hasMember($user), 404);

        $this->departments->setAdmin($department, $user, $request->boolean('is_admin'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.admin_changed', ['name' => $user->name])]);

        return back();
    }

    public function destroy(Request $request, Department $department, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $department);
        abort_unless($department->hasMember($user), 404);

        $this->departments->removeMember($department, $user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.member_removed', ['name' => $user->name])]);

        return back();
    }
}
