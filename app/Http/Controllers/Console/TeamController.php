<?php

namespace App\Http\Controllers\Console;

use App\Domain\Access\Services\InvitationService;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentAccess;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\StoreTeamRequest;
use App\Http\Requests\Console\UpdateTeamRequest;
use App\Models\User;
use App\Support\Tables\TableQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * LLD 03 §7–9: the teams area of the organisation console.
 */
class TeamController extends Controller
{
    public function __construct(
        private readonly DepartmentAccess $access,
        private readonly TeamFeatures $features,
        private readonly WorkspaceContext $context,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Team::class);

        $user = $request->user();

        $teams = TableQuery::for($this->visibleTeams($user)->with(['tier', 'department'])->withCount('members'), $request)
            ->searchable(['name', 'slug', 'location'])
            ->sortable(['name', 'members_count', 'created_at'], default: 'name')
            ->filters([
                'status' => fn (Builder $query, mixed $value) => $query->where('status', (string) $value),
                'tier' => fn (Builder $query, mixed $value) => $query->whereHas('tier', fn ($tier) => $tier->where('slug', (string) $value)),
                'department' => fn (Builder $query, mixed $value) => $query->whereHas('department', fn ($department) => $department->where('slug', (string) $value)),
            ])
            ->paginate(fn (Team $team): array => [
                'id' => $team->getKey(),
                'slug' => $team->slug,
                'name' => $team->name,
                'status' => $team->status->value,
                'tier' => $team->tier->localized('name'),
                'department' => $team->department?->localized('name'),
                'members' => (int) $team->members_count,
                'membersLimit' => $this->features->value($team, TeamFeature::MembersMax),
                'createdAt' => $team->created_at?->toIso8601String(),
            ]);

        return Inertia::render('console/teams/Index', [
            'teams' => $teams,
            'filters' => [
                'statuses' => array_column(TeamStatus::cases(), 'value'),
                'tiers' => $this->tierOptions(),
                'departments' => $this->departmentOptions($user, 'teams.view'),
            ],
            'editableDepartments' => $this->departmentOptions($user, 'teams.edit'),
            'can' => ['create' => $user->can('create', Team::class), 'unsupervised' => $user->isSuperAdmin()],
        ]);
    }

    public function store(StoreTeamRequest $request, TeamService $teams, InvitationService $invitations): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $department = isset($data['department_id']) ? Department::query()->find($data['department_id']) : null;
        $approved = $user->isSuperAdmin() || ($department !== null && $this->access->can($user, 'teams.approve', $department));

        $team = DB::transaction(function () use ($teams, $invitations, $data, $user, $approved): Team {
            $team = $teams->create($data, $user, $approved);

            if (filled($data['captain_email'] ?? null)) {
                $invitations->invite($team->workspace, $data['captain_email'], TeamRole::Captain->value, $user);
            }

            return $team;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => $approved ? __('teams.flash.created') : __('teams.flash.created_pending')]);

        return to_route('console.teams.show', $team->slug);
    }

    public function show(Request $request, Team $team): Response
    {
        $this->authorize('view', $team);

        $user = $request->user();
        $team->loadMissing(['tier', 'department']);

        return Inertia::render('console/teams/Show', [
            'team' => [
                'id' => $team->getKey(),
                'slug' => $team->slug,
                'name' => $team->name,
                'status' => $team->status->value,
                'statusReason' => $team->status_reason,
                'location' => $team->location,
                'isPublic' => (bool) $team->is_public,
                'tier' => ['id' => $team->tier->getKey(), 'slug' => $team->tier->slug, 'name' => $team->tier->localized('name')],
                'department' => $team->department === null ? null : [
                    'id' => $team->department->getKey(),
                    'slug' => $team->department->slug,
                    'name' => $team->department->localized('name'),
                ],
                'createdAt' => $team->created_at?->toIso8601String(),
                'approvedAt' => $team->approved_at?->toIso8601String(),
            ],
            'members' => $this->members($team),
            'features' => $this->features->breakdown($team),
            'transitions' => array_map(fn (TeamStatus $status): string => $status->value, Team::allowedTransitions()[$team->status->value] ?? []),
            'can' => [
                'update' => $user->can('update', $team),
                'changeStatus' => $user->can('changeStatus', $team),
                'assignTier' => $user->can('assignTier', $team),
                'overrideFeatures' => $user->can('overrideFeatures', $team),
            ],
            'canLeaveUnsupervised' => $user->isSuperAdmin(),
            'tiers' => fn (): array => $this->tierOptions(),
            'editableDepartments' => fn (): array => $this->departmentOptions($user, 'teams.edit'),
            'activity' => fn (): array => $this->recentActivity($team),
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = filled($data['slug'] ?? null) ? $data['slug'] : $team->slug;

        if (! array_key_exists('department_id', $data)) {
            $data['department_id'] = $team->department_id;
        }

        $team->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('teams.flash.updated')]);

        return to_route('console.teams.show', $team->slug);
    }

    /**
     * Teams the user may see: every team for the super admin and holders of
     * teams.view.all, otherwise those supervised by their departments.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Team>
     */
    private function visibleTeams(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = Team::query();

        if ($user->isSuperAdmin() || $this->access->can($user, 'teams.view.all')) {
            return $query;
        }

        return $query->whereIn('department_id', $this->access->departmentIdsWith($user, 'teams.view'));
    }

    /**
     * @return list<array{id: int, slug: string, name: string}>
     */
    private function tierOptions(): array
    {
        return Tier::query()->orderBy('position')->get()
            ->map(fn (Tier $tier): array => ['id' => $tier->getKey(), 'slug' => $tier->slug, 'name' => $tier->localized('name')])
            ->all();
    }

    /**
     * @return list<array{id: int, slug: string, name: string}>
     */
    private function departmentOptions(User $user, string $permission): array
    {
        $departments = $user->isSuperAdmin()
            ? Department::query()->orderBy('name_en')->get()
            : $this->access->departmentsWith($user, $permission);

        return $departments
            ->map(fn (Department $department): array => ['id' => $department->getKey(), 'slug' => $department->slug, 'name' => $department->localized('name')])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, role: string|null, canLogin: bool, joinedAt: string|null}>
     */
    private function members(Team $team): array
    {
        $order = array_flip(array_column(TeamRole::cases(), 'value'));

        return $this->context->within($team->workspace, fn (): array => $team->members()->with('roles')->get()
            ->map(fn (User $member): array => [
                'id' => $member->getKey(),
                'name' => $member->name,
                'role' => $member->roles->first()?->name,
                'canLogin' => (bool) $member->can_login,
                'joinedAt' => $member->pivot->joined_at ? (string) $member->pivot->joined_at : null,
            ])
            ->sortBy(fn (array $member): string => sprintf('%02d %s', $order[$member['role']] ?? 99, $member['name']))
            ->values()
            ->all());
    }

    /**
     * @return list<array{id: int, description: string, causer: string|null, at: string|null}>
     */
    private function recentActivity(Team $team): array
    {
        return Activity::query()->forSubject($team)->with('causer')->latest('id')->limit(10)->get()
            ->map(fn (Activity $activity): array => [
                'id' => $activity->getKey(),
                'description' => $activity->description,
                'causer' => $activity->causer?->name,
                'at' => $activity->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
