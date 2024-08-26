<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderSeller;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use  App\Models\Cart;
use App\Models\CartItem;
use App\Models\SellerProduct;
use App\Models\Product;
use App\Models\UserShop;
use App\Models\CartItemBidding;
class OrderController extends Controller
{
    private $limits = 15;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,delivered,cancelled']
        ]);

        $seller = $this->user();
        $sort =isset($request->sortbydate) ? $request->sortbydate : "DESC";
        $status =isset($request->status) ? $request->status : "confirmed";
        $orders = $seller
            ->seller_orders()
            ->with([
                
                // latest item that belongs to this seller
                'item' => function($query) use($seller) {
                    $query->whereHas('seller', function($query) use($seller) {
                        $query->where('users.id', $seller->id);
                    });
                },
                'item.seller_product'
            ]);
            
        
            $orders = $orders->orderBy('updated_at',$sort);
            
            $orders->withStatus($status);
        
        $orders = $orders->paginate(10);

        foreach ($orders as $key => $value) {
           $user=User::where('id',$value->customer_id)->first();
            $carts = CartItem::where('cart_id',$value->cart_id)->first();
            $seller = SellerProduct::where('id',$carts->seller_product_id)->first();
           $product= isset($seller->product_id)?? null;
           $productsCount = Product::withoutGlobalScope('active')->count();

            $pro =  Product::withoutGlobalScope('active')
            ->withFilters($request->query())
            ->with(['product_category'])->where('id',$product)
            ->first();
           $value->user=$user;
           $value->pro=$pro;
        }
        $status = isset($request->status) ? $request->status : 'confirmed';
        return view('seller/order/index',compact('orders','status','sort'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request,$id)
    {
        $order = Order::where('id',$id)->first();
        $order_seller = Orderseller::where('order_id',$order->id)->first();
        $customer = User::where('id',$order->customer_id)->first();
        $cart_id = $order->cart_id;
        $carts = CartItem::where('cart_id',$cart_id)->get();
        $abc=[];
           foreach ($carts as $key => $value) {
            $seller = SellerProduct::where('id',$value->seller_product_id)->first();
            $product=$seller->product_id;
            $usershop=UserShop::where('id',$seller->shop_id)->first();

            $productsCount = Product::withoutGlobalScope('active')->count();

            $pro =  Product::withoutGlobalScope('active')
            ->withFilters($request->query())
            ->with(['product_category'])->where('id',$product)
            ->first();

            $bid=CartItemBidding::where('cart_item_id',$value->id)->first();
            $seller_name=User::where('id',$seller->seller_id)->first();
            // $custom_name = User::where('id',$bid->customer_id)->first();
            if(!empty($bid)){
            $bidcustomer=User::where('id',$bid->customer_id)->first();
            }else{
                $bidcustomer='';
            }
            $value->bid=$bid;
            $value->bidcustomer=$bidcustomer;
            $value->usershop = $usershop;
            $value->pro = $pro;
            $value->seller=$seller;
          
            $value->seller_name=$seller_name;
            // $value->custom_name=$custom_name;
            // $pro=Product::where('id',$product)->first();
            // $shiv=ProductCategory::where('id',$pro->product_category_id)->first();
            // array_push($abc,$pro);
        }
        //return $carts;
        return view('seller/order/show',compact('carts','customer','order'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function chat_channels(Request $request)
    {
        $chat_channels = $this->user()->chat_channels()
            ->with(['participants'])
            ->latest()
            ->get();
        return view('seller.chat.index', compact('chat_channels'));
    }
}
