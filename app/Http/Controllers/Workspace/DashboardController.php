<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamMemberService;
use App\Domain\Work\Enums\ProjectStatus;
use App\Domain\Work\Enums\RobotStatus;
use App\Domain\Work\Services\BomCalculator;
use App\Http\Controllers\Controller;
use App\Support\Money\MoneyFormatter;
use Brick\Money\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Team dashboard (SRS FR-WS-01): members, projects, robots, parts cost and
 * upcoming event entries.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly TeamFeatures $features,
        private readonly TeamMemberService $members,
        private readonly BomCalculator $bom,
    ) {}

    public function __invoke(Request $request, Team $team): Response
    {
        $cost = $this->bom->forTeam($team);

        return Inertia::render('workspace/Dashboard', [
            'team' => [
                'slug' => $team->slug,
                'name' => $team->name,
                'status' => $team->status->value,
                'statusLabel' => $team->status->label(),
                'tier' => $team->tier->localized('name'),
                'logo' => $team->logoUrl(),
            ],
            'role' => $this->members->roleOf($team, $request->user())?->value,
            'stats' => [
                'members' => [
                    'used' => $team->memberCount(),
                    'limit' => (int) $this->features->value($team, TeamFeature::MembersMax),
                ],
                'projects' => [
                    'published' => $team->projects()->where('status', ProjectStatus::Published)->count(),
                    'drafts' => $team->projects()->where('status', ProjectStatus::Draft)->count(),
                ],
                'robots' => $team->robots()->where('status', '!=', RobotStatus::Retired)->count(),
                'partsCost' => [
                    'totals' => array_values(array_map(fn (Money $total): string => MoneyFormatter::format($total), $cost['totals'])),
                    'unpriced' => $cost['unpriced'],
                ],
            ],
            'upcoming' => $team->registrations()
                ->whereIn('status', RegistrationStatus::active())
                ->whereHas('event', fn ($query) => $query->where('ends_at', '>=', now()))
                ->with(['event', 'category'])
                ->get()
                ->sortBy(fn (EventRegistration $registration) => $registration->event->starts_at)
                ->take(5)
                ->map(fn (EventRegistration $registration): array => [
                    'id' => $registration->getKey(),
                    'slug' => $registration->event->slug,
                    'title' => (string) $registration->event->localized('title'),
                    'category' => (string) $registration->category->localized('name'),
                    'startsAt' => $registration->event->starts_at->toIso8601String(),
                    'timezone' => $registration->event->timezone,
                    'status' => $registration->status->value,
                ])
                ->values()
                ->all(),
        ]);
    }
}
