<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\OnlineOrderItem;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{

    public function store(Request $req)
    {
        // Logged-in ecommerce client (guard: store)
        $user = Auth::guard('store')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Validate payload (no tax/discount from client; we’ll read from products)
        $data = $req->validate([
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.product_id'         => ['required', 'integer'],
            'items.*.product_variant_id' => ['nullable', 'integer'],
            'items.*.qty'                => ['required', 'numeric', 'min:1'],
            'items.*.price'              => ['required', 'numeric', 'min:0'],
            'warehouse_id'               => ['nullable', 'integer'],
        ]);

        // Resolve warehouse: request → settings.default_warehouse_id → first warehouse
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        if (!$warehouseId) $warehouseId = (int) DB::table('store_settings')->value('default_warehouse_id');
        if ($warehouseId && !Warehouse::whereKey($warehouseId)->exists()) $warehouseId = 0;
        if (!$warehouseId) $warehouseId = (int) Warehouse::value('id');
        if (!$warehouseId) return response()->json(['error' => 'No warehouse configured.'], 422);

        // Preload product meta (TaxNet/discount/flags) and verify all exist
        $ids       = collect($data['items'])->pluck('product_id')->unique()->values();
        $products  = Product::whereIn('id', $ids)
                        ->get(['id','TaxNet','discount','discount_method','tax_method'])
                        ->keyBy('id');

        if ($products->count() !== $ids->count()) {
            $missing = $ids->diff($products->keys());
            return response()->json([
                'error'       => 'Some products not found.',
                'product_ids' => $missing->values(),
            ], 422);
        }

        // Normalize items & totals
        $normalizedItems = [];
        $subtotal = 0.0;

        foreach ($data['items'] as $i) {
            $pid   = (int) $i['product_id'];
            $pvid  = !empty($i['product_variant_id']) ? (int) $i['product_variant_id'] : null;
            $qty   = max(1, (float) $i['qty']);
            $price = round((float) $i['price'], 2);
            $line  = round($qty * $price, 2);

            // Pull tax/discount config from the product row
            $meta = $products->get($pid);
            $normalizedItems[] = [
                'product_id'         => $pid,
                'product_variant_id' => $pvid,
                'qty'                => $qty,
                'price'              => $price,

                // copied from products table
                'TaxNet'             => (float) ($meta->TaxNet ?? 0),
                'discount'           => (float) ($meta->discount ?? 0),
                'discount_method'    => (string) ($meta->discount_method ?? '1'), // '1'=percent, '2'=fixed (varchar)
                'tax_method'         => (string) ($meta->tax_method ?? '1'),      // '1'=Exclusive, '2'=Inclusive (varchar)

                'created_at'         => now(),
                'updated_at'         => now(),
            ];

            $subtotal += $line;
        }

        $grand    = round(max(0, $subtotal), 2);
        $clientId = $user->client_id ?? null;

        // Explicit date/time/status/ref on create
        $todayDate = now()->toDateString();
        $nowTime   = now()->format('H:i:s');
        $ref       = method_exists(\App\Models\OnlineOrder::class, 'generateRef')
                    ? \App\Models\OnlineOrder::generateRef()
                    : ('SO-'.now()->format('Ymd').'-'.str_pad((string)((\App\Models\OnlineOrder::max('id') ?? 0)+1), 4, '0', STR_PAD_LEFT));

        $order = DB::transaction(function () use ($clientId, $warehouseId, $grand, $normalizedItems, $todayDate, $nowTime, $ref) {
            $order = \App\Models\OnlineOrder::create([
                'date'         => $todayDate,
                'time'         => $nowTime,
                'ref'          => $ref,
                'status'       => 'pending',
                'client_id'    => $clientId,
                'warehouse_id' => $warehouseId,
                'total'        => $grand,
            ]);

            $order->items()->createMany($normalizedItems); // OnlineOrderItem boot() will set line_total
            return $order;
        });

        return response()->json([
            'id'     => $order->id,
            'ref'    => $order->ref,
            'status' => $order->status,
            'date'   => (string) $order->date,
            'time'   => (string) $order->time,
            'total'  => (float) $order->total,
        ], 201);
    }


}

