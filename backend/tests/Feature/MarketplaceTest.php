<?php

namespace Tests\Feature;

use App\Http\Middleware\FirebaseToken;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_directory_excludes_unapproved_inactive_and_suspended_owner_shops(): void
    {
        $visible = Shop::factory()->create(['approval_status' => 'approved']);
        Shop::factory()->create();
        Shop::factory()->create(['approval_status' => 'approved', 'is_active' => false]);
        Shop::factory()->create(['approval_status' => 'approved', 'owner_id' => User::factory()->create(['account_status' => 'suspended'])->id]);
        $this->getJson('/api/v1/shops')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_pending_shop_details_are_not_public(): void
    {
        $shop = Shop::factory()->create();
        $this->getJson('/api/v1/shops/'.$shop->id)->assertNotFound();
    }

    public function test_nearby_search_validates_coordinates(): void
    {
        $this->getJson('/api/v1/shops/nearby?latitude=100&longitude=0&radius=-1')->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'radius']);
    }

    public function test_nearby_search_filters_radius_and_orders_by_distance(): void
    {
        $near = Shop::factory()->create(['approval_status' => 'approved', 'latitude' => 14.6507, 'longitude' => 121.0494]);
        Shop::factory()->create(['approval_status' => 'approved', 'latitude' => 15.6507, 'longitude' => 121.0494]);
        Shop::factory()->create(['latitude' => 14.6507, 'longitude' => 121.0494]);
        $this->getJson('/api/v1/shops/nearby?latitude=14.6507&longitude=121.0494&radius=5')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $near->id);
    }

    public function test_protected_endpoints_require_firebase_identity(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/owner/shop-applications')->assertUnauthorized();
    }

    public function test_owner_cannot_change_another_shops_location(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $user = User::factory()->create();
        $shop = Shop::factory()->create();
        $this->actingAs($user)->patchJson('/api/v1/owner/shops/'.$shop->id.'/location', ['latitude' => 14, 'longitude' => 121, 'formatted_address' => 'New address', 'location_confirmed' => true])->assertForbidden();
    }

    public function test_location_update_requires_confirmation_and_writes_audit_log(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);
        $this->actingAs($user)->patchJson('/api/v1/owner/shops/'.$shop->id.'/location', ['latitude' => 14, 'longitude' => 121, 'formatted_address' => 'New address'])->assertUnprocessable();
        $this->actingAs($user)->patchJson('/api/v1/owner/shops/'.$shop->id.'/location', ['latitude' => 14, 'longitude' => 121, 'formatted_address' => 'New address', 'location_confirmed' => true])->assertOk();
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'shop.location_updated', 'subject_id' => $shop->id]);
    }

    public function test_customer_cannot_approve_shop(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $shop = Shop::factory()->create();
        $this->actingAs(User::factory()->create())->patchJson('/api/v1/admin/shops/'.$shop->id.'/status', ['status' => 'approved'])->assertForbidden();
    }

    public function test_admin_approval_publishes_shop_and_grants_owner_role(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $shop = Shop::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patchJson('/api/v1/admin/shops/'.$shop->id.'/status', ['status' => 'approved'])->assertOk();
        $this->getJson('/api/v1/shops/'.$shop->id)->assertOk();
        $this->assertDatabaseHas('users', ['id' => $shop->owner_id, 'role' => 'owner']);
    }
}
