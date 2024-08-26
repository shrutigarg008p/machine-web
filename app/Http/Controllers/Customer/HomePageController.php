<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Customer\EndPoints;
use App\Http\Resources\ShopResource;
use App\Http\Resources\CartItemResource;
use App\Models\OrderSeller;
use App\Models\Order;
use App\Models\User;
use App\Models\ShopRating;
use App\Models\CartItem;
use App\Models\ProductCategory;
use App\Traits\APICall;
use App\Vars\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Throwable;
use Ixudra\Curl\Facades\Curl;
use Yajra\DataTables\DataTables;
use Auth;
use App\Models\HelpSupportMessage;
use App\Models\ChatMessage;
use App\Models\Subscribe;
use Illuminate\Support\Facades\Validator;
use App\Models\UserShop;

class HomePageController extends Controller
{
    use APICall;

    // public function loginEnd(Request $request)
    // {
    //     if ($request->ajax()) {
    //         $result = APICall::callAPI('POST', EndPoints::LOGIN_CUSTOMER, json_encode($request->all(),true));
           
    //       return  $result1 = json_decode($result, true);
    //         if ($result1['status'] && !empty($result1['data'])) {
    //             if ($result1['data']['access_token'] && isset($result1['status'])) {
    //                 $auth_user = User::withoutGlobalScope('active')
    //                 ->where('email', $request->get('email_or_phone'))
    //                 ->orWhere('phone', $request->get('email_or_phone'))
    //                 ->first();
    //                 $this->auth->login($auth_user);
    //             }
    //         }
    //         return $result1;
    //     } else {
    //         return redirect('/');
    //     }
    // }

    public function loginEnd(Request $request)
    {   
        $validated = $request->validate([
            'email_or_phone' => ['required', 'max:191', 'email_or_phone'],
            'password' => ['required']
        ]);

        $email_phone = $request->get('email_or_phone');
        $field = boolval(filter_var($email_phone, FILTER_VALIDATE_EMAIL))
        ? 'email': 'phone';

        $credentials = [
            $field => $email_phone, 'password' => $request->get('password')
        ];
        if( ! $this->auth->validate($credentials) ) {
            return back()->withError(__('Invalid email address or password'));
        }
        //$this->auth->logoutOtherDevices($request->get('password'));
        $auth_user = User::where('email', $request->get('email_or_phone'))->orWhere('phone', $request->get('email_or_phone'))->firstOrFail();

        if( intval($auth_user->status) === 0 ) {
            return back()->withError(__('Account inactive'));
        }
        /*for seller login*/  

        if( $auth_user->role == Roles::CUSTOMER) {
                // if customer,seller email not verified
                if( empty($auth_user->email_verified_at) ) {
                    return back()->withError(__('Please verify your email address'));
                }

                if( config('app.verify_otp') && empty($auth_user->otp_verified_at) ) {
                    return back()->withError(__('Please verify your phone number'));
                }
        
                // if account temporarily deactivated
                if( !empty($auth_user->deactivated_till) ) {
        
                    if( now()->le($auth_user->deactivated_till) ) {
                        return back()->withError(__('Your account is temporarily blocked. Please try again after some time.'));
                    }
                }
        }

        $this->auth->login($auth_user, $request->has('remember_me'));

        return json_encode(['status'=> 1,'type'=>$auth_user->role,'message'=>'Logged in successfully']);
    }

    public function registerloginEnd(Request $request)
    {
        if ($request->ajax()) {
            $result = APICall::callAPI('POST', EndPoints::REGISTER_LOGIN_USER, json_encode($request->all()));
            $result1 = json_decode($result, true);
            if ($result1['status'] && !empty($result1['data'])) {
                if ($result1['data']['access_token'] && isset($result1['status'])) {
                    Session::put('access_token', $result1['data']['access_token']);
                    Session::put('user_data', $result1['data']['user']);
                    Session::put('user_login_email', $result1['data']['user']['email']);
                    Session::put('user_login_name', $result1['data']['user']['name']);
                    Session::put('user_login_rember', true);
                }
            }
            return $result;
        } else {
            return redirect('/');
        }
    }

    public function loginViaOtpEnd(Request $request)
    {
        if ($request->ajax()) {
            $result = APICall::callAPI('POST', EndPoints::VERIFY_VIA_OTP, json_encode($request->all()));
            $result1 = json_decode($result, true);
            if ($result1['status'] && !empty($result1['data'])) {
                if ($result1['data']['access_token'] && isset($result1['status'])) {
                    $auth_user = User::where('email', $request->get('email'))->firstOrFail();
                    $this->auth->login($auth_user, $request->has('remember_me'));
                }
            }

            return $result;
        } else {
            return redirect('/');
        }
    }

