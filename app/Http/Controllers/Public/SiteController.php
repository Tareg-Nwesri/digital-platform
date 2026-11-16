<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Models\Post;
use App\Domain\Content\Services\ContactMessageService;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventVisibility;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Finance\Models\Sponsor;
use App\Domain\Organisation\Models\Department;
use App\Domain\Teams\Models\Team;
use App\Domain\Work\Models\Project;
use App\Domain\Work\Models\Robot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ContactRequest;
use App\Support\Html\RichText;
use App\Support\PublicMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 09: the public website. Every query goes through the models' public
 * scopes; nothing here relies on policies.
 */
class SiteController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('public/Home', [
            'meta' => PublicMeta::make(null, __('public_site.home_description')),
            'teams' => Team::query()->public()->latest('approved_at')->limit(4)->get()->map(fn (Team $team): array => self::teamCard($team))->all(),
            'projects' => Project::query()->public()->with('team')->latest('published_at')->limit(3)->get()->map(fn (Project $project): array => self::projectCard($project))->all(),
            'events' => Event::query()->public()->orderByDesc('starts_at')->limit(3)->get()->map(fn (Event $event): array => EventController::card($event))->all(),
            'news' => Post::query()->public()->latest('published_at')->limit(3)->get()->map(fn (Post $post): array => self::postCard($post))->all(),
            'sponsors' => Sponsor::query()
                ->where('is_active', true)
                ->where('public_consent', true)
                ->whereHas('media', fn ($query) => $query->where('collection_name', 'logo'))
                ->with('media')
                ->orderBy('name_en')
                ->get()
                ->map(fn (Sponsor $sponsor): array => [
                    'name' => (string) $sponsor->localized('name'),
                    'logo' => $sponsor->logoUrl(),
                    'website' => $sponsor->website,
                ])
                ->all(),
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('public/About', [
            'meta' => PublicMeta::make(__('public_site.about_heading'), __('public_about.description')),
            'departments' => Department::query()->where('is_public', true)->orderBy('name_en')->get()
                ->map(fn (Department $department): array => [
                    'name' => (string) $department->localized('name'),
                    'description' => $department->localized('description'),
                ])->all(),
        ]);
    }

    public function getInvolved(): Response
    {
        return Inertia::render('public/GetInvolved', ['meta' => PublicMeta::make(__('public_site.get_involved'), __('public_site.get_involved_copy'))]);
    }

    public function faq(): Response
    {
        return Inertia::render('public/Faq', ['meta' => PublicMeta::make(__('public_site.faq'), __('public_site.faq_intro'))]);
    }

    public function privacy(): Response
    {
        return Inertia::render('public/Privacy', ['meta' => PublicMeta::make(__('public_site.privacy'), __('public_site.privacy_intro'))]);
    }

    public function contact(): Response
    {
        return Inertia::render('public/Contact', ['meta' => PublicMeta::make(__('public_site.contact'), __('public_site.contact_copy'))]);
    }

    public function storeContact(ContactRequest $request, string $locale, ContactMessageService $messages): RedirectResponse
    {
        $messages->receive([...$request->safe()->only(['name', 'email', 'subject', 'message']), 'locale' => $locale, 'ip' => $request->ip()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('public.contact_sent')]);

        return back();
    }

    public function teams(): Response
    {
        return Inertia::render('public/teams/Index', [
            'meta' => PublicMeta::make(__('public_site.teams'), __('public_site.join_team_copy')),
            'teams' => Team::query()->public()->orderBy('name')->paginate(12)->through(fn (Team $team): array => self::teamCard($team)),
        ]);
    }

    public function team(string $locale, string $slug): Response
    {
        $team = Team::query()->public()->where('slug', $slug)->firstOrFail();
        $description = $team->localized('description');

        return Inertia::render('public/teams/Show', [
            'meta' => PublicMeta::make($team->name, $description ?: __('public_site.join_team_copy'), $team->logoUrl()),
            'team' => [
                ...self::teamCard($team),
                'focus' => $team->localized('focus'),
                'officialUrl' => $team->official_url,
            ],
            'projects' => $team->projects()->published()->latest('published_at')->get()->map(fn (Project $project): array => self::projectCard($project, $team))->all(),
            'robots' => $team->robots()->where('is_public', true)->orderBy('name')->get()->map(fn (Robot $robot): array => self::robotCard($robot))->all(),
            'results' => EventRegistration::query()
                ->where('team_id', $team->getKey())
                ->where('status', RegistrationStatus::Approved)
                ->whereHas('event', fn ($query) => $query->public()->whereNotNull('results_published_at'))
                ->whereHas('result')
                ->with(['event', 'category', 'result'])
                ->get()
                ->sortByDesc(fn (EventRegistration $registration) => $registration->event->starts_at)
                ->map(fn (EventRegistration $registration): array => [
                    'event' => (string) $registration->event->localized('title'),
                    'eventSlug' => $registration->event->slug,
                    'category' => (string) $registration->category->localized('name'),
                    'placement' => $registration->result->placement,
                    'award' => $registration->result->localized('award'),
                    'date' => $registration->event->starts_at->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function projects(): Response
    {
        return Inertia::render('public/projects/Index', [
            'meta' => PublicMeta::make(__('public_site.projects'), __('public_site.projects_copy')),
            'projects' => Project::query()->public()->with('team')->latest('published_at')->paginate(12)
                ->through(fn (Project $project): array => self::projectCard($project)),
        ]);
    }

    public function project(string $locale, string $slug): Response
    {
        $project = Project::query()->public()->where('slug', $slug)->with('team')->firstOrFail();

        return Inertia::render('public/projects/Show', [
            'meta' => PublicMeta::make($project->localized('title'), $project->localized('excerpt')),
            'project' => [
                ...self::projectCard($project),
                'body' => RichText::clean($project->localized('body')),
                'robots' => $project->robots()->where('is_public', true)->orderBy('name')->get()->map(fn (Robot $robot): array => self::robotCard($robot))->all(),
            ],
        ]);
    }

    public function news(): Response
    {
        return Inertia::render('public/news/Index', [
            'meta' => PublicMeta::make(__('public_site.news'), __('public_site.news_intro')),
            'posts' => Post::query()->public()->latest('published_at')->paginate(12)->through(fn (Post $post): array => self::postCard($post)),
        ]);
    }

    public function article(string $locale, string $slug): Response
    {
        $post = Post::query()->public()->where('slug', $slug)->firstOrFail();

        return Inertia::render('public/news/Show', [
            'meta' => PublicMeta::make($post->localized('title'), $post->localized('excerpt')),
            'post' => [...self::postCard($post), 'body' => RichText::clean($post->localized('body'))],
        ]);
    }

    public function sitemap(): HttpResponse
    {
        $paths = ['', '/about', '/teams', '/projects', '/events', '/news', '/get-involved', '/faq', '/contact', '/privacy'];

        Team::query()->public()->pluck('slug')->each(function (string $slug) use (&$paths): void {
            $paths[] = '/teams/'.$slug;
        });
        Project::query()->public()->pluck('slug')->each(function (string $slug) use (&$paths): void {
            $paths[] = '/projects/'.$slug;
        });
        Post::query()->public()->pluck('slug')->each(function (string $slug) use (&$paths): void {
            $paths[] = '/news/'.$slug;
        });
        Event::query()->where('visibility', EventVisibility::Public)->where('status', '!=', EventStatus::Draft)->pluck('slug')
            ->each(function (string $slug) use (&$paths): void {
                $paths[] = '/events/'.$slug;
            });

        $urls = collect($paths)->flatMap(fn (string $path): array => [url('/en'.$path), url('/ar'.$path)]);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$urls->map(fn (string $url): string => '  <url><loc>'.e($url).'</loc></url>')->implode("\n")
            ."\n</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * @return array{slug: string, name: string, location: string|null, description: string|null, logo: string|null}
     */
    public static function teamCard(Team $team): array
    {
        return [
            'slug' => $team->slug,
            'name' => $team->name,
            'location' => $team->location,
            'description' => $team->localized('description'),
            'logo' => $team->logoUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function projectCard(Project $project, ?Team $team = null): array
    {
        $team ??= $project->team;

        return [
            'slug' => $project->slug,
            'title' => (string) $project->localized('title'),
            'excerpt' => $project->localized('excerpt'),
            'publishedAt' => $project->published_at?->toIso8601String(),
            'team' => ['slug' => $team->slug, 'name' => $team->name],
        ];
    }

    /**
     * @return array{name: string, status: string, description: string|null}
     */
    private static function robotCard(Robot $robot): array
    {
        return [
            'name' => $robot->name,
            'status' => $robot->status->value,
            'description' => $robot->localized('description'),
        ];
    }

    /**
     * @return array{slug: string, title: string, excerpt: string|null, publishedAt: string|null}
     */
    private static function postCard(Post $post): array
    {
        return [
            'slug' => $post->slug,
            'title' => (string) $post->localized('title'),
            'excerpt' => $post->localized('excerpt'),
            'publishedAt' => $post->published_at?->toIso8601String(),
        ];
    }
}
