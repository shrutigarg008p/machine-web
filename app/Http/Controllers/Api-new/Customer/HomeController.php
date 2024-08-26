<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\ApiController;
use App\Http\Resources\BannerResource;
use App\Models\ProductCategory;
use App\Repositories\BannerRepository;
use App\Services\Api\ApiResponse;
use Illuminate\Http\Request;

class HomeController extends ApiController
{

    private $bannerRepo;

    public function __construct()
    {
        parent::__construct();

        $this->bannerRepo = new BannerRepository();
    }

    public function index()
    {
        $user = $this->user();

        // $categories = cache()->remember('homepage_categories', 60*60, function() {
            $categories = ProductCategory::parentCategory()->get()->map(function($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'cover_image' => $category->cover_image
                        ? storage_url($category->cover_image)
                        : sample_img(1600, 1050, 'Product category')
                ];
            });
        // });

        $favourite_shops = $user->favourite_shops()->take(5)->get()
            ->map(function($shop) {
                $shop_image = isset($shop->photos[0]->photo) ? $shop->photos[0]->photo : sample_img(1600, 1050, $this->shop_name ?? '');
                return [
                    'id' => $shop->id,
                    'shop_name' => $shop->shop_name,
                    'shop_image' => $shop_image
                        ? storage_url($shop_image)
                        : sample_img(200, 200, $shop->shop_name ?? 'Awesome Shop')
                ];
            });

        return ApiResponse::ok(__('Homepage'), [
            'banners' => BannerResource::collection($this->bannerRepo->all()),
            'categories' => $categories,
            'favourite_shops' => $favourite_shops,
            'cart_count' => $user ? $user->cart_items()->count() : 0
        ]);
    }
}
