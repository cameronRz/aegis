<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_set_permissions', function (Blueprint $table) {
            $table->foreignId('permission_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();

            $table->primary(['permission_set_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_set_permissions');
    }
};
