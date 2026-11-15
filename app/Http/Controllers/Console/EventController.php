<?php

namespace App\Http\Controllers\Console;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventVisibility;
use App\Domain\Events\Enums\RegistrationSource;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Services\EventService;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentAccess;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamMemberService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\EventRequest;
use App\Models\User;
use App\Support\Tables\TableQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * LLD 06 §7–9: the events area of the organisation console.
 */
class EventController extends Controller
{
    public function __construct(
        private readonly DepartmentAccess $access,
        private readonly EventService $events,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Event::class);

        $user = $request->user();

        $events = TableQuery::for($this->visibleEvents($user)->with('department')->withCount([
            'registrations as approved_count' => fn ($query) => $query->where('status', RegistrationStatus::Approved),
            'registrations as pending_count' => fn ($query) => $query->where('status', RegistrationStatus::Pending),
            'registrations as waitlisted_count' => fn ($query) => $query->where('status', RegistrationStatus::Waitlisted),
        ]), $request)
            ->searchable(['title_en', 'title_ar', 'slug', 'city'])
            ->sortable(['title_en', 'starts_at', 'created_at'], default: 'starts_at', direction: 'desc')
            ->filters([
                'status' => fn (Builder $query, mixed $value) => $query->where('status', (string) $value),
                'department' => fn (Builder $query, mixed $value) => $query->whereHas('department', fn ($department) => $department->where('slug', (string) $value)),
            ])
            ->paginate(fn (Event $event): array => [
                'id' => $event->getKey(),
                'slug' => $event->slug,
                'title' => $event->localized('title'),
                'department' => $event->department->localized('name'),
                'status' => $event->status->value,
                'visibility' => $event->visibility->value,
                'startsAt' => $event->starts_at->toIso8601String(),
                'city' => $event->city,
                'capacity' => $event->capacity,
                'approved' => (int) $event->approved_count,
                'pending' => (int) $event->pending_count,
                'waitlisted' => (int) $event->waitlisted_count,
            ]);

