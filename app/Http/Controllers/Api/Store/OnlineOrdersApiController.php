<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Unit;
use App\Models\product_warehouse;
use DB;
use Auth;
use Illuminate\Http\Request;

class OnlineOrdersApiController extends Controller
{
    /**
     * GET /store/orders
     * Query: page, per_page, q (Ref or customer), from, to, sort, dir
     * Note: online orders don’t have per-row status yet — we return "ordered".
     */
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);
        $q    = trim((string) $request->query('q', ''));
        $from = $request->query('from');            // YYYY-MM-DD
        $to   = $request->query('to');              // YYYY-MM-DD
        $sort = $request->query('sort', 'created_at');
        $dir  = $request->query('dir', 'desc');
        $per  = max(1, min(200, (int) $request->query('per_page', 10)));

        $allowedSort = ['date', 'created_at', 'total', 'ref', 'id'];

        $orders = OnlineOrder::query()
            ->with('client')
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('ref', 'like', "%{$q}%")
                      ->orWhereHas('client', function ($c) use ($q) {
                          $c->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%");
                      });
                });
            })
            ->when($from, fn ($qq) => $qq->whereDate('date', '>=', $from))
            ->when($to,   fn ($qq) => $qq->whereDate('date', '<=', $to))
            ->when(in_array($sort, $allowedSort, true),
                   fn ($qq) => $qq->orderBy($sort, $dir),
                   fn ($qq) => $qq->latest())
            ->paginate($per);

        $data = $orders->getCollection()->map(function (OnlineOrder $o) {
            return [
                'id'            => $o->id,
                'code'          => $o->ref,
                'customer_name' => optional($o->client)->name,
                'status'        => $o->status,
                'total'         => (float) $o->total,
                'created_at'    => optional($o->created_at)->toDateTimeString() ?? (string) $o->date,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $orders->total(),
                'page'  => $orders->currentPage(),
                'pages' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * GET /store/orders/{id}
     * Returns header + items for details view
     */
   
    public function show(Request $request, $id)
    {
         $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);
        $order = OnlineOrder::with(['items.product', 'items.productVariant', 'client', 'warehouse'])
            ->findOrFail($id);

        $subtotal = (float) $order->items->sum(function ($i) {
            return (float) $i->price * (float) $i->qty;
        });

        return response()->json([
            'id'              => $order->id,
            'code'            => $order->ref,
            'status'          => $order->status,
            'shipping_status' => null,
            'customer_name'   => optional($order->client)->name,
            'customer_email'  => optional($order->client)->email,
            'customer_phone'  => optional($order->client)->phone,

            // NEW
            'warehouse_id'    => $order->warehouse_id,
            'warehouse_name'  => optional($order->warehouse)->name,

            'subtotal'        => $subtotal,
            'shipping'        => 0.0,
            'discount'        => 0.0,
            'total'           => (float) $order->total,

            'items'           => $order->items->map(function ($d) {
                $name    = optional($d->product)->name ?? ('#' . $d->product_id);
                $variant = optional($d->productVariant)->name;
                return [
                    'id'                 => $d->id,
                    'product_id'         => $d->product_id,
                    'product_variant_id' => $d->product_variant_id,
                    'name'               => $variant ? ($name . ' - ' . $variant) : $name,
                    'qty'                => (float) $d->qty,
                    'price'              => (float) $d->price,
                    'line_total'         => (float) $d->line_total,
                ];
            })->values(),
        ]);
    }



    public function update(Request $request, $id)
    {
         $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);
        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled',
        ]);

        $order = OnlineOrder::with(['items'])->findOrFail($id);
        $newStatus = $data['status'];

        // Fast path: no-op
        if ($order->status === $newStatus) {
            return response()->json(['ok' => true, 'status' => $order->status]);
        }

        // ---- CANCEL (only from pending) ----
        if ($newStatus === 'cancelled') {
            if ($order->status !== 'pending') {
                return response()->json(['error' => 'Only pending orders can be cancelled.'], 422);
            }
            $order->status = 'cancelled';
            $order->save();

            return response()->json(['ok' => true, 'status' => 'cancelled']);
        }

        // ---- CONFIRM (only from pending) → create Sale, decrement stock ----
        if ($newStatus === 'confirmed') {
            if ($order->status !== 'pending') {
                return response()->json(['error' => 'Only pending orders can be confirmed.'], 422);
            }

            // Resolve warehouse: order → settings.default_warehouse_id → first warehouse
            $warehouseId = (int) ($order->warehouse_id ?: (DB::table('store_settings')->value('default_warehouse_id') ?: 0));
            if (!$warehouseId) $warehouseId = (int) Warehouse::value('id');
            if (!$warehouseId) {
                return response()->json(['error' => 'No warehouse configured.'], 422);
            }

            // Pre-check stock (outside tx just to compile the list; final check in tx w/ locks)
            $insufficient = [];
            foreach ($order->items as $it) {
                $product = Product::find($it->product_id);
                $unit    = $this->saleUnitForProduct($product); // may be null
                $need    = $this->requiredBaseQty($it->qty, $unit);

                $pw = product_warehouse::where('deleted_at', '=', null)
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $it->product_id)
                    ->when($it->product_variant_id, function ($q) use ($it) {
                        $q->where('product_variant_id', $it->product_variant_id);
                    })
                    ->first();

                $have = $pw ? (float) $pw->qte : 0.0;
                if ($have < $need) {
                    $name = $product ? $product->name : ('#' . $it->product_id);
                    $insufficient[] = [
                        'product_id'         => $it->product_id,
                        'product_variant_id' => $it->product_variant_id,
                        'name'               => $name,
                        'required'           => $need,
                        'available'          => $have,
                    ];
                }
            }

            if (!empty($insufficient)) {
                return response()->json([
                    'error' => 'Insufficient stock for one or more items.',
                    'items' => $insufficient,
                ], 422);
            }

            // Transaction: final check WITH LOCKS, create Sale, details, decrement stock
            $sale = DB::transaction(function () use ($order, $warehouseId) {

                // Final stock check with row locks
                foreach ($order->items as $it) {
                    $product = Product::find($it->product_id);
                    $unit    = $this->saleUnitForProduct($product);
                    $need    = $this->requiredBaseQty($it->qty, $unit);

                    $pw = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $warehouseId)
                        ->where('product_id', $it->product_id)
                        ->when($it->product_variant_id, function ($q) use ($it) {
                            $q->where('product_variant_id', $it->product_variant_id);
                        })
                        ->lockForUpdate()
                        ->first();

                    $have = $pw ? (float) $pw->qte : 0.0;
                    if ($have < $need) {
                        // Concurrency safety: bail here
                        $name = $product ? $product->name : ('#' . $it->product_id);
                        throw new \RuntimeException("Insufficient stock for {$name} (need {$need}, have {$have}).");
                    }
                }

                // Create Sale header
                $sale = Sale::create([
                    'date'            => $order->date ?: now()->toDateString(),
                    'time'            => $order->time ?: now()->format('H:i:s'),
                    'Ref'             => $this->getNumberOrder(),
                    'is_pos'          => 0,
                    'client_id'       => $order->client_id,
                    'warehouse_id'    => $warehouseId,
                    'statut'          => 'completed',
                    'shipping_status' => null,

                    'discount'        => 0,
                    'shipping'        => 0,
                    'TaxNet'          => 0,
                    'tax_rate'        => 0,
                    'GrandTotal'      => (float) $order->total,

                    'paid_amount'     => 0,
                    'payment_statut'  => 'unpaid',
                    'notes'           => null,
                    'user_id'         => optional(Auth::user())->id, // admin who confirmed
                ]);

                // Create details + decrement stock
                foreach ($order->items as $it) {
                    $product = Product::find($it->product_id);
                    $unit    = $this->saleUnitForProduct($product);
                    $need    = $this->requiredBaseQty($it->qty, $unit);

                    // Pull meta from the order item; fallback to product fields if missing
                    $taxNet          = isset($it->TaxNet) ? (float)$it->TaxNet : (float)($product->TaxNet ?? 0);
                    $taxMethod       = $it->tax_method ?? ($product->tax_method ?? null);        // '1' Exclusive, '2' Inclusive
                    $discount        = isset($it->discount) ? (float)$it->discount : (float)($product->discount ?? 0);
                    $discountMethod  = $it->discount_method ?? ($product->discount_method ?? null); // '1' % , '2' fixed

                    // IMPORTANT: $it->price is already the final unit price the customer saw
                    // (your frontend sent display_price). So we DO NOT recompute tax/discount here.
                    $unitPrice = (float) $it->price;
                    $qty       = (float) $it->qty;

                    SaleDetail::create([
                        'date'               => $sale->date,
                        'sale_id'            => $sale->id,
                        'sale_unit_id'       => $unit ? $unit->id : null,
                        'quantity'           => $qty,
                        'price'              => $unitPrice,               // final unit price used for payment
                        'TaxNet'             => $taxNet,                  // <-- from online_order_items (fallback product)
                        'tax_method'         => $taxMethod,               // <-- from online_order_items (fallback product)
                        'discount'           => $discount,                // <-- from online_order_items (fallback product)
                        'discount_method'    => $discountMethod,          // <-- from online_order_items (fallback product)
                        'product_id'         => (int) $it->product_id,
                        'product_variant_id' => $it->product_variant_id ?: null,
                        'total'              => round($unitPrice * $qty, 2), // no extra tax/discount here to avoid double-counting
                    ]);

                    // decrement stock (same as before)
                    $pw = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $warehouseId)
                        ->where('product_id', $it->product_id)
                        ->when($it->product_variant_id, function ($q) use ($it) {
                            $q->where('product_variant_id', $it->product_variant_id);
                        })
                        ->lockForUpdate()
                        ->first();

                    if ($pw) {
                        $pw->qte = max(0, (float) $pw->qte - $need);
                        $pw->save();
                    }
                }

                // Flip online order status
                $order->status = 'confirmed';
                $order->save();

                return $sale;
            });

            return response()->json([
                'ok'       => true,
                'status'   => 'confirmed',
                'sale_id'  => $sale->id,
                'sale_ref' => $sale->Ref,
            ]);
        }

        // Fallback (shouldn’t hit)
        return response()->json(['error' => 'Unsupported transition.'], 422);
    }

    /**
     * Convert a sold quantity into "base" stock quantity by applying the sale unit operator.
     * Mirrors your normal sale logic:
     * - if unit.operator == '/' → qty / unit.operator_value
     * - else                   → qty * unit.operator_value
     */
    protected function requiredBaseQty($qty, ?Unit $unit): float
    {
        $q = (float) $qty;
        if (!$unit) return $q;
        $op = $unit->operator ?? '*';
        $v  = (float) ($unit->operator_value ?: 1);
        if ($v <= 0) $v = 1;
        return $op === '/' ? ($q / $v) : ($q * $v);
    }

    /**
     * Best-effort: pick a sale unit from the product if present.
     * Tries common attribute names safely; returns null if none.
     */
    protected function saleUnitForProduct(?Product $p): ?Unit
    {
        if (!$p) return null;
        $unitId = $p->sale_unit_id ?? $p->unit_sale_id ?? $p->unit_id ?? null;
        return $unitId ? Unit::find($unitId) : null;
    }


    public function getNumberOrder()
    {
        // Get the last sale with a reference that starts with 'SL_'
        $last = DB::table('sales')
            ->where('Ref', 'like', 'SL_%')
            ->latest('id')
            ->first();
    
        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode("_", $item);
    
            // Ensure valid structure before processing
            if (isset($nwMsg[1]) && is_numeric($nwMsg[1])) {
                $inMsg = $nwMsg[1] + 1;
                $code = $nwMsg[0] . '_' . $inMsg;
            } else {
                $code = 'SL_1111'; // Fallback if reference is corrupted
            }
        } else {
            $code = 'SL_1111';
        }
    
        return $code;
    }
}
