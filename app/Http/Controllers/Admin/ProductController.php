<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Traits\UpdateStatus;
use Astrotomic\Translatable\Validation\RuleFactory;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use UpdateStatus;

    protected $dt_resourceClass = ['products', Product::class];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $productsCount = Product::withoutGlobalScope('active')->count();

        $products = Product::withoutGlobalScope('active')
            ->withFilters($request->query())
            ->with(['product_category'])
            ->paginate(15);

        return view('admin.product.index', compact('productsCount', 'products'));
    }

    private function format_data_for_datatabes($records)
    {
        return $records->reduce(function($acc, $product) {

            $acc[] = [
                'id' => $product->id,
                'title' => $product->title,
                'product_category' => $product->product_category->title,
                'status' => $product->status,
                'created_at' => $product->created_at->format('Y/m/d')
            ];

            return $acc;
        }, []);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $categories = ProductCategory::query()
            ->parentCategory()
            ->with(['children'])
            ->get();

        return view('admin.product.create', compact('categories'));
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = RuleFactory::make([
            '%title%' => ['nullable', 'string', 'max:191'],
            '%short_description%' => ['nullable', 'string', 'max:1000'],
            '%description%' => ['nullable', 'string', 'max:5000'],
            '%additional_info%' => ['array'],
            '%additional_info.*%' => ['max:191']
        ], null, null, null, frontend_languages(true));

        $rules['product_category_id'] = ['required', 'exists:product_categories,id'];
        $rules['cover_image'] = ['nullable', 'image', 'max:5000'];
        $rules['gallery'] = ['array', 'max:6'];
        $rules['gallery.*'] = ['image', 'max:5000'];
        // $rules['price_type'] = ['required', 'in:bid,fixed'];
        // $rules['fixed_price'] = ['required_if:price_type,fixed'];
        $rules['country'] = ['nullable', 'max:191'];

        $validated = $request->validate($rules);

        // additional info
        foreach( $validated as $key => $value ) {
            if( is_array($value) && isset($value['additional_info']) ) {
                $additional_info = $value['additional_info'];
                $additional_info = array_combine($additional_info['key'], $additional_info['value']);
                $validated[$key]['additional_info'] = json_encode(array_filter($additional_info));
            }
        }

        if( $cover_image = $request->file('cover_image') ) {
            if( $cover_image = $cover_image->store('products', 'public') ) {
                $validated['cover_image'] = $cover_image;
            }
        }

        $product = Product::create($validated);

        $gallery_images = [];

        foreach( (array)$request->file('gallery') as $gallery ) {
            if( $gallery = $gallery->store('products', 'public') ) {

                $gallery_images[] = [
                    'product_id' => $product->id,
                    'image' => $gallery
                ];
            }
        }

        if( !empty($gallery_images) ) {
            ProductImage::insert($gallery_images);
        }

        return redirect()->route('admin.product.index')->withSuccess(__('Data saved successfully'));
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
        $categories = ProductCategory::query()
            ->parentCategory()
            ->with(['children'])
            ->get();
        $product_images = ProductImage::where('product_id',$product->id)->get();
        return view('admin.product.edit',  compact('product', 'categories','product_images'));
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
        $imageCount = ProductImage::where('product_id',$product->id)->get()->count();
        // $imageCount = 6-$imageCount;
        $imageCount = 6-$request->pro_images_count;
        $rules = RuleFactory::make([
            '%title%' => ['nullable', 'string', 'max:191'],
            '%short_description%' => ['nullable', 'string', 'max:1000'],
            '%description%' => ['nullable', 'string', 'max:5000'],
            '%additional_info%' => ['array'],
            '%additional_info.*%' => ['max:191']
        ], null, null, null, frontend_languages(true));

        $rules['product_category_id'] = ['required', 'exists:product_categories,id'];
        $rules['cover_image'] = ['nullable', 'image', 'max:5000'];
        $rules['gallery'] = ['array', "max:$imageCount"];
        $rules['gallery.*'] = ['image', 'max:5000'];
        // $rules['price_type'] = ['required', 'in:bid,fixed'];
        // $rules['fixed_price'] = ['required_if:price_type,fixed'];
        $rules['country'] = ['nullable', 'max:191'];

        $validated = $request->validate($rules);

        // additional info
        foreach( $validated as $key => $value ) {
            if( is_array($value) && isset($value['additional_info']) ) {
                $additional_info = $value['additional_info'];
                $additional_info = array_combine($additional_info['key'], $additional_info['value']);
                $validated[$key]['additional_info'] = json_encode(array_filter($additional_info));
            }
        }

        if( $cover_image = $request->file('cover_image') ) {
            if( $cover_image = $cover_image->store('products', 'public') ) {
                $validated['cover_image'] = $cover_image;
            }
        }
        $product->fill($validated);
        $product->update();

        if($request->hasfile('gallery'))
        {
            foreach($request->file('gallery') as $file)
            {
                $file_path = $file->store('products', 'public');
                $user_company_photos = ProductImage::create([
                'product_id'    => $product->id,
                'image'   => $file_path,
                ]);    
            }
        }

        $delString= $request->pro_img_id;
        $delArray = explode(',', $delString);
        foreach($delArray as $val){
             ProductImage::where('id',$val)->delete();
        }

        if($request->hasFile('gallerys')){
            $gallerys = array_values($request->file('gallerys'));
            foreach($gallerys as $key => $file){
                $updateImage= $request->updateImage;
                $updateImageArray = explode(',', $updateImage);
                $extension =$file->extension();
                $rand = rand('000','999');
                $file_path = $file->store('products', 'public');
                $success = ProductImage::where('id',$updateImageArray[$key])->update(['image'=>$file_path]);
            }
        }
        return redirect()->route('admin.product.index')->withSuccess(__('Data saved successfully'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Product $product)
    {
        $product->delete();
        return back()->withSuccess(__('Removed successfully'));
    }
}
