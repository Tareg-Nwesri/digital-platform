<?php

namespace App\Http\Controllers\Console;

use App\Domain\Access\Models\Invitation;
use App\Domain\Access\Services\InvitationService;
use App\Domain\Organisation\Models\Department;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\StoreDepartmentInvitationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentInvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function store(StoreDepartmentInvitationRequest $request, Department $department): RedirectResponse
    {
        $invitation = $this->invitations->invite($department->workspace, $request->validated('email'), $request->validated('role'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.invited', ['email' => $invitation->email])]);

        return back();
    }

    public function destroy(Request $request, Department $department, Invitation $invitation): RedirectResponse
    {
        $this->authorize('manageMembers', $department);
        abort_unless($invitation->workspace_id === $department->workspace_id && $invitation->isPending(), 404);

        $this->invitations->revoke($invitation, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.invitation_revoked')]);

        return back();
    }
}
