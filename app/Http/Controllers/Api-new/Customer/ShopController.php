<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\ApiController;
use App\Http\Resources\ProductCategoryResource;
use App\Http\Resources\ShopResource;
use App\Models\ProductCategory;
use App\Models\UserShop;
use App\Repositories\CategoryRepository;
use App\Repositories\ShopRepository;
use App\Services\Api\ApiResponse;
use Illuminate\Http\Request;

class ShopController extends ApiController
{
    private $shopRepo;

    private $categoryRepo;

    public function __construct()
    {
        parent::__construct();

        $this->shopRepo = new ShopRepository();
        $this->categoryRepo = new CategoryRepository();
    }

    public function index(Request $request)
    {
        $shops = $this->shopRepo;

        if( $user = $this->user() ) {
            $shops->setUser($user);
        }
	
//Change 26/10/22 by Tarachand
        if( $category_id = intval($request->get('category')) ) {
            if( $category = ProductCategory::find($category_id) ) {
                $shops->setProductCategory($category);
            }
        }

        return ApiResponse::ok(__('Shops'), ShopResource::collection($shops->all()));
    }

    public function detail(Request $request)
    {
        if( $shop_id = intval($request->get('shop')) ) {

            if( $shop = $this->shopRepo->find($shop_id) ) {

                $shop->load('categories.children');

                $shop->is_current_users_fav = $shop->isFavForUser($this->user());

                $data = [
                    'shop' => new ShopResource($shop),
                    'communication' => [
                        'call' => '',
                        'whatsapp' => '',
                        'share' => [
                            'facebook' => 'https://facebook.com',
                            'instagram' => 'https://facebook.com',
                        ]
                    ],
                    'overview' => '',
                    'services' => [],
                    'ratings' => [],
                    'categories' => []
                ];

                $data['overview'] = 'Lorem ipsum, dolor sit amet consectetur adipisicing elit. Sed laboriosam aliquam, error architecto omnis maxime, modi soluta laudantium iure dolorem incidunt voluptatum dolore. Cupiditate optio perferendis possimus, dolor quae architecto neque officia tenetur eius rem repellat perspiciatis adipisci porro temporibus necessitatibus molestiae suscipit iure sit dicta? Voluptates voluptas sapiente excepturi';

                $data['services'] = ['Home delivery', 'Takeaway available', 'Cash payment'];

                $data['ratings'] = [
                    'overall_average' => '4.8',
                    'total' => 2637,
                    'total_str' => '2.6K',
                    'total_by_star' => [
                        ['rating' => 5, 'total' => 1400, 'total_str' => '1.4K'],
                        ['rating' => 4, 'total' => 1190, 'total_str' => '1.1K'],
                        ['rating' => 3, 'total' => 236, 'total_str' => '236'],
                        ['rating' => 2, 'total' => 100, 'total_str' => '100'],
                        ['rating' => 1, 'total' => 14, 'total_str' => '14']
                    ],
                    'categories' => [
                        'service' => '100.0%',
                        'delivery' => '100.0%',
                        'quality' => '100.0%',
                        'price' => '100.0%'
                    ],
                    'total_reviews' => 4
                ];
                $categories = $shop->sub_categories;
   
                if( $categories->isNotEmpty() ) {
                    $data['categories'] = ProductCategoryResource::collection($categories);
                }

                return ApiResponse::ok(__('Product'), $data);
            }
        }

        return ApiResponse::error(__('Shop not found'));
    }

    public function favourites(Request $request)
    {
        $user = $this->user();
        $shops = $user->favourite_shops()
            ->with(['categories'])
            ->paginate(15)
            ->through(function($shop) {
                $category = $shop->categories->first();

                $shop->category = $category
                    ? [ 'id' => $category->id, 'title' => $category->title ]
                    : null;

                return $shop;
            });

        return ApiResponse::ok(__('Shops'), ShopResource::collection($shops));
    }

    public function add_to_favourite(Request $request)
    {

        if( $shop = intval($request->get('shop')) ) {
            if( $shop = UserShop::find($shop) ) {

                $removed = $this->user()->favourite_shops()->detach($shop->id);

                if( empty($removed) ) {
                    $this->user()->favourite_shops()->save($shop);

                    return ApiResponse::ok(__('Added to favourites'), ['is_favourite' => true]);
                }

                return ApiResponse::ok(__('Removed from favourites'), ['is_favourite' => false]);
            }
        }

        return ApiResponse::error(__('Shop not found'));
    }
}
