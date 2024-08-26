<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\ApiController;
use App\Http\Resources\CartItemBiddingResource;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\QuotationResource;
use App\Http\Resources\CustomerProductResource;
use App\Models\CartItem;
use App\Models\CartItemBidding;
use App\Models\Order;
use App\Models\OrderSeller;
use App\Services\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends ApiController
{
    public function orders(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,delivered']
        ]);

        $orders = $this->user()->orders()->latest()->with(['orders', 'item.seller_product']);

        if( $status = $request->get('status') ) {
            $orders->withStatus($status);
        }

        $orders = $orders->paginate(15)
            ->through(function($order) {
                $order->top_item = $order->item;
                return $order;
            });

        return ApiResponse::ok(__('Orders'), OrderResource::collection($orders));
    }
    
    public function order_detail(Request $request)
    {
        $request->validate([
            'order_id' => ['required', 'integer']
        ]);

        $order = $this->user()
            ->orders()
            ->with(['items.seller_product', 'orders.shop'])
            ->findOrFail($request->get('order_id'));

        $items = $order->items;

        $order = new OrderResource($order);

        $order->additional([
            'items' => CartItemResource::collection($items)
        ]);

        return ApiResponse::ok(__('Order detail'), $order);
    }

    public function submit_quotation(Request $request)
    {
        $user = $this->user();

        $validated = $this->verify_quotation($request, false);

        if( $validated instanceof Response ) {
            return $validated;
        }

        $cart = $user->cart;

        $cart_items = $cart->items;

        $submitted_items = collect( $validated['items'] );

        DB::beginTransaction();

        try {

            $now = date('Y-m-d H:i:s');

            $order_sellers = []; // seller-wise orders

            $biddings = [];

            // create an overall order
            $order = Order::create([
                'order_no' => "{$user->id}.{$cart->id}." . rand(),
                'cart_id'  => $cart->id,
                'customer_id' => $user->id,
                'address_id' => $request->get('address_id'),
                'delivery_type' => $request->get('delivery_type') ?? 'delivery'
            ]);

            // create shop wise order
            foreach( $cart_items as $cart_item ) {
                if( !isset($cart_item->seller_product) || !isset($cart_item->seller_product->seller) ) {
                    throw new \Exception('submit_quotation - seller_product or seller_product.seller is not set');
                }

                $seller_product = $cart_item->seller_product;

                // decrease inventory for this product
                if( $seller_product->qty !== NULL ) {
                    $seller_product->qty = intval($seller_product->qty) - intval($cart_item->qty);

                    if( $seller_product->qty < 0 ) {
                        DB::rollBack();

                        $available_qty = $cart_item->qty + $seller_product->qty;

                        return ApiResponse::error(
                            $available_qty <= 0
                                ? __('Not enough quantity available for this product')
                                : __('Only :available_qty item(s) available', ['available_qty' => $available_qty]),
                            new CustomerProductResource($seller_product)
                        );
                    }

                    $seller_product->save();
                }

                // unique entry for - order_id & shop_id
                if( ! isset($order_sellers[$seller_product->shop_id]) ) {

                    $order_sellers[$seller_product->shop_id] = [
                        'order_id' => $order->id,
                        'seller_id' => $seller_product->seller_id,
                        'shop_id' => $seller_product->shop_id,
                        'status' => OrderSeller::QUOTATION,
                        'created_at' => $now, 'updated_at' => $now
                    ];
                }

                // if the product type is bid
                // get it from the quotaion submitted
                if( $seller_product->isBid() ) {

                    $submitted_item = $submitted_items->where('product_id', $seller_product->id)->first();

                    if( empty($submitted_item) ) continue;

                    $biddings[] = [
                        'cart_id' => $cart->id,
                        'shop_id' => $seller_product->shop_id,
                        'cart_item_id' => $cart_item->id,
                        'customer_id' => $user->id,
                        'seller_id' => $seller_product->seller->id,
                        'bid' => $submitted_item['bid'],
                        'created_at' => $now, 'updated_at' => $now
                    ];
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

            // create seller-wise orders
            OrderSeller::insert(\array_values($order_sellers));

            // add biddings on products
            if( !empty($biddings) ) {
                CartItemBidding::insert($biddings);
            }

            // empty cart - soft delete
            $cart->delete();

            DB::commit();

            return ApiResponse::ok(__('Quotation added'));

        } catch(\Exception $e) {

            DB::rollBack();

            logger($e->getMessage());
        }

        return ApiResponse::error(__('Something went wrong'));
    }

    // check if user has a valid cart
    // check if we have enough quanity on each of the product to process for order
    public function verify_quotation(Request $request, $sendResponse = true)
    {
        $user = $this->user();

        // make sure the user has an active cart
        $cart = $user->cart;

        if( empty($cart) ) {
            return ApiResponse::error(__('No cart found'));
        }

        $cart_items = $cart->items()->with(['seller_product.seller'])->get();
        $cart->setRelation('items', $cart_items);

        if( $cart_items->isEmpty() ) {
            return ApiResponse::error(__('Please add items to your cart'));
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

            // check for cart items quantity
            foreach( $cart_items as $cart_item ) {
                if( !isset($cart_item->seller_product) || !isset($cart_item->seller_product->seller) ) {
                    throw new \Exception('submit_quotation - seller_product or seller_product.seller is not set');
                }
    
                $seller_product = $cart_item->seller_product;

                $submitted_item = $submitted_items->where('product_id', $seller_product->id)->first();

                // bid required for biddable products
                if( $seller_product->isBid() && !isset($submitted_item['bid']) ) {

                    return ApiResponse::validation(
                        __('Bid required for this product'),
                        new CustomerProductResource($seller_product)
                    );
                }
    
                // check if quanity is available
                if( $seller_product->qty !== NULL ) {
                    $qty = intval($seller_product->qty) - intval($cart_item->qty);
    
                    if( $qty < 0 ) {
    
                        $available_qty = $cart_item->qty + $qty;
    
                        return ApiResponse::validation(
                            $available_qty <= 0
                                ? __('Not enough quanity available for this product')
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

        return ApiResponse::error(__('Something went wrong'));
    }

    public function quotations(Request $request)
    {
        $quotations = $this->user()->quotations()
            ->latest()
            ->paginate(15);

        return ApiResponse::ok(__('Quotations'), QuotationResource::collection($quotations));
    }

    public function quotation_detail(Request $request)
    {
        $request->validate([
            'order_no' => ['required', 'max:191']
        ]);

        $quotation = $this->user()->quotations()
            ->where('order_no', $request->get('order_no'))
            ->firstOrFail();

        $items = $quotation->items()
            ->with(['biddings.customer', 'biddings.seller', 'seller_product.shop'])
            ->get();

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

        return ApiResponse::ok(__('Quotation'), $quotation);
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

        return ApiResponse::ok(__('Bid placed'));
    }
}
