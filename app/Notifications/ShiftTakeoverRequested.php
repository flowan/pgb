<?php

namespace App\Notifications;

use App\Models\ShiftTakeoverRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftTakeoverRequested extends Notification
{
    use Queueable;

    public function __construct(public ShiftTakeoverRequest $req) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $requester = $this->req->requester?->name ?? 'Een collega';
        $date = $this->req->target_date->format('d-m-Y');

        return (new MailMessage)
            ->subject('Verzoek om je shift over te nemen')
            ->greeting("Hoi {$notifiable->name},")
            ->line("{$requester} wil je shift op {$date} overnemen.")
            ->action('Bekijk in mijn rooster', url('/my-schedule'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'shift_takeover_requested',
            'request_id' => $this->req->id,
            'requester' => $this->req->requester?->name,
            'date' => $this->req->target_date->format('Y-m-d'),
        ];
    }

    public static function notify(ShiftTakeoverRequest $req): void
    {
        if ($req->target?->user) {
            $req->target->user->notify(new self($req));
        }
    }
}
