<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Vars\Roles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $android_users = User::where('platform','android');

        $ios_users = User::where('platform','ios');
        $total  = (object) [
            'users' => User::role(Roles::CUSTOMER)->count(),
            'sellers' => User::role(Roles::SELLER)->count(),
            'products' => Product::count(),
            'android_users' => $android_users->count(),
            'ios_users' => $ios_users->count(),
        ];
        return view('admin.dashboard.index',compact('total'));
    }

    public function settings()
    {
        return view('admin.settings');
    }

    public function change_view(){
        return view('admin.changepassword.changepassword');
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
                        $fail(__('old password is wrong'));
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
        return redirect()->route('admin.index')->withSuccess(__('Password Changed Successfully'));
    }
    
    public function changeAdminPassword(Request $request){
        $validatedData = $request->validate([
            'current_password' => 'required',
            'new_password' => 'required',
        ],[
            'current_password.required'=>"Please Enter Old Password",
            'new_password.required'=>"Please Enter New Password" 

        ]);
        if (!(Hash::check($request->get('current_password'), Auth::user()->password))) {
            // The passwords matches
            return redirect()->back()->with(__("stop","Your current password does not matches with the password you provided. Please try again."));
        }

        if(strcmp($request->get('current_password'), $request->get('new_password')) == 0){
            //Current password and new password are same
            return redirect()->back()->with(__("stop","New Password cannot be same as your current password. Please choose a different password."));
        }
        //Change Password
        $user = Auth::user();
        $user->password = bcrypt($request->get('new_password'));
        $user->save();
        Auth::logout();
        return redirect()->route('admin.index')->with(__('done','Password Changed Successfully'));
    }
}
