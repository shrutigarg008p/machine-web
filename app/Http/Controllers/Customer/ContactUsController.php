<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ContactUs;

class ContactUsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
        $request->validate([
            'name'=> ['required','string','regex:/^[a-zA-Z]+$/u','min:4','max:10'],
            'email' => ['required','email','max:25'],
            'phone'         => ['required', 'numeric','min:10','digits:10','regex:/^(7|8|9)\d{9}/'],
            'subject'           => ['required', 'min:4','max:20'], 
            'message' => ['required'],
          
        ]);

        $input = $request->all(); 
        ContactUs::create($input); 
        //  Send mail to admin 
        try{
            \Mail::send('mails/contact/contact', array( 
                'name' => $input['name'], 

                'email' => $input['email'], 

                'phone' => $input['phone'], 

                'subject' => $input['subject'] ?  $input['subject'] : "User Enquiry", 

                'message' => $input['message'], 

            ), function($message) use ($request){ 
                $message->from($request->email); 
                $message->to('amit.tomar@unyscape.com', 'Admin')->subject($request->get('subject')); 
            }); 
        } 
        catch(\Exception $e) {
                logger(' issue: '.$e->getMessage());
        }

        return redirect()->back()->with(['success' => 'Contact Form Submit Successfully']); 


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
