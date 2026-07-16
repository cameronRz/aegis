<?php

namespace App\Models;

use App\Enum\PermissionName;
use App\Enum\Tier;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['first_name', 'last_name', 'email', 'password', 'stripe_customer_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'stripe_customer_id'])]
#[Appends(['full_name'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
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
            'tier' => Tier::class,
        ];
    }

    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        $query->when($viewer->tier !== Tier::SiteAdmin, fn ($q) => $q->where('tier', '!=', Tier::SiteAdmin->value));
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

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return in_array($this->tier, [Tier::SiteAdmin, Tier::Admin], true);
    }

    /**
     * Returns the Tier cases this user is allowed to assign to other users.
     * Site admins can assign any tier; everyone else is restricted to user and below.
     *
     * @return Tier[]
     */
    public function assignableTiers(): array
    {
        return $this->tier === Tier::SiteAdmin
            ? Tier::cases()
            : [Tier::User];
    }

    public function hasPermission(PermissionName $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->loadMissing('roles.permissions')
            ->roles
            ->flatMap->permissions
            ->pluck('name')
            ->contains($permission->value);
    }

    public function canAssignRole(Role $role): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $role->loadMissing('permissions');

        return $role->permissions->every(function (Permission $permission): bool {
            $permissionName = PermissionName::tryFrom($permission->name);

            return $permissionName !== null && $this->hasPermission($permissionName);
        });
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => "$this->first_name $this->last_name",
        );
    }
}
