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

class UploadAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['firebase.credentials' => null]);
    }

    private function imageFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9e8AAAAASUVORK5CYII='));
    }

    private function productFor(Shop $shop): int
    {
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Formalwear '.$shop->id]);

        return DB::table('products')->insertGetId(['shop_id' => $shop->id, 'product_category_id' => $categoryId, 'name' => 'Evening gown', 'description' => 'Formal gown', 'daily_price_centavos' => 150000]);
    }

    public function test_shop_images_use_local_storage_and_require_public_shop_approval(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $shop = Shop::factory()->create();
        $response = $this->actingAs($shop->owner)->postJson('/api/v1/owner/shops/'.$shop->id.'/files', ['kind' => 'logo', 'file' => $this->imageFile()])->assertCreated();
        Storage::disk('local')->assertExists($response->json('path'));
        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'logo_path' => $response->json('path')]);
        $this->get($response->json('url'))->assertNotFound();
        $shop->approval_status = 'approved';
        $shop->save();
        $this->get($response->json('url'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->getJson('/api/v1/shops/'.$shop->id)->assertJsonPath('logo_url', $response->json('url'));
        $shop->approval_status = 'suspended';
        $shop->save();
        $this->get($response->json('url'))->assertNotFound();
    }

    public function test_private_documents_use_authorized_expiring_local_links(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $shop = Shop::factory()->create();
        $content = "%PDF-1.4\nPrivate business document\n%%EOF";
        $response = $this->actingAs($shop->owner)->postJson('/api/v1/owner/shops/'.$shop->id.'/files', ['kind' => 'verification', 'file' => UploadedFile::fake()->createWithContent('proof.pdf', $content)])->assertCreated()->assertJsonPath('url', null);
        Storage::disk('local')->assertExists($response->json('path'));
        $id = DB::table('shop_verification_documents')->value('id');
        $this->get('/api/v1/documents/'.$id.'/download')->assertForbidden();
        $url = $this->getJson('/api/v1/documents/'.$id)->assertOk()->json('url');
        $this->get($url)->assertOk()->assertStreamedContent($content)->assertHeader('Cache-Control', 'no-store, private');
        $this->get($url.'&modified=1')->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/documents/'.$id)->assertForbidden();
        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_product_image_paths_are_saved_and_visible_in_the_catalog(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $shop = Shop::factory()->create(['approval_status' => 'approved']);
        $productId = $this->productFor($shop);
        $response = $this->actingAs($shop->owner)->postJson('/api/v1/owner/shops/'.$shop->id.'/files', ['kind' => 'product', 'product_id' => $productId, 'file' => $this->imageFile()])->assertCreated();
        Storage::disk('local')->assertExists($response->json('path'));
        $this->assertDatabaseHas('product_images', ['product_id' => $productId, 'path' => $response->json('path')]);
        $this->get($response->json('url'))->assertOk();
        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.0.image_url', $response->json('url'));
        $this->getJson('/api/v1/products/'.$productId)->assertOk()->assertJsonPath('images.0.url', $response->json('url'));
        DB::table('products')->where('id', $productId)->update(['is_archived' => true]);
        $this->get($response->json('url'))->assertNotFound();
    }

    public function test_product_upload_cannot_attach_to_another_shops_product(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $shop = Shop::factory()->create();
        $otherShop = Shop::factory()->create();
        $productId = $this->productFor($otherShop);
        $this->actingAs($shop->owner)->postJson('/api/v1/owner/shops/'.$shop->id.'/files', ['kind' => 'product', 'product_id' => $productId, 'file' => $this->imageFile()])->assertForbidden();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_image_upload_rejects_documents_and_oversized_files(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $shop = Shop::factory()->create();
        $this->actingAs($shop->owner)->postJson('/api/v1/owner/shops/'.$shop->id.'/files', ['kind' => 'cover', 'file' => UploadedFile::fake()->createWithContent('file.pdf', "%PDF-1.4\n%%EOF")])->assertUnprocessable();
        $this->postJson('/api/v1/owner/shops/'.$shop->id.'/files', ['kind' => 'logo', 'file' => UploadedFile::fake()->create('large.png', 6000, 'image/png')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_verification_documents_are_private_to_owner_and_admin(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $shop = Shop::factory()->create();
        $id = DB::table('shop_verification_documents')->insertGetId(['shop_id' => $shop->id, 'path' => 'private/document.pdf', 'mime_type' => 'application/pdf', 'size' => 100]);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/shops/'.$shop->id.'/documents')->assertForbidden();
        $this->getJson('/api/v1/documents/'.$id)->assertForbidden();
        $this->actingAs($shop->owner)->getJson('/api/v1/shops/'.$shop->id.'/documents')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissing(['path' => 'private/document.pdf']);
    }

    public function test_other_owners_cannot_upload_shop_files(): void
    {
        $this->withoutMiddleware(FirebaseToken::class);
        $shop = Shop::factory()->create();
        $this->actingAs(User::factory()->create())->postJson('/api/v1/owner/shops/'.$shop->id.'/files')->assertForbidden();
    }
}
