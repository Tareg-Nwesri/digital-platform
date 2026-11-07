<?php

namespace App\Domain\Events\Policies;

use App\Domain\Events\Models\Event;
use App\Domain\Organisation\Services\DepartmentAccess;
use App\Models\User;

/**
 * Console abilities on events (LLD 06 §6), always within the department
 * that owns the event. The super admin passes through Gate::before.
 */
class EventPolicy
{
    public function __construct(private readonly DepartmentAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'events.view') || $this->access->can($user, 'events.edit');
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'events.edit');
    }

    public function view(User $user, Event $event): bool
    {
        return $this->holds($user, $event, 'events.view') || $this->holds($user, $event, 'events.edit');
    }

    /**
     * Configure the event, its categories and invitations, add or withdraw
     * teams, record results.
     */
    public function update(User $user, Event $event): bool
    {
        return $this->holds($user, $event, 'events.edit');
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    /**
     * Approve, waitlist, reject, promote, override rules, publish results.
     */
    public function review(User $user, Event $event): bool
    {
        return $this->holds($user, $event, 'events.approve');
    }

    private function holds(User $user, Event $event, string $permission): bool
    {
        $event->loadMissing('department');

        return $event->department !== null && $this->access->can($user, $permission, $event->department);
    }
}
