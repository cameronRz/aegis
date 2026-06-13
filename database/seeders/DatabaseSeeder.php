<?php

namespace Database\Seeders;

use App\Enum\Role;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Database\Seeder;
use Stripe\Exception\ApiErrorException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $stripe = app(StripeService::class);

        // Site admin
        $siteAdmin = User::create([
            'first_name' => config('admin.site_admin.first_name'),
            'last_name' => config('admin.site_admin.last_name'),
            'email' => config('admin.site_admin.email'),
            'password' => bcrypt(config('admin.site_admin.password')),
        ]);
        $siteAdmin->role = Role::SiteAdmin;
        $siteAdmin->save();
        $this->createStripeCustomer($stripe, $siteAdmin);

        // Test users
        $admin = User::factory()->create([
            'first_name' => 'Dora',
            'last_name' => 'Mena',
            'email' => 'dora@email.com',
        ]);
        $admin->role = Role::Admin;
        $admin->save();
        $this->createStripeCustomer($stripe, $admin);

        $benny = User::factory()->create([
            'first_name' => 'Benny',
            'last_name' => 'Bull',
            'email' => 'benny@email.com',
        ]);
        $this->createStripeCustomer($stripe, $benny);

        User::factory(5)->create(['role' => Role::Admin]);
        User::factory(120)->create();

        // Permissions
        $this->call(PermissionSeeder::class);

        // Products & categories
        $this->call(ProductSeeder::class);
    }

    private function createStripeCustomer(StripeService $stripe, User $user): void
    {
        try {
            $customer = $stripe->createCustomer($user);
            $user->stripe_customer_id = $customer->id;
            $user->saveQuietly();
        } catch (ApiErrorException $e) {
            $this->command->warn("Could not create Stripe customer for {$user->email}: {$e->getMessage()}");
        }
    }
}
