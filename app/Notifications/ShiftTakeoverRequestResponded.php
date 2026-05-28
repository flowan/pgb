<?php

namespace App\Notifications;

use App\Models\Client;
use App\Models\ShiftTakeoverRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftTakeoverRequestResponded extends Notification
{
    use Queueable;

    public function __construct(public ShiftTakeoverRequest $req, public bool $accepted) {}

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
        $date = $this->req->target_date->format('d-m-Y');
        $status = $this->accepted ? 'geaccepteerd' : 'afgewezen';

        return (new MailMessage)
            ->subject('Reactie op je overname-verzoek')
            ->greeting("Hoi {$notifiable->name},")
            ->line("{$target} heeft je overname-verzoek voor {$date} {$status}.")
            ->action('Bekijk in mijn rooster', url('/my-schedule'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'shift_takeover_request_responded',
            'request_id' => $this->req->id,
            'accepted' => $this->accepted,
            'target' => $this->req->target?->name,
            'date' => $this->req->target_date->format('Y-m-d'),
        ];
    }

    public static function notify(ShiftTakeoverRequest $req, bool $accepted): void
    {
        if ($req->requester?->user) {
            $req->requester->user->notify(new self($req, $accepted));
        }

        if ($accepted) {
            $clientId = $req->targetSchedule?->client_id ?? $req->targetScheduleException?->client_id;

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
