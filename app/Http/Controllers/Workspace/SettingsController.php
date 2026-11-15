<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\TeamLogoRequest;
use App\Http\Requests\Workspace\TeamProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 04 §3: the team's public profile and logo. The name, tier and
 * status stay with the organisers.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly TeamFeatures $features) {}

    public function edit(Request $request, Team $team): Response
    {
        abort_unless($request->user()->can('team.settings'), 403);

        return Inertia::render('workspace/settings/Edit', [
            'team' => [
                'slug' => $team->slug,
                'name' => $team->name,
                'logo' => $team->logoUrl(),
                'location' => $team->location,
                'focusEn' => $team->focus_en,
                'focusAr' => $team->focus_ar,
                'descriptionEn' => $team->description_en,
                'descriptionAr' => $team->description_ar,
                'officialUrl' => $team->official_url,
                'isPublic' => $team->is_public,
            ],
            'can' => ['publish' => $this->features->active($team, TeamFeature::PublicProfile)],
        ]);
    }

    public function update(TeamProfileRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        return $this->done('profile_saved');
    }

    public function logo(TeamLogoRequest $request, Team $team): RedirectResponse
    {
        $team->addMediaFromRequest('logo')->toMediaCollection('logo');
        activity('teams')->performedOn($team)->causedBy($request->user())->event('team.logo_changed')->log('Team logo changed');

        return $this->done('logo_saved');
    }

    public function destroyLogo(Request $request, Team $team): RedirectResponse
    {
        abort_unless($request->user()->can('team.settings'), 403);
        $team->clearMediaCollection('logo');
        activity('teams')->performedOn($team)->causedBy($request->user())->event('team.logo_removed')->log('Team logo removed');

        return $this->done('logo_removed');
    }

    private function done(string $key): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('teams.flash.'.$key)]);

        return back();
    }
}
