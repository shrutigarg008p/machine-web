<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\EndPoints;
use App\Http\Resources\ShopResource;
use App\Models\OrderSeller;
use App\Models\User;
use App\Traits\APICall;
use App\Vars\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Throwable;
use Ixudra\Curl\Facades\Curl;
use Yajra\DataTables\DataTables;
use Auth;

class HomePageController extends Controller
{
    use APICall;

    public function loginEnd(Request $request)
    {
        return $request->all();
        if ($request->ajax()) {
            $result = APICall::callAPI('POST', EndPoints::LOGIN_CUSTOMER, json_encode($request->all()));
            $result1 = json_decode($result, true);
            if ($result1['status'] && !empty($result1['data'])) {
                if ($result1['data']['access_token'] && isset($result1['status'])) {
                    $auth_user = User::withoutGlobalScope('active')
                    ->where('email', $request->get('email_or_phone'))
                    ->orWhere('phone', $request->get('email_or_phone'))
                    ->first();
                    $this->auth->login($auth_user);
                }
            }
            return $result;
        } else {
            return redirect('/');
        }
    }

    // public function loginEnd(Request $request)
    // {
    //     $validated = $request->validate([
    //         'email_or_phone' => ['required', 'email'],
    //         'password' => ['required']
    //     ]);

    //     if( ! $this->auth->validate($validated) ) {
    //         return back()->withError(__('Invalid email address or password'));
    //     }

    //     $auth_user = User::where('email', $request->get('email'))->firstOrFail();

    //     if( intval($auth_user->status) === 0 ) {
    //         return back()->withError(__('Account inactive'));
    //     }
    //     /*for seller login*/  

    //     if( $auth_user->role == Roles::CUSTOMER) {
    //             // if customer,seller email not verified
    //             if( empty($auth_user->email_verified_at) ) {
    //                 return back()->withError(__('Please verify your email address'));
    //             }

    //             if( config('app.verify_otp') && empty($auth_user->otp_verified_at) ) {
    //                 return back()->withError(__('Please verify your phone number'));
    //             }
        
    //             // if account temporarily deactivated
    //             if( !empty($auth_user->deactivated_till) ) {
        
    //                 if( now()->le($auth_user->deactivated_till) ) {
    //                     return back()->withError(__('Your account is temporarily blocked. Please try again after some time.'));
    //                 }
    //             }

    //             $redirect = redirect('home');
    //     }

    //     $this->auth->login($auth_user, $request->has('remember_me'));

    //     return $redirect->withSuccess(__('Logged in successfully'));
    // }

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

    public function index()
    {
        $apiProductCategory = Http::get(env('APP_URL') . EndPoints::PRODUCT_CATEGORY_LISTING,[
            'parent_only' => 1
        ]);
        $apiShop = Http::get(env('APP_URL') . EndPoints::SHOP_LISTING);
        try {
            $user = Auth::user() ?? null;
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
        return view('customer.index', compact('productCatListing', 'shopListing', 'favshopList'));
    }

    public function staticcontent(Request $request)
    {
        try {
            $staticContent = Http::post(env('APP_URL') . EndPoints::STATIC_CONTENT, [
                'slug' => \Request::segment(1)
            ]);
            $staticcontent = $staticContent->collect()['data'];
            return view('customer.staticcontent', compact('staticcontent'));
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public function sellerDashboard(Request $request)
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
                dd($SellerQuotation);
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

    public function sessionlogout(){
        Auth::logout();
        return redirect(url('/'));
    }
}
