<?php

namespace Database\Seeders;

use App\Enum\PermissionName;
use App\Enum\Tier;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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
        $siteAdmin->tier = Tier::SiteAdmin;
        $siteAdmin->save();
        $this->createStripeCustomer($stripe, $siteAdmin);

        // Test users
        $admin = User::factory()->create([
            'first_name' => 'Dora',
            'last_name' => 'Mena',
            'email' => 'dora@email.com',
        ]);
        $admin->tier = Tier::Admin;
        $admin->save();
        $this->createStripeCustomer($stripe, $admin);

        $benny = User::factory()->create([
            'first_name' => 'Benny',
            'last_name' => 'Bull',
            'email' => 'benny@email.com',
        ]);
        $this->createStripeCustomer($stripe, $benny);

        User::factory(5)->create(['tier' => Tier::Admin]);
        User::factory(120)->create();

        // Permissions
        $this->call(PermissionSeeder::class);

        // Create a default Client role with AI access and assign to all Tier::User users
        $aiPermission = Permission::where('name', PermissionName::UseAiAssistant->value)->first();
        $clientRole = Role::create(['name' => 'Client', 'description' => 'Default role for client users. Grants AI Assistant access.']);
        $clientRole->permissions()->attach($aiPermission->id);

        $userIds = User::where('tier', Tier::User->value)->pluck('id');
        $now = now();
        DB::table('role_user')->insert(
            $userIds->map(fn ($id) => [
                'user_id' => $id,
                'role_id' => $clientRole->id,
                'assigned_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );

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
