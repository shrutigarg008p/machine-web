<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderSeller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartItemBidding;
use App\Models\User;
use App\Models\UserShop;
use App\Models\UserAddress;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\CartItemBiddingResource;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\QuotationResource;
use Illuminate\Support\Facades\DB;
use App\Models\SellerProduct;
use App\Models\Product;
use App\Services\Api\ApiResponse;
use App\Models\OrderNotiifcation;
use App\Http\Resources\CustomerProductResource;

class QuotationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
         $user = Auth::user();
         $sort ="DESC";
         $pagelimit = ($request->get('limit') !=''?$request->get('limit'):'10');
        
        $quotations = $this->user()
            ->seller_quotations();
            
        if( $sort == $request->get('sortbydate') ) {
            $quotations = $quotations->$quotations->latest();
        }else {
            $quotations = $quotations->oldest();
        }
       
        DB::enableQueryLog();
        if(isset($request->status)){
            $quotations = $quotations->where('status',$request->status);
         }else{
             $quotations = $quotations->where('status','quotation');
         }
        
         $quotations= $quotations->paginate($pagelimit);
        // dd(DB::getQueryLog());
        $alldatas = [];
          $quotations;
        foreach ($quotations as $key => $value) {
            
            $order =  Order::where('order_no',$value->order_no)->first();
            $value->orders   = $order;
              $order_seller =  OrderSeller::where('order_id',$value->order_no)->first();
             $cart_id = $order->cart_id;
            
            if(isset($cart_id)){
                $cart=Cart::where('id',$cart_id)->first(); 
                $value->cart     =$cart;
             }
           // $cartitem=CartItem::where('cart_id',$order->cart_id)->first();
            if(isset($order_seller->seller_id)){
                $seller=User::where('id',$order_seller->seller_id)->first();
                $value->seller   =$seller;
            }
            if(isset($value->customer_id)){
                 $user=User::where('id',$value->customer_id)->first();
                 $value->user_name     =$user->name;
            }
            if(isset($order_seller->shop_id)){
                $usershop=UserShop::where('id',$order_seller->shop_id)->first();
                $value->usershop = $usershop;
            }
            if(isset($value->address_id)){
                $useraddress=UserAddress::where('id',$order->address_id)->first();
                $value->useraddress = $useraddress;
            }
            
        }
        $status = isset($request->status) ? $request->status : 'quotation';
        return view('seller/quotation/index',compact('quotations','status','sort'));
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

        // $productsCount = Product::withoutGlobalScope('active')->count();

        // $products = Product::withoutGlobalScope('active')
        //     ->withFilters($request->query())
        //     ->with(['product_category'])
        //     ->paginate(15);
        //     echo "<pre>";
        // print_r($products);die;
         // <td>{{$product->product_category->title ?? 'NA'}}</td>

            // dd($id);
       
        // dd($order_seller->order_id);
        $order = Order::where('id',$id)->first();
        $order_seller = OrderSeller::where('order_id',$order->id)->first();
        $customer = User::where('id',$order->customer_id)->first();
        $order_seller['order_no'] = $order->order_no;
        // dd($order->order_no);
        $cart_id = $order->cart_id;
        // dd($cart_id);
        $carts = CartItem::leftJoin('seller_products','cart_items.seller_product_id','seller_products.id')
                            ->where('cart_items.cart_id',$cart_id)
                            ->select('cart_items.*')->get();
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
        // echo "<pre>";
        // print_r($carts[1]['bid']->customer_id);


        // dd();
          $carts;
        return view('seller/quotation/show',compact('carts','order_seller','customer','id'));

         $quotation = $this->user()->quotations()
            ->where('order_no', $order->order_no)
            ->firstOrFail();
            dd($quotation);
           $quotations = $this->user()->quotations()
            ->paginate(15);
            return $quotations;
        $c=OrderSeller::where('order_id',$id)->first();
        $k=Order::where('id',$c->order_id)->first();
        $quotation = $this->user()->quotations()
            ->where('order_no', $k->order_no)
            ->firstOrFail();

            // dd($this->user());

        $items = $quotation->items()->with(['biddings.customer', 'biddings.seller'])->get();
        $quotation->setRelation('items', $items);

        $items = CartItemResource::collection($items)
            ->map(function(CartItemResource $cartItemResource) {
                $item = $cartItemResource->resource;

                $cartItemResource->additional([
                    'biddings' => CartItemBiddingResource::collection($item->biddings)
                ]);

                return $cartItemResource;
            });

        $quotation = new QuotationResource($quotation);

        $quotation->additional(['items' => $items]);
        dd($quotation);

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


   public function accept($id){
        $c=OrderSeller::where('id',$id)->update(['status'=>'confirmed']);
        $OrderSeller = OrderSeller::where('id',$id)->first();
        if(!empty($OrderSeller)){

           $orderId = $OrderSeller->order_id;
           
           $user = Auth::user();

           $c=OrderNotiifcation::where('order_id',$orderId)->where('seller_id',$user->id)->update(['user_read'=>'1','seller_read'=>'0']);
        }
       return json_encode(['status'=> 1, 'message'=>'Quotation Accepted']);
    }

    public function deny($id){
        $c=OrderSeller::where('id',$id)->update(['status'=>'rejected']);
        $OrderSeller = OrderSeller::where('id',$id)->first();
        
        if(!empty($OrderSeller)){

           $orderId = $OrderSeller->order_id;
           
           $user = Auth::user();

           $c=OrderNotiifcation::where('order_id',$orderId)->where('seller_id',$user->id)->update(['user_read'=>'1','seller_read'=>'0']);
        }
        return json_encode(['status'=> 1, 'message'=>'Quotation Rejected']);
    }

    public function accept_reject_bid(Request $request)
    {
        $request->validate([
            'bid_id' => ['required', 'integer'],
            'accepted' => ['required', 'in:-1,1']
        ]);

         $seller = $this->user();
        
          $bid = $seller->seller_biddings()->findOrFail($request->get('bid_id'));
        if( $bid->actionTaken ) {
             ApiResponse::forbidden(__('Cannot modify this bid'));
        }

        $accepted = $request->get('accepted');

        DB::beginTransaction();
        try {

            $bid->accepted = $accepted;
            $bid->save();
            $OrderSeller =Order::where('cart_id',$bid->cart_id)->first(); 
            // if all items' bids are accepted by the seller
            // then we convert this seller_order from quotation to order-pending
            if( $accepted == CartItemBidding::ACCEPTED ) {
                $this->convert_to_order($bid);
            }else{
                if(!empty($OrderSeller)){
                    OrderSeller::where('order_id',$OrderSeller->id)->update(['status'=>'rejected']);
                }
            }

            DB::commit();

        } catch(\Exception $e) {
            DB::rollBack();

            logger($e->getMessage());
        }

        $message = $accepted == CartItemBidding::ACCEPTED
            ? __('Bid accepted')
            : __('Bid rejected');

            $accete_reject = $accepted == CartItemBidding::ACCEPTED
            ? __('accepted')
            : __('rejected'); 
            $shop = UserShop::find($bid->shop_id);
            if(!empty($OrderSeller)){

                $orderId = $OrderSeller->id;
                
                $user = Auth::user();
     
                OrderNotiifcation::where('order_id',$orderId)->where('seller_id',$user->id)->update(['user_read'=>'1','seller_read'=>'0']);
             }
        
        $quotation_message = "Your quotation number ".$OrderSeller->order_no." has been ".$accete_reject." by ".$shop->shop_name;
        notificationFirebase($quotation_message, "Quotation ".$accete_reject,$bid->customer_id,"en");
        return json_encode(['status'=> 1, 'message'=>$message]);
    }

     // convert quotation to order if all bids are accepted by the seller
     private function convert_to_order(CartItemBidding $cartItemBidding)
     {
         $siblings = $cartItemBidding->siblings;
 
         $ok = true;
 
         foreach( $siblings as $sibling_bid ) {
             // if no action taken on any bid; break;
             if( $sibling_bid->noActionTaken ) {
                 $ok = false;
                 break;
             }
 
             // if there's rejected bid;
             // check if there's an accepted counterpart
             else if( $sibling_bid->isBidRejected ) {
                 $counter_bid = $siblings
                     ->where('cart_item_id', $sibling_bid->cart_item_id)
                     ->where('accepted', CartItemBidding::ACCEPTED)
                     ->first();
 
                 if( empty($counter_bid) ) {
                     $ok = false;
                     break;
                 }
             }
         }
 
         // if all set; conver it to an order
         if( $ok ) {
 
             $order = $cartItemBidding->order;
 
             if( empty($order) ) {
                 throw new \Exception('order not found id - '.$cartItemBidding->id);
             }
             
             // find the seller_order to mark it as order-pending
             // $seller_order = $cartItemBidding->seller_order;
             $seller_order = $cartItemBidding->get_seller_order($order->id);
 
             if( empty($seller_order) ) {
                 throw new \Exception('accept_reject bid - seller order not found id - '.$cartItemBidding->id);
             }
 
             $seller_order->status = 'confirmed';
             $seller_order->update();
         }
     }

     public function accept_reject_quotation(Request $request)
     {
        $request->validate([
            'id' => ['required', 'integer'],
            'accepted' => ['required', 'in:-1,1']
        ]);

        $order = Order::where('id',$request->id)->first();
        $order_seller = OrderSeller::where('order_id',$request->id)->first();
        if( $order_seller->actionTaken ) {
            ApiResponse::forbidden(__('Cannot modify this bid'));
       }
        // dd($order_seller->order_id);
        
        
        $customer = User::where('id',$order->customer_id)->first();

        // dd($order->order_no);
         $cart_id = $order->cart_id;
        
        $accepted = $request->get('accepted');
        DB::beginTransaction();
        try {
                    if( $accepted == CartItemBidding::ACCEPTED ) {
                       $items = CartItem::where('cart_id',$order->cart_id)->get();
                        foreach($items as $item) {
                            $seller_product = $item->seller_product;
                            $seller_product->qty = intval($seller_product->qty) - intval($item->qty);
                            if($seller_product->qty < 0) {
                                DB::rollBack();
        
                                $available_qty = $item->qty + $seller_product->qty;
                                $product_title = isset($item->seller_product->product->title) ? $item->seller_product->product->title : '';
                                return back()->withError(
                                    $available_qty <= 0
                                        ? __('Not enough quantity available for this product '.$product_title)
                                        : __('Only :available_qty item(s) available', ['available_qty' => $available_qty]),
                                    new CustomerProductResource($seller_product)
                                );
                            }
                            $seller_product->save();
                        }

                        $order_seller_status = 'confirmed';
                    }else{
                        $order_seller_status = 'rejected';
                    }
                    if(isset($cart_id)){
                        
                        CartItemBidding::where('cart_id',$cart_id)->update(['accepted'=>$accepted]);
                        OrderSeller::where('id',$order_seller->id)->update(['status'=>$order_seller_status]);
                        Order::where('id',$order->id)->update(['updated_at'=>date('Y-m-d G:i:s')]);
                    }else{
                        if( empty($cart_id) ) {
                            throw new \Exception('Cart not found id');
                        }
                    }
                DB::commit();

            } catch(\Exception $e) {
                DB::rollBack();

                logger($e->getMessage());
            }
            

        $message = $accepted == CartItemBidding::ACCEPTED
            ? __('Bid accepted')
            : __('Bid rejected');

            $accete_reject = $accepted == CartItemBidding::ACCEPTED
            ? __('accepted')
            : __('rejected'); 
            $shop = UserShop::find($order_seller->shop_id);
           

              $orderId = $order->id;
                
               $user = Auth::user();
     
                OrderNotiifcation::where('order_id',$orderId)->where('seller_id',$user->id)->update(['user_read'=>'1','seller_read'=>'0']);
         
        $quotation_message = "Your quotation number ".$order->order_no." has been ".$accete_reject." by ".$shop->shop_name;
        notificationFirebase($quotation_message, "Quotation ".$accete_reject,$customer->id,"en");
        return json_encode(['status'=> 1, 'message'=>$message]);
     }
}

