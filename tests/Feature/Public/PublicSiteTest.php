<?php

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Models\ContactMessage;
use App\Domain\Content\Models\Post;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\RegistrationSource;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Models\EventResult;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Work\Enums\ProjectStatus;
use App\Domain\Work\Enums\RobotStatus;
use App\Domain\Work\Models\Project;
use App\Domain\Work\Models\Robot;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 09: the public website. Only public and published data appears;
 * drafts and private records return 404; every page carries meta tags in
 * the first HTML response.
 */

beforeEach(function () {
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);
    Carbon::setTestNow('2027-02-01 10:00:00');

    $competition = Tier::query()->where('slug', 'competition')->value('id');
    $this->department = app(DepartmentService::class)->create(['name_en' => 'Media', 'name_ar' => 'الإعلام']);
    $this->team = Team::factory()->create(['slug' => 'falcons', 'name' => 'Falcons', 'tier_id' => $competition, 'is_public' => true, 'location' => 'Tripoli', 'description_en' => 'School team', 'description_ar' => 'فريق مدرسي']);
    $this->hidden = Team::factory()->create(['slug' => 'secret', 'name' => 'Secret team', 'tier_id' => $competition, 'is_public' => false]);

    $this->project = Project::query()->create(['team_id' => $this->team->id, 'slug' => 'line-follower', 'title_en' => 'Line follower', 'title_ar' => 'متتبع الخط', 'excerpt_en' => 'Follows lines', 'body_en' => '<p>Built in <strong>2026</strong>.</p><script>alert(1)</script>', 'status' => ProjectStatus::Published, 'published_at' => now()->subDay()]);
    Project::query()->create(['team_id' => $this->team->id, 'slug' => 'draft-bot', 'title_en' => 'Draft bot', 'status' => ProjectStatus::Draft]);
    Project::query()->create(['team_id' => $this->hidden->id, 'slug' => 'hidden-project', 'title_en' => 'Hidden project', 'status' => ProjectStatus::Published, 'published_at' => now()->subDay()]);
    Robot::query()->create(['team_id' => $this->team->id, 'project_id' => $this->project->id, 'name' => 'Falcon One', 'slug' => 'falcon-one', 'status' => RobotStatus::Ready, 'is_public' => true]);
    Robot::query()->create(['team_id' => $this->team->id, 'name' => 'Private bot', 'slug' => 'private-bot', 'status' => RobotStatus::Ready, 'is_public' => false]);

    $this->post = Post::query()->create(['department_id' => $this->department->id, 'slug' => 'season-opens', 'title_en' => 'Season opens', 'title_ar' => 'افتتاح الموسم', 'excerpt_en' => 'Registration is open', 'body_en' => '<p>Welcome.</p>', 'status' => PostStatus::Published, 'published_at' => now()->subHour()]);
    Post::query()->create(['department_id' => $this->department->id, 'slug' => 'draft-post', 'title_en' => 'Draft post', 'status' => PostStatus::Draft]);
    Post::query()->create(['department_id' => $this->department->id, 'slug' => 'scheduled', 'title_en' => 'Scheduled', 'status' => PostStatus::Published, 'published_at' => now()->addDay()]);

    $this->event = Event::factory()->for($this->department)->create(['slug' => 'robot-cup', 'title_en' => 'Robot Cup', 'status' => EventStatus::Finished, 'starts_at' => now()->subDays(10), 'ends_at' => now()->subDays(10)->addHours(8), 'results_published_at' => now()->subDays(5)]);
    $category = EventCategory::factory()->for($this->event)->create(['name_en' => 'Junior']);
    $registration = EventRegistration::query()->create(['event_id' => $this->event->id, 'category_id' => $category->id, 'team_id' => $this->team->id, 'source' => RegistrationSource::Applied, 'status' => RegistrationStatus::Approved]);
    EventResult::query()->create(['registration_id' => $registration->id, 'placement' => 1, 'award_en' => 'Champions', 'award_ar' => 'الأبطال']);
    EventRegistration::query()->create(['event_id' => $this->event->id, 'category_id' => $category->id, 'team_id' => $this->hidden->id, 'source' => RegistrationSource::Applied, 'status' => RegistrationStatus::Pending]);
});

it('sends the root to the home page in the visitor’s language', function () {
    $this->get('/')->assertRedirect('/en');
    $this->withUnencryptedCookie('locale', 'ar')->get('/')->assertRedirect('/ar');
});

