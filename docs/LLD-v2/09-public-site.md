# 09 · Public site: pages, SEO, sitemap, caching

SRS: FR-PUB-01…13, CI-02, CI-04, NFR-PERF-01 · HLD: 7.7, 10.4

## 1. Routing

```php
Route::get('/', RootRedirectController::class)->name('root');            // cookie locale, else Accept-Language ar → /ar, else /en
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::prefix('{locale}')->whereIn('locale', ['en', 'ar'])
    ->middleware(['public.locale', 'public.cache-headers'])
    ->name('public.')->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::get('/about', AboutController::class)->name('about');
        Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
        Route::get('/teams/{team:slug}', [TeamController::class, 'show'])->name('teams.show');
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project:slug}', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('/robots/{robot:slug}', [RobotController::class, 'show'])->name('robots.show');
        Route::get('/events', [EventController::class, 'index'])->name('events.index');
        Route::get('/events/{event:slug}', [EventController::class, 'show'])->name('events.show');
        Route::get('/events/{event:slug}/apply', ApplyRedirectController::class)->name('events.apply');
        Route::get('/news', [PostController::class, 'index'])->name('news.index');
        Route::get('/news/{post:slug}', [PostController::class, 'show'])->name('news.show');
        Route::get('/sponsors', SponsorController::class)->name('sponsors');
        Route::get('/get-involved', GetInvolvedController::class)->name('get-involved');
        Route::get('/faq', StaticPageController::class)->defaults('page', 'faq')->name('faq');
        Route::get('/privacy', StaticPageController::class)->defaults('page', 'privacy')->name('privacy');
        Route::get('/contact', [ContactController::class, 'create'])->name('contact');
        Route::post('/contact', [ContactController::class, 'store'])
            ->middleware(['throttle:contact', ProtectAgainstSpam::class])->name('contact.store');
    });
```

- **Route model binding** uses each model's public scope: `resolveRouteBindingQuery` is overridden in the public controllers through `Route::bind('publicTeam', …)`. Each public controller uses `Team::public()->where('slug', $slug)->firstOrFail()`, so drafts and private records return **404**.
- **`ApplyRedirectController`:**
  - not signed in: redirect to login with `intended` set to this URL;
  - captain or coach of one or more teams: redirect to `workspace.events.show` for the first such team, or to a team picker when there are several;
  - otherwise: redirect to `public.contact`.

## 2. Controllers and props

All public controllers return `Inertia::render('public/<Page>', props)` together with `->withViewData(['meta' => $meta])`, so the root template can print the tags (see §3).

| Page | Query (all through `scopePublic`) | Props |
| --- | --- | --- |
| `public/Home` | featured items per slot (or newest when a slot is empty); upcoming 3 events; latest 3 posts; public sponsors (logos) | `sections: {key, items}[]` ordered by the `public.home_sections` setting |
| `public/About` | `Department::public()` | `departments: {name, description}[]` + static copy |
| `public/Teams/Index` | `Team::public()` + `q` (LiteralLike on name) + `city`, 12 per page, ordered by name | `teams` (`PublicTeamCardResource`: name, slug, logo, city, excerpt), `filters`, `cities[]` |
| `public/Teams/Show` | the team; published projects (12 per page); public robots; registrations with status Approved for public or result-published events, plus results | `team`, `projects`, `robots`, `history: {event, category, placement?, award?}[]` |
| `public/Projects/Index`, `Show` | `Project::public()`, featured first | `project`, `team`, `robots` (public only), gallery URLs |
| `public/Robots/Show` | `Robot::public()` | `robot` (photos, status, specs, team link). **No BOM or cost** (SRS C-06) |
| `public/Events/Index` | `Event::public()`, grouped into upcoming, in progress and past | `events` (title, dates, city, status label, banner) |
| `public/Events/Show` | the event and its categories; approved registrations per category (team name, logo and slug if public, otherwise the name only); results when published | `event`, `categories: {name, rules, teams[], results[]?}[]`, `canApplyHint` (open and public) |
| `public/News/Index`, `Show` | `Post::public()` | posts |
| `public/Sponsors` | `Sponsor::public()` with `supported: {teams, events, projects}` from sponsorship links. **No amounts** | |
| `public/GetInvolved`, `Faq`, `Privacy` | static (translations, SRS C-08) | |
| `public/Contact` | | the form; shared `honeypot` and `turnstile` |

