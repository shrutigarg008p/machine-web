<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\UserImportByCollection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Monarobase\CountryList\CountryListFacade;
use Illuminate\Support\Facades\Hash;
use App\Models\UserShop;
use App\Models\UserShopPhoto;
use Spatie\Permission\Models\Permission;
use App\Vars\Roles;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ProductCategory;
use App\Models\SellerProductCategory;
use App\Events\NewUserRegistered;
use App\Events\UserActivatedDeActivated;
use App\Events\SellerApproveDisapprove;
use Graftak\CountryCodeHelper;
use Graftak\CountryCodeHelper\CountryCodes;
use Carbon\Carbon;
use App\Models\Translation\UserShopTranslation;
use Illuminate\Validation\Rule;
use App\Models\VerifyUser;
use App\Models\UserAddress;

class UserController extends Controller
{
    private $limits = 15;
    /**
     * function used for show  user by roles
     */
    public function index(Request $request)
    {
        $search =  $request->input('q');
        if (request()->start_date || request()->end_date) {
            $start_date = Carbon::parse(request()->start_date)->toDateTimeString();
            $end_date = Carbon::parse(request()->end_date)->toDateTimeString();
            $users = User::whereBetween('created_at', [$start_date, $end_date])->paginate($this->limits);
            $users->appends(['start_date' => $start_date, 'end_date' => $end_date]);
        } else if ($search != "") {
            $users = User::where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            })
                ->paginate($this->limits);
            $users->appends(['q' => $search,'type'=>$request->type]);
        } else {
            $users = User::orderBy('id', 'DESC')
                ->whereDoesntHave('roles', function ($query) {
                    $query->whereIn('name', [Roles::SUPER_ADMIN]);
                });
            # Filter Users By Type
            $type = $request->input('type');

            if (in_array($type, [Roles::CUSTOMER, Roles::SELLER])) {
                $users->role($type);
            }

            # Get Users Collection
            if($request->platform == "android"){
              $users = $users->orderBy('id', 'DESC')->where('platform','android')->paginate($this->limits);

            }else if($request->platform == "ios"){
               $users = $users->orderBy('id', 'DESC')->where('platform','ios')->paginate($this->limits);

            }else{
              $users = $users->orderBy('id', 'DESC')->paginate($this->limits);
            }
            $users->appends(['type' => $type]);
        }
        return view('admin.users.index', compact('users'));
    }

    /**
     * function used for create customer user 
     */
    public function create()
    {
        $countries =  CountryListFacade::getList('en');
        return view('admin.users.create', compact('countries'));
    }

    /*
    *function used for create seller user 
    */
    public function sellerCreate()
    {
        $categories = ProductCategory::query();
        $categories = $categories
            ->latest()
            ->get();
        return view('admin.users.seller_create', compact('categories'));
    }

    /*
    *function used for import users by excel
    */
    public function importUsers(Request $request)
    {
        try {
            Excel::import(new UserImportByCollection, $request->file('file'));
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $fail = collect($e->failures())->first();
            return back()->with('error', $fail->errors()[0] . ' at row number ' . $fail->row());
        }
        return back()->withSuccess(__('Registration successfull'));
    }

    /**
     *function used for store customer and seller
     */
    public function store(Request $request)
    {

        $cur_with_code = array(
            'AF' => 'AFN','AL' => 'ALL','DZ' => 'DZD','AS' => 'USD','AD' => 'EUR','AO' => 'AOA','AI' => 'XCD','AQ' => 'XCD','AG' => 'XCD','AR' => 'ARS','AM' => 'AMD','AW' => 'AWG','AU' => 'AUD','AT' => 'EUR','AZ' => 'AZN','BS' => 'BSD','BH' => 'BHD','BD' => 'BDT','BB' => 'BBD','BY' => 'BYR','BE' => 'EUR','BZ' => 'BZD','BJ' => 'XOF','BM' => 'BMD','BT' => 'BTN','BO' => 'BOB','BA' => 'BAM','BW' => 'BWP','BV' => 'NOK','BR' => 'BRL','IO' => 'USD','BN' => 'BND','BG' => 'BGN','BF' => 'XOF','BI' => 'BIF','KH' => 'KHR','CM' => 'XAF','CA' => 'CAD','CV' => 'CVE','KY' => 'KYD','CF' => 'XAF','TD' => 'XAF','CL' => 'CLP','CN' => 'CNY','HK' => 'HKD','CX' => 'AUD','CC' => 'AUD','CO' => 'COP','KM' => 'KMF','CG' => 'XAF','CD' => 'CDF','CK' => 'NZD','CR' => 'CRC','HR' => 'HRK','CU' => 'CUP','CY' => 'EUR','CZ' => 'CZK','DK' => 'DKK','DJ' => 'DJF','DM' => 'XCD','DO' => 'DOP','EC' => 'ECS','EG' => 'EGP','SV' => 'SVC','GQ' => 'XAF','ER' => 'ERN','EE' => 'EUR','ET' => 'ETB','FK' => 'FKP','FO' => 'DKK','FJ' => 'FJD','FI' => 'EUR','FR' => 'EUR','GF' => 'EUR','TF' => 'EUR','GA' => 'XAF','GM' => 'GMD', 'GE' => 'GEL','DE' => 'EUR','GH' => 'GHS','GI' => 'GIP','GR' => 'EUR','GL' => 'DKK','GD' => 'XCD','GP' => 'EUR','GU' => 'USD','GT' => 'QTQ','GG' => 'GGP','GN' => 'GNF','GW' => 'GWP','GY' => 'GYD','HT' => 'HTG','HM' => 'AUD','HN' => 'HNL','HU' => 'HUF','IS' => 'ISK','IN' => 'INR','ID' => 'IDR','IR' => 'IRR','IQ' => 'IQD','IE' => 'EUR','IM' => 'GBP','IL' => 'ILS','IT' => 'EUR','JM' => 'JMD','JP' => 'JPY','JE' => 'GBP','JO' => 'JOD','KZ' => 'KZT','KE' => 'KES','KI' => 'AUD','KP' => 'KPW','KR' => 'KRW','KW' => 'KWD','KG' => 'KGS','LA' => 'LAK','LV' => 'EUR','LB' => 'LBP','LS' => 'LSL','LR' => 'LRD','LY' => 'LYD','LI' => 'CHF','LT' => 'EUR','LU' => 'EUR','MK' => 'MKD','MG' => 'MGF','MW' => 'MWK','MY' => 'MYR','MV' => 'MVR','ML' => 'XOF','MT' => 'EUR','MH' => 'USD','MQ' => 'EUR','MR' => 'MRO','MU' => 'MUR','YT' => 'EUR','MX' => 'MXN','FM' => 'USD','MD' => 'MDL','MC' => 'EUR','MN' => 'MNT','ME' => 'EUR','MS' => 'XCD','MA' => 'MAD','MZ' => 'MZN','MM' => 'MMK','NA' => 'NAD','NR' => 'AUD','NP' => 'NPR','NL' => 'EUR','AN' => 'ANG','NC' => 'XPF','NZ' => 'NZD','NI' => 'NIO','NE' => 'XOF','NG' => 'NGN','NU' => 'NZD','NF' => 'AUD','MP' => 'USD','NO' => 'NOK','OM' => 'OMR','PK' => 'PKR','PW' => 'USD','PA' => 'PAB','PG' => 'PGK','PY' => 'PYG','PE' => 'PEN','PH' => 'PHP','PN' => 'NZD','PL' => 'PLN','PT' => 'EUR','PR' => 'USD','QA' => 'QAR','RE' => 'EUR','RO' => 'RON','RU' => 'RUB','RW' => 'RWF','SH' => 'SHP','KN' => 'XCD','LC' => 'XCD','PM' => 'EUR','VC' => 'XCD','WS' => 'WST','SM' => 'EUR','ST' => 'STD','SA' => 'SAR','SN' => 'XOF','RS' => 'RSD','SC' => 'SCR','SL' => 'SLL','SG' => 'SGD','SK' => 'EUR','SI' => 'EUR','SB' => 'SBD','SO' => 'SOS','ZA' => 'ZAR','GS' => 'GBP','SS' => 'SSP','ES' => 'EUR','LK' => 'LKR','SD' => 'SDG','SR' => 'SRD','SJ' => 'NOK','SZ' => 'SZL','SE' => 'SEK','CH' => 'CHF','SY' => 'SYP','TW' => 'TWD','TJ' => 'TJS','TZ' => 'TZS','TH' => 'THB','TG' => 'XOF','TK' => 'NZD','TO' => 'TOP','TT' => 'TTD','TN' => 'TND','TR' => 'TRY','TM' => 'TMT','TC' => 'USD','TV' => 'AUD','UG' => 'UGX','UA' => 'UAH','AE' => 'AED','GB' => 'GBP','US' => 'USD','UM' => 'USD','UY' => 'UYU','UZ' => 'UZS','VU' => 'VUV','VE' => 'VEF','VN' => 'VND','VI' => 'USD','WF' => 'XPF','EH' => 'MAD','YE' => 'YER','ZM' => 'ZMW','ZW' => 'ZWD',
            );

        if ($request->role == "customer") {
            $validator = $request->validate([
                'name'    =>  ['required', 'string', 'min:4', 'max:30'],
                'email'   => ['required', 'email', 'unique:users', 'max:35'],
                'password' => ['required', 'confirmed', Password::min(8)],
                'password_confirmation' => ['required'],
                'phone'   => ['required', 'digits_between:8,15', 'unique:users,phone'],
                'country'       => ['required', 'string'],
                'state'       => ['required', 'string'],
                'city'       => ['required', 'string'],
            ]);
            try {
                $ios3_country = CountryCodeHelper::map($validator['country'], CountryCodes::ALPHA_3);
                $currency =$cur_with_code[$validator['country']] ?? null;
                # Create Buyer User and store its Information
                $user = User::create([
                    'name' => $validator['name'],
                    'email'     => $validator['email'],
                    'password'  => Hash::make($validator['password']),
                    'phone'     => $validator['phone'],
                    'country' => $ios3_country,
                    'currency'=>$currency,
                    'state' => $validator['state'],
                    'city' => $validator['city'],
                    'address_line_1' => $request->address,
                    'address_line_2' => $request->address2,
                ]);


                $user_addr = UserAddress::create([
                    'user_id' => $user->id,
                    'name' => $validator['name'],
                    'email'     => $validator['email'],
                    'phone'     => $validator['phone'],
                    'country' => $ios3_country,
                    'state' => $validator['state'],
                    'city' => $validator['city'],
                    'address_1' => $request->address,
                    'address_2' => $request->address2,
                ]);

                $user->syncRoles([Roles::CUSTOMER]);


                #for send credentials in mail
                try {
                    $this->sendMailCredentials($user, $user->id);
                } catch (\Exception $e) {
                    logger($e->getMessage());
                }

                #for mail verifiation
                try {
                    $this->sendverifyMail($user, $user->id);
                } catch (\Exception $e) {
                    logger($e->getMessage());
                }
                return redirect()->route('admin.users.index')->withSuccess(__('Account created successfully'));
            } catch (\Exception $e) {
                logger($e->getMessage());
            }
        }
        if ($request->role == "seller") {
            // dd($request->all());
            $validator = $request->validate([
                'name'    =>  ['required', 'string', 'min:4', 'max:30'],
                'license'    =>  ['required', 'regex:/^[\w-]*$/'],
                'email'   => ['required', 'email', 'unique:users', 'max:35'],
                'password' => ['required', 'confirmed', Password::min(8)],
                'password_confirmation' => ['required'],
                'phone'   => ['required', 'digits_between:8,15', 'unique:users,phone'],
                'country'       => ['required', 'string'],
                'state'       => ['required', 'string'],
                'city'       => ['required', 'string'],
                'file' => ['required', 'mimes:jpg,jpeg,png'],
                'parent_id' => ['required'],
                'photos'          => ['required', 'nullable', 'array', 'max:5'],
                'photos.*'        => ['image', 'nullable', 'mimes:jpeg,png,jpg,gif', 'max:2500'],
                'working_hours_from' => ['nullable', 'date_format:H:i'],
                'working_hours_to' => ['nullable', 'date_format:H:i', 'after:working_hours_from'],
                'working_days'     => ['nullable', 'max:191'],
                'ar.overview'=>[
                    Rule::requiredIf(function () use ($request) {
                        if($request->has('en') && !$request->get('en')['overview']){
                            return true;
                        }

                    }
                )],
                // 'ar.services'=>[
                //     Rule::requiredIf(function () use ($request) {
                //         if($request->has('en') && !$request->get('en')['services']){
                //             return true;
                //         }

                //     }
                // )],
                // 'en.services'=>[
                //     Rule::requiredIf(function () use ($request) {
                //         if($request->has('ar') && !$request->get('ar')['services']){
                //             return true;
                //         }

                //     }
                // )],
                'en.overview'=>[
                     Rule::requiredIf(function () use ($request) {
                        if($request->has('ar') && !$request->get('ar')['overview']){
                            return true;
                        }

                    }
                )],

            ],
            [
                'ar.overview.required'=>'overview field is required for arabic',
                'en.overview.required'=>'overview field is required for english',
                // 'ar.services.required'=>'services field is required for arabic',
                // 'en.services.required'=>'services field is required for  english'
            ]);

            

            try {
                # Create Seller User and store its Information

                $ios3_country = CountryCodeHelper::map($validator['country'], CountryCodes::ALPHA_3);
                // $currency='';
                $currency =$cur_with_code[$validator['country']] ?? null;
                // foreach ($cur as $key => $value) {
                //     if($key == $validator['country']){
                //         $currency = $value;
                //         break;
                //     }
                // }
                if ($request->hasFile('file')) {
                    $extension = $request->file->extension();

                    $file_path = $request->file->store('seller/id/card', 'public');
                    $validator['image'] = $file_path;
                }
                // dd($currency);

                $user = User::create([
                    'name' => $validator['name'],
                    'email'     => $validator['email'],
                    'password'  => Hash::make($validator['password']),
                    'phone'     => $validator['phone'],
                    'country' => $ios3_country,
                    'currency'=>$currency,
                    'state' => $validator['state'],
                    'city' => $validator['city'],
                    'address_line_1' => $request->address,
                    'address_line_2' => $request->address2,
                    'image' => $validator['image']
                ]);

                $user->syncRoles([Roles::SELLER]);
                $weekMap = [
                    0 => 'Su',
                    1 => 'Mo',
                    2 => 'Tu',
                    3 => 'We',
                    4 => 'Th',
                    5 => 'Fr',
                    6 => 'Sa',
                ];
                $dayOfTheWeek = \Carbon\Carbon::now()->dayOfWeek;
                $weekday = $weekMap[$dayOfTheWeek];
                $user_shop = UserShop::create([
                    'user_id' => $user->id,
                    'shop_name' => $validator['name'],
                    'shop_email'     => $validator['email'],
                    'shop_contact'     => $validator['phone'],
                    'registration_no' => $validator['license'],
                    'country' => $ios3_country,
                    'state' => $validator['state'],
                    'address_1' => $request->address,
                    'address_2' => $request->address2,
                    'working_hours_from' => $request->work_hours_from,
                    'working_hours_to' => $request->work_hours_to,
                    'working_days' => $weekday
                ]);

                $user_addr = UserAddress::create([
                    'user_id' => $user->id,
                    'name' => $validator['name'],
                    'email'     => $validator['email'],
                    'phone'     => $validator['phone'],
                    'country' => $ios3_country,
                    'state' => $validator['state'],
                    'city' => $validator['city'],
                    'address_1' => $request->address,
                    'address_2' => $request->address2,
                ]);

                if($request->ar){
                    if(isset($request->ar['services']) ) {
                      $services = $request->ar['services'];
                      $services = array_combine($services['value'], $services['value']);
                    }
                
                    // dd($request->ar['overview']);
                    UserShopTranslation::create([
                        'locale'=>'ar',
                        'user_shop_id'=>$user_shop->id,
                        'overview'=>$request->ar['overview'],
                        'services'=>json_encode(array_filter($services))

                    ]);
                }
                if($request->en){
                    if(isset($request->en['services']) ) {
                     $services = $request->en['services'];
                     $services = array_combine($services['value'], $services['value']);
                    }
                    UserShopTranslation::create([
                        'locale'=>'en',
                        'user_shop_id'=>$user_shop->id,
                        'overview'=>$request->en['overview'],
                        'services'=>json_encode(array_filter($services))
                    ]);
                }
                foreach ($request->parent_id as $val) {
                    $seller_product_details = SellerProductCategory::create([
                        'user_shop_id'       => $user_shop->id,
                        'product_category_id'   => $val,
                    ]);
                }               

                if ($request->hasfile('photos')) {
                    foreach ($request->file('photos') as $file) {
                        $file_path = $file->store('shops_photos', 'public');
                        $validator['photo'] = $file_path;
                        $user_company_photos = UserShopPhoto::create([
                            'user_shop_id'    => $user_shop->id,
                            'photo'   => $file_path,
                        ]);
                    }
                }
                #for mail verification
                try {
                    $this->sendverifyMail($user, $user->id);
                } catch (\Exception $e) {
                    logger($e->getMessage());
                }
                return redirect()->route('admin.users.index')->withSuccess(__('Account created successfully'));
            } catch (\Exception $e) {
                logger($e->getMessage());
            }
        }
    }

    /**
     * function used for send verify mail using event 
     */
    public function sendverifyMail($user, $user_id)
    {
        $verify_user = VerifyUser::create([
            'user_id' => $user_id,
            'token'     => sha1(time())
        ]);    
        event(new NewUserRegistered($user));
    }

    public function verifyLink($token)
    {
        // dd($user);
        $verifyuser = VerifyUser::where('token', $token)->first();
        
        if (isset($verifyuser)) {
            $user = $verifyuser->user;

            // $redirect = redirect('/');
            $message = '';

            $user_role = $user->role;

            if (!$user->email_verified_at) {

                $verifyuser->user->email_verified_at = Carbon::now();
                $verifyuser->user->save();
                $email = $user->email;
                if( $user_role != 'seller' ) {
                    $redirect =  redirect('/customer');
                    try {
                        \Mail::send(
                            'mails.customer.confirmemail',
                            array(
                                'name' => $user->name ,
                                'email' => $user->email
                            ),
                            function ($message) use ($email) {

                                $message->to($email)->subject('Confirmation Email');
                            }
                        );
                    } catch (\Exception $e) {
                        logger(' issue: ' . $e->getMessage());
                    }
                }

                $message = 'Your email is verified. You can now log in';
            } else {
                $message = 'Your email is already verified. You can log in.';
            }
            /**
             * undocumented constant
             **/
           
            if( $user->role == 'seller' ) {
            
                $redirect = redirect('/seller/');
            }

            return $redirect->withSuccess(__($message));
        }

        return redirect()->route('home')
            ->with('error', 'Something Went Wrong.');
    }

    /**
     * function used for showing user details
     */
    public function show(User $user)
    {
        $user_shop = User::with('user_shop')->where('id', $user->id)->first();
        $user_company_datas =  $user_shop->user_shop->id ?? null;
        $user_company_photos =  UserShopPhoto::where('user_shop_id', $user_company_datas)->get();
        $categories = ProductCategory::query();
        $categories = $categories->latest()->get();
        $user_shop_id = $user_shop->user_shop->id ?? null;
        // dd($user_shop_id);
        $seller_categories = SellerProductCategory::where('user_shop_id', $user_shop_id)->get();
        // dd($seller_categories);
        $catArray = [];
        if(!empty($seller_categories)){
            foreach ($seller_categories as $key => $value) {
               array_push($catArray, $value->product_category_id);
            }
        }
        // dd($catArray);
        return view('admin.users.show', [
            'user' => $user,
            "user_shop"=>$user_shop,
            "user_company_photos"=>$user_company_photos,
            "categories"=>$categories,
            "catArray"=>$catArray
        ]);
    }

    /**
     * function used for edit roles
     */
    public function edit(Request $request, User $user)
    {

        if ($user->hasRole([Roles::ADMIN])) {
            $permissions = Permission::all();
            return view('admin.users.edit', compact('user', 'permissions'));
        }
        if ($user->hasRole([Roles::SELLER])) {
            $categories = ProductCategory::query();
            $categories = $categories
                ->latest()
                ->get();
            $user_id = $user->id ?? null;
            $user_shop = UserShop::where('user_id',$user_id)->first();
            // dd($user_shop->user_shop->id);
            // $user_shop_id = $user_shop->user_shop->id ?? null;
            $user_shop_id = $user_shop->id ?? null;

            $seller_categories = SellerProductCategory::where('user_shop_id', $user_shop_id)->get();
            $user_with_shop = User::with('user_shop')->where('id', $user_id)->first();
            // dd($user_shop_id);

            $catArray = [];
            if(!empty($seller_categories)){
                foreach ($seller_categories as $key => $value) {
                    // code...
                    array_push($catArray, $value->product_category_id);
                }
            }
            $user_company_datas =  $user_with_shop->user_shop->id ?? null;
            $user_company_photos =  UserShopPhoto::where('user_shop_id', $user_company_datas)->get();
            $user_shop_trans_datas =  UserShopTranslation::where('user_shop_id',$user_shop_id )->get();
            // dd($abc);

            return view('admin.users.selleredit', compact('user_shop', 'user', 'seller_categories', 'categories', 'catArray', 'user_company_photos','user_shop_trans_datas'));
        } else {
            return view('admin.users.edit', compact('user'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        // dd($request->all());
        if ($request->has('change_status')) {
            if ($request->time == "24") {
                // $user->status = 0;
                $user->deactivated_till = now()->addHours(24);
                $this->UserActivatedDeActivated($user);
                $message = __("User deactivated successfully for 24 hours");
            } elseif ($request->time == "48") {
                // $user->status = 0;
                $user->deactivated_till = now()->addHours(48);
                $this->UserActivatedDeActivated($user);
                $message = __("User deactivated successfully for 48 hours");
            } elseif ($request->time == "0") {
                // $user->status = 0;
                $user->deactivated_till = now()->addYears(5);
                $this->UserActivatedDeActivated($user);
                $message = __("User deactivated successfully permananetly");
            } else {
                // $user->status = 1;
                $user->deactivated_till = null;

                $this->UserActivatedDeActivated($user);
                $message = __("User activated successfully ");
            }
            # Change the status of the Magazine
            $user->save();
            $message = $message;
            return back()->withSuccess(__($message));
        }
        if ($request->has('change_seller_status')) {

            if ($request->time == "24") {
                // $user->status = 0; #2means 24hours deactivate
                $user->deactivated_till = now()->addHours(24);
                $this->UserActivatedDeActivated($user);

                $message = __("Seller deactivated successfully for 24 hours");
            } elseif ($request->time == "48") {
                // $user->status = 0; #3means 48hours deactivate
                $user->deactivated_till = now()->addHours(48);

                $this->UserActivatedDeActivated($user);

                $message = __("Seller deactivated successfully for 48 hours");
            } elseif ($request->time == "0") {
                // $user->status = 0; #0means permanent deactivate
                $user->deactivated_till = now()->addYears(5);

                $this->UserActivatedDeActivated($user);

                $message = __("Seller deactivated successfully permananetly");
            } else {
                // $user->status = 1;
                $user->deactivated_till = null;

                $this->UserActivatedDeActivated($user);

                $message = __("Seller activated successfully");
            }
            $user->save();
            $message = $message;
            return back()->withSuccess(__($message));
        }
        if ($request->has('change_admin_status')) {
            $user->status = $user->status ? 0 : 1;
            $user->save();
            $message = $user->status ?  __('Admin activated successfully') :  __('Admin deactivated successfully');
            return back()->withSuccess(__($message));
        }
        if ($user->hasRole([Roles::ADMIN])) {
            // dd();
            $request->validate([
                // 'status'        => ['required', 'in:0,1'],

                'role'          => ['required', 'in:admin'],
            ]);

            // $user->status = $request->input('status');
            $user->name = $request->name;
            $user->email = $request->email;

            $user->save();
            return redirect()->route('admin.users.systemUsers')->withSuccess(__('Admin updated successfully'));
        } else {
            # Handle Verify Vendor Action
            if ($request->has('verify_vendor')) {
                $verify_vendor = $request->input('verify_vendor');
                if ($verify_vendor === 'approve') {
                    $user->seller_verified = 1;
                    $message = 'Seller approved successfully';
                    $email = $user->email;
                    event(new SellerApproveDisapprove($user));
                } else {
                    $user->seller_verified = 0;
                    event(new SellerApproveDisapprove($user));
                    $message = 'Seller disapproved successfully';
                }

                # Save user records and return message
                $user->save();
                return redirect()->route('admin.users.index', ['type' => $user->role])
                    ->withSuccess(__($message));
            }
            $cur_with_code = array(
            'AF' => 'AFN','AL' => 'ALL','DZ' => 'DZD','AS' => 'USD','AD' => 'EUR','AO' => 'AOA','AI' => 'XCD','AQ' => 'XCD','AG' => 'XCD','AR' => 'ARS','AM' => 'AMD','AW' => 'AWG','AU' => 'AUD','AT' => 'EUR','AZ' => 'AZN','BS' => 'BSD','BH' => 'BHD','BD' => 'BDT','BB' => 'BBD','BY' => 'BYR','BE' => 'EUR','BZ' => 'BZD','BJ' => 'XOF','BM' => 'BMD','BT' => 'BTN','BO' => 'BOB','BA' => 'BAM','BW' => 'BWP','BV' => 'NOK','BR' => 'BRL','IO' => 'USD','BN' => 'BND','BG' => 'BGN','BF' => 'XOF','BI' => 'BIF','KH' => 'KHR','CM' => 'XAF','CA' => 'CAD','CV' => 'CVE','KY' => 'KYD','CF' => 'XAF','TD' => 'XAF','CL' => 'CLP','CN' => 'CNY','HK' => 'HKD','CX' => 'AUD','CC' => 'AUD','CO' => 'COP','KM' => 'KMF','CG' => 'XAF','CD' => 'CDF','CK' => 'NZD','CR' => 'CRC','HR' => 'HRK','CU' => 'CUP','CY' => 'EUR','CZ' => 'CZK','DK' => 'DKK','DJ' => 'DJF','DM' => 'XCD','DO' => 'DOP','EC' => 'ECS','EG' => 'EGP','SV' => 'SVC','GQ' => 'XAF','ER' => 'ERN','EE' => 'EUR','ET' => 'ETB','FK' => 'FKP','FO' => 'DKK','FJ' => 'FJD','FI' => 'EUR','FR' => 'EUR','GF' => 'EUR','TF' => 'EUR','GA' => 'XAF','GM' => 'GMD', 'GE' => 'GEL','DE' => 'EUR','GH' => 'GHS','GI' => 'GIP','GR' => 'EUR','GL' => 'DKK','GD' => 'XCD','GP' => 'EUR','GU' => 'USD','GT' => 'QTQ','GG' => 'GGP','GN' => 'GNF','GW' => 'GWP','GY' => 'GYD','HT' => 'HTG','HM' => 'AUD','HN' => 'HNL','HU' => 'HUF','IS' => 'ISK','IN' => 'INR','ID' => 'IDR','IR' => 'IRR','IQ' => 'IQD','IE' => 'EUR','IM' => 'GBP','IL' => 'ILS','IT' => 'EUR','JM' => 'JMD','JP' => 'JPY','JE' => 'GBP','JO' => 'JOD','KZ' => 'KZT','KE' => 'KES','KI' => 'AUD','KP' => 'KPW','KR' => 'KRW','KW' => 'KWD','KG' => 'KGS','LA' => 'LAK','LV' => 'EUR','LB' => 'LBP','LS' => 'LSL','LR' => 'LRD','LY' => 'LYD','LI' => 'CHF','LT' => 'EUR','LU' => 'EUR','MK' => 'MKD','MG' => 'MGF','MW' => 'MWK','MY' => 'MYR','MV' => 'MVR','ML' => 'XOF','MT' => 'EUR','MH' => 'USD','MQ' => 'EUR','MR' => 'MRO','MU' => 'MUR','YT' => 'EUR','MX' => 'MXN','FM' => 'USD','MD' => 'MDL','MC' => 'EUR','MN' => 'MNT','ME' => 'EUR','MS' => 'XCD','MA' => 'MAD','MZ' => 'MZN','MM' => 'MMK','NA' => 'NAD','NR' => 'AUD','NP' => 'NPR','NL' => 'EUR','AN' => 'ANG','NC' => 'XPF','NZ' => 'NZD','NI' => 'NIO','NE' => 'XOF','NG' => 'NGN','NU' => 'NZD','NF' => 'AUD','MP' => 'USD','NO' => 'NOK','OM' => 'OMR','PK' => 'PKR','PW' => 'USD','PA' => 'PAB','PG' => 'PGK','PY' => 'PYG','PE' => 'PEN','PH' => 'PHP','PN' => 'NZD','PL' => 'PLN','PT' => 'EUR','PR' => 'USD','QA' => 'QAR','RE' => 'EUR','RO' => 'RON','RU' => 'RUB','RW' => 'RWF','SH' => 'SHP','KN' => 'XCD','LC' => 'XCD','PM' => 'EUR','VC' => 'XCD','WS' => 'WST','SM' => 'EUR','ST' => 'STD','SA' => 'SAR','SN' => 'XOF','RS' => 'RSD','SC' => 'SCR','SL' => 'SLL','SG' => 'SGD','SK' => 'EUR','SI' => 'EUR','SB' => 'SBD','SO' => 'SOS','ZA' => 'ZAR','GS' => 'GBP','SS' => 'SSP','ES' => 'EUR','LK' => 'LKR','SD' => 'SDG','SR' => 'SRD','SJ' => 'NOK','SZ' => 'SZL','SE' => 'SEK','CH' => 'CHF','SY' => 'SYP','TW' => 'TWD','TJ' => 'TJS','TZ' => 'TZS','TH' => 'THB','TG' => 'XOF','TK' => 'NZD','TO' => 'TOP','TT' => 'TTD','TN' => 'TND','TR' => 'TRY','TM' => 'TMT','TC' => 'USD','TV' => 'AUD','UG' => 'UGX','UA' => 'UAH','AE' => 'AED','GB' => 'GBP','US' => 'USD','UM' => 'USD','UY' => 'UYU','UZ' => 'UZS','VU' => 'VUV','VE' => 'VEF','VN' => 'VND','VI' => 'USD','WF' => 'XPF','EH' => 'MAD','YE' => 'YER','ZM' => 'ZMW','ZW' => 'ZWD',
            );
            # Handle User Update Action
            if ($user->role == "customer") {
                $user_id = $user->id  ?? null;
                $request->validate([
                    'phone'         => ['required', 'digits_between:8,12'],
                    'email'         => ['required', 'email', 'max:35'],
                    'name'     => ['required', 'string', 'min:4', 'max:30'],
                    'country'       => ['required', 'string'],
                    'state'       => ['required', 'string'],
                    'city'       => ['required', 'string'],
                ]);

                $ios3_country = CountryCodeHelper::map($request['country'], CountryCodes::ALPHA_3);
                $currency =$cur_with_code[$request['country']] ?? null;

                # Update User
                $user->phone = $request->phone;
                $user->name = $request->name;
                $user->email = $request->email;
                $user->currency=$currency;
                $user->country = $ios3_country;
                $user->state = $request->state;
                $user->city = $request->city;
                $user->address_line_1= $request->address;
                $user->address_line_2 = $request->address2;
                $user->save();

                #user_address
                $user_addr_update_create= UserAddress::updateOrCreate([
                    'user_id'   => $user_id,
                ],[
                    'name' => $request->name,'email' => $request->email, 'phone' => $request->phone, 'country' => $ios3_country, 'state' => $request->state,'city'=>$request->city ,'address_1' => $request->address, 'address_2' => $request->address2
                ]); 
                #end
            }
            if ($user->role == "seller") {
                $user_id = $user->id  ?? null;
                $user_shop = UserShop::where('user_id', $user_id)->first();
                $user_shop_id = $user_shop->id ?? null;
                $imageCount = UserShopPhoto::where('user_shop_id', $user_shop_id)->get()->count();
                $imageCount = 5 - $request->images_count;
                #validation
                $request->validate([
                    'name'    =>  ['required', 'string', 'min:4', 'max:30'],
                    'email'   => ['required', 'email', 'max:35'],
                    'phone'   => ['required', 'digits_between:8,15'],
                    'country'       => ['required', 'string'],
                    'state'       => ['required', 'string'],
                    'city'       => ['required', 'string'],
                    'parent_id' => ['required', 'exists:product_categories,id'],
                    'photos'          => ['array', "max:$imageCount"],
                    'photos.*'        => ['image', 'mimes:jpeg,png,jpg,gif', 'max:2500'],
                ]);

                $ios3_country = CountryCodeHelper::map($request['country'], CountryCodes::ALPHA_3);
                $currency =$cur_with_code[$request['country']] ?? null;

                if ($request->hasFile('file')) {
                    $extension = $request->file->extension();
                    $file_path = $request->file->store('seller/id/card', 'public');
                } else {
                    $file_path = $user->image;
                }
                $user->name = $request->name;
                $user->email = $request->email;
                $user->phone = $request->phone;
                $user->currency=$currency;
                $user->country = $ios3_country;
                $user->state = $request->state;
                $user->city = $request->city;
                $user->address_line_1 = $request->address;
                $user->address_line_2 = $request->address2;
                $user->image = $file_path ?? null;
                $user->save();


                #user_address
                $user_addr_update_create= UserAddress::updateOrCreate([
                    'user_id'   => $user_id,
                ],[
                    'name' => $request->name,'email' => $request->email, 'phone' => $request->phone, 'country' => $ios3_country, 'state' => $request->state,'city'=>$request->city ,'address_1' => $request->address, 'address_2' => $request->address2
                ]); 
                #end
    
                $sellerCount = SellerProductCategory::where('user_shop_id', $user_shop_id)->get()->count();
                if ($sellerCount > 0) {
                    SellerProductCategory::where('user_shop_id', $user_shop_id)->delete();
                }
                if(!empty($request->parent_id) && !empty($user_shop)){
                    foreach ($request->parent_id as $val) {
                        $seller_product_details = SellerProductCategory::create([
                            'user_shop_id'       => $user_shop_id,
                            'product_category_id'   => $val,
                        ]);
                    }
                }

                $weekMap = [
                    0 => 'Su',
                    1 => 'Mo',
                    2 => 'Tu',
                    3 => 'We',
                    4 => 'Th',
                    5 => 'Fr',
                    6 => 'Sa',
                ];
                $dayOfTheWeek = \Carbon\Carbon::now()->dayOfWeek;
                $weekday = $weekMap[$dayOfTheWeek];
                #UserShop UpdateorCreate
                $user_shop_update_create= UserShop::updateOrCreate([
                    'user_id'   => $user_id,
                ],[
                    'shop_name' => $request->name, 'registration_no' => $request->license, 'shop_email' => $request->email, 'shop_contact' => $request->phone, 'country' => $request->country, 'state' => $request->state, 'address_1' => $request->address, 'address_2' => $request->address2, 'working_hours_from' => $request->work_hours_from,'working_hours_to' => $request->work_hours_to, 'working_days' => $weekday
                ]); 
                #end

                #user_address
                $user_addr_update_create= UserAddress::updateOrCreate([
                    'user_id'   => $user_id,
                ],[
                    'name' => $request->name,'email' => $request->email, 'phone' => $request->phone, 'country' => $request->country, 'state' => $request->state,'city'=>$request->city ,'address_1' => $request->address, 'address_2' => $request->address2
                ]); 
                #end

                #multilingual entry
                if($request->ar !=""){
                    if(isset($request->ar['services']) ) {
                        $services = $request->ar['services'];
                        $services = array_combine($services['value'], $services['value']);
                    }
                    $check_user_shop= UserShopTranslation::where('user_shop_id',$user_shop_id)->get()->count();
                    if($check_user_shop > 0){
                        UserShopTranslation::where('user_shop_id',$user_shop_id)->where('locale','ar')->update(['overview'=>$request->ar['overview'],'services'=>json_encode(array_filter($services)) ]);
                    }else{
                        UserShopTranslation::create([
                            'locale'=>'ar',
                            'user_shop_id'=>$user_shop_update_create->id,
                            'overview'=>$request->ar['overview'],
                            'services'=>json_encode(array_filter($services))
                        ]);
                    }
                }
                if($request->en !=""){
                    if(isset($request->en['services']) ) {
                        $services = $request->en['services'];
                        $services = array_combine($services['value'], $services['value']);
                    }
                    if($check_user_shop > 0){
                        UserShopTranslation::where('user_shop_id',$user_shop_id)->where('locale','en')->update(['overview'=>$request->en['overview'],'services'=>json_encode(array_filter($services))]);
                    }else{
                        UserShopTranslation::create([
                            'locale'=>'en',
                            'user_shop_id'=>$user_shop_update_create->id,
                            'overview'=>$request->en['overview'],
                            'services'=>json_encode(array_filter($services))
                        ]);
                    }
                }

                if ($request->hasfile('photos')) {
                    foreach ($request->file('photos') as $file) {
                        $file_path = $file->store('shops_photos', 'public');
                        $validator['photo'] = $file_path;

                        $user_company_photos = UserShopPhoto::create([
                            'user_shop_id'    => $user_shop_update_create->id,
                            'photo'   => $file_path,
                        ]);
                    }
                }

                $delShopString = $request->pro_shop_img_id;
                $delShopArray = explode(',', $delShopString);
                foreach ($delShopArray as $shopVal) {
                    UserShopPhoto::where('id', $shopVal)->delete();
                }

                if ($request->hasFile('shop_photos')) {
                    $shopimages = array_values($request->file('shop_photos'));
                    foreach ($shopimages as $key => $file) {
                        $updateImage = $request->updateShopImage;
                        $updateImageArray = explode(',', $updateImage);
                        $extension = $file->extension();
                        $rand = rand('000', '999');
                        $file_path = $file->store('shop_photos', 'public');
                        $success = UserShopPhoto::where('id', $updateImageArray[$key])->update(['photo' => $file_path]);
                    }
                }
            }
            if ($user->hasRole([Roles::CUSTOMER, Roles::SELLER])) {
                return redirect()->route('admin.users.index', ['type' => $user->role])
                    ->withSuccess(__(" updated successfully"));
            } else {
                return redirect()->route('admin.users.index')->withSuccess(__('Admin updated successfully'));
            }
        }
    }

    /**
     * function used for call event of user activated deactivated.
     */
    public function UserActivatedDeActivated($user)
    {
        event(new UserActivatedDeActivated($user));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }

    /**
     * function used for show system users.
     */
    public function system_users(Request $request)
    {
        $users = User::role(Roles::ADMIN)->orderBy('id', 'DESC')->get();
        return view('admin.users.systemUsers', compact('users'));
    }

    /**
     * function used for create system users.
     */
    public function createSystemUser()
    {
        $permissions = Permission::all();
        return view('admin.users.createSystemUser', compact('permissions'));
    }

    /**
     * function used for store system users.
     */
    public function storesystemuser(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:4', 'max:30'],
            'email' => ['required', 'email', 'unique:users', 'max:35'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'password_confirmation' => ['required'],
            'phone'   => ['required', 'digits_between:8,15', 'unique:users,phone'],
        ]);

        try {
            $validated['password'] = Hash::make($validated['password']);
            $validated['name'] = $validated['full_name'];

            # Create User Account
            $user = User::create($validated);

            $user->syncRoles([Roles::ADMIN]);

            return redirect()->route('admin.users.systemUsers')->withSuccess(__('Account created successfully'));
        } catch (\Throwable $th) {
            logger($th->getMessage());
        }
    }

    /**
     * function used for send reset link.
     */
    public function sendPasswordResetLink(Request $request)
    {
        try {
            $customer_controller =
                app(\App\Http\Controllers\Customer\ResetPasswordController::class);

            if (!$customer_controller) {
                throw new \Exception('Class Controllers\Customer not found');
            }

            $respone = $customer_controller->sendresetlink($request);

            $respone = $respone->getData();

            if (isset($respone->STATUS) && $respone->STATUS == 1) {
                return back()->withSuccess(__('Mail sent'));
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
        }

        return back()->withError(__('Mail could not be sent!'));
    }
}