it('renders the home page with public teams, projects, events and news', function () {
    $this->get('/ar')
        ->assertOk()
        ->assertSee('name="description"', false)
        ->assertSee('hreflang="en"', false)
        ->assertSee('rel="canonical" href="'.url('/ar').'"', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Home')
            ->where('locale', 'ar')
            ->has('teams', 1)
            ->where('teams.0.name', 'Falcons')
            ->where('teams.0.description', 'فريق مدرسي')
            ->has('projects', 1)
            ->where('projects.0.title', 'متتبع الخط')
            ->has('news', 1)
            ->where('news.0.title', 'افتتاح الموسم')
            ->has('events', 1)
            ->has('meta.title'));
});

it('lists and shows public teams only, with their published work', function () {
    $this->get('/en/teams')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/teams/Index')->has('teams.data', 1)->where('teams.data.0.slug', 'falcons'));

    $this->get('/en/teams/falcons')
        ->assertOk()
        ->assertSee('<title data-server-meta>Falcons', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/teams/Show')
            ->where('team.name', 'Falcons')
            ->has('projects', 1)
            ->has('robots', 1)
            ->where('robots.0.name', 'Falcon One')
            ->has('results', 1)
            ->where('results.0.award', 'Champions'));

    $this->get('/en/teams/secret')->assertNotFound();
});

it('lists and shows published projects of public teams, with clean HTML', function () {
    $this->get('/en/projects')
        ->assertInertia(fn (Assert $page) => $page->component('public/projects/Index')->has('projects.data', 1));

    $this->get('/en/projects/line-follower')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/projects/Show')
            ->where('project.team.slug', 'falcons')
            ->where('project.body', fn (string $body) => str_contains($body, '<strong>2026</strong>') && ! str_contains($body, 'script'))
            ->has('project.robots', 1));

    $this->get('/en/projects/draft-bot')->assertNotFound();
    $this->get('/en/projects/hidden-project')->assertNotFound();
});

it('lists and shows published news only', function () {
    $this->get('/en/news')
        ->assertInertia(fn (Assert $page) => $page->component('public/news/Index')->has('posts.data', 1));

    $this->get('/ar/news/season-opens')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/news/Show')->where('post.title', 'افتتاح الموسم'));

    $this->get('/en/news/draft-post')->assertNotFound();
    $this->get('/en/news/scheduled')->assertNotFound();
});

it('shows past events with the approved teams and published results', function () {
    $this->get('/en/events')
        ->assertInertia(fn (Assert $page) => $page->component('public/events/Index')->has('events', 1)->where('events.0.when', 'past'));

    $this->get('/en/events/robot-cup')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/events/Show')
            ->has('event.teams', 1)
            ->where('event.teams.0.name', 'Falcons')
            ->where('event.teams.0.slug', 'falcons')
            ->has('event.results', 1)
            ->where('event.results.0.placement', 1));
});

it('hides results until they are published', function () {
    $this->event->update(['results_published_at' => null]);

    $this->get('/en/events/robot-cup')
        ->assertInertia(fn (Assert $page) => $page->where('event.results', []));
});

it('serves the about, get involved, FAQ and privacy pages', function (string $path, string $component) {
    $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component)->has('meta.title'));
})->with([
    ['/en/about', 'public/About'],
    ['/ar/get-involved', 'public/GetInvolved'],
    ['/en/faq', 'public/Faq'],
    ['/ar/privacy', 'public/Privacy'],
    ['/en/contact', 'public/Contact'],
]);

it('lists public departments on the about page', function () {
    $this->department->update(['is_public' => true]);
    app(DepartmentService::class)->create(['name_en' => 'Finance']);

    $this->get('/ar/about')->assertInertia(fn (Assert $page) => $page->has('departments', 1)->where('departments.0.name', 'الإعلام'));
});

it('stores contact messages', function () {
    $this->post('/ar/contact', [
        'name' => 'Salma',
        'email' => 'salma@example.com',
        'subject' => 'Joining',
        'message' => 'How can our school join?',
        'privacy' => '1',
    ])->assertRedirect();

    expect(ContactMessage::query()->sole())->email->toBe('salma@example.com')->locale->toBe('ar');

    $this->post('/en/contact', ['name' => 'X'])->assertSessionHasErrors(['email', 'subject', 'message', 'privacy']);
});

it('protects the contact form with the honeypot', function () {
    config(['honeypot.enabled' => true]);

    $this->post('/en/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'subject' => 'x', 'message' => 'y', 'privacy' => '1'])
        ->assertSessionHasErrors('form');
});

it('lists only public pages in the sitemap', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee(url('/en/teams/falcons'), false)
        ->assertSee(url('/ar/projects/line-follower'), false)
        ->assertSee(url('/en/news/season-opens'), false)
        ->assertSee(url('/en/events/robot-cup'), false)
        ->assertDontSee('secret')
        ->assertDontSee('draft-post')
        ->assertDontSee('hidden-project');
});
