<?php

namespace App\Models;

use Database\Factories\UserPermissionSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'permission_set_id', 'assigned_by'])]
class UserPermissionSet extends Model
{
    /** @use HasFactory<UserPermissionSetFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permissionSet(): BelongsTo
    {
        return $this->belongsTo(PermissionSet::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
