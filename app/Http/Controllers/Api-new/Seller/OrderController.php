<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\ApiController;
use App\Http\Resources\CartItemBiddingResource;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\QuotationResource;
use App\Models\CartItemBidding;
use App\Models\OrderSeller;
use App\Services\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class OrderController extends ApiController
{
    public function quotations()
    {

        $quotations = $this->user()
            ->seller_quotations()
            ->latest()
            ->paginate(15);

        $quoCollect = collect();
        foreach($quotations as $key => $quotation){
            if(CartItemBidding::where('cart_id',$quotation->cart_id)->where('accepted',0)->exists()){
                $quoArray[$key]['order_no'] = $quotation->order_no;
                $quoArray[$key]['date'] = $quotation->created_at->format('d M, Y - h:i A');
                $quoArray[$key]['date_str'] = $quotation->created_at->diffForHumans();
                $quoCollect = $quoCollect->merge($quoArray);
            }
        }
        if(!empty($quoCollect)){
            return ApiResponse::ok(__('Quotations'), $quoCollect->unique('order_no')->values());
        }else{
            return ApiResponse::ok(__('Quotations'), $quoCollect);
        }
    }

    public function quotation_detail(Request $request)
    {
        $seller = $this->user();

        $request->validate([
            'order_no' => ['required', 'max:191']
        ]);

        $quotation = $this->user()->seller_quotations()
            ->where('order_no', $request->get('order_no'))
            ->firstOrFail();

        $items = $quotation
            ->items()
            ->with(['biddings.customer', 'biddings.seller', 'seller'])

            // only fetch items that belong to this user
            ->whereHas('seller', function($query) use($seller) {
                $query->where('users.id', $seller->id);
            })
            ->get();

        $quotation->setRelation('items', $items);

        $items = CartItemResource::collection($items)
            ->map(function(CartItemResource $cartItemResource) {
                $item = $cartItemResource->resource;

                $accepted_bid = $item->biddings->filter->isBidAccepted->first();

                // shop that is selling this item
                $shop = $item->shop;

                $cartItemResource->additional([
                    'shop' => $item->shop ? [
                        'id' => $shop->id,
                        'shop_name' => $shop->shop_name,
                        'shop_email' => $shop->shop_email
                    ] : null,
                    'biddings' => CartItemBiddingResource::collection($item->biddings),
                    'accepted_bid' => $accepted_bid
                        ? new CartItemBiddingResource($accepted_bid)
                        : null
                ]);

                return $cartItemResource;
            });

        $quotation = new QuotationResource($quotation);

        $quotation->additional([
            'items' => $items
        ]);

        return ApiResponse::ok(__('Quotation'), $quotation);
    }

    public function orders(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,delivered']
        ]);

        $seller = $this->user();

        $orders = $seller
            ->seller_orders()
            ->with([
                'orders',
                // latest item that belongs to this seller
                'item' => function($query) use($seller) {
                    $query->whereHas('seller', function($query) use($seller) {
                        $query->where('users.id', $seller->id);
                    });
                },
                'item.seller_product'
            ])
            ->latest();

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

        $seller = $this->user();

        $order = $seller
            ->seller_orders()
            ->with([
                // items that belong to this seller
                'items' => function($query) use($seller) {
                    $query->whereHas('seller', function($query) use($seller) {
                        $query->where('users.id', $seller->id);
                    });
                },
                'items.seller_product',
                'orders'
            ])
            ->findOrFail($request->get('order_id'));

        $items = $order->items;

        $order = new OrderResource($order);

        $order->additional([
            'items' => CartItemResource::collection($items)
        ]);

        return ApiResponse::ok(__('Order detail'), $order);
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
            return ApiResponse::forbidden(__('Cannot modify this bid'));
        }

        $accepted = $request->get('accepted');

        DB::beginTransaction();

        try {

            $bid->accepted = $accepted;
            $bid->save();

            // if all items' bids are accepted by the seller
            // then we convert this seller_order from quotation to order-pending
            if( $accepted == CartItemBidding::ACCEPTED ) {
                $this->convert_to_order($bid);
            }

            DB::commit();

        } catch(\Exception $e) {
            DB::rollBack();

            logger($e->getMessage());
        }

        $message = $accepted == CartItemBidding::ACCEPTED
            ? __('Bid accepted')
            : __('Bid rejected');

        return ApiResponse::ok($message);
    }

    public function update_order_status(Request $request)
    {
        $request->validate([
            'order_no' => ['required', 'max:191', 'exists:orders,order_no'],
            'status' => ['required', 'in:confirmed,delivered,cancelled']
        ]);

        $order_no = $request->get('order_no');

        $seller = $this->user();

        $order = OrderSeller::query()
            ->where('seller_id', $seller->id)
            ->whereHas('order', function($query) use($order_no) {
                $query->where('order_no', $order_no);
            })
            ->firstOrFail();

        $order->status = $request->get('status');
        $order->update();

        return ApiResponse::ok(__('Order status updated'));
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

            $seller_order->status = 'pending';
            $seller_order->update();
        }
    }
    public function paginate($items, $perPage = 10, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return (new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options))->withPath('/user/my_purchases?type=newspaper');
    }
}
