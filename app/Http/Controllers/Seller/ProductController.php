<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\UserShop;
use App\Models\SellerProduct;
use Astrotomic\Translatable\Validation\RuleFactory;
use Illuminate\Support\Facades\Auth;


class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($cat,$shop_id)
    {
        $productsCount = Product::where('status','1')->where('product_category_id',$cat)->count();

          $subCategory = ProductCategory::where('parent_id',$cat)->get();
            foreach ($subCategory as $key => $value) {
                $products = Product::where('status','1')->where('product_category_id',$value->id)->get();
                $value->products=$products;
             }  
       return view('seller.product.index', compact('productsCount', 'subCategory','shop_id','cat'));
    }

    public function myProducts()
    {
        $user = Auth::user() ?? null;
        $my_products =  SellerProduct::where('seller_id', $user->id)->get();
        return view('seller.product.myproducts', compact('my_products'));
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
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Product $product)
    {
        //
        $categories = ProductCategory::query()
            ->parentCategory()
            ->with(['children'])
            ->get();
        $product_images = ProductImage::where('product_id', $product->id)->get();
        $user = Auth::user() ?? null;
        $shop_datas = UserShop::where('user_id', $user->id)->get();
        // $seller_products = SellerProduct::where('product_id',$product->id)->where('')
        return view('seller.product.edit',  compact('product', 'categories', 'product_images', 'shop_datas'));
    }

    public function editProduct($id,$shop_id,$cat)
    {
        //

        $product= Product::find($id);
        $user = Auth::user() ?? null;
        $shop_datas = UserShop::where('user_id', $user->id)->where('id',$shop_id)->get();
        // $seller_products = SellerProduct::where('product_id',$product->id)->where('')
        return view('seller.product.edit',  compact('product',  'shop_datas','cat'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Product $product)
    {

        // dd($request->all());
        $validator = $request->validate(
            [
                'price_type'    =>  ['required', 'in:bid,fixed'],
                'fixed_price' => ['required_if:price_type,fixed'],
                'shops' => ['required']
            ],
            ['fixed_price.required_if' => 'The fixed price field is required']
        );

        if (isset($request->status)) {
            $status = $request->status;
        } else {
            $status = 0;
        }
        
        SellerProduct::updateOrCreate([
            'product_id'   => $request->product_id,
            'shop_id' => $request->shops,
            'seller_id' => $request->seller_id
        ], [
            'price_type' => $request->price_type, 'price' => $request->fixed_price, 'qty' => $request->quantity, 'status' => $status
        ]);

        return redirect(route('seller.product.category',[$request->category, $request->shops]))->withSuccess(__('Data saved successfully'));
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
}