    public function index(Request $request)
    {
	$user = Auth::user() ?? null;
        if(!empty($user)){
            if($user->role == Roles::SELLER) {
                return redirect(route('seller.dashboard'));
            }
        }
        $apiProductCategory = Http::get(config('app.url'). EndPoints::PRODUCT_CATEGORY_LISTING,[
            'parent_only' => 1
        ]);
        $apiShop = Http::get(config('app.url'). EndPoints::SHOP_LISTING);
        try {
            if(!empty($user)){
                $shops = $user->favourite_shops()
                ->with(['categories'])
                ->paginate(15)
                ->through(function($shop) {
                    $category = $shop->categories->first();
    
                    $shop->category = $category
                        ? [ 'id' => $category->id, 'title' => $category->title ]
                        : null;
    
                    return $shop;
                });
            $favshopList = json_decode(json_encode(ShopResource::collection($shops)), true);
            }else{
                $favshopList = [];
            }
            $productCatListing = $apiProductCategory->collect()['data'];
            $shopListing = $apiShop->collect()['data'];
        } catch (Throwable $e) {
            $productCatListing = [];
            $shopListing = [];
        } 
          $user_shops = UserShop::whereNotNull('address_1')->distinct('address_1')->select('address_1','latitude','longitude','id')->take('15')->get();
        return view('customer.index', compact('productCatListing', 'shopListing', 'favshopList','user_shops'));
    }

