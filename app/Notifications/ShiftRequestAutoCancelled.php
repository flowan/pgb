<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftRequestAutoCancelled extends Notification
{
    use Queueable;

    public function __construct(public string $requestType, public int $requestId) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Een verzoek is automatisch geannuleerd')
            ->greeting("Hoi {$notifiable->name},")
            ->line('Een verzoek is automatisch geannuleerd omdat de oorspronkelijke shift is verwijderd.')
            ->action('Bekijk in mijn rooster', url('/my-schedule'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'shift_request_auto_cancelled',
            'request_type' => $this->requestType,
            'request_id' => $this->requestId,
        ];
    }
}
