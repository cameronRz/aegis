<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserPermissionController extends Controller
{
    public function toggle(Request $request, User $user, Permission $permission): RedirectResponse
    {
        if ($user->permissions()->where('permission_id', $permission->id)->exists()) {
            $user->permissions()->detach($permission->id);
        } else {
            $user->permissions()->attach($permission->id, ['granted_by' => $request->user()->id]);
        }

        return back();
    }
}
