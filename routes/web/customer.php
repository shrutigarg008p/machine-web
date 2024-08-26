<?php
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\ChatController;
use App\Http\Controllers\Customer\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Seller\ForgotPasswordController;
use App\Http\Controllers\Customer\CategoryController;
use App\Http\Controllers\Customer\ShopController;
use App\Http\Controllers\Customer\CustomerAcoountController;
use App\Http\Controllers\Customer\CustomerOrderController;
use App\Http\Controllers\Customer\HomePageController;
use App\Http\Controllers\Customer\SettingsController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\NotificationController;
use App\Http\Controllers\AuthController;

Route::post('/forgot_password',[AuthController::class,'forgot_password'])->name('web.forgot_password');
//Authentication
Route::post('/loginEnd',[HomePageController::class,'loginEnd']);
Route::post('/registerloginEnd',[HomePageController::class,'registerloginEnd']);
Route::post('/loginViaOtpEnd',[HomePageController::class,'loginViaOtpEnd']);

//Seller PAGES
Route::get('/dashboard',[HomePageController::class,'customerDashboard'])->name('dashboard');
Route::get('/acceptOrRejectBid',[HomePageController::class,'acceptOrRejectBid']);
Route::get('/getRfqList',[HomePageController::class,'getRfqList']);
Route::view('/rfq','web/seller/rfq');
Route::get('/getrfqdetail/{order_id}',[HomePageController::class,'getRfqDetail']);
//Seller PAGES

//Home Page
Route::get('/home',[HomePageController::class,'index'])->name('main.home');
Route::get('/about-us',[HomePageController::class,'staticcontent'])->name('about-us');
Route::get('/faq',[HomePageController::class,'staticcontent'])->name('faq');
Route::get('/privacy-policy',[HomePageController::class,'staticcontent'])->name('privacy-policy');
Route::get('/terms-and-conditions',[HomePageController::class,'staticcontent'])->name('terms-and-conditions');
Route::get('/contact-us',[HomePageController::class,'staticcontent'])->name('contact');

#forgotpassword
Route::get('forgotview', [ForgotPasswordController::class, 'index'])
        ->name('forgotview');
Route::post('forgotpassword', [ForgotPasswordController::class, 'forgotpassword'])
    ->name('forgotpassword');
Route::get('forgotpassword/{token}',[ForgotPasswordController::class,'checktoken']);
Route::post('forgotpasswordupdate',[ForgotPasswordController::class,'forgetpasswordUpdate']);
#end

