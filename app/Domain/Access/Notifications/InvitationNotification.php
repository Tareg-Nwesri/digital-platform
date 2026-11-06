<?php

namespace App\Domain\Access\Notifications;

use App\Domain\Access\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent synchronously so the invitee is never left waiting for the cron-drained queue (LLD 11 §3).
 */
class InvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly string $plainToken,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $owner = $this->invitation->owner();
        $name = $owner?->name ?? $owner?->name_en ?? config('app.name');

        return (new MailMessage)
            ->subject(__('access.invitations.mail.subject', ['name' => $name]))
            ->greeting(__('access.invitations.mail.greeting'))
            ->line(__('access.invitations.mail.intro', [
                'name' => $name,
                'role' => __('access.roles.'.$this->invitation->role),
            ]))
            ->action(__('access.invitations.mail.action'), route('invitations.show', $this->plainToken))
            ->line(__('access.invitations.mail.expires', [
                'date' => $this->invitation->expires_at->format('Y-m-d'),
            ]));
    }
}
