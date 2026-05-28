<?php

namespace App\Notifications;

use App\Models\Client;
use App\Models\ShiftTakeoverOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftTakenOver extends Notification
{
    use Queueable;

    public function __construct(public ShiftTakeoverOffer $offer) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->offer->date->format('d-m-Y');
        $claimedBy = $this->offer->claimedBy?->name ?? 'Een collega';

        return (new MailMessage)
            ->subject('Een shift is overgenomen')
            ->greeting("Hoi {$notifiable->name},")
            ->line("{$claimedBy} heeft de aangeboden shift op {$date} overgenomen.")
            ->action('Bekijk in mijn rooster', url('/my-schedule'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'shift_taken_over',
            'offer_id' => $this->offer->id,
            'claimed_by' => $this->offer->claimedBy?->name,
            'date' => $this->offer->date->format('Y-m-d'),
        ];
    }

    public static function notify(ShiftTakeoverOffer $offer): void
    {
        if ($offer->offeredBy?->user) {
            $offer->offeredBy->user->notify(new self($offer));
        }

        $clientId = $offer->schedule?->client_id ?? $offer->scheduleException?->client_id;

        if (! $clientId) {
            return;
        }

        $client = Client::find($clientId);
        $holder = $client?->budgetHolder;

        if ($holder) {
            $holder->notify(new self($offer));
        }
    }
}