Resources never expose: member names, ages, birthdates, e-mails, BOMs, prices, budgets or private media.

## 3. SEO meta without SSR (CI-04)

### `SeoMeta` value object
`SeoMeta::make(title, description, image?, type = 'website')`:
- builds `canonical`, `alternates {en, ar, x-default}` from the current route with the locale swapped;
- builds `og:*`, plus `twitter:card` = summary_large_image.

Each public controller calls `SeoMeta::for($model)` or `SeoMeta::page('about')`.

### Root template `resources/views/app.blade.php` (the only Blade file)
```blade
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  @isset($meta)
    <title inertia>{{ $meta->title }}</title>
    <meta inertia="description" name="description" content="{{ $meta->description }}">
    <link rel="canonical" href="{{ $meta->canonical }}">
    @foreach($meta->alternates as $hreflang => $href)<link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">@endforeach
    <meta property="og:title" content="{{ $meta->title }}"> … og:description, og:url, og:image, og:type, og:locale
  @else
    <title inertia>{{ config('app.name') }}</title>
  @endisset
  <link rel="preconnect" href="https://fonts.googleapis.com"> … fonts (Noto Kufi Arabic only when $locale = ar)
  @vite(['resources/js/app.ts'])
  @inertiaHead
</head>
<body class="font-sans antialiased">@inertia</body>
</html>
```
- `$locale` and `$dir` are shared through `View::share` in `SetLocale`.
- On client-side navigation, `PublicLayout.vue` renders `<Head>` from the same `meta` prop. The `inertia` attributes stop duplicate tags.

### Sitemap
- `SitemapController` streams XML.
- For each locale it lists: the static routes, then `Team::public()`, `Project::public()`, `Robot::public()`, `Event::public()` and `Post::public()`, each with `lastmod = updated_at` and `xhtml:link` alternates.
- The XML is cached for 1 hour under the version key (§4).

## 4. Caching
- **`PublicCache`:** `remember(string $module, string $key, Closure $fn, int $ttl = 300)`.
  - The key is `public:{module}:v{version}:{locale}:{key}`.
  - `version` comes from `Cache::get("public:version:{module}")`.
  - `bump($module)` increments the version.
- **Modules and their sources:**
  - `teams`: Team saved, status, feature or override changes;
  - `work`: Project and Robot events;
  - `events`: Event, Registration and Result;
  - `content`: Post and Featured;
  - `sponsors`: Sponsor.
- **Invalidation:** listeners call `PublicCache::bump()`. The home page depends on every module, so its key includes all the versions.
- **`public.cache-headers` middleware:**
  - guests get `Cache-Control: public, max-age=60`;
  - signed-in users get `private`;
  - Inertia JSON responses get the `Vary: X-Inertia` header.

## 5. Locale middleware
`SetPublicLocale`:
1. Reads `{locale}`, otherwise returns 404.
2. Sets `App::setLocale`, and the queued cookie `locale` for 1 year.
3. It does **not** change a signed-in user's `users.locale`. The account language is changed only from the account menu.

The JSON translations are loaded by `laravel-vue-i18n` from `lang/{locale}.json` and `lang/{locale}/*.php`, through the Vite plugin.

## 6. Tests (rewritten from v1 `PublicSiteTest`, `PublicFoundationTest`)
- `root redirects to the cookie locale, else the platform default (Arabic, RTL first)`
- `invalid locale returns 404`
- `drafts, private, suspended and archived records return 404 on every public route` (dataset over the models)
- `team pages never expose rosters, ages or contact data` (`assertInertia` where `props.team` is missing those keys)
- `robot pages never include bom or prices`
- `invite-only events appear only after results are published`
- `event pages list only approved registrations; private teams show the name without a link`
- `first HTML response contains title, description, canonical, hreflang and og tags without javascript`
- `sitemap lists only public records in both locales with alternates`
- `contact is protected by honeypot, turnstile and the 3 per minute throttle`
- `saving a public model bumps the cache version so the next request is fresh`
- `numbers and dates render with western digits on Arabic pages`
