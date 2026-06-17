<?php

namespace App\Jobs;

use App\Enum\DocumentStatus;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Contracts\ClientContract as OpenAiClient;
use Smalot\PdfParser\Parser;

class ProcessDocumentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Document $document) {}

    public function handle(OpenAiClient $client): void
    {
        $text = $this->extractText();

        if (empty(trim($text))) {
            $this->document->update(['status' => DocumentStatus::Failed]);

            return;
        }

        $chunks = $this->splitIntoChunks($text);

        foreach ($chunks as $index => $chunk) {
            $response = $client->embeddings()->create([
                'model' => 'text-embedding-3-small',
                'input' => $chunk,
            ]);

            $vector = '['.implode(',', $response->embeddings[0]->embedding).']';

            DB::statement(
                'INSERT INTO document_chunks (document_id, content, embedding, chunk_index, created_at, updated_at) VALUES (?, ?, ?, ?, now(), now())',
                [$this->document->id, $chunk, $vector, $index]
            );
        }

        $this->document->update(['status' => DocumentStatus::Ready]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessDocumentJob failed', [
            'document_id' => $this->document->id,
            'error' => $exception->getMessage(),
        ]);

        $this->document->update(['status' => DocumentStatus::Failed]);
    }

    private function extractText(): string
    {
        $path = Storage::disk('local')->path($this->document->disk_path);

        if ($this->document->mime_type === 'application/pdf') {
            $parser = new Parser;
            $pdf = $parser->parseFile($path);

            return $pdf->getText();
        }

        return Storage::disk('local')->get($this->document->disk_path) ?? '';
    }

    /** @return string[] */
    private function splitIntoChunks(string $text): array
    {
        $chunkSize = 2000;
        $overlap = 400;
        $chunks = [];
        $offset = 0;
        $length = strlen($text);

        while ($offset < $length) {
            $chunk = trim(substr($text, $offset, $chunkSize));
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
            $offset += $chunkSize - $overlap;
        }

        return $chunks;
    }
}
