<?php

namespace App\Notifications;

use App\Models\OpenSwapOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OpenSwapOfferReceived extends Notification
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
        $offeredBy = $this->offer->offeredBy?->name ?? 'Een collega';
        $offeredDate = $this->offer->offered_date->format('d-m-Y');

        return (new MailMessage)
            ->subject('Je hebt een ruilbod ontvangen')
            ->greeting("Hoi {$notifiable->name},")
            ->line("{$offeredBy} biedt zijn shift op {$offeredDate} aan in ruil voor de jouwe.")
            ->action('Bekijk in mijn rooster', url('/my-schedule'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'open_swap_offer_received',
            'open_swap_offer_id' => $this->offer->id,
            'open_swap_request_id' => $this->offer->open_swap_request_id,
            'offered_by' => $this->offer->offeredBy?->name,
            'offered_date' => $this->offer->offered_date->format('Y-m-d'),
        ];
    }

    public static function notify(OpenSwapOffer $offer): void
    {
        $req = $offer->openSwapRequest;

        if ($req?->requester?->user) {
            $req->requester->user->notify(new self($offer));
        }
    }
}
