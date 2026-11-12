<?php

namespace Tests\Feature;

use App\Http\Middleware\FirebaseToken;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_profile_and_registration_require_authentication(): void
    {
        $this->postJson('/api/v1/auth/complete-registration')->assertUnauthorized();
        $this->patchJson('/api/v1/profile')->assertUnauthorized();
        $this->postJson('/api/v1/profile/photo')->assertUnauthorized();
    }

    public function test_new_renter_can_complete_registration_once(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $user = User::factory()->create(['registration_completed_at' => null]);
        $this->actingAs($user)->postJson('/api/v1/auth/complete-registration', ['full_name' => 'Jordan Cruz', 'account_type' => 'customer'])->assertOk()->assertJsonPath('role', 'customer')->assertJsonPath('full_name', 'Jordan Cruz');
        $this->assertNotNull($user->fresh()->registration_completed_at);
        $this->postJson('/api/v1/auth/complete-registration', ['full_name' => 'Jordan Cruz', 'account_type' => 'owner'])->assertConflict();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'customer']);
    }

    public function test_owner_registration_does_not_approve_shops_or_grant_admin_access(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $user = User::factory()->create(['registration_completed_at' => null]);
        $this->actingAs($user)->postJson('/api/v1/auth/complete-registration', ['full_name' => 'Alex Santos', 'account_type' => 'owner'])->assertOk()->assertJsonPath('role', 'owner');
        $shop = Shop::factory()->create(['owner_id' => $user->id]);
        $this->getJson('/api/v1/shops/'.$shop->id)->assertNotFound();
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Gowns']);
        $this->postJson('/api/v1/owner/products', ['shop_id' => $shop->id, 'product_category_id' => $categoryId, 'name' => 'Gown', 'description' => 'Evening gown', 'daily_price_centavos' => 10000, 'deposit_centavos' => 0, 'size' => 'M', 'color' => 'Plum', 'quantity' => 1])->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_registration_rejects_administrator_and_role_overrides(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $user = User::factory()->create(['registration_completed_at' => null]);
        $this->actingAs($user)->postJson('/api/v1/auth/complete-registration', ['full_name' => 'Jordan Cruz', 'account_type' => 'admin'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/complete-registration', ['full_name' => 'Jordan Cruz', 'account_type' => 'owner', 'role' => 'admin'])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'customer', 'registration_completed_at' => null]);
    }

    public function test_existing_administrator_cannot_be_downgraded_through_registration(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $user = User::factory()->create(['role' => 'admin', 'registration_completed_at' => null]);
        $this->actingAs($user)->postJson('/api/v1/auth/complete-registration', ['full_name' => 'Admin', 'account_type' => 'customer'])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'admin']);
    }

    public function test_profile_updates_only_the_authenticated_users_personal_details(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->patchJson('/api/v1/profile', ['full_name' => 'Jordan Cruz', 'phone' => '+63 917 123 4567'])->assertOk()->assertJsonPath('full_name', 'Jordan Cruz')->assertJsonPath('phone', '+63 917 123 4567');
        $this->assertDatabaseHas('users', ['id' => $other->id, 'full_name' => $other->full_name]);
        $this->patchJson('/api/v1/profile', ['full_name' => 'Another name', 'role' => 'admin', 'email' => 'changed@example.com', 'user_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors(['role', 'email', 'user_id']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'customer', 'email' => $user->email, 'full_name' => 'Jordan Cruz']);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('avatar.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9e8AAAAASUVORK5CYII='));
    }

    public function test_profile_photos_are_local_and_require_an_expiring_link(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $url = $this->actingAs($user)->postJson('/api/v1/profile/photo', ['photo' => $this->photo()])->assertOk()->json('profile_image_url');
        $oldPath = $user->fresh()->profile_image_path;
        Storage::disk('local')->assertExists($oldPath);
        $this->assertStringStartsWith('rentique/users/'.$user->id.'/profile/', $oldPath);
        $this->get('/api/v1/profiles/'.$user->id.'/photo')->assertForbidden();
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($url.'&modified=1')->assertForbidden();
        $this->actingAs($other)->getJson('/api/v1/auth/me')->assertJsonPath('profile_image_url', null);
        $this->postJson('/api/v1/profile/photo', ['photo' => $this->photo(), 'user_id' => $user->id])->assertUnprocessable();
        $this->actingAs($user)->postJson('/api/v1/profile/photo', ['photo' => $this->photo()])->assertOk();
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($user->fresh()->profile_image_path);
        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_profile_photo_rejects_non_images_and_oversized_files(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/v1/profile/photo', ['photo' => UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\n%%EOF")])->assertUnprocessable();
        $this->postJson('/api/v1/profile/photo', ['photo' => UploadedFile::fake()->create('large.png', 6000, 'image/png')])->assertUnprocessable();
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertNull($user->fresh()->profile_image_path);
    }
}
