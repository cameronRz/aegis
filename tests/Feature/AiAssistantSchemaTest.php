<?php

use App\Enum\DocumentStatus;
use App\Enum\MessageRole;
use App\Enum\PermissionName;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->withoutVite();
});

test('use_ai_assistant permission exists in the database after seeding', function () {
    $this->seed(PermissionSeeder::class);

    expect(Permission::where('name', PermissionName::UseAiAssistant->value)->exists())->toBeTrue();
});

test('Document can be created with DocumentStatus cast', function () {
    $document = Document::factory()->create();

    expect($document->status)->toBeInstanceOf(DocumentStatus::class)
        ->and($document->status)->toBe(DocumentStatus::Processing);
});

test('Document factory ready state sets status to ready', function () {
    $document = Document::factory()->ready()->create();

    expect($document->status)->toBe(DocumentStatus::Ready);
});

test('DocumentChunk belongs to a Document', function () {
    $chunk = DocumentChunk::factory()->create();

    expect($chunk->document)->toBeInstanceOf(Document::class);
});

test('Document cascades delete to its chunks', function () {
    $document = Document::factory()->create();
    DocumentChunk::factory()->count(2)->for($document)->create();

    $document->delete();

    expect(DocumentChunk::where('document_id', $document->id)->count())->toBe(0);
});

test('AiConversation can be created and belongs to a user', function () {
    $conversation = AiConversation::factory()->create();

    expect($conversation->user)->toBeInstanceOf(User::class);
});

test('AiMessage can be created with MessageRole cast', function () {
    $message = AiMessage::factory()->create();

    expect($message->role)->toBeInstanceOf(MessageRole::class)
        ->and($message->role)->toBe(MessageRole::User);
});

test('AiMessage factory assistant state sets role to assistant', function () {
    $message = AiMessage::factory()->assistant()->create();

    expect($message->role)->toBe(MessageRole::Assistant);
});

test('AiConversation cascades delete to its messages', function () {
    $conversation = AiConversation::factory()->create();
    AiMessage::factory()->count(2)->for($conversation, 'conversation')->create();

    $conversation->delete();

    expect(AiMessage::where('conversation_id', $conversation->id)->count())->toBe(0);
});

test('deleting a user cascades to their documents and conversations', function () {
    $user = User::factory()->create();
    Document::factory()->for($user)->create();
    AiConversation::factory()->for($user)->create();

    $user->forceDelete();

    expect(Document::where('user_id', $user->id)->count())->toBe(0)
        ->and(AiConversation::where('user_id', $user->id)->count())->toBe(0);
});
