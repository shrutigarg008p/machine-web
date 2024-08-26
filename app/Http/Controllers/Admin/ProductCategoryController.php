<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Traits\UpdateStatus;
use Astrotomic\Translatable\Validation\RuleFactory;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    use UpdateStatus;

    protected $dt_resourceClass = ['product_categories', ProductCategory::class];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $categories = ProductCategory::withoutGlobalScope('active');

        switch( $request->query('filter') ) {
            case 'parent_only':
                $categories->parentCategory();
                break;
        }

        $categories = $categories
            ->latest()
            ->get();

        return view('admin.product_category.index', compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $categories = ProductCategory::parentCategory()->get();
            
        return view('admin.product_category.create', compact('categories'));
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
        ], null, null, null, frontend_languages(true));

        $rules['parent_id'] = ['nullable', 'exists:product_categories,id'];

        $rules['cover_image'] = ['nullable', 'image', 'max:5000'];

        $validated = $request->validate($rules);

        $validated['status'] = $request->has('status') ? 1 : 0;

        if( $cover_image = $request->file('cover_image') ) {
            if( $cover_image = $cover_image->store('product_categories', 'public') ) {
                $validated['cover_image'] = $cover_image;
            }
        }

        ProductCategory::create($validated);

        return redirect()->route('admin.product_category.index')->withSuccess(__('Data saved successfully'));
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
    public function edit(ProductCategory $productCategory)
    {
        $categories = ProductCategory::parentCategory()
            ->where('id', '<>', $productCategory->id)
            ->get();

        return view('admin.product_category.edit',  compact('productCategory', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ProductCategory $productCategory)
    {
        $rules = RuleFactory::make([
            '%title%' => ['nullable', 'string', 'max:191'],
            '%short_description%' => ['nullable', 'string', 'max:1000'],
            '%description%' => ['nullable', 'string', 'max:5000'],
        ], null, null, null, frontend_languages(true));

        $rules['parent_id'] = ['nullable', 'exists:product_categories,id'];

        $rules['cover_image'] = ['nullable', 'image', 'max:5000'];

        $validated = $request->validate($rules);

        $validated['status'] = $request->has('status') ? 1 : 0;

        if( $cover_image = $request->file('cover_image') ) {
            if( $cover_image = $cover_image->store('product_categories', 'public') ) {
                $validated['cover_image'] = $cover_image;
            }
        }

        $productCategory->update($validated);

        return redirect()->route('admin.product_category.index')->withSuccess(__('Data saved successfully'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(ProductCategory $productCategory)
    {
        $productCategory->delete();

        return back()->withSuccess(__('Removed successfully'));
    }
}
