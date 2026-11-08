<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Models\Post;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Html\RichText;
use App\Support\Slugger;
use Carbon\CarbonInterface;

/**
 * News posts (LLD 08 §2). Bodies are sanitised on every save.
 */
final class PostService
{
    private const FIELDS = ['department_id', 'title_en', 'title_ar', 'excerpt_en', 'excerpt_ar', 'body_en', 'body_ar'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $by): Post
    {
        return Post::query()->create([
            ...$this->clean($data),
            'slug' => Slugger::unique(Post::class, (string) (filled($data['slug'] ?? null) ? $data['slug'] : $data['title_en'])),
            'status' => PostStatus::Draft,
            'author_id' => $by->getKey(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Post $post, array $data, User $by): Post
    {
        if ($post->status === PostStatus::Archived) {
            throw DomainRuleViolation::with(['post' => __('content.errors.archived')]);
        }

        $post->update($this->clean($data));

        return $post;
    }

    public function submitForReview(Post $post, User $by): void
    {
        if ($post->status !== PostStatus::Draft) {
            throw DomainRuleViolation::with(['status' => __('content.errors.not_draft')]);
        }

        $post->forceFill(['status' => PostStatus::InReview])->save();
    }

    /**
     * Publish now, or schedule when $at is in the future.
     */
    public function publish(Post $post, ?CarbonInterface $at, User $by): void
    {
        if (! in_array($post->status, [PostStatus::Draft, PostStatus::InReview, PostStatus::Scheduled], true)) {
            throw DomainRuleViolation::with(['status' => __('content.errors.cannot_publish')]);
        }

        $at ??= now();

        $post->forceFill([
            'status' => $at->isFuture() ? PostStatus::Scheduled : PostStatus::Published,
            'published_at' => $at,
            'approved_by' => $by->getKey(),
        ])->save();
    }

    public function unpublish(Post $post, User $by): void
    {
        $post->forceFill(['status' => PostStatus::Draft, 'published_at' => null])->save();
    }

    public function archive(Post $post, User $by): void
    {
        $post->forceFill(['status' => PostStatus::Archived])->save();
    }

    /**
     * Drafts and posts never published are deleted; anything that has been
     * public is archived so links keep a record.
     */
    public function remove(Post $post, User $by): void
    {
        if ($post->published_at === null && $post->status !== PostStatus::Published) {
            $post->delete();

            return;
        }

        $this->archive($post, $by);
    }

    /**
     * Publish scheduled posts whose time has come. Returns how many.
     */
    public function publishDue(): int
    {
        return Post::query()
            ->where('status', PostStatus::Scheduled)
            ->where('published_at', '<=', now())
            ->get()
            ->each(fn (Post $post) => $post->forceFill(['status' => PostStatus::Published])->save())
            ->count();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function clean(array $data): array
    {
        $values = array_intersect_key($data, array_flip(self::FIELDS));

        foreach (['body_en', 'body_ar'] as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = RichText::clean($values[$field]);
            }
        }

        return $values;
    }
}
