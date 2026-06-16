<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'email',
    'token',
    'invited_by',
    'role',
    'accepted_at',
])]
class Invitation extends Model
{
    use HasFactory;

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at');
    }

    public function scopeExpired(Builder $query): void
    {
        $query->whereNull('accepted_at')
            ->where('created_at', '<', now()->subDays(7));
    }

    public function isExpired(): bool
    {
        return $this->accepted_at === null
            && $this->created_at->lt(now()->subDays(7));
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}
