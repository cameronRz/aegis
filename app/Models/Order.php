<?php

namespace App\Models;

use App\Enum\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'status',
    'subtotal',
    'total',
    'stripe_checkout_session_id',
    'stripe_payment_intent_id',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            $order->order_number = 'ORD-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
            $order->saveQuietly();
        });
    }

    /** @param Builder<Order> $query */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
