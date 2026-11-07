<?php

namespace App\Http\Controllers\Console;

use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\AssignTeamTierRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TeamTierController extends Controller
{
    public function __invoke(AssignTeamTierRequest $request, Team $team, TeamService $teams): RedirectResponse
    {
        $tier = Tier::query()->findOrFail($request->validated('tier_id'));

        $teams->assignTier($team, $tier, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('teams.flash.tier_changed', ['tier' => $tier->localized('name')])]);

        return back();
    }
}
