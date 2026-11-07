<?php

namespace App\Http\Controllers\Console;

use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\ChangeTeamStatusRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TeamStatusController extends Controller
{
    public function __invoke(ChangeTeamStatusRequest $request, Team $team, TeamService $teams): RedirectResponse
    {
        $status = TeamStatus::from($request->validated('status'));

        $teams->changeStatus($team, $status, $request->validated('reason'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('teams.flash.status_changed', ['status' => $status->label()])]);

        return back();
    }
}
