<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use Illuminate\Http\Request;
use Astrotomic\Translatable\Validation\RuleFactory;

class ContentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $contents = Content::all();
        return view('admin.content.index', compact('contents'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $slug)
    {
        $content = $this->get_content($slug);

        return view('admin.content.edit', compact('content'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $slug)
    {
        $content_manager = $this->get_content($slug);

        $rules = RuleFactory::make([
            '%title%' => ['nullable', 'string', 'max:191'],
            '%page_content%' => ['nullable', 'string', 'max:1000'],
        ], null, null, null, frontend_languages(true));
        $validated = $request->validate($rules);
        $content_manager->updateOrCreate(['slug' => $slug], $validated);
        return redirect()->route('admin.content_manager.index')->withSuccess(__('Content Updated Successfully'));
    }

    protected function get_content($slug)
    {
        if( ! in_array($slug, config('app.static_content_slugs')) ) {
            abort(404);
        }

        return Content::where('slug', $slug)->first()
            ?? new Content(['slug' => $slug]);
    }
}
