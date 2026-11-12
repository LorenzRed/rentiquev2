<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ShopController extends Controller
{
    private function publicQuery(Request $request): Builder
    {
        $input = $request->validate(['search' => 'nullable|string|max:120', 'category' => 'nullable|string|max:100']);
        $query = Shop::publiclyVisible()->select(['id', 'shop_name', 'description', 'address', 'latitude', 'longitude', 'logo_path', 'contact_number', 'business_hours']);
        if (! empty($input['search'])) {
            $query->where(fn ($q) => $q->where('shop_name', 'like', '%'.$input['search'].'%')->orWhere('address', 'like', '%'.$input['search'].'%'));
        }
        if (! empty($input['category'])) {
            $query->whereIn('id', DB::table('products')->join('product_categories', 'product_categories.id', '=', 'products.product_category_id')->where('product_categories.name', $input['category'])->where('products.is_archived', false)->select('products.shop_id'));
        }

        return $query;
    }

    public function index(Request $request): LengthAwarePaginator
    {
        return $this->publicQuery($request)->orderBy('shop_name')->paginate(24);
    }

    public function show(Shop $shop): Shop
    {
        return Shop::publiclyVisible()->whereKey($shop->id)->select(['id', 'shop_name', 'description', 'address', 'latitude', 'longitude', 'contact_number', 'business_hours', 'logo_path', 'cover_path'])->firstOrFail();
    }

    public function nearby(Request $request): LengthAwarePaginator
    {
        $input = $request->validate(['latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180', 'radius' => 'nullable|numeric|min:0.1|max:200']);
        $lat = (float) $input['latitude'];
        $lng = (float) $input['longitude'];
        $query = $this->publicQuery($request);
        if (DB::getDriverName() === 'sqlite') {
            $pdo = DB::connection()->getPdo();
            foreach (['sqrt', 'sin', 'cos', 'asin', 'radians' => 'deg2rad', 'power' => 'pow'] as $name => $function) {
                $pdo->sqliteCreateFunction(is_string($name) ? $name : $function, $function, -1);
            }
        }
        if (isset($input['radius'])) {
            $delta = (float) $input['radius'] / 111.195;
            $query->whereBetween('latitude', [max(-90, $lat - $delta), min(90, $lat + $delta)]);
        }
        $distance = '6371.0088 * 2 * ASIN(SQRT(MIN(1.0, MAX(0.0, POWER(SIN(RADIANS(latitude - ?) / 2), 2) + COS(RADIANS(?)) * COS(RADIANS(latitude)) * POWER(SIN(RADIANS(longitude - ?) / 2), 2)))))';
        if (DB::getDriverName() === 'mysql') {
            $distance = str_replace(['MIN(', 'MAX('], ['LEAST(', 'GREATEST('], $distance);
        }
        $query->selectRaw($distance.' AS distance_km', [$lat, $lat, $lng]);
        if (isset($input['radius'])) {
            $query->whereRaw($distance.' <= ?', [$lat, $lat, $lng, (float) $input['radius']]);
        }

        return $query->orderBy('distance_km')->paginate(50);
    }

    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shop_name' => 'required|string|max:160', 'description' => 'required|string|max:5000', 'contact_number' => 'required|string|max:40', 'email' => 'required|email|max:255',
            'address' => 'required|string|max:255', 'province' => 'required|string|max:120', 'municipality' => 'required|string|max:120', 'barangay' => 'required|string|max:120',
            'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180', 'location_confirmed' => 'required|accepted', 'business_hours' => 'nullable|array',
        ]);
        unset($data['location_confirmed']);

        return DB::transaction(function () use ($request, $data) {
            $shop = new Shop($data);
            $shop->owner_id = $request->user()->id;
            $shop->formatted_address = $data['address'];
            $shop->location_updated_at = now();
            $shop->save();
            DB::table('shop_applications')->insert(['shop_id' => $shop->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

            return response()->json($shop, 201);
        });
    }

    public function location(Request $request, Shop $shop): Shop
    {
        Gate::authorize('update', $shop);
        $data = $request->validate(['latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180', 'formatted_address' => 'required|string|max:255', 'location_confirmed' => 'required|accepted']);
        unset($data['location_confirmed']);
        DB::transaction(function () use ($request, $shop, $data) {
            $before = $shop->only(['latitude', 'longitude', 'formatted_address']);
            $shop->fill($data);
            $shop->location_updated_at = now();
            $shop->save();
            DB::table('activity_logs')->insert(['user_id' => $request->user()->id, 'action' => 'shop.location_updated', 'subject_type' => 'shop', 'subject_id' => $shop->id, 'metadata' => json_encode(['before' => $before, 'after' => $data]), 'created_at' => now(), 'updated_at' => now()]);
        });

        return $shop;
    }

    public function review(Request $request, Shop $shop): Shop
    {
        Gate::authorize('review', $shop);
        $data = $request->validate(['status' => 'required|in:approved,rejected,suspended', 'notes' => 'nullable|string|max:2000']);

        return DB::transaction(function () use ($request, $shop, $data) {
            $shop->approval_status = $data['status'];
            $shop->save();
            if ($data['status'] === 'approved') {
                $shop->owner()->where('role', 'customer')->update(['role' => 'owner']);
            }
            DB::table('shop_applications')->where('shop_id', $shop->id)->update(['status' => $data['status'], 'reviewer_id' => $request->user()->id, 'review_notes' => $data['notes'] ?? null, 'reviewed_at' => now(), 'updated_at' => now()]);
            DB::table('activity_logs')->insert(['user_id' => $request->user()->id, 'action' => 'shop.'.$data['status'], 'subject_type' => 'shop', 'subject_id' => $shop->id, 'metadata' => json_encode($data), 'created_at' => now(), 'updated_at' => now()]);

            return $shop;
        });
    }
}
