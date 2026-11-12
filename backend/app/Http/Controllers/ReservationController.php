<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\User;
use App\Notifications\ReservationUpdated;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReservationController extends Controller
{
    private function availableUnits(int $variant, string $start, string $end): Builder
    {
        $blocked = DB::table('reservation_items')->join('reservations', 'reservations.id', '=', 'reservation_items.reservation_id')->whereNotNull('inventory_unit_id')->where(function ($query) use ($start, $end) {
            $query->whereIn('reservations.status', ['ongoing', 'overdue'])->orWhere(function ($q) use ($start, $end) {
                $q->whereIn('reservations.status', ['approved', 'ready_for_pickup'])->where('starts_at', '<=', $end)->where('ends_at', '>=', $start);
            });
        })->select('inventory_unit_id');

        return DB::table('inventory_units')->where('product_variant_id', $variant)->where('status', 'available')->whereNotIn('id', $blocked);
    }

    public function availability(Request $request, int $id): JsonResponse
    {
        $dates = $request->validate(['starts_at' => 'required|date_format:Y-m-d', 'ends_at' => 'required|date_format:Y-m-d|after_or_equal:starts_at']);
        $product = DB::table('products')->where('id', $id)->where('is_archived', false)->first();
        abort_unless($product && Shop::publiclyVisible()->whereKey($product->shop_id)->exists(), 404);
        $variants = DB::table('product_variants')->where('product_id', $id)->get()->map(fn ($variant) => [...(array) $variant, 'available_quantity' => $this->availableUnits($variant->id, $dates['starts_at'], $dates['ends_at'])->count()]);

        return response()->json(['data' => $variants]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['product_variant_id' => 'required|integer|exists:product_variants,id', 'starts_at' => 'required|date_format:Y-m-d|after_or_equal:today', 'ends_at' => 'required|date_format:Y-m-d|after_or_equal:starts_at']);

        return DB::transaction(function () use ($request, $data): JsonResponse {
            $variant = DB::table('product_variants')->where('id', $data['product_variant_id'])->lockForUpdate()->first();
            $product = DB::table('products')->where('id', $variant->product_id)->where('is_archived', false)->first();
            abort_unless($product && Shop::publiclyVisible()->whereKey($product->shop_id)->exists(), 404);
            abort_if($product->shop_id && Shop::whereKey($product->shop_id)->where('owner_id', $request->user()->id)->exists(), 422, 'You cannot reserve your own inventory.');
            abort_unless($this->availableUnits($variant->id, $data['starts_at'], $data['ends_at'])->exists(), 409, 'This item is unavailable for those dates.');
            $days = (int) CarbonImmutable::parse($data['starts_at'])->diffInDays(CarbonImmutable::parse($data['ends_at'])) + 1;
            abort_if($days > 365, 422, 'A rental cannot exceed 365 calendar days.');
            $id = DB::table('reservations')->insertGetId(['customer_id' => $request->user()->id, 'shop_id' => $product->shop_id, 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'status' => 'pending', 'rental_centavos' => $days * $product->daily_price_centavos, 'deposit_centavos' => $product->deposit_centavos, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('reservation_items')->insert(['reservation_id' => $id, 'product_variant_id' => $variant->id, 'daily_price_centavos' => $product->daily_price_centavos, 'deposit_centavos' => $product->deposit_centavos, 'created_at' => now(), 'updated_at' => now()]);

            return response()->json(DB::table('reservations')->find($id), 201);
        });
    }

    public function transition(Request $request, int $id, string $action): JsonResponse
    {
        return DB::transaction(function () use ($request, $id, $action): JsonResponse {
            $reservation = DB::table('reservations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($reservation, 404);
            if ($action === 'cancel') {
                abort_unless($reservation->customer_id === $request->user()->id, 403);
                abort_unless($reservation->status === 'pending', 409, 'Only pending reservations can be cancelled.');
                $next = 'cancelled';
            } else {
                $shop = Shop::whereKey($reservation->shop_id)->lockForUpdate()->firstOrFail();
                Gate::authorize('update', $shop);
                abort_unless($shop->approval_status === 'approved' && $shop->is_active, 403);
                $transitions = ['approve' => [['pending'], 'approved'], 'reject' => [['pending'], 'rejected'], 'ready' => [['approved'], 'ready_for_pickup'], 'pickup' => [['approved', 'ready_for_pickup'], 'ongoing'], 'return' => [['ongoing', 'overdue'], 'returned'], 'complete' => [['returned'], 'completed']];
                abort_unless(isset($transitions[$action]), 404);
                [$allowed,$next] = $transitions[$action];
                abort_unless(in_array($reservation->status, $allowed, true), 409, 'This action is not allowed for the current reservation status.');
                if ($action === 'approve') {
                    abort_if($reservation->starts_at < today()->toDateString(), 409, 'The pickup date has passed.');
                    $item = DB::table('reservation_items')->where('reservation_id', $id)->first();
                    DB::table('product_variants')->where('id', $item->product_variant_id)->lockForUpdate()->first();
                    $unit = $this->availableUnits($item->product_variant_id, $reservation->starts_at, $reservation->ends_at)->lockForUpdate()->first();
                    abort_unless($unit, 409, 'Inventory is no longer available.');
                    DB::table('reservation_items')->where('id', $item->id)->update(['inventory_unit_id' => $unit->id, 'updated_at' => now()]);
                }
                if ($action === 'pickup') {
                    abort_if($reservation->starts_at > today()->toDateString(), 409, 'Pickup is before the booked start date.');
                    $items = DB::table('reservation_items')->where('reservation_id', $id)->get();
                    foreach ($items as $item) {
                        DB::table('inventory_units')->where('id', $item->inventory_unit_id)->lockForUpdate()->first();
                        $held = DB::table('reservation_items')->join('reservations', 'reservations.id', '=', 'reservation_items.reservation_id')->where('inventory_unit_id', $item->inventory_unit_id)->where('reservations.id', '!=', $id)->whereIn('reservations.status', ['ongoing', 'overdue'])->exists();
                        abort_if($held, 409, 'This inventory unit has not been returned yet.');
                    }
                    DB::table('rental_transactions')->insert(['reservation_id' => $id, 'recorded_by' => $request->user()->id, 'picked_up_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                }
                if ($action === 'return') {
                    $data = $request->validate(['condition_notes' => 'nullable|string|max:5000', 'late_fee_centavos' => 'required|integer|min:0|max:100000000', 'damage_fee_centavos' => 'required|integer|min:0|max:100000000']);
                    DB::table('rental_returns')->insert([...$data, 'reservation_id' => $id, 'recorded_by' => $request->user()->id, 'returned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            DB::table('reservations')->where('id', $id)->update(['status' => $next, 'updated_at' => now()]);
            DB::table('activity_logs')->insert(['user_id' => $request->user()->id, 'action' => 'reservation.'.$next, 'subject_type' => 'reservation', 'subject_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            User::findOrFail($reservation->customer_id)->notify(new ReservationUpdated($id, $next));

            return response()->json(DB::table('reservations')->find($id));
        });
    }

    public function payment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['amount_centavos' => 'required|integer|min:1|max:100000000', 'kind' => 'required|in:rental,deposit,late_fee,damage_fee', 'idempotency_key' => 'required|uuid']);

        return DB::transaction(function () use ($request, $id, $data): JsonResponse {
            $reservation = DB::table('reservations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($reservation, 404);
            Gate::authorize('update', Shop::findOrFail($reservation->shop_id));
            abort_if(in_array($reservation->status, ['rejected', 'cancelled'], true), 409, 'Cannot record payments for a cancelled or rejected reservation.');
            $existing = DB::table('payments')->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless($existing->reservation_id === $id && $existing->amount_centavos === $data['amount_centavos'] && $existing->kind === $data['kind'], 409, 'This payment key has already been used.');

                return response()->json($existing);
            }
            $payment = DB::table('payments')->insertGetId([...$data, 'reservation_id' => $id, 'recorded_by' => $request->user()->id, 'method' => 'cash', 'created_at' => now(), 'updated_at' => now()]);

            return response()->json(DB::table('payments')->find($payment), 201);
        });
    }
}
