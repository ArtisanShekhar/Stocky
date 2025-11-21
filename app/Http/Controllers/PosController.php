<?php

namespace App\Http\Controllers;
use App\Models\Account;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Client;
use App\Models\Language;
use App\Models\PaymentSale;
use App\Models\Product;
use App\Models\Setting;
use App\Models\PosSetting;
use App\Models\ProductVariant;
use App\Models\product_warehouse;
use App\Models\PaymentWithCreditCard;
use App\Models\Role;
use App\Models\Unit;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\DraftSale;
use App\Models\DraftSaleDetail;
use App\Models\Warehouse;
use App\utils\helpers;
use Carbon\Carbon;
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
use App\Mail\SaleMail;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Mail;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe;

class PosController extends BaseController
{

    //------------ Create New  POS --------------\\

    public function CreatePOS(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
            'payments' => 'required|array|min:1',
            'payments.*.amount' => 'required|numeric',
            'payments.*.payment_method_id' => 'required',
        ]);

        // Block overpayment if multiple methods used
        $totalPaid = collect($request->payments)->sum('amount');
        if (count($request->payments) > 1 && $totalPaid > $request->GrandTotal) {
            return response()->json([
                'message' => 'Total Paid Exceeds Grand Total',
                'change_return' => $totalPaid - $request->GrandTotal,
            ], 422);
        }

        $item = \DB::transaction(function () use ($request, $totalPaid) {
            $helpers = new helpers();
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $order = new Sale;

            $order->is_pos = 1;
            $order->date = Carbon::now();
            $order->time = now()->toTimeString();
            $order->Ref = app('App\Http\Controllers\SalesController')->getNumberOrder();
            $order->client_id = $request->client_id;
            $order->warehouse_id = $request->warehouse_id;
            $order->tax_rate = $request->tax_rate;
            $order->TaxNet = $request->TaxNet;
            $order->discount = $request->discount;
            $order->shipping = $request->shipping;
            $order->GrandTotal = $request->GrandTotal;
            $order->notes = $request->notes;
            $order->statut = 'completed';
            $order->payment_statut = 'unpaid';
            $order->user_id = Auth::user()->id;
            $order->save();

            $data = $request['details'];
            $total_points_earned = 0;

            foreach ($data as $key => $value) {

                $product = Product::find($value['product_id']);
                $unit = Unit::where('id', $value['sale_unit_id'])->first();
                $total_points_earned += $value['quantity'] * $product->points;

                $orderDetails[] = [
                    'date' => Carbon::now(),
                    'sale_id' => $order->id,
                    'sale_unit_id' =>  $value['sale_unit_id'],
                    'quantity' => $value['quantity'],
                    'product_id' => $value['product_id'],
                    'product_variant_id' => $value['product_variant_id'],
                    'total' => $value['subtotal'],
                    'price' => $value['Unit_price'],
                    'TaxNet' => $value['tax_percent'],
                    'tax_method' => $value['tax_method'],
                    'discount' => $value['discount'],
                    'discount_method' => $value['discount_Method'],
                    'imei_number' => $value['imei_number'],
                ];

                if ($value['product_variant_id'] !== null) {
                    $product_warehouse = product_warehouse::where('warehouse_id', $order->warehouse_id)
                        ->where('product_id', $value['product_id'])->where('product_variant_id', $value['product_variant_id'])
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
                    $product_warehouse = product_warehouse::where('warehouse_id', $order->warehouse_id)
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

            SaleDetail::insert($orderDetails);

            $sale = Sale::findOrFail($order->id);
            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === sale->id
                $this->authorizeForUser($request->user('api'), 'check_record', $sale);
            }


            foreach ($request->payments as $payment) {
                if (isset($payment['amount']) && $payment['amount'] > 0) {
                    $accountId = $request->account_id ?? null;
        
                    $originalAmount = $payment['amount'];
                    $paymentAmount = $originalAmount;
                    $changeReturn = 0;
        
                    // Adjust if overpaid in single-payment mode
                    if (count($request->payments) === 1 && $originalAmount > $request->GrandTotal) {
                        $paymentAmount = $request->GrandTotal;
                        $changeReturn = $originalAmount - $request->GrandTotal;
                    }
        
                    if ($payment['payment_method_id'] == 1 || $payment['payment_method_id'] == '1') {

                        $client = Client::findOrFail($request->client_id);
                        Stripe\Stripe::setApiKey(config('app.STRIPE_SECRET'));
        
                        $existing = PaymentWithCreditCard::where('customer_id', $request->client_id)->first();
        
                        if (!$existing) {
                            $customer = \Stripe\Customer::create([
                                'source' => $request->token,
                                'email' => $client->email,
                                'name' => $client->name,
                            ]);
                            $charge = \Stripe\Charge::create([
                                'amount' => $paymentAmount * 100,
                                'currency' => 'usd',
                                'customer' => $customer->id,
                            ]);
                            $customerStripeId = $customer->id;
                        } else {
                            $customerStripeId = $existing->customer_stripe_id;
                            $cardId = $request->card_id;
        
                            if ($request->is_new_credit_card) {
                                $card = \Stripe\Customer::createSource($customerStripeId, ['source' => $request->token]);
                                $charge = \Stripe\Charge::create([
                                    'amount' => $paymentAmount * 100,
                                    'currency' => 'usd',
                                    'customer' => $customerStripeId,
                                    'source' => $card->id,
                                ]);
                            } else {
                                $charge = \Stripe\Charge::create([
                                    'amount' => $paymentAmount * 100,
                                    'currency' => 'usd',
                                    'customer' => $customerStripeId,
                                    'source' => $cardId,
                                ]);
                            }
                        }
                    }
        
                    $paymentSale = PaymentSale::create([
                        'sale_id' => $order->id,
                        'account_id' => $accountId,
                        'Ref' => app('App\\Http\\Controllers\\PaymentSalesController')->getNumberOrder(),
                        'date' => Carbon::now(),
                        'payment_method_id' => $payment['payment_method_id'],
                        'montant' => $paymentAmount,
                        'change' => $changeReturn,
                        'notes' => $request['payment_note'] ?? null,
                        'user_id' => Auth::user()->id,
                    ]);
        
                    if ($payment['payment_method_id'] == 1 || $payment['payment_method_id'] == '1') {
                        PaymentWithCreditCard::create([
                            'customer_id' => $request->client_id,
                            'payment_id' => $paymentSale->id,
                            'customer_stripe_id' => $customerStripeId,
                            'charge_id' => $charge->id,
                        ]);
                    }
    
                    if ($accountId) {
                        $account = Account::find($accountId);
                        if ($account) {
                            $account->update(['balance' => $account->balance + $paymentAmount]);
                        }
                    }
                }
            }
    
            $totalPaidAdjusted = min($totalPaid, $request->GrandTotal);
            $due = $order->GrandTotal - $totalPaidAdjusted;

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
                'paid_amount'           => $totalPaidAdjusted,
                'payment_statut'        => $due <= 0 ? 'paid' : ($due < $order->GrandTotal ? 'partial' : 'unpaid')
            ]);

            return $order->id;

        }, 10);

       $emailError = null;
        $smsError = null;

        try {
            if ($request->send_email) {
                $this->Send_Email($item);
            }
        } catch (\Throwable $e) {
            \Log::error('Send_Email failed: ' . $e->getMessage());
            $emailError = $e->getMessage();
        }

        try {
            if ($request->send_sms) {
                $this->Send_SMS($item);
            }
        } catch (\Throwable $e) {
            \Log::error('Send_SMS failed: ' . $e->getMessage());
            $smsError = $e->getMessage();
        }

        $response = [
            'success' => true,
            'id' => $item,
        ];

        if ($emailError) {
            $response['email_error'] = $emailError;
        }

        if ($smsError) {
            $response['sms_error'] = $smsError;
        }

        return response()->json($response, 200);
    }

    public function Send_Email($id)
    {

        //sale
        $sale = Sale::with('client')->where('deleted_at', '=', null)->findOrFail($id);

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
        $invoice_url = url('/api/sale_pdf/' . $id.'?'.$random_number);
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
 
     public function Send_SMS($id)
     {

         //sale
         $sale = Sale::with('client')->where('deleted_at', '=', null)->findOrFail($id);
 
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
         $invoice_url = url('/api/sale_pdf/' . $id.'?'.$random_number);
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
 
         return response()->json(['success' => true]);
 
         
     }

    //------------- get_draft_sales -----------\\

    public function get_draft_sales(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        // How many items do you want to display.
        $perPage = $request->limit;

        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = 'id';
        $dir = 'DESC';
        $helpers = new helpers();
        
        $data = array();

        // Check If User Has Permission View  All Records
        $draft_sales = DraftSale::with('client', 'warehouse','user')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });
    

        $totalRows = $draft_sales->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        
        $drafts = $draft_sales->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($drafts as $draft) {
            
            $item['id'] = $draft['id'];
            $item['date'] = $draft['date'];
            $item['Ref'] = $draft['Ref'];
            $item['warehouse_name'] = $draft['warehouse']['name'];
            $item['client_name'] = $draft['client']['name'];
            $item['GrandTotal'] = number_format($draft['GrandTotal'], 2, '.', '');
            $item['actions'] = '';
            
            $data[] = $item;
        }
        
    
        return response()->json([
            'totalRows' => $totalRows,
            'draft_sales' => $data,
        ]);
    }
  

    //------------ Create Draft --------------\\

    public function CreateDraft(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
        ]);

        \DB::transaction(function () use ($request) {
            $helpers = new helpers();
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $order = new DraftSale;

            $order->date = Carbon::now();
            $order->Ref = $this->getNumberOrderDraft();
            $order->client_id = $request->client_id;
            $order->warehouse_id = $request->warehouse_id;
            $order->tax_rate = $request->tax_rate;
            $order->TaxNet = $request->TaxNet;
            $order->discount = $request->discount;
            $order->shipping = $request->shipping;
            $order->GrandTotal = $request->GrandTotal;
            $order->user_id = Auth::user()->id;

            $order->save();

            $data = $request['details'];
            foreach ($data as $key => $value) {

                $unit = Unit::where('id', $value['sale_unit_id'])->first();
                $orderDetails[] = [
                'date' => Carbon::now(),
                'draft_sale_id' => $order->id,
                'sale_unit_id' =>  $value['sale_unit_id'],
                'quantity' => $value['quantity'],
                'product_id' => $value['product_id'],
                'product_variant_id' => $value['product_variant_id'],
                'total' => $value['subtotal'],
                'price' => $value['Unit_price'],
                'TaxNet' => $value['tax_percent'],
                'tax_method' => $value['tax_method'],
                'discount' => $value['discount'],
                'discount_method' => $value['discount_Method'],
                'imei_number' => $value['imei_number'],
                ];
            }

            DraftSaleDetail::insert($orderDetails);

        }, 10);

        return response()->json(['success' => true]);
    }

     //------------ remove_draft_sale -------------\\

     public function remove_draft_sale(Request $request, $id)
     {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
 
         \DB::transaction(function () use ($id, $request) {
 
             $role = Auth::user()->roles()->first();
             $view_records = Role::findOrFail($role->id)->inRole('record_view');
             $draft = DraftSale::findOrFail($id);
 
             // Check If User Has Permission view All Records
             if (!$view_records) {
                 // Check If User->id === draft->id
                 $this->authorizeForUser($request->user('api'), 'check_record', $draft);
             }
             $draft->details()->delete();
             $draft->update([
                 'deleted_at' => Carbon::now(),
             ]);
 
         }, 10);
 
         return response()->json(['success' => true]);
     }

      //------------ submit_sale_from_draft --------------\\

    public function submit_sale_from_draft(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
            'payment.amount' => 'required',
        ]);

        $draft = DraftSale::findOrFail($request['draft_sale_id']);
        if($draft){

            $item = \DB::transaction(function () use ($request, $draft) {
                $helpers = new helpers();
                $role = Auth::user()->roles()->first();
                $view_records = Role::findOrFail($role->id)->inRole('record_view');
                $order = new Sale;

                $order->is_pos = 1;
                $order->date = Carbon::now();
                $order->time = now()->toTimeString();
                $order->Ref = app('App\Http\Controllers\SalesController')->getNumberOrder();
                $order->client_id = $request->client_id;
                $order->warehouse_id = $request->warehouse_id;
                $order->tax_rate = $request->tax_rate;
                $order->TaxNet = $request->TaxNet;
                $order->discount = $request->discount;
                $order->shipping = $request->shipping;
                $order->GrandTotal = $request->GrandTotal;
                $order->notes = $request->notes;
                $order->statut = 'completed';
                $order->payment_statut = 'unpaid';
                $order->user_id = Auth::user()->id;

                $order->save();

                $data = $request['details'];
                $total_points_earned = 0;
                foreach ($data as $key => $value) {

                    $product = Product::find($value['product_id']);
                    $unit = Unit::where('id', $value['sale_unit_id'])->first();
                    $total_points_earned += $value['quantity'] * $product->points;

                    $orderDetails[] = [
                        'date' => Carbon::now(),
                        'sale_id' => $order->id,
                        'sale_unit_id' =>  $value['sale_unit_id'],
                        'quantity' => $value['quantity'],
                        'product_id' => $value['product_id'],
                        'product_variant_id' => $value['product_variant_id'],
                        'total' => $value['subtotal'],
                        'price' => $value['Unit_price'],
                        'TaxNet' => $value['tax_percent'],
                        'tax_method' => $value['tax_method'],
                        'discount' => $value['discount'],
                        'discount_method' => $value['discount_Method'],
                        'imei_number' => $value['imei_number'],
                    ];

                    if ($value['product_variant_id'] !== null) {
                        $product_warehouse = product_warehouse::where('warehouse_id', $order->warehouse_id)
                            ->where('product_id', $value['product_id'])->where('product_variant_id', $value['product_variant_id'])
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
                        $product_warehouse = product_warehouse::where('warehouse_id', $order->warehouse_id)
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

                SaleDetail::insert($orderDetails);

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
                                
                    if($request['amount'] > 0){
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
                            $PaymentSale->notes     = $request->payment['notes'];
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
                                'account_id' => $request->payment['account_id']?$request->payment['account_id']:NULL,
                                'Ref' => app('App\Http\Controllers\PaymentSalesController')->getNumberOrder(),
                                'date' => Carbon::now(),
                                'payment_method_id' => $request->payment['payment_method_id'],
                                'montant' => $request['amount'],
                                'change' => $request['change'],
                                'notes' => $request->payment['notes'],
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

                $draft->details()->delete();
                $draft->update([
                    'deleted_at' => Carbon::now(),
                ]);

                return $order->id;

            }, 10);

            return response()->json(['success' => true, 'id' => $item]);
        }else{
            return response()->json(['success' => false], 404);
        }

    }

    //--------------------- data_draft_convert_sale ------------------------\\

    public function data_draft_convert_sale(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
        $settings = Setting::where('deleted_at', '=', null)->with('Client')->first();
        $accounts = Account::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id','account_name']);

        $draft_sale_data = DraftSale::with('details.product.unitSale')->where('deleted_at', '=', null)->findOrFail($id);

          //get warehouses assigned to user
          $user_auth = auth()->user();
          if($user_auth->is_all_warehouses){
             $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);

          }else{
             $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
             $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
          }
        

         if ($draft_sale_data->client_id) {
            $client = Client::where('id', $draft_sale_data->client_id)
                ->whereNull('deleted_at')
                ->first();
        
            if ($client) {
                $sale['client_id'] = $client->id;
                $client_name = $client->name;
        
                // ✅ Pass royalty data for frontend usage
                $defaultClient = $client->id;
                $default_client_points = $client->points;
                $default_client_eligible = $client->is_royalty_eligible;
            } else {
                $defaultClient = '';
                $client_name = '';
                $default_client_points = 0;
                $default_client_eligible = false;
            }
        } else {
            $defaultClient = '';
            $client_name = '';
            $default_client_points = 0;
            $default_client_eligible = false;
        }
        

        if ($draft_sale_data->warehouse_id) {
            if (Warehouse::where('id', $draft_sale_data->warehouse_id)->where('deleted_at', '=', null)->first()) {
                $sale['warehouse_id'] = $draft_sale_data->warehouse_id;
            } else {
                $sale['warehouse_id'] = '';
            }
        } else {
            $sale['warehouse_id'] = '';
        }

        $sale['tax_rate'] = $draft_sale_data->tax_rate;
        $sale['TaxNet']   = $draft_sale_data->TaxNet;
        $sale['discount'] = $draft_sale_data->discount;
        $sale['shipping'] = $draft_sale_data->shipping;
        $GrandTotal = $draft_sale_data->GrandTotal;

        $detail_id = 0;
        foreach ($draft_sale_data['details'] as $detail) {

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
                    ->where('warehouse_id', $draft_sale_data->warehouse_id)
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
                    ->where('deleted_at', '=', null)->where('warehouse_id', $draft_sale_data->warehouse_id)
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
            $data['fix_stock'] = $detail['product']['type'] !='is_service'?$stock:'---';
            $data['current'] = $detail['product']['type'] !='is_service'?$stock:'---';

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
            $data['subtotal'] = $detail->total;

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
                $data['Total_price'] = $data['Net_price'] +  $data['taxe'];
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet'] - $tax_price);
                $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                $data['Total_price'] = $data['Net_price'] +  $data['taxe'];
            }

            $details[] = $data;
        }
        
        $categories = Category::where('deleted_at', '=', null)->get(['id', 'name']);
        $brands = Brand::where('deleted_at', '=', null)->get();
        $stripe_key = config('app.STRIPE_KEY');
        $payment_methods = PaymentMethod::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'stripe_key'     => $stripe_key,
            'brands'         => $brands,
            'warehouse_id'   => $sale['warehouse_id'],
            'client_id'      => $sale['client_id'],
            'client_name'    => $client_name,
            'clients'        => $clients,
            'warehouses'     => $warehouses,
            'categories'     => $categories,
            'accounts'       => $accounts,
            'payment_methods'  => $payment_methods,
            'sale'           => $sale,
            'GrandTotal'     => $GrandTotal,
            'details'        => $details,
            'defaultClient'  => $defaultClient,
            'default_client_points'   => $default_client_points,
            'default_client_eligible' => $default_client_eligible,
            'point_to_amount_rate' => $settings->point_to_amount_rate,
        ]);
    }
 

    //------------ Get Products--------------\\

    public function GetProductsByParametre(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        // How many items do you want to display.
        $perPage = PosSetting::where('deleted_at', '=', null)->first()->products_per_page;

        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $product_warehouse_data = product_warehouse::where('warehouse_id', $request->warehouse_id)
            ->with('product', 'product.unitSale')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($request) {
                return $query->whereHas('product', function ($q) use ($request) {
                    $q->where('not_selling', '=', 0);
                })

                ->where(function ($query) use ($request) {
                    return $query->whereHas('product', function ($q) use ($request) {
                        if (isset($request->product_combo) && $request->product_combo == '1') {
                            $q->whereIn('type', ['is_combo', 'is_single', 'is_variant', 'is_service']);
                        } elseif (!isset($request->product_combo) || $request->product_combo == '0') {
                            $q->whereNotIn('type', ['is_combo']);
                        }
                    });
                })
                ->where(function ($query) use ($request) {
                    if ($request->stock == '1' && $request->product_service == '1') {
                        return $query->where('qte', '>', 0)->orWhere('manage_stock', false);
    
                    }elseif($request->stock == '1' && $request->product_service == '0') {
                        return $query->where('qte', '>', 0)->orWhere('manage_stock', true);
    
                    }else{
                        return $query->where('manage_stock', true);
                    }
                });
            })

        // Filter
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('category_id'), function ($query) use ($request) {
                    return $query->whereHas('product', function ($q) use ($request) {
                        $q->where('category_id', '=', $request->category_id);
                    });
                });
            })
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('brand_id'), function ($query) use ($request) {
                    return $query->whereHas('product', function ($q) use ($request) {
                        $q->where('brand_id', '=', $request->brand_id);
                    });
                });
            });

        $totalRows = $product_warehouse_data->count();

        $product_warehouse_data = $product_warehouse_data
            ->offset($offSet)
            ->limit($perPage)
            ->get();

        foreach ($product_warehouse_data as $product_warehouse) {
            if ($product_warehouse->product_variant_id) {
                $productsVariants = ProductVariant::where('product_id', $product_warehouse->product_id)
                    ->where('id', $product_warehouse->product_variant_id)
                    ->where('deleted_at', null)
                    ->first();

                $item['product_variant_id'] = $product_warehouse->product_variant_id;
                $item['Variant'] = '['.$productsVariants->name . ']' . $product_warehouse['product']->name;
                $item['name'] = '['.$productsVariants->name . ']' . $product_warehouse['product']->name;

                $item['code'] = $productsVariants->code;
                $item['barcode'] = $productsVariants->code;

                $product_price = $product_warehouse['productVariant']->price;

            } else {
                $item['product_variant_id'] = null;
                $item['Variant'] = null;
                $item['code'] = $product_warehouse['product']->code;
                $item['name'] = $product_warehouse['product']->name;
                $item['barcode'] = $product_warehouse['product']->code;

                $product_price =  $product_warehouse['product']->price;

            }
            $item['id'] = $product_warehouse->product_id;
            $firstimage = explode(',', $product_warehouse['product']->image);
            $item['image'] = $firstimage[0];

            if($product_warehouse['product']['unitSale']){

                if ($product_warehouse['product']['unitSale']->operator == '/') {
                    $item['qte_sale'] = $product_warehouse->qte * $product_warehouse['product']['unitSale']->operator_value;
                    $price = $product_price / $product_warehouse['product']['unitSale']->operator_value;

                } else {
                    $item['qte_sale'] = $product_warehouse->qte / $product_warehouse['product']['unitSale']->operator_value;
                    $price = $product_price * $product_warehouse['product']['unitSale']->operator_value;

                }

            }else{
                $item['qte_sale'] = $product_warehouse['product']->type!='is_service'?$product_warehouse->qte:'---';
                $price = $product_price;
            }

            $item['unitSale'] = $product_warehouse['product']['unitSale']?$product_warehouse['product']['unitSale']->ShortName:'';
            $item['qte'] = $product_warehouse['product']->type!='is_service'?$product_warehouse->qte:'---';
            $item['product_type'] = $product_warehouse['product']->type;
            
            //Discount
            if ($product_warehouse['product']->discount !== 0.0) {
                //percent %
                if ($product_warehouse['product']->discount_method == '1') {
                    $discount_price = $price * $product_warehouse['product']->discount / 100;
                    $price_discounted = $price - $discount_price;
                    // fixed
                } else {
                    $price_discounted = $price - $product_warehouse['product']->discount;
                }
            } else {
                $price_discounted = $price - $product_warehouse['product']->discount;
            }
            
            
            if ($product_warehouse['product']->TaxNet !== 0.0) {

                //Exclusive
                if ($product_warehouse['product']->tax_method == '1') {
                    $tax_price = $price_discounted * $product_warehouse['product']->TaxNet / 100;

                    $item['Net_price'] = $price_discounted + $tax_price;

                    // Inxclusive
                } else {
                    $item['Net_price'] = $price_discounted;
                }
            } else {
                $item['Net_price'] = $price_discounted;
            }

            $data[] = $item;
        }

        return response()->json([
            'products' => $data,
            'totalRows' => $totalRows,
        ]);
    }

    //--------------------- Get Element POS ------------------------\\

    public function GetELementPos(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
        $settings = Setting::where('deleted_at', '=', null)->with('Client')->first();
        $accounts = Account::where('deleted_at', '=', null)->orderBy('id', 'desc')->get(['id','account_name']);

          //get warehouses assigned to user
          $user_auth = auth()->user();
          if($user_auth->is_all_warehouses){
             $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);

             if ($settings->warehouse_id) {
                if (Warehouse::where('id', $settings->warehouse_id)->where('deleted_at', '=', null)->first()) {
                    $defaultWarehouse = $settings->warehouse_id;
                } else {
                    $defaultWarehouse = '';
                }
            } else {
                $defaultWarehouse = '';
            }

          }else{
             $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
             $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);

             if ($settings->warehouse_id) {
                if (Warehouse::where('id', $settings->warehouse_id)->whereIn('id', $warehouses_id)->where('deleted_at', '=', null)->first()) {
                    $defaultWarehouse = $settings->warehouse_id;
                } else {
                    $defaultWarehouse = '';
                }
            } else {
                $defaultWarehouse = '';
            }
          }

          if ($settings->client_id) {
            $client = Client::where('id', $settings->client_id)
                ->whereNull('deleted_at')
                ->first();
        
            if ($client) {
                $defaultClient = $client->id;
                $default_client_name = $client->name;
                $default_client_points = $client->points;
                $default_client_eligible = $client->is_royalty_eligible; // ✅ New
            } else {
                $defaultClient = '';
                $default_client_name = '';
                $default_client_points = 0;
                $default_client_eligible = false;
            }
        } else {
            $defaultClient = '';
            $default_client_name = '';
            $default_client_points = 0;
            $default_client_eligible = false;
        }

        $categories = Category::where('deleted_at', '=', null)->get(['id', 'name']);
        $brands = Brand::where('deleted_at', '=', null)->get();
        $stripe_key = config('app.STRIPE_KEY');
        $products_per_page = PosSetting::where('deleted_at', '=', null)->first()->products_per_page;
        $payment_methods = PaymentMethod::where('deleted_at', '=', null)->get(['id', 'name']);
        $languages_available = Language::where('is_active', true)->get(['name', 'locale', 'flag']);

        return response()->json([
            'stripe_key' => $stripe_key,
            'brands' => $brands,
            'defaultWarehouse' => $defaultWarehouse,
            'defaultClient' => $defaultClient,
            'default_client_name' => $default_client_name,
            'clients' => $clients,
            'warehouses' => $warehouses,
            'categories' => $categories,
            'accounts'           => $accounts,
            'payment_methods'    => $payment_methods,
            'languages_available'  => $languages_available,
            'products_per_page'  => $products_per_page,
            'default_client_points'   => $default_client_points,
            'default_client_eligible' => $default_client_eligible,
            'point_to_amount_rate' => $settings->point_to_amount_rate,
        ]);
    }


      //------------- Reference Number Draft -----------\\

      public function getNumberOrderDraft()
      {
  
          $last = DB::table('draft_sales')->latest('id')->first();
  
          if ($last) {
              $item = $last->Ref;
              $nwMsg = explode("_", $item);
              $inMsg = $nwMsg[1] + 1;
              $code = $nwMsg[0] . '_' . $inMsg;
          } else {
              $code = 'DR_1111';
          }
          return $code;
      }

}
