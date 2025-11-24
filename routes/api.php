<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Store\OnlineOrdersApiController;
use App\Http\Controllers\Api\Store\PagesApiController;
use App\Http\Controllers\Api\Store\BannersApiController;
use App\Http\Controllers\Api\Store\SettingsApiController;
use App\Http\Controllers\Api\Store\CollectionController;
use App\Http\Controllers\Api\Store\SubscriberController;
use App\Http\Controllers\Api\Store\MessageController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

//--------------------------- Reset Password  ---------------------------

Route::group([
    'prefix' => 'password',
], function () {
    Route::post('create', 'PasswordResetController@create');
    Route::post('reset', 'PasswordResetController@reset');
});


Route::post('getAccessToken', 'AuthController@getAccessToken');
Route::get('/get-logo-setting', function () {
    $setting = \App\Models\Setting::first();
    return response()->json([
        'logo' => $setting->logo ?? null,
    ]);
});

Route::get('/translations/{locale}', function ($locale) {
    $translations = \DB::table('translations')
        ->where('locale', $locale)
        ->pluck('value', 'key');

    return response()->json($translations);
});


 Route::get('/languages', 'LanguageController@load_language');

Route::middleware(['auth:api', 'Is_Active'])->group(function () {

    Route::get('/admin/store/settings',  [SettingsApiController::class, 'show']);
    Route::post('/admin/store/settings', [SettingsApiController::class, 'update']);

    Route::get('/store/orders',        [OnlineOrdersApiController::class, 'index']);
    Route::get('/store/orders/{id}',   [OnlineOrdersApiController::class, 'show']);
    Route::patch('/store/orders/{id}', [OnlineOrdersApiController::class, 'update']);

    Route::get('/store/pages',         [PagesApiController::class, 'index']);
    Route::post('/store/pages',        [PagesApiController::class, 'store']);
    Route::get('/store/pages/{id}',    [PagesApiController::class, 'show']);
    Route::put('/store/pages/{id}',    [PagesApiController::class, 'update']);
    Route::delete('/store/pages/{id}', [PagesApiController::class, 'destroy']);

    Route::get('/store/banners',         [BannersApiController::class, 'index']);
    Route::post('/store/banners',        [BannersApiController::class, 'store']);
    Route::get('/store/banners/{id}',    [BannersApiController::class, 'show']);
    Route::put('/store/banners/{id}',    [BannersApiController::class, 'update']);
    Route::delete('/store/banners/{id}', [BannersApiController::class, 'destroy']);

    Route::get('/store/subscribers', [SubscriberController::class, 'index']);
    Route::delete('/store/subscribers/{id}', [SubscriberController::class, 'destroy']);

    Route::get('/store/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/store/messages/{id}', [MessageController::class, 'show'])->name('messages.show');
    Route::patch('/store/messages/{id}/toggle-read', [MessageController::class, 'toggleRead']); // optional
    Route::delete('/store/messages/{id}', [MessageController::class, 'destroy'])->name('messages.destroy');


    // Collections (manual)
    Route::get('/admin/store/collections',                      [CollectionController::class, 'index']);
    Route::post('/admin/store/collections',                     [CollectionController::class, 'store']);
    Route::get('/admin/store/collections/{collection}',         [CollectionController::class, 'show']);
    Route::put('/admin/store/collections/{collection}',         [CollectionController::class, 'update']);
    Route::delete('/admin/store/collections/{collection}',      [CollectionController::class, 'destroy']);
    Route::post('/admin/store/collections/{collection}/products', [CollectionController::class, 'syncProducts']);

    Route::get('/admin/store/products', [CollectionController::class, 'searchProducts']);
    Route::get('/admin/products',       [CollectionController::class, 'searchProducts']);



    Route::get("dashboard_data", "DashboardController@dashboard_data");
    
    Route::get('/retrieve-customer', 'StripeController@retrieveCustomer');
    Route::post('/update-customer-stripe', 'StripeController@updateCustomer');

    Route::get('/languages_setting', 'LanguageController@index');
    Route::post('/languages_setting', 'LanguageController@store');
    Route::put('/languages_setting/{language}', 'LanguageController@update');
    Route::delete('/languages_setting/{language}', 'LanguageController@destroy');
    Route::post('/languages_setting/{id}/set-default', 'LanguageController@setDefault');
    Route::post('/languages_setting/{id}/set-active', 'LanguageController@setLocaleActive');
    Route::post('/languages_setting/set-default/{locale}', 'LanguageController@setDefaultByLocale');

    Route::get('/translations_setting/{locale}', 'LanguageController@get_translate');
    Route::put('/translations_setting/{locale}', 'LanguageController@update_translate');
    Route::put('/translations_setting/{locale}', 'LanguageController@updateOrInsert');
    Route::delete('/translations_setting/{locale}/{key}', 'LanguageController@delete_translate');

    //-------------------------- Clear Cache ---------------------------

    Route::get("clear_cache", "SettingsController@Clear_Cache");

    //------------------------------- subscriptions ------------------------\\

    Route::resource('subscriptions', 'SubscriptionController');
    Route::put('/subscriptions/{id}/status', 'SubscriptionController@updateStatus');

    //------------------------------- error_logs ------------------------\\

    Route::get('/error-logs', 'ErrorLogController@index')->name('error_logs.index');

    //-------------------------- Reports ---------------------------

    Route::get("report/client", "ReportController@Client_Report");
    Route::get("report/client/{id}", "ReportController@Client_Report_detail");
    Route::get("report/client_sales", "ReportController@Sales_Client");
    Route::get("report/client_payments", "ReportController@Payments_Client");
    Route::get("report/client_quotations", "ReportController@Quotations_Client");
    Route::get("report/client_returns", "ReportController@Returns_Client");
    Route::get("report/provider", "ReportController@Providers_Report");
    Route::get("report/provider/{id}", "ReportController@Provider_Report_detail");
    Route::get("report/provider_purchases", "ReportController@Purchases_Provider");
    Route::get("report/provider_payments", "ReportController@Payments_Provider");
    Route::get("report/provider_returns", "ReportController@Returns_Provider");
    Route::get("report/sales", "ReportController@Report_Sales");
    Route::get("report/purchases", "ReportController@Report_Purchases");
    Route::get("report/get_last_sales", "ReportController@Get_last_Sales");
    Route::get("report/stock_alert", "ReportController@Products_Alert");
    Route::get("report/payment_chart", "ReportController@Payment_chart");
    Route::get("report/warehouse_report", "ReportController@Warehouse_Report");
    Route::get("report/sales_warehouse", "ReportController@Sales_Warehouse");
    Route::get("report/quotations_warehouse", "ReportController@Quotations_Warehouse");
    Route::get("report/returns_sale_warehouse", "ReportController@Returns_Sale_Warehouse");
    Route::get("report/returns_purchase_warehouse", "ReportController@Returns_Purchase_Warehouse");
    Route::get("report/expenses_warehouse", "ReportController@Expenses_Warehouse");
    Route::get("report/warhouse_count_stock", "ReportController@Warhouse_Count_Stock");
    Route::get("report/report_today", "ReportController@report_today");
    Route::get("report/count_quantity_alert", "ReportController@count_quantity_alert");
    Route::get("report/profit_and_loss", "ReportController@ProfitAndLoss");
    Route::get("report/report_dashboard", "ReportController@report_dashboard");
    Route::get("report/top_products", "ReportController@report_top_products");
    Route::get("report/top_customers", "ReportController@report_top_customers");
    Route::get("report/product_report", "ReportController@product_report");
    Route::get("report/sale_products_details", "ReportController@sale_products_details");
    Route::get("report/product_sales_report", "ReportController@product_sales_report");
    Route::get("report/product_purchases_report", "ReportController@product_purchases_report");

    Route::get("report/users", "ReportController@users_Report");
    Route::get("report/stock", "ReportController@stock_Report");
    Route::get("report/get_sales_by_user", "ReportController@get_sales_by_user");
    Route::get("report/get_quotations_by_user", "ReportController@get_quotations_by_user");
    Route::get("report/get_sales_return_by_user", "ReportController@get_sales_return_by_user");
    Route::get("report/get_purchases_by_user", "ReportController@get_purchases_by_user");
    Route::get("report/get_purchase_return_by_user", "ReportController@get_purchase_return_by_user");
    Route::get("report/get_transfer_by_user", "ReportController@get_transfer_by_user");
    Route::get("report/get_adjustment_by_user", "ReportController@get_adjustment_by_user");

    Route::get("report/get_sales_by_product", "ReportController@get_sales_by_product");
    Route::get("report/get_quotations_by_product", "ReportController@get_quotations_by_product");

    Route::get("report/get_sales_return_by_product", "ReportController@get_sales_return_by_product");
    Route::get("report/get_purchases_by_product", "ReportController@get_purchases_by_product");
    Route::get("report/get_purchase_return_by_product", "ReportController@get_purchase_return_by_product");
    Route::get("report/get_transfer_by_product", "ReportController@get_transfer_by_product");
    Route::get("report/get_adjustment_by_product", "ReportController@get_adjustment_by_product");
    Route::get("report/client_pdf/{id}", "ReportController@download_report_client_pdf");
    Route::get("report/provider_pdf/{id}", "ReportController@download_report_provider_pdf");

    Route::get("report/inventory_valuation_summary", "ReportController@inventory_valuation_summary");
    Route::get("report/expenses_report", "ReportController@expenses_report");
    Route::get("report/deposits_report", "ReportController@deposits_report");
    Route::get("report/report_transactions", "ReportController@report_transactions");
    Route::get("report/sales_by_category_report", "ReportController@sales_by_category_report");
    Route::get("report/sales_by_brand_report", "ReportController@sales_by_brand_report");
    Route::get("report/seller_report", "ReportController@seller_report");
    Route::get('report/inactive_customers', 'ReportController@inactiveCustomers');
    Route::get('report/zero_sales_products', 'ReportController@zeroSalesProducts');
    Route::get('report/dead_stock', 'ReportController@deadStock');
    Route::get('report/draft_invoices', 'ReportController@draftInvoices');
    Route::get('report/discount_summary', 'ReportController@discountSummary');
    Route::get('report/tax_summary', 'ReportController@taxSummary');
    Route::get('report/stock_aging', 'ReportController@stockAging');
    Route::get('report/stock_aging/filters','ReportController@stockAgingFilters');
    Route::get('report/stock_transfer', 'ReportController@stockTransferReport');
    Route::get('report/stock_adjustment', 'ReportController@stockAdjustmentReport');
    Route::get('report/top_suppliers', 'ReportController@topSuppliersReport');








    //------------------------------- payment_methods ------------------------\\
    //------------------------------------------------------------------\\    
    Route::resource('payment_methods', 'PaymentMethodController');

    //------------------------------Employee------------------------------------\\

    Route::resource('employees', 'hrm\EmployeesController');
    Route::post('employees/import/csv', 'hrm\EmployeesController@import_employees');
    Route::post('employees/delete/by_selection', 'hrm\EmployeesController@delete_by_selection');
    Route::get("get_employees_by_department", "hrm\EmployeesController@Get_employees_by_department");
    Route::put("update_social_profile/{id}", "hrm\EmployeesController@update_social_profile");
    Route::get("get_experiences_by_employee", "hrm\EmployeesController@get_experiences_by_employee");
    Route::get("get_accounts_by_employee", "hrm\EmployeesController@get_accounts_by_employee");
    Route::get("Get_employees_by_company", "hrm\EmployeesController@Get_employees_by_company");

    //------------------------------- Employee Experience ----------------\\
    //--------------------------------------------------------------------\\
    
    Route::resource('work_experience', 'hrm\EmployeeExperienceController');


    //------------------------------- Employee Accounts bank ----------------\\
    //--------------------------------------------------------------------\\
    
    Route::resource('employee_account', 'hrm\EmployeeAccountController');


     //------------------------------- company --------------------------\\
    //--------------------------------------------------------------------\\
    Route::resource('company', 'hrm\CompanyController');
    Route::get("get_all_company", "hrm\CompanyController@Get_all_Company");
    Route::post("company/delete/by_selection", "hrm\CompanyController@delete_by_selection");


     //------------------------------- departments --------------------------\\
    //--------------------------------------------------------------------\\
    Route::resource('departments', 'hrm\DepartmentsController');
    Route::get("get_all_departments", "hrm\DepartmentsController@Get_all_Departments");
    Route::get("get_departments_by_company", "hrm\DepartmentsController@Get_departments_by_company")->name('Get_departments_by_company');
    Route::post("departments/delete/by_selection", "hrm\DepartmentsController@delete_by_selection");

    //------------------------------- designations --------------------------\\
    //--------------------------------------------------------------------\\
    Route::resource('designations', 'hrm\DesignationsController');
    Route::get("get_designations_by_department", "hrm\DesignationsController@Get_designations_by_department");
    Route::post("designations/delete/by_selection", "hrm\DesignationsController@delete_by_selection");

    //------------------------------- office_shift ------------------\\
    //----------------------------------------------------------------\\

    Route::resource('office_shift', 'hrm\OfficeShiftController');
    Route::post("office_shift/delete/by_selection", "hrm\OfficeShiftController@delete_by_selection");

    //------------------------------- Attendances ------------------------\\
    //--------------------------------------------------------------------\\
    Route::resource('attendances', 'hrm\AttendancesController');
    Route::get("daily_attendance", "hrm\AttendancesController@daily_attendance")->name('daily_attendance');
    Route::post('attendance_by_employee/{id}', 'hrm\EmployeeSessionController@attendance_by_employee')->name('attendance_by_employee.post');
    Route::post("attendances/delete/by_selection", "hrm\AttendancesController@delete_by_selection");


    
    //------------------------------- Request leave  -----------------------\\
    //----------------------------------------------------------------\\

    Route::resource('leave', 'hrm\LeaveController');
    Route::resource('leave_type', 'hrm\LeaveTypeController');
    Route::post("leave/delete/by_selection", "hrm\LeaveController@delete_by_selection");
    Route::post("leave_type/delete/by_selection", "hrm\LeaveTypeController@delete_by_selection");


     //------------------------------- holiday ----------------------\\
    //----------------------------------------------------------------\\

    Route::resource('holiday', 'hrm\HolidayController');
    Route::post("holiday/delete/by_selection", "hrm\HolidayController@delete_by_selection");

      //------------------------------- payroll ----------------------\\
    //----------------------------------------------------------------\\

    Route::resource('payroll', 'hrm\PayrollController');

    //------------------------------- core --------------------------\\
    //--------------------------------------------------------------------\\

    Route::prefix('core')->group(function () {

       Route::get("get_departments_by_company", "hrm\CoreController@Get_departments_by_company");
       Route::get("get_designations_by_department", "hrm\CoreController@Get_designations_by_department");
       Route::get("get_office_shift_by_company", "hrm\CoreController@Get_office_shift_by_company");
       Route::get("get_employees_by_company", "hrm\CoreController@Get_employees_by_company");

    });


    //------------------------------- CLIENTS --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('clients', 'ClientController');
    Route::post('customers/import', 'ClientController@import');
    Route::get('get_clients_without_paginate', 'ClientController@Get_Clients_Without_Paginate');
    Route::post('clients/delete/by_selection', 'ClientController@delete_by_selection');
    Route::post('clients_pay_due', 'ClientController@clients_pay_due');
    Route::post('clients_pay_return_due', 'ClientController@pay_sale_return_due');
    Route::get('get_client_store_data/{id}', 'ClientController@get_client_store_data');
    Route::get('get_points_client/{id}', 'ClientController@getPoints');
    Route::post('customers/{id}/update-points', 'ClientController@updatePoints');


    // Customer Ledger (separate endpoints)
    Route::get('/sales_client',    'ClientController@salesByClient');
    Route::get('/payments_client', 'ClientController@paymentsByClient');
    Route::get('/quotations_client', 'ClientController@quotationsByClient');
    Route::get('/returns_client',     'ClientController@returnsByClient');

    // Basic client info for header (optional but recommended)
    Route::get('clients/{id}/brief', 'ClientController@clientBrief');
    Route::get('/client_ledger_pdf', 'ClientController@export');


    //------------------------------- CLIENTS Ecommerce--------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('clients_without_ecommerce', 'ClientsEcommerceController');

    //------------------------------- Providers --------------------------\\
    //--------------------------------------------------------------------\\

    Route::resource('providers', 'ProvidersController');
    Route::post('suppliers/import', 'ProvidersController@import');

    Route::post('providers/delete/by_selection', 'ProvidersController@delete_by_selection');
    Route::post('pay_supplier_due', 'ProvidersController@pay_supplier_due');
    Route::post('pay_purchase_return_due', 'ProvidersController@pay_purchase_return_due');

    //---------------------- POS (point of sales) ----------------------\\
    //------------------------------------------------------------------\\

    Route::post('pos/create_pos', 'PosController@CreatePOS');
    Route::get('pos/get_products_pos', 'PosController@GetProductsByParametre');
    Route::get('pos/data_create_pos', 'PosController@GetELementPos');

    //----------------------Draft -------------------------------------\\
    //------------------------------------------------------------------\\
    Route::post('pos/create_draft', 'PosController@CreateDraft');
    Route::get('get_draft_sales', 'PosController@get_draft_sales');
    Route::delete('remove_draft_sale/{id}', 'PosController@remove_draft_sale');
    Route::get('pos/data_draft_convert_sale/{id}', 'PosController@data_draft_convert_sale');
    Route::post('pos/submit_sale_from_draft', 'PosController@submit_sale_from_draft');


       //------------------------------- Project -----------------------\\
            //----------------------------------------------------------------\\

            Route::resource('projects', 'ProjectController');

            Route::post("projects/delete/by_selection", "ProjectController@delete_by_selection");
            Route::post("project_discussions", "ProjectController@Create_project_discussions");
            Route::delete("project_discussions/{id}", "ProjectController@destroy_project_discussion");

            Route::post("project_issues", "ProjectController@Create_project_issues");
            Route::put("project_issues/{id}", "ProjectController@Update_project_issues");
            Route::delete("project_issues/{id}", "ProjectController@destroy_project_issues");

            Route::post("project_documents", "ProjectController@Create_project_documents");
            Route::delete("project_documents/{id}", "ProjectController@destroy_project_documents");

            //------------------------------- Task -----------------------\\
            //----------------------------------------------------------------\\

            Route::resource('tasks', 'TaskController');
            Route::put("update_task_status/{id}", "TaskController@update_task_status");

            Route::post("tasks/delete/by_selection", "TaskController@delete_by_selection");
            Route::get("tasks_kanban", "TaskController@tasks_kanban")->name('tasks_kanban');
            Route::post("task_change_status", "TaskController@task_change_status")->name('task_change_status');

            Route::post("task_discussions", "TaskController@Create_task_discussions");
            Route::delete("task_discussions/{id}", "TaskController@destroy_task_discussion");

            Route::post("task_documents", "TaskController@Create_task_documents");
            Route::delete("task_documents/{id}", "TaskController@destroy_task_documents");

            

    //------------------------------- PRODUCTS --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('products', 'ProductsController');
    Route::post('products/import/single',   'ProductsController@import_single_products')->middleware('auth:api');
    Route::post('products/import/variants', 'ProductsController@import_variant_products')->middleware('auth:api');

    Route::get('get_Products_by_warehouse/{id}', 'ProductsController@Products_by_Warehouse');
    Route::get('get_product_detail/{id}', 'ProductsController@Get_Products_Details');
    Route::get('get_products_stock_alerts', 'ProductsController@Products_Alert');
    Route::get('barcode_create_page', 'ProductsController@Get_element_barcode');
    Route::post('products/delete/by_selection', 'ProductsController@delete_by_selection');
    Route::get('show_product_data/{id}/{variant_id}', 'ProductsController@show_product_data');
    Route::get('get_products_materiels', 'ProductsController@get_products_materiels')->name('get_products_materiels');

    Route::get('opening-stock/import/meta',       'ProductsController@opening_stock_meta');
    Route::post('opening-stock/import/single',    'ProductsController@opening_stock_import_single');
    Route::post('opening-stock/import/variants',  'ProductsController@opening_stock_import_variants');



     //---- count stock ----------
     Route::get('count_stock', 'ProductsController@count_stock_list');
     Route::post('store_count_stock', 'ProductsController@store_count_stock');


    //------------------------------- Category --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('categories', 'CategorieController');
    Route::post('categories/delete/by_selection', 'CategorieController@delete_by_selection');

    //------------------------------- Units --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('units', 'UnitsController');
    Route::get('get_sub_units_by_base', 'UnitsController@Get_Units_SubBase');
    Route::get('get_units', 'UnitsController@Get_sales_units');

    //------------------------------- Brands--------------------------\\
    //------------------------------------------------------------------\\
    Route::resource('brands', 'BrandsController');
    Route::post('brands/delete/by_selection', 'BrandsController@delete_by_selection');

    //------------------------------- Currencies --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('currencies', 'CurrencyController');
    Route::post('currencies/delete/by_selection', 'CurrencyController@delete_by_selection');


    //------------------------------- WAREHOUSES --------------------------\\

    Route::resource('warehouses', 'WarehouseController');
    Route::post('warehouses/delete/by_selection', 'WarehouseController@delete_by_selection');

    //------------------------------- PURCHASES --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('purchases', 'PurchasesController');
    Route::get('get_payments_by_purchase/{id}', 'PurchasesController@Get_Payments');
    Route::post('purchase_send_email', 'PurchasesController@Send_Email');
    Route::post('purchase_send_sms', 'PurchasesController@Send_SMS');
    Route::post('purchases_delete_by_selection', 'PurchasesController@delete_by_selection');
    Route::get('get_Products_by_purchase/{id}', 'PurchasesController@get_Products_by_purchase');
    Route::post('purchase_send_whatsapp', 'PurchasesController@purchase_send_whatsapp');
    Route::post('purchases/scan-barcode', 'PurchasesController@scanBarcode');

    
    Route::get('get_import_purchases', 'PurchasesController@get_import_purchases');
    Route::post('store_import_purchases', 'PurchasesController@store_import_purchases');
    //------------------------------- Payments  Purchases --------------------------\\
    //------------------------------------------------------------------------------\\

    Route::resource('payment_purchase', 'PaymentPurchasesController');
    Route::get('payment_purchase_get_number', 'PaymentPurchasesController@getNumberOrder');
    Route::post('payment_purchase_send_email', 'PaymentPurchasesController@SendEmail');
    Route::post('payment_purchase_send_sms', 'PaymentPurchasesController@Send_SMS');

    //-------------------------------  Sales --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('sales', 'SalesController');
    Route::get('convert_to_sale_data/{id}', 'SalesController@Elemens_Change_To_Sale');
    Route::get('get_payments_by_sale/{id}', 'SalesController@Payments_Sale');
    Route::post('sales_send_email', 'SalesController@Send_Email');
    Route::post('sales_send_sms', 'SalesController@Send_SMS');
    Route::post('sales_delete_by_selection', 'SalesController@delete_by_selection');
    Route::get('get_Products_by_sale/{id}', 'SalesController@get_Products_by_sale');
    Route::post('sales_send_whatsapp', 'SalesController@sales_send_whatsapp');
    Route::get('get_today_sales', 'SalesController@get_today_sales');

    //-------------------------------  Shipments --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('shipments', 'ShipmentController');


    //------------------------------- Payments  Sales --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('payment_sale', 'PaymentSalesController');
    Route::get('payment_sale_get_number', 'PaymentSalesController@getNumberOrder');
    Route::post('payment_sale_send_email', 'PaymentSalesController@SendEmail');
    Route::post('payment_sale_send_sms', 'PaymentSalesController@Send_SMS');

    //------------------------------- Expenses --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('expenses', 'ExpensesController');
    Route::post('expenses_delete_by_selection', 'ExpensesController@delete_by_selection');


    //------------------------------- Expenses Category--------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('expenses_category', 'CategoryExpenseController');
    Route::post('expenses_category_delete_by_selection', 'CategoryExpenseController@delete_by_selection');

     //------------------------------- Accounts --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('accounts', 'AccountController');
    Route::post('accounts_delete_by_selection', 'AccountController@delete_by_selection');

      //------------------------------- TransferMoneyController --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('transfer_money', 'TransferMoneyController');


    //------------------------------- Deposits --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('deposits', 'DepositsController');
    Route::post('deposits_delete_by_selection', 'DepositsController@delete_by_selection');


    //------------------------------- deposits Category--------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('deposits_category', 'CategoryDepositController');
    Route::post('deposits_category_delete_by_selection', 'CategoryDepositController@delete_by_selection');


    //------------------------------- Quotations --------------------------\\
    //------------------------------------------------------------------\\
    Route::resource('quotations', 'QuotationsController');
    Route::post('quotations_send_email', 'QuotationsController@SendEmail');
    Route::post('quotations_send_sms', 'QuotationsController@Send_SMS');
    Route::post('quotations_delete_by_selection', 'QuotationsController@delete_by_selection');
    Route::post('quotation_send_whatsapp', 'QuotationsController@quotation_send_whatsapp');

    //------------------------------- Sales Return --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('returns/sale', 'SalesReturnController');
    Route::post('returns/sale/send/email', 'SalesReturnController@Send_Email');
    Route::post('returns/sale/send/sms', 'SalesReturnController@Send_SMS');
    Route::get('returns/sale/payment/{id}', 'SalesReturnController@Payment_Returns');
    Route::post('returns/sale/delete/by_selection', 'SalesReturnController@delete_by_selection');
    Route::get('returns/sale/create_sell_return/{id}', 'SalesReturnController@create_sell_return');
    Route::get('returns/sale/edit_sell_return/{id}/{sale_id}', 'SalesReturnController@edit_sell_return');

    //------------------------------- Purchases Return --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('returns/purchase', 'PurchasesReturnController');
    Route::post('returns/purchase/send/email', 'PurchasesReturnController@Send_Email');
    Route::post('returns/purchase/send/sms', 'PurchasesReturnController@Send_SMS');
    Route::get('returns/purchase/payment/{id}', 'PurchasesReturnController@Payment_Returns');
    Route::post('returns/purchase/delete/by_selection', 'PurchasesReturnController@delete_by_selection');
    Route::get('returns/purchase/create_purchase_return/{id}', 'PurchasesReturnController@create_purchase_return');
    Route::get('returns/purchase/edit_purchase_return/{id}/{purchase_id}', 'PurchasesReturnController@edit_purchase_return');
    
    //------------------------------- Payment Sale Returns --------------------------\\
    //--------------------------------------------------------------------------------\\

    Route::resource('payment/returns_sale', 'PaymentSaleReturnsController');
    Route::get('payment/returns_sale/Number/order', 'PaymentSaleReturnsController@getNumberOrder');
    Route::post('payment/returns_sale/send/email', 'PaymentSaleReturnsController@SendEmail');
    Route::post('payment/returns_sale/send/sms', 'PaymentSaleReturnsController@Send_SMS');

    //------------------------------- Payments Purchase Returns --------------------------\\
    //---------------------------------------------------------------------------------------\\

    Route::resource('payment/returns_purchase', 'PaymentPurchaseReturnsController');
    Route::get('payment/returns_purchase/Number/Order', 'PaymentPurchaseReturnsController@getNumberOrder');
    Route::post('payment/returns_purchase/send/email', 'PaymentPurchaseReturnsController@SendEmail');
    Route::post('payment/returns_purchase/send/sms', 'PaymentPurchaseReturnsController@Send_SMS');

    //------------------------------- Adjustments --------------------------\\
    //------------------------------------------------------------------\\

    Route::resource('adjustments', 'AdjustmentController');
    Route::get('adjustments/detail/{id}', 'AdjustmentController@Adjustment_detail');
    Route::post('adjustments/delete/by_selection', 'AdjustmentController@delete_by_selection');

    //------------------------------- Transfers --------------------------\\
    //--------------------------------------------------------------------\\
    Route::resource('transfers', 'TransferController');
    Route::post('transfers/delete/by_selection', 'TransferController@delete_by_selection');

    //------------------------------- Users --------------------------\\
    //------------------------------------------------------------------\\

    Route::get('get_user_auth', 'UserController@GetUserAuth');
    Route::resource('users', 'UserController');
    Route::put('users_switch_activated/{id}', 'UserController@IsActivated');
    Route::get('Get_user_profile', 'UserController@GetInfoProfile');
    Route::put('update_user_profile/{id}', 'UserController@updateProfile');

    //------------------------------- Permission Groups user -----------\\
    //------------------------------------------------------------------\\

    Route::resource('roles', 'PermissionsController');
    Route::resource('roles/check/create_page', 'PermissionsController@Check_Create_Page');
    Route::post('roles/delete/by_selection', 'PermissionsController@delete_by_selection');

    
    //------------------------------- Settings ------------------------\\
    //------------------------------------------------------------------\\    
    Route::resource('settings', 'SettingsController');
    Route::get('get_Settings_data', 'SettingsController@getSettings');
    Route::put('pos_settings/{id}', 'SettingsController@update_pos_settings');
    Route::get('get_pos_Settings', 'SettingsController@get_pos_Settings');

    //------------------------------- appearance_settings ------------------------\\
    //------------------------------------------------------------------\\   

    Route::get('get_appearance_settings', 'SettingsController@get_appearance_settings');
    Route::put('update_appearance_settings/{id}', 'SettingsController@update_appearance_settings');

    //------------------------------- Mail Settings ------------------------\\

    Route::put('update_config_mail/{id}', 'MailSettingsController@update_config_mail');
    Route::get('get_config_mail', 'MailSettingsController@get_config_mail');

    //------------------------------- SMS Settings ------------------------\\

    Route::get('get_sms_config', 'Sms_SettingsController@get_sms_config');
    Route::post('update_twilio_config', 'Sms_SettingsController@update_twilio_config');
    Route::post('update_nexmo_config', 'Sms_SettingsController@update_nexmo_config');
    Route::post('update_infobip_config', 'Sms_SettingsController@update_infobip_config');
    Route::post('update_termi_config', 'Sms_SettingsController@update_termi_config');

    Route::put('update_Default_SMS', 'Sms_SettingsController@update_Default_SMS');

    // notifications_template
    Route::get('get_sms_template', 'Notifications_Template@get_sms_template');
    Route::put('update_sms_body', 'Notifications_Template@update_sms_body');

    Route::get('get_emails_template', 'Notifications_Template@get_emails_template');
    Route::put('update_custom_email', 'Notifications_Template@update_custom_email');


    //------------------------------- Payment_gateway Settings ------------------------\\

    Route::post('payment_gateway', 'Payment_gateway_SettingsController@Update_payment_gateway');
    Route::get('get_payment_gateway', 'Payment_gateway_SettingsController@Get_payment_gateway');

    //------------------------------- Update Settings ------------------------\\

    Route::get('get_version_info', 'UpdateController@get_version_info');
    
    //------------------------------- Backup --------------------------\\
    //------------------------------------------------------------------\\
    
    Route::get("get_backup", "BackupController@Get_Backup");
    Route::get("generate_new_backup", "BackupController@Generate_Backup");
    Route::delete("delete_backup/{name}", "BackupController@Delete_Backup");


    //------------------------------- Module Settings ------------------------\\

    Route::get('get_modules_info', 'ModuleSettingsController@get_modules_info');
    Route::post('update_status_module', 'ModuleSettingsController@update_status_module');
    Route::post('upload_module', 'ModuleSettingsController@upload_module');

});

    //-------------------------------  Print & PDF ------------------------\\
    //------------------------------------------------------------------\\

    Route::get('sale_pdf/{id}', 'SalesController@Sale_PDF');
    Route::get('quote_pdf/{id}', 'QuotationsController@Quotation_pdf');
    Route::get('purchase_pdf/{id}', 'PurchasesController@Purchase_pdf');
    Route::get('return_sale_pdf/{id}', 'SalesReturnController@Return_pdf');
    Route::get('return_purchase_pdf/{id}', 'PurchasesReturnController@Return_pdf');
    Route::get('payment_purchase_pdf/{id}', 'PaymentPurchasesController@Payment_purchase_pdf');
    Route::get('payment_return_sale_pdf/{id}', 'PaymentSaleReturnsController@payment_return');
    Route::get('payment_return_purchase_pdf/{id}', 'PaymentPurchaseReturnsController@payment_return');
    Route::get('payment_sale_pdf/{id}', 'PaymentSalesController@payment_sale');
    Route::get('sales_print_invoice/{id}', 'SalesController@Print_Invoice_POS');
    Route::get('transfer_pdf/{id}', 'TransferController@transfer_pdf');
    Route::get('adjustment_pdf/{id}', 'AdjustmentController@adjustment_pdf');


    // Route::get('/available-modules', 'ModuleSettingsController@get_modules_enabled');
