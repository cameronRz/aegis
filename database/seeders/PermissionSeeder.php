<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'view_users',
                'display_name' => 'View Users',
                'description' => 'Access the users list and view user details.',
            ],
            [
                'name' => 'create_user',
                'display_name' => 'Create Users',
                'description' => 'Create new user accounts (managers and users only).',
            ],
            [
                'name' => 'edit_user',
                'display_name' => 'Edit Users',
                'description' => 'Edit user accounts (managers and users only).',
            ],
            [
                'name' => 'delete_user',
                'display_name' => 'Delete Users',
                'description' => 'Delete user accounts (managers and users only).',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }
    }
}
