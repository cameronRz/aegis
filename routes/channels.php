<?php

use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{id}', function (User $user, int $id) {
    $conversation = SupportConversation::find($id);

    if (! $conversation) {
        return false;
    }

    return $user->id === $conversation->user_id || $user->can('handle_support');
});
