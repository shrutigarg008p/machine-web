<?php

namespace App\Http\Controllers\Customer;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Http\Resources\CustomerProductResource;
use App\Models\SellerProduct;
use App\Http\Resources\CartItemBiddingResource;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\QuotationResource;
use App\Models\ProductBidding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Repositories\ShopRepository;
use App\Http\Resources\ShopResource;
use App\Models\ShopRating;
use App\Http\Resources\ProductCategoryResource;

class ChatController extends Controller
{
    private $shopRepo;

    public function __construct()
    {
        parent::__construct();

        $this->shopRepo = new ShopRepository();
    }

    public function index()
    {
        $user = Auth::user() ?? null;

        $channels = ChatChannel::where('user_id', $user->id)
            ->orWhereHas('participants', function($query) use($user) {
                $query->where('chat_participants.user_id', $user->id);
            })
            ->with(['user', 'participants'])
	        ->orderBy('id','DESC')
            ->get();
        // dd($channels);

        foreach($channels as $value){
            $unread_msg =  ChatMessage::where('chat_channel_id',$value->id)->where('user_id','!=',$user->id)->where('status',0)->count();
            $last_msg =  ChatMessage::where('chat_channel_id',$value->id)->select('message')->latest()->first();
            $value->unread_msg = $unread_msg;
            $value->last_msg = isset($last_msg->message) ? $last_msg->message : NULL;
          }
          
        $channels;
        return view('customer.inner.chat.index', compact('channels'));
    }
    
