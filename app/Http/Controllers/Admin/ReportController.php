<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Vars\Roles;
use App\Exports\UserReportExport;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function userreport(Request $request,$type){
        # User Query Instance
        $users = User::orderBy('id', 'DESC')
            ->whereDoesntHave('roles', function ($query) {
            $query->whereIn('name', [Roles::SUPER_ADMIN]);
        });
        # Filter Users By Type
        if (in_array($type, [Roles::CUSTOMER, Roles::SELLER])) {
            $users->role($type);
        }
        $users = $this->checkFilterValuesforReport($request,$users);
        
        # Get Users Collection
        $users = $users->orderBy('id','DESC')->get();
        
        return view('admin.report.userreport', compact('users','type'));
    }

    public function getuserReport(Request $request,$type,$filetype){
        # User Query Instance
        $users = User::orderBy('id', 'DESC')
            ->whereDoesntHave('roles', function ($query) {
            $query->whereIn('name', [Roles::SUPER_ADMIN]);
        });
        if (in_array($type, [Roles::CUSTOMER, Roles::SELLER])) {
            $users->role($type);
        }
        // $query = $this->checkFilterValuesforReport($request,$query);
        $users = $users->orderBy('id','DESC')->get();

        if($filetype=='pdf'){
            $mpdf = new \Mpdf\Mpdf(
                ['tempDir' => storage_path('temp')]
            );
            $htmlData =view('admin.report.pdfViews.userPDF', compact('users','type'))->render();
            $mpdf->WriteHTML($htmlData);
            $mpdf->Output("UserReport.pdf", "D");
        }else{
            $user = new UserReportExport($users,$type);
            return \Excel::download($user,'UserReport.xls');
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function checkFilterValuesforReport($request,$query){
        
        if( $starts_at = \strtotime($request->query('starts_at')) ) {
            $query->whereDate('created_at', '>=', date('Y-m-d H:i:s', $starts_at));
        }
        if( $starts_at = \strtotime($request->query('ends_at')) ) {
            $query->whereDate('created_at', '<=', date('Y-m-d H:i:s', $starts_at));
        }
        return $query;
    }
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
