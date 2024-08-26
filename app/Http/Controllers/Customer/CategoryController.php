<?php

namespace App\Http\Controllers\Customer;

use App\Traits\APICall;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShopResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SellerProduct;
use App\Models\UserShop;
class CategoryController extends Controller
{
    use APICall;

    public function __construct(){
        $this->middleware('auth');
    }

    public function categoryList(Request $request){
        $result = APICall::callAPI('POST',config('app.url') .EndPoints::SHOP_CATEGORIES,json_encode([
            'parent_only' =>1
        ]));
        $categories = json_decode($result, true);
        return view('customer.product.category',compact('categories'));
    }
}

?>