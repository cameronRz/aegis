<?php

namespace Database\Seeders;

use App\Enum\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Site admin
        $siteAdmin = User::create([
            'first_name' => config('admin.site_admin.first_name'),
            'last_name' => config('admin.site_admin.last_name'),
            'email' => config('admin.site_admin.email'),
            'password' => bcrypt(config('admin.site_admin.password')),
        ]);
        $siteAdmin->role = Role::SiteAdmin;
        $siteAdmin->save();

        // Test users
        $admin = User::factory()->create([
            'first_name' => 'Dora',
            'last_name' => 'Mena',
            'email' => 'dora@email.com',
        ]);
        $admin->role = Role::Admin;
        $admin->save();

        $manager = User::factory()->create([
            'first_name' => 'Jack',
            'last_name' => 'Penny',
            'email' => 'jack@email.com',
        ]);
        $manager->role = Role::Manager;
        $manager->save();

        User::factory()->create([
            'first_name' => 'Benny',
            'last_name' => 'Bull',
            'email' => 'benny@email.com',
        ]);

        User::factory(5)->create(['role' => Role::Admin]);
        User::factory(20)->create(['role' => Role::Manager]);
        User::factory(100)->create();

        // Permissions
        $this->call(PermissionSeeder::class);
    }
}
