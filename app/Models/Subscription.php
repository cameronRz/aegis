<?php

namespace App\Models;

use App\Enum\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'order_id',
    'product_id',
    'product_name',
    'stripe_subscription_id',
    'stripe_price_id',
    'status',
    'quantity',
    'trial_ends_at',
    'current_period_start',
    'current_period_end',
    'cancel_at_period_end',
    'canceled_at',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @param Builder<Subscription> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [
            SubscriptionStatus::Active->value,
            SubscriptionStatus::Trialing->value,
            SubscriptionStatus::PastDue->value,
        ]);
    }
}
