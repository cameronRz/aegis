<?php

use App\Enum\BillingInterval;
use App\Enum\PriceType;
use App\Enum\ProductType;
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
            $table->enum('type', array_column(ProductType::cases(), 'value'));
            $table->string('sku')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('description');
            $table->unsignedInteger('price');
            $table->enum('price_type', array_column(PriceType::cases(), 'value'));
            $table->enum('billing_interval', array_column(BillingInterval::cases(), 'value'))->nullable();
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
