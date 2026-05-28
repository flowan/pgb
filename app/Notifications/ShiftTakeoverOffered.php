<?php

namespace App\Notifications;

use App\Models\Caregiver;
use App\Models\ShiftTakeoverOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftTakeoverOffered extends Notification
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
        $offeredBy = $this->offer->offeredBy?->name ?? 'Een collega';

        return (new MailMessage)
            ->subject('Een collega biedt een shift aan voor overname')
            ->greeting("Hoi {$notifiable->name},")
            ->line("{$offeredBy} biedt een shift aan op {$date}. Misschien kun jij deze overnemen?")
            ->action('Bekijk in mijn rooster', url('/my-schedule'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'shift_takeover_offered',
            'offer_id' => $this->offer->id,
            'offered_by' => $this->offer->offeredBy?->name,
            'date' => $this->offer->date->format('Y-m-d'),
        ];
    }

    public static function notifyColleagues(ShiftTakeoverOffer $offer): void
    {
        $clientId = $offer->schedule?->client_id ?? $offer->scheduleException?->client_id;

        if (! $clientId) {
            return;
        }

        $colleagues = Caregiver::where('client_id', $clientId)
            ->whereNotNull('user_id')
            ->where('id', '!=', $offer->offered_by_caregiver_id)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        foreach ($colleagues as $user) {
            $user->notify(new self($offer));
        }
    }
}
