<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ReservationUpdated extends Notification
{
    public function __construct(public int $reservationId, public string $status) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['reservation_id' => $this->reservationId, 'status' => $this->status, 'message' => 'Your reservation is now '.str_replace('_', ' ', $this->status).'.'];
    }
}
