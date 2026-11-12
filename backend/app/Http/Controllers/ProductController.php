<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): LengthAwarePaginator
    {
        $data = $request->validate(['search' => 'nullable|string|max:120', 'category' => 'nullable|string|max:100', 'shop_id' => 'nullable|integer', 'size' => 'nullable|string|max:30', 'min_price' => 'nullable|integer|min:0', 'max_price' => 'nullable|integer|min:0']);
        $query = DB::table('products')->join('shops', 'shops.id', '=', 'products.shop_id')->join('users', 'users.id', '=', 'shops.owner_id')->join('product_categories', 'product_categories.id', '=', 'products.product_category_id')->where('shops.approval_status', 'approved')->where('shops.is_active', true)->where('users.account_status', 'active')->where('products.is_archived', false)->select('products.*', 'shops.shop_name', 'product_categories.name as category');
        if (! empty($data['search'])) {
            $query->where('products.name', 'like', '%'.$data['search'].'%');
        }
        if (! empty($data['category'])) {
            $query->where('product_categories.name', $data['category']);
        }
        if (! empty($data['shop_id'])) {
            $query->where('products.shop_id', $data['shop_id']);
        }
        if (! empty($data['size'])) {
            $query->whereIn('products.id', DB::table('product_variants')->where('size', $data['size'])->select('product_id'));
        }
        if (isset($data['min_price'])) {
            $query->where('daily_price_centavos', '>=', $data['min_price']);
        }
        if (isset($data['max_price'])) {
            $query->where('daily_price_centavos', '<=', $data['max_price']);
        }

        $query->selectSub(DB::table('product_images')->select('id')->whereColumn('product_id', 'products.id')->orderBy('position')->orderBy('id')->limit(1), 'image_id');

        return $query->orderByDesc('products.id')->paginate(24)->through(function (object $product): object {
            $product->image_url = $product->image_id ? route('product-images.show', ['id' => $product->image_id]) : null;

            return $product;
        });
    }

    public function show(int $id): JsonResponse
    {
        $product = DB::table('products')->where('id', $id)->where('is_archived', false)->first();
        abort_unless($product && Shop::publiclyVisible()->whereKey($product->shop_id)->exists(), 404);

        $images = DB::table('product_images')->where('product_id', $id)->orderBy('position')->get()->map(function (object $image): object {
            $image->url = route('product-images.show', ['id' => $image->id]);

            return $image;
        });

        return response()->json(['product' => $product, 'variants' => DB::table('product_variants')->where('product_id', $id)->get(), 'images' => $images]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['shop_id' => 'required|integer|exists:shops,id', 'product_category_id' => 'required|integer|exists:product_categories,id', 'name' => 'required|string|max:160', 'description' => 'required|string|max:5000', 'rental_policy' => 'nullable|string|max:5000', 'daily_price_centavos' => 'required|integer|min:1|max:100000000', 'deposit_centavos' => 'required|integer|min:0|max:100000000', 'size' => 'required|string|max:30', 'color' => 'required|string|max:50', 'quantity' => 'required|integer|min:1|max:100']);
        $shop = Shop::findOrFail($data['shop_id']);
        Gate::authorize('update', $shop);
        abort_unless($shop->approval_status === 'approved' && $shop->is_active, 403, 'Your shop must be active and approved.');

        return DB::transaction(function () use ($data): JsonResponse {
            $product = collect($data)->except(['size', 'color', 'quantity'])->all();
            $id = DB::table('products')->insertGetId([...$product, 'created_at' => now(), 'updated_at' => now()]);
            $variant = DB::table('product_variants')->insertGetId(['product_id' => $id, 'size' => $data['size'], 'color' => $data['color'], 'created_at' => now(), 'updated_at' => now()]);
            for ($i = 0; $i < $data['quantity']; $i++) {
                DB::table('inventory_units')->insert(['product_variant_id' => $variant, 'code' => (string) Str::uuid(), 'status' => 'available', 'created_at' => now(), 'updated_at' => now()]);
            }

            return response()->json(['id' => $id], 201);
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = DB::table('products')->find($id);
        abort_unless($product, 404);
        Gate::authorize('update', Shop::findOrFail($product->shop_id));
        $data = $request->validate(['name' => 'sometimes|required|string|max:160', 'description' => 'sometimes|required|string|max:5000', 'daily_price_centavos' => 'sometimes|integer|min:1|max:100000000', 'deposit_centavos' => 'sometimes|integer|min:0|max:100000000', 'is_archived' => 'sometimes|boolean']);
        DB::table('products')->where('id', $id)->update([...$data, 'updated_at' => now()]);

        return response()->json(DB::table('products')->find($id));
    }
}
