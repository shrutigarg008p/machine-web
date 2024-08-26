<?php

namespace App\Http\Controllers\Seller;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use App\Vars\Roles;



class VendorAuthController extends Controller
{
    
    public function index()
    {
        return "ik";
        if(Auth::check()){
            return redirect(route('seller.dashboard'));
        }
        return view('seller.auth.login');

        // $total = (object) [
        //     'sellers' => User::role(Roles::SELLER)->count(),
        // ];
        // // dd($total);
        // return view('seller.dashboard.index', compact('total'));
    }

    public function settings()
    {
        return view('seller.account.settings');
    }

    
    public function change_view(){
        return view('seller.account.change_password');
    }
    public function changePassword(Request $request)
    {
        // dd();
        # Get Logged In User Instance
        $user = $this->user();

        # Validate Form Inputs
        $request->validate([
            'old_password' => [
                'required',
                'string',
                'min:8',
                function ($attr, $value, $fail) use ($user){
                    if(! Hash::check($value, $user->password)){
                        $fail('old password is wrong');
                    }
                },
            ],
            'new_password' => [
                'required', 
                'string', 
                'min:8',
                'different:old_password',
                'confirmed'
            ] 
        ]);   

        # Update User Password and Logged Out
        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        # Logged out
        Auth::logout();
        return redirect('/')->withSuccess(__('Password Changed Successfully'));
    }
    
    public function profileUpdate(Request $request){
        $user = $this->user();

        # Validate Form Inputs
        $request->validate([
            'name'                  => ['required', 'string', 'max:191'],
            'email'                 => ['required', 'max:191', 'email'],
            'phone'                => ['required', 'digits_between:8,12','unique:users,phone,'.$user->id],
        ]);   

        # Update User Password and Logged Out
        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->phone = $request->input('phone');

        if( $profile_pic = $request->file('profile_pic') ) {
            $user_profile_pic = auth()->user()->profile_pic;
            if(isset($user_profile_pic)){
                unlink('storage/app/public/'.$user_profile_pic);
            }
            if( $profile_pic = $profile_pic->store('seller_images', 'public') ) {
                $user['profile_pic'] = $profile_pic;
            }
        }

        $user->save();

        # Logged out
        return json_encode(['status'=> 1, 'message'=>'Profile Updated','user'=>$user]);
    }

    public function logout(Request $request)
    {
        $auth_user = $this->user();

        $this->auth->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        $redirect = redirect('/');

        if( $auth_user->role != Roles::CUSTOMER ) {
            $redirect = redirect('/');
        }

        return $redirect->withInfo(__('Logged out successfully'));
    }
  
}
