<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Deposit;
use App\Models\Unit;
use App\Models\PaymentPurchase;
use App\Models\PaymentPurchaseReturns;
use App\Models\PaymentSale;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DraftSale;
use App\Models\PaymentMethod;
use App\Models\PaymentSaleReturns;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\TransferDetail;
use App\Models\Adjustment;
use App\Models\AdjustmentDetail;
use App\Models\ProductVariant;
use App\Models\product_warehouse;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\Setting;
use App\Models\PurchaseDetail;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetails;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SaleReturn;
use App\Models\SaleReturnDetails;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use App\utils\helpers;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use DB;

class ReportController extends BaseController
{


    //----------- Get Last 5 Sales --------------\\

    public function Get_last_Sales()
    {

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $Sales = Sale::with('details', 'client', 'facture')->where('deleted_at', '=', null)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        foreach ($Sales as $Sale) {

            $item['Ref'] = $Sale['Ref'];
            $item['statut'] = $Sale['statut'];
            $item['client_name'] = $Sale['client']['name'];
            $item['GrandTotal'] = $Sale['GrandTotal'];
            $item['paid_amount'] = $Sale['paid_amount'];
            $item['due'] = $Sale['GrandTotal'] - $Sale['paid_amount'];
            $item['payment_status'] = $Sale['payment_statut'];

            $data[] = $item;
        }

        return response()->json($data);
    }


    //----------------- Customers Report -----------------------\\

