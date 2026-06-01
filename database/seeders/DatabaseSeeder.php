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
        $admin = User::create([
            'first_name' => config('admin.site_admin.first_name'),
            'last_name' => config('admin.site_admin.last_name'),
            'email' => config('admin.site_admin.email'),
            'password' => bcrypt(config('admin.site_admin.password')),
        ]);

        $admin->role = Role::SiteAdmin->value;
        $admin->save();

        User::factory()->create([
            'first_name' => 'Benny',
            'last_name' => 'Bull',
            'email' => 'benny@email.com',
        ]);

        User::factory(125)->create();
    }
}
