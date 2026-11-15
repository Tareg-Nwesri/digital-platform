<?php

namespace App\Http\Controllers\Public;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventVisibility;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Services\GuestRegistrationService;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamMemberService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\RegisterTeamRequest;
use App\Support\Html\RichText;
use App\Support\PublicMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Public event pages and the guest team registration form.
 */
class EventController extends Controller
{
    public function index(): Response
    {
        $events = Event::query()
            ->where('visibility', EventVisibility::Public)
            ->whereNotIn('status', [EventStatus::Draft, EventStatus::Archived])
            ->get();
        $upcoming = $events->filter(fn (Event $event): bool => ! $event->ends_at->isPast())->sortBy('starts_at');
        $past = $events->filter(fn (Event $event): bool => $event->ends_at->isPast())->sortByDesc('starts_at');

        return Inertia::render('public/events/Index', [
            'meta' => PublicMeta::make(__('public_site.events'), __('public_site.events_intro')),
            'events' => $upcoming->concat($past)->map(fn (Event $event): array => self::card($event))->values()->all(),
        ]);
    }

    public function show(string $locale, Event $event): Response
    {
        $event = $this->visible($event);

        return Inertia::render('public/events/Show', [
            'meta' => PublicMeta::make($event->localized('title'), $event->localized('description'), $event->coverUrl()),
            'event' => [
                ...$this->details($event),
                'when' => self::when($event),
                'teams' => $this->teams($event),
                'results' => $this->results($event),
            ],
        ]);
    }

    public function create(string $locale, Event $event): Response
    {
        $event = $this->visible($event);

        return Inertia::render('public/events/Register', [
            'meta' => PublicMeta::make(__('Register your team').' · '.$event->localized('title'), $event->localized('description'), $event->coverUrl()),
            'event' => $this->details($event),
        ]);
    }

    /**
     * An event card for lists: upcoming, in progress or past.
     *
     * @return array<string, mixed>
     */
    public static function card(Event $event): array
    {
        return [
            'slug' => $event->slug,
            'title' => (string) $event->localized('title'),
            'excerpt' => Str::limit(trim(strip_tags((string) $event->localized('description'))), 180),
            'venue' => $event->venue,
            'city' => $event->city,
            'timezone' => $event->timezone,
            'startsAt' => $event->starts_at->toIso8601String(),
            'endsAt' => $event->ends_at->toIso8601String(),
            'registrationClosesAt' => ($event->registration_closes_at ?? $event->starts_at)->toIso8601String(),
            'isOpen' => $event->isAcceptingApplications(),
            'when' => self::when($event),
            'cover' => $event->coverUrl('card'),
        ];
    }

    private static function when(Event $event): string
    {
        return match (true) {
            $event->ends_at->isPast() => 'past',
            $event->starts_at->isFuture() => 'upcoming',
            default => 'current',
        };
    }

    /**
     * Approved teams; private teams are named but not linked.
     *
     * @return list<array{name: string, slug: string|null, category: string}>
     */
    private function teams(Event $event): array
    {
        $publicIds = Team::query()->public()->pluck('id')->all();

        return $event->registrations()->where('status', RegistrationStatus::Approved)->with(['team', 'category'])->get()
            ->sortBy(fn (EventRegistration $registration): string => sprintf('%05d %s', $registration->category->position, $registration->team->name))
            ->map(fn (EventRegistration $registration): array => [
                'name' => $registration->team->name,
                'slug' => in_array($registration->team_id, $publicIds, true) ? $registration->team->slug : null,
                'category' => (string) $registration->category->localized('name'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{team: string, category: string, placement: int|null, score: string|null, award: string|null}>
     */
    private function results(Event $event): array
    {
        if ($event->results_published_at === null) {
            return [];
        }

        return $event->registrations()->where('status', RegistrationStatus::Approved)->whereHas('result')->with(['team', 'category', 'result'])->get()
            ->sortBy(fn (EventRegistration $registration): string => sprintf('%05d %05d', $registration->category->position, $registration->result->placement ?? 99999))
            ->map(fn (EventRegistration $registration): array => [
                'team' => $registration->team->name,
                'category' => (string) $registration->category->localized('name'),
                'placement' => $registration->result->placement,
                'score' => $registration->result->score,
                'award' => $registration->result->localized('award'),
            ])
            ->values()
            ->all();
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
            'meta' => PublicMeta::make(__('Registration received')),
            'event' => $this->summary($event),
            'teamName' => $done['team'],
            'categoryName' => $done['category'],
            'email' => $done['email'],
        ]);
    }

    public function document(string $locale, Event $event, int $media): Media
    {
        return $this->visible($event)->media()->where('collection_name', 'documents')->findOrFail($media);
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
            'cover' => $event->coverUrl('card'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function details(Event $event): array
    {
        return [
            ...$this->summary($event),
            'cover' => $event->coverUrl(),
            'documents' => $event->documentList(fn (Media $media): string => route('public.events.documents', [app()->getLocale(), $event->slug, $media->getKey()])),
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
