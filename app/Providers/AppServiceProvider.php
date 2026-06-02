<?php

namespace App\Providers;

use App\Enum\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::before(function (User $user, string $ability) {
            if (in_array($user->role, [Role::SiteAdmin, Role::Admin], true)) {
                return true;
            }

            return null;
        });

        Gate::define('admin', fn (User $user) => in_array($user->role, [Role::SiteAdmin, Role::Admin], true));
        Gate::define('view_users', fn (User $user) => $user->hasPermission('view_users'));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
