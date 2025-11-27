<?php

namespace App\Http\Controllers;
use Twilio\Rest\Client as Client_Twilio;
use GuzzleHttp\Client as Client_guzzle;
use GuzzleHttp\Client as Client_termi;
use App\Models\SMSMessage;
use Infobip\Api\SendSmsApi;
use Infobip\Configuration;
use Infobip\Model\SmsAdvancedTextualRequest;
use Infobip\Model\SmsDestination;
use Infobip\Model\SmsTextualMessage;
use Illuminate\Support\Str;
use App\Models\EmailMessage;
use App\Mail\CustomEmail;
use App\Models\Account;

use App\Models\PaymentMethod;
use App\Mail\SaleMail;
use App\Models\Client;
use App\Models\Unit;
use App\Models\PaymentSale;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\product_warehouse;
use App\Models\Quotation;
use App\Models\Shipment;
use App\Models\sms_gateway;
use App\Models\Role;
use App\Models\SaleReturn;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Setting;
use App\Models\PosSetting;
use App\Models\SaleBarcodeScan;
use App\Models\PurchaseBarcodeScan;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use App\utils\helpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Stripe;
use App\Models\PaymentWithCreditCard;
use DB;
use PDF;
use ArPHP\I18N\Arabic;

class SalesController extends BaseController
{

    //---------------- Scan Barcode for Sale Detail ----------------\\

    public function scanBarcode(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', Sale::class);

        $request->validate([
            'sale_detail_id' => 'required|exists:sale_details,id',
            'barcode' => 'required|string',
            'type' => 'nullable|in:indoor,outdoor',
        ]);

        $detail = SaleDetail::with('product.category')->findOrFail($request->sale_detail_id);
        $sale = Sale::findOrFail($detail->sale_id);

        $product = Product::where('code', $detail->product->code)->first();
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product code not found.'], 404);
        }

        // Barcode must start with product code
        if (strpos($request->barcode, $product->code) !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid barcode. It does not match product code series.'
            ], 400);
        }

        // Check if barcode exists in purchase stock
        $existsInWarehouse = PurchaseBarcodeScan::where('barcode', $request->barcode)->exists();

        if (!$existsInWarehouse) {
            return response()->json([
                'success' => false,
                'message' => 'This barcode does not exist in warehouse stock.'
            ], 400);
        }

        // Warehouse product
        $warehouseProduct = product_warehouse::firstOrCreate([
            'product_id' => $product->id,
            'warehouse_id' => $sale->warehouse_id,
        ], [
            'qte' => 0,
            'manage_stock' => 1
        ]);

        $categoryCode = $detail->product->category->code ?? null;

        // Must be released
        if (!in_array($sale->statut, ['released'])) {
            return response()->json(['success' => false, 'message' => 'Sale must be released to scan barcodes.'], 400);
        }

        // Check duplicate barcode
        $existingBarcode = SaleBarcodeScan::where('barcode', $request->barcode)
            ->where('sale_detail_id', $detail->id)
            ->exists();

        if ($existingBarcode) {
            return response()->json(['success' => false, 'message' => 'This barcode has already been scanned.'], 400);
        }

        // ============================
        // CATEGORY 123 (two scans = one unit)
        // ============================
        if ($categoryCode == '123') {

            if (!$request->type) {
                return response()->json(['success' => false, 'message' => 'Type (indoor/outdoor) is required for this category.'], 400);
            }

            $indoorScans = SaleBarcodeScan::where('sale_detail_id', $detail->id)
                ->where('type', 'indoor')
                ->count();
            $outdoorScans = SaleBarcodeScan::where('sale_detail_id', $detail->id)
                ->where('type', 'outdoor')
                ->count();

            $maxQty = $detail->quantity;

            if ($request->type == 'indoor' && $indoorScans >= $maxQty) {
                return response()->json(['success' => false, 'message' => 'Cannot scan more indoor barcodes than ordered quantity.'], 400);
            }

            if ($request->type == 'outdoor' && $outdoorScans >= $maxQty) {
                return response()->json(['success' => false, 'message' => 'Cannot scan more outdoor barcodes than ordered quantity.'], 400);
            }

            $oldUnits = floor(($indoorScans + $outdoorScans) / 2);

            // Add Sale Scan
            SaleBarcodeScan::create([
                'sale_detail_id' => $detail->id,
                'barcode' => $request->barcode,
                'type' => $request->type,
            ]);

            // Update counters
            $indoorScans += $request->type == 'indoor' ? 1 : 0;
            $outdoorScans += $request->type == 'outdoor' ? 1 : 0;

            $totalScans = $indoorScans + $outdoorScans;
            $newUnits = floor($totalScans / 2);

            $decrement = $oldUnits - $newUnits;

            if ($decrement < 0) {
                // Only decrement stock when 1 complete unit is scanned (2 barcodes)
                $warehouseProduct->qte -= abs($decrement);
                $warehouseProduct->save();
            }

            return response()->json(['success' => true, 'message' => 'Barcode scanned successfully.', 'released_quantity' => $newUnits]);
        }

        // ============================
        // NORMAL PRODUCT (each scan = one unit)
        // ============================
        else {

            $existingScans = SaleBarcodeScan::where('sale_detail_id', $detail->id)->count();

            if ($existingScans >= $detail->quantity) {
                return response()->json(['success' => false, 'message' => 'Cannot scan more barcodes than ordered quantity.'], 400);
            }

            // Add scan
            SaleBarcodeScan::create([
                'sale_detail_id' => $detail->id,
                'barcode' => $request->barcode,
                'type' => null,
            ]);

            // DECREMENT WAREHOUSE
            $warehouseProduct->qte -= 1;
            $warehouseProduct->save();

            return response()->json(['success' => true, 'message' => 'Barcode scanned successfully.', 'released_quantity' => $existingScans + 1]);
        }
    }



    //------------- GET ALL SALES -----------\\

