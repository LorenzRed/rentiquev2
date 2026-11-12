<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\ReservationUpdated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendRentalReminders extends Command
{
    protected $signature = 'rentique:reminders';

    protected $description = 'Mark overdue rentals and send daily pickup and return reminders';

    public function handle(): int
    {
        $reservations = DB::table('reservations')->whereIn('status', ['approved', 'ready_for_pickup', 'ongoing', 'overdue'])->orderBy('id')->get();
        foreach ($reservations as $reservation) {
            DB::transaction(function () use ($reservation): void {
                $current = DB::table('reservations')->where('id', $reservation->id)->lockForUpdate()->first();
                $event = null;
                if (in_array($current->status, ['ongoing', 'overdue'], true) && $current->ends_at < today()->toDateString()) {
                    DB::table('reservations')->where('id', $current->id)->update(['status' => 'overdue', 'updated_at' => now()]);
                    $event = 'overdue';
                } elseif (in_array($current->status, ['approved', 'ready_for_pickup'], true) && $current->starts_at === today()->toDateString()) {
                    $event = 'pickup_reminder';
                } elseif ($current->status === 'ongoing' && $current->ends_at === today()->toDateString()) {
                    $event = 'return_reminder';
                }
                if (! $event) {
                    return;
                }
                $action = 'reminder.'.$event.'.'.today()->toDateString();
                if (DB::table('activity_logs')->where('subject_type', 'reservation')->where('subject_id', $current->id)->where('action', $action)->exists()) {
                    return;
                }
                User::findOrFail($current->customer_id)->notify(new ReservationUpdated($current->id, $event));
                DB::table('activity_logs')->insert(['action' => $action, 'subject_type' => 'reservation', 'subject_id' => $current->id, 'created_at' => now(), 'updated_at' => now()]);
            });
        }

        return self::SUCCESS;
    }
}
