<?php

namespace App\Notifications;

use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ScheduleException;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CaregiverReportedSick extends Notification
{
    use Queueable;

    public function __construct(
        public Caregiver $caregiver,
        public string $clientName,
        public string $date,
        public string $startTime,
        public string $endTime,
        public int $exceptionId,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = \Carbon\Carbon::parse($this->date)->format('d-m-Y');

        return (new MailMessage)
            ->subject('Een zorgverlener heeft zich ziek gemeld')
            ->greeting("Hoi {$notifiable->name},")
            ->line("{$this->caregiver->name} heeft zich ziek gemeld voor {$date} bij {$this->clientName}. De shift is automatisch aangeboden voor overname.")
            ->action('Bekijk in het rooster', url('/dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'caregiver_reported_sick',
            'caregiver' => $this->caregiver->name,
            'client' => $this->clientName,
            'date' => $this->date,
            'exception_id' => $this->exceptionId,
        ];
    }

    public static function notifyBudgetHolder(Client $client, Caregiver $caregiver, ScheduleException $exception): void
    {
        $holder = $client->budgetHolder;

        if (! $holder) {
            return;
        }

        $holder->notify(new self(
            caregiver: $caregiver,
            clientName: $client->name,
            date: $exception->date->format('Y-m-d'),
            startTime: $exception->start_time,
            endTime: $exception->end_time,
            exceptionId: $exception->id,
        ));
    }
}
