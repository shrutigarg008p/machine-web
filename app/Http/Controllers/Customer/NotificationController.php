<?php

namespace App\Http\Controllers\Customer;

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

        $c=OrderNotiifcation::where('user_read','1')->where('user_id',$user->id)->update(['user_read'=>null]);
    	
    	//where('order_id',$id)->
    	    return response()->json(['success'=>1]);
    	}catch(\Exception $ex){
           return response()->json(['success'=>0]);
          }
    }

    public function notificationList(){
		
		try{
			
		$userId = Auth::user()->id;
      
      $notifications = OrderNotiifcation::with('seller')->with('orderseller')->where('user_id',$userId)->where('user_read',null)->get();
        	
        	// echo("<pre>");
        	// print_r($notifications->toArray());
        	// die;
      return view('customer.product.notification',compact('notifications'));
    	   
    	}catch(\Exception $ex){
           return redirect('/home');
      }
    }

}