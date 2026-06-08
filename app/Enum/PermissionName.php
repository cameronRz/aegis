<?php

namespace App\Enum;

enum PermissionName: string
{
    case ViewUsers = 'view_users';
    case CreateUser = 'create_user';
    case EditUser = 'edit_user';
    case DeleteUser = 'delete_user';
}
