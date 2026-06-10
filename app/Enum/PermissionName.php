<?php

namespace App\Enum;

enum PermissionName: string
{
    case ViewUsers = 'view_users';
    case CreateUser = 'create_user';
    case EditUser = 'edit_user';
    case DeleteUser = 'delete_user';

    // Categories
    case ViewCategories = 'view_categories';
    case CreateCategory = 'create_category';
    case EditCategory = 'edit_category';
    case DeleteCategory = 'delete_category';

    // Products
    case ViewProducts = 'view_products';
    case CreateProduct = 'create_product';
    case EditProduct = 'edit_product';
    case DeleteProduct = 'delete_product';
}
