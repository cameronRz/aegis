<?php

namespace App\Observers;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;

class DocumentObserver
{
    public function created(Document $document): void
    {
        ProcessDocumentJob::dispatch($document);
    }
}
