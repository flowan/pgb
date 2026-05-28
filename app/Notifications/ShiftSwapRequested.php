<?php

namespace App\Notifications;

use App\Models\ShiftSwapRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftSwapRequested extends Notification
{
    use Queueable;

    public function __construct(public ShiftSwapRequest $req) {}

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
        $requesterDate = $this->req->requester_date->format('d-m-Y');
        $targetDate = $this->req->target_date->format('d-m-Y');

        return (new MailMessage)
            ->subject('Een collega wil een shift met je ruilen')
            ->greeting("Hoi {$notifiable->name},")
            ->line("{$requester} wil zijn shift op {$requesterDate} ruilen met jouw shift op {$targetDate}.")
            ->action('Bekijk in mijn rooster', url('/my-schedule'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'shift_swap_requested',
            'swap_request_id' => $this->req->id,
            'requester' => $this->req->requester?->name,
            'requester_date' => $this->req->requester_date->format('Y-m-d'),
            'target_date' => $this->req->target_date->format('Y-m-d'),
        ];
    }

    public static function notify(ShiftSwapRequest $req): void
    {
        if ($req->target?->user) {
            $req->target->user->notify(new self($req));
        }
    }
}
