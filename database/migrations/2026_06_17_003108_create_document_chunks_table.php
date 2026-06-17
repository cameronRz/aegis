<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->unsignedInteger('chunk_index');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(1536)');
        DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
