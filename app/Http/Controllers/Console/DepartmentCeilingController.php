<?php

namespace App\Http\Controllers\Console;

use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\UpdateCeilingRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class DepartmentCeilingController extends Controller
{
    public function __invoke(UpdateCeilingRequest $request, Department $department, DepartmentPermissionService $permissions): RedirectResponse
    {
        $permissions->setCeiling($department, $request->validated('permissions'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.ceiling_saved')]);

        return back();
    }
}
