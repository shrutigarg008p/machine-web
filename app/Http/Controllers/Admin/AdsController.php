<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ad;
use App\Models\AdSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $allAdsData=Ad::where('status',1)->get();
        return view('admin.ads/ads',compact('allAdsData'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        $textadsData=Ad::where('type','Text')->get();
        $imgadsData=Ad::where('type','Image')->get();
        $urladsData=Ad::where('type','URL')->get();
        $allAdsData=Ad::orderBY('type','desc')->get();
        $ad_set=AdSetting::first();
        return view('admin.ads/create',compact('ad_set','textadsData','imgadsData','urladsData','allAdsData'));
    }

    public function changeShowStatus(Request $request)
    {
        // dd($request->status);
        // $adscreen = AdScreen::find($request->screen_id);
        // $adscreen->status = $request->status;
        // $adscreen->save();
        if(!empty($request->status))
        DB::table('ad_settings')->update(['is_enable' => $request->status]);
        // $arr = array('message' => 'Status Updated Successfully', 'title' => 'Status');
        // return json_encode($arr);
        return response()->json(['success'=>'Ad status changed successfully.']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->all());

        if($request->textAds == "textAds"){
        
            // $validator = Validator::make($request->all(),[
            //       'ad_title.*'=>'required',
            // ]);


            // dump(request()->has('text_radio'));
            // dump(request()->get('text_radio'));
            // dump( 

            //     empty( array_filter( request()->get('ad_title') ) ) 
            // );
            // dump(!empty(request()->get('text_radio')));
            // dump(request()->has('text_radio') && !empty(request()->get('text_radio')) && empty( array_filter( request()->get('ad_title') ) ));
            if(request()->has('text_radio') && !empty(request()->get('text_radio')) && empty( array_filter( request()->get('ad_title') ) ) ){

                // dump('if');
                $chkAd = Ad::where('status',1)->get();
                foreach($chkAd as $k){
                 $k->status =0;
                $k->save();   
                }
                Ad::where('id',$request->text_radio)->update(['status'=>1]); 
                return response()->json(['status'=>1,'redirectvalue'=>1,'msg'=>'Status Update successfully']);

            }
            else{

                // dump('else');
                $validator = Validator::make($request->all(),[
                  'ad_title.*'=>'required',
                ]);

                if(!$validator->passes()){
                  return response()->json(['status'=>0, 'error'=>$validator->errors()->toArray()]);
                }
                else{

                    $text_titles = $request->get('ad_desc');

                    foreach ($request->ad_title as $key => $value) {
                    Ad::create(['title'=>$value,'description'=>$text_titles[$key],'type'=>'Text','ads_type_show'=>$request->active_ad_show_text[0] ?? null]);
                    }
                    if($request->text_radio){
                        $chkAd = Ad::where('status',1)->get();
                        foreach($chkAd as $k){
                         $k->status =0;
                        $k->save();   
                        }
                        Ad::where('id',$request->text_radio)->update(['status'=>1]);  
                    }
                    return response()->json(['status'=>1,'redirectvalue'=>0,'msg'=>'Text Ad  has been successfully added']);
                } 
            }






           
       
        }
























        if($request->imgAds=="imgAds"){

            if(request()->has('image_radio') && !empty(request()->get('image_radio')) && empty( array_filter( request()->get('ad_img_title') ) ) ){

                $chkAd = Ad::where('status',1)->get();
                    foreach($chkAd as $k){
                     $k->status =0;
                    $k->save();   
                    }
                    Ad::where('id',$request->image_radio)->update(['status'=>1]);  
                return response()->json(['status'=>1,'redirectvalue'=>1,'msg'=>'Status Update successfully']);

            }
            else{

            // dd($request->file('ad_img_upload'));
            $validator = Validator::make($request->all(),[
                  'ad_img_title.*'=>'required',
                  // 'profileImage.*'=>'required|image|mimes:jpeg,png,jpg,gif,svg|max:20',
                  'ad_img_upload' => 'required',
                  'ad_img_upload.*' => 'image',
  
              ]);
            // dd($validator);
            if(!$validator->passes()){
                  return response()->json(['status'=>0, 'error'=>$validator->errors()->toArray()]);
              }else{
                    $image_titles = $request->get('ad_img_title');
                    $images = (array)$request->file('ad_img_upload');
                    $images = \array_filter($images);
                    if(count($images)>0){
                    foreach($images as $key => $image) {
                        $img['title'] = $image_titles[$key];
                        
                        $image_path = $image->store('ads/img', 'public');
                        $img['image'] = $image_path;
                        $img['type']= 'Image';
                        $img['ads_type_show']=$request->active_ad_show_img[0] ?? null;

                        $save = Ad::create($img);
                        // if($save){
                        //                       return response()->json(['status'=>1, 'msg'=>'New Student has been successfully registered']);

                        // }
                    }
                 }else{
                     foreach ($request->ad_img_title as $key => $value) {
                    Ad::create(['title'=>$value,'image'=>null,'type'=>'Image','ads_type_show'=>$request->active_ad_show_img[0] ?? null]);
                    }

                 }
                    if($request->image_radio){
                        $chkAd = Ad::where('status',1)->get();
                        foreach($chkAd as $k){
                         $k->status =0;
                        $k->save();   
                        }
                        Ad::where('id',$request->image_radio)->update(['status'=>1]);  
                    }
                }
                return response()->json(['status'=>1,'redirectvalue'=>0, 'msg'=>'Image Ad  has been successfully added']);
            }
        }

        if($request->urlAds == "urlAds"){

             if(request()->has('url_radio') && !empty(request()->get('url_radio')) && empty( array_filter( request()->get('ad_url_title') ) ) ){

                $chkAd = Ad::where('status',1)->get();
                foreach($chkAd as $k){
                 $k->status =0;
                $k->save();   
                }
                Ad::where('id',$request->url_radio)->update(['status'=>1]); 
                return response()->json(['status'=>1,'redirectvalue'=>1,'msg'=>'Status Update successfully']);

            }
          $validator = Validator::make($request->all(),[
              'ad_url_title.*'=>'required',
              // 'ad_url.*'=>'required',
            
          ]);
          if(!$validator->passes()){
              return response()->json(['status'=>0, 'error'=>$validator->errors()->toArray()]);
          }else{
            $url_titles = $request->get('ad_url');

            foreach ($request->ad_url_title as $key => $value) {
            Ad::create(['title'=>$value,'url'=>$url_titles[$key],'type'=>'URL','ads_type_show'=>$request->active_ad_show_url[0] ?? null]);
            }

             if($request->url_radio){
                $chkAd = Ad::where('status',1)->get();
                foreach($chkAd as $k){
                 $k->status =0;
                $k->save();   
                }
                Ad::where('id',$request->url_radio)->update(['status'=>1]);  
            }

          } 
          return response()->json(['status'=>1,'redirectvalue'=>0 ,'msg'=>'URL Ad  has been successfully added']);
        }   
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
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
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
