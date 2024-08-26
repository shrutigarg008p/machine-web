<?php

namespace App\Http\Controllers\Web;

use App\Traits\APICall;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShopResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    use APICall;

    public function __construct(){
        $this->middleware('auth');
    }

    public function categoryList(Request $request){
        return $result = APICall::callAPI('POST',EndPoints::SHOP_CATEGORIES,json_encode([
            'parent_only' =>1
        ]));
        $categories = json_decode($result, true);

        return view('customer.product.category',compact('categories'));
    }

    public function shopDetails(Request $request,$id){
        $result = APICall::callAPI('POST', EndPoints::SHOP_DETAILS,json_encode([
            'shop' => $id
        ]));
        $shopdetails = json_decode($result, true);
        return view('customer.product.shopdetails',compact('shopdetails'));
    }

    public function productlisting(Request $request,$catid,$shop_id)
    {
        $result = APICall::callAPI('POST', EndPoints::PRODUCTS_LISTING,json_encode([
            'category' => $catid,
            'shop' => $shop_id
        ]));
        $productlisting = json_decode($result, true);
        return view('customer.product.productlisting',compact('productlisting','catid','shop_id'));
    }
    public function productdetails(Request $request, $product_id,$catid,$shop_id)
    {
        $result = APICall::callAPI('POST', EndPoints::PRODUCTS_DETAILS,json_encode([
            'product'=> $product_id
        ]));
        $productdetails = json_decode($result, true);
        $result1 = APICall::callAPI('POST', EndPoints::PRODUCTS_LISTING,json_encode([
            'category' => $catid,
            'shop' => $shop_id
        ]));
        $productlisting = json_decode($result1, true);

        return view('customer.product.productdetails',compact('productdetails','productlisting'));
    }
    
    public function favourites()
    {
        $user = $this->user();
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

        $favouriteShops = json_decode(json_encode(ShopResource::collection($shops)), true);
        return view('customer.inner.favourites',compact('favouriteShops'));
    }
        
}

?>