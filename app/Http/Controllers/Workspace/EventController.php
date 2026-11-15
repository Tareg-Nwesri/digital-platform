<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Services\RegistrationService;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Teams\Services\TeamMemberService;
use App\Domain\Work\Enums\RobotStatus;
use App\Domain\Work\Models\Robot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\EventEntryRequest;
use App\Models\User;
use App\Support\Html\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * LLD 04 §9: the events the team can see, its entries, applying,
 * accepting invitations and withdrawing.
 */
class EventController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrations,
        private readonly TeamFeatures $features,
        private readonly TeamMemberService $members,
    ) {}

    public function index(Team $team): Response
    {
        $events = Event::query()->visibleToTeam($team)
            ->whereNotIn('status', [EventStatus::Archived])
            ->orderBy('starts_at')
            ->get();
        $entries = $team->registrations()->with('category')->latest('id')->get()->groupBy('event_id');

        return Inertia::render('workspace/events/Index', [
            'team' => ['slug' => $team->slug, 'name' => $team->name],
            'events' => $events->map(fn (Event $event): array => [
                ...$this->summary($event),
                'entries' => ($entries->get($event->getKey()) ?? collect())
                    ->map(fn (EventRegistration $registration): array => $this->entry($registration))
                    ->values()
                    ->all(),
            ])->all(),
        ]);
    }

    public function show(Request $request, Team $team, Event $event): Response
    {
        $this->assertVisible($team, $event);
        $startDate = $event->localStartDate();

        return Inertia::render('workspace/events/Show', [
            'team' => ['slug' => $team->slug, 'name' => $team->name],
            'event' => [
                ...$this->summary($event),
                'description' => RichText::clean($event->localized('description')),
                'rules' => RichText::clean($event->localized('rules')),
                'documents' => $event->documentList(fn (Media $media): string => $media->getUrl()),
                'categories' => $event->categories()->orderBy('position')->get()->map(fn (EventCategory $category): array => [
                    'id' => $category->getKey(),
                    'name' => (string) $category->localized('name'),
                    'ageMin' => $category->age_min,
                    'ageMax' => $category->age_max,
                    'membersMin' => $category->members_min,
                    'membersMax' => $category->members_max,
                    'robotsPerTeam' => $category->robots_per_team,
                    'maxWeightG' => $category->max_weight_g,
                    'maxLengthMm' => $category->max_length_mm,
                    'maxWidthMm' => $category->max_width_mm,
                    'maxHeightMm' => $category->max_height_mm,
                ])->all(),
            ],
            'entries' => $team->registrations()->where('event_id', $event->getKey())->with(['category', 'members', 'robots'])->latest('id')->get()
                ->map(fn (EventRegistration $registration): array => [
                    ...$this->entry($registration),
                    'members' => $registration->members->pluck('name')->all(),
                    'robots' => $registration->robots->pluck('name')->all(),
                ])->all(),
            'members' => $team->members()->orderBy('name_en')->get()->map(fn (User $member): array => [
                'id' => $member->getKey(),
                'name' => $member->name,
                'age' => $member->ageOn($startDate),
                'role' => $this->members->roleOf($team, $member)?->value,
            ])->all(),
            'robots' => $team->robots()->where('status', '!=', RobotStatus::Retired)->orderBy('name')->get()
                ->map(fn (Robot $robot): array => [
                    'id' => $robot->getKey(),
                    'name' => $robot->name,
                    'status' => $robot->status->value,
                    'weightG' => $robot->weight_g,
                    'lengthMm' => $robot->length_mm,
                    'widthMm' => $robot->width_mm,
                    'heightMm' => $robot->height_mm,
                ])->all(),
            'can' => [
                'apply' => $request->user()->can('events.apply') && $this->features->active($team, TeamFeature::EventsRegister),
            ],
        ]);
    }

    public function apply(EventEntryRequest $request, Team $team, Event $event): RedirectResponse
    {
        $this->assertVisible($team, $event);
        $category = EventCategory::query()->findOrFail($request->integer('category_id'));

        $this->registrations->apply($event, $category, $team, $request->memberIds(), $request->user(), $request->robotIds());

        return $this->done('applied');
    }

    public function accept(EventEntryRequest $request, Team $team, EventRegistration $registration): RedirectResponse
    {
        $this->assertOwn($team, $registration);
        $this->registrations->acceptInvite($registration, $request->memberIds(), $request->user(), $request->robotIds());

        return $this->done('invitation_accepted');
    }

    public function withdraw(Request $request, Team $team, EventRegistration $registration): RedirectResponse
    {
        $this->assertOwn($team, $registration);
        abort_unless($request->user()->can('events.apply'), 403);
        $this->registrations->withdraw($registration, $request->user());

        return $this->done('entry_withdrawn');
    }

    private function assertVisible(Team $team, Event $event): void
    {
        abort_unless(Event::query()->visibleToTeam($team)->whereKey($event->getKey())->exists(), 404);
    }

    private function assertOwn(Team $team, EventRegistration $registration): void
    {
        abort_unless($registration->team_id === $team->getKey(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Event $event): array
    {
        return [
            'slug' => $event->slug,
            'title' => (string) $event->localized('title'),
            'venue' => $event->venue,
            'city' => $event->city,
            'timezone' => $event->timezone,
            'status' => $event->status->value,
            'startsAt' => $event->starts_at->toIso8601String(),
            'endsAt' => $event->ends_at->toIso8601String(),
            'registrationClosesAt' => ($event->registration_closes_at ?? $event->starts_at)->toIso8601String(),
            'isOpen' => $event->isAcceptingApplications(),
            'cover' => $event->coverUrl('card'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(EventRegistration $registration): array
    {
        return [
            'id' => $registration->getKey(),
            'status' => $registration->status->value,
            'categoryId' => $registration->category_id,
            'category' => (string) $registration->category->localized('name'),
            'waitlistPosition' => $registration->waitlist_position,
            'decisionNote' => $registration->status === RegistrationStatus::Rejected ? $registration->decision_note : null,
            'canWithdraw' => $registration->status->isActive(),
        ];
    }

    private function done(string $key): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.'.$key)]);

        return back();
    }
}
