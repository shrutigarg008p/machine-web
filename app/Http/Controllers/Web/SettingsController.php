<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserAddressResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\APICall;

class SettingsController extends Controller
{
    public function settings(Request $request)
    {
        $useraddress=UserAddressResource::collection($this->user()->addresses);
        // foreach($useraddress as $item)

        // dd($item->email);
        // $user = $this->user();
        

        // $address = $user->addresses()->find($request->get('address_id'));

        // $useraddress = new UserAddressResource($address);
        
        // $addressdetails =json_decode(json_encode($useraddress),true);
        
        
        return view('customer.inner.manageshop.address',compact('useraddress'));
    }
    
    public function add_address()
    {
        return view('customer.inner.manageshop.addaddress');
    }
    public function addnewaddress(Request $request)
    
    {
        $user = $this->user();

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191', 'unique:user_addresses,email,'.$user->id.',user_id'],
            'phone' => ['nullable', 'digits_between:8,12', 'unique:user_addresses,phone,'.$user->id.',user_id'],
            'address_1' => ['nullable', 'string', 'max:191'],
            'address_2' => ['nullable', 'string', 'max:191'],
            'country' => ['nullable', 'string', 'max:191'],
            'state' => ['nullable', 'string', 'max:191'],
            'city' => ['nullable', 'string', 'max:191'],
            'zip' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['nullable', 'in:1'],
            'latitude' => ['nullable', 'max:191', 'lat_long'],
            'longitude' => ['nullable', 'max:191', 'lat_long']
        ]);
        // dd($validator);

        if ($validator->fails()){
            return $this->validation_error_response($validator);
        }

        $address = $this->user()->addresses()->create( $validator->validated() );

        // set other addresses as secondary
        if( $request->get('is_primary') ) {
            $this->user()->addresses()
                ->where('id', '<>', $address->id)
                ->update(['is_primary' => 0]);
        }
        new UserAddressResource($address);
        // dd(new UserAddressResource($address));
        return redirect('settings')->with('success','Address Added Sucessfully ! ');

    }

    public function edit(Request $request)
    {
        $id = $request->get('id');
        // dd($request->id);
        $useraddress=UserAddressResource::collection($this->user()->addresses)
        ->where('id',$id)->first();
        if(!empty($useraddress) ){
            return view('customer.inner.manageshop.editaddress',compact('useraddress','id'));
        }
        else{
            return redirect()->back();
        }
    }

    public function update(Request $request)
    {
        $user = $this->user();

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'exists:user_addresses,id,user_id,'.$user->id],
            'name' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191', 'unique:user_addresses,email,'.$user->id.',user_id'],
            'phone' => ['nullable', 'digits_between:8,12', 'unique:user_addresses,phone,'.$user->id.',user_id'],
            'address_1' => ['nullable', 'string', 'max:191'],
            'address_2' => ['nullable', 'string', 'max:191'],
            'country' => ['nullable', 'string', 'max:191'],
            'state' => ['nullable', 'string', 'max:191'],
            'city' => ['nullable', 'string', 'max:191'],
            'zip' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['nullable', 'in:1'],
            'latitude' => ['nullable', 'max:191', 'lat_long'],
            'longitude' => ['nullable', 'max:191', 'lat_long']
        ]);
        
        if ($validator->fails()) {
            return $this->validation_error_response($validator);
        }
        $address = $this->user()->addresses()->find($request->get('id'));

        $address->fill($validator->validated());
        $address->update();

        // set other addresses as secondary
        if( $request->get('is_primary') ) {
            $this->user()->addresses()
                ->where('id', '<>', $request->get('address_id'))
                ->update(['is_primary' => 0]);
        }
        new UserAddressResource($address);
        return redirect('settings')->with('success','Address Updated Sucessfully ! ');
    }

    public function destroy(Request $request,$id)
    {
         $this->user()->addresses()
            ->where('id',$id)
            ->delete();
            return redirect()->back();
    }
        
}
