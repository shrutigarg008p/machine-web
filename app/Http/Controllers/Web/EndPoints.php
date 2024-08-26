<?php

namespace App\Http\Controllers\Web;

class EndPoints
{
    public const LOGIN_CUSTOMER = '/api/login';
    public const REGISTER_LOGIN_USER = '/api/login_via_otp';
    public const VERIFY_VIA_OTP = '/api/login_via_otp/verify';
    public const PRODUCT_CATEGORY_LISTING = '/api/products/category_listing';
    public const SHOP_LISTING = '/api/customer/shop';
    public const STATIC_CONTENT = '/api/content';
    public const SHOP_FAV_LIST = '/api/customer/shop/favourites';
    public const SELLER_ORDER_QUOTATIONS = '/api/seller/order';
    public const ACCEPT_REJECT_BID = '/api/seller/order/accept_reject_bid';
    public const ORDER_DETAILS = '/api/seller/order/order_detail';

    //Customer
    public const SHOP_CATEGORIES = '/api/products/category_listing';
    public const SHOP_BYCATEGORIES = '/api/customer/shop';
    public const SHOP_DETAILS = '/api/customer/shop/detail';
    public const CATEGORY_PRODUCTS = '/api/customer/shop/detail';
    public const PRODUCTS_LISTING = '/api/products';
    public const PRODUCTS_DETAILS = '/api/products/detail';
    public const FAVOURITE_SHOP = '/api/customer/shop/favourites';
    public const ORDER_LIST = '/api/customer/order/orders';
    public const ORDER_DETAIL = '/api/customer/order/order_detail';
    public const LOGOUT = '/api/logout';
    public const ADD_ADDRESS = '/api/customer/address/create';
    //Customer

}

?>