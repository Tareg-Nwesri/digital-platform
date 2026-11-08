<?php

namespace App\Http\Controllers\Console;

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Services\PostService;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\PostRequest;
use App\Http\Requests\Console\PublishPostRequest;
use App\Models\User;
use App\Support\Tables\TableQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 08 §5: news posts in the console.
 */
class PostController extends Controller
{
    public function __construct(
        private readonly PostService $posts,
        private readonly DepartmentAccess $access,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Post::class);

        $user = $request->user();
        $query = Post::query()->with(['department', 'author']);

        if (! $user->isSuperAdmin()) {
            $query->whereIn('department_id', array_unique([
                ...$this->access->departmentIdsWith($user, 'content.view'),
                ...$this->access->departmentIdsWith($user, 'content.edit'),
            ]));
        }

        $posts = TableQuery::for($query, $request)
            ->searchable(['title_en', 'title_ar', 'slug'])
            ->sortable(['title_en', 'published_at', 'updated_at'], default: 'updated_at', direction: 'desc')
            ->filters([
                'status' => fn (Builder $query, mixed $value) => $query->where('status', (string) $value),
            ])
            ->paginate(fn (Post $post): array => [
                'id' => $post->getKey(),
                'title' => $post->localized('title'),
                'department' => $post->department->localized('name'),
                'author' => $post->author?->name,
                'status' => $post->status->value,
                'publishedAt' => $post->published_at?->toIso8601String(),
                'updatedAt' => $post->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('console/posts/Index', [
            'posts' => $posts,
            'statuses' => array_column(PostStatus::cases(), 'value'),
            'departments' => $this->departmentOptions($user),
            'can' => ['create' => $user->can('create', Post::class)],
        ]);
    }

    public function store(PostRequest $request): RedirectResponse
    {
        $post = $this->posts->create($request->validated(), $request->user());

        return $this->done('created', to_route('console.posts.edit', $post));
    }

    public function edit(Request $request, Post $post): Response
    {
        $this->authorize('view', $post);

        $user = $request->user();

        return Inertia::render('console/posts/Edit', [
            'post' => [
                'id' => $post->getKey(),
                'slug' => $post->slug,
                'departmentId' => $post->department_id,
                'titleEn' => $post->title_en,
                'titleAr' => $post->title_ar,
                'excerptEn' => $post->excerpt_en,
                'excerptAr' => $post->excerpt_ar,
                'bodyEn' => $post->body_en,
                'bodyAr' => $post->body_ar,
                'status' => $post->status->value,
                'publishedAt' => $post->published_at?->toIso8601String(),
            ],
            'departments' => $this->departmentOptions($user),
            'can' => [
                'edit' => $user->can('update', $post),
                'publish' => $user->can('publish', $post),
            ],
        ]);
    }

    public function update(PostRequest $request, Post $post): RedirectResponse
    {
        $this->posts->update($post, $request->validated(), $request->user());

        return $this->done('saved', back());
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $this->posts->remove($post, $request->user());

        return $this->done('removed', to_route('console.posts.index'));
    }

    public function submit(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $this->posts->submitForReview($post, $request->user());

        return $this->done('submitted', back());
    }

    public function publish(PublishPostRequest $request, Post $post): RedirectResponse
    {
        $this->posts->publish($post, $request->publishAt(), $request->user());

        return $this->done($post->status === PostStatus::Scheduled ? 'scheduled' : 'published', back());
    }

    public function unpublish(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('publish', $post);

        $this->posts->unpublish($post, $request->user());

        return $this->done('unpublished', back());
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function departmentOptions(User $user): array
    {
        $departments = $user->isSuperAdmin()
            ? Department::query()->orderBy('name_en')->get()
            : $this->access->departmentsWith($user, 'content.edit');

        return $departments->map(fn (Department $department): array => [
            'id' => $department->getKey(),
            'name' => (string) $department->localized('name'),
        ])->values()->all();
    }

    private function done(string $key, RedirectResponse $response): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('content.flash.'.$key)]);

        return $response;
    }
}
