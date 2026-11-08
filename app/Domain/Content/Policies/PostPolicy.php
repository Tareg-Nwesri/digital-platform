<?php

namespace App\Domain\Content\Policies;

use App\Domain\Content\Models\Post;
use App\Domain\Organisation\Services\DepartmentAccess;
use App\Models\User;

/**
 * News posts in the console (LLD 08 §4), within the post's department.
 * The super admin passes through Gate::before.
 */
class PostPolicy
{
    public function __construct(private readonly DepartmentAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'content.view') || $this->access->can($user, 'content.edit');
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'content.edit');
    }

    public function view(User $user, Post $post): bool
    {
        return $this->holds($user, $post, 'content.view') || $this->holds($user, $post, 'content.edit');
    }

    public function update(User $user, Post $post): bool
    {
        return $this->holds($user, $post, 'content.edit');
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function publish(User $user, Post $post): bool
    {
        return $this->holds($user, $post, 'content.approve');
    }

    private function holds(User $user, Post $post, string $permission): bool
    {
        $post->loadMissing('department');

        return $post->department !== null && $this->access->can($user, $permission, $post->department);
    }
}
