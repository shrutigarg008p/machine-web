<?php

namespace App\Http\Controllers\Web;
use App\Traits\APICall;
use App\Http\Resources\ShopResource;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    public function __construct(){
        $this->middleware('auth');
    }

    
    public function order(Request $request)
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
            
            $orderListing = json_decode(json_encode(OrderResource::collection($orders)), true);
            // dd($orderListing);
        // foreach ($orderListing as $value) {
        //     dd($value['order_no']);
        // }            
        return view('customer.inner.orders.orderlist',compact('orderListing'));
    }
    
    
    public function orderdetails(Request $request, $order_id)
    {
        // dd($this->user());

        $order = $this->user()
            ->orders()
            ->with(['items.seller_product', 'orders.shop'])
            ->findOrFail($order_id);

        $items = $order->items;

        $order = new OrderResource($order);

        $order->additional([
            'items' => CartItemResource::collection($items)
        ]);       
        $orderdetails =json_decode(json_encode($order),true);
        // dd($orderdetails);
        return view('customer.inner.orders.orderdetails',compact('orderdetails'));
    }
        
}
