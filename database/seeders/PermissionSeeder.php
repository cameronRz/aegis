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

            // Categories
            [
                'name' => 'view_categories',
                'display_name' => 'View Categories',
                'description' => 'Access the categories list.',
            ],
            [
                'name' => 'create_category',
                'display_name' => 'Create Categories',
                'description' => 'Create a new categories.',
            ],
            [
                'name' => 'edit_category',
                'display_name' => 'Edit Categories',
                'description' => 'Edit categories.',
            ],
            [
                'name' => 'delete_category',
                'display_name' => 'Delete Categories',
                'description' => 'Delete categories.',
            ],

            // Products
            [
                'name' => 'view_products',
                'display_name' => 'View Products',
                'description' => 'Access the products list.',
            ],
            [
                'name' => 'create_product',
                'display_name' => 'Create Product',
                'description' => 'Create a new product.',
            ],
            [
                'name' => 'edit_product',
                'display_name' => 'Edit Product',
                'description' => 'Edit products.',
            ],
            [
                'name' => 'delete_product',
                'display_name' => 'Delete Product',
                'description' => 'Delete products.',
            ],

            // AI
            [
                'name' => 'use_ai_assistant',
                'display_name' => 'Use AI Assistant',
                'description' => 'Access the AI assistant chat.',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }
    }
}