    // initiate a chat
    public function createChannel(Request $request)
    {
        // TODO: Authorize whether this user can initiate chat
        //      with this bidder

        // TODO: if a chat already initiate; redirect to chat screen

        $user = $this->user();

        $participants = (array) $request->get('users');
        $message = $request->get('message');
	$type = $request->get('type');
	$type = isset($type) ? $type : 0;
        array_push($participants, $user->id);

        // try to locate the existing channel
        // dd($this->checkForExistingChannel($participants));
        if( $channel = $this->checkForExistingChannel($participants) ) {
            if(isset($message)){
                $chnl = ChatChannel::find($channel->id);
                $this->staticMessage($chnl,$message,$type);
            }
            return redirect()->route('chat.channel.view', ['channel' => $channel->id]);
            //return $channel = $this->checkForExistingChannel($participants);
            //return redirect()->route('chat.channel.view', ['channel' => $channel->id,'orderid'=> $chatOrderId]);

        }

        DB::beginTransaction();

        try {

            $channel = $user->chat_channels()
                ->save(new ChatChannel());

            $channel->participants()
                ->attach($participants);

            if(isset($message)){
                $this->staticMessage($channel,$message,$type);
            }

            DB::commit();
            // redirect to chat screen
            return redirect()->route('chat.channel.view', ['channel' => $channel->id])
                ->withSuccess(__('Chat initiated successfully'));

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        DB::rollBack();

        return back()->withError(__('Something went wrong!'));
    }
    public function viewChannel(ChatChannel $channel)
    {
	    $user = $this->user();
        //Update Chat message status unread to  read
        ChatMessage::where('chat_channel_id',$channel->id)->where('user_id','!=',$user->id)->where('status',0)->update(['status'=>'1']);

        // TODO: authorize whether this user can see this channel

        

        $messages = $channel->messages()
            ->with(['user'])
            ->latest()
            ->get()
            ->reverse();

        $participants = $channel->participants
            ->filter(function($participant) use($user) {
                return $participant->id !== $user->id;
            });

        return view('customer.inner.chat.view', compact('channel', 'messages', 'participants','user'));
    }

    public function createProductChannel(Request $request)
    {
        // TODO: Authorize whether this user can initiate chat
        //      with this bidder

        // TODO: if a chat already initiate; redirect to chat screen

        $user = $this->user();

        $participants = (array) $request->get('users');
        $productTitle = $request->get('product_title');
        $productId = $request->get('productid');
        array_push($participants, $user->id);

        // try to locate the existing channel
        if( $channel = $this->checkForExistingChannel($participants) ) {
            return redirect()->route('chat.channel.viewproduct', ['channel' => $channel->id,'productid'=> $productId]);
        }

        DB::beginTransaction();

        try {

            $channel = $user->chat_channels()
                ->save(new ChatChannel());

            $channel->participants()
                ->attach($participants);

            DB::commit();

            // redirect to chat screen
            return redirect()->route('chat.channel.viewproduct', ['channel' => $channel->id,'productid'=> $productId])
                ->withSuccess(__('Chat initiated successfully'));

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        DB::rollBack();

        return back()->withError(__('Something went wrong!'));
    }

    public function viewChannelProduct(ChatChannel $channel,$productId)
    {
        $user = $this->user();
        //Update Chat message status unread to  read
        ChatMessage::where('chat_channel_id',$channel->id)->where('user_id','!=',$user->id)->where('status',0)->update(['status'=>'1']);

        // TODO: authorize whether this user can see this channel

        
        // $id=$productId;
        $messages = $channel->messages()
            ->with(['user'])
            ->latest()
            ->get()
            ->reverse();

        $participants = $channel->participants
            ->filter(function($participant) use($user) {
                return $participant->id !== $user->id;
            });


            /**/
            $product = intval($productId);

        if( $sellerProduct = SellerProduct::find($product) ) {

            $data = new CustomerProductResource($sellerProduct);

            $product = $sellerProduct->product;

            $product_images = $product->product_images
                ->map(function($image) {
                    return storage_url($image->image);
                });

            $additional_info = $product->additional_info
                ? json_decode($product->additional_info)
                : [];

            $additional_info = collect($additional_info)
                ->filter()
                ->map(function($value, $key) {
                    return [
                        'key' => $key,
                        'value' => $value
                    ];
                });

                $user = $this->user();

            $in_cart = false;

            if( $user && $user->cart ) {
                $in_cart = $user->cart->isSellerProductInCart($sellerProduct);
            }

            $data->additional([
                'addtional_images' => $product_images,
                'description' => $product->description,
                'additional_info' => array_values($additional_info->toArray()),
                'is_favourite' => $user && $sellerProduct->isFavForUser($user),
                'in_cart' => $in_cart
            ]);

            $productdetails =json_decode(json_encode($data), true); 
        }
            /**/

        return view('customer.inner.chat.viewproductchat', compact('channel', 'messages', 'participants','user','productId','productdetails'));
    }

    public function chatProductInfo(Request $request)
    {


           $product = intval($request->productid);

        if( $sellerProduct = SellerProduct::find($product) ) {

            $data = new CustomerProductResource($sellerProduct);

            $product = $sellerProduct->product;

            $product_images = $product->product_images
                ->map(function($image) {
                    return storage_url($image->image);
                });

            $additional_info = $product->additional_info
                ? json_decode($product->additional_info)
                : [];

            $additional_info = collect($additional_info)
                ->filter()
                ->map(function($value, $key) {
                    return [
                        'key' => $key,
                        'value' => $value
                    ];
                });

                $user = $this->user();

            $in_cart = false;

            if( $user && $user->cart ) {
                $in_cart = $user->cart->isSellerProductInCart($sellerProduct);
            }

            $data->additional([
                'addtional_images' => $product_images,
                'description' => $product->description,
                'additional_info' => array_values($additional_info->toArray()),
                'is_favourite' => $user && $sellerProduct->isFavForUser($user),
                'in_cart' => $in_cart
            ]);

            $productdetails =json_decode(json_encode($data), true); 
        }

           return $productdetails;
    
    }
    /**/

    public function chatShopInfo(Request $request)
    {
        if( $shop_id = intval($request->shopid) ) {
            if( $shop = $this->shopRepo->find($shop_id) ) {

                $shop->load('categories.children');

                $shop->is_current_users_fav = $shop->isFavForUser($this->user());
                $ratings_count = ShopRating::where('shop_id',$shop->id)->count();
                $rate_total = ShopRating::where('shop_id',$shop->id)->sum('rate');
                if($ratings_count != 0 and $rate_total != 0){
                $overall_average = $rate_total/$ratings_count;
                }else{
                    $overall_average =5;
                }
                $ratings = ShopRating::where('shop_id',$shop->id)->select('rate','review')->get();

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
                    'shop_ratings' => $ratings,
                    'categories' => []
                ];

                $data['overview'] = 'Lorem ipsum, dolor sit amet consectetur adipisicing elit. Sed laboriosam aliquam, error architecto omnis maxime, modi soluta laudantium iure dolorem incidunt voluptatum dolore. Cupiditate optio perferendis possimus, dolor quae architecto neque officia tenetur eius rem repellat perspiciatis adipisci porro temporibus necessitatibus molestiae suscipit iure sit dicta? Voluptates voluptas sapiente excepturi';

                $data['services'] = ['Home delivery', 'Takeaway available', 'Cash payment'];

                $data['ratings'] = [
                    'overall_average' => $overall_average,
                    'total' => $ratings_count,
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
                $shopdetails = json_decode(json_encode($data), true);
            
                return $shopdetails;
            }
        }
    
    }
    /**/
    public function createShopChannel(Request $request)
    {
        // TODO: Authorize whether this user can initiate chat
        //      with this bidder

        // TODO: if a chat already initiate; redirect to chat screen

        $user = $this->user();

        $participants = (array) $request->get('users');
        $shopName = $request->get('shop_name');
        array_push($participants, $user->id);
        // try to locate the existing channel
        if( $channel = $this->checkForExistingChannel($participants) ) {
            return redirect()->route('chat.shopchannel.view', ['channel' => $channel->id]);
        }

        DB::beginTransaction();

        try {

            $channel = $user->chat_channels()
                ->save(new ChatChannel());

            $channel->participants()
                ->attach($participants);

            DB::commit();

            // redirect to chat screen
            return redirect()->route('chat.shopchannel.view', ['channel' => $channel->id])
                ->withSuccess(__('Chat initiated successfully'));

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        DB::rollBack();

        return back()->withError(__('Something went wrong!'));
    }
    public function viewShopChannel(ChatChannel $channel)
    {
        // dd($shopname);
        $user = $this->user();
        //Update Chat message status unread to  read
        ChatMessage::where('chat_channel_id',$channel->id)->where('user_id','!=',$user->id)->where('status',0)->update(['status'=>'1']);
        // TODO: authorize whether this user can see this channel
        // $shopname=$shopname;
        $user = $this->user();
        $messages = $channel->messages()
            ->with(['user'])
            ->latest()
            ->get()
            ->reverse();

        $participants = $channel->participants
            ->filter(function($participant) use($user) {
                return $participant->id !== $user->id;
            });

        return view('customer.inner.chat.viewshopchat', compact('channel', 'messages', 'participants','user'));
    }
    public function viewChatChannel(ChatChannel $channel)
    {
        $user = $this->user();
        //Update Chat message status unread to  read
        ChatMessage::where('chat_channel_id',$channel->id)->where('user_id','!=',$user->id)->where('status',0)->update(['status'=>'1']);

        // TODO: authorize whether this user can see this channel

        $user = $this->user();
        // $id=$chatOrderId;
        $messages = $channel->messages()
            ->with(['user'])
            ->latest()
            ->get()
            ->reverse();

        $participants = $channel->participants
            ->filter(function($participant) use($user) {
                return $participant->id !== $user->id;
            });

        $orderdetails = [];
        $chatOrderId=0;
            


        return view('customer.inner.chat.view', compact('channel', 'messages', 'participants','user','chatOrderId','orderdetails'));
    }
    public function chatOrderInfo(Request $request)
    {
        // dd($request->chatorderid);
        $id = $request->chatorderid;
                    /*for get the order details in chat heder*/
            $quotation = Order::where('order_no',$id)
            ->firstOrFail();

         $items = $quotation->items()
            ->with(['biddings.customer', 'biddings.seller', 'seller_product.shop'])
            ->get();

        $quotation->setRelation('items', $items);
        $items = CartItemResource::collection($items)
            ->map(function(CartItemResource $cartItemResource) {
                $item = $cartItemResource->resource;

                $cartItemResource->additional([
                    'biddings' => CartItemBiddingResource::collection($item->biddings),
                    'sellerid' =>isset($item->seller_product->seller_id) ? $item->seller_product->seller_id : '',
                ]);

                return $cartItemResource;
            });
          
        $quotation = new QuotationResource($quotation);
        
        $quotation->additional(['items' => $items]);
   
         $orderdetails =json_decode(json_encode($quotation),true);
         
         return $orderdetails;
            /**/
    }

    public function fetchMessages()
    {
        // return Message::with('user')->get();
    }

    public function sendMessage(Request $request, ChatChannel $channel)
    {
        // TODO:: authorize whether this user can send message at this channel
        try {
            $user = $this->user();
            $type = $request->input('type');
            $message = $user->chat_messages()->create([
                'message' => $request->input('message'),
                'type' => (!empty($type))?$type:0,
                'chat_channel_id' => $channel->id
            ]);

            // emit this message to this channel's subscribers
            broadcast(
                new MessageSent($user, $channel, $message)
            )->toOthers();

            return back();
        } catch (\Exception $e) {
            logger($e->getMessage());
        }
        return back()->withError(__('Something went wrong!'));
    }

    private function checkForExistingChannel(array $participants)
    {
       $channels =DB::table('chat_participants')->where('user_id',$participants[0])->get();

        if(isset($channels)){
            foreach($channels as $channel){

                 $children = DB::table('chat_participants')->where('user_id',$participants[1])->where('chat_channel_id',$channel->chat_channel_id)->select('chat_channel_id as id')->first();
                if($children){
                    return $children;
                }
         }
        }
        
        return false;
        
      
    }
    public function staticMessage($channel,$msg,$type){
        try {
            $user = $this->user();
            $message = $user->chat_messages()->create([
                'message' => $msg,
                'type' => $type,
                'chat_channel_id' => $channel->id
            ]);

            // emit this message to this channel's subscribers
            broadcast(
                new MessageSent($user, $channel, $message)
            )->toOthers();

            return 1;
        } catch (\Exception $e) {
            logger($e->getMessage());
        }
        return back()->withError(__('Something went wrong!'));
   }

}