    public function staticcontent(Request $request)
    {
        try {
           $staticContent = Http::post(config('app.url'). EndPoints::STATIC_CONTENT, [
                'slug' => \Request::segment(1)
            ]);
             $staticcontent = $staticContent->collect()['data'];
            return view('customer.staticcontent', compact('staticcontent'));
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }


    public function dashboard()
    {
        $total = (object) [
            'customer' => User::role(Roles::CUSTOMER)->count(),
        ];
        $user = Auth::user();
        $order_count =  Order::leftJoin('order_sellers','orders.id','order_sellers.order_id')
                                ->where('orders.customer_id',$user->id)
                                ->where('order_sellers.status','confirmed')->count();
        $msg_total = ChatMessage::where('user_id',$user->id)->count();
        $quotation_total = Order::leftJoin('order_sellers','orders.id','order_sellers.order_id')
                            ->where('orders.customer_id',$user->id)
                            ->where('order_sellers.status','quotation')->count();

        return view('customer.inner.dashboard',compact('order_count','msg_total','quotation_total'));
    }

    public function customerDashboard(Request $request)
    {
        try {
            if (!empty(Session::get('access_token'))) {
                $access_token = Session::get('access_token');
                if (!empty($access_token)) {
                    $apiSellerQuotation = Http::withToken($access_token)->get(env('APP_URL') . EndPoints::SELLER_ORDER_QUOTATIONS);
                }
            }
            $SellerQuotation = $apiSellerQuotation->collect()['data'];
            return view('customer.seller.dashboard', compact('SellerQuotation'));
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public function acceptOrRejectBid(Request $request)
    {
        try {
            if (!empty(Session::get('access_token'))) {
                $access_token = Session::get('access_token');
                if (!empty($access_token)) {
                    $apiAcceptRejectBid = Http::withToken($access_token)->post(env('APP_URL') . EndPoints::ACCEPT_REJECT_BID, [
                        'bid_id' => 1,
                        'accepted' => 1
                    ]);
                }
            }
            $acceptbid = $apiAcceptRejectBid->collect();
            return json_encode($acceptbid->toArray());
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public function getRfqList(Request $request){
        // if ($request->ajax()) {
            try {
                // if (!empty(Session::get('access_token'))) {
                //     $access_token = Session::get('access_token');
                //     if (!empty($access_token)) {
                //         $apiSellerQuotation = Http::withToken($access_token)->get(env('APP_URL') . EndPoints::SELLER_ORDER_QUOTATIONS,[
                //             'status'=>$request->type
                //         ]);
                //     }
                // }
                // $SellerQuotation = $apiSellerQuotation->collect()['data'];
                $user = Auth::user();
                $SellerQuotation = OrderSeller::where('seller_id',$user->id)->get();
                // dd($SellerQuotation);
                return Datatables::of($SellerQuotation)
                ->addIndexColumn()
                ->addColumn('action', function($row) use($request){
                    $id = $row['id'];
                    if($request->type == 'pending'){
                        $btn ='<a href="javascript:void(0);" class="accept" onClick=acceptorrehectbid('.$id.',"1") style="margin-right:10px">Accept</a><a href="javascript:void(0);" onClick=acceptorrehectbid('.$id.',"-1") class="deny">Deny</a>'; 
                    }else if($request->type == 'confirmed'){
                        $btn = '<a href="javascript:void(0);" class="deny"><img src="'.asset("web/images/chat-icon-btn.png").' alt="">Chat</a>
                        <a href="javascript:void(0);" class="deny">Details</a>';
                    }else{
                        $btn ='<a href="'.url('getrfqdetail/'.base64_encode($id)).'" class="deny">Details</a>'; 
                    }     
                    return $btn;
                })
                ->rawColumns(['action'])
                ->make(true);   
            } catch (Throwable $e) {
                return $e->getMessage();
            }
        // }
    }

    // public function getRfqDetail(Request $request,$order_id){
    //     // $getorderID = base64_decode($order_id);
    //     $getorderID = $order_id;
    //     try {
    //         if (!empty(Session::get('access_token'))) {
    //             $access_token = Session::get('access_token');
    //             if (!empty($access_token)) {
    //                 $apigetRfqDetail = Http::withToken($access_token)->get(env('APP_URL') . EndPoints::ORDER_DETAILS,[
    //                     'order_id'=>$getorderID
    //                 ]);
    //             }
    //         }
    //         $RfqDetail = $apigetRfqDetail->collect()['data'];
    //         return view('customer.seller.rfq_detail',compact('RfqDetail'));
    //     } catch (Throwable $e) {
    //         return $e->getMessage();
    //     }
    // }

    public function sessionlogout(Request $request){
        $auth_user = $this->user();

        $this->auth->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
        return redirect()->route('main.home');
    }

    public function help_support(){
        return view('customer.inner.account.help_support');
    }

    public function help_support_store(Request $request){
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

    public function rating($seller_id,$shop_id){
        return view('customer.inner.rating',compact('seller_id','shop_id'));
    }

    public function rate(Request $request){
        $validator = $request->validate([
            'rate'   => ['required'],
	    'seller_id' => ['required'],
            'shop_id'   =>  ['required'],
            'review'   => ['nullable','max:255'],
            ]);
        $user = Auth::user();
        $input =  $request->all();
        $input['user_id'] = $user->id;
        ShopRating::create($input);
        return redirect(route('order'))->withSuccess(__('Thank You for Rating'));
    }

    public function searchShope(Request $request)
{

// dd("sdsddddd");
$lat = $request->get('latshop');
$lng = $request->get('lngshop');
$shoptitle = $request->get('shoptitle');
$distance = 1;

// $query = UserShop::getByDistance($lat, $lng, $distance);

    // if(empty($query)) {
    //   return view('articles.index', compact('categories'));
    // }

    // $ids = [];

    // //Extract the id's
    // foreach($query as $q)
    // {
    //   array_push($ids, $q->id);
    // }

    // // Get the listings that match the returned ids
    // $results = DB::table('user_shops')->whereIn( 'id', $ids)->orderBy('id', 'DESC')->paginate(3);        

    // $articles = $results;

    $result = APICall::callAPI('POST',config('app.url') .EndPoints::SHOP_CATEGORIES,json_encode([
            'parent_only' =>1
        ]));
          
        $categories = json_decode($result, true);

        return view('customer.product.shopcategory',compact('categories','lat','lng','shoptitle'));
        


}
 public function subscribe(Request $request){
    $validator = Validator::make($request->all(), [
        'email'=> ['required','email'],
    ]);
    if ($validator->fails()) {
        return $this->validation_error_response($validator);
    }
    $sub = Subscribe::where('email', $request->email)->first();
    if(empty($sub)){
     Subscribe::create($request->all());
    }
    return json_encode(['status'=> 1, 'message'=>'Thank you for Subscribe Us']);
 }
    public function dis(Request $request){
      return GetDrivingDistance(21.3181081,17.3983774,83.02217399999999,78.5582652);
      }

      public function sessionStore(Request $request){
          Session::put('lat', $request->lat);
          Session::put('long', $request->long);
         return 1;
      }

	public function testt(Request $request){
	return notificationFirebase("edddds","dd",468,"en");
	}
}
