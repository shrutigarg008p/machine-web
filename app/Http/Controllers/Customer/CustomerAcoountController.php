<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Vars\UserStatus;
use App\Models\UserShop;
use App\Models\UserShopPhoto;
use App\Http\Resources\User as ResourcesUser;
use Auth;

class CustomerAcoountController extends Controller
{
    public function account()
    {
        $user = $this->user();
        // dd($user);
 
        return view('customer.inner.account.account',compact('user'));
    }

    public function updateprofile(Request $request)
    {   
        $user = $this->user();
        $validated = $request->validate([
            'name'          => ['nullable', 'string', 'max:191'],
            'email'         => ['nullable', 'max:191', 'email', 'unique:users,email,'.$user->id],
            'phone_code'    => ['nullable', 'max:10'],
            'phone'         => ['nullable', 'digits_between:8,12', 'unique:users,phone,'.$user->id],
            'profile_pic'   => ['nullable', 'mimes:jpeg,png,jpg,gif', 'max:100'],
            ]);
        DB::beginTransaction();

        try {
            $data = $validated;
            if($request->hasFile('profile_pic')) {
                // if(isset($user->profile_pic)){
                //     unlink('storage/'.$user->profile_pic);
                // }
                $profile_pic = $request->file('profile_pic');
                if( $profile_pic = $profile_pic->store('user_images', 'public') ) {
                     $data['profile_pic'] = $profile_pic;
                }
            }
            DB::commit();

            $user->update($data);
            DB::rollBack();
         
            return back()->withSuccess('Updated Successfully');
        } catch(\Exception $e) {
            logger($e->getMessage());
            return back()->withError('Updated Failed');
        }
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

}