    public function Client_Report(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $data = array();

        $clients = Client::where('deleted_at', '=', null)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('name', 'LIKE', "%{$request->search}%")
                        ->orWhere('code', 'LIKE', "%{$request->search}%")
                        ->orWhere('phone', 'LIKE', "%{$request->search}%");
                });
            });

        $totalRows = $clients->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $clients = $clients->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($clients as $client) {
            $item['total_sales'] = DB::table('sales')
                ->where('deleted_at', '=', null)
                ->where('client_id', $client->id)
                ->count();

            $item['total_amount'] = DB::table('sales')
                ->where('deleted_at', '=', null)
                ->where('statut', 'completed')
                ->where('client_id', $client->id)
                ->sum('GrandTotal');

            $item['total_paid'] = DB::table('sales')
                ->where('deleted_at', '=', null)
                ->where('statut', 'completed')
                ->where('client_id', $client->id)
                ->sum('paid_amount');

            $item['due'] = $item['total_amount'] - $item['total_paid'];

            $item['total_amount_return'] = DB::table('sale_returns')
                ->where('deleted_at', '=', null)
                ->where('client_id', $client->id)
                ->sum('GrandTotal');

            $item['total_paid_return'] = DB::table('sale_returns')
                ->where('sale_returns.deleted_at', '=', null)
                ->where('sale_returns.client_id', $client->id)
                ->sum('paid_amount');

            $item['return_Due'] = $item['total_amount_return'] - $item['total_paid_return'];

            $item['name'] = $client->name;
            $item['phone'] = $client->phone;
            $item['code'] = $client->code;
            $item['id'] = $client->id;

            $data[] = $item;
        }

        return response()->json([
            'report' => $data,
            'totalRows' => $totalRows,
        ]);

    }

    //----------------- Customers Report By ID-----------------------\\

    public function Client_Report_detail(request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);

        $client = Client::where('deleted_at', '=', null)->findOrFail($id);

        $data['total_sales'] = DB::table('sales')
            ->where('deleted_at', '=', null)
            ->where('client_id', $id)
            ->count();

        $data['total_amount'] = DB::table('sales')
            ->where('deleted_at', '=', null)
            ->where('statut', 'completed')
            ->where('client_id', $id)
            ->sum('GrandTotal');

        $data['total_paid'] = DB::table('sales')
            ->where('deleted_at', '=', null)
            ->where('statut', 'completed')
            ->where('client_id', $client->id)
            ->sum('paid_amount');

        $data['due'] = $data['total_amount'] - $data['total_paid'];

        return response()->json(['report' => $data]);
    }

    //----------------- Provider Report By ID-----------------------\\

    public function Provider_Report_detail(request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_suppliers', Provider::class);

        $provider = Provider::where('deleted_at', '=', null)->findOrFail($id);

        $data['total_purchase'] = DB::table('purchases')
            ->where('deleted_at', '=', null)
            ->where('provider_id', $id)
            ->count();

        $data['total_amount'] = DB::table('purchases')
            ->where('deleted_at', '=', null)
            ->where('statut', 'received')
            ->where('provider_id', $id)
            ->sum('GrandTotal');

        $data['total_paid'] = DB::table('purchases')
            ->where('deleted_at', '=', null)
            ->where('statut', 'received')
            ->where('provider_id', $id)
            ->sum('paid_amount');

        $data['due'] = $data['total_amount'] - $data['total_paid'];

        return response()->json(['report' => $data]);

    }

    //-------------------- Get Sales By Clients -------------\\

    public function Sales_Client(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $sales = Sale::where('deleted_at', '=', null)->with('client','warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where('client_id', $request->id)
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_statut', 'like', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $sales->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $sales = $sales->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($sales as $sale) {
            $item['id'] = $sale->id;
            $item['date'] = $sale->date;
            $item['Ref'] = $sale->Ref;
            $item['warehouse_name'] = $sale['warehouse']->name;
            $item['client_name'] = $sale['client']->name;
            $item['statut'] = $sale->statut;
            $item['GrandTotal'] = $sale->GrandTotal;
            $item['paid_amount'] = $sale->paid_amount;
            $item['due'] = $sale->GrandTotal - $sale->paid_amount;
            $item['payment_status'] = $sale->payment_statut;
            $item['shipping_status'] = $sale->shipping_status;
            
            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'sales' => $data,
        ]);

    }

    //-------------------- Get Payments By Clients -------------\\

    public function Payments_Client(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $payments = DB::table('payment_sales')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('payment_sales.user_id', '=', Auth::user()->id);
                }
            })
            ->where('payment_sales.deleted_at', '=', null)
            ->join('sales', 'payment_sales.sale_id', '=', 'sales.id')
            ->join('payment_methods', 'payment_sales.payment_method_id', '=', 'payment_methods.id')
            ->where('sales.client_id', $request->id)
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('payment_sales.Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_sales.date', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_methods.name', 'LIKE', "%{$request->search}%");
                });
            })
            ->select(
                'payment_sales.date', 'payment_sales.Ref AS Ref', 'sales.Ref AS Sale_Ref',
                'payment_methods.name as payment_method', 'payment_sales.montant'
            );

        $totalRows = $payments->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $payments = $payments->offset($offSet)
            ->limit($perPage)
            ->orderBy('payment_sales.id', 'desc')
            ->get();

        return response()->json([
            'payments' => $payments,
            'totalRows' => $totalRows,
        ]);

    }

    //-------------------- Get Quotations By Clients -------------\\

    public function Quotations_Client(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');
        $data = array();
        
        $Quotations = Quotation::with('client', 'warehouse')
            ->where('deleted_at', '=', null)
            ->where('client_id', $request->id)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            //Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Quotations->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $Quotations = $Quotations->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($Quotations as $Quotation) {

            $item['id'] = $Quotation->id;
            $item['date'] = $Quotation->date;
            $item['Ref'] = $Quotation->Ref;
            $item['statut'] = $Quotation->statut;
            $item['warehouse_name'] = $Quotation['warehouse']->name;
            $item['client_name'] = $Quotation['client']->name;
            $item['GrandTotal'] = $Quotation->GrandTotal;

            $data[] = $item;
        }

        return response()->json([
            'quotations' => $data,
            'totalRows' => $totalRows,
        ]);
    }

    //-------------------- Get Returns By Client -------------\\

    public function Returns_Client(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        //  Check If User Has Permission Show All Records
        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $SaleReturn = SaleReturn::where('deleted_at', '=', null)->with('sale','client','warehouse')
            ->where('client_id', $request->id)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_statut', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('sale', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $SaleReturn->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $SaleReturn = $SaleReturn->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($SaleReturn as $Sale_Return) {
            $item['id'] = $Sale_Return->id;
            $item['Ref'] = $Sale_Return->Ref;
            $item['statut'] = $Sale_Return->statut;
            $item['client_name'] = $Sale_Return['client']->name;
            $item['sale_ref'] = $Sale_Return['sale']?$Sale_Return['sale']->Ref:'---';
            $item['sale_id'] = $Sale_Return['sale']?$Sale_Return['sale']->id:NULL;
            $item['warehouse_name'] = $Sale_Return['warehouse']->name;
            $item['GrandTotal'] = $Sale_Return->GrandTotal;
            $item['paid_amount'] = $Sale_Return->paid_amount;
            $item['due'] = $Sale_Return->GrandTotal - $Sale_Return->paid_amount;
            $item['payment_status'] = $Sale_Return->payment_statut;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'returns_customer' => $data,
        ]);
    }



    //------------- Show Report Purchases ----------\\

    public function Report_Purchases(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'ReportPurchases', Purchase::class);
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
        );
        $columns = array(
            0 => 'Ref',
            1 => 'statut',
            2 => 'provider_id',
            3 => 'payment_statut',
            4 => 'warehouse_id',
        );
        $data = array();
        $total = 0;

        $Purchases = Purchase::select('purchases.*')
            ->with('facture', 'provider', 'warehouse')
            ->join('providers', 'purchases.provider_id', '=', 'providers.id')
            ->where('purchases.deleted_at', '=', null)
            ->whereBetween('purchases.date', array($request->from, $request->to));
            
        //  Check If User Has Permission Show All Records
        $Purchases = $helpers->Show_Records($Purchases);
        //Multiple Filter
        $Filtred = $helpers->filter($Purchases, $columns, $param, $request)
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('Ref', 'LIKE', "%{$request->search}%")
                    ->orWhere('statut', 'LIKE', "%{$request->search}%")
                    ->orWhere('GrandTotal', $request->search)
                    ->orWhere('payment_statut', 'like', "$request->search")
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('provider', function ($q) use ($request) {
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
        $Purchases = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy('purchases.' . $order, $dir)
            ->get();

        foreach ($Purchases as $Purchase) {

            $item['id'] = $Purchase->id;
            $item['date'] = $Purchase->date;
            $item['Ref'] = $Purchase->Ref;
            $item['warehouse_name'] = $Purchase['warehouse']->name;
            $item['discount'] = $Purchase->discount;
            $item['shipping'] = $Purchase->shipping;
            $item['statut'] = $Purchase->statut;
            $item['provider_name'] = $Purchase['provider']->name;
            $item['provider_email'] = $Purchase['provider']->email;
            $item['provider_tele'] = $Purchase['provider']->phone;
            $item['provider_code'] = $Purchase['provider']->code;
            $item['provider_adr'] = $Purchase['provider']->adresse;
            $item['GrandTotal'] = $Purchase['GrandTotal'];
            $item['paid_amount'] = $Purchase['paid_amount'];
            $item['due'] = $Purchase['GrandTotal'] - $Purchase['paid_amount'];
            $item['payment_status'] = $Purchase['payment_statut'];

            $data[] = $item;
        }

        $suppliers = provider::where('deleted_at', '=', null)->get(['id', 'name']);

            //get warehouses assigned to user
            $user_auth = auth()->user();
            if($user_auth->is_all_warehouses){
                $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
            }else{
                $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
                $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
            } 

        return response()->json([
            'totalRows' => $totalRows,
            'purchases' => $data,
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
        ]);
    }
    
    //------------- Show Report SALES -----------\\

    public function Report_Sales(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Reports_sales', Sale::class);
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
        );
        $columns = array(
            0 => 'Ref',
            1 => 'statut',
            2 => 'client_id',
            3 => 'payment_statut',
            4 => 'warehouse_id',
            5 => 'warehouse_id',
        );
        
        $data = array();

        $Sales = Sale::select('sales.*')
            ->with('facture', 'client', 'warehouse','user')
            ->join('clients', 'sales.client_id', '=', 'clients.id')
            ->where('sales.deleted_at', '=', null)
            ->whereBetween('sales.date', array($request->from, $request->to));

        //  Check If User Has Permission Show All Records
        $Sales = $helpers->Show_Records($Sales);
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
                        return $query->whereHas('user', function ($q) use ($request) {
                            $q->where('username', 'LIKE', "%{$request->search}%");
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
            ->orderBy('sales.' . $order, $dir)
            ->get();

        foreach ($Sales as $Sale) {

            $item['id'] = $Sale['id'];
            $item['date'] = $Sale['date'];
            $item['Ref'] = $Sale['Ref'];
            $item['statut'] = $Sale['statut'];
            $item['discount'] = $Sale['discount'];
            $item['shipping'] = $Sale['shipping'];
            $item['warehouse_name'] = $Sale['warehouse']['name'];
            $item['seller'] = $Sale['user']['username'];
            $item['client_name'] = $Sale['client']['name'];
            $item['client_email'] = $Sale['client']['email'];
            $item['client_tele'] = $Sale['client']['phone'];
            $item['client_code'] = $Sale['client']['code'];
            $item['client_adr'] = $Sale['client']['adresse'];
            $item['GrandTotal'] = $Sale['GrandTotal'];
            $item['paid_amount'] = $Sale['paid_amount'];
            $item['due'] = $Sale['GrandTotal'] - $Sale['paid_amount'];
            $item['payment_status'] = $Sale['payment_statut'];

            $data[] = $item;
        }

        $customers = client::where('deleted_at', '=', null)->get(['id', 'name']);
        $sellers = User::where('deleted_at', '=', null)->get(['id', 'username']);

            //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json(
            [
                'totalRows' => $totalRows,
                'sales' => $data,
                'sellers' => $sellers,
                'customers' => $customers, 
                'warehouses' => $warehouses
            ]
        );
    }

    //----------------- Providers Report -----------------------\\

    public function Providers_Report(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_suppliers', Provider::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $data = array();

        $providers = Provider::where('deleted_at', '=', null)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('name', 'LIKE', "%{$request->search}%")
                        ->orWhere('code', 'LIKE', "%{$request->search}%")
                        ->orWhere('phone', 'LIKE', "%{$request->search}%");
                });
            });

        $totalRows = $providers->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $providers = $providers->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($providers as $provider) {
            $item['total_purchase'] = DB::table('purchases')
                ->where('deleted_at', '=', null)
                ->where('provider_id', $provider->id)
                ->count();

            $item['total_amount'] = DB::table('purchases')
                ->where('deleted_at', '=', null)
                ->where('statut', 'received')
                ->where('provider_id', $provider->id)
                ->sum('GrandTotal');

            $item['total_paid'] = DB::table('purchases')
                ->where('deleted_at', '=', null)
                ->where('statut', 'received')
                ->where('provider_id', $provider->id)
                ->sum('paid_amount');

            $item['due'] = $item['total_amount'] - $item['total_paid'];



            $item['total_amount_return'] = DB::table('purchase_returns')
            ->where('deleted_at', '=', null)
            ->where('provider_id', $provider->id)
            ->sum('GrandTotal');

            $item['total_paid_return'] = DB::table('purchase_returns')
                ->where('deleted_at', '=', null)
                ->where('provider_id', $provider->id)
                ->sum('paid_amount');

            $item['return_Due'] = $item['total_amount_return'] - $item['total_paid_return'];

            $item['id'] = $provider->id;
            $item['name'] = $provider->name;
            $item['phone'] = $provider->phone;
            $item['code'] = $provider->code;

            $data[] = $item;
        }

        return response()->json([
            'report' => $data,
            'totalRows' => $totalRows,
        ]);

    }

    //-------------------- Get Purchases By Provider -------------\\

    public function Purchases_Provider(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_suppliers', Provider::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $purchases = Purchase::where('deleted_at', '=', null)
            ->with('provider','warehouse')
            ->where('provider_id', $request->id)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_statut', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('provider', function ($q) use ($request) {
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

        $totalRows = $purchases->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $purchases = $purchases->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($purchases as $purchase) {
            $item['id'] = $purchase->id;
            $item['Ref'] = $purchase->Ref;
            $item['warehouse_name'] = $purchase['warehouse']->name;
            $item['provider_name'] = $purchase['provider']->name;
            $item['statut'] = $purchase->statut;
            $item['GrandTotal'] = $purchase->GrandTotal;
            $item['paid_amount'] = $purchase->paid_amount;
            $item['due'] = $purchase->GrandTotal - $purchase->paid_amount;
            $item['payment_status'] = $purchase->payment_statut;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'purchases' => $data,
        ]);

    }

    //-------------------- Get Payments By Provider -------------\\

    public function Payments_Provider(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_suppliers', Provider::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $payments = DB::table('payment_purchases')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where('payment_purchases.deleted_at', '=', null)
            ->join('purchases', 'payment_purchases.purchase_id', '=', 'purchases.id')
            ->join('payment_methods', 'payment_purchases.payment_method_id', '=', 'payment_methods.id')
            ->where('purchases.provider_id', $request->id)
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('payment_purchases.Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_purchases.date', 'LIKE', "%{$request->search}%")
                         ->orWhere('payment_methods.name', 'LIKE', "%{$request->search}%");
                });
            })
            ->select(
                'payment_purchases.date', 'payment_purchases.Ref AS Ref', 'purchases.Ref AS purchase_Ref',
                'payment_methods.name as payment_method', 'payment_purchases.montant'
            );

        $totalRows = $payments->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $payments = $payments->offset($offSet)
            ->limit($perPage)
            ->orderBy('payment_purchases.id', 'desc')
            ->get();

        return response()->json([
            'payments' => $payments,
            'totalRows' => $totalRows,
        ]);
    }

    //-------------------- Get Returns By Providers -------------\\

    public function Returns_Provider(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_suppliers', Provider::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $PurchaseReturn = PurchaseReturn::where('deleted_at', '=', null)
            ->with('purchase','provider','warehouse')
            ->where('provider_id', $request->id)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_statut', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('provider', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('purchase', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $PurchaseReturn->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $PurchaseReturn = $PurchaseReturn->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($PurchaseReturn as $Purchase_Return) {
            $item['id'] = $Purchase_Return->id;
            $item['Ref'] = $Purchase_Return->Ref;
            $item['statut'] = $Purchase_Return->statut;
            $item['purchase_ref'] = $Purchase_Return['purchase']?$Purchase_Return['purchase']->Ref:'---';
            $item['purchase_id'] = $Purchase_Return['purchase']?$Purchase_Return['purchase']->id:NULL;
            $item['provider_name'] = $Purchase_Return['provider']->name;
            $item['warehouse_name'] = $Purchase_Return['warehouse']->name;
            $item['GrandTotal'] = $Purchase_Return->GrandTotal;
            $item['paid_amount'] = $Purchase_Return->paid_amount;
            $item['due'] = $Purchase_Return->GrandTotal - $Purchase_Return->paid_amount;
            $item['payment_status'] = $Purchase_Return->payment_statut;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'returns_supplier' => $data,
        ]);

    }

    //-------------------- Top 5 Suppliers -------------\\

    public function ToProviders(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_suppliers', Provider::class);

        $results = DB::table('purchases')->where('purchases.deleted_at', '=', null)
            ->join('providers', 'purchases.provider_id', '=', 'providers.id')
            ->select(DB::raw('providers.name'), DB::raw('count(*) as count'))
            ->groupBy('providers.name')
            ->orderBy('count', 'desc')
            ->take(5)
            ->get();

        $data = [];
        $providers = [];
        foreach ($results as $result) {
            $providers[] = $result->name;
            $data[] = $result->count;
        }
        $data[] = 0;
        return response()->json(['providers' => $providers, 'data' => $data]);
    }

    //----------------- Warehouse Report By ID-----------------------\\

    public function Warehouse_Report(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'WarehouseStock', Product::class);

        $data['sales'] = Sale::where('deleted_at', '=', null)
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })->count();

        $data['purchases'] = Purchase::where('deleted_at', '=', null)
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })->count();

        $data['ReturnPurchase'] = PurchaseReturn::where('deleted_at', '=', null)
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })->count();

        $data['ReturnSale'] = SaleReturn::where('deleted_at', '=', null)
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })->count();

        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json([
            'data' => $data,
            'warehouses' => $warehouses,
        ], 200);

    }

    //-------------------- Get Sales By Warehouse -------------\\

    public function Sales_Warehouse(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'WarehouseStock', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = [];

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $sales = Sale::where('deleted_at', '=', null)->with('client','warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('GrandTotal', $request->search)
                        ->orWhere('payment_statut', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $sales->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $sales = $sales->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($sales as $sale) {
            $item['id'] = $sale->id;
            $item['date'] = $sale->date;
            $item['Ref'] = $sale->Ref;
            $item['client_name'] = $sale['client']->name;
            $item['warehouse_name'] = $sale['warehouse']->name;
            $item['statut'] = $sale->statut;
            $item['GrandTotal'] = $sale->GrandTotal;
            $item['paid_amount'] = $sale->paid_amount;
            $item['due'] = $sale->GrandTotal - $sale->paid_amount;
            $item['payment_status'] = $sale->payment_statut;
            $item['shipping_status'] = $sale->shipping_status;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'sales' => $data,
        ]);

    }

    //-------------------- Get Quotations By Warehouse -------------\\

    public function Quotations_Warehouse(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'WarehouseStock', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = [];

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $Quotations = Quotation::where('deleted_at', '=', null)
            ->with('client','warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })
        //Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('GrandTotal', $request->search)
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });
        $totalRows = $Quotations->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $Quotations = $Quotations->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($Quotations as $Quotation) {
            $item['id'] = $Quotation->id;
            $item['date'] = $Quotation->date;
            $item['Ref'] = $Quotation->Ref;
            $item['warehouse_name'] = $Quotation['warehouse']->name;
            $item['client_name'] = $Quotation['client']->name;
            $item['statut'] = $Quotation->statut;
            $item['GrandTotal'] = $Quotation->GrandTotal;

            $data[] = $item;
        }

        return response()->json([
            'quotations' => $data,
            'totalRows' => $totalRows,
        ]);
    }

    //-------------------- Get Returns Sale By Warehouse -------------\\

    public function Returns_Sale_Warehouse(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'WarehouseStock', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        //  Check If User Has Permission Show All Records
        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $SaleReturn = SaleReturn::where('deleted_at', '=', null)
            ->with('sale','client','warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })
        //Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('GrandTotal', $request->search)
                        ->orWhere('payment_statut', 'like', "$request->search")

                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('sale', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $SaleReturn->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $SaleReturn = $SaleReturn->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($SaleReturn as $Sale_Return) {
            $item['id'] = $Sale_Return->id;
            $item['warehouse_name'] = $Sale_Return['warehouse']->name;
            $item['Ref'] = $Sale_Return->Ref;
            $item['statut'] = $Sale_Return->statut;
            $item['client_name'] = $Sale_Return['client']->name;
            $item['sale_ref'] = $Sale_Return['sale']?$Sale_Return['sale']->Ref:'---';
            $item['sale_id'] = $Sale_Return['sale']?$Sale_Return['sale']->id:NULL;
            $item['GrandTotal'] = $Sale_Return->GrandTotal;
            $item['paid_amount'] = $Sale_Return->paid_amount;
            $item['due'] = $Sale_Return->GrandTotal - $Sale_Return->paid_amount;
            $item['payment_status'] = $Sale_Return->payment_statut;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'returns_sale' => $data,
        ]);
    }

    //-------------------- Get Returns Purchase By Warehouse -------------\\

    public function Returns_Purchase_Warehouse(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'WarehouseStock', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        //  Check If User Has Permission Show All Records
        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $PurchaseReturn = PurchaseReturn::where('deleted_at', '=', null)
            ->with('purchase','provider','warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->orWhere(function ($query) use ($request) {
                return $query->whereHas('purchase', function ($q) use ($request) {
                    $q->where('Ref', 'LIKE', "%{$request->search}%");
                });
            })
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })
        //Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('GrandTotal', $request->search)
                        ->orWhere('payment_statut', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('provider', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $PurchaseReturn->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $PurchaseReturn = $PurchaseReturn->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($PurchaseReturn as $Purchase_Return) {
            $item['id'] = $Purchase_Return->id;
            $item['Ref'] = $Purchase_Return->Ref;
            $item['statut'] = $Purchase_Return->statut;
            $item['purchase_ref'] = $Purchase_Return['purchase']?$Purchase_Return['purchase']->Ref:'---';
            $item['purchase_id'] = $Purchase_Return['purchase']?$Purchase_Return['purchase']->id:NULL;
            $item['warehouse_name'] = $Purchase_Return['warehouse']->name;
            $item['provider_name'] = $Purchase_Return['provider']->name;
            $item['GrandTotal'] = $Purchase_Return->GrandTotal;
            $item['paid_amount'] = $Purchase_Return->paid_amount;
            $item['due'] = $Purchase_Return->GrandTotal - $Purchase_Return->paid_amount;
            $item['payment_status'] = $Purchase_Return->payment_statut;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'returns_purchase' => $data,
        ]);
    }

    //-------------------- Get Expenses By Warehouse -------------\\

    public function Expenses_Warehouse(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'WarehouseStock', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        //  Check If User Has Permission Show All Records
        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $Expenses = Expense::where('deleted_at', '=', null)
            ->with('expense_category','warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse_id);
                });
            })
        //Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('date', 'LIKE', "%{$request->search}%")
                        ->orWhere('details', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('expense_category', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Expenses->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $Expenses = $Expenses->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($Expenses as $Expense) {

            $item['date'] = $Expense->date;
            $item['Ref'] = $Expense->Ref;
            $item['details'] = $Expense->details;
            $item['amount'] = $Expense->amount;
            $item['warehouse_name'] = $Expense['warehouse']->name;
            $item['category_name'] = $Expense['expense_category']->name;
            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'expenses' => $data,
        ]);
    }

    //----------------- Warhouse Count Stock -----------------------\\

    public function Warhouse_Count_Stock(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'WarehouseStock', Product::class);

        $stock_count = product_warehouse::join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->join('warehouses', 'product_warehouse.warehouse_id', '=', 'warehouses.id')
            ->where('product_warehouse.deleted_at', '=', null)
            ->select(
                DB::raw("count(DISTINCT products.id) as value"),
                DB::raw("warehouses.name as name"),
                DB::raw('(IFNULL(SUM(qte),0)) AS value1'),
            )
            ->where('qte', '>', 0)
            ->groupBy('warehouses.name')
            ->get();

        $stock_value = DB::table('product_warehouse')
            ->leftJoin('products', 'product_warehouse.product_id', '=', 'products.id')
            ->leftJoin('warehouses', 'product_warehouse.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('product_variants', function ($join) {
                $join->on('product_warehouse.product_variant_id', '=', 'product_variants.id')
                        ->whereNotNull('product_warehouse.product_variant_id');
            })
            ->whereNull('product_warehouse.deleted_at')
            ->select(
                DB::raw("SUM(COALESCE(product_variants.price, products.price) * qte) as price"),
                DB::raw("SUM(COALESCE(product_variants.cost, products.cost) * qte) as cost"),
                'warehouses.name as name'
            )
            ->where('qte', '>', 0)
            ->groupBy('warehouses.name')
            ->get();

        $data = [];
        foreach ($stock_value as $key => $value) {
            $item['name'] = $value->name;
            $item['value'] = $value->price;
            $item['value1'] = $value->cost;
            $data[] = $item;
        }

        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json([
            'stock_count' => $stock_count,
            'stock_value' => $data,
            'warehouses' => $warehouses,
        ]);

    }

    //-------------- Count  Product Quantity Alerts ---------------\\

    public function count_quantity_alert(request $request)
    {

        $products_alerts = product_warehouse::join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->whereRaw('qte <= stock_alert')
            ->count();

        return response()->json($products_alerts);
    }


     //-----------------Profit And Loss ---------------------------\\

    public function ProfitAndLoss(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Reports_profit', Client::class);

        $start = Carbon::parse($request->from)->toDateString();
        $end   = Carbon::parse($request->to)->toDateString();

        // Warehouses visible to user
        $user = $request->user('api');
        if ($user->is_all_warehouses) {
            $warehouses   = Warehouse::whereNull('deleted_at')->get(['id','name']);
            $warehouseIds = $warehouses->pluck('id')->all();
        } else {
            $warehouseIds = UserWarehouse::where('user_id', $user->id)->pluck('warehouse_id')->all();
            $warehouses   = Warehouse::whereNull('deleted_at')->whereIn('id', $warehouseIds)->get(['id','name']);
        }
        $warehouseId = (int) ($request->warehouse_id ?: 0);

        // Helper closures
        $applyWarehouse = fn ($q) => $warehouseId
            ? $q->where('warehouse_id', $warehouseId)
            : $q->whereIn('warehouse_id', $warehouseIds);

        // -------------------- Aggregates --------------------

        // Sales
        $salesAgg = Sale::whereNull('deleted_at')
            ->where('statut','completed')
            ->whereBetween('date', [$start, $end])
            ->where($applyWarehouse)
            ->selectRaw('COALESCE(SUM(GrandTotal),0) AS sum, COUNT(*) AS nmbr')
            ->first();

        // Purchases (received)
        $purchAgg = Purchase::whereNull('deleted_at')
            ->where('statut','received')
            ->whereBetween('date', [$start, $end])
            ->where($applyWarehouse)
            ->selectRaw('COALESCE(SUM(GrandTotal),0) AS sum, COUNT(*) AS nmbr')
            ->first();

        // Sales returns (received)
        $saleRetAgg = SaleReturn::whereNull('deleted_at')
            ->where('statut','received')
            ->whereBetween('date', [$start, $end])
            ->where($applyWarehouse)
            ->selectRaw('COALESCE(SUM(GrandTotal),0) AS sum, COUNT(*) AS nmbr')
            ->first();

        // Purchase returns (completed)
        $purchRetAgg = PurchaseReturn::whereNull('deleted_at')
            ->where('statut','completed')
            ->whereBetween('date', [$start, $end])
            ->where($applyWarehouse)
            ->selectRaw('COALESCE(SUM(GrandTotal),0) AS sum, COUNT(*) AS nmbr')
            ->first();

        // Payment sales (JOIN for warehouse filter)
        $paySales = PaymentSale::join('sales as s','s.id','=','payment_sales.sale_id')
            ->whereNull('payment_sales.deleted_at')
            ->whereBetween('payment_sales.date', [$start,$end])
            ->when($warehouseId, fn($q)=>$q->where('s.warehouse_id',$warehouseId),
                              fn($q)=>$q->whereIn('s.warehouse_id',$warehouseIds))
            ->selectRaw('COALESCE(SUM(payment_sales.montant),0) AS sum')
            ->value('sum');

        // Payment sale returns
        $paySaleRet = PaymentSaleReturns::join('sale_returns as sr','sr.id','=','payment_sale_returns.sale_return_id')
            ->whereNull('payment_sale_returns.deleted_at')
            ->whereBetween('payment_sale_returns.date', [$start,$end])
            ->when($warehouseId, fn($q)=>$q->where('sr.warehouse_id',$warehouseId),
                              fn($q)=>$q->whereIn('sr.warehouse_id',$warehouseIds))
            ->selectRaw('COALESCE(SUM(payment_sale_returns.montant),0) AS sum')
            ->value('sum');

        // Payment purchase returns
        $payPurchRet = PaymentPurchaseReturns::join('purchase_returns as pr','pr.id','=','payment_purchase_returns.purchase_return_id')
            ->whereNull('payment_purchase_returns.deleted_at')
            ->whereBetween('payment_purchase_returns.date', [$start,$end])
            ->when($warehouseId, fn($q)=>$q->where('pr.warehouse_id',$warehouseId),
                              fn($q)=>$q->whereIn('pr.warehouse_id',$warehouseIds))
            ->selectRaw('COALESCE(SUM(payment_purchase_returns.montant),0) AS sum')
            ->value('sum');

        // Payment purchases
        $payPurch = PaymentPurchase::join('purchases as p','p.id','=','payment_purchases.purchase_id')
            ->whereNull('payment_purchases.deleted_at')
            ->whereBetween('payment_purchases.date', [$start,$end])
            ->when($warehouseId, fn($q)=>$q->where('p.warehouse_id',$warehouseId),
                              fn($q)=>$q->whereIn('p.warehouse_id',$warehouseIds))
            ->selectRaw('COALESCE(SUM(payment_purchases.montant),0) AS sum')
            ->value('sum');

        // Expenses
        $expenses = Expense::whereNull('deleted_at')
            ->whereBetween('date', [$start,$end])
            ->where($applyWarehouse)
            ->selectRaw('COALESCE(SUM(amount),0) AS sum')
            ->value('sum');

        // -------------------- COGS & Average Cost --------------------
        $cogsPack = $this->calcCogsAndAvgCostFast($start, $end, $warehouseId, $warehouseIds);
        $cogsFIFO = $cogsPack['fifo'];
        $avgCostTotal = $cogsPack['avg'];

        // -------------------- Compose response (numeric; format in UI) --------------------
        $salesSum    = (float) $salesAgg->sum;
        $purchSum    = (float) $purchAgg->sum;
        $saleRetSum  = (float) $saleRetAgg->sum;
        $purchRetSum = (float) $purchRetAgg->sum;

        $data = [
            'sales_sum'               => $salesSum,
            'sales_count'             => (int) $salesAgg->nmbr,
            'purchases_sum'           => (float) $purchSum,
            'purchases_count'         => (int) $purchAgg->nmbr,
            'returns_sales_sum'       => (float) $saleRetSum,
            'returns_sales_count'     => (int) $saleRetAgg->nmbr,
            'returns_purchases_sum'   => (float) $purchRetSum,
            'returns_purchases_count' => (int) $purchRetAgg->nmbr,

            'paiement_sales'          => (float) $paySales,
            'PaymentSaleReturns'      => (float) $paySaleRet,
            'PaymentPurchaseReturns'  => (float) $payPurchRet,
            'paiement_purchases'      => (float) $payPurch,
            'expenses_sum'            => (float) $expenses,

            'product_cost_fifo'       => (float) $cogsFIFO,
            'averagecost'             => (float) $avgCostTotal,

            'profit_fifo'             => $salesSum - $cogsFIFO,
            'profit_average_cost'     => $salesSum - $avgCostTotal,

            'payment_received'        => (float) ($paySales + $payPurchRet),
            'payment_sent'            => (float) ($payPurch + $paySaleRet + $expenses),
            'paiement_net'            => (float) (($paySales + $payPurchRet) - ($payPurch + $paySaleRet + $expenses)),

            'total_revenue'           => (float) ($salesSum - $saleRetSum),
        ];

        return response()->json([
            'data'        => $data,
            'warehouses'  => $warehouses,
        ]);
    }

    /**
     * Fast COGS using:
     *  - FIFO: purchases grouped once + pointer burn-up to start date
     *  - AVG: set-based average cost per product/variant at end date
     */
    protected function calcCogsAndAvgCostFast(string $start, string $end, int $warehouseId, array $warehouseIds): array
    {
        // Keys (products actually sold in period)
        $soldKeys = SaleDetail::join('sales as s','s.id','=','sale_details.sale_id')
            ->where('s.statut','completed')
            ->when($warehouseId, fn($q)=>$q->where('s.warehouse_id',$warehouseId),
                              fn($q)=>$q->whereIn('s.warehouse_id',$warehouseIds))
            ->whereBetween('sale_details.date', [$start,$end])
            ->select('sale_details.product_id','sale_details.product_variant_id')
            ->distinct()->get();

        if ($soldKeys->isEmpty()) {
            return ['fifo'=>0.0, 'avg'=>0.0];
        }

        $key = fn($pid,$vid)=> $pid.':'.($vid ?? 'null');

        $productIds   = $soldKeys->pluck('product_id')->unique()->values();
        $variantIds   = $soldKeys->pluck('product_variant_id')->unique()->filter()->values();

        // Sales qty in period per key
        $salesQty = SaleDetail::join('sales as s','s.id','=','sale_details.sale_id')
            ->where('s.statut','completed')
            ->when($warehouseId, fn($q)=>$q->where('s.warehouse_id',$warehouseId),
                              fn($q)=>$q->whereIn('s.warehouse_id',$warehouseIds))
            ->whereBetween('sale_details.date', [$start,$end])
            ->select('sale_details.product_id','sale_details.product_variant_id', DB::raw('SUM(sale_details.quantity) as qty'))
            ->groupBy('sale_details.product_id','sale_details.product_variant_id')
            ->get()
            ->keyBy(fn($r)=>$key($r->product_id,$r->product_variant_id));

        // Sales qty before start (to burn FIFO layers)
        $salesBefore = SaleDetail::join('sales as s','s.id','=','sale_details.sale_id')
            ->where('s.statut','completed')
            ->when($warehouseId, fn($q)=>$q->where('s.warehouse_id',$warehouseId),
                              fn($q)=>$q->whereIn('s.warehouse_id',$warehouseIds))
            ->where('sale_details.date','<',$start)
            ->select('sale_details.product_id','sale_details.product_variant_id', DB::raw('SUM(sale_details.quantity) as qty'))
            ->groupBy('sale_details.product_id','sale_details.product_variant_id')
            ->get()
            ->keyBy(fn($r)=>$key($r->product_id,$r->product_variant_id));

        // Purchases (all time up to end) grouped and ordered (for FIFO)
        $purchases = PurchaseDetail::join('purchases as p','p.id','=','purchase_details.purchase_id')
            ->where('p.statut','received')
            ->when($warehouseId, fn($q)=>$q->where('p.warehouse_id',$warehouseId),
                              fn($q)=>$q->whereIn('p.warehouse_id',$warehouseIds))
            ->whereIn('purchase_details.product_id', $productIds)
            ->select([
                'purchase_details.product_id',
                'purchase_details.product_variant_id',
                'purchase_details.quantity',
                'purchase_details.cost',
                'p.date',
            ])
            ->orderBy('p.date','asc')
            ->get()
            ->groupBy(fn($r)=>$key($r->product_id,$r->product_variant_id));

        // Average cost per key at end date (set-based)
        $avgCost = $this->averageCostBulk($productIds->all(), $variantIds->all(), $end, $warehouseId, $warehouseIds);

        // FIFO: iterate keys once with preloaded rows
        $totalFifo = 0.0;
        $totalAvg  = 0.0;

        foreach ($soldKeys as $k) {
            $kstr = $key($k->product_id, $k->product_variant_id);
            $qtySold = (float) ($salesQty[$kstr]->qty ?? 0);
            if ($qtySold <= 0) continue;

            // ---- AVG ----
            $avg = (float) ($avgCost[$kstr] ?? 0);
            $totalAvg += $avg * $qtySold;

            // ---- FIFO ----
            $layers = ($purchases[$kstr] ?? collect())->values(); // list of {quantity, cost}
            if ($layers->isEmpty()) {
                // no purchases -> fallback to average
                $totalFifo += $avg * $qtySold;
                continue;
            }

            // burn layers for sales before start
            $burn = (float) ($salesBefore[$kstr]->qty ?? 0);
            $i = 0;
            while ($burn > 0 && $i < $layers->count()) {
                $q = (float) $layers[$i]->quantity;
                if ($q <= 0) { $i++; continue; }
                $consume = min($q, $burn);
                $layers[$i]->quantity = $q - $consume;
                $burn -= $consume;
                if ($layers[$i]->quantity <= 0) $i++;
            }

            // now cost the period sales
            $remain = $qtySold;
            while ($remain > 0) {
                if ($i >= $layers->count()) {
                    // ran out of layers -> fallback to avg for the rest
                    $totalFifo += $avg * $remain;
                    $remain = 0;
                    break;
                }
                $q = max(0.0, (float) $layers[$i]->quantity);
                $c = (float) $layers[$i]->cost;
                if ($q <= 0) { $i++; continue; }

                $take = min($q, $remain);
                $totalFifo += $take * $c;
                $layers[$i]->quantity = $q - $take;
                $remain -= $take;
                if ($layers[$i]->quantity <= 0) $i++;
            }
        }

        return ['fifo'=>$totalFifo, 'avg'=>$totalAvg];
    }

    /**
     * Set-based average cost by (product,variant) at end date.
     * AVG = (Σ purchases qty*cost + Σ adjustments(±qty)*product.cost) / (Σ purchases qty + Σ adjustments qty)
     */
    protected function averageCostBulk(array $productIds, array $variantIds, string $end, int $warehouseId, array $warehouseIds): array
    {
        $key = fn($pid,$vid)=> $pid.':'.($vid ?? 'null');

        // Purchases up to end
        $pIn = PurchaseDetail::join('purchases as p','p.id','=','purchase_details.purchase_id')
            ->where('p.statut','received')
            ->when($warehouseId, fn($q)=>$q->where('p.warehouse_id',$warehouseId),
                              fn($q)=>$q->whereIn('p.warehouse_id',$warehouseIds))
            ->whereIn('purchase_details.product_id', $productIds)
            ->where('p.date','<=',$end)
            ->select(
                'purchase_details.product_id',
                'purchase_details.product_variant_id',
                DB::raw('SUM(purchase_details.quantity) as qty'),
                DB::raw('SUM(purchase_details.quantity * purchase_details.cost) as cost')
            )
            ->groupBy('purchase_details.product_id','purchase_details.product_variant_id')
            ->get()
            ->keyBy(fn($r)=>$key($r->product_id,$r->product_variant_id));

        // Adjustments up to end (valued at product.cost)
        $adj = AdjustmentDetail::join('adjustments as a','a.id','=','adjustment_details.adjustment_id')
            ->when($warehouseId, fn($q)=>$q->where('a.warehouse_id',$warehouseId),
                              fn($q)=>$q->whereIn('a.warehouse_id',$warehouseIds))
            ->whereIn('adjustment_details.product_id', $productIds)
            ->where('a.date','<=',$end)
            ->leftJoin('products as pr','pr.id','=','adjustment_details.product_id')
            ->select(
                'adjustment_details.product_id',
                'adjustment_details.product_variant_id',
                DB::raw("SUM(CASE WHEN adjustment_details.type='add' THEN adjustment_details.quantity ELSE -adjustment_details.quantity END) as qty"),
                DB::raw("SUM(CASE WHEN adjustment_details.type='add' THEN adjustment_details.quantity ELSE -adjustment_details.quantity END) * COALESCE(pr.cost,0) as cost")
            )
            ->groupBy('adjustment_details.product_id','adjustment_details.product_variant_id')
            ->get()
            ->keyBy(fn($r)=>$key($r->product_id,$r->product_variant_id));

        // Build map avg cost per key
        $avg = [];
        // unify keys from purchases/adjustments
        $keys = collect(array_unique(array_merge($pIn->keys()->all(), $adj->keys()->all())));
        foreach ($keys as $kstr) {
            $pq = (float) ($pIn[$kstr]->qty  ?? 0);
            $pc = (float) ($pIn[$kstr]->cost ?? 0);
            $aq = (float) ($adj[$kstr]->qty  ?? 0);
            $ac = (float) ($adj[$kstr]->cost ?? 0);

            $qty = $pq + $aq;
            $cost= $pc + $ac;
            $avg[$kstr] = $qty > 0 ? ($cost / $qty) : 0.0;
        }
        return $avg;
    }



     //-------------------- report_top_products -------------\\

     public function report_top_products(request $request)
     {
 
        $this->authorizeForUser($request->user('api'), 'Top_products', Product::class);

        $Role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($Role->id)->inRole('record_view');
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $products_data = SaleDetail::join('sales', 'sale_details.sale_id', '=', 'sales.id')
        ->join('products', 'sale_details.product_id', '=', 'products.id')
        ->where(function ($query) use ($view_records) {
            if (!$view_records) {
                return $query->where('sales.user_id', '=', Auth::user()->id);
            }
        })
        ->whereBetween('sale_details.date', array($request->from, $request->to))
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('products.name','LIKE', "%{$request->search}%")
                    ->orWhere('products.code', 'LIKE', "%{$request->search}%");
            });
        })
        ->select(
            DB::raw('products.name as name'),
            DB::raw('products.code as code'),
            DB::raw('count(*) as total_sales'),
            DB::raw('sum(total) as total'),
        )
        ->groupBy('products.name');

        $totalRows = $products_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        
        
        $products = $products_data->offset($offSet)
        ->limit($perPage)
        ->orderBy('total_sales', 'desc')
        ->get();


        return response()->json([
            'products' => $products,
            'totalRows' => $totalRows,
        ]);

     }


    //-------------------- report_top_customers -------------\\

    public function report_top_customers(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'Top_customers', Client::class);

        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $customers_count = Sale::where('sales.deleted_at', '=', null)
        ->where(function ($query) use ($view_records) {
            if (!$view_records) {
                return $query->where('sales.user_id', '=', Auth::user()->id);
            }
        })

        ->join('clients', 'sales.client_id', '=', 'clients.id')
        ->select(DB::raw('clients.name'), DB::raw("count(*) as total_sales"))
        ->groupBy('clients.name')->get();

        $totalRows = $customers_count->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }

        $customers_data = Sale::where('sales.deleted_at', '=', null)
        ->where(function ($query) use ($view_records) {
            if (!$view_records) {
                return $query->where('sales.user_id', '=', Auth::user()->id);
            }
        })

        ->join('clients', 'sales.client_id', '=', 'clients.id')
        ->select(
            DB::raw('clients.name as name'), 
            DB::raw('clients.phone as phone'), 
            DB::raw('clients.email as email'), 
            DB::raw("count(*) as total_sales"),
            DB::raw('sum(GrandTotal) as total'),
        )
        ->groupBy('clients.name');

        $customers = $customers_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('total_sales', 'desc')
            ->get();

        return response()->json([
            'customers' => $customers,
            'totalRows' => $totalRows,
        ]);

    }


     //----------------- Users Report -----------------------\\

     public function users_Report(request $request)
     {
 
         $this->authorizeForUser($request->user('api'), 'users_report', User::class);
 
         // How many items do you want to display.
         $perPage = $request->limit;
         $pageStart = \Request::get('page', 1);
         // Start displaying items from this number;
         $offSet = ($pageStart * $perPage) - $perPage;
         $order = $request->SortField;
         $dir = $request->SortType;
         $data = array();
 
         $users = User::where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('username', 'LIKE', "%{$request->search}%");
                });
            });
 
         $totalRows = $users->count();
         if($perPage == "-1"){
             $perPage = $totalRows;
         }
         $users = $users->offset($offSet)
             ->limit($perPage)
             ->orderBy($order, $dir)
             ->get();
 
         foreach ($users as $user) {
            $item['total_sales'] = DB::table('sales')
                 ->where('deleted_at', '=', null)
                 ->where('user_id', $user->id)
                 ->count();

            $item['total_purchases'] = DB::table('purchases')
                 ->where('deleted_at', '=', null)
                 ->where('user_id', $user->id)
                 ->count();

            $item['total_quotations'] = DB::table('quotations')
                 ->where('deleted_at', '=', null)
                 ->where('user_id', $user->id)
                 ->count();

            $item['total_return_sales'] = DB::table('sale_returns')
                 ->where('deleted_at', '=', null)
                 ->where('user_id', $user->id)
                 ->count();

            $item['total_return_purchases'] = DB::table('purchase_returns')
                 ->where('deleted_at', '=', null)
                 ->where('user_id', $user->id)
                 ->count();

            $item['total_transfers'] = DB::table('transfers')
                 ->where('deleted_at', '=', null)
                 ->where('user_id', $user->id)
                 ->count();

            $item['total_adjustments'] = DB::table('adjustments')
                 ->where('deleted_at', '=', null)
                 ->where('user_id', $user->id)
                 ->count();
 
             $item['id'] = $user->id;
             $item['username'] = $user->username;
             $data[] = $item;
         }
 
         return response()->json([
             'report' => $data,
             'totalRows' => $totalRows,
         ]);
 
     }


      //-------------------- Get Sales By user -------------\\

    public function get_sales_by_user(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'users_report', User::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $sales = Sale::where('deleted_at', '=', null)->with('user','client','warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where('user_id', $request->id)
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_statut', 'like', "%{$request->search}%")
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

        $totalRows = $sales->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $sales = $sales->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($sales as $sale) {
            $item['username'] = $sale['user']->username;
            $item['client_name'] = $sale['client']->name;
            $item['warehouse_name'] = $sale['warehouse']->name;
            $item['date'] = $sale->date;
            $item['Ref'] = $sale->Ref;
            $item['sale_id'] = $sale->id;
            $item['statut'] = $sale->statut;
            $item['GrandTotal'] = $sale->GrandTotal;
            $item['paid_amount'] = $sale->paid_amount;
            $item['due'] = $sale->GrandTotal - $sale->paid_amount;
            $item['payment_status'] = $sale->payment_statut;
            $item['shipping_status'] = $sale->shipping_status;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'sales' => $data,
        ]);

    }

     //-------------------- Get Quotations By user -------------\\

     public function get_quotations_by_user(request $request)
     {
 
        $this->authorizeForUser($request->user('api'), 'users_report', User::class);
         // How many items do you want to display.
         $perPage = $request->limit;
         $pageStart = \Request::get('page', 1);
         // Start displaying items from this number;
         $offSet = ($pageStart * $perPage) - $perPage;
 
         $Role = Auth::user()->roles()->first();
         $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');
         $data = array();

         $Quotations = Quotation::with('client', 'warehouse','user')
            ->where('deleted_at', '=', null)
             ->where('user_id', $request->id)
             ->where(function ($query) use ($ShowRecord) {
                 if (!$ShowRecord) {
                     return $query->where('user_id', '=', Auth::user()->id);
                 }
             })
              //Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
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

         $totalRows = $Quotations->count();
         if($perPage == "-1"){
             $perPage = $totalRows;
         }
         $Quotations = $Quotations->offset($offSet)
             ->limit($perPage)
             ->orderBy('id', 'desc')
             ->get();

            foreach ($Quotations as $Quotation) {

                $item['id'] = $Quotation->id;
                $item['date'] = $Quotation->date;
                $item['Ref'] = $Quotation->Ref;
                $item['statut'] = $Quotation->statut;
                $item['username'] = $Quotation['user']->username;
                $item['warehouse_name'] = $Quotation['warehouse']->name;
                $item['client_name'] = $Quotation['client']->name;
                $item['GrandTotal'] = $Quotation->GrandTotal;

                $data[] = $item;
            }
 
         return response()->json([
             'quotations' => $data,
             'totalRows' => $totalRows,
         ]);
     }

      //-------------------- Get Purchases By user -------------\\

    public function get_purchases_by_user(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'users_report', User::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $purchases = Purchase::where('deleted_at', '=', null)
            ->with('user','provider','warehouse')
            ->where('user_id', $request->id)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_statut', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('provider', function ($q) use ($request) {
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

        $totalRows = $purchases->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $purchases = $purchases->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($purchases as $purchase) {
            $item['Ref'] = $purchase->Ref;
            $item['purchase_id'] = $purchase->id;
            $item['username'] = $purchase['user']->username;
            $item['provider_name'] = $purchase['provider']->name;
            $item['warehouse_name'] = $purchase['warehouse']->name;
            $item['statut'] = $purchase->statut;
            $item['GrandTotal'] = $purchase->GrandTotal;
            $item['paid_amount'] = $purchase->paid_amount;
            $item['due'] = $purchase->GrandTotal - $purchase->paid_amount;
            $item['payment_status'] = $purchase->payment_statut;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'purchases' => $data,
        ]);

    }

     //-------------------- Get sale Returns By user -------------\\

     public function get_sales_return_by_user(request $request)
     {
 
        $this->authorizeForUser($request->user('api'), 'users_report', User::class);
         // How many items do you want to display.
         $perPage = $request->limit;
         $pageStart = \Request::get('page', 1);
         // Start displaying items from this number;
         $offSet = ($pageStart * $perPage) - $perPage;
         $data = array();
 
         //  Check If User Has Permission Show All Records
         $Role = Auth::user()->roles()->first();
         $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');
 
         $SaleReturn = SaleReturn::where('deleted_at', '=', null)->with('user','client','warehouse')
             ->where('user_id', $request->id)
             ->where(function ($query) use ($ShowRecord) {
                 if (!$ShowRecord) {
                     return $query->where('user_id', '=', Auth::user()->id);
                 }
             })
             // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_statut', 'like', "$request->search")
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
 
         $totalRows = $SaleReturn->count();
         if($perPage == "-1"){
             $perPage = $totalRows;
         }
         $SaleReturn = $SaleReturn->offset($offSet)
             ->limit($perPage)
             ->orderBy('id', 'desc')
             ->get();
 
         foreach ($SaleReturn as $Sale_Return) {
             $item['Ref'] = $Sale_Return->Ref;
             $item['return_sale_id'] = $Sale_Return->id;
             $item['statut'] = $Sale_Return->statut;
             $item['username'] = $Sale_Return['user']->username;
             $item['client_name'] = $Sale_Return['client']->name;
             $item['warehouse_name'] = $Sale_Return['warehouse']->name;
             $item['GrandTotal'] = $Sale_Return->GrandTotal;
             $item['paid_amount'] = $Sale_Return->paid_amount;
             $item['due'] = $Sale_Return->GrandTotal - $Sale_Return->paid_amount;
             $item['payment_status'] = $Sale_Return->payment_statut;
 
             $data[] = $item;
         }
 
         return response()->json([
             'totalRows' => $totalRows,
             'sales_return' => $data,
         ]);
     }

    //-------------------- Get purchase Returns By user -------------\\

    public function get_purchase_return_by_user(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'users_report', User::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $PurchaseReturn = PurchaseReturn::where('deleted_at', '=', null)
            ->with('user','provider','warehouse')
            ->where('user_id', $request->id)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('payment_statut', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('provider', function ($q) use ($request) {
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

        $totalRows = $PurchaseReturn->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $PurchaseReturn = $PurchaseReturn->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($PurchaseReturn as $Purchase_Return) {
            $item['Ref'] = $Purchase_Return->Ref;
            $item['return_purchase_id'] = $Purchase_Return->id;
            $item['statut'] = $Purchase_Return->statut;
            $item['username'] = $Purchase_Return['user']->username;
            $item['provider_name'] = $Purchase_Return['provider']->name;
            $item['warehouse_name'] = $Purchase_Return['warehouse']->name;
            $item['GrandTotal'] = $Purchase_Return->GrandTotal;
            $item['paid_amount'] = $Purchase_Return->paid_amount;
            $item['due'] = $Purchase_Return->GrandTotal - $Purchase_Return->paid_amount;
            $item['payment_status'] = $Purchase_Return->payment_statut;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'purchases_return' => $data,
        ]);

    }

     //-------------------- Get transfers By user -------------\\

     public function get_transfer_by_user(request $request)
     {
 
         $this->authorizeForUser($request->user('api'), 'users_report', User::class);
 
         // How many items do you want to display.
         $perPage = $request->limit;
         $pageStart = \Request::get('page', 1);
         // Start displaying items from this number;
         $offSet = ($pageStart * $perPage) - $perPage;
         $data = array();
 
         $Role = Auth::user()->roles()->first();
         $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');
 
         $transfers = Transfer::with('from_warehouse', 'to_warehouse')
             ->with('user')
             ->where('user_id', $request->id)
             ->where(function ($query) use ($ShowRecord) {
                 if (!$ShowRecord) {
                     return $query->where('user_id', '=', Auth::user()->id);
                 }
             })
             // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('from_warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('to_warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });
 
         $totalRows = $transfers->count();
         if($perPage == "-1"){
             $perPage = $totalRows;
         }
         $transfers = $transfers->offset($offSet)
             ->limit($perPage)
             ->orderBy('id', 'desc')
             ->get();
 
        foreach ($transfers as $transfer) {
                $item['id'] = $transfer->id;
                $item['date'] = $transfer->date;
                $item['Ref'] = $transfer->Ref;
                $item['username'] = $transfer['user']->username;
                $item['from_warehouse'] = $transfer['from_warehouse']->name;
                $item['to_warehouse'] = $transfer['to_warehouse']->name;
                $item['GrandTotal'] = $transfer->GrandTotal;
                $item['items'] = $transfer->items;
                $item['statut'] = $transfer->statut;

                $data[] = $item;
        }
         return response()->json([
             'totalRows' => $totalRows,
             'transfers' => $data,
         ]);
 
     }

    //-------------------- Get adjustment By user -------------\\

    public function get_adjustment_by_user(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'users_report', User::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $Adjustments = Adjustment::with('warehouse')
            ->with('user')
            ->where('user_id', $request->id)
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Adjustments->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $Adjustments = $Adjustments->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($Adjustments as $Adjustment) {
            $item['id'] = $Adjustment->id;
            $item['username'] = $Adjustment['user']->username;
            $item['date'] = $Adjustment->date;
            $item['Ref'] = $Adjustment->Ref;
            $item['warehouse_name'] = $Adjustment['warehouse']->name;
            $item['items'] = $Adjustment->items;
            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'adjustments' => $data,
        ]);

    }


    //----------------- stock Report -----------------------\\

    public function stock_Report(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $data = array();

        
        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
            $warehouses_id = Warehouse::where('deleted_at', '=', null)->pluck('id')->toArray();
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $products_data = Product::with('unit', 'category', 'brand')
        ->where('deleted_at', '=', null)
        // ->where('type', '!=', 'is_service')
        // Search With Multiple Param
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('products.name', 'LIKE', "%{$request->search}%")
                    ->orWhere('products.code', 'LIKE', "%{$request->search}%")
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('category', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    });
            });
        });

        $totalRows = $products_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $products = $products_data->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($products as $product) {


            if($product->type != 'is_service'){

                $item['id'] = $product->id;
                $item['code'] = $product->code;
                $item['name'] = $product->name;
                $item['category'] = $product['category']->name;

                $current_stock = product_warehouse::where('product_id', $product->id)
                ->where('deleted_at', '=', null)
                ->whereIn('warehouse_id', $warehouses_id)
                ->where(function ($query) use ($request) {
                    return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                            return $query->where('warehouse_id', $request->warehouse_id);
                        });
                    })
                ->sum('qte');

                $item['quantity'] = $current_stock .' '.$product['unit']->ShortName;

                $data[] = $item;

            }else{

                $item['id'] = $product->id;
                $item['code'] = $product->code;
                $item['name'] = $product->name;
                $item['category'] = $product['category']->name;
                $item['quantity'] = 0;

                $data[] = $item;
            }



        }


        return response()->json([
            'report' => $data,
            'totalRows' => $totalRows,
            'warehouses' => $warehouses,
        ]);

    }

    //-------------------- Get Sales By product -------------\\

    public function get_sales_by_product(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $sale_details_data = SaleDetail::with('product','sale','sale.client','sale.warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('sale', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where('product_id', $request->id)
             // Search With Multiple Param
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('sale.client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('sale.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('sale', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $sale_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $sale_details = $sale_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($sale_details as $detail) {

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

            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail->date;
            $item['Ref'] = $detail['sale']->Ref;
            $item['sale_id'] = $detail['sale']->id;
            $item['client_name'] = $detail['sale']['client']->name;
            $item['unit_sale'] = $unit?$unit->ShortName:'';
            $item['warehouse_name'] = $detail['sale']['warehouse']->name;
            $item['quantity'] = $detail->quantity .' '.$item['unit_sale'];
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'sales' => $data,
        ]);

    }

    //-------------------- Get quotations By product -------------\\

    public function get_quotations_by_product(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $quotation_details_data = QuotationDetail::with('product','quotation','quotation.client','quotation.warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('quotation', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where('product_id', $request->id)
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('quotation.client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('quotation.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('quotation', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $quotation_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $quotation_details = $quotation_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($quotation_details as $detail) {

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

            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['quotation']->date;
            $item['Ref'] = $detail['quotation']->Ref;
            $item['quotation_id'] = $detail['quotation']->id;
            $item['client_name'] = $detail['quotation']['client']->name;
            $item['warehouse_name'] = $detail['quotation']['warehouse']->name;
            $item['unit_sale'] = $unit?$unit->ShortName:'';
            $item['quantity'] = $detail->quantity .' '.$item['unit_sale'];
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'quotations' => $data,
        ]);

    }

    //-------------------- Get purchases By product -------------\\

    public function get_purchases_by_product(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $purchase_details_data = PurchaseDetail::with('product','purchase','purchase.provider','purchase.warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('purchase', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where('product_id', $request->id)
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('purchase.provider', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('purchase.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('purchase', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $purchase_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $purchase_details = $purchase_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($purchase_details as $detail) {

            //-------check if detail has purchase_unit_id Or Null
            if($detail->purchase_unit_id !== null){
            $unit = Unit::where('id', $detail->purchase_unit_id)->first();
        }else{
            $product_unit_purchase_id = Product::with('unitPurchase')
            ->where('id', $detail->product_id)
            ->first();
            $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
        }

            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['purchase']->date;
            $item['Ref'] = $detail['purchase']->Ref;
            $item['purchase_id'] = $detail['purchase']->id;
            $item['provider_name'] = $detail['purchase']['provider']->name;
            $item['warehouse_name'] = $detail['purchase']['warehouse']->name;
            $item['quantity'] = $detail->quantity .' '.$unit->ShortName;;
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;
            $item['unit_purchase'] = $unit->ShortName;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'purchases' => $data,
        ]);

    }

    //-------------------- Get purchases return By product -------------\\

    public function get_purchase_return_by_product(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $purchase_return_details_data = PurchaseReturnDetails::with('product','PurchaseReturn','PurchaseReturn.provider','PurchaseReturn.warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('PurchaseReturn', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where('quantity', '>', 0)
            ->where('product_id', $request->id)
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('PurchaseReturn.provider', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('PurchaseReturn.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('PurchaseReturn', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

                $totalRows = $purchase_return_details_data->count();
                if($perPage == "-1"){
                    $perPage = $totalRows;
                }
                $purchase_return_details = $purchase_return_details_data->offset($offSet)
                    ->limit($perPage)
                    ->orderBy('id', 'desc')
                    ->get();

                $data = [];
                foreach ($purchase_return_details as $detail) {

                    //-------check if detail has purchase_unit_id Or Null
                if($detail->purchase_unit_id !== null){
                $unit = Unit::where('id', $detail->purchase_unit_id)->first();
            }else{
                $product_unit_purchase_id = Product::with('unitPurchase')
                ->where('id', $detail->product_id)
                ->first();
                $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
            }

            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['PurchaseReturn']->date;
            $item['Ref'] = $detail['PurchaseReturn']->Ref;
            $item['return_purchase_id'] = $detail['PurchaseReturn']->id;
            $item['provider_name'] = $detail['PurchaseReturn']['provider']->name;
            $item['warehouse_name'] = $detail['PurchaseReturn']['warehouse']->name;
            $item['quantity'] = $detail->quantity .' '.$unit->ShortName;;
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;
            $item['unit_purchase'] = $unit->ShortName;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'purchases_return' => $data,
        ]);

    }

    //-------------------- Get sales return By product -------------\\

    public function get_sales_return_by_product(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $Sale_Return_details_data = SaleReturnDetails::with('product','SaleReturn','SaleReturn.client','SaleReturn.warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('SaleReturn', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where('quantity', '>', 0)
            ->where('product_id', $request->id)
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('SaleReturn.client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('SaleReturn.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('SaleReturn', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Sale_Return_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $Sale_Return_details = $Sale_Return_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($Sale_Return_details as $detail) {

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

            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['SaleReturn']->date;
            $item['Ref'] = $detail['SaleReturn']->Ref;
            $item['return_sale_id'] = $detail['SaleReturn']->id;
            $item['client_name'] = $detail['SaleReturn']['client']->name;
            $item['warehouse_name'] = $detail['SaleReturn']['warehouse']->name;
            $item['unit_sale'] = $unit?$unit->ShortName:'';
            $item['quantity'] = $detail->quantity .' '.$item['unit_sale'];
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'sales_return' => $data,
        ]);

    }

    //-------------------- Get transfers By product -------------\\

    public function get_transfer_by_product(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $transfer_details_data = TransferDetail::with('product','transfer','transfer.from_warehouse','transfer.to_warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('transfer', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where('product_id', $request->id)
            // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('transfer.from_warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('transfer.to_warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('transfer', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $transfer_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $transfer_details = $transfer_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($transfer_details as $detail) {

            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['transfer']->date;
            $item['Ref'] = $detail['transfer']->Ref;
            $item['from_warehouse'] = $detail['transfer']['from_warehouse']->name;
            $item['to_warehouse'] = $detail['transfer']['to_warehouse']->name;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'transfers' => $data,
        ]);

    }

    //-------------------- Get adjustments By product -------------\\

    public function get_adjustment_by_product(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'stock_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $adjustment_details_data = AdjustmentDetail::with('product','adjustment','adjustment.warehouse')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('adjustment', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->where('product_id', $request->id)
                // Search With Multiple Param
                ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('adjustment.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('adjustment', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $adjustment_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $adjustment_details = $adjustment_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($adjustment_details as $detail) {

            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail['adjustment']->date;
            $item['Ref'] = $detail['adjustment']->Ref;
            $item['warehouse_name'] = $detail['adjustment']['warehouse']->name;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }
        return response()->json([
            'totalRows' => $totalRows,
            'adjustments' => $data,
        ]);

    }

    //------------- download_report_client_pdf -----------\\

    public function download_report_client_pdf(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'Reports_customers', Client::class);

        $helpers = new helpers();
        $client = Client::where('deleted_at', '=', null)->findOrFail($id);

        $Sales = Sale::where('deleted_at', '=', null)
        ->where([
            ['payment_statut', '!=', 'paid'],
            ['client_id', $id]
        ])->get();

        $sales_details = [];

        foreach ($Sales as $Sale) {
            
            $item_sale['date'] = $Sale['date'];
            $item_sale['Ref'] = $Sale['Ref'];
            $item_sale['GrandTotal'] = number_format($Sale['GrandTotal'], 2, '.', '');
            $item_sale['paid_amount'] = number_format($Sale['paid_amount'], 2, '.', '');
            $item_sale['due'] = number_format($item_sale['GrandTotal'] - $item_sale['paid_amount'], 2, '.', '');
            $item_sale['payment_status'] = $Sale['payment_statut'];
            
            $sales_details[] = $item_sale;
        }

        $data['client_name'] = $client->name;
        $data['phone'] = $client->phone;

        $data['total_sales'] = DB::table('sales')->where('deleted_at', '=', null)->where('client_id', $id)->count();

        $data['total_amount'] = DB::table('sales')
                ->where('deleted_at', '=', null)
                ->where('statut', 'completed')
                ->where('client_id', $client->id)
                ->sum('GrandTotal');

        $data['total_paid'] = DB::table('sales')
            ->where('deleted_at', '=', null)
            ->where('statut', 'completed')
            ->where('client_id', $client->id)
            ->sum('paid_amount');

        $data['due'] = $data['total_amount'] - $data['total_paid'];

        $data['total_amount_return'] = DB::table('sale_returns')
            ->where('deleted_at', '=', null)
            ->where('client_id', $client->id)
            ->sum('GrandTotal');

        $data['total_paid_return'] = DB::table('sale_returns')
            ->where('deleted_at', '=', null)
            ->where('client_id', $client->id)
            ->sum('paid_amount');

        $data['return_Due'] = $data['total_amount_return'] - $data['total_paid_return'];
     
        $symbol = $helpers->Get_Currency();
        $settings = Setting::where('deleted_at', '=', null)->first();

        $pdf = \PDF::loadView('pdf.report_client_pdf', [
            'symbol' => $symbol,
            'client' => $data,
            'sales' => $sales_details,
            'setting' => $settings,
        ]);

        return $pdf->download('report_client.pdf');

    }

     //------------- download_report_provider_pdf -----------\\

     public function download_report_provider_pdf(Request $request, $id)
     {
 
        $this->authorizeForUser($request->user('api'), 'Reports_suppliers', Provider::class);
 
         $helpers = new helpers();
         $provider = Provider::where('deleted_at', '=', null)->findOrFail($id);
 
         $purchases = Purchase::where('deleted_at', '=', null)
         ->where('payment_statut', '!=', 'paid')
         ->where('provider_id', $id)
         ->get();

         $purchases_details = [];
 
         foreach ($purchases as $purchase) {
             
             $item_purchase['date'] = $purchase['date'];
             $item_purchase['Ref'] = $purchase['Ref'];
             $item_purchase['GrandTotal'] = number_format($purchase['GrandTotal'], 2, '.', '');
             $item_purchase['paid_amount'] = number_format($purchase['paid_amount'], 2, '.', '');
             $item_purchase['due'] = number_format($item_purchase['GrandTotal'] - $item_purchase['paid_amount'], 2, '.', '');
             $item_purchase['payment_status'] = $purchase['payment_statut'];
             
             $purchases_details[] = $item_purchase;
         }
 
         $data['provider_name'] = $provider->name;
         $data['phone'] = $provider->phone;
 
        $data['total_purchase'] = DB::table('purchases')->where('deleted_at', '=', null)->where('provider_id', $id)->count();

        $data['total_amount'] = DB::table('purchases')
            ->where('deleted_at', '=', null)
            ->where('statut', 'received')
            ->where('provider_id', $id)
            ->sum('GrandTotal');

        $data['total_paid'] = DB::table('purchases')
            ->where('deleted_at', '=', null)
            ->where('statut', 'received')
            ->where('provider_id', $id)
            ->sum('paid_amount');

        $data['due'] = $data['total_amount'] - $data['total_paid'];

        $data['total_amount_return'] = DB::table('purchase_returns')
            ->where('deleted_at', '=', null)
            ->where('provider_id', $id)
            ->sum('GrandTotal');

        $data['total_paid_return'] = DB::table('purchase_returns')
            ->where('deleted_at', '=', null)
            ->where('provider_id', $id)
            ->sum('paid_amount');

        $data['return_Due'] = $data['total_amount_return'] - $data['total_paid_return'];
      
         $symbol = $helpers->Get_Currency();
         $settings = Setting::where('deleted_at', '=', null)->first();
 
         $pdf = \PDF::loadView('pdf.report_provider_pdf', [
             'symbol' => $symbol,
             'provider' => $data,
             'purchases' => $purchases_details,
             'setting' => $settings,
         ]);
 
         return $pdf->download('report_provider.pdf');
 
     }


    //-------------------- product_report -------------\\

    public function product_report(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'product_report', Product::class);

        $Role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($Role->id)->inRole('record_view');
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
            $array_warehouses_id = Warehouse::where('deleted_at', '=', null)->pluck('id')->toArray();
        }else{
            $array_warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $array_warehouses_id)->get(['id', 'name']);
        }

    
        $products_data = Product::where('deleted_at', '=', null)->select('id', 'name','code', 'is_variant','unit_id','type')
    
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('name','LIKE', "%{$request->search}%")
                    ->orWhere('code', 'LIKE', "%{$request->search}%");
                });
        });
        
        $totalRows = $products_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
    
                
        $products = $products_data->offset($offSet)
        ->limit($perPage)
        ->get();


        $product_details = [];
        $total_sales = 0;
        foreach ($products as $product) {

            if($product->type != 'is_service'){
                $nestedData['id'] = $product->id;
                $nestedData['name'] = $product->name;
                $nestedData['code'] = $product->code;

                $nestedData['sold_amount'] = SaleDetail::with('sale')->where('product_id', $product->id)
                ->where(function ($query) use ($view_records) {
                    if (!$view_records) {
                        return $query->whereHas('sale', function ($q) use ($request) {
                            $q->where('user_id', '=', Auth::user()->id);
                        });

                    }
                })
                ->where(function ($query) use ($request, $array_warehouses_id) {
                    if ($request->warehouse_id) {
                        return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                            $q->where('warehouse_id', $request->warehouse_id);
                        });
                    }else{
                        return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                            $q->whereIn('warehouse_id', $array_warehouses_id);
                        });

                    }
                })
                ->whereBetween('date', array($request->from, $request->to))
                ->sum('total');

                $lims_product_sale_data = SaleDetail::select('sale_unit_id', 'quantity')->with('sale')->where('product_id', $product->id)
                    ->where(function ($query) use ($view_records) {
                        if (!$view_records) {
                            return $query->whereHas('sale', function ($q) use ($request) {
                                $q->where('user_id', '=', Auth::user()->id);
                            });

                        }
                    })
                    ->where(function ($query) use ($request, $array_warehouses_id) {
                        if ($request->warehouse_id) {
                            return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                                $q->where('warehouse_id', $request->warehouse_id);
                            });
                        }else{
                            return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                                $q->whereIn('warehouse_id', $array_warehouses_id);
                            });

                        }
                    })
                ->whereBetween('date', array($request->from, $request->to))
                ->get();

                $sold_qty = 0;
                if(count($lims_product_sale_data)) {
                    foreach ($lims_product_sale_data as $product_sale) {
                        $unit =  Unit::find($product_sale->sale_unit_id);

                        if($unit->operator == '*'){
                            $sold_qty += $product_sale->quantity * $unit->operator_value;
                        }
                        elseif($unit->operator == '/'){
                            $sold_qty += $product_sale->quantity / $unit->operator_value;
                        }
                    
                    }
                }
            
                $unit_shortname = Unit::where('id', $product->unit_id)->first();
                
                $nestedData['sold_qty'] = $sold_qty .' '. $unit_shortname->ShortName;

                $product_details[] = $nestedData;

            }else{

                $nestedData['id'] = $product->id;
                $nestedData['name'] = $product->name;
                $nestedData['code'] = $product->code;

                $nestedData['sold_amount'] = SaleDetail::with('sale')->where('product_id', $product->id)
                ->where(function ($query) use ($view_records) {
                    if (!$view_records) {
                        return $query->whereHas('sale', function ($q) use ($request) {
                            $q->where('user_id', '=', Auth::user()->id);
                        });

                    }
                })
                ->where(function ($query) use ($request, $array_warehouses_id) {
                    if ($request->warehouse_id) {
                        return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                            $q->where('warehouse_id', $request->warehouse_id);
                        });
                    }else{
                        return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                            $q->whereIn('warehouse_id', $array_warehouses_id);
                        });

                    }
                })
                ->whereBetween('date', array($request->from, $request->to))
                ->sum('total');

                $sold_qty = SaleDetail::select('sale_unit_id', 'quantity')->with('sale')->where('product_id', $product->id)
                ->where(function ($query) use ($view_records) {
                    if (!$view_records) {
                        return $query->whereHas('sale', function ($q) use ($request) {
                            $q->where('user_id', '=', Auth::user()->id);
                        });

                    }
                })
                ->where(function ($query) use ($request, $array_warehouses_id) {
                    if ($request->warehouse_id) {
                        return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                            $q->where('warehouse_id', $request->warehouse_id);
                        });
                    }else{
                        return $query->whereHas('sale', function ($q) use ($request, $array_warehouses_id) {
                            $q->whereIn('warehouse_id', $array_warehouses_id);
                        });

                    }
                })
                ->whereBetween('date', array($request->from, $request->to))
                ->sum('quantity');

                $nestedData['sold_qty'] = $sold_qty;

                $product_details[] = $nestedData;
            }
        }

            

    

        return response()->json([
            'products' => $product_details,
            'totalRows' => $totalRows,
            'warehouses' => $warehouses,
        ]);
    
    }


    //-------------------- sale product details -------------\\

    public function sale_products_details(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'product_report', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $sale_details_data = SaleDetail::with('product','sale','sale.client','sale.warehouse','sale.user')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->whereHas('sale', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
            ->whereBetween('date', array($request->from, $request->to))
            ->where('product_id', $request->id)

             //Filters
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('Ref'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('sale', function ($q) use ($request) {
                            $q->where('Ref', 'LIKE', "{$request->Ref}");
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('client_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('sale.client', function ($q) use ($request) {
                            $q->where('client_id', $request->client_id);
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('sale.warehouse', function ($q) use ($request) {
                            $q->where('warehouse_id', $request->warehouse_id);
                        });
                    });
                });
            })

            ->where(function ($query) use ($request) {
                return $query->when($request->filled('user_id'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('sale.user', function ($q) use ($request) {
                            $q->where('user_id', $request->user_id);
                        });
                    });
                });
            })

            //search
             ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                            return $query->whereHas('sale.client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('sale.warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('sale', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('product', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $sale_details_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $sale_details = $sale_details_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($sale_details as $detail) {

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


            if($detail->product_variant_id){
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                ->where('id', $detail->product_variant_id)->first();

                $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];

            }else{
                $product_name = $detail['product']['name'];
            }

            $item['date'] = $detail->date;
            $item['Ref'] = $detail['sale']->Ref;
            $item['created_by'] = $detail['sale']['user']->username;
            $item['sale_id'] = $detail['sale']->id;
            $item['client_name'] = $detail['sale']['client']->name;
            $item['warehouse_name'] = $detail['sale']['warehouse']->name;
            $item['unit_sale'] = $unit?$unit->ShortName:'';
            $item['quantity'] = $detail->quantity .' '.$item['unit_sale'];
            $item['total'] = $detail->total;
            $item['product_name'] = $product_name;

            $data[] = $item;
        }

        $customers = client::where('deleted_at', '=', null)->get(['id', 'name']);
        $users = User::get(['id', 'username']);

        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json([
            'totalRows' => $totalRows,
            'sales' => $data,
            'customers' => $customers,
            'warehouses' => $warehouses,
            'users' => $users,
        ]);

    }


    //-------------------- product_sales_report  -------------\\

    public function product_sales_report(request $request)
    {
 
         $this->authorizeForUser($request->user('api'), 'product_sales_report', Sale::class);
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
             0 => '=',
             1 => '=',
         );
         $columns = array(
             0 => 'client_id',
             1 => 'warehouse_id',
         );
         $data = array();

         $sale_details_data = SaleDetail::with('product','sale','sale.client','sale.warehouse')
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->whereHas('sale', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })
         ->whereBetween('date', array($request->from, $request->to));

         // Filter
         $sale_details_Filtred = $sale_details_data->where(function ($query) use ($request) {
             return $query->when($request->filled('client_id'), function ($query) use ($request) {
                 return $query->whereHas('sale.client', function ($q) use ($request) {
                     $q->where('client_id', '=', $request->client_id);
                 });
             });
         })
        
         ->where(function ($query) use ($request) {
             return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                 return $query->whereHas('sale.warehouse', function ($q) use ($request) {
                     $q->where('warehouse_id', '=', $request->warehouse_id);
                 });
             });
         })

        // Search With Multiple Param
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where(function ($query) use ($request) {
                        return $query->whereHas('sale.client', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('sale.warehouse', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('sale', function ($q) use ($request) {
                            $q->where('Ref', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('product', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    });
            });
        });



         $totalRows = $sale_details_Filtred->count();
         if($perPage == "-1"){
             $perPage = $totalRows;
         }

         $sale_details = $sale_details_Filtred
         ->offset($offSet)
         ->limit($perPage)
         ->orderBy($order, $dir)
         ->get();

         foreach ($sale_details as $detail) {

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
 
 
             if($detail->product_variant_id){
                 $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                 ->where('id', $detail->product_variant_id)->first();
 
                 $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];
 
             }else{
                 $product_name = $detail['product']['name'];
             }
 
             $item['date'] = $detail->date;
             $item['Ref'] = $detail['sale']->Ref;
             $item['client_name'] = $detail['sale']['client']->name;
             $item['warehouse_name'] = $detail['sale']['warehouse']->name;
             $item['quantity'] = $detail->quantity;
             $item['total'] = $detail->total;
             $item['product_name'] = $product_name;
             $item['unit_sale'] = $unit?$unit->ShortName:'';
 
             $data[] = $item;
         }


        //get warehouses assigned to user
       $user_auth = auth()->user();
       if($user_auth->is_all_warehouses){
           $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
       }else{
           $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
           $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
       }

       $customers = client::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'totalRows' => $totalRows,
            'sales' => $data,
            'customers' => $customers,
            'warehouses' => $warehouses,
        ]);

    }


    //-------------------- product_purchases_report  -------------\\

    public function product_purchases_report(request $request)
    {
 
         $this->authorizeForUser($request->user('api'), 'product_purchases_report', Purchase::class);
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
             0 => '=',
             1 => '=',
         );
         $columns = array(
             0 => 'provider_id',
             1 => 'warehouse_id',
         );
         $data = array();

         $purchase_details_data = PurchaseDetail::with('product','purchase','purchase.provider','purchase.warehouse')
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->whereHas('purchase', function ($q) use ($request) {
                        $q->where('user_id', '=', Auth::user()->id);
                    });
                }
            })

            ->where(function ($query) use ($request) {
                return $query->whereHas('purchase', function ($q) use ($request) {
                    $q->whereBetween('date', array($request->from, $request->to));
                });
            });

        // Filter
        $purchase_details_Filtred = $purchase_details_data->where(function ($query) use ($request) {
            return $query->when($request->filled('provider_id'), function ($query) use ($request) {
                return $query->whereHas('purchase.provider', function ($q) use ($request) {
                    $q->where('provider_id', '=', $request->provider_id);
                });
            });
        })
        
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('warehouse_id'), function ($query) use ($request) {
                return $query->whereHas('purchase.warehouse', function ($q) use ($request) {
                    $q->where('warehouse_id', '=', $request->warehouse_id);
                });
            });
        })

        // Search With Multiple Param
            ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where(function ($query) use ($request) {
                        return $query->whereHas('purchase.provider', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('purchase', function ($q) use ($request) {
                            $q->where('Ref', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('product', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('purchase.warehouse', function ($q) use ($request) {
                            $q->where('name', 'LIKE', "%{$request->search}%");
                        });
                    });
            });
        });



         $totalRows = $purchase_details_Filtred->count();
         if($perPage == "-1"){
             $perPage = $totalRows;
         }

         $purchase_details = $purchase_details_Filtred
         ->offset($offSet)
         ->limit($perPage)
         ->orderBy($order, $dir)
         ->get();

         foreach ($purchase_details as $detail) {

            //-------check if detail has purchase_unit_id Or Null
            if($detail->purchase_unit_id !== null){
               $unit = Unit::where('id', $detail->purchase_unit_id)->first();
           }else{
               $product_unit_purchase_id = Product::with('unitPurchase')
               ->where('id', $detail->product_id)
               ->first();
               $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
           }
  
              if($detail->product_variant_id){
                  $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                  ->where('id', $detail->product_variant_id)->first();
  
                  $product_name = '['.$productsVariants->name . ']' . $detail['product']['name'];
  
              }else{
                  $product_name = $detail['product']['name'];
              }
  
              $item['date'] = $detail['purchase']->date;
              $item['Ref'] = $detail['purchase']->Ref;
              $item['provider_name'] = $detail['purchase']['provider']->name;
              $item['warehouse_name'] = $detail['purchase']['warehouse']->name;
              $item['quantity'] = $detail->quantity;
              $item['total'] = $detail->total;
              $item['product_name'] = $product_name;
              $item['unit_purchase'] = $unit->ShortName;

              $data[] = $item;
          }

        //get warehouses assigned to user
       $user_auth = auth()->user();
       if($user_auth->is_all_warehouses){
           $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
       }else{
           $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
           $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
       }

       $suppliers = Provider::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'totalRows' => $totalRows,
            'purchases' => $data,
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
        ]);

    }



    //----------------- inventory_valuation_summary -----------------------\\

    public function inventory_valuation_summary(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'inventory_valuation', Product::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $data = array();

        $helpers = new helpers();
        $currency_code = $helpers->Get_Currency_Code();
        
        //get warehouses assigned to user
        $user_auth = auth()->user();
        $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        $array_warehouses_id = Warehouse::where('deleted_at', '=', null)->pluck('id')->toArray();

        if(empty($request->warehouse_id) || $request->warehouse_id === 0){
            $warehouse_id = 0;
        }else{
            $warehouse_id = $request->warehouse_id;
        }

        $products_data = Product::with('unit')->where('deleted_at', '=', null)

        // Search With Multiple Param
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('products.name', 'LIKE', "%{$request->search}%")
                    ->orWhere('products.code', 'LIKE', "%{$request->search}%");
            });
        });

        $totalRows = $products_data->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $products = $products_data->offset($offSet)
            ->limit($perPage)
            ->orderBy('id', 'desc')
            ->get();

            
        foreach ($products as $product) {
            $inventory_value = 0;
            $stock = 0;

            $item['id']               = $product->id;
            $item['code']             = $product->code;
            $item['name']             = $product->name;
            $item['unit_name']        = $product['unit']?$product['unit']->ShortName:'';


        if($product->type == 'is_variant'){

            $product_variant_data = ProductVariant::where('product_id', $product->id)
            ->where('deleted_at', '=', null)
            ->get();

            $item['variant_name'] = '';
            $item['stock_hand'] = '';
            $item['inventory_value'] = '';

            foreach ($product_variant_data as $product_variant) {
                $item['variant_name']  .= $product_variant->name.' ('.$item['unit_name'].' )';
                $item['variant_name']  .= '<br>';


                $current_stock = product_warehouse::where('product_id', $product->id)
                ->where('product_variant_id', $product_variant->id)
                ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                    if ($warehouse_id !== 0) {
                        return  $query->where('warehouse_id', $warehouse_id);
                    }else{
                        return  $query->whereIn('warehouse_id', $array_warehouses_id);
        
                    }
                })
                ->where('deleted_at', '=', null)
                ->sum('qte');
            

                $item['stock_hand'] .= number_format($current_stock, 2, '.', ',');
                $item['stock_hand']  .= '<br>';

                $average_cost = $this->get_average_cost_by_product($product->id, $product_variant->id, $warehouse_id);
                
                $item['inventory_value'] .= $current_stock * $average_cost;
                $item['inventory_value']  .= '<br>';

            }
        
        }else{

            $item['variant_name'] = '---';

            $current_stock = product_warehouse::where('product_id', $product->id)
            ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                if ($warehouse_id !== 0) {
                    return  $query->where('warehouse_id', $warehouse_id);
                }else{
                    return  $query->whereIn('warehouse_id', $array_warehouses_id);
    
                }
            })
            ->where('deleted_at', '=', null)
            ->sum('qte');


            //calcule average Cost
            $average_cost = $this->get_average_cost_by_product($product->id, null, $warehouse_id);

            $inventory_value += $current_stock * $average_cost;

            $item['stock_hand'] = $product->type != 'is_service'?number_format($current_stock, 2, '.', ',') :'0.0';
            $item['inventory_value'] =  $product->type !='is_service'?$inventory_value:'0.0';
            $item['inventory_value']  .= '<br>';

        }
            
            $data[] = $item;

        }
    

        return response()->json([
            'reports'   => $data,
            'totalRows'  => $totalRows,
            'warehouses' => $warehouses,
        ]);

    }


              // Calculate the average cost of a product.
     public function get_average_cost_by_product($product_id ,$product_variant_id , $warehouse_id)
     {        
            // Get the cost of the product
            if($product_variant_id){
                $product = ProductVariant::where('product_id', $product_id)->find($product_variant_id);
                $product_cost = $product->cost;
            }else{
                $product = Product::find($product_id);
                $product_cost = $product->cost;
            }

            $array_warehouses_id = Warehouse::where('deleted_at', '=', null)->pluck('id')->toArray();

            $purchases = PurchaseDetail::where('product_id', $product_id)
            ->where('product_variant_id', $product_variant_id)
            ->join('purchases', 'purchases.id', '=', 'purchase_details.purchase_id')
            ->where('purchases.statut' , 'received')
            ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                if ($warehouse_id !== 0) {
                    return  $query->where('purchases.warehouse_id', $warehouse_id);
                }else{
                    return  $query->whereIn('purchases.warehouse_id', $array_warehouses_id);
    
                }
            })

            ->select('purchase_details.quantity as quantity',
                    'purchase_details.cost as cost',
                    'purchase_details.purchase_unit_id as purchase_unit_id')
            ->get();
 
            $purchase_cost = 0;
            $purchase_quantity = 0;
            foreach ($purchases as $purchase) {
               
                $unit = Unit::where('id', $purchase->purchase_unit_id)->first();
            
                if ($unit) {
                    if ($unit->operator == '/') {
                        $purchase_quantity += $purchase->quantity / $unit->operator_value;
                        $purchase_cost += ($purchase->quantity / $unit->operator_value) * ($purchase->cost / $unit->operator_value);
                    } else {
                        $purchase_quantity += $purchase->quantity * $unit->operator_value;
                        $purchase_cost += ($purchase->quantity * $unit->operator_value) * ($purchase->cost * $unit->operator_value);

                    }
                }else{
                    $purchase_quantity += $purchase->quantity;
                    $purchase_cost += $purchase->quantity * $purchase->cost;
                }

            }
 
            // Get the total cost and quantity for all adjustments of the product
            $adjustments = AdjustmentDetail::with('adjustment')
            ->where(function ($query) use ($warehouse_id, $array_warehouses_id) {
                if ($warehouse_id !== 0) {
                    return $query->whereHas('adjustment', function ($q) use ($array_warehouses_id, $warehouse_id) {
                        $q->where('warehouse_id', $warehouse_id);
                    });
                }else{
                    return $query->whereHas('adjustment', function ($q) use ($array_warehouses_id, $warehouse_id) {
                        $q->whereIn('warehouse_id', $array_warehouses_id);
                    });
    
                }
            })
            ->where('product_id', $product_id)
            ->where('product_variant_id', $product_variant_id)
            ->get();
 
            $adjustment_cost = 0;
            $adjustment_quantity = 0;
            foreach ($adjustments as $adjustment) {
                if($adjustment->type == 'add'){
                    $adjustment_quantity += $adjustment->quantity;
                }else{
                    $adjustment_quantity -= $adjustment->quantity;
                }
            }
    
            // Calculate the average cost of purchase

            if($purchase_quantity === 0 || $purchase_quantity == 0 || $purchase_quantity == '0'){
                $average_cost_purchase = $product_cost;
            }else{
                $average_cost_purchase = $purchase_cost / $purchase_quantity;
            }

             // Calculate adjustment_cost multiply by the average cost of purchase
            if($adjustment_quantity === 0 || $adjustment_quantity == 0 || $adjustment_quantity == '0'){
                $adjustment_cost = 0;
            }else{
                $adjustment_cost = $adjustment_quantity * $average_cost_purchase;
            }

            // Calculate the total  average cost
            $total_cost = $purchase_cost + $adjustment_cost;
            $total_quantity = $purchase_quantity + $adjustment_quantity;

            
            if($total_quantity === 0 || $total_quantity == 0 || $total_quantity == '0'){
                $average_cost = $product_cost;
            }else{
                $average_cost = $total_cost / $total_quantity;
            }

      
        return $average_cost;
     }


     //----------------- expenses_report -----------------------\\

    public function expenses_report(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'expenses_report', Expense::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $data = array();

        $helpers = new helpers();
        
        //get warehouses assigned to user
        $user_auth = auth()->user();
        $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        $array_warehouses_id = Warehouse::where('deleted_at', '=', null)->pluck('id')->toArray();

        if(empty($request->warehouse_id) || $request->warehouse_id === 0){
            $warehouse_id = 0;
        }else{
            $warehouse_id = $request->warehouse_id;
        }

        $expenses_data = Expense::join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
        ->where('expenses.deleted_at', '=', null)
        ->where(function ($query) use ($request, $warehouse_id, $array_warehouses_id) {
            if ($warehouse_id !== 0) {
                return $query->where('expenses.warehouse_id', $warehouse_id);
            }else{
                return $query->whereIn('expenses.warehouse_id', $array_warehouses_id);

            }
        })
        ->whereBetween('expenses.date', array($request->from, $request->to))

        // Search With Multiple Param
        ->where(function ($query) use ($request) {
            return $query->when($request->filled('search'), function ($query) use ($request) {
                return $query->where(function ($query) use ($request) {
                    return $query->whereHas('expense_category', function ($q) use ($request) {
                        $q->where('name', 'LIKE', "%{$request->search}%");
                    });
                });
            });
        })

        ->select(
            DB::raw('expense_categories.name as category_name'),
            DB::raw('SUM(expenses.amount) as total_expenses')
        )
    
        ->groupBy('expense_categories.name');

        // Get the total number of grouped rows correctly
        $totalRows = DB::table(DB::raw("({$expenses_data->toSql()}) as sub"))
        ->mergeBindings($expenses_data->getQuery())
        ->count();

        if ($perPage == "-1") {
        $perPage = $totalRows;
        }

        $expenses = $expenses_data
        ->offset($offSet)
        ->limit($perPage)
        ->orderBy('total_expenses', 'desc') // Order by total amount
        ->get();

        foreach ($expenses as $expense) {

            $item['category_name'] = $expense->category_name;
            $item['total_expenses'] = $expense->total_expenses;

            $data[] = $item;
        }

        return response()->json([
            'reports'   => $data,
            'totalRows'  => $totalRows,
            'warehouses' => $warehouses,
        ]);

    }

    
     //----------------- deposits_report -----------------------\\

     public function deposits_report(request $request)
     {
 
         $this->authorizeForUser($request->user('api'), 'deposits_report', Deposit::class);
 
         // How many items do you want to display.
         $perPage = $request->limit;
         $pageStart = \Request::get('page', 1);
         // Start displaying items from this number;
         $offSet = ($pageStart * $perPage) - $perPage;
         $order = $request->SortField;
         $dir = $request->SortType;
         $data = array();
 
         $helpers = new helpers();
         
         $deposits_data = Deposit::join('deposit_categories', 'deposits.deposit_category_id', '=', 'deposit_categories.id')
         ->where('deposits.deleted_at', '=', null)
         ->whereBetween('deposits.date', array($request->from, $request->to))
 
         // Search With Multiple Param
         ->where(function ($query) use ($request) {
             return $query->when($request->filled('search'), function ($query) use ($request) {
                 return $query->where(function ($query) use ($request) {
                     return $query->whereHas('deposit_categories', function ($q) use ($request) {
                         $q->where('title', 'LIKE', "%{$request->search}%");
                     });
                 });
             });
         })
         
         ->select(
             DB::raw('deposits.id as id'),
             DB::raw('deposit_categories.title as category_name'),
             DB::raw('sum(deposits.amount) as total_deposits'),
         )
         ->groupBy('deposit_categories.title');
 
         $totalRows = $deposits_data->count();
         if($perPage == "-1"){
             $perPage = $totalRows;
         }
         $deposits = $deposits_data->offset($offSet)
         ->limit($perPage)
         ->orderBy('id', 'desc')
         ->get();
 
         foreach ($deposits as $deposit) {
 
             $item['id'] = $deposit->id;
             $item['category_name'] = $deposit->category_name;
             $item['total_deposits'] = $deposit->total_deposits;
 
             $data[] = $item;
         }
 
         return response()->json([
             'reports'   => $data,
             'totalRows'  => $totalRows,
         ]);
 
     }


    public function report_transactions(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'report_transactions', PaymentSale::class);
    
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
    
        $helpers = new helpers();
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
    
        $allPayments = collect();
    
        $onlyClientFilter = $request->filled('client_id') && !$request->filled('provider_id');
        $onlyProviderFilter = $request->filled('provider_id') && !$request->filled('client_id');
    
        // Load Payment Sales if provider_id is not exclusively set
        if (!$onlyProviderFilter) {
            $paymentSales = PaymentSale::with(['sale.client', 'account', 'payment_method'])
                ->whereNull('deleted_at')
                ->whereBetween('date', [$request->from, $request->to])
                ->when(!$view_records, fn($q) => $q->where('user_id', Auth::id()))
                ->when($request->filled('client_id'), function ($q) use ($request) {
                    $q->whereHas('sale.client', fn($q2) => $q2->where('id', $request->client_id));
                })
                ->when($request->filled('sale_id'), fn($q) => $q->where('sale_id', $request->sale_id))
                ->when($request->filled('payment_method_id'), fn($q) => $q->where('payment_method_id', $request->payment_method_id))
                ->when($request->filled('search'), function ($q) use ($request) {
                    $q->where(function ($query) use ($request) {
                        $query->where('Ref', 'LIKE', "%{$request->search}%")
                            ->orWhere('date', 'LIKE', "%{$request->search}%")
                            ->orWhereHas('sale', fn($q2) => $q2->where('Ref', 'LIKE', "%{$request->search}%"))
                            ->orWhereHas('payment_method', fn($q2) => $q2->where('name', 'LIKE', "%{$request->search}%"))
                            ->orWhereHas('sale.client', fn($q2) => $q2->where('name', 'LIKE', "%{$request->search}%"));
                    });
                });
    
            $salesMapped = $paymentSales->get()->map(function ($payment) {
                return [
                    'date'           => $payment->date,
                    'Ref'            => $payment->Ref,
                    'Ref_Sale'       => '(sale)'.' '.optional($payment->sale)->Ref,
                    'client_name'    => optional($payment->sale->client)->name,
                    'payment_method' => optional($payment->payment_method)->name,
                    'montant'        => $payment->montant,
                    'account_name'   => optional($payment->account)->account_name ?? '---',
                    'type'           => 'sale',
                    'created_at'     => $payment->created_at,
                ];
            });
    
            $allPayments = $allPayments->merge($salesMapped);
        }
    
        // Load Payment Purchases if client_id is not exclusively set
        if (!$onlyClientFilter) {
            $paymentPurchases = PaymentPurchase::with(['purchase.provider', 'account', 'payment_method'])
                ->whereNull('deleted_at')
                ->whereBetween('date', [$request->from, $request->to])
                ->when(!$view_records, fn($q) => $q->where('user_id', Auth::id()))
                ->when($request->filled('provider_id'), function ($q) use ($request) {
                    $q->whereHas('purchase.provider', fn($q2) => $q2->where('id', $request->provider_id));
                })
                ->when($request->filled('purchase_id'), fn($q) => $q->where('purchase_id', $request->purchase_id))
                ->when($request->filled('payment_method_id'), fn($q) => $q->where('payment_method_id', $request->payment_method_id))
                ->when($request->filled('search'), function ($q) use ($request) {
                    $q->where(function ($query) use ($request) {
                        $query->where('Ref', 'LIKE', "%{$request->search}%")
                            ->orWhere('date', 'LIKE', "%{$request->search}%")
                            ->orWhereHas('purchase', fn($q2) => $q2->where('Ref', 'LIKE', "%{$request->search}%"))
                            ->orWhereHas('payment_method', fn($q2) => $q2->where('name', 'LIKE', "%{$request->search}%"))
                            ->orWhereHas('purchase.provider', fn($q2) => $q2->where('name', 'LIKE', "%{$request->search}%"));
                    });
                });
    
            $purchaseMapped = $paymentPurchases->get()->map(function ($payment) {
                return [
                    'date'           => $payment->date,
                    'Ref'            => $payment->Ref,
                    'Ref_Sale'       => '(purchase)'.' '.optional($payment->purchase)->Ref,
                    'client_name'    => optional($payment->purchase->provider)->name,
                    'payment_method' => optional($payment->payment_method)->name,
                    'montant'        => $payment->montant,
                    'account_name'   => optional($payment->account)->account_name ?? '---',
                    'type'           => 'purchase',
                    'created_at'     => $payment->created_at,
                ];
            });
    
            $allPayments = $allPayments->merge($purchaseMapped);
        }
    
        $totalRows = $allPayments->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }
    
        $sortedPayments = $allPayments->sortByDesc('created_at')
            ->slice($offSet, $perPage)
            ->values();
    
        $clients = Client::whereNull('deleted_at')->get(['id', 'name']);
        $suppliers = Provider::whereNull('deleted_at')->get(['id', 'name']);
        $sales = Sale::whereNull('deleted_at')->get(['id', 'Ref']);
        $purchases = Purchase::whereNull('deleted_at')->get(['id', 'Ref']);
        $payment_methods = PaymentMethod::whereNull('deleted_at')->get(['id', 'name']);

        // Summary Totals By Payment Method
        $paymentSummary = PaymentMethod::whereNull('deleted_at')->withCount([])->get()->map(function ($method) use ($request) {
        $purchaseTotal = PaymentPurchase::whereNull('deleted_at')->where('payment_method_id', $method->id)
            ->whereBetween('date', [$request->from, $request->to])
            ->sum('montant');

        $saleTotal = PaymentSale::whereNull('deleted_at')->where('payment_method_id', $method->id)
            ->whereBetween('date', [$request->from, $request->to])
            ->sum('montant');

        $expenseTotal = Expense::whereNull('deleted_at')->where('payment_method_id', $method->id)
            ->whereBetween('date', [$request->from, $request->to])
            ->sum('amount');

        return [
            'payment_method' => $method->name,
            'purchase_total' => (float) $purchaseTotal,
            'sale_total'     => (float) $saleTotal,
            'expense_total'  => (float) $expenseTotal,
        ];
      });
    
        return response()->json([
            'totalRows' => $totalRows,
            'payments' => $sortedPayments,
            'sales' => $sales,
            'clients' => $clients,
            'payment_methods' => $payment_methods,
            'suppliers' => $suppliers,
            'purchases' => $purchases,
            'payment_summary' => $paymentSummary,
        ]);
    }


    public function sales_by_category_report(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'report_sales_by_category', Sale::class);

        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?? 'category_name';
        $dir = $request->SortType ?? 'asc';

        $data = [];

        $salesQuery = Category::leftJoin('products', 'categories.id', '=', 'products.category_id')
            ->leftJoin('sale_details', 'products.id', '=', 'sale_details.product_id')
            ->leftJoin('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->where(function ($query) use ($request) {
                $query->whereNull('sales.deleted_at')
                    ->whereBetween('sales.date', [$request->from, $request->to])
                    ->orWhereNull('sales.date'); // Ensures categories without sales are included
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('categories.name', 'like', "%{$request->search}%");
            })
            ->select(
                'categories.id as id',
                'categories.name as category_name',
               DB::raw('COALESCE(SUM(sale_details.total), 0) as total_sales')
            )
            ->groupBy('categories.id', 'categories.name');

        $totalRows = $salesQuery->get()->count();

        if ($perPage == "-1") {
            $perPage = $totalRows;
        }

        $sales = $salesQuery
            ->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($sales as $sale) {
            $data[] = [
                'id' => $sale->id,
                'category_name' => $sale->category_name,
                'total_sales' => round($sale->total_sales, 2),
            ];
        }


        $helpers = new helpers();
        $currency = $helpers->Get_Currency_Code();


        return response()->json([
            'reports'   => $data,
            'totalRows' => $totalRows,
            'currency'  => $currency,
        ]);
    }

    public function sales_by_brand_report(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'report_sales_by_brand', Sale::class);

        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?? 'brand_name';
        $dir = $request->SortType ?? 'asc';

        $data = [];

        $salesQuery = Brand::leftJoin('products', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('sale_details', 'products.id', '=', 'sale_details.product_id')
            ->leftJoin('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->where(function ($query) use ($request) {
                $query->whereNull('sales.deleted_at')
                    ->whereBetween('sales.date', [$request->from, $request->to])
                    ->orWhereNull('sales.date'); // Ensures brands without sales are included
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('brands.name', 'like', "%{$request->search}%");
            })
            ->select(
                'brands.id as id',
                'brands.name as brand_name',
                DB::raw('COALESCE(SUM(sale_details.total), 0) as total_sales')
            )
            ->groupBy('brands.id', 'brands.name');

        $totalRows = $salesQuery->get()->count();

        if ($perPage == "-1") {
            $perPage = $totalRows;
        }


        $sales = $salesQuery
            ->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($sales as $sale) {
            $data[] = [
                'id' => $sale->id,
                'brand_name' => $sale->brand_name,
                'total_sales' => round($sale->total_sales, 2),
            ];
        }

        $helpers = new helpers();
        $currency = $helpers->Get_Currency_Code();

        return response()->json([
            'reports'   => $data,
            'totalRows' => $totalRows,
            'currency'  => $currency,
        ]);
    }


    public function seller_report(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'seller_report', User::class);

        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $start_time = $request->start_time; // e.g., '08:00'
        $end_time = $request->end_time;

        // dd($start_time);
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?? 'id';
        $dir = $request->SortType ?? 'desc';

        // Filter users
        $usersQuery = User::when($request->filled('search'), function ($query) use ($request) {
            $query->where('username', 'LIKE', "%{$request->search}%");
        });

        $totalRows = $usersQuery->count();
        if ($perPage == "-1") {
            $perPage = $totalRows;
        }

        $users = $usersQuery
            ->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        // Get all payment method names
        $paymentMethods = DB::table('payment_methods')->whereNull('deleted_at')->pluck('name', 'id');

        $report = [];
        $warehouse_id = $request->warehouse_id; // may be null

        foreach ($users as $user) {
            $salesQuery = DB::table('sales')
            ->whereNull('deleted_at')
            ->whereBetween('date', [$start_date, $end_date])
            ->where('user_id', $user->id)
            ->when($warehouse_id, fn($q) => $q->where('warehouse_id', $warehouse_id))
            ->when($start_time && $end_time, fn($q) => $q->whereBetween('time', [$start_time . ':00', $end_time . ':00']));

            $row = [
                'id' => $user->id,
                'username' => $user->username,
                'total_sales' => number_format($salesQuery->sum('GrandTotal'), 2, '.', ','),
            ];

            foreach ($paymentMethods as $methodName) {
                $row[$methodName] = 0;
            }

            $payments = DB::table('payment_sales')
                ->join('sales', 'payment_sales.sale_id', '=', 'sales.id')
                ->whereNull('payment_sales.deleted_at')
                ->whereNull('sales.deleted_at')
                ->where('payment_sales.user_id', $user->id)
                ->when($start_date && $end_date, function ($q) use ($start_date, $end_date) {
                    $q->whereBetween('payment_sales.date', [$start_date, $end_date]);
                })
                ->when($warehouse_id, function ($q) use ($warehouse_id) {
                    $q->where('sales.warehouse_id', $warehouse_id);
                })
                ->when($start_time && $end_time, fn($q) =>
                    $q->whereBetween('sales.time', [$start_time . ':00', $end_time . ':00'])
                )               
                 ->select('payment_sales.payment_method_id', DB::raw('SUM(payment_sales.montant) as total'))
                ->groupBy('payment_sales.payment_method_id')
                ->get();

            foreach ($payments as $payment) {
                $methodName = $paymentMethods[$payment->payment_method_id] ?? 'Unknown';
                $row[$methodName] = number_format((float) $payment->total, 2, '.', ',');

            }

            $report[] = $row;
        }

        $warehouses = Warehouse::whereNull('deleted_at')->get(['id', 'name']);

        return response()->json([
            'report' => $report,
            'warehouses' => $warehouses,
            'paymentMethods' => array_values($paymentMethods->toArray()),
            'totalRows' => $totalRows,
        ]);
    }



    public function inactiveCustomers(Request $request)
    {
        // Reuse your customers report permission or add a new one
        $this->authorizeForUser($request->user('api'), 'inactive_customers_report', Client::class);
        // OR: $this->authorizeForUser($request->user('api'), 'Reports_inactive_customers', Client::class);

        // ---- Inputs (with sane defaults) ----
        $perPage   = (int) ($request->limit ?? 10);
        $pageStart = (int) ($request->page ?? 1);
        $offSet    = ($pageStart * $perPage) - $perPage;

        $order = $request->SortField ?: 'name';
        $dir   = $request->SortType ?: 'asc';

        // Only allow 30/60/90 (default 30)
        $period = (int) $request->period;
        if (!in_array($period, [30, 60, 90], true)) {
            $period = 30;
        }
        $cutoff = now()->subDays($period); // customers with last sale < cutoff (or null)

        // ---- Subquery: last completed sale per client ----
        // NOTE: you store sale date/time separately; concat to get proper latest datetime.
        $salesAgg = DB::table('sales')
            ->select([
                'client_id',
                DB::raw('MAX(CONCAT(date, " ", IFNULL(time,"00:00:00"))) AS last_sale_dt'),
                DB::raw('COUNT(*) AS sales_count'),
            ])
            ->whereNull('deleted_at')
            ->where('statut', 'completed')
            ->groupBy('client_id');

        // ---- Base query: clients left-joined to last sale ----
        $clientsBase = Client::query()
            ->from('clients')
            ->leftJoinSub($salesAgg, 's', 's.client_id', '=', 'clients.id')
            ->whereNull('clients.deleted_at')
            // global search (name/code/phone)
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($qq) use ($s) {
                    $qq->where('clients.name', 'LIKE', "%{$s}%")
                       ->orWhere('clients.code', 'LIKE', "%{$s}%")
                       ->orWhere('clients.phone', 'LIKE', "%{$s}%");
                });
            })
            // inactivity filter
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('s.last_sale_dt')
                  ->orWhere('s.last_sale_dt', '<', $cutoff->toDateTimeString());
            });

        // ---- Total rows (before pagination) ----
        $totalRows = (clone $clientsBase)->count();

        // ---- Sorting (support computed column) ----
        $sortableComputed = [
            'days_inactive' => 'COALESCE(TIMESTAMPDIFF(DAY, s.last_sale_dt, NOW()), 99999)',
            'last_sale_at'  => 's.last_sale_dt',
        ];

        if (isset($sortableComputed[$order])) {
            $clientsBase->orderByRaw($sortableComputed[$order] . ' ' . ($dir === 'desc' ? 'DESC' : 'ASC'));
        } else {
            // default to clients table columns
            $clientsBase->orderBy($order, $dir);
        }

        // ---- Fetch page ----
        $clients = $clientsBase
            ->select([
                'clients.id',
                'clients.name',
                'clients.code',
                'clients.phone',
                DB::raw('s.last_sale_dt'),
                DB::raw('COALESCE(s.sales_count, 0) AS total_sales'),
            ])
            ->when($perPage !== -1, fn($q) => $q->offset($offSet)->limit($perPage))
            ->get();

        // ---- Shape payload ----
        $data = [];
        foreach ($clients as $c) {
            $lastAt = $c->last_sale_dt ? Carbon::parse($c->last_sale_dt) : null;
            $daysInactive = $lastAt ? $lastAt->diffInDays(now()) : null;

            $data[] = [
                'id'            => $c->id,
                'name'          => $c->name,
                'code'          => $c->code,
                'phone'         => $c->phone,
                'last_sale_at'  => $lastAt ? $lastAt->format('Y-m-d H:i') : null,
                'days_inactive' => $daysInactive ?? 99999, // for sorting; front-end can show "—" if null
                'total_sales'   => (int) $c->total_sales,
                'status'        => $lastAt ? 'Inactive' : 'Never Purchased',
            ];
        }

        return response()->json([
            'report'    => $data,
            'totalRows' => $totalRows,
        ]);
    }




    public function zeroSalesProducts(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'zeroSalesProducts', Product::class);

        $perPage = (int) ($request->input('limit', 10));
        $page    = max(1, (int) $request->input('page', 1));
        $offSet  = ($page - 1) * ($perPage === -1 ? 0 : $perPage);

        $order   = $request->input('SortField', 'last_sale_at');
        $dirRaw  = strtolower($request->input('SortType', 'asc'));
        $dir     = $dirRaw === 'desc' ? 'desc' : 'asc';

        // --- Period handling ---
        $periodRaw = $request->input('period', 30);
        $isAllTime = ($periodRaw === 'all' || $periodRaw === 'ALL' || (int)$periodRaw === -1 || (int)$periodRaw === 0);

        if ($isAllTime) {
            $cutoff = null; // no cutoff => 'never sold ever'
        } else {
            $period = (int) $periodRaw;
            if (!in_array($period, [30, 60, 90], true)) {
                $period = 30;
            }
            $cutoff = now()->subDays($period)->toDateTimeString();
        }

        // Optional filters
        $warehouseId = $request->input('warehouse_id');
        $brandId     = $request->input('brand_id');
        $categoryId  = $request->input('category_id');

        // Sales within window (or lifetime if $cutoff null)
        $periodAgg = DB::table('sale_details as sd')
            ->join('sales as s', 's.id', '=', 'sd.sale_id')
            ->whereNull('s.deleted_at')
            ->where('s.statut', 'completed')
            ->when($warehouseId, fn($q) => $q->where('s.warehouse_id', $warehouseId))
            ->when($cutoff, fn($q) => $q->whereRaw('CONCAT(s.date, " ", IFNULL(s.time, "00:00:00")) >= ?', [$cutoff]))
            ->groupBy('sd.product_id')
            ->select([
                'sd.product_id',
                DB::raw('SUM(sd.quantity) as period_qty'),
            ]);

        // Lifetime last sale timestamp
        $lastAgg = DB::table('sale_details as sd2')
            ->join('sales as s2', 's2.id', '=', 'sd2.sale_id')
            ->whereNull('s2.deleted_at')
            ->where('s2.statut', 'completed')
            ->when($warehouseId, fn($q) => $q->where('s2.warehouse_id', $warehouseId))
            ->groupBy('sd2.product_id')
            ->select([
                'sd2.product_id',
                DB::raw('MAX(CONCAT(s2.date, " ", IFNULL(s2.time, "00:00:00"))) as last_sale_dt'),
            ]);

        $base = Product::query()
            ->from('products')
            ->leftJoinSub($periodAgg, 'p', 'p.product_id', '=', 'products.id')
            ->leftJoinSub($lastAgg,   'l', 'l.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->where('products.type', '!=', 'ingredient') // exclude non-stock
            ->when($brandId, fn($q) => $q->where('products.brand_id', $brandId))
            ->when($categoryId, fn($q) => $q->where('products.category_id', $categoryId))
            // zero sales in the chosen window (or ever if "all")
            ->where(function ($q) {
                $q->whereNull('p.period_qty')->orWhere('p.period_qty', '=', 0);
            })
            // search
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->input('search');
                $q->where(function ($qq) use ($s) {
                    $qq->where('products.name', 'LIKE', "%{$s}%")
                    ->orWhere('products.code', 'LIKE', "%{$s}%");
                });
            });

        // total rows (before pagination)
        $totalRows = (clone $base)->count();

        // Sorting (support computed)
        $sortableComputed = [
            'last_sale_at'         => 'l.last_sale_dt',
            'days_since_last_sale' => 'COALESCE(TIMESTAMPDIFF(DAY, l.last_sale_dt, NOW()), 99999)',
            'period_qty'           => 'COALESCE(p.period_qty, 0)',
        ];

        if (array_key_exists($order, $sortableComputed)) {
            if ($order === 'last_sale_at') {
                // Put "Never sold" first on ASC (NULLs first)
                $base->orderByRaw('l.last_sale_dt IS NULL DESC, l.last_sale_dt ' . ($dir === 'desc' ? 'DESC' : 'ASC'));
            } else {
                $base->orderByRaw($sortableComputed[$order] . ' ' . strtoupper($dir));
            }
        } else {
            // default column sorting
            $base->orderBy($order, $dir);
        }

        $rows = $base
            ->select([
                'products.id',
                'products.code',
                'products.name',
                'products.price',
                DB::raw('COALESCE(p.period_qty, 0) as period_qty'),
                DB::raw('l.last_sale_dt'),
            ])
            ->when($perPage !== -1, fn($q) => $q->offset($offSet)->limit($perPage))
            ->get();

        $data = $rows->map(function ($r) {
            $last = $r->last_sale_dt ? \Carbon\Carbon::parse($r->last_sale_dt) : null;
            return [
                'id'                   => (int) $r->id,
                'code'                 => $r->code,
                'name'                 => $r->name,
                'price'                => (float) $r->price,
                'period_qty'           => (int) $r->period_qty, // 0 or null
                'last_sale_at'         => $last ? $last->format('Y-m-d H:i') : null,
                'days_since_last_sale' => $last ? $last->diffInDays(now()) : null,
                'status'               => $last ? 'InactiveInPeriod' : 'NeverSold',
            ];
        })->all();

        return response()->json([
            'report'    => $data,
            'totalRows' => $totalRows,
        ]);
    }



    public function deadStock(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Dead_Stock_Report', Product::class);

        // ---- Inputs
        $perPage = (int) ($request->limit ?? 10);           // -1 => ALL
        if ($perPage === 0) $perPage = 10;
        $page    = max(1, (int) ($request->page ?? 1));
        $offset  = $perPage === -1 ? 0 : ($page - 1) * $perPage;

        $order = $request->SortField ?: 'days_since_last_movement';
        $dir   = strtolower($request->SortType ?: 'desc') === 'asc' ? 'asc' : 'desc';

        $period = (int) $request->period;
        if (!in_array($period, [30, 60, 90], true)) $period = 60;
        $cutoff = now()->subDays($period)->toDateTimeString();

        $warehouseId = $request->warehouse_id;
        $brandId     = $request->brand_id;
        $categoryId  = $request->category_id;
        $search      = trim((string) $request->search);

        // ---------------- Movement WITHIN the period (product-level) ----------------
        $saleWithin = DB::table('sale_details as d')
            ->join('sales as h', 'h.id', '=', 'd.sale_id')
            ->whereNull('h.deleted_at')
            ->where('h.statut', 'completed')
            ->when($warehouseId, fn($q) => $q->where('h.warehouse_id', $warehouseId))
            ->whereRaw('CONCAT(h.date," ",IFNULL(h.time,"00:00:00")) >= ?', [$cutoff])
            ->groupBy('d.product_id')
            ->select('d.product_id', DB::raw('MAX(CONCAT(h.date," ",IFNULL(h.time,"00:00:00"))) as last_dt'));

        $purchaseWithin = DB::table('purchase_details as d')
            ->join('purchases as h', 'h.id', '=', 'd.purchase_id')
            ->whereNull('h.deleted_at')
            ->when($warehouseId, fn($q) => $q->where('h.warehouse_id', $warehouseId))
            ->whereRaw('COALESCE(CONCAT(h.date," ",IFNULL(h.time,"00:00:00")), h.created_at) >= ?', [$cutoff])
            ->groupBy('d.product_id')
            ->select('d.product_id', DB::raw('MAX(COALESCE(CONCAT(h.date," ",IFNULL(h.time,"00:00:00")), h.created_at)) as last_dt'));

        $transferWithin = DB::table('transfer_details as d')
            ->join('transfers as h', 'h.id', '=', 'd.transfer_id')
            ->whereNull('h.deleted_at')
            ->when($warehouseId, function ($q) use ($warehouseId) {
                $q->where(function ($qq) use ($warehouseId) {
                    $qq->where('h.from_warehouse_id', $warehouseId)
                    ->orWhere('h.to_warehouse_id', $warehouseId);
                });
            })
            ->whereRaw('COALESCE(CONCAT(h.date," ",IFNULL(h.time,"00:00:00")), h.created_at) >= ?', [$cutoff])
            ->groupBy('d.product_id')
            ->select('d.product_id', DB::raw('MAX(COALESCE(CONCAT(h.date," ",IFNULL(h.time,"00:00:00")), h.created_at)) as last_dt'));

        $adjustWithin = DB::table('adjustment_details as d')
            ->join('adjustments as h', 'h.id', '=', 'd.adjustment_id')
            ->whereNull('h.deleted_at')
            ->when($warehouseId, fn($q) => $q->where('h.warehouse_id', $warehouseId))
            ->whereRaw('COALESCE(CONCAT(h.date," ",IFNULL(h.time,"00:00:00")), h.created_at) >= ?', [$cutoff])
            ->groupBy('d.product_id')
            ->select('d.product_id', DB::raw('MAX(COALESCE(CONCAT(h.date," ",IFNULL(h.time,"00:00:00")), h.created_at)) as last_dt'));

        // ---------------- Lifetime LAST movement (all-time, product-level) ----------------
        $saleAll = DB::table('sale_details as d')->join('sales as h','h.id','=','d.sale_id')
            ->whereNull('h.deleted_at')->where('h.statut','completed')
            ->when($warehouseId, fn($q) => $q->where('h.warehouse_id', $warehouseId))
            ->groupBy('d.product_id')
            ->select('d.product_id', DB::raw('MAX(CONCAT(h.date," ",IFNULL(h.time,"00:00:00"))) as last_dt'));

        $purchaseAll = DB::table('purchase_details as d')->join('purchases as h','h.id','=','d.purchase_id')
            ->whereNull('h.deleted_at')
            ->when($warehouseId, fn($q) => $q->where('h.warehouse_id', $warehouseId))
            ->groupBy('d.product_id')
            ->select('d.product_id', DB::raw('MAX(COALESCE(CONCAT(h.date," ",IFNULL(h.time,"00:00:00")), h.created_at)) as last_dt'));

        $transferAll = DB::table('transfer_details as d')->join('transfers as h','h.id','=','d.transfer_id')
            ->whereNull('h.deleted_at')
            ->when($warehouseId, function ($q) use ($warehouseId) {
                $q->where(function ($qq) use ($warehouseId) {
                    $qq->where('h.from_warehouse_id', $warehouseId)
                    ->orWhere('h.to_warehouse_id', $warehouseId);
                });
            })
            ->groupBy('d.product_id')
            ->select('d.product_id', DB::raw('MAX(COALESCE(CONCAT(h.date," ",IFNULL(h.time,"00:00:00")), h.created_at)) as last_dt'));

        $adjustAll = DB::table('adjustment_details as d')->join('adjustments as h','h.id','=','d.adjustment_id')
            ->whereNull('h.deleted_at')
            ->when($warehouseId, fn($q) => $q->where('h.warehouse_id', $warehouseId))
            ->groupBy('d.product_id')
            ->select('d.product_id', DB::raw('MAX(COALESCE(CONCAT(h.date," ",IFNULL(h.time,"00:00:00")), h.created_at)) as last_dt'));

        // ---------------- BASE (product-level only) ----------------
        $base = DB::table('products as pr')
            ->leftJoinSub($saleWithin,     'sw',  'sw.product_id','=','pr.id')
            ->leftJoinSub($purchaseWithin, 'pw',  'pw.product_id','=','pr.id')
            ->leftJoinSub($transferWithin, 'tw',  'tw.product_id','=','pr.id')
            ->leftJoinSub($adjustWithin,   'aw',  'aw.product_id','=','pr.id')
            ->leftJoinSub($saleAll,        'sa',  'sa.product_id','=','pr.id')
            ->leftJoinSub($purchaseAll,    'pa',  'pa.product_id','=','pr.id')
            ->leftJoinSub($transferAll,    'ta',  'ta.product_id','=','pr.id')
            ->leftJoinSub($adjustAll,      'aa',  'aa.product_id','=','pr.id')
            ->leftJoin('product_warehouse as pwh', function ($j) use ($warehouseId) {
                $j->on('pwh.product_id','=','pr.id');
                if ($warehouseId) $j->where('pwh.warehouse_id', $warehouseId);
            })
            ->whereNull('pr.deleted_at')
            ->where('pr.type','!=','ingredient')
            ->when($brandId,    fn($q) => $q->where('pr.brand_id', $brandId))
            ->when($categoryId, fn($q) => $q->where('pr.category_id', $categoryId))
            // Dead in period: no movement from ANY source
            ->whereRaw('(sw.last_dt IS NULL AND pw.last_dt IS NULL AND tw.last_dt IS NULL AND aw.last_dt IS NULL)');

        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('pr.name','LIKE',"%{$search}%")
                ->orWhere('pr.code','LIKE',"%{$search}%");
            });
        }

        $select = [
            'pr.id as product_id',
            DB::raw('NULL as product_variant_id'),
            'pr.code',
            'pr.name as product_name',
            DB::raw('NULL as variant_name'),
            DB::raw('COALESCE(SUM(pwh.qte),0) as on_hand'),
            DB::raw('GREATEST(
                IFNULL(sa.last_dt,"0000-01-01 00:00:00"),
                IFNULL(pa.last_dt,"0000-01-01 00:00:00"),
                IFNULL(ta.last_dt,"0000-01-01 00:00:00"),
                IFNULL(aa.last_dt,"0000-01-01 00:00:00")
            ) as last_movement_dt'),
        ];

        $base->groupBy('pr.id','pr.code','pr.name');

        // ---------- total rows ----------
        $totalRows = DB::query()
            ->fromSub((clone $base)->select(DB::raw('1')), 't')
            ->count();

        // ---------- sorting ----------
        $rowsQ = (clone $base)->select($select);
        if ($order === 'days_since_last_movement') {
            $rowsQ->orderByRaw(
                ' (CASE WHEN last_movement_dt = "0000-01-01 00:00:00" THEN 99999 ELSE TIMESTAMPDIFF(DAY, last_movement_dt, NOW()) END) ' .
                ($dir === 'asc' ? 'ASC' : 'DESC')
            );
        } elseif ($order === 'last_movement_dt' || $order === 'last_movement_at') {
            $rowsQ->orderByRaw(
                ' (last_movement_dt = "0000-01-01 00:00:00") DESC, last_movement_dt ' .
                ($dir === 'asc' ? 'ASC' : 'DESC')
            );
        } else {
            $safe = ['code','product_name','on_hand'];
            if (in_array($order, $safe, true)) $rowsQ->orderBy($order, $dir);
            else $rowsQ->orderBy('product_name','asc');
        }

        // ---------- pagination ----------
        if ($perPage !== -1) {
            $rowsQ->offset($offset)->limit($perPage);
        }

        $rows = $rowsQ->get();

        // ---------- map ----------
        $data = $rows->map(function ($r) {
            $last = ($r->last_movement_dt === '0000-01-01 00:00:00') ? null : Carbon::parse($r->last_movement_dt);
            return [
                'product_id'               => (int) $r->product_id,
                'product_variant_id'       => null,
                'code'                     => $r->code,
                'product_name'             => $r->product_name,
                'variant_name'             => null,
                'on_hand'                  => (float) $r->on_hand,
                'last_movement_at'         => $last ? $last->format('Y-m-d H:i') : null,
                'days_since_last_movement' => $last ? $last->diffInDays(now()) : null,
                'status'                   => $last ? 'NoMovementInPeriod' : 'NeverMoved',
            ];
        })->all();

        // ---------- explicit range for the UI ----------
        if ($totalRows === 0) {
            $from = 0; $to = 0;
        } else {
            if ($perPage === -1) {
                $from = 1; $to = (int) $totalRows;
            } else {
                $from = $offset + 1;
                $to   = min($offset + $perPage, (int) $totalRows);
            }
        }

        return response()->json([
            'report'     => $data,
            'totalRows'  => (int) $totalRows,
            'page'       => (int) $page,
            'perPage'    => (int) $perPage,                 // -1 when “All”
            'range'      => ['from' => (int) $from, 'to' => (int) $to],
        ]);
    }


    public function draftInvoices(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'draft_invoices_report', Sale::class);

        // Paging/sort
        $perPage   = (int) ($request->limit ?? 10);
        $pageStart = (int) ($request->page ?? 1);
        $offSet    = ($pageStart * $perPage) - $perPage;

        $order = $request->get('SortField', 'age_days');
        $dir   = strtolower($request->get('SortType','desc')) === 'asc' ? 'asc' : 'desc';

        // Date range
        $start = $request->filled('from') ? Carbon::parse($request->get('from'))->startOfDay()
                                        : now()->subDays(29)->startOfDay();
        $end   = $request->filled('to')   ? Carbon::parse($request->get('to'))->endOfDay()
                                        : now()->endOfDay();

        // Filters
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $search      = trim((string) $request->get('search',''));

        // Date expression (prefer d.date; fallback to created_at)
        $dtExpr  = "COALESCE(CONCAT(d.date,' 00:00:00'), d.created_at)";
        $ageExpr = 'DATEDIFF(CURDATE(), COALESCE(d.date, DATE(d.created_at)))';

        $base = DB::table('draft_sales as d')
            ->leftJoin('clients as c', 'c.id', '=', 'd.client_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'd.warehouse_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->whereNull('d.deleted_at')
            ->whereBetween(DB::raw($dtExpr), [$start, $end])
            ->when($warehouseId, fn($q) => $q->where('d.warehouse_id', $warehouseId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('d.Ref', 'LIKE', "%{$search}%")
                    ->orWhere('c.name', 'LIKE', "%{$search}%")
                    ->orWhere('c.code', 'LIKE', "%{$search}%")
                    ->orWhere('w.name', 'LIKE', "%{$search}%")
                    ->orWhere('u.username', 'LIKE', "%{$search}%");
                });
            });

        $totalRows = (clone $base)->count();

        $select = [
            'd.id','d.Ref','d.date','d.created_at','d.GrandTotal','d.TaxNet','d.tax_rate','d.discount','d.shipping',
            'c.name as client_name','w.name as warehouse_name','u.username as user_name',
            DB::raw("$ageExpr as age_days"),
        ];

        $rowsQ = (clone $base)->select($select);

        $sortableComputed = [
            'age_days'   => $ageExpr,
            'client'     => 'c.name',
            'warehouse'  => 'w.name',
            'user'       => 'u.username',
            'created_at' => 'd.created_at',
            'date'       => 'd.date',
            'Ref'        => 'd.Ref',
            'GrandTotal' => 'd.GrandTotal',
            'TaxNet'     => 'd.TaxNet',
            'discount'   => 'd.discount',
            'shipping'   => 'd.shipping',
        ];

        if (isset($sortableComputed[$order])) {
            $rowsQ->orderByRaw($sortableComputed[$order] . ' ' . ($dir === 'asc' ? 'ASC' : 'DESC'));
        } else {
            $rowsQ->orderByRaw($ageExpr . ' DESC');
        }

        if ($perPage !== -1) {
            $rowsQ->offset($offSet)->limit($perPage);
        }

        $rows = $rowsQ->get();

        $data = $rows->map(function ($r) {
            $when = $r->date ?: $r->created_at;
            $dateFormatted = $when ? Carbon::parse($when)->format('Y-m-d') : null;

            return [
                'id'         => (int) $r->id,
                'Ref'        => $r->Ref,
                'date'       => $dateFormatted,
                'client'     => $r->client_name,
                'warehouse'  => $r->warehouse_name,
                'user'       => $r->user_name,
                'GrandTotal' => (float) $r->GrandTotal,
                'TaxNet'     => (float) $r->TaxNet,
                'tax_rate'   => (float) $r->tax_rate,
                'discount'   => (float) $r->discount,
                'shipping'   => (float) $r->shipping,
                'age_days'   => (int) $r->age_days,
                'status'     => 'Draft',
            ];
        })->all();

        // Warehouses for dropdown
        $warehouses = \App\Models\Warehouse::whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id','name']);

        return response()->json([
            'report'     => $data,
            'totalRows'  => $totalRows,
            'warehouses' => $warehouses,
        ]);
    }


    public function discountSummary(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'discount_summary_report', \App\Models\Sale::class);

        // Date range (default last 30 days)
        $start = $request->filled('from') ? \Carbon\Carbon::parse($request->get('from'))->startOfDay()
                                        : now()->subDays(29)->startOfDay();
        $end   = $request->filled('to')   ? \Carbon\Carbon::parse($request->get('to'))->endOfDay()
                                        : now()->endOfDay();

        $search = trim((string) $request->get('search',''));

        // paging/sort
        $perPage = max(1, (int) ($request->get('limit', 10)));
        $page    = max(1, (int) ($request->get('page', 1)));
        $offset  = ($page - 1) * $perPage;

        $order   = $request->get('SortField', 'date_time');
        $dir     = strtolower($request->get('SortType','desc')) === 'asc' ? 'asc' : 'desc';
        $allowed = ['sale_id','date_time','user_name','total_discount'];
        if (!in_array($order, $allowed, true)) $order = 'date_time';

        // Date expr (handles optional time)
        $dateExpr = "COALESCE(CONCAT(s.date,' ',IFNULL(s.time,'00:00:00')), s.created_at)";
        $between  = [$start->toDateTimeString(), $end->toDateTimeString()];

        // ---- LINE discount value on each detail row ----
        // Handles both numeric (1/2) and string ('percent'/'fixed') methods.
        $lineDiscountExpr = "
        CASE
            WHEN sd.discount IS NULL THEN 0
            WHEN (sd.discount_method IN ('percent','percentage','%') OR sd.discount_method = 1)
            THEN COALESCE(sd.price,0) * COALESCE(sd.quantity,0) * COALESCE(sd.discount,0)/100
            WHEN (sd.discount_method IN ('fixed','amount','value') OR sd.discount_method = 2)
            THEN COALESCE(sd.discount,0) * COALESCE(sd.quantity,0)
            ELSE 0
        END
        ";

        // ---- HEADER discount per sale (includes points) ----
        $headerExpr = "COALESCE(s.discount,0) + COALESCE(s.discount_from_points,0)";

        // Base: completed sales within date range
        $detailsBase = DB::table('sale_details as sd')
            ->join('sales as s','s.id','=','sd.sale_id')
            ->leftJoin('users as u','u.id','=','s.user_id')
            ->whereNull('s.deleted_at')
            ->where('s.statut','completed')
            ->whereBetween(DB::raw($dateExpr), $between);

        if ($search !== '') {
            $detailsBase->where(function($q) use ($search){
                $q->where('u.username','LIKE',"%{$search}%")
                ->orWhere('s.id','LIKE',"%{$search}%");
            });
        }

        // A. OVERALL TOTAL = sum(line) + sum(header)
        $overallLine   = (clone $detailsBase)->selectRaw("COALESCE(SUM($lineDiscountExpr),0) as t")->value('t') ?? 0;

        // Use header-only aggregation to avoid multiplying header by line count
        $headerBase = DB::table('sales as s')
            ->whereNull('s.deleted_at')
            ->where('s.statut','completed')
            ->whereBetween(DB::raw($dateExpr), $between);

        if ($search !== '') {
            $headerBase->leftJoin('users as u','u.id','=','s.user_id')
                    ->where(function($q) use ($search){
                        $q->where('u.username','LIKE',"%{$search}%")
                            ->orWhere('s.id','LIKE',"%{$search}%");
                    });
        }

        $overallHeader = (clone $headerBase)->selectRaw("COALESCE(SUM($headerExpr),0) as t")->value('t') ?? 0;
        $overallTotal  = (float) $overallLine + (float) $overallHeader;

        // B. TIMESERIES per day = (sum line per day) + (sum header per day)
        $tsLine = (clone $detailsBase)
            ->selectRaw("DATE(s.date) as d")
            ->selectRaw("COALESCE(SUM($lineDiscountExpr),0) as line_total")
            ->groupBy('d')->get()->keyBy('d');

        $tsHeader = (clone $headerBase)
            ->selectRaw("DATE(s.date) as d")
            ->selectRaw("COALESCE(SUM($headerExpr),0) as header_total")
            ->groupBy('d')->get()->keyBy('d');

        // merge days
        $allDays = collect($tsLine->keys())->merge($tsHeader->keys())->unique()->sort();
        $timeseries = $allDays->map(function($d) use($tsLine,$tsHeader){
            $l = (float) ($tsLine[$d]->line_total ?? 0);
            $h = (float) ($tsHeader[$d]->header_total ?? 0);
            return (object)['d'=>$d, 'total_discount'=> $l + $h];
        })->values();

        // C. TABLE rows — one row per sale with BOTH components (no duplication)
        $tableBase = (clone $detailsBase)
            ->groupBy('s.id','u.username','s.date','s.time','s.created_at')
            ->selectRaw('s.id as sale_id')
            ->selectRaw("$dateExpr as dt")
            ->selectRaw('COALESCE(u.username,"—") as user_name')
            ->selectRaw("COALESCE(SUM($lineDiscountExpr),0) as line_discount")
            ->selectRaw("MAX($headerExpr) as header_discount") // header value once per sale
            ->selectRaw("(COALESCE(SUM($lineDiscountExpr),0) + COALESCE(MAX($headerExpr),0)) as total_discount");

        // Count rows safely
        $totalRows = DB::query()->fromSub($tableBase, 'x')->count();

        // Fetch page
        $sortCol = $order === 'date_time' ? 'dt' : $order;
        $rows = $tableBase
            ->orderBy($sortCol, $dir)
            ->offset($offset)->limit($perPage)
            ->get()
            ->map(function($r){
                return [
                    'sale_id'        => (int) $r->sale_id,
                    'date_time'      => \Carbon\Carbon::parse($r->dt)->format('Y-m-d H:i'),
                    'user_name'      => $r->user_name,
                    'total_discount' => (float) $r->total_discount,
                    // you can expose the components if you later want to show them:
                    'line_discount'   => (float) $r->line_discount,
                    'header_discount' => (float) $r->header_discount,
                ];
            });

        return response()->json([
            'report'        => $rows,
            'totalRows'     => $totalRows,
            'overall_total' => (float) $overallTotal,
            'timeseries'    => $timeseries,
        ]);
    }



    public function taxSummary(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'tax_summary_report', \App\Models\Sale::class);

        $start = $request->filled('from') ? \Carbon\Carbon::parse($request->get('from'))->startOfDay()
                                        : now()->subDays(29)->startOfDay();
        $end   = $request->filled('to')   ? \Carbon\Carbon::parse($request->get('to'))->endOfDay()
                                        : now()->endOfDay();

        $search = trim((string) $request->get('search',''));
        $perPage = max(1, (int) ($request->get('limit', 10)));
        $page    = max(1, (int) ($request->get('page', 1)));
        $offset  = ($page - 1) * $perPage;

        $order   = $request->get('SortField', 'date_time');
        $dir     = strtolower($request->get('SortType','desc')) === 'asc' ? 'asc' : 'desc';
        $allowed = ['sale_id','date_time','user_name','taxable_base','tax_collected','effective_rate'];
        if (!in_array($order, $allowed, true)) $order = 'date_time';

        $dateExpr = "COALESCE(CONCAT(s.date,' ',IFNULL(s.time,'00:00:00')), s.created_at)";
        $dExpr    = "DATE(COALESCE(s.date, DATE(s.created_at)))";
        $between  = [$start->toDateTimeString(), $end->toDateTimeString()];

        // === expressions (paste from block above) ===
        $unitSubtotalExpr     = "COALESCE(sd.price,0)";
        $discountPerUnitExpr  = "
        CASE
            WHEN sd.discount IS NULL THEN 0
            WHEN (sd.discount_method IN ('fixed','amount','value') OR sd.discount_method = 2) 
            THEN COALESCE(sd.discount,0)
            ELSE COALESCE(sd.price,0) * COALESCE(sd.discount,0) / 100
        END
        ";
        $unitAfterDiscExpr    = "GREATEST( COALESCE(sd.price,0) - ($discountPerUnitExpr), 0 )";
        $rateExpr             = "COALESCE(sd.TaxNet,0) / 100";
        $taxPerUnitExpr       = "($unitAfterDiscExpr) * ($rateExpr)";
        $basePerUnitExpr      = "
        CASE
            WHEN (sd.tax_method IN ('2','Inclusive')) 
            THEN GREATEST(($unitAfterDiscExpr) - ($taxPerUnitExpr), 0)
            ELSE ($unitAfterDiscExpr)
        END
        ";
        $qtyExpr              = "COALESCE(sd.quantity,0)";
        $taxableBaseExpr      = "($basePerUnitExpr) * $qtyExpr";
        $taxAmountExpr        = "($taxPerUnitExpr) * $qtyExpr";

        $base = DB::table('sale_details as sd')
            ->join('sales as s', 's.id', '=', 'sd.sale_id')
            ->leftJoin('users as u','u.id','=','s.user_id')
            ->whereNull('s.deleted_at')
            ->where('s.statut','completed')
            ->whereBetween(DB::raw($dateExpr), $between);

        if ($search !== '') {
            $base->where(function($q) use ($search){
                $q->where('u.username','LIKE',"%{$search}%")
                ->orWhere('s.id','LIKE',"%{$search}%");
            });
        }

        // Totals for filtered range
        $totalsRow = (clone $base)
            ->selectRaw("COALESCE(SUM($taxableBaseExpr),0) as base_total")
            ->selectRaw("COALESCE(SUM($taxAmountExpr),0)   as tax_total")
            ->first();

        $totals = [
            'base' => (float) ($totalsRow->base_total ?? 0),
            'tax'  => (float) ($totalsRow->tax_total ?? 0),
        ];

        // Timeseries per day
        $timeseries = (clone $base)
            ->selectRaw("$dExpr as d")
            ->selectRaw("COALESCE(SUM($taxableBaseExpr),0) as taxable_base")
            ->selectRaw("COALESCE(SUM($taxAmountExpr),0)   as tax_collected")
            ->groupBy('d')->orderBy('d','asc')->get();

        // Table: one row per sale
        $tableBase = (clone $base)
            ->groupBy('s.id','u.username','s.date','s.time','s.created_at')
            ->selectRaw('s.id as sale_id')
            ->selectRaw("$dateExpr as dt")
            ->selectRaw('COALESCE(u.username,"—") as user_name')
            ->selectRaw("COALESCE(SUM($taxableBaseExpr),0) as taxable_base")
            ->selectRaw("COALESCE(SUM($taxAmountExpr),0)   as tax_collected")
            ->selectRaw("CASE WHEN SUM($taxableBaseExpr)=0 
                    THEN NULL 
                    ELSE (SUM($taxAmountExpr)/SUM($taxableBaseExpr))*100 
                END as effective_rate");

        $totalRows = DB::query()->fromSub($tableBase, 'x')->count();
        $sortCol = $order === 'date_time' ? 'dt' : $order;

        $rows = $tableBase
            ->orderBy($sortCol, $dir)
            ->offset($offset)->limit($perPage)
            ->get()
            ->map(function($r){
                return [
                    'sale_id'        => (int) $r->sale_id,
                    'date_time'      => \Carbon\Carbon::parse($r->dt)->format('Y-m-d H:i'),
                    'user_name'      => $r->user_name,
                    'taxable_base'   => (float) $r->taxable_base,
                    'tax_collected'  => (float) $r->tax_collected,
                    'effective_rate' => is_null($r->effective_rate) ? null : round((float)$r->effective_rate, 2),
                ];
            });

        return response()->json([
            'report'     => $rows,
            'totalRows'  => $totalRows,
            'totals'     => $totals,
            'timeseries' => $timeseries,
        ]);
    }



    public function stockAging(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Stock_Aging_Report', Product::class);

        // ---- paging/sort ----
        $perPage   = (int) ($request->limit ?? 10);
        if ($perPage === 0) $perPage = 10;
        $pageStart = max((int) ($request->page ?? 1), 1);
        $offSet    = ($pageStart * $perPage) - $perPage;

        $order = $request->get('SortField', 'age_days');
        if ($order === 'last_inbound_at') $order = 'last_inbound_dt';
        $dir   = strtolower($request->get('SortType', 'desc')) === 'asc' ? 'asc' : 'desc';

        // ---- filters ----
        $dimension   = in_array($request->dimension, ['product','variant'], true) ? $request->dimension : 'product';
        $warehouseId = $request->filled('warehouse_id') ? $request->warehouse_id : null;
        $brandId     = $request->filled('brand_id')     ? $request->brand_id     : null;
        $categoryId  = $request->filled('category_id')  ? $request->category_id  : null;
        $search      = trim((string) $request->search);

        // ---- buckets ----
        $cuts = collect(explode(',', (string) $request->buckets))
            ->map(fn($x) => (int) trim($x))
            ->filter(fn($n) => $n > 0)
            ->sort()->values();
        if ($cuts->isEmpty()) { $cuts = collect([30,60,90]); }

        // ---------- helpers to build inbound subqueries ----------
        $buildPurchaseInbound = function(array $groupCols) use ($warehouseId) {
            return DB::table('purchase_details as d')
                ->join('purchases as h','h.id','=','d.purchase_id')
                ->whereNull('h.deleted_at')
                ->when($warehouseId, fn($q) => $q->where('h.warehouse_id', $warehouseId))
                ->groupBy($groupCols)
                ->select(array_merge($groupCols, [
                    DB::raw("MAX(COALESCE(TIMESTAMP(h.`date`, IFNULL(h.`time`,'00:00:00')), h.`created_at`)) as in_dt")
                ]));
        };

        $buildTransferInbound = function(array $groupCols) use ($warehouseId) {
            return DB::table('transfer_details as d')
                ->join('transfers as h','h.id','=','d.transfer_id')
                ->whereNull('h.deleted_at')
                ->when($warehouseId, fn($q) => $q->where('h.to_warehouse_id', $warehouseId))
                ->groupBy($groupCols)
                ->select(array_merge($groupCols, [
                    DB::raw("MAX(COALESCE(TIMESTAMP(h.`date`, IFNULL(h.`time`,'00:00:00')), h.`created_at`)) as in_dt")
                ]));
        };

        $buildAdjustInbound = function(array $groupCols) use ($warehouseId) {
            return DB::table('adjustment_details as d')
                ->join('adjustments as h','h.id','=','d.adjustment_id')
                ->whereNull('h.deleted_at')
                ->when($warehouseId, fn($q) => $q->where('h.warehouse_id', $warehouseId))
                ->where(function ($q) {
                    $q->where('d.type', '=', 'add')
                    ->orWhere('d.quantity', '>', 0);
                })
                ->groupBy($groupCols)
                ->select(array_merge($groupCols, [
                    DB::raw("MAX(COALESCE(TIMESTAMP(h.`date`, IFNULL(h.`time`,'00:00:00')), h.`created_at`)) as in_dt")
                ]));
        };

        if ($dimension === 'variant') {
            // ---------------- VARIANT dimension ----------------
            $cols = ['product_id','product_variant_id'];

            $purchaseInbound = $buildPurchaseInbound($cols);
            $transferInbound = $buildTransferInbound($cols);
            $adjustInbound   = $buildAdjustInbound($cols);

            $base = DB::table('product_variants as pv')
                ->join('products as pr', 'pr.id','=','pv.product_id')
                ->leftJoinSub($purchaseInbound, 'pi', fn($j) => $j
                    ->on('pi.product_id','=','pv.product_id')
                    ->on('pi.product_variant_id','=','pv.id'))
                ->leftJoinSub($transferInbound, 'ti', fn($j) => $j
                    ->on('ti.product_id','=','pv.product_id')
                    ->on('ti.product_variant_id','=','pv.id'))
                ->leftJoinSub($adjustInbound,   'ai', fn($j) => $j
                    ->on('ai.product_id','=','pv.product_id')
                    ->on('ai.product_variant_id','=','pv.id'))
                ->leftJoin('product_warehouse as pwh', function ($j) use ($warehouseId) {
                    $j->on('pwh.product_id','=','pv.product_id')
                    ->on('pwh.product_variant_id','=','pv.id');
                    if ($warehouseId) $j->where('pwh.warehouse_id', $warehouseId);
                })
                ->whereNull('pr.deleted_at')
                ->where('pr.type','!=','ingredient')
                ->when($brandId,    fn($q) => $q->where('pr.brand_id', $brandId))
                ->when($categoryId, fn($q) => $q->where('pr.category_id', $categoryId));

            if ($search !== '') {
                $base->where(function ($q) use ($search) {
                    $q->where('pr.name','LIKE',"%{$search}%")
                    ->orWhere('pr.code','LIKE',"%{$search}%")
                    ->orWhere('pv.name','LIKE',"%{$search}%");
                });
            }

            // COUNT via subquery (1 row per variant)
            $totalRows = DB::query()
                ->fromSub(
                    (clone $base)->select('pv.id')->groupBy('pv.id'),
                    't'
                )->count();

            $rowsQ = (clone $base)->select([
                'pr.id as product_id',
                'pv.id as product_variant_id',
                'pr.code',
                'pr.name as product_name',
                'pv.name as variant_name',
                DB::raw('COALESCE(SUM(pwh.qte),0) as on_hand'),
                DB::raw("GREATEST(
                    IFNULL(pi.in_dt,'1970-01-01 00:00:00'),
                    IFNULL(ti.in_dt,'1970-01-01 00:00:00'),
                    IFNULL(ai.in_dt,'1970-01-01 00:00:00')
                ) as last_inbound_dt"),
            ])->groupBy('pr.id','pv.id');

        } else {
            // ---------------- PRODUCT dimension ----------------
            // Build product-level inbound subqueries directly (so alias `in_dt` exists here)
            $pi = DB::table('purchase_details as d')
                ->join('purchases as h','h.id','=','d.purchase_id')
                ->whereNull('h.deleted_at')
                ->when($warehouseId, fn($q) => $q->where('h.warehouse_id', $warehouseId))
                ->groupBy('product_id')
                ->select([
                    'product_id',
                    DB::raw("MAX(COALESCE(TIMESTAMP(h.`date`, IFNULL(h.`time`,'00:00:00')), h.`created_at`)) as in_dt")
                ]);

            $ti = DB::table('transfer_details as d')
                ->join('transfers as h','h.id','=','d.transfer_id')
                ->whereNull('h.deleted_at')
                ->when($warehouseId, fn($q) => $q->where('h.to_warehouse_id', $warehouseId))
                ->groupBy('product_id')
                ->select([
                    'product_id',
                    DB::raw("MAX(COALESCE(TIMESTAMP(h.`date`, IFNULL(h.`time`,'00:00:00')), h.`created_at`)) as in_dt")
                ]);

            $ai = DB::table('adjustment_details as d')
                ->join('adjustments as h','h.id','=','d.adjustment_id')
                ->whereNull('h.deleted_at')
                ->when($warehouseId, fn($q) => $q->where('h.warehouse_id', $warehouseId))
                ->where(function ($q) {
                    $q->where('d.type', '=', 'add')
                    ->orWhere('d.quantity', '>', 0);
                })
                ->groupBy('product_id')
                ->select([
                    'product_id',
                    DB::raw("MAX(COALESCE(TIMESTAMP(h.`date`, IFNULL(h.`time`,'00:00:00')), h.`created_at`)) as in_dt")
                ]);

            $base = DB::table('products as pr')
                ->leftJoinSub($pi, 'pi', 'pi.product_id','=','pr.id')
                ->leftJoinSub($ti, 'ti', 'ti.product_id','=','pr.id')
                ->leftJoinSub($ai, 'ai', 'ai.product_id','=','pr.id')
                ->leftJoin('product_warehouse as pwh', function ($j) use ($warehouseId) {
                    $j->on('pwh.product_id','=','pr.id');
                    if ($warehouseId) $j->where('pwh.warehouse_id', $warehouseId);
                })
                ->whereNull('pr.deleted_at')
                ->where('pr.type','!=','ingredient')
                ->when($brandId,    fn($q) => $q->where('pr.brand_id', $brandId))
                ->when($categoryId, fn($q) => $q->where('pr.category_id', $categoryId));

            if ($search !== '') {
                $base->where(function ($q) use ($search) {
                    $q->where('pr.name','LIKE',"%{$search}%")
                    ->orWhere('pr.code','LIKE',"%{$search}%");
                });
            }

            // COUNT via subquery (1 row per product)
            $totalRows = DB::query()
                ->fromSub(
                    (clone $base)->select('pr.id')->groupBy('pr.id'),
                    't'
                )->count();

            $rowsQ = (clone $base)->select([
                'pr.id as product_id',
                DB::raw('NULL as product_variant_id'),
                'pr.code',
                'pr.name as product_name',
                DB::raw('NULL as variant_name'),
                DB::raw('COALESCE(SUM(pwh.qte),0) as on_hand'),
                DB::raw("GREATEST(
                    IFNULL(pi.in_dt,'1970-01-01 00:00:00'),
                    IFNULL(ti.in_dt,'1970-01-01 00:00:00'),
                    IFNULL(ai.in_dt,'1970-01-01 00:00:00')
                ) as last_inbound_dt"),
            ])->groupBy('pr.id');
        }

        // ---- sorting ----
        if ($order === 'age_days') {
            $rowsQ->orderByRaw(
                " (CASE WHEN last_inbound_dt = '1970-01-01 00:00:00'
                        THEN 99999
                        ELSE TIMESTAMPDIFF(DAY, last_inbound_dt, NOW())
                END) " . ($dir === 'asc' ? 'ASC' : 'DESC')
            );
        } elseif ($order === 'last_inbound_dt') {
            $rowsQ->orderByRaw(
                " (last_inbound_dt = '1970-01-01 00:00:00') DESC, last_inbound_dt " . ($dir === 'asc' ? 'ASC' : 'DESC')
            );
        } else {
            $safe = ['code','product_name','variant_name','on_hand'];
            if (in_array($order, $safe, true)) {
                $rowsQ->orderBy($order, $dir);
            } else {
                $rowsQ->orderBy('product_name','asc');
            }
        }

        if ($perPage !== -1) {
            $rowsQ->offset($offSet)->limit($perPage);
        }

        $rows = $rowsQ->get();

        // ---- bucket label helper ----
        $labelBucket = function (?int $age) use ($cuts) {
            if ($age === null) return null;
            $c = $cuts->all(); // ascending
            if ($age <= $c[0]) return "0–{$c[0]}";
            if (count($c) === 1) return ">{$c[0]}";
            if ($age <= $c[1]) return ($c[0]+1)."–{$c[1]}";
            if (count($c) === 2) return ">{$c[1]}";
            if ($age <= $c[2]) return ($c[1]+1)."–{$c[2]}";
            return ">{$c[2]}";
        };

        $data = $rows->map(function ($r) use ($labelBucket) {
            $last = ($r->last_inbound_dt === '1970-01-01 00:00:00') ? null : Carbon::parse($r->last_inbound_dt);
            $ageDays = $last ? $last->diffInDays(now()) : null;

            return [
                'product_id'         => (int) $r->product_id,
                'product_variant_id' => $r->product_variant_id ? (int) $r->product_variant_id : null,
                'code'               => $r->code,
                'product_name'       => $r->product_name,
                'variant_name'       => $r->variant_name,
                'on_hand'            => (float) $r->on_hand,
                'last_inbound_at'    => $last ? $last->format('Y-m-d H:i') : null,
                'age_days'           => $ageDays,
                'age_bucket'         => $labelBucket($ageDays),
            ];
        })->all();

        return response()->json([
            'report'    => $data,
            'totalRows' => (int) $totalRows,
        ]);
    }


    public function stockAgingFilters(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Stock_Aging_Report', Product::class);

        // Fetch visible options; adjust table/column names if yours differ
        $warehouses = DB::table('warehouses')
            ->whereNull('deleted_at')
            ->select('id','name')
            ->orderBy('name')
            ->get();

        $brands = DB::table('brands')
            ->whereNull('deleted_at')
            ->select('id','name')
            ->orderBy('name')
            ->get();

        $categories = DB::table('categories')
            ->whereNull('deleted_at')
            ->select('id','name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'warehouses' => $warehouses,
            'brands'     => $brands,
            'categories' => $categories,
        ]);
    }




    public function stockTransferReport(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Stock_Transfer_Report', Transfer::class);

        $start = Carbon::parse($request->from ?? now()->subDays(6))->startOfDay();
        $end   = Carbon::parse($request->to ?? now())->endOfDay();

        // Filters
        $fromWarehouseId = $request->integer('from_warehouse_id') ?: null;
        $toWarehouseId   = $request->integer('to_warehouse_id') ?: null;
        $warehouseId     = $request->integer('warehouse_id') ?: null; // if set, “direction” applies
        $direction       = in_array($request->direction, ['all','inbound','outbound'], true) ? $request->direction : 'all';
        $statut          = $request->get('statut'); // optional: pending/completed/etc.
        $search          = trim((string) $request->get('search',''));

        // Table params
        $perPage = max(1, (int) ($request->get('limit', 10)));
        $page    = max(1, (int) ($request->get('page', 1)));
        $offset  = ($page - 1) * $perPage;

        $sortField = $request->get('SortField', 'dt');
        $sortType  = strtolower($request->get('SortType','desc')) === 'asc' ? 'asc' : 'desc';

        // Warehouses (for dropdowns)
        $warehouses = Warehouse::whereNull('deleted_at')->orderBy('name')->get(['id','name']);

        // Base join (details + header)
        $base = TransferDetail::from('transfer_details as d')
            ->join('transfers as t', 't.id', '=', 'd.transfer_id')
            ->whereNull('t.deleted_at')
            ->whereBetween(DB::raw('COALESCE(CONCAT(t.date," ",IFNULL(t.time,"00:00:00")), t.created_at)'), [$start, $end])
            ->when($statut, fn($q) => $q->where('t.statut', $statut))
            ->when($fromWarehouseId, fn($q) => $q->where('t.from_warehouse_id', $fromWarehouseId))
            ->when($toWarehouseId,   fn($q) => $q->where('t.to_warehouse_id', $toWarehouseId))
            ->when($warehouseId && $direction === 'inbound',  fn($q) => $q->where('t.to_warehouse_id', $warehouseId))
            ->when($warehouseId && $direction === 'outbound', fn($q) => $q->where('t.from_warehouse_id', $warehouseId))
            ->when($warehouseId && $direction === 'all',      fn($q) => $q->where(function($qq) use($warehouseId){
                $qq->where('t.from_warehouse_id',$warehouseId)->orWhere('t.to_warehouse_id',$warehouseId);
            }))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function($w) use ($search) {
                    $w->where('t.notes','LIKE',"%{$search}%")
                      ->orWhere('t.id','LIKE',"%{$search}%");
                });
            });

        // ---------- KPIs ----------
        $kpi = (clone $base)
            ->selectRaw('COUNT(DISTINCT t.id) as transfers_count')
            ->selectRaw('COUNT(d.id) as lines_count')
            ->selectRaw('COALESCE(SUM(d.quantity),0) as qty_sum')
            ->selectRaw('COALESCE(SUM(d.total),0) as value_sum')
            ->first();

        // ---------- Time-series (day) ----------
        $timeseries = (clone $base)
            ->selectRaw('DATE(COALESCE(t.date, DATE(t.created_at))) as d')
            ->selectRaw('COALESCE(SUM(d.quantity),0) as qty')
            ->selectRaw('COALESCE(SUM(d.total),0) as val')
            ->groupBy('d')->orderBy('d','asc')->get();

        // ---------- Top routes ----------
        $routes = (clone $base)
            ->join('warehouses as wf','wf.id','=','t.from_warehouse_id')
            ->join('warehouses as wt','wt.id','=','t.to_warehouse_id')
            ->selectRaw('t.from_warehouse_id, wf.name as from_name')
            ->selectRaw('t.to_warehouse_id, wt.name as to_name')
            ->selectRaw('COUNT(DISTINCT t.id) as transfers')
            ->selectRaw('COALESCE(SUM(d.quantity),0) as qty')
            ->selectRaw('COALESCE(SUM(d.total),0) as val')
            ->groupBy('t.from_warehouse_id','wf.name','t.to_warehouse_id','wt.name')
            ->orderBy('val','desc')
            ->limit(10)->get();

        // ---------- Table (one row per transfer) ----------
        $tableBase = (clone $base)
            ->leftJoin('warehouses as wf','wf.id','=','t.from_warehouse_id')
            ->leftJoin('warehouses as wt','wt.id','=','t.to_warehouse_id')
            ->selectRaw('t.id as transfer_id')
            ->selectRaw('COALESCE(CONCAT(t.date," ",IFNULL(t.time,"00:00:00")), t.created_at) as dt')
            ->selectRaw('wf.name as from_wh, wt.name as to_wh')
            ->selectRaw('COALESCE(SUM(d.quantity),0) as qty')
            ->selectRaw('COALESCE(SUM(d.total),0) as val')
            ->selectRaw('t.statut as statut')
            ->groupBy('t.id','dt','from_wh','to_wh','t.statut');

        $totalRows = DB::table(DB::raw("({$tableBase->toSql()}) as x"))
            ->mergeBindings($tableBase->getQuery())
            ->count();

        $sortable = ['dt','from_wh','to_wh','qty','val','statut','transfer_id'];
        if (!in_array($sortField, $sortable, true)) $sortField = 'dt';

        $rows = $tableBase
            ->orderBy($sortField, $sortType)
            ->offset($offset)->limit($perPage)
            ->get()
            ->map(function($r){
                return [
                    'transfer_id' => (int) $r->transfer_id,
                    'date_time'   => Carbon::parse($r->dt)->format('Y-m-d H:i'),
                    'from'        => $r->from_wh,
                    'to'          => $r->to_wh,
                    'qty'         => (float) $r->qty,
                    'value'       => (float) $r->val,
                    'statut'      => $r->statut,
                ];
            });

        return response()->json([
            'data' => [
                'kpis' => [
                    'transfers_count' => (int) $kpi->transfers_count,
                    'lines_count'     => (int) $kpi->lines_count,
                    'qty_sum'         => (float) $kpi->qty_sum,
                    'value_sum'       => (float) $kpi->value_sum,
                    'avg_items_per_transfer' => $kpi->transfers_count ? round($kpi->qty_sum / $kpi->transfers_count, 2) : 0,
                    'avg_value_per_transfer' => $kpi->transfers_count ? round($kpi->value_sum / $kpi->transfers_count, 2) : 0,
                ],
                'timeseries' => $timeseries,
                'routes'     => $routes,
                'rows'       => $rows,
                'totalRows'  => $totalRows,
            ],
            'warehouses' => $warehouses,
        ]);
    }



    public function stockAdjustmentReport(Request $request)
    {
        // Permission (adjust to your policy/ability name if different)
        $this->authorizeForUser($request->user('api'), 'Stock_Adjustment_Report', Adjustment::class);

        // Dates
        $start = $request->from ? Carbon::parse($request->from.' 00:00:00') : now()->subDays(6)->startOfDay();
        $end   = $request->to   ? Carbon::parse($request->to.' 23:59:59') : now()->endOfDay();

        // Warehouse scope (user)
        $user = auth()->user();
        if ($user->is_all_warehouses) {
            $warehouses = Warehouse::whereNull('deleted_at')->orderBy('name')->get(['id','name']);
            $array_warehouses_id = $warehouses->pluck('id')->all();
        } else {
            $array_warehouses_id = UserWarehouse::where('user_id', $user->id)->pluck('warehouse_id')->all();
            $warehouses = Warehouse::whereNull('deleted_at')->whereIn('id',$array_warehouses_id)->orderBy('name')->get(['id','name']);
        }

        // Filters
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $search = trim((string)$request->get('search',''));

        // Base (details + header)
        $base = AdjustmentDetail::from('adjustment_details as d')
            ->join('adjustments as a','a.id','=','d.adjustment_id')
            ->whereNull('a.deleted_at')
            ->whereBetween(
                DB::raw("COALESCE(CONCAT(a.date,' ',IFNULL(a.time,'00:00:00')), a.created_at)"),
                [$start->toDateTimeString(), $end->toDateTimeString()]
            )
            ->when($warehouseId, fn($q) => $q->where('a.warehouse_id', $warehouseId))
            ->when(!$warehouseId, fn($q) => $q->whereIn('a.warehouse_id', $array_warehouses_id))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function($w) use ($search) {
                    $w->where('a.Ref','LIKE',"%{$search}%")
                    ->orWhere('a.notes','LIKE',"%{$search}%");
                });
            });

        // ---- KPIs
        $kpi = (clone $base)
            ->selectRaw('COUNT(DISTINCT a.id) as adjustments_count')
            ->selectRaw("SUM(CASE WHEN d.type='add' THEN d.quantity ELSE 0 END) as qty_added")
            ->selectRaw("SUM(CASE WHEN d.type='sub' THEN d.quantity ELSE 0 END) as qty_removed")
            ->first();

        // ---- Time series (by day)
        $timeseries = (clone $base)
            ->selectRaw("DATE(COALESCE(a.date, DATE(a.created_at))) as d")
            ->selectRaw("SUM(CASE WHEN d.type='add' THEN d.quantity ELSE -d.quantity END) as net_qty")
            ->groupBy('d')->orderBy('d','asc')->get();

        // ---- By type (pie)  ✅ alias renamed from `lines` to `total_lines`
        $byType = (clone $base)
            ->select('d.type')
            ->selectRaw('COUNT(d.id) as total_lines')
            ->selectRaw('SUM(d.quantity) as qty')
            ->groupBy('d.type')->get();

        // ---- Table (paginated)
        $perPage  = max(1, (int)($request->get('limit', 10)));
        $page     = max(1, (int)($request->get('page', 1)));
        $offset   = ($page - 1) * $perPage;
        $sortField = $request->get('SortField', 'dt');
        $sortType  = strtolower($request->get('SortType','desc')) === 'asc' ? 'asc' : 'desc';
        $sortable  = ['dt','warehouse','qty','net_qty','ref','adj_id'];

        $tableBase = (clone $base)
            ->leftJoin('warehouses as w','w.id','=','a.warehouse_id')
            ->selectRaw('a.id as adj_id, a.Ref as ref')
            ->selectRaw('COALESCE(CONCAT(a.date," ",IFNULL(a.time,"00:00:00")), a.created_at) as dt')
            ->selectRaw('w.name as warehouse')
            ->selectRaw('SUM(d.quantity) as qty')
            ->selectRaw('SUM(CASE WHEN d.type="add" THEN d.quantity ELSE -d.quantity END) as net_qty')
            ->groupBy('a.id','a.Ref','dt','w.name');

        // total rows (count groups)
        $totalRows = DB::table(DB::raw("({$tableBase->toSql()}) as t"))
            ->mergeBindings($tableBase->getQuery())
            ->count();

        $rows = (clone $tableBase)
            ->orderBy(in_array($sortField, $sortable, true) ? $sortField : 'dt', $sortType)
            ->offset($offset)->limit($perPage)
            ->get()
            ->map(function ($r) {
                return [
                    'adj_id'   => (int)$r->adj_id,
                    'ref'      => $r->ref,
                    'date'     => Carbon::parse($r->dt)->format('Y-m-d H:i'),
                    'warehouse'=> $r->warehouse,
                    'qty'      => (float)$r->qty,
                    'net_qty'  => (float)$r->net_qty,
                ];
            });

        return response()->json([
            'data' => [
                'kpis' => [
                    'adjustments_count' => (int)$kpi->adjustments_count,
                    'qty_added'         => (float)$kpi->qty_added,
                    'qty_removed'       => (float)$kpi->qty_removed,
                    'net_qty'           => (float)$kpi->qty_added - (float)$kpi->qty_removed,
                ],
                'timeseries' => $timeseries,
                'byType'     => $byType,
                'rows'       => $rows,
                'totalRows'  => $totalRows,
            ],
            'warehouses' => $warehouses,
        ]);
    }


    public function topSuppliersReport(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Top_Suppliers_Report',  Provider::class);

        // Dates (safe, quoted)
        $start = $request->from ? Carbon::parse($request->from.' 00:00:00') : now()->startOfMonth();
        $end   = $request->to   ? Carbon::parse($request->to.' 23:59:59')   : now()->endOfMonth();

        // User warehouse scope
        $user = auth()->user();
        if ($user->is_all_warehouses) {
            $warehouses = Warehouse::whereNull('deleted_at')->orderBy('name')->get(['id','name']);
            $array_warehouses_id = $warehouses->pluck('id')->all();
        } else {
            $array_warehouses_id = UserWarehouse::where('user_id', $user->id)->pluck('warehouse_id')->all();
            $warehouses = Warehouse::whereNull('deleted_at')->whereIn('id',$array_warehouses_id)->orderBy('name')->get(['id','name']);
        }

        // Filters
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $search = trim((string) $request->get('search',''));

        // Header base (purchases)
        $hdr = DB::table('purchases as h')
            ->whereNull('h.deleted_at')
            ->where('h.statut','received')
            ->whereBetween(DB::raw("COALESCE(CONCAT(h.date,' ',IFNULL(h.time,'00:00:00')), h.created_at)"), [$start->toDateTimeString(), $end->toDateTimeString()])
            ->when($warehouseId, fn($q)=>$q->where('h.warehouse_id',$warehouseId))
            ->when(!$warehouseId, fn($q)=>$q->whereIn('h.warehouse_id',$array_warehouses_id));

        // VALUE by supplier (sum GrandTotal, count orders)
        $valueAgg = (clone $hdr)
            ->groupBy('h.provider_id')
            ->selectRaw('h.provider_id, SUM(h.GrandTotal) as value_sum, COUNT(*) as orders_count');

        // QTY by supplier (sum details quantity)
        $qtyAgg = DB::table('purchase_details as d')
            ->join('purchases as h','h.id','=','d.purchase_id')
            ->whereNull('h.deleted_at')
            ->where('h.statut','received')
            ->whereBetween(DB::raw("COALESCE(CONCAT(h.date,' ',IFNULL(h.time,'00:00:00')), h.created_at)"), [$start->toDateTimeString(), $end->toDateTimeString()])
            ->when($warehouseId, fn($q)=>$q->where('h.warehouse_id',$warehouseId))
            ->when(!$warehouseId, fn($q)=>$q->whereIn('h.warehouse_id',$array_warehouses_id))
            ->groupBy('h.provider_id')
            ->selectRaw('h.provider_id, COALESCE(SUM(d.quantity),0) as qty_sum');

        // Join + supplier names (assuming table 'providers')
        $base = DB::query()
            ->fromSub($valueAgg, 'v')
            ->leftJoinSub($qtyAgg, 'q', 'q.provider_id', '=', 'v.provider_id')
            ->join('providers as s','s.id','=','v.provider_id')
            ->selectRaw('s.id as supplier_id, s.name as supplier')
            ->selectRaw('v.orders_count, v.value_sum, COALESCE(q.qty_sum,0) as qty_sum')
            ->selectRaw('CASE WHEN v.orders_count>0 THEN v.value_sum / v.orders_count ELSE 0 END as avg_value')
            ->when($search !== '', fn($q)=>$q->where('s.name','LIKE',"%{$search}%"));

        // KPIs
        $kpis = [
            'vendors_count'   => (clone $base)->count(),
            'total_purchases' => (clone $hdr)->count(),
            'total_qty'       => (float) DB::query()->fromSub($qtyAgg,'qq')->sum('qty_sum'),
            'total_spend'     => (float) DB::query()->fromSub($valueAgg,'vv')->sum('value_sum'),
        ];

        // Charts: Top 10 by value & qty
        $topByValue = (clone $base)->orderBy('value_sum','desc')->limit(10)->get();
        $topByQty   = (clone $base)->orderBy('qty_sum','desc')->limit(10)->get();

        // Table (pagination + sorting)
        $perPage  = max(1, (int)($request->get('limit', 10)));
        $page     = max(1, (int)($request->get('page', 1)));
        $offset   = ($page - 1) * $perPage;
        $sortField = $request->get('SortField','value_sum');
        $sortType  = strtolower($request->get('SortType','desc')) === 'asc' ? 'asc' : 'desc';
        $sortable  = ['supplier','orders_count','qty_sum','value_sum','avg_value'];

        $tableTotal = (clone $base)->count();

        $rows = (clone $base)
            ->orderBy(in_array($sortField,$sortable,true)?$sortField:'value_sum', $sortType)
            ->offset($offset)->limit($perPage)
            ->get()
            ->map(function($r){
                return [
                    'supplier_id'   => (int) $r->supplier_id,
                    'supplier'      => $r->supplier,
                    'orders_count'  => (int) $r->orders_count,
                    'qty_sum'       => (float) $r->qty_sum,
                    'value_sum'     => (float) $r->value_sum,
                    'avg_value'     => (float) $r->avg_value,
                ];
            });

        return response()->json([
            'data' => [
                'kpis'        => $kpis,
                'topByValue'  => $topByValue,
                'topByQty'    => $topByQty,
                'rows'        => $rows,
                'totalRows'   => $tableTotal,
                'range'       => ['from'=>$start->toDateString(),'to'=>$end->toDateString()],
            ],
            'warehouses' => $warehouses,
        ]);
    }











}