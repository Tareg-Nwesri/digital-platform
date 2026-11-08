<?php

namespace App\Domain\Content\Services;

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Content\Enums\MessageStatus;
use App\Domain\Content\Models\ContactMessage;
use App\Domain\Content\Models\ContactMessageNote;
use App\Domain\Content\Notifications\NewContactMessageNotification;
use App\Domain\Organisation\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * The contact inbox (LLD 08 §2). The public form (phase 4) calls receive().
 */
final class ContactMessageService
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly WorkspaceContext $context,
    ) {}

    /**
     * Store a message for the configured inbox department (else the super
     * admin) and notify its admins.
     *
     * @param  array{name: string, email: string, subject: string, message: string, locale?: string|null, ip?: string|null}  $data
     */
    public function receive(array $data): ContactMessage
    {
        $department = Department::query()->find($this->settings->get('inbox.department_id'));

        $message = ContactMessage::query()->create([
            'department_id' => $department?->getKey(),
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'locale' => in_array($data['locale'] ?? null, ['ar', 'en'], true) ? $data['locale'] : app()->getLocale(),
            'status' => MessageStatus::New,
            'ip_hash' => filled($data['ip'] ?? null) ? hash_hmac('sha256', (string) $data['ip'], (string) config('app.key')) : null,
        ]);

        Notification::send($this->recipients($department), new NewContactMessageNotification($message));

        return $message;
    }

    public function assign(ContactMessage $message, ?User $assignee, User $by): void
    {
        $message->forceFill(['assigned_to' => $assignee?->getKey()])->save();
        $this->log($message, $by, 'inbox.assigned', ['assigned_to' => $assignee?->getKey()]);
    }

    public function setStatus(ContactMessage $message, MessageStatus $status, User $by): void
    {
        $message->forceFill(['status' => $status])->save();
        $this->log($message, $by, 'inbox.status', ['status' => $status->value]);
    }

    public function addNote(ContactMessage $message, string $body, User $by): ContactMessageNote
    {
        $note = $message->notes()->create(['user_id' => $by->getKey(), 'body' => $body]);

        if ($message->status === MessageStatus::New) {
            $this->setStatus($message, MessageStatus::InProgress, $by);
        }

        return $note;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(?Department $department): Collection
    {
        if ($department !== null && $department->admins()->exists()) {
            return $department->admins()->get();
        }

        return $this->context->within(Workspace::platform(), fn (): Collection => User::role(User::SUPER_ADMIN)->get());
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(ContactMessage $message, User $by, string $event, array $properties): void
    {
        activity('inbox')->performedOn($message)->causedBy($by)->withProperties($properties)->event($event)
            ->log(ucfirst(str_replace(['inbox.', '_'], ['', ' '], $event)));
    }
}
