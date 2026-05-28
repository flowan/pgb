<?php

namespace App\Notifications;

use App\Models\Caregiver;
use App\Models\OpenSwapRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OpenSwapRequested extends Notification
{
    use Queueable;

    public function __construct(public OpenSwapRequest $req) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->req->date->format('d-m-Y');
        $requester = $this->req->requester?->name ?? 'Een collega';

        return (new MailMessage)
            ->subject('Een collega zoekt een ruil voor een shift')
            ->greeting("Hoi {$notifiable->name},")
            ->line("{$requester} zoekt iemand om een shift op {$date} mee te ruilen.")
            ->action('Bekijk in mijn rooster', url('/my-schedule'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'open_swap_requested',
            'open_swap_request_id' => $this->req->id,
            'requester' => $this->req->requester?->name,
            'date' => $this->req->date->format('Y-m-d'),
        ];
    }

    public static function notifyColleagues(OpenSwapRequest $req): void
    {
        $clientId = $req->schedule?->client_id ?? $req->scheduleException?->client_id;

        if (! $clientId) {
            return;
        }

        $colleagues = Caregiver::where('client_id', $clientId)
            ->whereNotNull('user_id')
            ->where('id', '!=', $req->requester_caregiver_id)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        foreach ($colleagues as $user) {
            $user->notify(new self($req));
        }
    }
}
