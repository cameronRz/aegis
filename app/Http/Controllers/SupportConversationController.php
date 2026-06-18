<?php

namespace App\Http\Controllers;

use App\Enum\ConversationStatus;
use App\Enum\SettingKey;
use App\Models\AppSetting;
use App\Models\SupportConversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('use_support');
        abort_unless(AppSetting::get(SettingKey::SupportChatEnabled, true), 503);

        $conversation = SupportConversation::forUser($request->user())
            ->with(['messages.sender:id,first_name,last_name'])
            ->latest('last_message_at')
            ->first();

        return Inertia::render('support/index', [
            'conversation' => $conversation,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('use_support');
        abort_unless(AppSetting::get(SettingKey::SupportChatEnabled, true), 503);

        $existing = SupportConversation::forUser($request->user())->open()->first();

        if ($existing) {
            return redirect()->route('support.conversations.show', $existing);
        }

        $conversation = SupportConversation::create([
            'user_id' => $request->user()->id,
            'status' => ConversationStatus::Open,
        ]);

        return redirect()->route('support.conversations.show', $conversation);
    }

    public function show(Request $request, SupportConversation $conversation): Response
    {
        $isOwner = $request->user()->id === $conversation->user_id;
        $isAgent = $request->user()->can('handle_support');

        abort_unless($isOwner || $isAgent, 403);

        if ($isOwner && ! $isAgent) {
            abort_unless(AppSetting::get(SettingKey::SupportChatEnabled, true), 503);
        }

        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $request->user()->id)
            ->update(['read_at' => now()]);

        $conversation->load(['messages.sender:id,first_name,last_name']);

        return Inertia::render('support/index', [
            'conversation' => $conversation,
        ]);
    }
}
