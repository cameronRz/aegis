<?php

namespace App\Http\Controllers;

use App\Enum\DocumentStatus;
use App\Enum\MessageRole;
use App\Enum\SettingKey;
use App\Http\Requests\StoreAiMessageRequest;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AppSetting;
use App\Models\DocumentChunk;
use OpenAI\Contracts\ClientContract;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiMessageController extends Controller
{
    public function store(StoreAiMessageRequest $request): StreamedResponse
    {
        abort_unless(AppSetting::get(SettingKey::AiAssistantEnabled, true), 503);
        $this->authorize('use_ai_assistant');

        $conversation = AiConversation::findOrFail($request->input('conversation_id'));

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::User,
            'content' => $request->input('content'),
        ]);

        $client = app(ClientContract::class);

        $embeddingResponse = $client->embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $request->input('content'),
        ]);
        $vector = '['.implode(',', $embeddingResponse->embeddings[0]->embedding).']';

        $chunks = DocumentChunk::whereHas('document', fn ($q) => $q->where('status', DocumentStatus::Ready))
            ->orderByRaw('embedding <=> ?::vector', [$vector])
            ->with('document:id,title')
            ->limit(5)
            ->get();

        $contextParts = $chunks->map(
            fn ($chunk) => "Document: \"{$chunk->document?->title}\"\n{$chunk->content}"
        );

        $systemPrompt = "You are a helpful AI assistant. Answer questions using only the context documents provided below. If the answer is not found in the documents, say so clearly and honestly.\n\nContext:\n\n"
            .$contextParts->join("\n\n---\n\n");

        $sources = $chunks->map(fn ($chunk) => [
            'document_title' => $chunk->document?->title ?? 'Unknown',
            'chunk_index' => $chunk->chunk_index,
        ])->values()->toArray();

        $userContent = $request->input('content');
        $conversationId = $conversation->id;

        return response()->stream(function () use ($client, $systemPrompt, $userContent, $conversationId, $sources) {
            echo 'data: '.json_encode(['type' => 'sources', 'sources' => $sources])."\n\n";
            ob_flush();
            flush();

            $stream = $client->chat()->createStreamed([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

            $fullContent = '';
            foreach ($stream as $response) {
                $delta = $response->choices[0]->delta->content ?? '';
                if ($delta !== '') {
                    $fullContent .= $delta;
                    echo 'data: '.json_encode(['type' => 'delta', 'content' => $delta])."\n\n";
                    ob_flush();
                    flush();
                }
            }

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();

            AiMessage::create([
                'conversation_id' => $conversationId,
                'role' => MessageRole::Assistant,
                'content' => $fullContent ?: '(No response)',
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
