<?php

namespace App\Models;

use Database\Factories\PermissionSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description'])]
class PermissionSet extends Model
{
    /** @use HasFactory<PermissionSetFactory> */
    use HasFactory;

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_set_permissions');
    }

    public function userPermissionSets(): HasMany
    {
        return $this->hasMany(UserPermissionSet::class);
    }

    public function isAssigned(): bool
    {
        return $this->userPermissionSets()->exists();
    }
}
