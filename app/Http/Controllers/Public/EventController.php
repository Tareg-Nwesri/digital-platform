<?php

namespace App\Http\Controllers\Public;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventVisibility;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Services\GuestRegistrationService;
use App\Domain\Teams\Services\TeamMemberService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\RegisterTeamRequest;
use App\Support\Html\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public event pages and the guest team registration form.
 */
class EventController extends Controller
{
    public function index(): Response
    {
        $events = Event::query()
            ->where('visibility', EventVisibility::Public)
            ->whereIn('status', [EventStatus::Open, EventStatus::Closed, EventStatus::Running])
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('public/events/Index', [
            'events' => $events->map(fn (Event $event): array => $this->summary($event))->all(),
        ]);
    }

    public function show(string $locale, Event $event): Response
    {
        return Inertia::render('public/events/Show', ['event' => $this->details($this->visible($event))]);
    }

    public function create(string $locale, Event $event): Response
    {
        return Inertia::render('public/events/Register', ['event' => $this->details($this->visible($event))]);
    }

    public function store(RegisterTeamRequest $request, string $locale, Event $event, GuestRegistrationService $registrations, TeamMemberService $members): RedirectResponse
    {
        $event = $this->visible($event);

        if (! $event->isAcceptingApplications()) {
            return back()->withErrors(['registration' => __('events.rules.window_closed')])->withInput();
        }

        $category = EventCategory::query()->findOrFail($request->integer('category_id'));
        $registration = $registrations->register($event, $category, $request->validated());

        $request->session()->put('public.registered', [
            'event' => $event->getKey(),
            'team' => $registration->team->name,
            'category' => $category->localized('name'),
            'email' => $members->captain($registration->team)?->email,
        ]);

        return to_route('public.events.registered', [$locale, $event]);
    }

    public function registered(Request $request, string $locale, Event $event): Response|RedirectResponse
    {
        $done = $request->session()->get('public.registered');

        if (! is_array($done) || $done['event'] !== $event->getKey()) {
            return to_route('public.events.register', [$locale, $event]);
        }

        return Inertia::render('public/events/Registered', [
            'event' => $this->summary($event),
            'teamName' => $done['team'],
            'categoryName' => $done['category'],
            'email' => $done['email'],
        ]);
    }

    private function visible(Event $event): Event
    {
        abort_unless($event->isVisibleToVisitors(), 404);

        return $event;
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
            'startsAt' => $event->starts_at->toIso8601String(),
            'endsAt' => $event->ends_at->toIso8601String(),
            'registrationOpensAt' => $event->registration_opens_at?->toIso8601String(),
            'registrationClosesAt' => ($event->registration_closes_at ?? $event->starts_at)->toIso8601String(),
            'isOpen' => $event->isAcceptingApplications(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function details(Event $event): array
    {
        return [
            ...$this->summary($event),
            'description' => RichText::clean((string) $event->localized('description')),
            'rules' => RichText::clean((string) $event->localized('rules')),
            'terms' => RichText::clean((string) $event->localized('terms')),
            'categories' => $event->categories()->orderBy('position')->get()->map(fn (EventCategory $category): array => [
                'id' => $category->getKey(),
                'name' => (string) $category->localized('name'),
                'ageMin' => $category->age_min,
                'ageMax' => $category->age_max,
                'membersMin' => $category->members_min,
                'membersMax' => $category->members_max,
                'robotsPerTeam' => $category->robots_per_team,
            ])->all(),
        ];
    }
}
