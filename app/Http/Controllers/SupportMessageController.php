<?php

namespace App\Http\Controllers;

use App\Enum\ConversationStatus;
use App\Enum\SettingKey;
use App\Events\NewSupportMessage;
use App\Models\AppSetting;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupportMessageController extends Controller
{
    public function store(Request $request, SupportConversation $conversation): RedirectResponse
    {
        abort_unless(AppSetting::get(SettingKey::SupportChatEnabled, true), 503);

        $canUse = $request->user()->can('use_support');
        $canHandle = $request->user()->can('handle_support');
        abort_unless($canUse || $canHandle, 403);

        abort_if($conversation->status === ConversationStatus::Closed, 422, 'This conversation is closed.');

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'content' => $validated['content'],
        ]);

        $message->load('sender:id,first_name,last_name,full_name');

        $conversation->last_message_at = now();

        if ($canHandle && $conversation->agent_id === null && $request->user()->id !== $conversation->user_id) {
            $conversation->agent_id = $request->user()->id;
        }

        $conversation->save();

        broadcast(new NewSupportMessage($message))->toOthers();

        return back();
    }
}
