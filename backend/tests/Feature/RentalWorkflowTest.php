<?php

namespace Tests\Feature;

use App\Http\Middleware\FirebaseToken;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RentalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function inventory(): array
    {
        $shop = Shop::factory()->create(['approval_status' => 'approved']);
        $category = DB::table('product_categories')->insertGetId(['name' => 'Gowns']);
        $product = DB::table('products')->insertGetId(['shop_id' => $shop->id, 'product_category_id' => $category, 'name' => 'Evening gown', 'description' => 'A gown', 'daily_price_centavos' => 150000, 'deposit_centavos' => 50000]);
        $variant = DB::table('product_variants')->insertGetId(['product_id' => $product, 'size' => 'M', 'color' => 'Plum']);
        DB::table('inventory_units')->insert(['product_variant_id' => $variant, 'code' => 'UNIT-1', 'status' => 'available']);

        return [$shop, $product, $variant];
    }

    public function test_inclusive_pricing_and_approval_prevent_overlapping_allocation(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        [$shop,$product,$variant] = $this->inventory();
        $customer = User::factory()->create();
        $payload = ['product_variant_id' => $variant, 'starts_at' => today()->toDateString(), 'ends_at' => today()->addDays(2)->toDateString()];
        $first = $this->actingAs($customer)->postJson('/api/v1/customer/reservations', $payload)->assertCreated()->assertJsonPath('rental_centavos', 450000)->json('id');
        $second = $this->actingAs($customer)->postJson('/api/v1/customer/reservations', $payload)->assertCreated()->json('id');
        $this->actingAs($shop->owner)->patchJson('/api/v1/owner/reservations/'.$first.'/approve')->assertOk();
        $this->actingAs($shop->owner)->patchJson('/api/v1/owner/reservations/'.$second.'/approve')->assertConflict();
        $this->getJson('/api/v1/products/'.$product.'/availability?starts_at='.$payload['starts_at'].'&ends_at='.$payload['ends_at'])->assertOk()->assertJsonPath('data.0.available_quantity', 0);
    }

    public function test_rental_lifecycle_and_payments_are_safe_against_duplicate_submissions(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        [$shop,$product,$variant] = $this->inventory();
        $customer = User::factory()->create();
        $id = $this->actingAs($customer)->postJson('/api/v1/customer/reservations', ['product_variant_id' => $variant, 'starts_at' => today()->toDateString(), 'ends_at' => today()->toDateString()])->assertCreated()->json('id');
        $this->actingAs($shop->owner)->patchJson('/api/v1/owner/reservations/'.$id.'/approve')->assertOk();
        $payment = ['amount_centavos' => 150000, 'kind' => 'rental', 'idempotency_key' => (string) Str::uuid()];
        $this->postJson('/api/v1/owner/reservations/'.$id.'/payments', $payment)->assertCreated();
        $this->postJson('/api/v1/owner/reservations/'.$id.'/payments', $payment)->assertOk();
        $this->assertDatabaseCount('payments', 1);
        $this->patchJson('/api/v1/owner/reservations/'.$id.'/pickup')->assertOk();
        $this->patchJson('/api/v1/owner/reservations/'.$id.'/pickup')->assertConflict();
        $this->patchJson('/api/v1/owner/reservations/'.$id.'/return', ['late_fee_centavos' => 0, 'damage_fee_centavos' => 0])->assertOk();
        $this->patchJson('/api/v1/owner/reservations/'.$id.'/complete')->assertOk();
        $this->assertDatabaseCount('rental_transactions', 1);
        $this->assertDatabaseCount('rental_returns', 1);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $customer->id]);
    }

    public function test_customers_cannot_read_another_customers_bookings(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        [$shop,$product,$variant] = $this->inventory();
        $customer = User::factory()->create();
        $this->actingAs($customer)->postJson('/api/v1/customer/reservations', ['product_variant_id' => $variant, 'starts_at' => today()->toDateString(), 'ends_at' => today()->toDateString()])->assertCreated();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/customer/reservations')->assertOk()->assertJsonCount(0, 'data');
    }
}