public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Sale::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        // How many items do you want to display.
        $perPage = $request->limit;

        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();
        // Filter fields With Params to retrieve
        $param = array(
            0 => 'like',
            1 => 'like',
            2 => '=',
            3 => 'like',
            4 => '=',
            5 => '=',
            6 => 'like',
        );
        $columns = array(
            0 => 'Ref',
            1 => 'statut',
            2 => 'client_id',
            3 => 'payment_statut',
            4 => 'warehouse_id',
            5 => 'date',
            6 => 'shipping_status',
        );
        $data = array();

        // Check If User Has Permission View  All Records
        $Sales = Sale::with('facture', 'client', 'warehouse','user', 'details.product.category')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });
        //Multiple Filter
        $Filtred = $helpers->filter($Sales, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('GrandTotal', $request->search)
                        ->orWhere('payment_statut', 'like', "%{$request->search}%")
                        ->orWhere('shipping_status', 'like', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Filtred->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        
        $Sales = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($Sales as $Sale) {
            
            $item['id']   = $Sale['id'];
            $item['date'] = $Sale['date'] . ' ' . $Sale['time'];
            $item['Ref']  = $Sale['Ref'];
            $item['created_by'] = $Sale['user']->username;
            $item['statut'] = $Sale['statut'];
            $item['shipping_status'] =  $Sale['shipping_status'];
            $item['discount'] = $Sale['discount'];
            $item['shipping'] = $Sale['shipping'];
            $item['warehouse_name'] = $Sale['warehouse']['name'];
            $item['client_id'] = $Sale['client']['id'];
            $item['client_name'] = $Sale['client']['name'];
            $item['client_email'] = $Sale['client']['email'];
            $item['client_tele'] = $Sale['client']['phone'];
            $item['client_code'] = $Sale['client']['code'];
            $item['client_adr'] = $Sale['client']['adresse'];
            $item['GrandTotal'] = number_format($Sale['GrandTotal'], 2, '.', '');
            $item['paid_amount'] = number_format($Sale['paid_amount'], 2, '.', '');
            $item['due'] = number_format($item['GrandTotal'] - $item['paid_amount'], 2, '.', '');
            $item['payment_status'] = $Sale['payment_statut'];

            if (SaleReturn::where('sale_id', $Sale['id'])->where('deleted_at', '=', null)->exists()) {
                $sellReturn = SaleReturn::where('sale_id', $Sale['id'])->where('deleted_at', '=', null)->first();
                $item['salereturn_id'] = $sellReturn->id;
                $item['sale_has_return'] = 'yes';
            }else{
                $item['sale_has_return'] = 'no';
            }
            $category_codes = $Sale->details->pluck('product.category.code')->unique()->filter()->values();

            $item['category_codes'] = $category_codes;
            // Aggregate unique category names of products in the sale details
            $categories = $Sale->details->pluck('product.category.name')->unique()->filter()->values()->all();
            $item['categories'] = implode(', ', $categories);

            // Calculate total scanned/released quantity
            $total_scanned = 0;
            foreach ($Sale->details as $detail) {
                $scans = SaleBarcodeScan::where('sale_detail_id', $detail->id)->count();
                $category_code = $detail->product->category->code ?? null;
                if ($category_code == '123') {
                    $total_scanned += floor($scans / 2);
                } else {
                    $total_scanned += $scans;
                }
            }
            $item['total_scanned_quantity'] = $total_scanned;
            
            $item['details'] = $Sale->details->map(function($d) {

                $name = $d->product_variant_id ? '[' . $d->productVariant->name . '] ' . $d->product->name : $d->product->name;

                $category_code = $d->product->category->code ?? null;

                return [

                    'id' => $d->id,

                    'name' => $name,

                    'code' => $d->product->code,

                    'category_code' => $category_code,

                ];

            });

            $data[] = $item;
        }
        
        $stripe_key = config('app.STRIPE_KEY');
        $customers = client::where('deleted_at', '=', null)->get(['id', 'name']);
        $accounts = Account::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id','account_name']);
        $payment_methods = PaymentMethod::whereNull('deleted_at')->get(['id', 'name']);

       //get warehouses assigned to user
       $user_auth = auth()->user();
       if($user_auth->is_all_warehouses){
           $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
       }else{
           $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
           $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
       }

        return response()->json([
            'stripe_key' => $stripe_key,
            'totalRows' => $totalRows,
            'sales' => $data,
            'customers' => $customers,
            'warehouses' => $warehouses,
            'accounts' => $accounts,
            'payment_methods' => $payment_methods,
        ]);
    }

    //------------- STORE NEW SALE-----------\\

    public function store(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'create', Sale::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
        ]);

        \DB::transaction(function () use ($request) {
            $helpers = new helpers();
            $order = new Sale;

            $order->is_pos = 0;
            $order->date = $request->date;
            $order->time = now()->toTimeString();
            $order->Ref = $this->getNumberOrder();
            $order->client_id = $request->client_id;
            $order->GrandTotal = $request->GrandTotal;
            $order->warehouse_id = $request->warehouse_id;
            $order->tax_rate = $request->tax_rate;
            $order->TaxNet = $request->TaxNet;
            $order->discount = $request->discount;
            $order->shipping = $request->shipping;
            $order->statut = $request->statut;
            $order->payment_statut = 'unpaid';
            $order->notes = $request->notes;
            $order->user_id = Auth::user()->id;
            $order->save();

            $data = $request['details'];
            $total_points_earned = 0;
            foreach ($data as $key => $value) {

                $product = Product::find($value['product_id']);
                $unit = Unit::where('id', $value['sale_unit_id'])->first();
                $total_points_earned += $value['quantity'] * $product->points;

                $orderDetails[] = [
                    'date'         => $request->date,
                    'sale_id'      => $order->id,
                    'sale_unit_id' => $value['sale_unit_id']?$value['sale_unit_id']:NULL,
                    'quantity'     => $value['quantity'],
                    'price'        => $value['Unit_price'],
                    'TaxNet'       => $value['tax_percent'],
                    'tax_method'   => $value['tax_method'],
                    'discount'     => $value['discount'],
                    'discount_method'    => $value['discount_Method'],
                    'product_id'         => $value['product_id'],
                    'product_variant_id' => $value['product_variant_id']?$value['product_variant_id']:NULL,
                    'total'              => $value['subtotal'],
                    'imei_number'        => $value['imei_number'],
                ];


                if ($order->statut == "completed") {
                    if ($value['product_variant_id'] !== null) {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $order->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($unit && $product_warehouse) {
                            if ($unit->operator == '/') {
                                $product_warehouse->qte -= $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse->qte -= $value['quantity'] * $unit->operator_value;
                            }
                            $product_warehouse->save();
                        }

                    } else {
                        $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                            ->where('warehouse_id', $order->warehouse_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($unit && $product_warehouse) {
                            if ($unit->operator == '/') {
                                $product_warehouse->qte -= $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_warehouse->qte -= $value['quantity'] * $unit->operator_value;
                            }
                            $product_warehouse->save();
                        }
                    }
                }
            }
            SaleDetail::insert($orderDetails);

            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');

            if ($request->payment['status'] != 'pending') {
                $sale = Sale::findOrFail($order->id);
                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === sale->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $sale);
                }


                try {

                    $total_paid = $sale->paid_amount + $request['amount'];
                    $due = $sale->GrandTotal - $total_paid;
                    
                    if ($due === 0.0 || $due < 0.0) {
                        $payment_statut = 'paid';
                    } else if ($due != $sale->GrandTotal) {
                        $payment_statut = 'partial';
                    } else if ($due == $sale->GrandTotal) {
                        $payment_statut = 'unpaid';
                    }
                    
                    if($request['amount'] > 0 && $request->payment['status'] != 'pending'){
                        if ($request->payment['payment_method_id'] == 1 || $request->payment['payment_method_id'] == '1') {
                            $Client = Client::whereId($request->client_id)->first();
                            Stripe\Stripe::setApiKey(config('app.STRIPE_SECRET'));
    
                            // Check if the payment record exists
                            $PaymentWithCreditCard = PaymentWithCreditCard::where('customer_id', $request->client_id)->first();
                            if (!$PaymentWithCreditCard) {
    
                                // Create a new customer and charge the customer with a new credit card
                                $customer = \Stripe\Customer::create([
                                    'source' => $request->token,
                                    'email'  => $Client->email,
                                    'name'   => $Client->name,
                                ]);
    
                                // Charge the Customer instead of the card:
                                $charge = \Stripe\Charge::create([
                                    'amount'   => $request['amount'] * 100,
                                    'currency' => 'usd',
                                    'customer' => $customer->id,
                                ]);
                                $PaymentCard['customer_stripe_id'] = $customer->id;
    
                            // Check if the payment record not exists
                            } else {
    
                                 // Retrieve the customer ID and card ID
                                $customer_id = $PaymentWithCreditCard->customer_stripe_id;
                                $card_id = $request->card_id;
    
                                // Charge the customer with the new credit card or the selected card
                                if ($request->is_new_credit_card || $request->is_new_credit_card == 'true' || $request->is_new_credit_card === 1) {
                                    // Retrieve the customer
                                    $customer = \Stripe\Customer::retrieve($customer_id);
    
                                    // Create New Source
                                    $card = \Stripe\Customer::createSource(
                                        $customer_id,
                                        [
                                          'source' => $request->token,
                                        ]
                                      );
    
                                    $charge = \Stripe\Charge::create([
                                        'amount'   => $request['amount'] * 100,
                                        'currency' => 'usd',
                                        'customer' => $customer_id,
                                        'source'   => $card->id,
                                    ]);
                                    $PaymentCard['customer_stripe_id'] = $customer_id;
    
                                } else {
                                    $charge = \Stripe\Charge::create([
                                        'amount'   => $request['amount'] * 100,
                                        'currency' => 'usd',
                                        'customer' => $customer_id,
                                        'source'   => $card_id,
                                    ]);
                                    $PaymentCard['customer_stripe_id'] = $customer_id;
                                }
                            }
    
                            $PaymentSale            = new PaymentSale();
                            $PaymentSale->sale_id   = $order->id;
                            $PaymentSale->Ref       = app('App\Http\Controllers\PaymentSalesController')->getNumberOrder();
                            $PaymentSale->date      = Carbon::now();
                            $PaymentSale->payment_method_id = $request->payment['payment_method_id'];
                            $PaymentSale->montant   = $request['amount'];
                            $PaymentSale->change    = $request['change'];
                            $PaymentSale->notes     = NULL;
                            $PaymentSale->user_id   = Auth::user()->id;
                            $PaymentSale->account_id   = $request->payment['account_id']?$request->payment['account_id']:NULL;
                            $PaymentSale->save();

                            $account = Account::where('id', $request->payment['account_id'])->exists();

                            if ($account) {
                                // Account exists, perform the update
                                $account = Account::find($request->payment['account_id']);
                                $account->update([
                                    'balance' => $account->balance + $request['amount'],
                                ]);
                            }
    
                            $sale->update([
                                'paid_amount'    => $total_paid,
                                'payment_statut' => $payment_statut,
                            ]);
    
                            $PaymentCard['customer_id'] = $request->client_id;
                            $PaymentCard['payment_id']  = $PaymentSale->id;
                            $PaymentCard['charge_id']   = $charge->id;
                            PaymentWithCreditCard::create($PaymentCard);
    
                            // Paying Method Cash
                        } else {
    
                            PaymentSale::create([
                                'sale_id' => $order->id,
                                'Ref' => app('App\Http\Controllers\PaymentSalesController')->getNumberOrder(),
                                'date' => Carbon::now(),
                                'account_id' => $request->payment['account_id']?$request->payment['account_id']:NULL,
                                'payment_method_id' => $request->payment['payment_method_id'],
                                'montant' => $request['amount'],
                                'change' => $request['change'],
                                'notes' => NULL,
                                'user_id' => Auth::user()->id,
                            ]);

                            $account = Account::where('id', $request->payment['account_id'])->exists();

                            if ($account) {
                                // Account exists, perform the update
                                $account = Account::find($request->payment['account_id']);
                                $account->update([
                                    'balance' => $account->balance + $request['amount'],
                                ]);
                            }
    
                            $sale->update([
                                'paid_amount' => $total_paid,
                                'payment_statut' => $payment_statut,
                            ]);
                        }
    
                    }
                } catch (Exception $e) {
                    return response()->json(['message' => $e->getMessage()], 500);
                }
                
            }

            // 🪙 Points logic
            $client = Client::find($request->client_id);
            $used_points = $request->used_points ?? 0;
            $discount_from_points = $request->discount_from_points ?? 0;
            $earned_points = 0;

            if ($client && ($client->is_royalty_eligible == 1 || $client->is_royalty_eligible || $client->is_royalty_eligible === 1)) {

                // Deduct used points if valid
                if ($used_points > 0 && $client->points >= $used_points) {
                    $client->decrement('points',  $used_points);
                }

                 // Earn points
                $earned_points = $total_points_earned;

                $client->increment('points', $earned_points);

                $order_used_points = $used_points;
                $order_earned_points = $earned_points;
                $order_discount_from_points = $discount_from_points;
            } else {
                $order_used_points = 0;
                $order_earned_points = 0;
                $order_discount_from_points = 0;
            }

            $order->update([
                'used_points'           => $order_used_points,
                'earned_points'         => $order_earned_points,
                'discount_from_points'  => $order_discount_from_points,
            ]);


        }, 10);

        return response()->json(['success' => true]);
    }


    //------------- UPDATE SALE -----------

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Sale::class);

        request()->validate([
            'warehouse_id' => 'required',
            'client_id'    => 'required',
        ]);

        \DB::transaction(function () use ($request, $id) {

            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $current_Sale = Sale::findOrFail($id);
            
            if (SaleReturn::where('sale_id', $id)->where('deleted_at', '=', null)->exists()) {
                return response()->json(['success' => false , 'Return exist for the Transaction' => false], 403);
            }else{
                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === Sale->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $current_Sale);
                }
                $old_sale_details = SaleDetail::where('sale_id', $id)->get();
                $new_sale_details = $request['details'];
                $length = sizeof($new_sale_details);

                // Get Ids for new Details
                $new_products_id = [];
                foreach ($new_sale_details as $new_detail) {
                    $new_products_id[] = $new_detail['id'];
                }

                // Init Data with old Parametre
                $old_products_id = [];
                foreach ($old_sale_details as $key => $value) {
                    $old_products_id[] = $value->id;
                    
                    //check if detail has sale_unit_id Or Null
                    if($value['sale_unit_id'] !== null){
                        $old_unit = Unit::where('id', $value['sale_unit_id'])->first();
                    }else{
                        $product_unit_sale_id = Product::with('unitSale')
                        ->where('id', $value['product_id'])
                        ->first();

                        if($product_unit_sale_id['unitSale']){
                            $old_unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                        }{
                            $old_unit = NULL;
                        }
                    }

                    if ($current_Sale->statut == "completed") {

                        if ($value['product_variant_id'] !== null) {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Sale->warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($product_warehouse && $old_unit) {
                                if ($old_unit->operator == '/') {
                                    $product_warehouse->qte += $value['quantity'] / $old_unit->operator_value;
                                } else {
                                    $product_warehouse->qte += $value['quantity'] * $old_unit->operator_value;
                                }
                                $product_warehouse->save();
                            }

                        } else {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Sale->warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->first();
                            if ($product_warehouse && $old_unit) {
                                if ($old_unit->operator == '/') {
                                    $product_warehouse->qte += $value['quantity'] / $old_unit->operator_value;
                                } else {
                                    $product_warehouse->qte += $value['quantity'] * $old_unit->operator_value;
                                }
                                $product_warehouse->save();
                            }
                        }
                    }
                    // Delete Detail
                    if (!in_array($old_products_id[$key], $new_products_id)) {
                        $SaleDetail = SaleDetail::findOrFail($value->id);
                        $SaleDetail->delete();
                    }
                }


                // Update Data with New request
                $total_points_earned = 0;
                foreach ($new_sale_details as $prd => $prod_detail) {

                    $product = Product::find($prod_detail['product_id']);
                    $total_points_earned += $prod_detail['quantity'] * $product->points;

                    $get_type_product = Product::where('id', $prod_detail['product_id'])->first()->type;

                    
                    if($prod_detail['sale_unit_id'] !== null || $get_type_product == 'is_service'){
                        $unit_prod = Unit::where('id', $prod_detail['sale_unit_id'])->first();

                        if ($request['statut'] == "completed") {

                            if ($prod_detail['product_variant_id'] !== null) {
                                $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                    ->where('warehouse_id', $request->warehouse_id)
                                    ->where('product_id', $prod_detail['product_id'])
                                    ->where('product_variant_id', $prod_detail['product_variant_id'])
                                    ->first();

                                if ($product_warehouse && $unit_prod) {
                                    if ($unit_prod->operator == '/') {
                                        $product_warehouse->qte -= $prod_detail['quantity'] / $unit_prod->operator_value;
                                    } else {
                                        $product_warehouse->qte -= $prod_detail['quantity'] * $unit_prod->operator_value;
                                    }
                                    $product_warehouse->save();
                                }

                            } else {
                                $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                    ->where('warehouse_id', $request->warehouse_id)
                                    ->where('product_id', $prod_detail['product_id'])
                                    ->first();

                                if ($product_warehouse && $unit_prod) {
                                    if ($unit_prod->operator == '/') {
                                        $product_warehouse->qte -= $prod_detail['quantity'] / $unit_prod->operator_value;
                                    } else {
                                        $product_warehouse->qte -= $prod_detail['quantity'] * $unit_prod->operator_value;
                                    }
                                    $product_warehouse->save();
                                }
                            }

                        }

                        $orderDetails['sale_id']      = $id;
                        $orderDetails['date']         = $request['date'];
                        $orderDetails['price']        = $prod_detail['Unit_price'];
                        $orderDetails['sale_unit_id'] = $prod_detail['sale_unit_id'];
                        $orderDetails['TaxNet']       = $prod_detail['tax_percent'];
                        $orderDetails['tax_method']   = $prod_detail['tax_method'];
                        $orderDetails['discount']     = $prod_detail['discount'];
                        $orderDetails['discount_method'] = $prod_detail['discount_Method'];
                        $orderDetails['quantity']        = $prod_detail['quantity'];
                        $orderDetails['product_id']      = $prod_detail['product_id'];
                        $orderDetails['product_variant_id'] = $prod_detail['product_variant_id'];
                        $orderDetails['total']              = $prod_detail['subtotal'];
                        $orderDetails['imei_number']        = $prod_detail['imei_number'];

                        if (!in_array($prod_detail['id'], $old_products_id)) {
                            $orderDetails['date'] = $request['date'];
                            $orderDetails['sale_unit_id'] = $unit_prod ? $unit_prod->id : Null;
                            SaleDetail::Create($orderDetails);
                        } else {
                            SaleDetail::where('id', $prod_detail['id'])->update($orderDetails);
                        }
                    }
                }

                 $client = Client::find($current_Sale->client_id);

                // Step 1: Rollback previous points
                if ($client && $client->is_royalty_eligible) {
                    $previous_used = $current_Sale->used_points ?? 0;
                    $previous_earned = $current_Sale->earned_points ?? 0;

                    // Restore previously used points
                    if ($previous_used > 0) {
                        $client->increment('points', $previous_used);
                    }

                    // Remove previously earned points (safe from negative)
                    if ($previous_earned > 0) {
                        $new_balance = max(0, $client->points - $previous_earned);
                        $client->update(['points' => $new_balance]);
                    }

                    // Step 2: Apply new point logic
                    $new_used = $request['used_points'] ?? 0;
                    $new_earned = $total_points_earned;

                    if ($new_used > 0 && $client->points >= $new_used) {
                        $client->decrement('points', $new_used);
                    }

                    if ($new_earned > 0) {
                        $client->increment('points', $new_earned);
                    }

                }

                $due = $request['GrandTotal'] - $current_Sale->paid_amount;
                if ($due === 0.0 || $due < 0.0) {
                    $payment_statut = 'paid';
                } else if ($due != $request['GrandTotal']) {
                    $payment_statut = 'partial';
                } else if ($due == $request['GrandTotal']) {
                    $payment_statut = 'unpaid';
                }

                $current_Sale->update([
                    'date'         => $request['date'],
                    'client_id'    => $request['client_id'],
                    'warehouse_id' => $request['warehouse_id'],
                    'notes'        => $request['notes'],
                    'statut'       => $request['statut'],
                    'tax_rate'     => $request['tax_rate'],
                    'TaxNet'       => $request['TaxNet'],
                    'discount'     => $request['discount'],
                    'shipping'     => $request['shipping'],
                    'GrandTotal'   => $request['GrandTotal'],
                    'payment_statut' => $payment_statut,
                    'used_points'    => $new_used,
                    'earned_points'  => $new_earned,
                    'discount_from_points'  => $request['discount_from_points'],
                ]);
            }

        }, 10);

        return response()->json(['success' => true]);
    }

    //------------- Remove SALE BY ID -----------\\

     public function destroy(Request $request, $id)
     {
         $this->authorizeForUser($request->user('api'), 'delete', Sale::class);
 
         \DB::transaction(function () use ($id, $request) {
             $role = Auth::user()->roles()->first();
             $view_records = Role::findOrFail($role->id)->inRole('record_view');
             $current_Sale = Sale::findOrFail($id);
             $old_sale_details = SaleDetail::where('sale_id', $id)->get();
             $shipment_data =  Shipment::where('sale_id', $id)->first();

             if (SaleReturn::where('sale_id', $id)->where('deleted_at', '=', null)->exists()) {
                return response()->json(['success' => false , 'Return exist for the Transaction' => false], 403);
            }else{
                
                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === Sale->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $current_Sale);
                }

                // 🪙 Adjust royalty points for the client
                $client = Client::find($current_Sale->client_id);
                if ($client) {
                    // Restore used points (if any)
                    if ($current_Sale->used_points > 0) {
                        $client->increment('points', $current_Sale->used_points);
                    }

                    // Deduct earned points (if any)
                    if ($current_Sale->earned_points > 0) {
                        // Ensure not below zero
                        $new_points = max(0, $client->points - $current_Sale->earned_points);
                        $client->update(['points' => $new_points]);
                    }
                }

              

                foreach ($old_sale_details as $key => $value) {
                    
                    //check if detail has sale_unit_id Or Null
                    if($value['sale_unit_id'] !== null){
                        $old_unit = Unit::where('id', $value['sale_unit_id'])->first();
                    }else{
                        $product_unit_sale_id = Product::with('unitSale')
                        ->where('id', $value['product_id'])
                        ->first();
                        if($product_unit_sale_id['unitSale']){
                            $old_unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                        }{
                            $old_unit = NULL;
                        }
                    }

                    if ($current_Sale->statut == "completed") {

                        if ($value['product_variant_id'] !== null) {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Sale->warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($product_warehouse && $old_unit) {
                                if ($old_unit->operator == '/') {
                                    $product_warehouse->qte += $value['quantity'] / $old_unit->operator_value;
                                } else {
                                    $product_warehouse->qte += $value['quantity'] * $old_unit->operator_value;
                                }
                                $product_warehouse->save();
                            }

                        } else {
                            $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                ->where('warehouse_id', $current_Sale->warehouse_id)
                                ->where('product_id', $value['product_id'])
                                ->first();
                            if ($product_warehouse && $old_unit) {
                                if ($old_unit->operator == '/') {
                                    $product_warehouse->qte += $value['quantity'] / $old_unit->operator_value;
                                } else {
                                    $product_warehouse->qte += $value['quantity'] * $old_unit->operator_value;
                                }
                                $product_warehouse->save();
                            }
                        }
                    }
                    
                }

                if($shipment_data){
                    $shipment_data->delete();
                }
                $current_Sale->details()->delete();
                $current_Sale->update([
                    'deleted_at' => Carbon::now(),
                    'shipping_status' => NULL,
                ]);


                $Payment_Sale_data = PaymentSale::where('sale_id', $id)->get();
                foreach($Payment_Sale_data as $Payment_Sale){
                   if ($Payment_Sale->payment_method_id == 1 || $Payment_Sale->payment_method_id == '1') {
                        $PaymentWithCreditCard = PaymentWithCreditCard::where('payment_id', $Payment_Sale->id)->first();
                        if($PaymentWithCreditCard){
                            $PaymentWithCreditCard->delete();
                        }
                    }

                    $account = Account::find($Payment_Sale->account_id);
 
                    if ($account) {
                        $account->update([
                            'balance' => $account->balance - $Payment_Sale->montant,
                        ]);
                    }

                    $Payment_Sale->delete();
                }
            }
 
         }, 10);
 
         return response()->json(['success' => true]);
     }

    //-------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'delete', Sale::class);

        \DB::transaction(function () use ($request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $selectedIds = $request->selectedIds;
            foreach ($selectedIds as $sale_id) {

                if (SaleReturn::where('sale_id', $sale_id)->where('deleted_at', '=', null)->exists()) {
                    return response()->json(['success' => false , 'Return exist for the Transaction' => false], 403);
                }else{
                    $current_Sale = Sale::findOrFail($sale_id);
                    $old_sale_details = SaleDetail::where('sale_id', $sale_id)->get();
                    $shipment_data =  Shipment::where('sale_id', $sale_id)->first();

                    // Check If User Has Permission view All Records
                    if (!$view_records) {
                        // Check If User->id === current_Sale->id
                        $this->authorizeForUser($request->user('api'), 'check_record', $current_Sale);
                    }

                     // 🪙 Adjust royalty points for the client
                    $client = Client::find($current_Sale->client_id);
                    if ($client) {
                        // Restore used points (if any)
                        if ($current_Sale->used_points > 0) {
                            $client->increment('points', $current_Sale->used_points);
                        }

                        // Deduct earned points (if any)
                        if ($current_Sale->earned_points > 0) {
                            // Ensure not below zero
                            $new_points = max(0, $client->points - $current_Sale->earned_points);
                            $client->update(['points' => $new_points]);
                        }
                    }

                    foreach ($old_sale_details as $key => $value) {
                    
                         //check if detail has sale_unit_id Or Null
                        if($value['sale_unit_id'] !== null){
                            $old_unit = Unit::where('id', $value['sale_unit_id'])->first();
                        }else{
                            $product_unit_sale_id = Product::with('unitSale')
                            ->where('id', $value['product_id'])
                            ->first();
                            if($product_unit_sale_id['unitSale']){
                                $old_unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                            }{
                                $old_unit = NULL;
                            }
                        }
        
                        if ($current_Sale->statut == "completed") {
        
                            if ($value['product_variant_id'] !== null) {
                                $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                    ->where('warehouse_id', $current_Sale->warehouse_id)
                                    ->where('product_id', $value['product_id'])
                                    ->where('product_variant_id', $value['product_variant_id'])
                                    ->first();
        
                                if ($product_warehouse && $old_unit) {
                                    if ($old_unit->operator == '/') {
                                        $product_warehouse->qte += $value['quantity'] / $old_unit->operator_value;
                                    } else {
                                        $product_warehouse->qte += $value['quantity'] * $old_unit->operator_value;
                                    }
                                    $product_warehouse->save();
                                }
        
                            } else {
                                $product_warehouse = product_warehouse::where('deleted_at', '=', null)
                                    ->where('warehouse_id', $current_Sale->warehouse_id)
                                    ->where('product_id', $value['product_id'])
                                    ->first();
                                if ($product_warehouse && $old_unit) {
                                    if ($old_unit->operator == '/') {
                                        $product_warehouse->qte += $value['quantity'] / $old_unit->operator_value;
                                    } else {
                                        $product_warehouse->qte += $value['quantity'] * $old_unit->operator_value;
                                    }
                                    $product_warehouse->save();
                                }
                            }
                        }
                        
                    }

                    if($shipment_data){
                        $shipment_data->delete();
                    }
                    
                    $current_Sale->details()->delete();
                    $current_Sale->update([
                        'deleted_at' => Carbon::now(),
                        'shipping_status' => NULL,
                    ]);


                    $Payment_Sale_data = PaymentSale::where('sale_id', $sale_id)->get();
                    foreach($Payment_Sale_data as $Payment_Sale){
                        if ($Payment_Sale->payment_method_id == 1 || $Payment_Sale->payment_method_id == '1') {
                            $PaymentWithCreditCard = PaymentWithCreditCard::where('payment_id', $Payment_Sale->id)->first();
                            if($PaymentWithCreditCard){
                                $PaymentWithCreditCard->delete();
                            }
                        }

                        $account = Account::find($Payment_Sale->account_id);
 
                        if ($account) {
                            $account->update([
                                'balance' => $account->balance - $Payment_Sale->montant,
                            ]);
                        }

                        $Payment_Sale->delete();
                    }
                }
            }

        }, 10);

        return response()->json(['success' => true]);
    }

   
    //---------------- Get Details Sale-----------------\\

    public function show(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', Sale::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $sale_data = Sale::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = array();

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === sale->id
            $this->authorizeForUser($request->user('api'), 'check_record', $sale_data);
        }

        $sale_details['Ref']  = $sale_data->Ref;
        $sale_details['date'] = $sale_data->date . ' ' . $sale_data->time;
        $sale_details['note'] = $sale_data->notes;
        $sale_details['statut'] = $sale_data->statut;
        $sale_details['warehouse'] = $sale_data['warehouse']->name;
        $sale_details['discount'] = $sale_data->discount;
        $sale_details['shipping'] = $sale_data->shipping;
        $sale_details['tax_rate'] = $sale_data->tax_rate;
        $sale_details['TaxNet'] = $sale_data->TaxNet;
        $sale_details['client_name'] = $sale_data['client']->name;
        $sale_details['client_phone'] = $sale_data['client']->phone;
        $sale_details['client_adr'] = $sale_data['client']->adresse;
        $sale_details['client_email'] = $sale_data['client']->email;
        $sale_details['client_tax'] = $sale_data['client']->tax_number;
        $sale_details['GrandTotal'] = number_format($sale_data->GrandTotal, 2, '.', '');
        $sale_details['paid_amount'] = number_format($sale_data->paid_amount, 2, '.', '');
        $sale_details['due'] = number_format($sale_details['GrandTotal'] - $sale_details['paid_amount'], 2, '.', '');
        $sale_details['payment_status'] = $sale_data->payment_statut;

        if (SaleReturn::where('sale_id', $id)->where('deleted_at', '=', null)->exists()) {
            $sellReturn = SaleReturn::where('sale_id', $id)->where('deleted_at', '=', null)->first();
            $sale_details['salereturn_id'] = $sellReturn->id;
            $sale_details['sale_has_return'] = 'yes';
        }else{
            $sale_details['sale_has_return'] = 'no';
        }

        foreach ($sale_data['details'] as $detail) {

             //check if detail has sale_unit_id Or Null
             if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }{
                    $unit = NULL;
                }
            }

            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name .']'. $detail['product']['name'];
 
            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }

            $data['quantity'] = $detail->quantity;
            $data['total'] = $detail->total;
            $data['price'] = $detail->price;
            $data['unit_sale'] = $unit?$unit->ShortName:'';

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->price * $detail->discount / 100;
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = $detail->price;
            $data['discount'] = $detail->discount;

            if ($detail->tax_method == '1') {
                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = $tax_price;
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }

        $company = Setting::where('deleted_at', '=', null)->first();

        return response()->json([
            'details' => $details,
            'sale' => $sale_details,
            'company' => $company,
        ]);

    }

    //-------------- Print Invoice ---------------\\

    public function Print_Invoice_POS(Request $request, $id)
    {
        $helpers = new helpers();
        $details = array();

        $sale = Sale::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $item['id'] = $sale->id;
        $item['Ref'] = $sale->Ref;
        $item['date'] = $sale->date . ' ' . $sale->time;
        $item['discount'] = number_format($sale->discount, 2, '.', '');
        $item['shipping'] = number_format($sale->shipping, 2, '.', '');
        $item['taxe'] =     number_format($sale->TaxNet, 2, '.', '');
        $item['tax_rate'] = $sale->tax_rate;
        $item['client_name'] = $sale['client']->name;
        $item['warehouse_name'] = $sale['warehouse']->name;
        $item['seller_name'] = $sale['user']->username;
        $item['GrandTotal'] = number_format($sale->GrandTotal, 2, '.', '');
        $item['paid_amount'] = number_format($sale->paid_amount, 2, '.', '');

        foreach ($sale['details'] as $detail) {

             //check if detail has sale_unit_id Or Null
             if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();
                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }{
                    $unit = NULL;
                }

            }

            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                    $data['code'] = $productsVariants->code;
                    $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
                    
                } else {
                    $data['code'] = $detail['product']['code'];
                    $data['name'] = $detail['product']['name'];
                }
                
           
            $data['quantity'] = number_format($detail->quantity, 2, '.', '');
            $data['total'] = number_format($detail->total, 2, '.', '');
            $data['unit_sale'] = $unit?$unit->ShortName:'';

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }

        $payments = PaymentSale::with('sale','payment_method')
            ->where('sale_id', $id)
            ->orderBy('id', 'DESC')
            ->get();

        $settings = Setting::where('deleted_at', '=', null)->first();
        $pos_settings = PosSetting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        return response()->json([
            'symbol' => $symbol,
            'payments' => $payments,
            'setting' => $settings,
            'pos_settings' => $pos_settings,
            'sale' => $item,
            'details' => $details,
        ]);

    }

    //------------- GET PAYMENTS SALE -----------\\

    public function Payments_Sale(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', PaymentSale::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $Sale = Sale::findOrFail($id);

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === Sale->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Sale);
        }

        $payments = PaymentSale::with('sale','payment_method')
            ->where('sale_id', $id)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })->orderBy('id', 'DESC')->get();

        $due = $Sale->GrandTotal - $Sale->paid_amount;

        return response()->json(['payments' => $payments, 'due' => $due]);

    }

    //------------- Reference Number Order SALE -----------\\

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

    //------------- SALE PDF -----------\\

    public function Sale_PDF(Request $request, $id)
    {

        $details = array();
        $helpers = new helpers();
        $sale_data = Sale::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $sale['client_name'] = $sale_data['client']->name;
        $sale['client_phone'] = $sale_data['client']->phone;
        $sale['client_adr'] = $sale_data['client']->adresse;
        $sale['client_email'] = $sale_data['client']->email;
        $sale['client_tax'] = $sale_data['client']->tax_number;
        $sale['TaxNet'] = number_format($sale_data->TaxNet, 2, '.', '');
        $sale['discount'] = number_format($sale_data->discount, 2, '.', '');
        $sale['shipping'] = number_format($sale_data->shipping, 2, '.', '');
        $sale['statut'] = $sale_data->statut;
        $sale['Ref'] = $sale_data->Ref;
        $sale['date'] = $sale_data->date . ' ' . $sale_data->time;
        $sale['GrandTotal'] = number_format($sale_data->GrandTotal, 2, '.', '');
        $sale['paid_amount'] = number_format($sale_data->paid_amount, 2, '.', '');
        $sale['due'] = number_format($sale['GrandTotal'] - $sale['paid_amount'], 2, '.', '');
        $sale['payment_status'] = $sale_data->payment_statut;

        $detail_id = 0;
        foreach ($sale_data['details'] as $detail) {

            //check if detail has sale_unit_id Or Null
            if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }{
                    $unit = NULL;
                }

            }

            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }

                $data['detail_id'] = $detail_id += 1;
                $data['quantity'] = number_format($detail->quantity, 2, '.', '');
                $data['total'] = number_format($detail->total, 2, '.', '');
                $data['unitSale'] = $unit?$unit->ShortName:'';
                $data['price'] = number_format($detail->price, 2, '.', '');

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = number_format($detail->discount, 2, '.', '');
            } else {
                $data['DiscountNet'] = number_format($detail->price * $detail->discount / 100, 2, '.', '');
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = number_format($detail->price, 2, '.', '');
            $data['discount'] = number_format($detail->discount, 2, '.', '');

            if ($detail->tax_method == '1') {
                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = number_format($tax_price, 2, '.', '');
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                $data['taxe'] = number_format($detail->price - $data['Net_price'] - $data['DiscountNet'], 2, '.', '');
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }
        $settings = Setting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        $Html = view('pdf.sale_pdf', [
            'symbol' => $symbol,
            'setting' => $settings,
            'sale' => $sale,
            'details' => $details,
        ])->render();

        $arabic = new Arabic();
        $p = $arabic->arIdentify($Html);

        for ($i = count($p)-1; $i >= 0; $i-=2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i-1], $p[$i] - $p[$i-1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i-1], $p[$i] - $p[$i-1]);
        }

        $pdf = PDF::loadHTML($Html);
        return $pdf->download('sale.pdf');

    }

    //----------------Show Form Create Sale ---------------\\

    public function create(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'create', Sale::class);

       //get warehouses assigned to user
       $user_auth = auth()->user();
       if($user_auth->is_all_warehouses){
           $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
       }else{
           $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
           $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
       }

        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
        $accounts = Account::where('deleted_at', '=', null)->get(['id', 'account_name']);
        $payment_methods = PaymentMethod::whereNull('deleted_at')->get(['id', 'name']);
        $stripe_key = config('app.STRIPE_KEY');
        $settings = Setting::where('deleted_at', '=', null)->first();

        return response()->json([
            'stripe_key' => $stripe_key,
            'clients' => $clients,
            'warehouses' => $warehouses,
            'accounts' => $accounts,
            'payment_methods' => $payment_methods,
            'point_to_amount_rate' => $settings->point_to_amount_rate,
        ]);

    }

      //------------- Show Form Edit Sale -----------\\

      public function edit(Request $request, $id)
      {
        if (SaleReturn::where('sale_id', $id)->where('deleted_at', '=', null)->exists()) {
            return response()->json(['success' => false , 'Return exist for the Transaction' => false], 403);
        }else{
          $this->authorizeForUser($request->user('api'), 'update', Sale::class);
          $role = Auth::user()->roles()->first();
          $view_records = Role::findOrFail($role->id)->inRole('record_view');
          $Sale_data = Sale::with('details.product.unitSale')
              ->where('deleted_at', '=', null)
              ->findOrFail($id);
          $details = array();
          // Check If User Has Permission view All Records
          if (!$view_records) {
              // Check If User->id === sale->id
              $this->authorizeForUser($request->user('api'), 'check_record', $Sale_data);
          }
  
          if ($Sale_data->client_id) {
              if (Client::where('id', $Sale_data->client_id)
                  ->where('deleted_at', '=', null)
                  ->first()) {
                  $sale['client_id'] = $Sale_data->client_id;
              } else {
                  $sale['client_id'] = '';
              }
          } else {
              $sale['client_id'] = '';
          }
  
          if ($Sale_data->warehouse_id) {
              if (Warehouse::where('id', $Sale_data->warehouse_id)
                  ->where('deleted_at', '=', null)
                  ->first()) {
                  $sale['warehouse_id'] = $Sale_data->warehouse_id;
              } else {
                  $sale['warehouse_id'] = '';
              }
          } else {
              $sale['warehouse_id'] = '';
          }
  
          $sale['date'] = $Sale_data->date;
          $sale['tax_rate'] = $Sale_data->tax_rate;
          $sale['TaxNet'] = $Sale_data->TaxNet;
          $sale['used_points'] = $Sale_data->used_points;
          $sale['discount'] = $Sale_data->discount;
          $sale['shipping'] = $Sale_data->shipping;
          $sale['statut'] = $Sale_data->statut;
          $sale['notes'] = $Sale_data->notes;
  
          $detail_id = 0;
          foreach ($Sale_data['details'] as $detail) {

                //check if detail has sale_unit_id Or Null
                if($detail->sale_unit_id !== null){
                    $unit = Unit::where('id', $detail->sale_unit_id)->first();
                    $data['no_unit'] = 1;
                }else{
                    $product_unit_sale_id = Product::with('unitSale')
                    ->where('id', $detail->product_id)
                    ->first();

                    if($product_unit_sale_id['unitSale']){
                        $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                    }{
                        $unit = NULL;
                    }
    
                    $data['no_unit'] = 0;
                }

        
              if ($detail->product_variant_id) {
                  $item_product = product_warehouse::where('product_id', $detail->product_id)
                      ->where('deleted_at', '=', null)
                      ->where('product_variant_id', $detail->product_variant_id)
                      ->where('warehouse_id', $Sale_data->warehouse_id)
                      ->first();
  
                  $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                      ->where('id', $detail->product_variant_id)->first();
  
                  $item_product ? $data['del'] = 0 : $data['del'] = 1;
                  $data['product_variant_id'] = $detail->product_variant_id;
                  $data['code'] = $productsVariants->code;
                  $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
                 
                  if ($unit && $unit->operator == '/') {
                    $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                  } else if ($unit && $unit->operator == '*') {
                    $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                  } else {
                    $stock = 0;
                  }
  
              } else {
                  $item_product = product_warehouse::where('product_id', $detail->product_id)
                      ->where('deleted_at', '=', null)->where('warehouse_id', $Sale_data->warehouse_id)
                      ->where('product_variant_id', '=', null)->first();
  
                  $item_product ? $data['del'] = 0 : $data['del'] = 1;
                  $data['product_variant_id'] = null;
                  $data['code'] = $detail['product']['code'];
                  $data['name'] = $detail['product']['name'];

                  if ($unit && $unit->operator == '/') {
                        $stock= $item_product ? $item_product->qte * $unit->operator_value : 0;
                    } else if ($unit && $unit->operator == '*') {
                    $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                  } else {
                    $stock = 0;
                  }
  
                }
                
                $data['id'] = $detail->id;
                $data['stock'] = $detail['product']['type'] !='is_service'?$stock:'---';
                $data['product_type'] = $detail['product']['type'];
                $data['detail_id'] = $detail_id += 1;
                $data['product_id'] = $detail->product_id;
                $data['total'] = $detail->total;
                $data['quantity'] = $detail->quantity;
                $data['qte_copy'] = $detail->quantity;
                $data['etat'] = 'current';
                $data['unitSale'] = $unit?$unit->ShortName:'';
                $data['sale_unit_id'] = $unit?$unit->id:'';
                $data['is_imei'] = $detail['product']['is_imei'];
                $data['imei_number'] = $detail->imei_number;

                if ($detail->discount_method == '2') {
                    $data['DiscountNet'] = $detail->discount;
                } else {
                    $data['DiscountNet'] = $detail->price * $detail->discount / 100;
                }

                $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
                $data['Unit_price'] = $detail->price;
                
                $data['tax_percent'] = $detail->TaxNet;
                $data['tax_method'] = $detail->tax_method;
                $data['discount'] = $detail->discount;
                $data['discount_Method'] = $detail->discount_method;

                if ($detail->tax_method == '1') {
                    $data['Net_price'] = $detail->price - $data['DiscountNet'];
                    $data['taxe'] = $tax_price;
                    $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
                } else {
                    $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                    $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                    $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
                }


               $details[] = $data;
          }
        
            //get warehouses assigned to user
            $user_auth = auth()->user();
            if($user_auth->is_all_warehouses){
                $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
            }else{
                $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
                $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
            }

          $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
          $settings = Setting::where('deleted_at', '=', null)->first();

          return response()->json([
              'details' => $details,
              'sale' => $sale,
              'clients' => $clients,
              'warehouses' => $warehouses,
              'discount_from_points' =>  $Sale_data->discount_from_points,
              'point_to_amount_rate' => $settings->point_to_amount_rate,
          ]);
        }
  
      }



    //------------- Show Form Convert To Sale -----------\\

    public function Elemens_Change_To_Sale(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Quotation::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $Quotation = Quotation::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);
        $details = array();
        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === Quotation->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Quotation);
        }

        if ($Quotation->client_id) {
            if (Client::where('id', $Quotation->client_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $sale['client_id'] = $Quotation->client_id;
            } else {
                $sale['client_id'] = '';
            }
        } else {
            $sale['client_id'] = '';
        }

        if ($Quotation->warehouse_id) {
            if (Warehouse::where('id', $Quotation->warehouse_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $sale['warehouse_id'] = $Quotation->warehouse_id;
            } else {
                $sale['warehouse_id'] = '';
            }
        } else {
            $sale['warehouse_id'] = '';
        }

        $sale['date'] = $Quotation->date;
        $sale['TaxNet'] = $Quotation->TaxNet;
        $sale['tax_rate'] = $Quotation->tax_rate;
        $sale['discount'] = $Quotation->discount;
        $sale['shipping'] = $Quotation->shipping;
        $sale['statut'] = 'completed';
        $sale['notes'] = $Quotation->notes;

        $detail_id = 0;
        foreach ($Quotation['details'] as $detail) {
           
                //check if detail has sale_unit_id Or Null
                if($detail->sale_unit_id !== null || $detail['product']['type'] == 'is_service'){
                    $unit = Unit::where('id', $detail->sale_unit_id)->first();

                if ($detail->product_variant_id) {
                    $item_product = product_warehouse::where('product_id', $detail->product_id)
                        ->where('product_variant_id', $detail->product_variant_id)
                        ->where('warehouse_id', $Quotation->warehouse_id)
                        ->where('deleted_at', '=', null)
                        ->first();
                    $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                        ->where('id', $detail->product_variant_id)->where('deleted_at', null)->first();

                    $item_product ? $data['del'] = 0 : $data['del'] = 1;
                    $data['product_variant_id'] = $detail->product_variant_id;
                    $data['code'] = $productsVariants->code;
                    $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];

                    if ($unit && $unit->operator == '/') {
                        $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                    } else if ($unit && $unit->operator == '*') {
                        $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                    } else {
                        $stock = 0;
                    }

                } else {
                    $item_product = product_warehouse::where('product_id', $detail->product_id)
                        ->where('warehouse_id', $Quotation->warehouse_id)
                        ->where('product_variant_id', '=', null)
                        ->where('deleted_at', '=', null)
                        ->first();

                    $item_product ? $data['del'] = 0 : $data['del'] = 1;
                    $data['product_variant_id'] = null;
                    $data['code'] = $detail['product']['code'];
                    $data['name'] = $detail['product']['name'];

                    if ($unit && $unit->operator == '/') {
                        $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                    } else if ($unit && $unit->operator == '*') {
                        $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                    } else {
                        $stock = 0;
                    }
                }
                
                $data['id'] = $id;
                $data['stock'] = $detail['product']['type'] !='is_service'?$stock:'---';
                $data['product_type'] = $detail['product']['type'];
                $data['detail_id'] = $detail_id += 1;
                $data['quantity'] = $detail->quantity;
                $data['product_id'] = $detail->product_id;
                $data['total'] = $detail->total;
                $data['etat'] = 'current';
                $data['qte_copy'] = $detail->quantity;
                $data['unitSale'] = $unit?$unit->ShortName:'';
                $data['sale_unit_id'] = $unit?$unit->id:'';

                $data['is_imei'] = $detail['product']['is_imei'];
                $data['imei_number'] = $detail->imei_number;

                if ($detail->discount_method == '2') {
                    $data['DiscountNet'] = $detail->discount;
                } else {
                    $data['DiscountNet'] = $detail->price * $detail->discount / 100;
                }
                $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
                $data['Unit_price'] = $detail->price;
                $data['tax_percent'] = $detail->TaxNet;
                $data['tax_method'] = $detail->tax_method;
                $data['discount'] = $detail->discount;
                $data['discount_Method'] = $detail->discount_method;

                if ($detail->tax_method == '1') {
                    $data['Net_price'] = $detail->price - $data['DiscountNet'];
                    $data['taxe'] = $tax_price;
                    $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
                } else {
                    $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                    $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                    $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
                }

                $details[] = $data;
            }
        }

       //get warehouses assigned to user
       $user_auth = auth()->user();
       if($user_auth->is_all_warehouses){
           $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
       }else{
           $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
           $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
       }
         
        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'details' => $details,
            'sale' => $sale,
            'clients' => $clients,
            'warehouses' => $warehouses,
        ]);

    }

    
    //------------------- get_Products_by_sale -----------------\\

    public function get_Products_by_sale(Request $request , $id)
    {

        $this->authorizeForUser($request->user('api'), 'create', SaleReturn::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $SaleReturn = Sale::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = array();

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === SaleReturn->id
            $this->authorizeForUser($request->user('api'), 'check_record', $SaleReturn);
        }

        $Return_detail['client_id'] = $SaleReturn->client_id;
        $Return_detail['warehouse_id'] = $SaleReturn->warehouse_id;
        $Return_detail['sale_id'] = $SaleReturn->id;
        $Return_detail['tax_rate'] = 0;
        $Return_detail['TaxNet'] = 0;
        $Return_detail['discount'] = 0;
        $Return_detail['shipping'] = 0;
        $Return_detail['statut'] = "received";
        $Return_detail['notes'] = "";

        $detail_id = 0;
        foreach ($SaleReturn['details'] as $detail) {

            //check if detail has sale_unit_id Or Null
            if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
                $data['no_unit'] = 1;
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }{
                    $unit = NULL;
                }

                $data['no_unit'] = 0;
            }

            if ($detail->product_variant_id) {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('product_variant_id', $detail->product_variant_id)
                    ->where('deleted_at', '=', null)
                    ->where('warehouse_id', $SaleReturn->warehouse_id)
                    ->first();

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = $detail->product_variant_id;
                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];

                if ($unit && $unit->operator == '/') {
                    $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } else if ($unit && $unit->operator == '*') {
                    $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                    $stock = 0;
                }

            } else {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('warehouse_id', $SaleReturn->warehouse_id)
                    ->where('deleted_at', '=', null)->where('product_variant_id', '=', null)
                    ->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = null;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];

                if ($unit && $unit->operator == '/') {
                    $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } else if ($unit && $unit->operator == '*') {
                    $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                    $stock = 0;
                }

            }

            $data['id'] = $detail->id;
            $data['stock'] = $detail['product']['type'] !='is_service'?$stock:'---';
            $data['detail_id'] = $detail_id += 1;
            $data['quantity'] = $detail->quantity;
            $data['sale_quantity'] = $detail->quantity;
            $data['product_id'] = $detail->product_id;
            $data['unitSale'] = $unit->ShortName;
            $data['sale_unit_id'] = $unit->id;
            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->price * $detail->discount / 100;
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = $detail->price;
            $data['tax_percent'] = $detail->TaxNet;
            $data['tax_method'] = $detail->tax_method;
            $data['discount'] = $detail->discount;
            $data['discount_Method'] = $detail->discount_method;

            if ($detail->tax_method == '1') {

                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = $tax_price;
                $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
            }

            $details[] = $data;
        }


        return response()->json([
            'details' => $details,
            'sale_return' => $Return_detail,
        ]);

    }



     //------------- Send sale on Email -----------\\

     public function Send_Email(Request $request)
     {
        $this->authorizeForUser($request->user('api'), 'view', Sale::class);

          //sale
          $sale = Sale::with('client')->where('deleted_at', '=', null)->findOrFail($request->id);
 
          $helpers = new helpers();
          $currency = $helpers->Get_Currency();
 
          //settings
          $settings = Setting::where('deleted_at', '=', null)->first();
      
          //the custom msg of sale
          $emailMessage  = EmailMessage::where('name', 'sale')->first();
  
          if($emailMessage){
              $message_body = $emailMessage->body;
              $message_subject = $emailMessage->subject;
          }else{
              $message_body = '';
              $message_subject = '';
          }
  
          //Tags
          $random_number = Str::random(10);
          $invoice_url = url('/api/sale_pdf/' . $request->id.'?'.$random_number);
          $invoice_number = $sale->Ref;
  
          $total_amount = $currency .' '.number_format($sale->GrandTotal, 2, '.', ',');
          $paid_amount  = $currency .' '.number_format($sale->paid_amount, 2, '.', ',');
          $due_amount   = $currency .' '.number_format($sale->GrandTotal - $sale->paid_amount, 2, '.', ',');
  
          $contact_name = $sale['client']->name;
          $business_name = $settings->CompanyName;
  
          //receiver email
          $receiver_email = $sale['client']->email;
  
          //replace the text with tags
          $message_body = str_replace('{contact_name}', $contact_name, $message_body);
          $message_body = str_replace('{business_name}', $business_name, $message_body);
          $message_body = str_replace('{invoice_url}', $invoice_url, $message_body);
          $message_body = str_replace('{invoice_number}', $invoice_number, $message_body);
  
          $message_body = str_replace('{total_amount}', $total_amount, $message_body);
          $message_body = str_replace('{paid_amount}', $paid_amount, $message_body);
          $message_body = str_replace('{due_amount}', $due_amount, $message_body);
 
         $email['subject'] = $message_subject;
         $email['body'] = $message_body;
         $email['company_name'] = $business_name;
 
         $this->Set_config_mail(); 
 
         Mail::to($receiver_email)->send(new CustomEmail($email));
         return response()->json(['message' => 'Email sent successfully'], 200);
 
     }
 

     //-------------------Sms Notifications -----------------\\
 
     public function Send_SMS(Request $request)
     {
        $this->authorizeForUser($request->user('api'), 'view', Sale::class);

         //sale
         $sale = Sale::with('client')->where('deleted_at', '=', null)->findOrFail($request->id);
 
         $helpers = new helpers();
         $currency = $helpers->Get_Currency();
         
         //settings
         $settings = Setting::where('deleted_at', '=', null)->first();
     
         $default_sms_gateway = sms_gateway::where('id' , $settings->sms_gateway)
         ->where('deleted_at', '=', null)->first();

         //the custom msg of sale
         $smsMessage  = SMSMessage::where('name', 'sale')->first();
 
         if($smsMessage){
             $message_text = $smsMessage->text;
         }else{
             $message_text = '';
         }
 
         //Tags
         $random_number = Str::random(10);
         $invoice_url = url('/api/sale_pdf/' . $request->id.'?'.$random_number);
         $invoice_number = $sale->Ref;
 
         $total_amount = $currency.' '.number_format($sale->GrandTotal, 2, '.', ',');
         $paid_amount  = $currency.' '.number_format($sale->paid_amount, 2, '.', ',');
         $due_amount   = $currency.' '.number_format($sale->GrandTotal - $sale->paid_amount, 2, '.', ',');
 
         $contact_name = $sale['client']->name;
         $business_name = $settings->CompanyName;
 
         //receiver Number
         $receiverNumber = $sale['client']->phone;
 
         //replace the text with tags
         $message_text = str_replace('{contact_name}', $contact_name, $message_text);
         $message_text = str_replace('{business_name}', $business_name, $message_text);
         $message_text = str_replace('{invoice_url}', $invoice_url, $message_text);
         $message_text = str_replace('{invoice_number}', $invoice_number, $message_text);
 
         $message_text = str_replace('{total_amount}', $total_amount, $message_text);
         $message_text = str_replace('{paid_amount}', $paid_amount, $message_text);
         $message_text = str_replace('{due_amount}', $due_amount, $message_text);
 
        //twilio
         if($default_sms_gateway->title == "twilio"){
             try {
     
                 $account_sid = env("TWILIO_SID");
                 $auth_token = env("TWILIO_TOKEN");
                 $twilio_number = env("TWILIO_FROM");
     
                 $client = new Client_Twilio($account_sid, $auth_token);
                 $client->messages->create($receiverNumber, [
                     'from' => $twilio_number, 
                     'body' => $message_text]);
         
             } catch (Exception $e) {
                 return response()->json(['message' => $e->getMessage()], 500);
             }
            }
        //termii
        elseif($default_sms_gateway->title == "termii"){

            $client = new Client_termi();
            $url = 'https://api.ng.termii.com/api/sms/send';

            $payload = [
                'to' => $receiverNumber,
                'from' => env('TERMI_SENDER'),
                'sms' => $message_text,
                'type' => 'plain',
                'channel' => 'generic',
                'api_key' => env('TERMI_KEY'),
            ];

            try {
                $response = $client->post($url, [
                    'json' => $payload,
                ]);

                $result = json_decode($response->getBody(), true);
                return response()->json($result);
            } catch (\Exception $e) {
                Log::error("Termii SMS Error: " . $e->getMessage());
                return response()->json(['status' => 'error', 'message' => 'Failed to send SMS'], 500);
            }
             
 
        }
        //  //---- infobip
         elseif($default_sms_gateway->title == "infobip"){
 
             $BASE_URL = env("base_url");
             $API_KEY = env("api_key");
             $SENDER = env("sender_from");
 
             $configuration = (new Configuration())
                 ->setHost($BASE_URL)
                 ->setApiKeyPrefix('Authorization', 'App')
                 ->setApiKey('Authorization', $API_KEY);
             
             $client = new Client_guzzle();
             
             $sendSmsApi = new SendSMSApi($client, $configuration);
             $destination = (new SmsDestination())->setTo($receiverNumber);
             $message = (new SmsTextualMessage())
                 ->setFrom($SENDER)
                 ->setText($message_text)
                 ->setDestinations([$destination]);
                 
             $request = (new SmsAdvancedTextualRequest())->setMessages([$message]);
             
             try {
                 $smsResponse = $sendSmsApi->sendSmsMessage($request);
                 echo ("Response body: " . $smsResponse);
             } catch (Throwable $apiException) {
                 echo("HTTP Code: " . $apiException->getCode() . "\n");
             }
             
         }
 
         return response()->json(['success' => true]);
 
         
     }


      // sales_send_whatsapp
    public function sales_send_whatsapp(Request $request)
    {

         //sale
         $sale = Sale::with('client')->where('deleted_at', '=', null)->findOrFail($request->id);
 
         $helpers = new helpers();
         $currency = $helpers->Get_Currency();
         
         //settings
         $settings = Setting::where('deleted_at', '=', null)->first();

         //the custom msg of sale
         $smsMessage  = SMSMessage::where('name', 'sale')->first();
 
         if($smsMessage){
             $message_text = $smsMessage->text;
         }else{
             $message_text = '';
         }
 
         //Tags
         $random_number = Str::random(10);
         $invoice_url = url('/api/sale_pdf/' . $request->id.'?'.$random_number);
         $invoice_number = $sale->Ref;
 
         $total_amount = $currency.' '.number_format($sale->GrandTotal, 2, '.', ',');
         $paid_amount  = $currency.' '.number_format($sale->paid_amount, 2, '.', ',');
         $due_amount   = $currency.' '.number_format($sale->GrandTotal - $sale->paid_amount, 2, '.', ',');
 
         $contact_name = $sale['client']->name;
         $business_name = $settings->CompanyName;
 
         //receiver Number
         $receiverNumber = $sale['client']->phone;

          // Check if the phone number is empty or null
        if (empty($receiverNumber) || $receiverNumber == null || $receiverNumber == 'null' || $receiverNumber == '') {
            return response()->json(['error' => 'Phone number is missing'], 400);
        }
 
 
         //replace the text with tags
         $message_text = str_replace('{contact_name}', $contact_name, $message_text);
         $message_text = str_replace('{business_name}', $business_name, $message_text);
         $message_text = str_replace('{invoice_url}', $invoice_url, $message_text);
         $message_text = str_replace('{invoice_number}', $invoice_number, $message_text);
 
         $message_text = str_replace('{total_amount}', $total_amount, $message_text);
         $message_text = str_replace('{paid_amount}', $paid_amount, $message_text);
         $message_text = str_replace('{due_amount}', $due_amount, $message_text);
 

        return response()->json(['message' => $message_text , 'phone' => $receiverNumber ]);


    }


    // get_today_sales
    public function get_today_sales(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        $today = Carbon::today()->toDateString();
        $data['today'] = $today;

        $data['total_sales_amount'] = Sale::whereNull('deleted_at')
            ->whereDate('date', $today)
            ->sum('GrandTotal');

        $data['total_amount_paid'] = Sale::whereNull('deleted_at')
            ->whereDate('date', $today)
            ->sum('paid_amount');

        // Fetch payment methods by name or ID
        $cashMethod = PaymentMethod::where('name', 'Cash')->first();
        $creditCardMethod = PaymentMethod::where('name', 'Credit Card')->first();
        $chequeMethod = PaymentMethod::where('name', 'Cheque')->first();

        $data['total_cash'] = $cashMethod
            ? PaymentSale::whereNull('deleted_at')
                ->whereDate('date', $today)
                ->where('payment_method_id', $cashMethod->id)
                ->sum('montant')
            : 0;

        $data['total_credit_card'] = $creditCardMethod
            ? PaymentSale::whereNull('deleted_at')
                ->whereDate('date', $today)
                ->where('payment_method_id', $creditCardMethod->id)
                ->sum('montant')
            : 0;

        $data['total_cheque'] = $chequeMethod
            ? PaymentSale::whereNull('deleted_at')
                ->whereDate('date', $today)
                ->where('payment_method_id', $chequeMethod->id)
                ->sum('montant')
            : 0;

        return response()->json($data);
    }

    public function Send_Subscription_Reminder_SMS($subscription_id)
    {
        // Load Subscription details with client relationship
        $subscription = Subscription::with('client')->findOrFail($subscription_id);

        // Retrieve currency and settings
        $helpers = new helpers();
        $currency = $helpers->Get_Currency();

        $settings = Setting::whereNull('deleted_at')->first();
        $default_sms_gateway = sms_gateway::where('id', $settings->sms_gateway)
            ->whereNull('deleted_at')->first();

        // Get SMS message template
        $smsMessage = SMSMessage::where('name', 'subscription_reminder')->first();
        $message_text = $smsMessage ? $smsMessage->text : '';

        // Prepare tags replacement
        $client_name = $subscription->client->name;
        $business_name = $settings->CompanyName;
        $next_billing_date = Carbon::parse($subscription->next_billing_date)->format('Y-m-d');

        // Replace tags in SMS template
        $message_text = str_replace('{client_name}', $client_name, $message_text);
        $message_text = str_replace('{business_name}', $business_name, $message_text);
        $message_text = str_replace('{next_billing_date}', $next_billing_date, $message_text);

        // Receiver phone number
        $receiverNumber = $subscription->client->phone;

        // Send SMS based on the gateway
        if ($default_sms_gateway->title == "twilio") {
            try {
                $account_sid = env("TWILIO_SID");
                $auth_token = env("TWILIO_TOKEN");
                $twilio_number = env("TWILIO_FROM");

                $client = new Client_Twilio($account_sid, $auth_token);
                $client->messages->create($receiverNumber, [
                    'from' => $twilio_number,
                    'body' => $message_text
                ]);

            } catch (Exception $e) {
                Log::error('Twilio SMS Error: ' . $e->getMessage());

                ErrorLog::create([
                    'context' => 'Twilio SMS',
                    'message' => $e->getMessage(),
                    'details' => json_encode([
                        'receiver' => $receiverNumber,
                        'trace' => $e->getTraceAsString()
                    ]),
                ]);

                return response()->json(['message' => $e->getMessage()], 500);
            }
        } elseif ($default_sms_gateway->title == "termii") {
            $client = new Client_termi();
            $url = 'https://api.ng.termii.com/api/sms/send';

            $payload = [
                'to' => $receiverNumber,
                'from' => env('TERMI_SENDER'),
                'sms' => $message_text,
                'type' => 'plain',
                'channel' => 'generic',
                'api_key' => env('TERMI_KEY'),
            ];

            try {
                $response = $client->post($url, ['json' => $payload]);
                $result = json_decode($response->getBody(), true);
                return response()->json($result);
            } catch (\Exception $e) {
                Log::error("Termii SMS Error: " . $e->getMessage());

                ErrorLog::create([
                    'context' => 'Termii SMS',
                    'message' => $e->getMessage(),
                    'details' => json_encode([
                        'receiver' => $receiverNumber,
                        'payload' => $payload,
                        'trace' => $e->getTraceAsString()
                    ]),
                ]);

                return response()->json(['status' => 'error', 'message' => 'Failed to send SMS'], 500);
            }
        } elseif ($default_sms_gateway->title == "infobip") {
            $BASE_URL = env("base_url");
            $API_KEY = env("api_key");
            $SENDER = env("sender_from");

            $configuration = (new Configuration())
                ->setHost($BASE_URL)
                ->setApiKeyPrefix('Authorization', 'App')
                ->setApiKey('Authorization', $API_KEY);

            $client = new Client_guzzle();

            $sendSmsApi = new SendSMSApi($client, $configuration);
            $destination = (new SmsDestination())->setTo($receiverNumber);
            $message = (new SmsTextualMessage())
                ->setFrom($SENDER)
                ->setText($message_text)
                ->setDestinations([$destination]);

            $request = (new SmsAdvancedTextualRequest())->setMessages([$message]);

            try {
                $smsResponse = $sendSmsApi->sendSmsMessage($request);
                Log::info("Infobip SMS sent successfully", [$smsResponse]);
            } catch (Throwable $apiException) {
                Log::error("Infobip SMS Error: " . $apiException->getMessage());

                ErrorLog::create([
                    'context' => 'Infobip SMS',
                    'message' => $apiException->getMessage(),
                    'details' => json_encode([
                        'receiver' => $receiverNumber,
                        'trace' => $apiException->getTraceAsString()
                    ]),
                ]);
                
                return response()->json(['status' => 'error', 'message' => $apiException->getMessage()], 500);
            }
        }

        return response()->json(['success' => true]);
    }



    public function Send_Subscription_Payment_Success_SMS($subscription_id, $invoice_id)
    {
        $subscription = Subscription::with('client')->findOrFail($subscription_id);
        $invoice = Sale::findOrFail($invoice_id);

        $settings = Setting::first();
        $default_sms_gateway = sms_gateway::where('id', $settings->sms_gateway)->first();

        $message_text = 'Hello {client_name}, your subscription at {business_name} has been successfully renewed.';

        $tags = [
            '{client_name}' => $subscription->client->name,
            '{business_name}' => $settings->CompanyName,
        ];

        $message_text = str_replace(array_keys($tags), array_values($tags), $message_text);

        $receiverNumber = $subscription->client->phone;

        // Example using Termii Gateway
        if ($default_sms_gateway->title == "termii") {
            $client = new Client_termi();
            $payload = [
                'to' => $receiverNumber,
                'from' => env('TERMI_SENDER'),
                'sms' => $message_text,
                'type' => 'plain',
                'channel' => 'generic',
                'api_key' => env('TERMI_KEY'),
            ];

            try {
                $response = $client->post('https://api.ng.termii.com/api/sms/send', ['json' => $payload]);
                $result = json_decode($response->getBody(), true);
                return response()->json($result);
            } catch (\Exception $e) {
                Log::error("Termii SMS Error: " . $e->getMessage());
                return response()->json(['status' => 'error', 'message' => 'Failed to send SMS'], 500);
            }

            
        }
    }
}
