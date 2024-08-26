<?php

use App\Http\Controllers\Seller\ChatController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Seller\VendorAuthController;
use App\Http\Controllers\Seller\VendorController;

use App\Http\Controllers\Seller\ForgotPasswordController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\VerifyController;
use App\Http\Controllers\Seller\OrderController;
use App\Http\Controllers\Seller\ShopController;
use App\Http\Controllers\Seller\QuotationController;
use App\Http\Controllers\Seller\NotificationController;


#forgotpassword
Route::get('forgotview', [ForgotPasswordController::class, 'index'])
        ->name('forgotview');
Route::post('forgotpassword', [ForgotPasswordController::class, 'forgotpassword'])
    ->name('forgotpassword');
Route::get('forgotpassword/{token}',[ForgotPasswordController::class,'checktoken']);
Route::post('forgotpasswordupdate',[ForgotPasswordController::class,'forgetpasswordUpdate']);
#end



#goto admin dashbaord
Route::get('/', [VendorAuthController::class, 'index'])
        ->name('index');
#end            
// Route::get('register', [VendorAuthController::class, 'register'])
//         ->name('register');
// Route::post('registered', [VendorAuthController::class, 'registered'])
//         ->name('registered');    

#email verify seller         
Route::get('/verify/{token}',[VendorController::class,'verifylink']);
#end

#seller register
Route::get('create',[VendorController::class,'sellerCreate']);
Route::resource('store', VendorController::class);
#end

#otp verify
Route::get('/unique/{tok}',[VerifyController::class, 'getVerify']);
Route::post('/verify',[VerifyController::class, 'postVerify'])->name('verify');
#end

Route::middleware(['auth', 'role:seller'])->group( function() {

    Route::get('settings', [VendorAuthController::class, 'settings'])
            ->name('settings');
    Route::post('profile-update', [VendorAuthController::class, 'profileUpdate'])->name('profileUpdate');
    #change-password        
    Route::post('changepassword', [VendorAuthController::class, 'changePassword'])->name('changePassword');
  
    #end

    #products
    Route::resource('product', ProductController::class)
        ->parameters(['product' => 'us_product']);
    
    Route::get('product/{category_id}/{shop_id}',[ProductController::class, 'index'])->name('product.category');
    Route::get('product-edit/{product_id}/{shop_id}/{category_id}',[ProductController::class, 'editProduct'])->name('product.edit.category');
    Route::get('my/products',[ProductController::class, 'myProducts'])->name('product.list');

    Route::post('product/update_status', [ProductController::class, 'update_status'])->name('product.update_status');
    #end


    #dashboard
    Route::get('dashboard', [VendorController::class, 'index'])
        ->name('dashboard');
    #end     


    #help and Support
    Route::get('help-and-supports', [VendorController::class, 'helpAndSupport'])
        ->name('help_and_support');
    Route::post('help-and-supports-store', [VendorController::class, 'helpAndSupportStore'])
        ->name('help_and_support_store');
    #end     


    #shops        
    Route::resource('shops', ShopController::class);

     Route::get('shop/edit/{shopid}/{user}',[ShopController::class, 'edit'])->name('shop.edit')    ;
     Route::put('shop/update/{shopid}/{user}',[ShopController::class, 'update'])->name('shop.update')   ;
    // Route::delete('shops/destroy/{shopid}/{user}',[ShopController::class, 'destroy'])->name('shops.destroy')   ;
    Route::get('shops/show/{shopid}/{user}',[ShopController::class, 'show'])->name('shop.show');
    Route::get('add-address', [ShopController::class, 'add_address'])->name('add_address');
    #end


    #quotations
    Route::resource('quotations', QuotationController::class);
    Route::get('quotations/{id}', [QuotationController::class, 'show']);
    Route::get('quotations/accept/{id}', [QuotationController::class, 'accept'])->name('quatation.accept');
    Route::get('quotations/deny/{id}', [QuotationController::class, 'deny'])->name('quatation.deny');
    Route::get('quotation/accept_reject_bid', [QuotationController::class, 'accept_reject_bid'])->name('quatation.accept_reject_bid');
    Route::get('quotation/accept_reject_quotation', [QuotationController::class, 'accept_reject_quotation'])->name('quatation.accept_reject_quotation');
    #end

    #Notification
    Route::get('notificationread', [NotificationController::class, 'notificationRead']);
    Route::get('notificationlist', [NotificationController::class, 'notificationList'])->name('notificationlist');
    #end

    #orders
    Route::resource('order', OrderController::class);
    Route::get('orders/{id}', [OrderController::class, 'show']);
    #end

    // Route::get('chats', [OrderController::class, 'chat_channels'])->name('chat.index');

        ## Chat Routes
    Route::get('chat', [ChatController::class, 'index'])
        ->name('chat.index');

    Route::post('create_channel', [ChatController::class, 'createChannel'])
        ->name('chat.channel.create');

    Route::get('view_channel/{channel}/{orderid}', [ChatController::class, 'viewChannel'])
        ->name('chat.channel.view');
        
    Route::get('view_chat_channel/{channel}', [ChatController::class, 'viewChatChannel'])
        ->name('channel.chat.view');    

    Route::post('messages/{channel}', [ChatController::class, 'sendMessage'])
        ->name('chat.channel.messages.send');

    Route::get('messages', [ChatController::class, 'fetchMessages'])
        ->name('chat.message.fetch');
    Route::post('orderchatid', [ChatController::class, 'chatOrderInfo'])
        ->name('order.chat.id');
    Route::post('productchatid', [ChatController::class, 'chatProductInfo'])
    ->name('order.chat.productid');
    Route::post('shopchatid', [ChatController::class, 'chatShopInfo'])
    ->name('order.chat.shopid');
});
