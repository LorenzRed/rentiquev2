<?php

use App\Http\Controllers\GeocodingController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\UploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('shops', [ShopController::class, 'index']);
    Route::get('geocode', GeocodingController::class)->middleware('throttle:10,1');
    Route::get('shops/nearby', [ShopController::class, 'nearby']);
    Route::get('shops/{shop}', [ShopController::class, 'show']);
    Route::get('shops/{shop}/images/{kind}', [UploadController::class, 'shopImage'])->where('kind', 'logo|cover')->name('shops.image');
    Route::get('product-images/{id}', [UploadController::class, 'productImage'])->name('product-images.show');
    Route::get('documents/{id}/download', [UploadController::class, 'downloadDocument'])->middleware('signed')->name('documents.download');
    Route::get('profiles/{id}/photo', [ProfileController::class, 'photo'])->middleware('signed')->name('profile.photo');
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{id}/availability', [ReservationController::class, 'availability']);
    Route::get('products/{id}', [ProductController::class, 'show']);
    Route::get('categories', fn () => DB::table('product_categories')->orderBy('name')->get());
    Route::middleware('firebase')->group(function () {
        Route::post('auth/sync', fn (Request $request) => $request->user());
        Route::get('auth/me', fn (Request $request) => $request->user());
        Route::post('auth/complete-registration', [ProfileController::class, 'completeRegistration']);
        Route::patch('profile', [ProfileController::class, 'update']);
        Route::post('profile/photo', [ProfileController::class, 'uploadPhoto']);
        Route::get('owner/shops', [ManagementController::class, 'ownerShops']);
        Route::get('owner/products', [ManagementController::class, 'ownerProducts']);
        Route::get('owner/reservations', [ManagementController::class, 'ownerReservations']);
        Route::get('owner/reports', [ManagementController::class, 'reports']);
        Route::post('owner/products', [ProductController::class, 'store']);
        Route::post('owner/shops/{shop}/files', [UploadController::class, 'store']);
        Route::get('shops/{shop}/documents', [UploadController::class, 'documents']);
        Route::get('documents/{id}', [UploadController::class, 'document']);
        Route::patch('owner/products/{id}', [ProductController::class, 'update']);
        Route::post('customer/reservations', [ReservationController::class, 'store']);
        Route::patch('customer/reservations/{id}/{action}', [ReservationController::class, 'transition'])->where('action', 'cancel');
        Route::patch('owner/reservations/{id}/{action}', [ReservationController::class, 'transition'])->where('action', 'approve|reject|ready|pickup|return|complete');
        Route::post('owner/reservations/{id}/payments', [ReservationController::class, 'payment']);
        Route::get('admin/shops', [ManagementController::class, 'adminShops']);
        Route::get('admin/users', [ManagementController::class, 'users']);
        Route::patch('admin/users/{user}/status', [ManagementController::class, 'userStatus']);
        Route::get('notifications', fn (Request $request) => $request->user()->notifications()->paginate(30));
        Route::post('owner/shop-applications', [ShopController::class, 'apply']);
        Route::patch('owner/shops/{shop}/location', [ShopController::class, 'location']);
        Route::patch('admin/shops/{shop}/status', [ShopController::class, 'review']);
        Route::get('customer/reservations', fn (Request $request) => DB::table('reservations')->where('customer_id', $request->user()->id)->orderByDesc('id')->paginate(20));
    });
});
