<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartItemBiddingResource;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\QuotationResource;
use App\Http\Resources\CustomerProductResource;
use App\Models\CartItem;
use App\Models\CartItemBidding;
use App\Models\Order;
use App\Models\OrderSeller;
use App\Models\OrderNotiifcation;
use App\Services\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function orders(Request $request)
    {

        $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,delivered,cancelled']
        ]);


	 $sort =  isset($request->sortbydate) ? $request->sortbydate : 'DESC';
         if($sort == 'ASC'){
        	$orders = $this->user()->orders()->oldest()->with(['orders', 'item.seller_product']);
        }else{
            	$orders = $this->user()->orders()->latest()->with(['orders', 'item.seller_product']);	
        }

        if( $status = $request->get('status') ) {
            $orders->withStatus($status);
        }else{
            $orders->withStatus('confirmed');
            $status='confirmed';
        }

         $orderListing= $orders->paginate(10);
        //$orderListing = json_decode(json_encode(OrderResource::collection($orders)),true);
            return view('customer.inner.orders.orderlist',compact('orderListing','status','sort'));
    }
    
    public function order_detail($id)
    {
        $order = $this->user()
            ->orders()
            ->with(['items.seller_product' => function ($query) {
                $query->withoutGlobalScopes(['has_product']);
            },'orders.shop'])
            ->findOrFail($id);

            $items = $order->items()
            ->with(['biddings.customer', 'biddings.seller', 'seller_product.shop'])
            ->get();
            $order->setRelation('items', $items);
	$order_seller = OrderSeller::where('order_id',$order->id)->first();
	$order->seller_name = $order_seller->seller->name;
        $order = new OrderResource($order);
        $items = CartItemResource::collection($items)
            ->map(function(CartItemResource $cartItemResource) {
                $item = $cartItemResource->resource;

                $cartItemResource->additional([
                    'biddings' => CartItemBiddingResource::collection($item->biddings),
                    'sellerid' =>$item->seller_product->seller_id ?? null,
                ]);

                return $cartItemResource;
        });
        
        $order->additional(['items' => $items]);

        $orderdetails = json_decode(json_encode($order),true);

        return view('customer.inner.orders.orderdetails',compact('orderdetails'));
    }

    public function submit_quotation(Request $request)
    {
         $user = $this->user();
        unset($request['product']);
        unset($request['quantity']);
        $validated = $this->verify_quotation($request, false);

        if( $validated instanceof Response ) {
            return $validated;
        }

        $cart = $user->cart;

          $cart_items = $cart->items;
       $seller_id = [];
       $i=0;
       $checkBiddingProduct=1;
       foreach( $cart_items as $cart_item ) {
        $seller_id[$i] = $cart_item->seller_product->seller_id;
        $i++;
        $seller_product = $cart_item->seller_product;
        if($seller_product->isBid()){
            $checkBiddingProduct = 0;
        }

       }
        $sellers = collect(array_unique($seller_id));
       
        $submitted_items = collect($validated['items'] );
        DB::beginTransaction();

        try {

            $now = date('Y-m-d H:i:s');

            $order_sellers = []; // seller-wise orders

            $biddings = [];
            // create an overall order
            $order_data = [];
            for($i=0;$i < sizeof($sellers);$i++){
                $order = Order::create([
                    'order_no' => "{$user->id}.{$cart->id}." . rand(),
                    'cart_id'  => $cart->id,
                    'customer_id' => $user->id,
                    'address_id' => $request->get('address_id'),
                    'delivery_type' => $request->get('delivery_type') ?? 'delivery'
                ]);
                $order_data[$i]["order_id"]= $order->id;
                $order_data[$i]["cart_id"]= $cart->id;
                $order_data[$i]['seller_id'] = $sellers[$i];
            }
             $order_data = collect($order_data);
            // create shop wise order
            foreach( $cart_items as $cart_item ) {
                if( !isset($cart_item->seller_product) || !isset($cart_item->seller_product->seller) ) {
                    throw new \Exception('submit_quotation - seller_product or seller_product.seller is not set');
                }

                $seller_product = $cart_item->seller_product;

                // dd($seller_product);

                // decrease inventory for this product
                if( $seller_product->qty !== NULL ) {
                    if($checkBiddingProduct){
                        $seller_product->qty = intval($seller_product->qty) - intval($cart_item->qty);
                    }

                    if( $seller_product->qty <= 0 ) {
                        DB::rollBack();

                        $available_qty = $seller_product->qty;
                        $product_title = isset($cart_item->seller_product->product->title) ? $cart_item->seller_product->product->title : '';
                        return back()->withError(
                            $available_qty <= 0
                                ? __('Not enough quantity available for this product '.$product_title)
                                : __('Only :available_qty item(s) available', ['available_qty' => $available_qty]),
                            new CustomerProductResource($seller_product)
                        );
                    }

                    $seller_product->save();
                }

                //To check cart had one bid product
                

                //end
               $order_id = $order_data->where('seller_id',$seller_product->seller_id)->first();
                // unique entry for - order_id & shop_id
                if( ! isset($order_sellers[$seller_product->shop_id]) ) {

                    $order_sellers[$seller_product->shop_id] = [
                        'order_id' => $order_id['order_id'],
                        'seller_id' => $seller_product->seller_id,
                        'shop_id' => $seller_product->shop_id,
                        'created_at' => $now, 'updated_at' => $now
                    ];

                    $order_notification[$seller_product->shop_id] = [
                        'order_id' => $order_id['order_id'],
                        'user_id' => $user->id,
                        'seller_id' => $seller_product->seller_id,
                        'shop_id' => $seller_product->shop_id,
                        'user_read' => "0",
                        'seller_read' => "1",
                        'created_at' => $now, 'updated_at' => $now
                    ];
                }

                // if the product type is bid
                // get it from the quotaion submitted
                // dump($seller_product);
                if( $seller_product->isBid() ) {

                    // $submitted_item = $submitted_items->where('product_id', $seller_product->id)->first();
                    $submitted_item = array_search($seller_product->id,$submitted_items->toArray()["'product_id'"]);
                    if( empty((string)$submitted_item) && $submitted_item != '') continue;

                    $biddings[] = [
                        'cart_id' => $cart->id,
                        'shop_id' => $seller_product->shop_id,
                        'cart_item_id' => $cart_item->id,
                        'customer_id' => $user->id,
                        'seller_id' => $seller_product->seller->id,
                        'bid' => $submitted_items->toArray()["'bid'"][$submitted_item],
                        'created_at' => $now, 
                        'updated_at' => $now
                    ];
                    // dd($biddings);
                }
                
                // if it's fixed; save amount to the cart item
                else {
                    /// 
                    $cart_item->amount = price_format(floatval($seller_product->price) * intval($cart_item->qty));
                    $cart_item->save();
                }
            }
            if( empty($order_sellers) ) {
                throw new \Exception('No seller-wise orders created - customer order controller');
            }

             // add biddings on products
             if( !empty($biddings) ) {
                CartItemBidding::insert($biddings);
            }

           
            // create seller-wise orders
             $order_data;
           OrderSeller::insert(\array_values($order_sellers));
           OrderNotiifcation::insert(\array_values($order_notification));
            for($i=0; $i<sizeof($order_data); $i++){
                $check_item = CartItemBidding::where('cart_id',$order_data[$i]['cart_id'])->where('seller_id',$order_data[$i]['seller_id'])->first();
                if($check_item){
                    $order_serller_status = OrderSeller::QUOTATION;
                    $msg_status = "Quotation";
                }else{
                    $order_serller_status  = OrderSeller::CONFIRMED;
                    $msg_status = "Order";
                }

                OrderSeller::where('seller_id',$order_data[$i]['seller_id'])
                            ->where('order_id',$order_data[$i]['order_id'])
                            ->update(['status'=>$order_serller_status]);
                $quotation_message = "You have receive new ".$msg_status." from ".$user->name." with  ".$msg_status." number is ".$order->order_no;
                notificationFirebase($quotation_message,"New  ".$msg_status,$seller_product->seller->id,"en");                    
            }
            // empty cart - soft delete
            $cart->delete();

            DB::commit();

            return back()->withSuccess(__('Quotation added'));

        } catch(\Exception $e) {
            return $e;
            DB::rollBack();

            logger($e->getMessage());
        }

        return back()->withError(__('Something went wrong'));
    }

    // check if user has a valid cart
    // check if we have enough quanity on each of the product to process for order
    public function verify_quotation(Request $request, $sendResponse = true)
    {
        $user = $this->user();

        // make sure the user has an active cart
        $cart = $user->cart;

        if( empty($cart) ) {
            return back()->withError(__('No cart found'));
        }

        $cart_items = $cart->items()->with(['seller_product.seller'])->get();
        $cart->setRelation('items', $cart_items);

        if( $cart_items->isEmpty() ) {
            return back()->withError(__('Please add items to your cart'));
        }

        // product ids of those products which are for bidding
       
        $bid_product_ids = $cart_items
            ->where('seller_product.price_type', 'bid')
            ->pluck(['seller_product_id'])
            ->toArray();

        $validated = $request->validate([
            'address_id' => ['bail', 'nullable', 'integer', "exists:user_addresses,id,user_id,{$user->id}"],
            'delivery_type' => ['nullable', 'in:delivery,pick-up'],
            'items' => ['nullable', 'array'],
            'items.product_id' => ['exists:seller_products,id', Rule::in($bid_product_ids)],
            'items.bid' => ['nullable', 'numeric']
        ], [
            'items.product_id.exists' => __('Product :input do not exist'),
            'items.product_id.in' => __('Invalid product :input'),
        ]);
 
         $submitted_items = collect($validated['items']);

        try {
            //check biding product
            $checkBiddingProduct=1;
            foreach( $cart_items as $cart_item ) {
                $seller_product = $cart_item->seller_product;
                if($seller_product->isBid()){
                    $checkBiddingProduct=0;
                }
            }

            // check for cart items quantity
            foreach( $cart_items as $cart_item ) {
                if( !isset($cart_item->seller_product) || !isset($cart_item->seller_product->seller) ) {
                    throw new \Exception('submit_quotation - seller_product or seller_product.seller is not set');
                }
    
                $seller_product = $cart_item->seller_product;
                
                   $submitted_item = $submitted_items->where('product_id', $seller_product->id)->first();

                // bid required for biddable products
                if( $seller_product->isBid() && !isset($submitted_item['bid']) ) {

                    back()->withError(
                        __('Bid required for this product'),
                        new CustomerProductResource($seller_product)
                    );
                }
    
                // check if quanity is available
                if( $seller_product->qty !== NULL ) {
                    if($checkBiddingProduct){
                         $qty = intval($seller_product->qty) - intval($cart_item->qty);
                    }else{
                           $qty = intval($seller_product->qty);
                    }
                    if( $qty <= 0 ) {
    
                        $available_qty =  $qty;
                        $product_title = isset($cart_item->seller_product->product->title) ? $cart_item->seller_product->product->title : '';
                        return back()->withError(
                            $available_qty <= 0
                                ? __('Not enough quanity available for this product '. $product_title)
                                : __('Only :available_qty items left', ['available_qty' => $available_qty]),
                            new CustomerProductResource($seller_product)
                        );
                    }
                }
            }

            return $sendResponse ? ApiResponse::ok(__('Verified')) : $validated;

        } catch(\Exception $e) {
           
            logger($e->getMessage());
        }

        return back()->withError(__('Something went wrong'));
    }

    public function quotations(Request $request)
    {
      $quotations = $this->user()->quotations()
            ->latest()
            ->paginate(10);
           
            return view('customer.inner.quatations.quatationlist',compact('quotations'));

    }

    public function quotation_detail($id)
    {
      
        $quotation = $this->user()->quotations()
            ->where('order_no',$id)
            ->firstOrFail();

         $items = $quotation->items()
            ->with(['biddings.customer', 'biddings.seller', 'seller_product.shop'])
            ->get();

        $quotation->setRelation('items', $items);
        // dd($quotation);
        $items = CartItemResource::collection($items)
            ->map(function(CartItemResource $cartItemResource) {
                $item = $cartItemResource->resource;

                $cartItemResource->additional([
                    'biddings' => CartItemBiddingResource::collection($item->biddings),
                    'sellerid' =>isset($item->seller_product->seller_id) ? $item->seller_product->seller_id : '',
                ]);

                return $cartItemResource;
            });
          
        $quotation = new QuotationResource($quotation);
        
        $quotation->additional(['items' => $items]);
   
         $orderdetails =json_decode(json_encode($quotation),true);

        return view('customer.inner.quatations.quatationdetails',compact('orderdetails'));
    }

    // This is to place a better bid in case a bid is rejected by the seller
    public function place_bid(Request $request)
    {
        $request->validate([
            'bid_id' => ['required', 'integer'],
            'bid_amount' => ['required', 'numeric']
        ]);

        $user = $this->user();

        $bidding = $user->biddings()->findOrFail($request->get('bid_id'));

        $this->authorize('place_bid', [$bidding, $request->get('bid_amount')]);

        $user->biddings()->create([
            'shop_id' => $bidding->shop_id,
            'cart_id' => $bidding->cart_id,
            'cart_item_id' => $bidding->cart_item_id,
            'seller_id' => $bidding->seller_id,
            'bid' => floatval($request->get('bid_amount')),
            'accepted' => 0
        ]);

        return back()->withSuccess(__('Bid placed'));
    }
}
