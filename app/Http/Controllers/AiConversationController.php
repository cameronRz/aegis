<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('use_ai_assistant');

        $conversation = AiConversation::where('user_id', $request->user()->id)
            ->latest()
            ->first()
            ?? AiConversation::create(['user_id' => $request->user()->id]);

        $messages = $conversation->messages()->orderBy('created_at')->get();

        return Inertia::render('ai/show', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('use_ai_assistant');

        AiConversation::create(['user_id' => $request->user()->id]);

        return redirect()->route('ai.index');
    }
}
