<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserShop;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\OrderSeller;
use App\Models\SellerProduct;
use App\Models\Order;
use App\Models\ProductCategory;
use App\Models\ChatMessage;
use App\Models\UserShopPhoto;
use Illuminate\Validation\Rule;

use App\Http\Resources\UserAddressResource;

class ShopController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $user=Auth()->user() ?? null ;
        $user_shops =  UserShop::with(['categories'])->where('user_id',$user->id)->get();
        foreach ($user_shops as $key => $value) {
            
            $quotation_quotation_total = OrderSeller::where('shop_id',$value->id)->where('status','quotation')->count();
            $quotation_process_total = OrderSeller::where('shop_id',$value->id)->where('status','confirmed')->count();
            $quotation_close_total = OrderSeller::where('shop_id',$value->id)->where('status','delivered')->count();

            $close_order_pick_up_total = Order::leftJoin('order_sellers','orders.id','order_sellers.order_id')
                                                ->where('order_sellers.shop_id',$value->id)
                                                ->where('orders.delivery_type','pick-up')
                                                ->count();
            $close_order_delivery_total = Order::leftJoin('order_sellers','orders.id','order_sellers.order_id')
                                                ->where('order_sellers.shop_id',$value->id)
                                                ->where('orders.delivery_type','delivery')
                                                ->count();

            $active_sale_total = SellerProduct::where('shop_id',$value->id)->count();
            $on_sale_total = SellerProduct::where('shop_id',$value->id)->where('price_type','bid')->count();

            $msg_total = ChatMessage::where('user_id',$user->id)->count();
            
            $value->quotation_quotation_total = $quotation_quotation_total;
            $value->quotation_process_total = $quotation_process_total;
            $value->quotation_close_total = $quotation_close_total;

            $value->close_order_pick_up_total = $close_order_pick_up_total;
            $value->close_order_delivery_total = $close_order_delivery_total;

            $value->active_sale_total = $active_sale_total;
            $value->on_sale_total = $on_sale_total;


        }
        return view('seller/shop/index',compact('user_shops','user'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $categories = ProductCategory::withoutGlobalScope('active')->parentCategory()->latest()->get();
            
        return view('seller/shop/create',compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->all();
        //
        $validator = $request->validate([
	    'shop_name'     => ['required', 'string', 'min:4', 'max:50'],
            'owner_name'     => ['required', 'string', 'min:4', 'max:30'],
            'shop_phone'         => ['required', 'digits_between:8,12','unique:user_shops,shop_contact'],
            'shop_email'    => ['required', 'email', 'max:100','unique:user_shops'],
            'product_categories' => ['nullable', 'array', 'max:5'],
            'product_categories.*' => ['numeric',
                Rule::exists('product_categories','id')->where(function($query) {
                    $query->whereNull('parent_id');
                })
            ],
            'file' => ['nullable','max:5'],
        ]);

        $arr = $request->working_days;
        $weekday = implode(",",$arr);
        $user = Auth::user() ?? null;
        if(!empty($user))
        $store  = UserShop::create([
            'user_id'=>$user->id,
            'shop_owner'=>$validator['owner_name'],
            'shop_name'=>$validator['shop_name'],
            'shop_email'=>$validator['shop_email'],
            'shop_contact'=>$validator['shop_phone'],
            'registration_no'=>isset($validator['registration']) ? $validator['registration'] : '',
            'country'=>$request->country,
            'state'=>$request->state,
            'address_1'=>$request->address,
            'address_2'=>$request->address2,
            'id_document'=>$file_path ?? null,
            'working_days'=>$weekday,
            'working_hours_from' => $request->work_hours_from,'working_hours_to' => $request->work_hours_to,
            'latitude'=>$request->lat,
            'longitude'=>$request->lng
        ]);
        
        
        if ($request->hasfile('file')) {
            //return $store;
            foreach ($request->file('file') as $file) {
                $file_path = $file->store('shops_photos', 'public');
                $validator['file'] = $file_path;
                 $user_company_photos = UserShopPhoto::create([
                    'user_shop_id'    => $store->id,
                    'photo'   => $file_path,
                ]);
            }
        }

        if(!empty( $product_categories = (array)$request->get('product_categories'))) {
            $store->categories()
                ->sync($product_categories);
        }
        $store->load(['categories.shops']);

       if($store)
       return redirect()->route('seller.shops.index')->withSuccess(__('Shop saved successfully'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($shopid, User $user)
    {
        //
        $user_shop = UserShop::where('id',$shopid)->where('user_id',$user->id)->first();
        // dd($user_shop);
        return view('seller/shop/show',compact('user_shop','user'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($shopid,User $user)
    {
        $categories = ProductCategory::withoutGlobalScope('active')->parentCategory()->latest()->get();
        //
        //return $shopid;
        if(!empty($user) && !empty($shopid))
        $seller_shops = UserShop::with('categories')->where('id',$shopid)->where('user_id',$user->id)->first();
        $seller_shops->categories[0]->id;
        $seller_shops_images = UserShopPhoto::where('user_shop_id',$shopid)->get();
        return view('seller/shop/create',compact('seller_shops','user','categories','seller_shops_images'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $shopid,User $user)
    {
        //
	$user = Auth::user() ?? null;
        $validator = $request->validate([
 	    'shop_name'     => ['required', 'string', 'min:4', 'max:50'],
	    'owner_name'     => ['required', 'string', 'min:4', 'max:30'],
            'shop_phone'         => ['required', 'digits_between:8,12','unique:user_shops,shop_contact,'.$shopid],
            'shop_email'         => ['required', 'email', 'max:100','unique:user_shops,shop_email,'.$shopid],
            'product_categories' => ['nullable', 'array', 'max:5'],
            'product_categories.*' => ['numeric',
                Rule::exists('product_categories','id')->where(function($query) {
                    $query->whereNull('parent_id');
                })
            ],
	   'file'        => ['nullable','max:5'], 

        ]);
        
       $arr = $request->working_days;
        $weekday = implode(",",$arr);
 	    
        if(!empty($user))
        $update  = UserShop::where('id',$shopid)->where('user_id',$user->id)->update([
            'shop_owner'=>$validator['owner_name'],
            'shop_name'=>$validator['shop_name'],
            'shop_email'=>$validator['shop_email'],
            'shop_contact'=>$validator['shop_phone'],
            'registration_no'=>isset($validator['registration']) ? $validator['registration'] : '',
            'country'=>$request->country,
            'state'=>$request->state,
            'address_1'=>$request->searchfield,
            'address_2'=>$request->address2,
            'id_document'=>$file_path ?? null,
            'working_days'=>$weekday,
            'working_hours_from' => $request->work_hours_from,'working_hours_to' => $request->work_hours_to,
            'latitude'=>$request->lat,
            'longitude'=>$request->lng

        ]);

        $shop = UserShop::where('id',$shopid)->where('user_id',$user->id)->first();
        if ($request->hasfile('file')) {
            //old image delete
            $shop_images = UserShopPhoto::where('user_shop_id',$shopid)->get();
            if(!empty($shop_images)){
                foreach ($shop_images as $image) {
                    unlink('storage/app/public/'.$image->photo);
                }
                
            $shop_images_delete = UserShopPhoto::where('user_shop_id',$shopid)->delete();
            }
            //return $store;
            foreach ($request->file('file') as $file) {
                $file_path = $file->store('shops_photos', 'public');
                $validator['file'] = $file_path;
                 $user_company_photos = UserShopPhoto::create([
                    'user_shop_id'    => $shopid,
                    'photo'   => $file_path,
                ]);
            }
        }
        if( ! empty( $product_categories = (array)$request->get('product_categories') ) ) {
            $shop->categories()
                ->sync($product_categories);
        }
        $shop->load(['categories.shops']);
        
       return redirect()->route('seller.shops.index')->withSuccess(__('Shop updated successfully'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($shopid,User $user)
    {
        //
        $count_shops = UserShop::where('user_id',$user->id)->get()->count();
        if($count_shops > 1){
            $success = UserShop::where('id',$shopid)->where('user_id',$user->id)->delete();
            if($success){
            return redirect()->route('seller.shops.index')->withSuccess(__('Removed successfully'));
            }else{
               return redirect()->route('seller.shops.index')->withError(__('Something went wrong')); 
            }
        }else{
            return back()->withError(__('Sorry You cannot delete becuase, you have to keep one shop'));
        }   
    }
}
