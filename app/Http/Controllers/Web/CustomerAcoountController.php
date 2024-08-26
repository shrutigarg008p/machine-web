<?php

namespace App\Http\Controllers\Web;

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
        $validator = Validator::make($request->all(), [
            'name'          => ['nullable', 'string', 'max:191'],
            'email'         => ['nullable', 'max:191', 'email', 'unique:users,email,'.$user->id],
            'phone_code'    => ['nullable', 'max:10'],
            'phone'         => ['nullable', 'digits_between:8,12', 'unique:users,phone,'.$user->id],
            'profile_pic'   => ['nullable', 'mimes:jpeg,png,jpg,gif', 'max:100'],
            'old_password'  => ['nullable'],
            'password'      => ['nullable', 'confirmed'],
            'country'         => ['nullable', 'max:4'],
            'city'            => ['nullable', 'max:4'],
            'state'           => ['nullable', 'max:191'],
            'address_1'       => ['nullable', 'max:191'],
            'address_2'       => ['nullable', 'max:191'],
            'latitude'       => ['nullable', 'max:191', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude'      => ['nullable', 'max:191', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
        ]);

        
        if ($validator->fails()) {
            return $this->validation_error_response($validator);
        }

        DB::beginTransaction();

        try {
            $data = $validator->validated();
            // dd($data['old_password'], $user->password);

            
            if( empty($data) ) {
                throw new \Exception("update_account - invalid data");
            }
             // password update
             if( isset($data['old_password']) && isset($data['password']) ) {

                if( Hash::check($data['old_password'], $user->password) ) {

                    $user->password = Hash::make($data['password']);
                    $user->update();
                    
                    return response()->json(['data' => 'Password Updated Successfully']);
                }
                return ('Old password is incorrect');
            }
            if( isset($user->email) ) {
                // unset($data['email']);
            }

            if( isset($user->phone) ) {
                // unset($data['phone']);
            }
            if( isset($user->password) ) {
                // unset($data['phone']);
            }

            // if( empty($user->password) && isset($data['password']) ) {
            //     $data['password'] = Hash::make($data['password']);
            // } else {
            //     unset($data['password']);
            // }

            if( $profile_pic = $request->file('profile_pic') ) {
                if( $profile_pic = $profile_pic->store('user_images', 'public') ) {
                    $data['profile_pic'] = $profile_pic;
                }
            }
            // dd($user->password);

            

            $user->fill($data);
            // dd($user);
            $user->status = UserStatus::OK;
           
            $user->onboarded = 1;

            $data['user_id'] = $user->id;

           
            $user->update();

            DB::commit();

            new ResourcesUser($user);
        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        DB::rollBack();
        // dd($user);
        return redirect()->back()->with('Updated Successfully');


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
