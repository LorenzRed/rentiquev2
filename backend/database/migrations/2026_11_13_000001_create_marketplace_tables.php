<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('firebase_uid')->nullable()->unique();
            $table->string('full_name')->default('');
            $table->string('phone')->nullable();
            $table->string('role')->default('customer')->index();
            $table->string('account_status')->default('active');
            $table->string('profile_image_path')->nullable();
            $table->string('name')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('shop_name');
            $table->text('description');
            $table->string('contact_number');
            $table->string('email');
            $table->string('address');
            $table->string('province');
            $table->string('municipality');
            $table->string('barangay');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('formatted_address');
            $table->json('business_hours')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('approval_status')->default('pending')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamp('location_updated_at');
            $table->timestamps();
            $table->index(['latitude', 'longitude']);
        });
        Schema::create('shop_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained();
            $table->string('status')->default('pending');
            $table->foreignId('reviewer_id')->nullable()->constrained('users');
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('shop_verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained();
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('product_category_id')->constrained();
            $table->string('name');
            $table->text('description');
            $table->text('rental_policy')->nullable();
            $table->unsignedInteger('daily_price_centavos');
            $table->unsignedInteger('deposit_centavos')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('size');
            $table->string('color');
            $table->timestamps();
        });
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('path');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
        Schema::create('inventory_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained();
            $table->string('code')->unique();
            $table->string('status')->default('available');
            $table->timestamps();
        });
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('shop_id')->constrained();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('rental_centavos');
            $table->unsignedInteger('deposit_centavos');
            $table->timestamps();
            $table->index(['starts_at', 'ends_at']);
        });
        Schema::create('reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained();
            $table->foreignId('product_variant_id')->constrained();
            $table->foreignId('inventory_unit_id')->nullable()->constrained();
            $table->unsignedInteger('daily_price_centavos');
            $table->unsignedInteger('deposit_centavos');
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained();
            $table->foreignId('recorded_by')->constrained('users');
            $table->unsignedInteger('amount_centavos');
            $table->string('kind');
            $table->string('method')->default('cash');
            $table->uuid('idempotency_key')->unique();
            $table->timestamps();
        });
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained();
            $table->unsignedInteger('amount_centavos');
            $table->string('reason');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('rental_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->unique();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamp('picked_up_at');
            $table->timestamps();
        });
        Schema::create('rental_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->unique();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamp('returned_at');
            $table->text('condition_notes')->nullable();
            $table->unsignedInteger('late_fee_centavos')->default(0);
            $table->unsignedInteger('damage_fee_centavos')->default(0);
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->unique();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('shop_id')->constrained();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('token', 512)->unique();
            $table->string('platform');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['device_tokens', 'system_settings', 'activity_logs', 'announcements', 'reviews', 'notifications', 'rental_returns', 'rental_transactions', 'refunds', 'payments', 'reservation_items', 'reservations', 'inventory_units', 'product_images', 'product_variants', 'products', 'product_categories', 'shop_verification_documents', 'shop_applications', 'shops'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['firebase_uid', 'full_name', 'phone', 'role', 'account_status', 'profile_image_path']));
    }
};
