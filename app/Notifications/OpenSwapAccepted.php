<?php

namespace App\Notifications;

use App\Models\Client;
use App\Models\OpenSwapOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OpenSwapAccepted extends Notification
{
    use Queueable;

    public function __construct(public OpenSwapOffer $offer) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $req = $this->offer->openSwapRequest;
        $requester = $req?->requester?->name ?? 'De aanvrager';
        $date = $req?->date->format('d-m-Y') ?? '';

        return (new MailMessage)
            ->subject('Je ruilbod is geaccepteerd')
            ->greeting("Hoi {$notifiable->name},")
            ->line("{$requester} heeft je ruilbod voor de shift op {$date} geaccepteerd.")
            ->action('Bekijk in mijn rooster', url('/my-schedule'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'open_swap_accepted',
            'open_swap_offer_id' => $this->offer->id,
            'open_swap_request_id' => $this->offer->open_swap_request_id,
            'offered_date' => $this->offer->offered_date->format('Y-m-d'),
        ];
    }

    public static function notify(OpenSwapOffer $offer): void
    {
        if ($offer->offeredBy?->user) {
            $offer->offeredBy->user->notify(new self($offer));
        }

        $req = $offer->openSwapRequest;
        $clientId = $req?->schedule?->client_id ?? $req?->scheduleException?->client_id;

        if ($clientId) {
            $client = Client::find($clientId);
            $holder = $client?->budgetHolder;

            if ($holder) {
                $holder->notify(new self($offer));
            }
        }
    }
}
