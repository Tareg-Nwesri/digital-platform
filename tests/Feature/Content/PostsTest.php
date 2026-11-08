<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Services\PostService;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 08 §1–2, §4–8: news posts. Editors (content.edit) write drafts and
 * send them for review; department admins (content.approve) publish now or
 * on a schedule. Bodies are sanitised on the server.
 */

beforeEach(function () {
    $this->seed(PermissionCatalogueSeeder::class);
    Carbon::setTestNow('2027-02-01 10:00:00');

    $superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $superAdmin->assignRole(User::SUPER_ADMIN));

    $departments = app(DepartmentService::class);
    $permissions = app(DepartmentPermissionService::class);
    $this->media = $departments->create(['name_en' => 'Media']);
    $permissions->setCeiling($this->media, ['content.view', 'content.edit'], $superAdmin->fresh());
    $this->events = $departments->create(['name_en' => 'Events']);
    $permissions->setCeiling($this->events, ['content.view', 'content.edit'], $superAdmin->fresh());

    $this->admin = User::factory()->create();
    $this->editor = User::factory()->create(['name_en' => 'Media Editor']);
    $this->outsider = User::factory()->create();
    $departments->addMember($this->media, $this->admin, isAdmin: true);
    $departments->addMember($this->media, $this->editor);
    $departments->addMember($this->events, $this->outsider);
    $permissions->syncMember($this->media, $this->editor, ['content.view', 'content.edit'], $this->admin);
    $permissions->syncMember($this->events, $this->outsider, ['content.view', 'content.edit'], $superAdmin->fresh());

    $this->posts = app(PostService::class);
});

function postPayload(array $overrides = []): array
{
    return array_merge([
        'department_id' => test()->media->id,
        'title_en' => 'Falcons win the Robot Cup',
        'title_ar' => 'الصقور يفوزون بكأس الروبوت',
        'excerpt_en' => 'A clean sweep in Tripoli.',
        'body_en' => '<h2>Final</h2><p>The team <strong>won</strong>.</p>',
        'body_ar' => '<p>فاز الفريق.</p>',
    ], $overrides);
}

it('sanitises post bodies on the server', function () {
    $post = $this->posts->create(postPayload([
        'body_en' => '<p onclick="steal()">Hi <script>alert(1)</script><a href="javascript:alert(1)">x</a> <a href="https://example.test">ok</a></p><iframe src="https://evil.test"></iframe>',
    ]), $this->editor);

    expect($post->body_en)->not->toContain('script')
        ->not->toContain('onclick')
        ->not->toContain('javascript:')
        ->not->toContain('iframe')
        ->toContain('href="https://example.test"')
        ->toContain('rel="noopener noreferrer"');
});

it('creates drafts with a unique slug and sends them for review', function () {
    $first = $this->posts->create(postPayload(), $this->editor);
    $second = $this->posts->create(postPayload(), $this->editor);

    expect($first->status)->toBe(PostStatus::Draft)
        ->and($first->slug)->toBe('falcons-win-the-robot-cup')
        ->and($second->slug)->toBe('falcons-win-the-robot-cup-2')
        ->and($first->author_id)->toBe($this->editor->id);

    $this->posts->submitForReview($first, $this->editor);

    expect($first->fresh()->status)->toBe(PostStatus::InReview);
});

it('publishes now or schedules for later, and publishes due posts', function () {
    $now = $this->posts->create(postPayload(), $this->editor);
    $later = $this->posts->create(postPayload(['title_en' => 'Coming soon']), $this->editor);

    $this->posts->publish($now, null, $this->admin);
    $this->posts->publish($later, Carbon::parse('2027-02-03 09:00:00'), $this->admin);

    expect($now->fresh())->status->toBe(PostStatus::Published)->approved_by->toBe($this->admin->id)
        ->and($later->fresh()->status)->toBe(PostStatus::Scheduled)
        ->and(Post::query()->public()->pluck('id')->all())->toBe([$now->id]);

    Carbon::setTestNow('2027-02-03 09:01:00');
    $this->artisan('posts:publish-due')->assertSuccessful();

    expect($later->fresh()->status)->toBe(PostStatus::Published)
        ->and(Post::query()->public()->count())->toBe(2);
});

