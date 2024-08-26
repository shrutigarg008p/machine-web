<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Vars\Roles;
use App\Models\User;
use App\Models\ProductCategory;
use App\Models\SellerProductCategory;
use App\Models\SellerProduct;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Graftak\CountryCodeHelper;
use Monarobase\CountryList\CountryListFacade;
use Graftak\CountryCodeHelper\CountryCodes;
use Illuminate\Support\Facades\Hash;
use App\Models\UserShop;
use App\Models\UserShopPhoto;
use App\Models\Translation\UserShopTranslation;
use App\Events\NewSellerRegistered;
use App\Models\VerifyUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\OrderSeller;
use App\Models\Order;
use App\Models\ChatMessage;
use App\Models\HelpSupportMessage;

class VendorController extends Controller
{
    //

    public function index()
    {
        
        $total = (object) [
            'sellers' => User::role(Roles::SELLER)->count(),
        ];
        // dd($total);
        $user = Auth::user();
        DB::enableQueryLog();
        $quotation_total = OrderSeller::where('seller_id',$user->id)->where('status','quotation')->count();
        $quotations = OrderSeller::where('seller_id',$user->id)->orderBy('id','DESC')->get();
        foreach ($quotations as $key => $value) {
            $order =  Order::where('id',$value->order_id)->first();
	    $usershop= UserShop::where('user_id',$value->seller_id)->first();
            $value->orders   = $order;
	    $value->usershop= $usershop;
         }

        $msg_total = ChatMessage::where('user_id','!=',$user->id)->count();
        $order_total = Order::leftJoin('order_sellers','orders.id','order_sellers.order_id')->where('order_sellers.seller_id',$user->id)->where('order_sellers.status','confirmed')->count();

       $orders = Order::leftJoin('order_sellers','orders.id','order_sellers.order_id')->where('order_sellers.seller_id',$user->id)->orderBy('id','DESC')->select('orders.*','order_sellers.status as order_status')->get();
         foreach ($orders as $key => $value) {
           $user=User::where('id',$value->customer_id)->first();
                       $value->user=$user;
        }
	
	
        return view('seller.dashboard.index', compact('total','quotations','quotation_total','msg_total','orders','order_total'));
    }
    public function sellerCreate()
    {
        $categories = ProductCategory::query();
        $categories = $categories
            ->latest()
            ->get();
        return view('seller.users.seller_create', compact('categories'));
    }
    public function store(Request $request){
        // dd($request->all());
        if ($request->role == "seller") {
                // dd($request->all());
                $validator = $request->validate([
                    'name'    =>  ['required', 'string', 'min:4', 'max:30'],
                    'license'    =>  ['required', 'regex:/^[\w-]*$/'],
                    'email'   => ['required', 'email', 'unique:users', 'max:25'],
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

                $cur_with_code = array(
            'AF' => 'AFN','AL' => 'ALL','DZ' => 'DZD','AS' => 'USD','AD' => 'EUR','AO' => 'AOA','AI' => 'XCD','AQ' => 'XCD','AG' => 'XCD','AR' => 'ARS','AM' => 'AMD','AW' => 'AWG','AU' => 'AUD','AT' => 'EUR','AZ' => 'AZN','BS' => 'BSD','BH' => 'BHD','BD' => 'BDT','BB' => 'BBD','BY' => 'BYR','BE' => 'EUR','BZ' => 'BZD','BJ' => 'XOF','BM' => 'BMD','BT' => 'BTN','BO' => 'BOB','BA' => 'BAM','BW' => 'BWP','BV' => 'NOK','BR' => 'BRL','IO' => 'USD','BN' => 'BND','BG' => 'BGN','BF' => 'XOF','BI' => 'BIF','KH' => 'KHR','CM' => 'XAF','CA' => 'CAD','CV' => 'CVE','KY' => 'KYD','CF' => 'XAF','TD' => 'XAF','CL' => 'CLP','CN' => 'CNY','HK' => 'HKD','CX' => 'AUD','CC' => 'AUD','CO' => 'COP','KM' => 'KMF','CG' => 'XAF','CD' => 'CDF','CK' => 'NZD','CR' => 'CRC','HR' => 'HRK','CU' => 'CUP','CY' => 'EUR','CZ' => 'CZK','DK' => 'DKK','DJ' => 'DJF','DM' => 'XCD','DO' => 'DOP','EC' => 'ECS','EG' => 'EGP','SV' => 'SVC','GQ' => 'XAF','ER' => 'ERN','EE' => 'EUR','ET' => 'ETB','FK' => 'FKP','FO' => 'DKK','FJ' => 'FJD','FI' => 'EUR','FR' => 'EUR','GF' => 'EUR','TF' => 'EUR','GA' => 'XAF','GM' => 'GMD', 'GE' => 'GEL','DE' => 'EUR','GH' => 'GHS','GI' => 'GIP','GR' => 'EUR','GL' => 'DKK','GD' => 'XCD','GP' => 'EUR','GU' => 'USD','GT' => 'QTQ','GG' => 'GGP','GN' => 'GNF','GW' => 'GWP','GY' => 'GYD','HT' => 'HTG','HM' => 'AUD','HN' => 'HNL','HU' => 'HUF','IS' => 'ISK','IN' => 'INR','ID' => 'IDR','IR' => 'IRR','IQ' => 'IQD','IE' => 'EUR','IM' => 'GBP','IL' => 'ILS','IT' => 'EUR','JM' => 'JMD','JP' => 'JPY','JE' => 'GBP','JO' => 'JOD','KZ' => 'KZT','KE' => 'KES','KI' => 'AUD','KP' => 'KPW','KR' => 'KRW','KW' => 'KWD','KG' => 'KGS','LA' => 'LAK','LV' => 'EUR','LB' => 'LBP','LS' => 'LSL','LR' => 'LRD','LY' => 'LYD','LI' => 'CHF','LT' => 'EUR','LU' => 'EUR','MK' => 'MKD','MG' => 'MGF','MW' => 'MWK','MY' => 'MYR','MV' => 'MVR','ML' => 'XOF','MT' => 'EUR','MH' => 'USD','MQ' => 'EUR','MR' => 'MRO','MU' => 'MUR','YT' => 'EUR','MX' => 'MXN','FM' => 'USD','MD' => 'MDL','MC' => 'EUR','MN' => 'MNT','ME' => 'EUR','MS' => 'XCD','MA' => 'MAD','MZ' => 'MZN','MM' => 'MMK','NA' => 'NAD','NR' => 'AUD','NP' => 'NPR','NL' => 'EUR','AN' => 'ANG','NC' => 'XPF','NZ' => 'NZD','NI' => 'NIO','NE' => 'XOF','NG' => 'NGN','NU' => 'NZD','NF' => 'AUD','MP' => 'USD','NO' => 'NOK','OM' => 'OMR','PK' => 'PKR','PW' => 'USD','PA' => 'PAB','PG' => 'PGK','PY' => 'PYG','PE' => 'PEN','PH' => 'PHP','PN' => 'NZD','PL' => 'PLN','PT' => 'EUR','PR' => 'USD','QA' => 'QAR','RE' => 'EUR','RO' => 'RON','RU' => 'RUB','RW' => 'RWF','SH' => 'SHP','KN' => 'XCD','LC' => 'XCD','PM' => 'EUR','VC' => 'XCD','WS' => 'WST','SM' => 'EUR','ST' => 'STD','SA' => 'SAR','SN' => 'XOF','RS' => 'RSD','SC' => 'SCR','SL' => 'SLL','SG' => 'SGD','SK' => 'EUR','SI' => 'EUR','SB' => 'SBD','SO' => 'SOS','ZA' => 'ZAR','GS' => 'GBP','SS' => 'SSP','ES' => 'EUR','LK' => 'LKR','SD' => 'SDG','SR' => 'SRD','SJ' => 'NOK','SZ' => 'SZL','SE' => 'SEK','CH' => 'CHF','SY' => 'SYP','TW' => 'TWD','TJ' => 'TJS','TZ' => 'TZS','TH' => 'THB','TG' => 'XOF','TK' => 'NZD','TO' => 'TOP','TT' => 'TTD','TN' => 'TND','TR' => 'TRY','TM' => 'TMT','TC' => 'USD','TV' => 'AUD','UG' => 'UGX','UA' => 'UAH','AE' => 'AED','GB' => 'GBP','US' => 'USD','UM' => 'USD','UY' => 'UYU','UZ' => 'UZS','VU' => 'VUV','VE' => 'VEF','VN' => 'VND','VI' => 'USD','WF' => 'XPF','EH' => 'MAD','YE' => 'YER','ZM' => 'ZMW','ZW' => 'ZWD',
            );
                try {
                    # Create Seller User and store its Information

                    $ios3_country = CountryCodeHelper::map($validator['country'], CountryCodes::ALPHA_3);

                    $currency =$cur_with_code[$validator['country']] ?? null;


                    if ($request->hasFile('file')) {
                        $extension = $request->file->extension();

                        $file_path = $request->file->store('seller/id/card', 'public');
                        $validator['image'] = $file_path;
                    }
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
                    return redirect()->route('seller.index')->withSuccess(__('Account created successfully'));
                } catch (\Exception $e) {
                    logger($e->getMessage());
                }
        }
    }

    public function sendverifyMail($user, $user_id)
    {
        $verify_user = VerifyUser::create([
            'user_id' => $user_id,
            'token'     => sha1(time())
        ]);    
        event(new NewSellerRegistered($user));
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

                try {
                    \Mail::send(
                        'mails.seller.confirmemail',
                        array(
                            'name' => $user->name,
                            'email' => $user->email
                        ),
                        function ($message) use ($email) {
                        $message->to($email)->subject('Confirmation Email');
                        }
                    );
                } catch (\Exception $e) {
                    logger(' issue: ' . $e->getMessage());
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
            ->with(__('error', 'Something Went Wrong.'));
    }

    public function helpAndSupport(){
        return view('seller.account.help_support');
    }

    public function helpAndSupportStore(Request $request){
        $validator = $request->validate([
            'name'   => ['required', 'string','max:25'],
            'email'   => ['required', 'email','max:25'],
            'message' => ['required']
            ]);

            $help = new HelpSupportMessage();
            $help->name = $request->name;
            $help->email = $request->email;
            $help->message = $request->message;
            $help->save();
            return back()->withSuccess(__('Sent Successfully'));
    }
}
