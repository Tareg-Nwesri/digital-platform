<?php

namespace App\Domain\Content\Notifications;

use App\Domain\Content\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells inbox owners a contact message arrived, in each recipient's language.
 */
class NewContactMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ContactMessage $contactMessage) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('content.mail.subject', ['subject' => $this->contactMessage->subject]))
            ->line(__('content.mail.intro', ['name' => $this->contactMessage->name]))
            ->line(Str::limit($this->contactMessage->message, 300))
            ->action(__('content.mail.action'), route('console.inbox.show', $this->contactMessage));
    }
}
