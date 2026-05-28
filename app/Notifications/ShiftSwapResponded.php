<?php

namespace App\Notifications;

use App\Models\Client;
use App\Models\ShiftSwapRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftSwapResponded extends Notification
{
    use Queueable;

    public function __construct(public ShiftSwapRequest $req, public bool $accepted) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $target = $this->req->target?->name ?? 'Je collega';
        $requesterDate = $this->req->requester_date->format('d-m-Y');
        $status = $this->accepted ? 'geaccepteerd' : 'afgewezen';

        return (new MailMessage)
            ->subject("Ruilverzoek is {$status}")
            ->greeting("Hoi {$notifiable->name},")
            ->line("{$target} heeft je ruilverzoek voor {$requesterDate} {$status}.")
            ->action('Bekijk in mijn rooster', url('/my-schedule'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'shift_swap_responded',
            'swap_request_id' => $this->req->id,
            'accepted' => $this->accepted,
            'target' => $this->req->target?->name,
            'requester_date' => $this->req->requester_date->format('Y-m-d'),
        ];
    }

    public static function notify(ShiftSwapRequest $req, bool $accepted): void
    {
        if ($req->requester?->user) {
            $req->requester->user->notify(new self($req, $accepted));
        }

        if ($accepted) {
            $clientId = $req->requesterSchedule?->client_id ?? $req->requesterScheduleException?->client_id;

            if ($clientId) {
                $client = Client::find($clientId);
                $holder = $client?->budgetHolder;

                if ($holder) {
                    $holder->notify(new self($req, $accepted));
                }
            }
        }
    }
}
