<?php

namespace App\Domain\Content\Policies;

use App\Domain\Content\Models\ContactMessage;
use App\Domain\Organisation\Services\DepartmentAccess;
use App\Models\User;

/**
 * The contact inbox (LLD 08 §4). Messages with no department belong to the
 * super admin, who passes through Gate::before.
 */
class ContactMessagePolicy
{
    public function __construct(private readonly DepartmentAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'inbox.view') || $this->access->can($user, 'inbox.edit');
    }

    public function view(User $user, ContactMessage $message): bool
    {
        return $this->holds($user, $message, 'inbox.view') || $this->holds($user, $message, 'inbox.edit');
    }

    public function update(User $user, ContactMessage $message): bool
    {
        return $this->holds($user, $message, 'inbox.edit');
    }

    private function holds(User $user, ContactMessage $message, string $permission): bool
    {
        $message->loadMissing('department');

        return $message->department !== null && $this->access->can($user, $permission, $message->department);
    }
}
