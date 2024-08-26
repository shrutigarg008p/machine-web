<?php

namespace App\Http\Controllers\Admin;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\ProductBidding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user() ?? null;

        $channels = ChatChannel::where('user_id', $user->id)
            ->orWhereHas('participants', function($query) use($user) {
                $query->where('chat_participants.user_id', $user->id);
            })
            ->with(['user', 'participants'])
            ->get();
        // dd($channels);

        foreach($channels as $value){
            $unread_msg =  ChatMessage::where('chat_channel_id',$value->id)->where('status',0)->count();
            $last_msg =  ChatMessage::where('chat_channel_id',$value->id)->select('message')->latest()->first();
            $value->unread_msg = $unread_msg;
            $value->last_msg = $last_msg->message ?? null;
          }

        return view('admin.chat.index', compact('channels'));
    }
    
    // initiate a chat
    public function createChannel(Request $request)
    {
        // TODO: Authorize whether this user can initiate chat
        //      with this bidder

        // TODO: if a chat already initiate; redirect to chat screen

        $user = $this->user();

        $participants = (array)$request->get('users');

        array_push($participants, $user->id);

        // try to locate the existing channel
        if( $channel = $this->checkForExistingChannel($participants) ) {
            return redirect()->route('admin.chat.channel.view', ['channel' => $channel->id]);
        }

        DB::beginTransaction();

        try {

            $channel = $user->chat_channels()
                ->save(new ChatChannel());

            $channel->participants()
                ->attach($participants);

            DB::commit();

            // redirect to chat screen
            return redirect()->route('admin.chat.channel.view', ['channel' => $channel->id])
                ->withSuccess(__('Chat initiated successfully'));

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        DB::rollBack();

        return back()->withError(__('Something went wrong!'));
    }
    
    public function viewChannel(ChatChannel $channel)
    {
        //Update Chat message status unread to  read
        ChatMessage::where('chat_channel_id',$channel->id)->where('status',0)->update(['status'=>'1']);

        // TODO: authorize whether this user can see this channel

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

        return view('admin.chat.view', compact('channel', 'messages', 'participants','user'));
    }

    public function fetchMessages()
    {
        // return Message::with('user')->get();
    }

    public function sendMessage(Request $request, ChatChannel $channel)
    {
        // TODO:: authorize whether this user can send message at this channel

        $user = $this->user();

        $message = $user->chat_messages()->create([
            'message' => $request->input('message'),
            'chat_channel_id' => $channel->id
        ]);

        // emit this message to this channel's subscribers
        broadcast(
            new MessageSent($user, $channel, $message)
        )->toOthers();

        return redirect()->back()->with('status', 'Message Sent!');
    }

    private function checkForExistingChannel(array $participants)
    {
        $channel = DB::table('chat_participants')
            ->select('chat_channel_id as id')
            ->groupBy('chat_channel_id')
            ->havingRaw('SUM(user_id) = ' . array_sum($participants))
            ->first();

        return $channel;
    }


}
