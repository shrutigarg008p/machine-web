<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Complaint;
use App\Models\User;
class ComplaintController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // dd($request->all());
        // $pend_complaints =  Complaint::where('status',0)->get();
        // $progress_complaints =  Complaint::where('status',1)->get();
        // $completed_complaints =  Complaint::where('status',2)->get();
        $complaints =  Complaint::get();
        $status =  $request->status;
        // return view('admin/complaints/index',compact('pend_complaints','status','progress_complaints','completed_complaints'));
        return view('admin/complaints/index',compact('complaints','status'));
        
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
    public function show(Complaint $complaint)
    {
        //
        // dd($user);
        $complaint = $complaint->id;
        /*$user_id = $user->id;
        $comp_user = Complaint::where('id',$complaint)->where('user_id',$user_id)->first();*/
        $comp_user = Complaint::where('id',$complaint)->first();
        if(!empty($comp_user))
        $show_user =User::where('id',$comp_user->user_id)->first();
        return view('admin/complaints/show',compact('show_user','comp_user'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Complaint $complaint,User $user)
    {
        $edit_comp = Complaint::where('id',$complaint->id)->first();
        if(!empty($edit_comp))
        $user =User::where('id',$edit_comp->user_id)->first();
        return view('admin/complaints/edit',compact('edit_comp','user'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,Complaint $complaint,User $user)
    {
        //

        $request->validate([
        'status'    =>  ['required'],
        'remark'   => ['required'],
        ]);
        $user_id = $user->id;
        Complaint::where('id',$complaint->id)->where('user_id',$user_id)->update(['status'=>$request->status,'remark'=>$request->remark]);
        return redirect('admin/complaints/list?status='.$request->status)->withSuccess(__('updated successfully'));

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
