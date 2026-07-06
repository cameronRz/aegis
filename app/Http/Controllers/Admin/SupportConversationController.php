<?php

namespace App\Http\Controllers\Admin;

use App\Enum\ConversationStatus;
use App\Events\ConversationClosed;
use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $conversations = SupportConversation::with('client:id,first_name,last_name')
            ->withCount(['messages as unread_count' => function ($query) use ($request) {
                $query->whereNull('read_at')
                    ->where('sender_id', '!=', $request->user()->id);
            }])
            ->orderByDesc('last_message_at')
            ->paginate(15);

        return Inertia::render('admin/support/index', [
            'conversations' => $conversations,
        ]);
    }

    public function show(Request $request, SupportConversation $conversation): Response
    {
        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $request->user()->id)
            ->update(['read_at' => now()]);

        $conversation->load([
            'messages.sender:id,first_name,last_name',
            'client:id,first_name,last_name',
            'agent:id,first_name,last_name',
        ]);

        return Inertia::render('admin/support/show', [
            'conversation' => $conversation,
        ]);
    }

    public function close(SupportConversation $conversation): RedirectResponse
    {
        $conversation->update(['status' => ConversationStatus::Closed]);

        broadcast(new ConversationClosed($conversation));

        return redirect()->back();
    }
}
