<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('sku')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('description');
            $table->unsignedInteger('price');
            $table->string('price_type');
            $table->string('billing_interval')->nullable();
            $table->unsignedSmallInteger('billing_interval_count')->nullable();
            $table->unsignedSmallInteger('trial_period_days')->nullable();
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->boolean('track_inventory')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('image')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