        return Inertia::render('console/events/Index', [
            'events' => $events,
            'filters' => [
                'statuses' => array_column(EventStatus::cases(), 'value'),
                'departments' => $this->departmentOptions($user, 'events.view'),
            ],
            'editableDepartments' => $this->departmentOptions($user, 'events.edit'),
            'visibilities' => array_column(EventVisibility::cases(), 'value'),
            'can' => ['create' => $user->can('create', Event::class)],
        ]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $event = $this->events->create($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.created')]);

        return to_route('console.events.show', $event->slug);
    }

    public function show(Request $request, Event $event): Response
    {
        $this->authorize('view', $event);

        $user = $request->user();
        $event->loadMissing('department');

        return Inertia::render('console/events/Show', [
            'event' => $this->eventProps($event),
            'transitions' => array_map(fn (EventStatus $status): string => $status->value, Event::allowedTransitions()[$event->status->value] ?? []),
            'categories' => $event->categories()->withCount([
                'registrations as approved_count' => fn ($query) => $query->where('status', RegistrationStatus::Approved),
            ])->get()->map(fn (EventCategory $category): array => [
                'id' => $category->getKey(),
                'name' => $category->localized('name'),
                'nameEn' => $category->name_en,
                'nameAr' => $category->name_ar,
                'ageMin' => $category->age_min,
                'ageMax' => $category->age_max,
                'membersMin' => $category->members_min,
                'membersMax' => $category->members_max,
                'robotsPerTeam' => $category->robots_per_team,
                'capacity' => $category->capacity,
                'approved' => (int) $category->approved_count,
            ])->all(),
            'registrations' => $this->registrations($event),
            'invitations' => $event->invitedTeams()->orderBy('name')->get()
                ->map(fn (Team $team): array => ['id' => $team->getKey(), 'name' => $team->name, 'slug' => $team->slug])
                ->all(),
            'can' => [
                'edit' => $user->can('update', $event),
                'review' => $user->can('review', $event),
                'delete' => $user->can('delete', $event) && $event->status === EventStatus::Draft,
            ],
            'editableDepartments' => fn (): array => $this->departmentOptions($user, 'events.edit'),
            'visibilities' => array_column(EventVisibility::cases(), 'value'),
            'teamOptions' => fn (): array => $user->can('update', $event) ? $this->teamOptions() : [],
            'teamMembers' => Inertia::optional(fn (): array => $this->teamMembers($request)),
            'activity' => fn (): array => $this->recentActivity($event),
        ]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $this->events->update($event, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.updated')]);

        return to_route('console.events.show', $event->slug);
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $this->events->delete($event, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.deleted')]);

        return to_route('console.events.index');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Event>
     */
    private function visibleEvents(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = Event::query();

        if ($user->isSuperAdmin()) {
            return $query;
        }

        $ids = array_unique([
            ...$this->access->departmentIdsWith($user, 'events.view'),
            ...$this->access->departmentIdsWith($user, 'events.edit'),
        ]);

        return $query->forDepartments($ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventProps(Event $event): array
    {
        return [
            'id' => $event->getKey(),
            'slug' => $event->slug,
            'title' => $event->localized('title'),
            'titleEn' => $event->title_en,
            'titleAr' => $event->title_ar,
            'descriptionEn' => $event->description_en,
            'descriptionAr' => $event->description_ar,
            'venue' => $event->venue,
            'city' => $event->city,
            'timezone' => $event->timezone,
            'startsAt' => $event->starts_at->toIso8601String(),
            'endsAt' => $event->ends_at->toIso8601String(),
            'registrationOpensAt' => $event->registration_opens_at?->toIso8601String(),
            'registrationClosesAt' => $event->registration_closes_at?->toIso8601String(),
            'status' => $event->status->value,
            'visibility' => $event->visibility->value,
            'capacity' => $event->capacity,
            'approved' => $event->approvedCount(),
            'resultsPublishedAt' => $event->results_published_at?->toIso8601String(),
            'cover' => $event->coverUrl(),
            'documents' => $event->documentList(fn (Media $media): string => $media->getUrl()),
            'department' => [
                'id' => $event->department->getKey(),
                'slug' => $event->department->slug,
                'name' => $event->department->localized('name'),
            ],
        ];
    }

    /**
     * Who to call about a guest entry: the captain the visitor named.
     *
     * @return array{name: string, email: string|null, phone: string|null}|null
     */
    private function contact(EventRegistration $registration): ?array
    {
        if ($registration->source !== RegistrationSource::Public) {
            return null;
        }

        $captain = app(TeamMemberService::class)->captain($registration->team);

        return $captain === null ? null : ['name' => $captain->name, 'email' => $captain->email, 'phone' => $captain->phone];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function registrations(Event $event): array
    {
        $order = array_flip(array_column(RegistrationStatus::cases(), 'value'));

        return $event->registrations()
            ->with(['team', 'category', 'members', 'result', 'reviewer', 'robots'])
            ->get()
            ->sortBy(fn (EventRegistration $registration): string => sprintf('%02d %05d %s', $order[$registration->status->value], $registration->waitlist_position ?? 0, $registration->team->name))
            ->map(fn (EventRegistration $registration): array => [
                'id' => $registration->getKey(),
                'status' => $registration->status->value,
                'source' => $registration->source->value,
                'team' => ['id' => $registration->team->getKey(), 'name' => $registration->team->name, 'slug' => $registration->team->slug],
                'city' => $registration->team->location,
                'contact' => $this->contact($registration),
                'robots' => $registration->robots->pluck('name')->all(),
                'category' => ['id' => $registration->category->getKey(), 'name' => $registration->category->localized('name')],
                'members' => $registration->members_snapshot
                    ?? $registration->members->map(fn (User $member): array => ['user_id' => $member->getKey(), 'name' => $member->name, 'age' => $member->ageOn($event->localStartDate()), 'role' => null])->all(),
                'waitlistPosition' => $registration->waitlist_position,
                'decisionNote' => $registration->decision_note,
                'overrideReason' => $registration->override_reason,
                'overrideViolations' => $registration->override_violations ?? [],
                'reviewedBy' => $registration->reviewer?->name,
                'reviewedAt' => $registration->reviewed_at?->toIso8601String(),
                'createdAt' => $registration->created_at?->toIso8601String(),
                'result' => $registration->result === null ? null : [
                    'placement' => $registration->result->placement,
                    'score' => $registration->result->score,
                    'awardEn' => $registration->result->award_en,
                    'awardAr' => $registration->result->award_ar,
                    'notes' => $registration->result->notes,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function teamOptions(): array
    {
        return Team::query()->where('status', TeamStatus::Active)->orderBy('name')->get(['id', 'name'])
            ->map(fn (Team $team): array => ['id' => $team->getKey(), 'name' => $team->name])
            ->all();
    }

    /**
     * Members of the team picked in the add-team form, loaded on demand.
     *
     * @return list<array{id: int, name: string, role: string|null, birthdate: string|null}>
     */
    private function teamMembers(Request $request): array
    {
        $team = Team::query()->find($request->integer('team'));

        if ($team === null) {
            return [];
        }

        $roles = app(TeamMemberService::class);

        return $team->members()->orderBy('name_en')->get()
            ->map(fn (User $member): array => [
                'id' => $member->getKey(),
                'name' => $member->name,
                'role' => $roles->roleOf($team, $member)?->value,
                'birthdate' => $member->birthdate?->toDateString(),
            ])
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
     * @return list<array{id: int, description: string, event: string|null, causer: string|null, at: string|null}>
     */
    private function recentActivity(Event $event): array
    {
        $registrationIds = $event->registrations()->pluck('id');

        return Activity::query()
            ->where(fn ($query) => $query
                ->where(fn ($subject) => $subject->where('subject_type', $event->getMorphClass())->where('subject_id', $event->getKey()))
                ->orWhere(fn ($subject) => $subject->where('subject_type', (new EventRegistration)->getMorphClass())->whereIn('subject_id', $registrationIds)))
            ->with('causer')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (Activity $activity): array => [
                'id' => $activity->getKey(),
                'description' => $activity->description,
                'event' => $activity->event,
                'causer' => $activity->causer?->name,
                'at' => $activity->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
