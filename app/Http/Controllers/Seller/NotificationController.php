<?php

namespace App\Http\Controllers\Seller;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
// use App\Models\ChatMessage;
use App\Models\ProductBidding;
use App\Models\OrderNotiifcation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{

	public function notificationRead(){

		try{
		$user = Auth::user();

        $c=OrderNotiifcation::where('seller_read','1')->where('seller_id',$user->id)->update(['seller_read'=>'0']);
        
        return response()->json(['success'=>1]);	
        //where('order_id',$id)->
		// return redirect()->route('seller.quotations.index')->withSuccess(__('Quatation Accepted'));;
    	}catch(\Exception $ex){
           return response()->json(['success'=>0]);
          }
    }

    public function notificationList(){
        
        try{
            
            $user = Auth::user();
            $userId=$user->id;    
            $notifications = OrderNotiifcation::with('user')->with('orderseller')->where('seller_id',$userId)->get();
      
            // echo("<pre>");
            // print_r($notifications->toArray());
            // die;
            return view('seller.product.notification',compact('notifications'));
           
           }catch(\Exception $ex){
            // dd($ex);
           return redirect('/home');
          }
    }

}