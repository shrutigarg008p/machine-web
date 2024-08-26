<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerProductResource;
use App\Models\SellerProduct;
use App\Services\Api\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // show products listing based on the shop
    public function productlisting(Request $request,$catid,$shop_id)
    {
      $category = intval($catid);

        $products = SellerProduct::query()
            ->with(['product.product_category'])
            ->where('shop_id', $shop_id);
        if( $category ) {
            $products->whereRelation('product.product_category', 'id', $category);
        }

        $user = $this->user();

        $products = CustomerProductResource::collection($products->latest()->paginate(100))
            ->map(function($sellerProductResource) use($user) {
                if($sellerProductResource->product->status == 1){            
                    $sellerProductResource->additional([
                        'is_favourite' => $sellerProductResource->resource->isFavForUser($user)
                    ]);
                    return $sellerProductResource;
                }
            });
            $productlisting = json_decode($products->filter(), true);
            return view('customer.product.productlisting',compact('productlisting','catid','shop_id'));
    }

    public function productdetails(Request $request, $product_id,$catid,$shop_id)
    {
        

        $product = intval($product_id);

        if( $sellerProduct = SellerProduct::find($product) ) {

            $data = new CustomerProductResource($sellerProduct);

            $product = $sellerProduct->product;

            $product_images = $product->product_images
                ->map(function($image) {
                    return storage_url($image->image);
                });

            $additional_info = $product->additional_info
                ? json_decode($product->additional_info)
                : [];

            $additional_info = collect($additional_info)
                ->filter()
                ->map(function($value, $key) {
                    return [
                        'key' => $key,
                        'value' => $value
                    ];
                });

            $user = $this->user();

            $in_cart = false;

            if( $user && $user->cart ) {
                $in_cart = $user->cart->isSellerProductInCart($sellerProduct);
            }

            $data->additional([
                'addtional_images' => $product_images,
                'description' => $product->description,
                'additional_info' => array_values($additional_info->toArray()),
                'is_favourite' => $user && $sellerProduct->isFavForUser($user),
                'in_cart' => $in_cart
            ]);

            $category = intval($catid);

            $products = SellerProduct::query()
                ->with(['product.product_category'])
                ->where('shop_id', $shop_id);
            if( $category ) {
                $products->whereRelation('product.product_category', 'id', $category);
            }
    
            $user = $this->user();
    
            $products = CustomerProductResource::collection($products->latest()->paginate(15))
                ->map(function($sellerProductResource) use($user) {
    
                    $sellerProductResource->additional([
                        'is_favourite' => $user && $sellerProductResource->resource->isFavForUser($user)
                    ]);
    
                    return $sellerProductResource;
                });
            
    
        $productlisting = json_decode($products, true);
        $productdetails =json_decode(json_encode($data), true); 
        return view('customer.product.productdetails',compact('productdetails','productlisting','catid','shop_id'));
        }

       
    }

    public function favourites()
    {
        $user = $this->user();

        $products = $user->favourite_products()->paginate(15);

        $favouriteShops = json_decode(json_encode(CustomerProductResource::collection($products)), true);
        return view('customer.inner.favoritesProducts',compact('favouriteShops'));
    }

    public function add_to_favourite($id){
        if( $product = intval($id)){
            if( $product = SellerProduct::find($product) ) {
    
                $removed = $this->user()->favourite_products()->detach($product->id);
    
                if( empty($removed) ) {
                    $this->user()->favourite_products()->save($product);
                    return ApiResponse::ok(__('Added to favourites'), ['is_favourite' => true]);
                }
    
                return ApiResponse::ok(__('Removed from favourites'), ['is_favourite' => false]);
            }
        }
    }
}
