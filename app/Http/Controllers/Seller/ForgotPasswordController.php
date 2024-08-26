<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\SendCode;
use Exception;
use Twilio\Rest\Client;

class ForgotPasswordController extends Controller
{
   
    public function index()
    {
        return view('seller/forgotpassword/forgotpasswordemail');
    }

    public function forgotpassword(Request $request)
    {
        // dd($request->all());
        if($request->forgot_type == "email"){
            $this->validate($request, [
                'forgot_pass' => "required",
                ],
                [
                    'forgot_pass.required' => "Please enter email address"
                ]
            );

            $user = User::where('email', $request->forgot_pass)->first();

            if(isset($user)&& $user->role!="seller"){
                return back()->with(__('error','Unauthorized! Not a valid  seller.'));
            }

            if ($user) {
                $token = Str::random(30);
                $link = url("seller/forgotpassword/$token");
                $email = $user->email;
                DB::table('password_resets')->insert(['email' => $email, 'token' => $token, 'created_at' => Carbon::now() ]);
                try{
                    Mail::send('seller.forgotpassword.forgottemplate',
                        [
                            'username' => ucfirst($user->name),
                            'actionUrl' => $link
                        ],
                        function ($m) use ($email) {
                            // $m->from('admin@magazine.com', 'Magazine GCGL');
                            $m->to($email)->subject('Forgot Password!');
                        });
                } catch (\Exception $e) {
                        $error_message = $e->getMessage();

                      }

                return redirect('/seller/login')->with(__('success', "we have sent a reset password link to this email: $email"));
            } else {

                return back()->with(__('error', "Sorry, this email not registered with us please try another."));
            }
        }else if($request->forgot_type =="phone"){

            $this->validate($request, [
                'forgot_pass' => "required|digits:10",
                ],[
                    'forgot_pass.digits'=>"phone number must be 10 digits"
                ]
            );
            $user = User::where('phone', $request->forgot_pass)->first();
            if(isset($user)&& $user->role!="seller"){
                return back()->with(__('error','Unauthorized! Not a valid  seller.'));
            }
            if ($user) {
                $user_otp = rand(100000,999999);
                User::where('id',$user->id)->update(['otp'=>$user_otp]);
                $receiverNumber = "+91".$request->forgot_pass;
                $message = "Your reset password otp is ". $user_otp;
                try {
                    $account_sid = getenv("TWILIO_SID");
                    $auth_token = getenv("TWILIO_TOKEN");
                    $twilio_number = getenv("TWILIO_FROM");
                    $client = new Client($account_sid, $auth_token);
                    $client->messages->create($receiverNumber, [
                        'from' => $twilio_number, 
                        'body' => $message]);
                } catch (Exception $e) {
                    logger("Error: ". $e->getMessage());
                }
                $token = Str::random(30);
                DB::table('password_resets')->insert(['email'=>$user->email,'otp' => $user_otp, 'token' => $token, 'created_at' => Carbon::now() ]);
                return redirect("seller/unique/$token");
                
                
            } else {
                return back()->with('error', "Sorry, this phone not registered with us please try another.");
            }
        }else{
            return back()->with(__('error', "Please enter phone or email address."));
        }
    }

    public function checktoken($token){
   
        $tokens = $token;
        $change = DB::table('password_resets')->where('token', $token)->first();
        if(!empty($change)){
        $email=$change->email;
        }
        if (!$change) {
            echo "Password recovery link has expired, Please try again.";
            die;
        }
        return view('seller.forgotpassword.forgotupdatepassword', compact('tokens','email'));
    }

    public function forgetpasswordUpdate(Request $request){
        $this->validate($request, [
            "new_password" => "required|min:6",
            "confirm_password" => "required|min:6|same:new_password",
        ], [
            "new_password.required" => "Please Enter Password",
            "confirm_password.required" => "Please Confirm Password",

        ]);
        $email = $request['email'];
        $token = $request['password_token'];
        $am = $request['new_password'];
        $new = $request['confirm_password'];

        $updatePassword = DB::table('password_resets')->where(['email' => $email, 'token' => $token])
            ->first();

        //print_r($updatePassword);exit;
        if (!$updatePassword) return back()->withInput()
            ->with('error', 'Invalid token!');

        $user = User::where('email', $email)
            ->update(['password' => Hash::make($new) ]);

        DB::table('password_resets')
            ->where(['email' => $email])
            ->delete();

        return redirect('/seller/login')
            ->with(__('success', 'Your password has been changed!'));
    }   
}
