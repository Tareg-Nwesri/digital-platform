<?php

namespace App\Http\Controllers\Console;

use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\UpdateTeamFeaturesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamFeatureController extends Controller
{
    public function __invoke(UpdateTeamFeaturesRequest $request, Team $team, TeamFeatures $features): RedirectResponse
    {
        DB::transaction(function () use ($request, $team, $features): void {
            foreach ($request->overrides() as ['feature' => $feature, 'value' => $value]) {
                $value === null
                    ? $features->clearOverride($team, $feature, $request->user())
                    : $features->override($team, $feature, $value, $request->user());
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('teams.flash.features_saved')]);

        return back();
    }
}
