<?php

namespace App\Providers;

use App\Enum\PermissionName;
use App\Models\Document;
use App\Models\Product;
use App\Models\User;
use App\Observers\DocumentObserver;
use App\Observers\ProductObserver;
use App\Services\StripeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use OpenAI;
use OpenAI\Contracts\ClientContract;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            ClientContract::class,
            fn () => OpenAI::client(config('services.openai.key') ?? '')
        );

        $this->app->singleton(StripeService::class, function () {
            $client = new StripeClient([
                'api_key' => config('services.stripe.secret'),
            ]);

            return new StripeService($client);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Product::observe(ProductObserver::class);
        Document::observe(DocumentObserver::class);

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        foreach (PermissionName::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }

        // Derived routing guard — not a DB permission. Grants route access to anyone with either support role.
        Gate::define('support_participant', fn (User $user): bool => $user->hasPermission(PermissionName::UseSupport) || $user->hasPermission(PermissionName::HandleSupport)
        );
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
