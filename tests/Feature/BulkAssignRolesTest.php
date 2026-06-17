<?php

use App\Enum\Tier;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->withoutVite();

    Permission::create([
        'name' => 'edit_user',
        'display_name' => 'Edit Users',
        'description' => null,
    ]);

    $this->siteAdmin = User::factory()->create(['tier' => Tier::SiteAdmin]);
    $this->admin = User::factory()->create(['tier' => Tier::Admin]);
    $this->role = Role::factory()->create();
});

it('redirects guests to login', function () {
    $target = User::factory()->create(['tier' => Tier::User]);

    post(route('admin.users.bulk-assign-roles'), [
        'user_ids' => [$target->id],
        'role_ids' => [$this->role->id],
    ])->assertRedirect('/login');
});

it('forbids users without edit_user permission', function () {
    $user = User::factory()->create(['tier' => Tier::User]);
    $target = User::factory()->create(['tier' => Tier::User]);

    actingAs($user)
        ->post(route('admin.users.bulk-assign-roles'), [
            'user_ids' => [$target->id],
            'role_ids' => [$this->role->id],
        ])->assertForbidden();
});

it('assigns a role to multiple users', function () {
    $targets = User::factory()->count(3)->create(['tier' => Tier::User]);

    actingAs($this->admin)
        ->post(route('admin.users.bulk-assign-roles'), [
            'user_ids' => $targets->pluck('id')->toArray(),
            'role_ids' => [$this->role->id],
        ])->assertRedirect();

    foreach ($targets as $target) {
        expect($target->fresh()->roles()->where('role_id', $this->role->id)->exists())->toBeTrue();
    }
});

it('rejects bulk role assignment that grants permissions beyond the actor', function () {
    $staffRole = Role::factory()->create(['name' => 'Staff']);
    $staffRole->permissions()->sync([Permission::where('name', 'edit_user')->firstOrFail()->id]);

    $viewProducts = Permission::create([
        'name' => 'view_products',
        'display_name' => 'View Products',
        'description' => null,
    ]);
    $privilegedRole = Role::factory()->create(['name' => 'Product Viewer']);
    $privilegedRole->permissions()->sync([$viewProducts->id]);

    $staff = User::factory()->create(['tier' => Tier::User]);
    $staff->roles()->attach($staffRole->id, ['assigned_by' => null]);
    $target = User::factory()->create(['tier' => Tier::User]);

    actingAs($staff)
        ->post(route('admin.users.bulk-assign-roles'), [
            'user_ids' => [$target->id],
            'role_ids' => [$privilegedRole->id],
        ])->assertSessionHasErrors('role_ids');

    expect($target->fresh()->roles()->where('role_id', $privilegedRole->id)->exists())->toBeFalse();
});

it('does not remove existing roles when bulk assigning', function () {
    $target = User::factory()->create(['tier' => Tier::User]);
    $existingRole = Role::factory()->create();
    $target->roles()->attach($existingRole->id, ['assigned_by' => null]);

    actingAs($this->admin)
        ->post(route('admin.users.bulk-assign-roles'), [
            'user_ids' => [$target->id],
            'role_ids' => [$this->role->id],
        ])->assertRedirect();

    $assignedIds = $target->fresh()->roles()->pluck('role_id')->toArray();
    expect($assignedIds)->toContain($existingRole->id)
        ->and($assignedIds)->toContain($this->role->id);
});

it('silently skips users the actor cannot edit', function () {
    $otherAdmin = User::factory()->create(['tier' => Tier::Admin]);

    actingAs($this->admin)
        ->post(route('admin.users.bulk-assign-roles'), [
            'user_ids' => [$otherAdmin->id],
            'role_ids' => [$this->role->id],
        ])->assertRedirect();

    expect($otherAdmin->fresh()->roles()->count())->toBe(0);
});

it('returns a validation error when role_ids is empty', function () {
    $target = User::factory()->create(['tier' => Tier::User]);

    actingAs($this->admin)
        ->post(route('admin.users.bulk-assign-roles'), [
            'user_ids' => [$target->id],
            'role_ids' => [],
        ])->assertSessionHasErrors('role_ids');
});

it('returns a validation error when user_ids is missing', function () {
    actingAs($this->admin)
        ->post(route('admin.users.bulk-assign-roles'), [
            'role_ids' => [$this->role->id],
        ])->assertSessionHasErrors('user_ids');
});

it('tracks who assigned the role via the pivot', function () {
    $target = User::factory()->create(['tier' => Tier::User]);

    actingAs($this->admin)
        ->post(route('admin.users.bulk-assign-roles'), [
            'user_ids' => [$target->id],
            'role_ids' => [$this->role->id],
        ]);

    $pivot = $target->fresh()->roles()->where('role_id', $this->role->id)->first()?->pivot;
    expect($pivot?->assigned_by)->toBe($this->admin->id);
});

it('site admin can assign roles across all tier levels', function () {
    $anotherAdmin = User::factory()->create(['tier' => Tier::Admin]);

    actingAs($this->siteAdmin)
        ->post(route('admin.users.bulk-assign-roles'), [
            'user_ids' => [$anotherAdmin->id],
            'role_ids' => [$this->role->id],
        ])->assertRedirect();

    expect($anotherAdmin->fresh()->roles()->where('role_id', $this->role->id)->exists())->toBeTrue();
});