it('unpublishes and archives posts', function () {
    $post = $this->posts->create(postPayload(), $this->editor);
    $this->posts->publish($post, null, $this->admin);

    $this->posts->unpublish($post, $this->admin);
    expect($post->fresh()->status)->toBe(PostStatus::Draft)
        ->and(Post::query()->public()->exists())->toBeFalse();

    $this->posts->archive($post->fresh(), $this->admin);
    expect($post->fresh()->status)->toBe(PostStatus::Archived);
});

it('lists posts of the user’s departments in the console', function () {
    $this->posts->create(postPayload(), $this->editor);
    $this->posts->create(postPayload(['department_id' => $this->events->id, 'title_en' => 'Events news']), $this->outsider);

    $this->actingAs($this->editor)
        ->get(route('console.posts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/posts/Index')
            ->has('posts.data', 1)
            ->where('posts.data.0.title', 'Falcons win the Robot Cup')
            ->where('posts.data.0.status', 'draft')
            ->where('can.create', true));

    $this->actingAs(User::factory()->create())->get(route('console.posts.index'))->assertForbidden();
});

it('lets editors write and submit, but only approvers publish', function () {
    $this->actingAs($this->editor)->post(route('console.posts.store'), postPayload())->assertRedirect();
    $post = Post::query()->sole();

    $this->actingAs($this->editor)->get(route('console.posts.edit', $post))
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/posts/Edit')
            ->where('post.titleEn', 'Falcons win the Robot Cup')
            ->where('can.publish', false));

    $this->actingAs($this->editor)->patch(route('console.posts.update', $post), postPayload(['title_en' => 'Falcons win again']))->assertRedirect();
    $this->actingAs($this->editor)->post(route('console.posts.submit', $post))->assertRedirect();
    $this->actingAs($this->editor)->post(route('console.posts.publish', $post))->assertForbidden();

    $this->actingAs($this->admin)->post(route('console.posts.publish', $post), ['published_at' => '2027-02-05 08:00'])->assertRedirect();

    expect($post->fresh())
        ->title_en->toBe('Falcons win again')
        ->status->toBe(PostStatus::Scheduled);
});

it('keeps other departments out of a post and its department out of reach', function () {
    $post = $this->posts->create(postPayload(), $this->editor);

    $this->actingAs($this->outsider)->get(route('console.posts.edit', $post))->assertForbidden();
    $this->actingAs($this->outsider)->patch(route('console.posts.update', $post), postPayload(['department_id' => $this->events->id]))->assertForbidden();
    $this->actingAs($this->editor)->post(route('console.posts.store'), postPayload(['department_id' => $this->events->id]))->assertSessionHasErrors('department_id');
});

it('validates posts', function (array $payload, string $error) {
    $this->actingAs($this->editor)->post(route('console.posts.store'), postPayload($payload))->assertSessionHasErrors($error);
})->with([
    'missing title' => [['title_en' => ''], 'title_en'],
    'long excerpt' => [['excerpt_en' => str_repeat('a', 401)], 'excerpt_en'],
]);

it('deletes drafts but archives published posts', function () {
    $draft = $this->posts->create(postPayload(), $this->editor);
    $published = $this->posts->create(postPayload(['title_en' => 'Live']), $this->editor);
    $this->posts->publish($published, null, $this->admin);

    $this->actingAs($this->editor)->delete(route('console.posts.destroy', $draft))->assertRedirect(route('console.posts.index'));
    $this->actingAs($this->admin)->delete(route('console.posts.destroy', $published))->assertRedirect(route('console.posts.index'));

    expect(Post::query()->find($draft->id))->toBeNull()
        ->and($published->fresh()->status)->toBe(PostStatus::Archived);
});
