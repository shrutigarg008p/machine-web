<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banner;
use App\Models\Translation\BannerTranslation;
use Astrotomic\Translatable\Validation\RuleFactory;


class BannerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $banner = Banner::orderBy('id','desc')->get();
        return view('admin.banner.index', compact('banner'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
         // $categories = ProductCategory::parentCategory()->get();
            
        return view('admin.banner.create');
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

         $rules = RuleFactory::make([
            '%title%' => ['nullable', 'string', 'max:191'],
            '%short_description%' => ['nullable', 'string', 'max:1000'],
        ], null, null, null, frontend_languages(true));


        $rules['image'] = ['nullable', 'image', 'max:5000'];

        $validated = $request->validate($rules);


        if( $image = $request->file('image') ) {
            if( $image = $image->store('banner', 'public') ) {
                $validated['image'] = $image;
            }
        }

        Banner::create($validated);
        // BannerTranslation::create($validated);

        return redirect()->route('admin.banner.index')->withSuccess(__('Data saved successfully'));
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
    public function edit(Banner $banner)
    {
        //
        // dd($id);

        $banner_datas = Banner::where('id',$banner->id)->first();
        return view('admin/banner/edit',compact('banner_datas','banner')); 


    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Banner $banner)
    {
        //
        $rules = RuleFactory::make([
            '%title%' => ['nullable', 'string', 'max:191'],
            '%short_description%' => ['nullable', 'string', 'max:1000'],
        ], null, null, null, frontend_languages(true));


        $rules['image'] = ['nullable', 'image', 'max:5000'];

        $validated = $request->validate($rules);


        if( $image = $request->file('image') ) {
            if( $image = $image->store('banner', 'public') ) {
                $validated['image'] = $image;
            }
        }

        $banner->update($validated);

        return redirect()->route('admin.banner.index')->withSuccess(__('Data saved successfully'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Banner $banner)
    {
        //
        Banner::where('id',$banner->id)->delete();
        return redirect()->route('admin.banner.index')->withSuccess(__('Removed successfully'));
    }
}
