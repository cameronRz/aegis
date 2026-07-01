<?php

namespace App\Events;

use App\Models\SupportConversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationClosed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly SupportConversation $conversation) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("conversation.{$this->conversation->id}");
    }

    public function broadcastWith(): array
    {
        return ['conversation_id' => $this->conversation->id];
    }
}
