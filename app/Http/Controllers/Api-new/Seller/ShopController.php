<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\ApiController;
use App\Http\Resources\ShopResource;
use App\Models\UserShop;
use App\Models\UserShopPhoto;
use App\Services\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ShopController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $seller = $this->user();

        $shops = $seller->user_shops()
            ->with([
                'categories.children',
                'photos'
            ]);

        $shops = $shops->paginate(1000)
            ->through(function($shop) {
                $this->shop_additional_info($shop);
                return $shop;
            });

        return ApiResponse::ok(__('Shops'), ShopResource::collection($shops));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'shop_owner'     => ['required', 'max:191'],
            'shop_name'     => ['required', 'max:191'],
            'shop_email'     => ['required', 'max:191', 'email'],
            'shop_contact'     => ['required', 'digits_between:8,18'],
            
            'registration_no' => ['nullable', 'max:191'],
            'country'         => ['nullable', 'max:4'],
            'state'           => ['nullable', 'max:191'],
            'address_1'       => ['nullable', 'max:191'],
            'address_2'       => ['nullable', 'max:191'],

            'product_categories' => ['nullable', 'array', 'max:5'],
            'product_categories.*' => ['numeric',
                Rule::exists('product_categories','id')->where(function($query) {
                    $query->where('status', 1)->whereNull('parent_id');
                })
            ],

            'working_hours_from' => ['nullable', 'date_format:H:i'],
            'working_hours_to' => ['nullable', 'date_format:H:i', 'after:working_hours_from'],

            'working_days'     => ['nullable', 'array', 'max:7'],
            'working_days.*'     => ['in:Su,Mo,Tu,We,Th,Fr,Sa'],

            'photos'          => ['nullable', 'array', 'max:6'],
            'photos.*'        => ['nullable', 'mimes:jpeg,png,jpg', 'max:2500'],

            'latitude'       => ['nullable', 'max:191', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude'      => ['nullable', 'max:191', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
        ]);

        DB::beginTransaction();

        try {

            if( isset($data['working_days']) ) {
                $data['working_days'] = \implode(',', $data['working_days']);
            }

            $userShop = $this->user()->user_shops()->create( $data );

            foreach( (array)$request->file('photos') as $photo ) {
                if( $photo = $photo->store('shops_photos', 'public') ) {
    
                    $shop_photos[] = [
                        'user_shop_id' => $userShop->id,
                        'photo' => $photo
                    ];
                }
            }
    
            if( !empty($shop_photos) ) {
                UserShopPhoto::insert($shop_photos);
            }

            if( ! empty( $product_categories = (array)$request->get('product_categories') ) ) {
                $userShop->categories()
                    ->sync($product_categories);
            }

            DB::commit();

            $userShop->load(['categories.shops', 'photos']);

            return ApiResponse::ok(__('Shop saved'), new ShopResource($userShop));

        } catch(\Exception $e) {
            DB::rollBack();

            logger($e->getMessage());
        }

        return ApiResponse::ok(__('Something went wrong'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $request->validate([
            'shop_id' => ['required', 'integer', 'exists:user_shops,id']
        ]);

        $seller = $this->user();

        $shop = $seller->user_shops()
            ->with(['categories.shops', 'photos']);

        // probably won't be required
        if( $seller->latitude && $seller->longitude ) {
            $shop->withDistance($seller->latitude, $seller->longitude);
        }

        $shop = $shop->findOrFail($request->get('shop_id'));

        $this->shop_additional_info($shop);

        return ApiResponse::ok(__('Shop'), new ShopResource($shop));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'shop_id' => ['required', 'numeric', 'exists:user_shops,id'],

            'shop_owner'     => ['nullable', 'max:191'],
            'shop_name'     => ['nullable', 'max:191'],
            'shop_email'     => ['nullable', 'max:191', 'email'],
            'shop_number'     => ['nullable', 'digits_between:8,18'],
            
            'registration_no' => ['nullable', 'max:191'],
            'country'         => ['nullable', 'max:4'],
            'state'           => ['nullable', 'max:191'],
            'address_1'       => ['nullable', 'max:191'],
            'address_2'       => ['nullable', 'max:191'],

            'product_categories' => ['nullable', 'array', 'max:5'],
            'product_categories.*' => ['numeric',
                Rule::exists('product_categories','id')->where(function($query) {
                    $query->whereNull('parent_id');
                })
            ],

            'working_hours_from' => ['nullable', 'date_format:H:i'],
            'working_hours_to' => ['nullable', 'date_format:H:i', 'after:working_hours_from'],

            'working_days'     => ['nullable', 'array', 'max:7'],
            'working_days.*'     => ['in:Su,Mo,Tu,We,Th,Fr,Sa'],

            'photos'          => ['nullable', 'array', 'max:6'],
            'photos.*'        => ['nullable', 'mimes:jpeg,png,jpg', 'max:2500'],

            'latitude'       => ['nullable', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude'      => ['nullable', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
        ]);

        DB::beginTransaction();

        try {

            if( isset($data['working_days']) ) {
                $data['working_days'] = \implode(',', $data['working_days']);
            }

            /** @var \App\Models\UserShop $userShop */
            $userShop = $this->user()->user_shops()->findOrFail($request->get('shop_id'));

            foreach( (array)$request->file('photos') as $photo ) {
                if( $photo = $photo->store('shops_photos', 'public') ) {
    
                    $shop_photos[] = [
                        'user_shop_id' => $userShop->id,
                        'photo' => $photo
                    ];
                }
            }
    
            if( !empty($shop_photos) ) {
                UserShopPhoto::insert($shop_photos);
            }

            $product_categories = $request->get('product_categories');

            $product_categories = \array_unique(\array_filter($product_categories));

            if( ! empty( $product_categories ) ) {
                $userShop->categories()
                    ->sync($product_categories);
            }

            DB::commit();

            $userShop->load(['categories.shops', 'photos']);

            $this->shop_additional_info($userShop);

            return ApiResponse::ok(__('Shop saved'), new ShopResource($userShop));

        } catch(\Exception $e) {
            DB::rollBack();

            logger($e->getMessage());
        }

        return ApiResponse::ok(__('Something went wrong'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'shop_id' => ['required', 'integer', 'exists:user_shops,id']
        ]);

        $seller = $this->user();

        $seller->user_shops()->where('id', $request->get('shop_id'))->delete();

        return ApiResponse::ok(__('Shop removed'));
    }

    private function shop_additional_info(UserShop $shop)
    {
        $additional = [
            'quotes' => [
                'new' => '2',
                'in_progress' => '2',
                'closed' => '2',
            ],
            'orders' => [
                'new' => '2',
                'in_progress' => '2',
                'closed' => '2',
            ],
            'products' => [
                'active' => '212'
            ],
        ];

        $shop->setRelation('_additional_info', $additional);

        return $additional;
    }
}
