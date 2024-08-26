<?php

namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Services\Api\ApiResponse;

class CartController extends Controller
{

    public function index()
    {
        $user = $this->user();

        $cart_items = $user
            ->cart_items()
            ->has('seller_product')
            ->with([
                'seller_product.product',
                'seller_product.shop',
                'seller_product.shops'
            ])
            ->latest()
            ->paginate(15);

        $cart = $user->cart;
	
	$cart_count = $user
            ->cart_items()
            ->has('seller_product')
            ->with([
                'seller_product.product',
                'seller_product.shop',
                'seller_product.shops'
            ])
            ->count();
       $data =  [
            'items' => json_decode(json_encode(CartItemResource::collection($cart_items)), true),
            'cart_items' => $cart ? $cart_count : 0,
            'total_sum' => $cart ? $cart->total_amount : 0,
            'currency' => 'AED',
            'primary_address' => $user ? $user->primary_address : null
        ];
    
        return view('customer.cart.cart',compact('data'));
    }

    public function add_to_cart($req,$id){
            $product = intval($id);
            $qty = 1;
    
            $user = $this->user();
    
            $cart_item = $user->cart_items()
                ->firstWhere('seller_product_id', $product);
    
            if( empty($cart_item) ) {
    
                $cart_item = new CartItem([
                    'seller_product_id' => $product,
                    'user_id' => $user->id,
                    'cart_id' => $user->cart()->firstOrCreate()->id
                ]);
            }
    
            $cart_item->qty = $cart_item->qty ? ($cart_item->qty + $qty) : 1;
            $cart_item->save();
    
            $cart = $user->cart;
    
            $data['item'] = new CartItemResource($cart_item);
    
            $data['cart_items'] = intval($cart ? $cart->total : 0);
    
            $data['currency'] = 'AED';
            $data['total_sum'] = $cart ? $cart->total_amount : '0.00';
            if($req=='web'){
                return redirect(route('cart'));
            }else{
                return ApiResponse::ok(__('Item added'), $data);
            }
    }

    public function remove_from_cart($id){
        $product = intval($id);
        $qty = 1;
        $data = [
            'item' => null,
            'cart_items' => 0,
            'total_sum' => '0.00',
            'currency' => 'AED'
        ];

        $user = $this->user();

        $cart_item = $user->cart_items()
            ->firstWhere('seller_product_id', $product);

        if( $cart_item ) {
            
            if( $qty == -1 ) {
                $cart_item->delete();
            }
            else {
                $cart_item->qty = $cart_item->qty - $qty;

                if( $cart_item->qty == 0 ) {
                    $cart_item->delete();
                } else {
                    $cart_item->save();

                    $data['item'] = new CartItemResource($cart_item);
                }
            }

            /** @var CartItem $cart */
            $cart = $user->cart;
    
            if( $cart->items->isEmpty() ) {
                $cart->forceDelete();
            }

            $data['cart_items'] = intval($cart ? $cart->total : 0);
            $data['total_sum'] = $cart ? $cart->total_amount : '0.00';
        }

        //return redirect(route('cart'));
        return ApiResponse::ok(__('Item removed'), $data);
    }


    public function delete_from_cart($id){
        $product = intval($id);
        $user = $this->user();
        $cart_item = $user->cart_items()
        ->firstWhere('seller_product_id', $product);
        $cart_item->forceDelete();
        return ApiResponse::ok(__('Item deleted'));
    }
}