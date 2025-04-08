<?php

namespace App\Notifications\Meter;

use App\Filament\Resources\ReadingResource;
use App\Models\Meter;
use App\Models\User;
use Filament\Actions\CreateAction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ForgotToRead extends Notification
{
    use Queueable;

    public function __construct(protected Meter $meter)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = ReadingResource::getUrl(parameters: [
            'action' => CreateAction::getDefaultName(),
            'tenant' => $this->meter,
        ]);

        return (new MailMessage)
            ->subject(__('notifications.forgot_to_read.subject'))
            ->greeting(__('notifications.forgot_to_read.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.forgot_to_read.content', ['meter' => $this->meter->name]))
            ->action(__('notifications.forgot_to_read.action'), $url);
    }
}
