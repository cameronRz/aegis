<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'content',
    'embedding',
    'chunk_index',
])]
class DocumentChunk extends Model
{
    use HasFactory;

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
