<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_reminders_are_sent_once_per_day(): void
    {
        $shop = Shop::factory()->create();
        $customer = User::factory()->create();
        $id = DB::table('reservations')->insertGetId(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'starts_at' => today()->subDays(2)->toDateString(), 'ends_at' => today()->subDay()->toDateString(), 'status' => 'ongoing', 'rental_centavos' => 10000, 'deposit_centavos' => 0]);
        $this->artisan('rentique:reminders')->assertSuccessful();
        $this->artisan('rentique:reminders')->assertSuccessful();
        $this->assertDatabaseHas('reservations', ['id' => $id, 'status' => 'overdue']);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('activity_logs', 1);
    }
}