Route::middleware(['auth', 'role:customer'])->group( function() {

    Route::get('categories',[CategoryController::class,'categoryList'])->name('categories');
    Route::get('shopdetails/{id}',[ShopController::class,'detail'])->name('shopdetails');
    Route::get('add-to-favorite-product/{id}',[ProductController::class,'add_to_favourite'])->name('add_to_favorite_product');
    Route::get('add-to-favorite-shop/{id}',[ShopController::class,'add_to_favourite'])->name('add_to_favourite_shop');
    Route::get('productlisting/{catid}/{shop_id}',[ProductController::class,'productlisting'])->name('productlisting');
    Route::get('productdetails/{product_id}/{catid}/{shop_id}',[ProductController::class,'productdetails'])->name('productdetails');
    Route::get('dashboard',[HomePageController::class,'dashboard'])->name('dashboard');
    Route::get('help-support',[HomePageController::class,'help_support'])->name('help_support');
    Route::post('help-support-store',[HomePageController::class,'help_support_store'])->name('help_support_store');
    Route::get('favourites-shops',[ShopController::class,'favourites'])->name('favourites');
    Route::get('favourites-products',[ProductController::class,'favourites'])->name('favourites.product');
    Route::get('rating/{seller_id}/{shop_id}',[HomePageController::class,'rating'])->name('rating');
    Route::post('rating',[HomePageController::class,'rate'])->name('shop.rating');
    // Route::view('chat','customer/inner/chat');

    #Chat Routes
        Route::get('chat', [ChatController::class, 'index'])
        ->name('chat.index');
        
        Route::post('create_channel', [ChatController::class, 'createChannel'])
            ->name('chat.channel.create');

        Route::post('create_shop_channel', [ChatController::class, 'createShopChannel'])
            ->name('chat.shopchannel.create');

       Route::post('create_channel_prodcut', [ChatController::class, 'createProductChannel'])
            ->name('chat.channelprodcut.create');     
       Route::get('view_channel_product/{channel}/{productid}', [ChatController::class, 'viewChannelProduct'])
        ->name('chat.channel.viewproduct');     


       Route::get('view_shopchannel/{channel}', [ChatController::class, 'viewShopChannel'])
        ->name('chat.shopchannel.view');     
        
        // Route::get('view_channel/{channel}', [ChatController::class, 'viewChannel'])
        // ->name('chat.channel.view');
        
        // Route::get('view_chat_channel/{channel}', [ChatController::class, 'viewChatChannel'])
        // ->name('channel.chat.view');

       Route::get('view_channel/{channel}', [ChatController::class, 'viewChannel'])
        ->name('chat.channel.view');
        
        Route::get('view_chat_channel/{channel}', [ChatController::class, 'viewChatChannel'])
        ->name('channel.chat.view');     

        Route::post('orderchatid', [ChatController::class, 'chatOrderInfo'])
        ->name('order.chat.id');


        Route::post('productchatid', [ChatController::class, 'chatProductInfo'])
        ->name('order.chat.productid');

        Route::post('shopchatid', [ChatController::class, 'chatShopInfo'])
        ->name('order.chat.shopid');

        Route::post('messages/{channel}', [ChatController::class, 'sendMessage'])
        ->name('chat.channel.messages.send');
        
        Route::get('messages', [ChatController::class, 'fetchMessages'])
        ->name('chat.message.fetch');
    });
    
    #Order Routes
    Route::get('order', [OrderController::class, 'orders'])->name('order');
    Route::get('orderdetails/{order_id}', [OrderController::class,'order_detail'])->name('orderDetails');
    
    #Quatations Routes
    Route::get('quatations', [OrderController::class, 'quotations'])->name('quatations');
    Route::get('quatationsdetails/{order_id}', [OrderController::class,'quotation_detail'])->name('quotationDetails');
    #Logout Routes
    Route::get('/sessionlogout', [HomePageController::class, 'sessionlogout'])
    ->name('sessionlogout');
    
    #settings Routes
    Route::get('settings', [SettingsController::class, 'settings'])->name('settings');
    Route::get('add-address', [SettingsController::class, 'add_address'])->name('add_address');
    Route::post('addnewaddress', [SettingsController::class, 'addnewaddress'])->name('addnewaddress');
    Route::get('destroy/{id}', [SettingsController::class, 'destroy'])->name('destroy');
    Route::get('edit', [SettingsController::class, 'edit'])->name('edit');
    Route::post('update', [SettingsController::class, 'update'])->name('update');
    Route::get('set-primary-address/{id}', [SettingsController::class, 'set_primary_address'])->name('set_primary_address');
    //Cart
    Route::get('cart', [CartController::class, 'index'])->name('cart');
    Route::get('add-to-cart/{req}/{id}', [CartController::class, 'add_to_cart'])->name('add_to_cart');
    Route::get('remove-from-cart/{id}', [CartController::class, 'remove_from_cart'])->name('remove_from_cart');
    Route::get('delete-from-cart/{id}', [CartController::class, 'delete_from_cart'])->name('delete_from_cart');
    #Account Routes
    Route::get('account', [CustomerAcoountController::class, 'account'])->name('account');
    Route::any('updateprofile', [CustomerAcoountController::class, 'updateprofile'])->name('updateprofile');
    Route::any('changePassword', [CustomerAcoountController::class, 'changePassword'])->name('changePassword');


    Route::post('customer/update-account', [AuthController::class, 'update_account'])
    ->name('customer.update_account_save');

    Route::post('seller/shop-create', [AuthController::class, 'shop_create'])
    ->name('seller.shop_create');

    Route::post('submit-quotation',[OrderController::class, 'submit_quotation'])
    ->name('submit_quotation');
    Route::get('customer/notificationread', [NotificationController::class, 'notificationRead']);
    Route::get('notificationlist', [NotificationController::class, 'notificationList'])->name('notificationlist');
    
    Route::get('/subscribe', [HomePageController::class, 'subscribe'])->name('subscribe');
    Route::get('/dis',[HomePageController::class, 'dis'])->name('dis');
    Route::post('/session-store-lat-long',[HomePageController::class, 'sessionStore'])->name('sessionStore');
    Route::get('/testt',[HomePageController::class, 'testt'])->name('testt');