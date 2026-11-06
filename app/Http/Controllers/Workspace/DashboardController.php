<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamMemberService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Team dashboard (SRS FR-WS-01). Project, robot and budget tiles arrive with those modules.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly TeamFeatures $features,
        private readonly TeamMemberService $members,
    ) {}

    public function __invoke(Request $request, Team $team): Response
    {
        return Inertia::render('workspace/Dashboard', [
            'team' => [
                'slug' => $team->slug,
                'name' => $team->name,
                'status' => $team->status->value,
                'statusLabel' => $team->status->label(),
                'tier' => $team->tier->localized('name'),
            ],
            'role' => $this->members->roleOf($team, $request->user())?->value,
            'stats' => [
                'members' => [
                    'used' => $team->memberCount(),
                    'limit' => (int) $this->features->value($team, TeamFeature::MembersMax),
                ],
            ],
        ]);
    }
}
