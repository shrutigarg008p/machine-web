<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AdsController;
// use App\Http\Controllers\Admin\VerifyController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\ShopController;
use App\Http\Controllers\Customer\ResetPasswordController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ContactUsController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\OrderController;


// Route::get('/verify',[VerifyController::class, 'getVerify'])->name('getverify');
// Route::post('/verify',[VerifyController::class, 'postVerify'])->name('verify');

Route::middleware(['auth', 'role:superadmin|admin'])->group( function() {
    

    Route::get('content_manager', [ContentController::class, 'index'])
        ->name('content_manager.index');
    Route::get('content/{slug}/edit', [ContentController::class, 'edit'])
        ->name('content_manager.edit');
    Route::post('content/{slug}/update', [ContentController::class, 'update'])
        ->name('content_manager.update');
               

    Route::get('/', [AdminController::class, 'index'])
        ->name('index');

    Route::get('settings', [AdminController::class, 'settings'])
        ->name('settings'); 
        Route::post('changepassword', [AdminController::class, 'changePassword'])
            ->name('changePassword');

    Route::resource('users', UserController::class);
    Route::get('user/verify/{token}',[UserController::class,'verifylink'])
        ->name('verify.email.link');
    Route::post('users/status/{user}/{type}', [UserController::class, 'update']);
    
    Route::get('/seller/create',[UserController::class,'sellerCreate'])->name('users.seller_create');

    Route::get('/systemusers',[UserController::class,'system_users'])->name('users.systemUsers');
    Route::get('createsystemuser',[UserController::class,'createSystemUser'])->name('users.createsystemuser');
    Route::post('storesystemuser',[UserController::class,'storesystemuser'])->name('users.storesystemuser');
    Route::post('sendPasswordResetLink',[UserController::class,'sendPasswordResetLink'])->name('users.sendPasswordResetLink');
    Route::post('importusers', [UserController::class,'importUsers'])->name('importusers');

    Route::resource('ads',AdsController::class);

       Route::get('changepassword', [AdminController::class, 'change_view'])
            ->name('changeview');
        Route::post('changeadminpassword', [AdminController::class, 'changeAdminPassword'])
            ->name('changepassword');

    Route::resource('product_category', ProductCategoryController::class)
        ->parameters(['product_category' => 'us_product_category']);
        
    Route::post('product_category/update_status', [ProductCategoryController::class, 'update_status'])->name('product_category.update_status');

    Route::resource('product', ProductController::class)
        ->parameters(['product' => 'us_product']);

    Route::post('product/update_status', [ProductController::class, 'update_status'])->name('product.update_status');
    Route::get('forgotpassword/{token}',[ResetPasswordController::class,'checktoken']);
    Route::post('forgetpasswordupdate',[ResetPasswordController::class,'forgetpasswordupdate']);


    #Banner 
    Route::get('banner', [BannerController::class, 'index'])
        ->name('banner.index');
    Route::get('banner/create', [BannerController::class, 'create']);
    Route::post('banner/store', [BannerController::class, 'store']);
    Route::get('banner/{banner}/edit', [BannerController::class, 'edit'])
        ->name('banner.edit');
    Route::post('banner/{banner}/update', [BannerController::class, 'update'])
        ->name('banner.update');
    Route::delete('banner/{banner}', [BannerController::class, 'destroy'])
        ->name('banner.destroy');
    #end
        
    Route::get('shops', [ShopController::class, 'index'])->name('shops.index');

    #User Report
    Route::prefix('reports')->group(function(){
        Route::get('/users/{type}', [ReportController::class, 'userreport'])->name('userreport');
        Route::post('getPdf/{type}/{file}', [ReportController::class,'getuserReport'])->name('downloadPDF');
    });
    #end

    Route::get('contacts/list', [ContactUsController::class, 'index'])->name('contacts.index');
    Route::prefix('complaints')->group(function(){
      Route::get('list', [ComplaintController::class, 'index'])->name('complaints.index');
      // Route::get('show/{complaint}/{user}', [ComplaintController::class, 'show'])->name('complaints.show');
      Route::get('show/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
      Route::get('edit/{complaint}/{user}', [ComplaintController::class, 'edit'])->name('complaints.edit');
      Route::post('update/{complaint}/{user}', [ComplaintController::class, 'update'])->name('complaints.update');
    });


    #orders
    Route::resource('order', OrderController::class);
    #end

    ## Chat Routes
    Route::get('chat', [ChatController::class, 'index'])
        ->name('chat.index');

    Route::post('create_channel', [ChatController::class, 'createChannel'])
        ->name('chat.channel.create');

    Route::get('view_channel/{channel}', [ChatController::class, 'viewChannel'])
        ->name('chat.channel.view');

    Route::post('messages/{channel}', [ChatController::class, 'sendMessage'])
        ->name('chat.channel.messages.send');

    Route::get('messages', [ChatController::class, 'fetchMessages'])
        ->name('chat.message.fetch');

});
Route::get('changeShowStatus', [AdsController::class,'changeShowStatus']);


