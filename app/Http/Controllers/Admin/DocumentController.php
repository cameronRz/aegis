<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function index(): Response
    {
        $documents = Document::with('user:id,first_name,last_name')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/documents/index', [
            'documents' => $documents,
        ]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store('documents', 'local');

        Document::create([
            'user_id' => $request->user()->id,
            'title' => $request->input('title'),
            'original_filename' => $file->getClientOriginalName(),
            'disk_path' => $path,
            'mime_type' => $file->getMimeType(),
            'status' => 'processing',
        ]);

        return back();
    }

    public function destroy(Document $document): RedirectResponse
    {
        Storage::disk('local')->delete($document->disk_path);
        $document->delete();

        return back();
    }
}
