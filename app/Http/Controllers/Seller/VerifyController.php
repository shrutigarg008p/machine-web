<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
class VerifyController extends Controller
{
    public function getVerify($tokens){
        // dd($tokens);
        return view('seller.otp.verify',compact('tokens'));
    }
    public function postVerify(Request $request){
      // dd($request->all());
        if($user=User::where('otp',$request->otp)->first()){
            // $user->otp_verified=1;
            $user->otp=null;
            $user->save();
            $token=$request->password_token;
            return redirect("seller/forgotpassword/$token"); 
        }
        else{
            return back()->with(__('error','verify code is not correct. Please try again'));
        }
    }
}
