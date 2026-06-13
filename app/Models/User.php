<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enum\PermissionName;
use App\Enum\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['first_name', 'last_name', 'email', 'password', 'stripe_customer_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[Appends(['full_name'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            $user->passkeys()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => Role::class,
        ];
    }

    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        $query->when($viewer->role !== Role::SiteAdmin, fn ($q) => $q->where('role', '!=', Role::SiteAdmin->value));
    }

    public function scopeSearch(Builder $query, ?string $search): void
    {
        $query->when($search, function ($q, string $term) {
            $q->where(function ($inner) use ($term) {
                $inner->where('first_name', 'ilike', "%{$term}%")
                    ->orWhere('last_name', 'ilike', "%{$term}%")
                    ->orWhere('email', 'ilike', "%{$term}%");
            });
        });
    }

    public function userPermissionSet(): HasOne
    {
        return $this->hasOne(UserPermissionSet::class);
    }

    public function permissionSet(): HasOneThrough
    {
        return $this->hasOneThrough(
            PermissionSet::class,
            UserPermissionSet::class,
            'user_id',
            'id',
            'id',
            'permission_set_id'
        );
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [Role::SiteAdmin, Role::Admin], true);
    }

    /**
     * Returns the Role cases this user is allowed to assign to other users.
     * Site admins can assign any role; everyone else is restricted to manager and below.
     *
     * @return Role[]
     */
    public function assignableRoles(): array
    {
        return $this->role === Role::SiteAdmin
            ? Role::cases()
            : [Role::User];
    }

    public function hasPermission(PermissionName $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $set = $this->loadMissing('permissionSet')->permissionSet;

        return $set !== null
            && $set->loadMissing('permissions')->permissions->pluck('name')->contains($permission->value);
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => "$this->first_name $this->last_name",
        );
    }
}
