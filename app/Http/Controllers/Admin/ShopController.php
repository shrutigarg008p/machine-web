<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserShop;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index()
    {
        $shops = UserShop::active()->paginate(15);

        return view('admin.shop.index', compact('shops'));
    }
}
