<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;


class AdminAuthController extends Controller
{
    
    public function index()
    {
        if(Auth::check()){
            return redirect('/admin/dashboard');
        }
        return view('admin.auth.index');
    }

    public function login(Request $request)
    {
        # Validate Form Requests
        $request->validate([
            'email'     => ['required','email'],
            'password'  => ['required']
        ]);
        
        $credentials = ['email' => $request->email, 'password' => $request->password];

        if (! Auth::attempt($credentials, $request->has('remember_me'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        # Authenticate User Login
        // Auth::login($user);
        return redirect()->route('admin.dashboard')
            ->withSuccess(__('Welcome to the Admin Dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('admin.index');

    }
}
