<?php

namespace App\Models;

use App\Concerns\Sortable;
use App\Enum\BillingInterval;
use App\Enum\PriceType;
use App\Enum\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id',
    'name',
    'type',
    'sku',
    'is_active',
    'description',
    'price',
    'price_type',
    'billing_interval',
    'billing_interval_count',
    'trial_period_days',
    'stock_quantity',
    'track_inventory',
    'sort_order',
    'image',
    'stripe_product_id',
    'stripe_price_id',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes, Sortable;

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'price_type' => PriceType::class,
            'billing_interval' => BillingInterval::class,
            'is_active' => 'boolean',
            'track_inventory' => 'boolean',
        ];
    }

    protected function sortableScope(): array
    {
        return ['category_id'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $search): void
    {
        $query->when($search, function (Builder $q, string $term): void {
            $q->where(function (Builder $inner) use ($term): void {
                $inner->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%");
            });
        });
    }
}
