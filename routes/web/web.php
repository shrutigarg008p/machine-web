<?php

use App\Http\Controllers\Web\HomePageController;    
use Illuminate\Support\Facades\Route;
Route::get('/dashboard',[HomePageController::class,'sellerDashboard']);
Route::group([],function(){

    //Authentication
    Route::post('/loginEnd',[HomePageController::class,'loginEnd']);
    Route::post('/registerloginEnd',[HomePageController::class,'registerloginEnd']);
    Route::post('/loginViaOtpEnd',[HomePageController::class,'loginViaOtpEnd']);

    //Seller PAGES
     Route::get('/dashboard',[HomePageController::class,'sellerDashboard']);
    Route::get('/acceptOrRejectBid',[HomePageController::class,'acceptOrRejectBid']);
    Route::get('/getRfqList',[HomePageController::class,'getRfqList']);
    Route::view('/rfq','web/seller/rfq');
    Route::get('/getrfqdetail/{order_id}',[HomePageController::class,'getRfqDetail']);
    //Seller PAGES

    //Home Page
    // Route::get('/home',[HomePageController::class,'index'])->name('home');
    Route::get('/about-us',[HomePageController::class,'staticcontent'])->name('about-us');
    Route::get('/faq',[HomePageController::class,'staticcontent']);
    Route::get('/privacy-policy',[HomePageController::class,'staticcontent']);
    Route::get('/terms-and-conditions',[HomePageController::class,'staticcontent']);

});

?>