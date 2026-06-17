<?php

use App\Enum\DocumentStatus;
use App\Enum\Tier;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->create(['tier' => Tier::Admin]);
});

test('admin can view documents index', function () {
    actingAs($this->admin)
        ->get(route('admin.documents'))
        ->assertOk();
});

test('non-admin cannot view documents index', function () {
    $user = User::factory()->create(['tier' => Tier::User]);

    actingAs($user)
        ->get(route('admin.documents'))
        ->assertForbidden();
});

test('admin can upload a document and job is dispatched', function () {
    Queue::fake();
    Storage::fake('local');

    $file = UploadedFile::fake()->create('manual.txt', 50, 'text/plain');

    actingAs($this->admin)
        ->post(route('admin.documents.store'), [
            'title' => 'Product Manual',
            'file' => $file,
        ])
        ->assertRedirect();

    $document = Document::first();
    expect($document)->not->toBeNull()
        ->and($document->title)->toBe('Product Manual')
        ->and($document->status)->toBe(DocumentStatus::Processing)
        ->and($document->user_id)->toBe($this->admin->id);

    Storage::disk('local')->assertExists($document->disk_path);
    Queue::assertPushed(ProcessDocumentJob::class);
});

test('upload requires a title and valid file', function () {
    actingAs($this->admin)
        ->post(route('admin.documents.store'), [])
        ->assertSessionHasErrors(['title', 'file']);
});

test('upload rejects non pdf/txt files', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->image('photo.jpg');

    actingAs($this->admin)
        ->post(route('admin.documents.store'), [
            'title' => 'Image',
            'file' => $file,
        ])
        ->assertSessionHasErrors(['file']);
});

test('admin can delete a document and file is removed', function () {
    Storage::fake('local');
    Queue::fake();

    $path = UploadedFile::fake()->create('guide.txt', 10, 'text/plain')->store('documents', 'local');

    $document = Document::factory()->ready()->create([
        'user_id' => $this->admin->id,
        'disk_path' => $path,
    ]);

    actingAs($this->admin)
        ->delete(route('admin.documents.destroy', $document))
        ->assertRedirect();

    expect(Document::find($document->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

test('non-admin cannot delete a document', function () {
    $user = User::factory()->create(['tier' => Tier::User]);
    $document = Document::factory()->ready()->create();

    actingAs($user)
        ->delete(route('admin.documents.destroy', $document))
        ->assertForbidden();
});
