<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagementController extends Controller
{
    public function ownerShops(Request $request): LengthAwarePaginator
    {
        return Shop::where('owner_id', $request->user()->id)->orderByDesc('id')->paginate(20);
    }

    public function ownerProducts(Request $request): LengthAwarePaginator
    {
        $data = $request->validate(['shop_id' => 'nullable|integer']);

        return DB::table('products')->whereIn('shop_id', Shop::where('owner_id', $request->user()->id)->select('id'))->when($data['shop_id'] ?? null, fn ($query, $shopId) => $query->where('shop_id', $shopId))->orderByDesc('id')->paginate(30);
    }

    public function ownerReservations(Request $request): LengthAwarePaginator
    {
        return DB::table('reservations')->whereIn('shop_id', Shop::where('owner_id', $request->user()->id)->select('id'))->orderByDesc('id')->paginate(30);
    }

    public function adminShops(Request $request): LengthAwarePaginator
    {
        abort_unless($request->user()->role === 'admin', 403);

        return Shop::orderByDesc('id')->paginate(30);
    }

    public function users(Request $request): LengthAwarePaginator
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate(['search' => 'nullable|string|max:120']);

        return User::when($data['search'] ?? null, fn ($q, $search) => $q->where('full_name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))->paginate(30);
    }

    public function userStatus(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->role === 'admin' && $user->role !== 'admin', 403);
        $data = $request->validate(['account_status' => 'required|in:active,suspended']);
        $user->account_status = $data['account_status'];
        $user->save();
        DB::table('activity_logs')->insert(['user_id' => $request->user()->id, 'action' => 'user.'.$data['account_status'], 'subject_type' => 'user', 'subject_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json($user);
    }

    public function reports(Request $request): JsonResponse
    {
        $shops = Shop::query();
        if ($request->user()->role !== 'admin') {
            $shops->where('owner_id', $request->user()->id);
        }
        $ids = $shops->pluck('id');
        $rentals = DB::table('reservations')->whereIn('shop_id', $ids);

        return response()->json(['shops' => $ids->count(), 'products' => DB::table('products')->whereIn('shop_id', $ids)->count(), 'reservations' => (clone $rentals)->count(), 'pending' => (clone $rentals)->where('status', 'pending')->count(), 'ongoing' => (clone $rentals)->where('status', 'ongoing')->count(), 'overdue' => (clone $rentals)->where('status', 'overdue')->count(), 'completed' => (clone $rentals)->where('status', 'completed')->count(), 'rental_revenue_centavos' => DB::table('payments')->whereIn('reservation_id', (clone $rentals)->select('id'))->where('kind', 'rental')->sum('amount_centavos'), 'deposits_received_centavos' => DB::table('payments')->whereIn('reservation_id', (clone $rentals)->select('id'))->where('kind', 'deposit')->sum('amount_centavos')]);
    }
}
