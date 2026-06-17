<?php

use App\Enum\DocumentStatus;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenAI\Contracts\ClientContract;
use OpenAI\Responses\Embeddings\CreateResponse;
use OpenAI\Testing\ClientFake;

beforeEach(function () {
    $this->withoutVite();
});

test('job extracts text from txt file, stores chunks, and sets status to ready', function () {
    Queue::fake();
    Storage::fake('local');

    $content = str_repeat('This is test content for the document. ', 60);
    $path = 'documents/test.txt';
    Storage::disk('local')->put($path, $content);

    $document = Document::factory()->create([
        'disk_path' => $path,
        'mime_type' => 'text/plain',
    ]);

    $fakeClient = new ClientFake([
        CreateResponse::fake([
            'data' => [
                ['embedding' => array_fill(0, 1536, 0.1), 'index' => 0, 'object' => 'embedding'],
            ],
        ]),
        CreateResponse::fake([
            'data' => [
                ['embedding' => array_fill(0, 1536, 0.2), 'index' => 0, 'object' => 'embedding'],
            ],
        ]),
    ]);

    $this->app->instance(ClientContract::class, $fakeClient);

    app()->call([new ProcessDocumentJob($document), 'handle']);

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Ready);
    expect(DocumentChunk::where('document_id', $document->id)->count())->toBeGreaterThan(0);
});

test('job sets status to failed when failed() hook is called', function () {
    $document = Document::factory()->create();

    $job = new ProcessDocumentJob($document);
    $job->failed(new RuntimeException('OpenAI error'));

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Failed);
});

test('job sets status to failed when document text is empty', function () {
    Queue::fake();
    Storage::fake('local');

    $path = 'documents/empty.txt';
    Storage::disk('local')->put($path, '   ');

    $document = Document::factory()->create([
        'disk_path' => $path,
        'mime_type' => 'text/plain',
    ]);

    $fakeClient = new ClientFake([]);
    $this->app->instance(ClientContract::class, $fakeClient);

    app()->call([new ProcessDocumentJob($document), 'handle']);

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Failed);
});

test('DocumentObserver dispatches ProcessDocumentJob on document creation', function () {
    Queue::fake();

    $document = Document::factory()->create();

    Queue::assertPushed(ProcessDocumentJob::class, function ($job) use ($document) {
        return $job->document->id === $document->id;
    });
});
