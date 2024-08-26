<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Seller\VendorAuthController;
use App\Http\Controllers\Customer\HomePageController;
use App\Http\Controllers\PushNotifications;
use Illuminate\Support\Facades\Route;

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

Route::group([
    'middleware' => ['guest']
], function() {
    // superadmin, admin, seller login
    Route::get('admin/login', [AuthController::class, 'login_admin'])
        ->name('admin.login');

    Route::post('login_action', [AuthController::class, 'login_action'])
        ->name('login_action');


    Route::get('seller/login', [AuthController::class, 'login_seller'])
        ->name('seller.login');
    
    Route::get('customer/login', [AuthController::class, 'login_customer'])
    ->name('customer.login');
    
    Route::post('customer/send-otp-for-login', [AuthController::class, 'send_otp_for_login'])
    ->name('customer.send_otp_for_login');
    
    Route::post('customer/otp-verify', [AuthController::class, 'otp_for_verify'])
    ->name('customer.otp_for_verify');

    Route::get('/',[HomePageController::class,'index'])->name('home');
    Route::get('/',[HomePageController::class,'index'])->name('login');

    // Route::post('login_seller_action', [AuthController::class, 'login_seller_action'])
    //     ->name('login_seller_action');    
});

Route::group([
    'middleware' => ['auth']
], function() {
    
    Route::post('logout', [AuthController::class, 'logout'])
        ->name('logout');
    Route::post('customer/logout', [AuthController::class, 'logout'])
        ->name('customer.logout');
    Route::post('seller/logout', [VendorAuthController::class, 'logout'])
        ->name('seller.logout');
    Route::get('searchshop',[HomePageController::class,'searchShope'])->name('searchshop');
});

Route::group([
    'prefix' => 'admin',
    'as' => 'admin.'
], base_path('routes/web/admin.php'));

Route::group([
    'prefix' => 'seller',
    'as' => 'seller.'
], base_path('routes/web/seller.php'));

Route::group([
    // 'prefix' => 'customer',
    // 'as' => 'customer.'
], base_path('routes/web/customer.php'));

//Route::group([], base_path('routes/web/web.php'));

// Route::get('/{any?}', function () {
//     return view('customer.index');
// })->where('any', '^(?!api\/)[\/\w\.\,-]*');

// Route::get('/',[HomePageController::class,'index']);
Route::get('push',[HomePageController::class,'push'])->name('web.push');