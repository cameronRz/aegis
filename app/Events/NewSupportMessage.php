<?php

namespace App\Events;

use App\Enum\PermissionName;
use App\Enum\Tier;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewSupportMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly SupportMessage $message) {}

    public function broadcastOn(): array
    {
        $conversation = $this->message->conversation;
        $senderIsClient = $conversation->user_id === $this->message->sender_id;

        $channels = [new PrivateChannel("conversation.{$conversation->id}")];

        if ($senderIsClient) {
            $handlerIds = User::where(function ($query) {
                $query->whereIn('tier', [Tier::Admin->value, Tier::SiteAdmin->value])
                    ->orWhereHas('roles.permissions', fn ($q) => $q->where('name', PermissionName::HandleSupport->value));
            })->pluck('id');

            foreach ($handlerIds as $id) {
                $channels[] = new PrivateChannel("App.Models.User.{$id}");
            }
        } else {
            $channels[] = new PrivateChannel("App.Models.User.{$conversation->user_id}");
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'content' => $this->message->content,
            'read_at' => $this->message->read_at,
            'created_at' => $this->message->created_at,
            'sender' => $this->message->sender->only('id', 'first_name', 'last_name', 'full_name'),
        ];
    }
}
