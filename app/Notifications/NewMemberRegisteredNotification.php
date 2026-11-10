<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMemberRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly User $newMember) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim(($this->newMember->detail?->first_name ?? '').' '.($this->newMember->detail?->last_name ?? ''));

        return (new MailMessage)
            ->subject(__('New member registration: :name', ['name' => $name]))
            ->line(__(':name just registered on the site (:email).', ['name' => $name, 'email' => $this->newMember->primary_email]))
            ->action(__('Review profile'), route('admin.profile.show', $this->newMember))
            ->line(__('Their profile is unclassified until a status is assigned.'));
    }
}
