<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Cookie;
use Laravel\passport\Passport;
use App\Http\Controllers\Admin\StoreSettingsController as AdminStoreSettings;
use App\Http\Controllers\StoreFrontController;
use App\Http\Controllers\StoreAuthController;
use App\Http\Controllers\Api\Store\CheckoutController;
use App\Http\Controllers\Api\Store\AccountPagesController;
use App\Http\Controllers\Api\Store\MyOrdersApiController;
use App\Http\Controllers\Api\Store\NewsletterController;
use App\Http\Controllers\Api\Store\MessageController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */

//------------------------------------------------------------------\\
// Passport::routes();

Route::post('/login', [
    'uses' => 'Auth\LoginController@login',
    'middleware' => 'Is_Active',
]);

Route::get('password/find/{token}', 'PasswordResetController@find');




Route::middleware(['web','auth:web','Is_Active'])->group(function () {
    Route::get('/admin/store/settings', [AdminStoreSettings::class, 'show']);
    Route::post('/admin/store/settings', [AdminStoreSettings::class, 'update']);
});




$installed = Storage::disk('public')->exists('installed');

//------------------------------------------------------------------\\
// ONLINE STORE ROUTES (Only if installed)
if ($installed === true) {


    Route::prefix('online_store')->group(function () {


        Route::get('/lang/{locale}', function ($locale) {
            $supported = ['en','fr','es','ar'];

            // Use provided locale if supported, otherwise fallback to 'en'
            $chosen = in_array($locale, $supported, true) ? $locale : 'en';

            // Store in session
            session(['locale' => $chosen]);

            // Optionally persist for a year via cookie
            Cookie::queue('locale', $chosen, 60 * 24 * 365, '/');

            return back();
        })->name('lang.switch');

        Route::get('/', [StoreFrontController::class, 'index'])->name('store.index');
        Route::get('/shop', [StoreFrontController::class, 'shop'])->name('store.shop');
        Route::get('/contact', [StoreFrontController::class, 'contact'])->name('store.contact');
        Route::post('/contact', [StoreFrontController::class, 'sendContact'])->name('store.contact.send');
        Route::post('/store/orders', [CheckoutController::class, 'store'])->name('store.orders.store');
        Route::get('/collections/{slug}', [StoreFrontController::class, 'collection'])->name('store.collection.show');

        Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');
        Route::post('/contact/send', [MessageController::class, 'store'])->name('store.contact.send');

        // Account pages (require login on 'store' guard)
        Route::middleware(['web', 'auth:store'])->group(function () {
            Route::view('/checkout', 'store.checkout')->name('checkout');
            Route::view('/thank-you', 'store.thank-you')->name('store.thankyou');


            Route::get('/account', [AccountPagesController::class, 'account'])->name('account');

            Route::put('/account', [AccountPagesController::class, 'update'])->name('account.update');

            Route::get('/account/orders', [AccountPagesController::class, 'orders'])
                ->name('account.orders');

            Route::get('/account/orders/{id}', function ($id) {
                // $s is likely shared via view composer; if not, fetch StoreSetting here
                return view('store.order-show', ['id' => $id]);
            })->name('account.order.show');

            // Customer's own orders (JSON for the account orders table)
            Route::get('/my/orders', [MyOrdersApiController::class, 'index'])
                ->name('my_orders.index');
            // (Optional) details endpoint if you add a “view” drawer/page:
            Route::get('/my/orders/{id}', [MyOrdersApiController::class, 'show'])
                ->name('my_orders.show');
        });

        // Auth pages (only for guests of 'store' guard)
        Route::middleware('guest:store')->group(function () {
            Route::get('/login', [StoreAuthController::class, 'showLogin'])->name('store.login.show');
            Route::post('/login', [StoreAuthController::class, 'login'])->name('store.login');

            Route::get('/register', [StoreAuthController::class, 'showRegister'])->name('store.register.show');
            Route::post('/register', [StoreAuthController::class, 'register'])->name('store.register');

            });

        // Logout (must be logged in on 'store')
        Route::post('/logout', [StoreAuthController::class, 'logout'])
            ->middleware('auth:store')->name('store.logout');

    });


} else {
    // if not installed: redirect all /online_store requests to /setup
    Route::any('/online_store/{any?}', function () {
        return redirect('/setup');
    })->where('any', '.*');
}


//------------------------------------------------------------------\\

$installed = Storage::disk('public')->exists('installed');

if ($installed === false) {
    Route::get('/setup', [
        'uses' => 'SetupController@viewCheck',
    ])->name('setup');

    Route::get('/setup/step-1', [
        'uses' => 'SetupController@viewStep1',
    ]);

    Route::post('/setup/step-2', [
        'as' => 'setupStep1', 'uses' => 'SetupController@setupStep1',
    ]);

    Route::post('/setup/testDB', [
        'as' => 'testDB', 'uses' => 'TestDbController@testDB',
    ]);

    Route::get('/setup/step-2', [
        'uses' => 'SetupController@viewStep2',
    ]);

    Route::get('/setup/step-3', [
        'uses' => 'SetupController@viewStep3',
    ]);

    Route::get('/setup/finish', function () {

        return view('setup.finishedSetup');
    });

    Route::get('/setup/getNewAppKey', [
        'as' => 'getNewAppKey', 'uses' => 'SetupController@getNewAppKey',
    ]);

    Route::get('/setup/getPassport', [
        'as' => 'getPassport', 'uses' => 'SetupController@getPassport',
    ]);

    Route::get('/setup/getMegrate', [
        'as' => 'getMegrate', 'uses' => 'SetupController@getMegrate',
    ]);

    Route::post('/setup/step-3', [
        'as' => 'setupStep2', 'uses' => 'SetupController@setupStep2',
    ]);

    Route::post('/setup/step-4', [
        'as' => 'setupStep3', 'uses' => 'SetupController@setupStep3',
    ]);

    Route::post('/setup/step-5', [
        'as' => 'setupStep4', 'uses' => 'SetupController@setupStep4',
    ]);

    Route::post('/setup/lastStep', [
        'as' => 'lastStep', 'uses' => 'SetupController@lastStep',
    ]);

    Route::get('setup/lastStep', function () {
        return redirect('/setup', 301);
    });

} else {
    Route::any('/setup/{vue}', function () {
        abort(403);
    });
}

//------------------------------------------------------------------\\

Route::group(['middleware' => ['web', 'auth:web', 'Is_Active']], function () {

    Route::get('/login', function () {
        $installed = Storage::disk('public')->exists('installed');
        if ($installed === false) {
            return redirect('/setup');
        } else {
            return redirect('/login');
        }
    });


    Route::get('/{vue?}',
      function () {
        $installed = Storage::disk('public')->exists('installed');

        if ($installed === false) {
            return redirect('/setup');
        } else {
            return view('layouts.master');
        }
    })->where('vue', '^(?!api|setup|update|password|online_store).*$');
 
});
   
    Auth::routes([
        'register' => false,
    ]);


    

//------------------------- -UPDATE ----------------------------------------\\

Route::group(['middleware' => ['web', 'auth:web', 'Is_Active']], function () {

    Route::get('/update', 'UpdateController@viewStep1');

    Route::get('/update/finish', function () {

        return view('update.finishedUpdate');
    });

    Route::post('/update/lastStep', [
        'as' => 'update_lastStep', 'uses' => 'UpdateController@lastStep',
    ]);

});




