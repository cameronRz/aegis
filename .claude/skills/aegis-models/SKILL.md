---
name: aegis-models
description: "Activate when working with Aegis domain models, database schema, or the role/permission system. Triggers: User model, Permission model, Category model, Role enum, PermissionName enum, Sortable trait, gates, `user_permissions` pivot, `hasPermission`, database migrations or table structure, form requests, or validation rules. Do NOT activate for frontend-only changes or route definitions."
license: MIT
metadata:
  author: Cameron
---

# Aegis — Domain Models & Backend

## User Model

The central model. Represents both admin-side staff and (eventually) client-side end users, differentiated by `role`.

| Field | Type | Notes |
|---|---|---|
| `id` | int | |
| `first_name` | string | |
| `last_name` | string | |
| `email` | string | unique |
| `password` | hashed string | |
| `role` | `Role` enum | default: `user` |
| `email_verified_at` | datetime\|null | |
| `two_factor_secret` | text\|null | Fortify managed |
| `two_factor_recovery_codes` | text\|null | Fortify managed |
| `two_factor_confirmed_at` | timestamp\|null | |
| `remember_token` | string\|null | |

**Appended attributes:**
- `full_name` — computed: `"{first_name} {last_name}"`

**Relationships:**
- `permissions()` → `BelongsToMany(Permission)` via `user_permissions` pivot; pivot has `granted_by` (user_id FK) and timestamps
- Passkeys → managed by Fortify via `passkeys` table (user_id FK, cascade delete)

**Key methods:**
- `isAdmin(): bool` — returns `true` for `site_admin` and `admin` roles; use this instead of inline `in_array($role, [...])` checks
- `hasPermission(PermissionName $permission): bool` — returns `true` if user has the named permission. Calls `isAdmin()` first (short-circuits for admins), then checks `$this->loadMissing('permissions')->permissions->pluck('name')->contains($permission->value)` (in-memory, no additional DB query once loaded)

**Fillable:** `first_name`, `last_name`, `email`, `password` — `role` and `email_verified_at` are intentionally NOT fillable; set them directly on the model instance after create to prevent mass assignment escalation.

**Traits:** `HasFactory`, `Notifiable`, `PasskeyAuthenticatable`, `TwoFactorAuthenticatable`

---

## Category Model

Self-referential model for organising products into a tree of categories.

| Field | Type | Notes |
|---|---|---|
| `id` | int | |
| `parent_id` | int\|null | FK → categories (null = root) |
| `name` | string | |
| `slug` | string | unique |
| `sort_order` | int | default 0; auto-assigned on create via `Sortable` trait |
| `is_active` | boolean | default true |

**Fillable:** `parent_id`, `name`, `slug`, `sort_order`, `is_active`

**Casts:** `is_active` → `boolean`

**Traits:** `HasFactory`, `Sortable`

**Relationships:**
- `parent()` → `BelongsTo(Category)` via `parent_id`
- `children()` → `HasMany(Category)` via `parent_id`

**Scopes:**
- `scopeActive()` — filters to `is_active = true`
- `scopeOrdered()` — orders by `sort_order` then `id` (provided by `Sortable` trait)
- `scopeRoots()` — filters to `parent_id IS NULL`

**`sortableScope()` override:** returns `['parent_id']` — each group of siblings gets its own independent sort sequence. Root categories (`parent_id = null`) are also scoped correctly.

---

## `Sortable` Trait (`App\Concerns\Sortable`)

Reusable trait for models that need auto-assigned `sort_order`. Apply to any model that has a `sort_order` column.

**What it does:**
- `bootSortable()` — hooks into `creating`; sets `sort_order` to `max(sort_order) + 1` within the scope. Only fires if `sort_order` is `null` (manually-provided values are respected).
- `sortableScope(): array` — override in the model to return column names that scope the sequence (e.g. `['parent_id']`, `['category_id']`). Default `[]` = global max.
- `scopeOrdered(Builder $query)` — orders by `sort_order` then `id` as tiebreaker.

**Adding to a new model:**
1. Add `use Sortable;` to the model
2. Override `sortableScope()` if the sort should be scoped (e.g. within a category)
3. Ensure the table has a `sort_order` integer column

---

## Permission Model

Named, reusable permissions that can be granted to users. Think of these as feature flags assigned per user by admins.

| Field | Type | Notes |
|---|---|---|
| `id` | int | |
| `name` | string | unique slug, e.g. `view_users` |
| `display_name` | string | human-readable label |
| `description` | string\|null | |

**Relationships:**
- `users()` → `BelongsToMany(User)` via `user_permissions` pivot

---

## Pivot: `user_permissions`

| Column | Notes |
|---|---|
| `user_id` | FK → users |
| `permission_id` | FK → permissions |
| `granted_by` | FK → users (who granted it) |
| timestamps | |
| unique(`user_id`, `permission_id`) | one grant per user per permission |

---

## Role & Permission System

### Roles (`App\Enum\Role`)

```
site_admin > admin > manager > user
```

| Role | Access |
|---|---|
| `site_admin` | Full access to everything, bypasses all permission checks |
| `admin` | Can perform actions explicitly enabled for admins by site_admin (coded permissions) |
| `manager` | Elevated above `user`; intended to have more capabilities than regular users |
| `user` | Base-level access |

### `PermissionName` enum (`App\Enum\PermissionName`)

A string-backed enum that is the single source of truth for permission slugs:

| Case | Value |
|---|---|
| `ViewUsers` | `view_users` |
| `CreateUser` | `create_user` |
| `EditUser` | `edit_user` |
| `DeleteUser` | `delete_user` |
| `ViewCategories` | `view_categories` |
| `CreateCategory` | `create_category` |
| `EditCategory` | `edit_category` |
| `DeleteCategory` | `delete_category` |

Adding a new permission: add a case here, add a row in `PermissionSeeder`, and the gate is auto-registered (no manual `AppServiceProvider` edit needed).

### Gates (defined in `AppServiceProvider`)
- `admin` — passes when `$user->isAdmin()`
- One gate per `PermissionName` case — auto-registered via `foreach (PermissionName::cases() as $permission)`. Each calls `$user->hasPermission($permission)`, which short-circuits for admins via `isAdmin()`.
- **No `Gate::before()`** — removed so that `UserPolicy` methods are never bypassed by a global short-circuit

### `UserPolicy` (`app/Policies/UserPolicy.php`)
Auto-discovered by Laravel for the `User` model. No `before()` method — each method handles the admin hierarchy explicitly so that admins cannot modify other admins.

| Method | Logic |
|---|---|
| `update(viewer, target)` | Self → false. SiteAdmin → true. Else: `!target->isAdmin()` |
| `delete(viewer, target)` | Self → false. SiteAdmin → true. Else: `!target->isAdmin()` |
| `managePermissions(viewer, target)` | Self → false. SiteAdmin → true. Else: viewer is Admin AND target is not Admin |

**Why no `before()` on the policy:** The route-level `can:edit_user` gate already lets admins through (via `hasPermission → isAdmin`). Adding a `before()` on the policy would also let them edit *other* admins, which is intentionally restricted.

### How permissions work in practice
- Permissions are defined as `PermissionName` enum cases; their `->value` is the slug stored in the `permissions` table `name` column
- Granted to individual users via the `user_permissions` pivot
- `User::hasPermission(PermissionName $permission)` checks `$permission->value` against the pivot; admins bypass via `isAdmin()`
- Adding a new permission: add a `PermissionName` case → add a `PermissionSeeder` row → gate auto-registers (no `AppServiceProvider` edit needed)

### Two-layer authorization pattern

Every user-modifying action uses two separate checks:

1. **Route gate** (`can:edit_user` middleware) — "Can this user perform this class of action at all?" Passes for anyone with the global capability (admins + permission holders).
2. **Controller policy** (`$this->authorize('update', $user)`) — "Can this user act on *this specific record*?" Enforces per-record restrictions (e.g., admin can't edit another admin).

Both layers are required. The route gate alone allows admins to modify other admins. The policy alone would require duplicating gate logic everywhere.

### Authorization pitfalls — do not repeat these

**`Gate::before()` silently bypasses all policies.**
There is no `Gate::before()` in `AppServiceProvider`. Do not add one. A global `Gate::before()` that returns `true` for admins short-circuits `$this->authorize()` calls in addition to route gates — policies never execute. The admin hierarchy is instead handled inside `hasPermission()` (for gates) and explicitly inside each policy method. If you add a `Gate::before()` for any reason, every policy restriction for admins silently disappears.

**Gate definitions in `AppServiceProvider::boot()` must be enum-driven — never DB-driven.**
Do not replace the `PermissionName::cases()` loop with `Permission::each()` or any DB query in `boot()`. Tests run under `php artisan test`, where `app()->runningInConsole()` is `true`. A `!app()->runningInConsole()` guard would skip gate registration entirely during tests, causing all protected routes to return 403 regardless of user role. The gate names come from `PermissionName` enum case values (static); only the *evaluation* (inside the closure) hits the DB.

**`$this->authorize()` requires the `AuthorizesRequests` trait on the base `Controller`.**
Laravel 12+ ships a minimal base `Controller` class with no traits. This project adds `AuthorizesRequests` explicitly (`app/Http/Controllers/Controller.php`). If `$this->authorize()` ever throws "Call to undefined method", check that the trait is present — the error message does not mention the trait by name.

---

## Database Tables

| Table | Purpose |
|---|---|
| `users` | All user accounts (staff and future clients) |
| `permissions` | Named permission definitions |
| `user_permissions` | Many-to-many: which users have which permissions |
| `categories` | Product category tree (self-referential via `parent_id`) |
| `passkeys` | WebAuthn credentials (Fortify managed) |
| `password_reset_tokens` | Laravel password reset |
| `sessions` | Database-backed sessions |
| `cache` | Laravel cache |
| `jobs` | Queue jobs |

---

## Authentication (Fortify)

**Enabled features:** Registration, password reset, email verification, 2FA (TOTP), passkeys (WebAuthn).

**Config highlights:**
- Home path after login: `/dashboard`
- Auth username field: `email`
- Rate limits: 5 login/min, 5 2FA/min, 10 passkeys/min
- Passkey RP ID: derived from `APP_URL`

Actions (in `app/Actions/Fortify/`):
- `CreateNewUser` — validates and creates new users
- `ResetUserPassword` — handles password reset

---

## Form Requests & Validation

Shared validation traits (in `app/Http/Requests/Concerns/`):
- `PasswordValidationRules` — `passwordRules()` (required, confirmed, Password::default()), `currentPasswordRules()`
- `ProfileValidationRules` — `profileRules(?int $userId)` for first/last name and unique email

Form requests:
- `StoreUserRequest` — validates new user creation; uses `profileRules()` + role + optional permissions
- `UpdateUserRequest` — validates user edits; same shape as `StoreUserRequest` but passes `$userId` to `profileRules()` so the email uniqueness check ignores the current user
- `StoreCategoryRequest` — validates `name` (required string), `slug` (required, unique, lowercase-kebab regex), `parent_id` (nullable FK → categories), `is_active` (boolean). `sort_order` is intentionally excluded — auto-assigned by the `Sortable` trait.
- `UpdateCategoryRequest` — same rules as `StoreCategoryRequest` except the slug uniqueness check ignores the current category via `Rule::unique('categories', 'slug')->ignore($this->route('category'))`.

### Authorization in `UserController`
- Route-level: `can:edit_user` / `can:delete_user` gates control access to routes
- Controller-level: `$this->authorize('update', $user)` / `$this->authorize('delete', $user)` enforce per-model policy (e.g., admin can't edit another admin)
- `show()` passes `canEdit`, `canDelete`, `canManagePermissions` as server-side Inertia props — computed via `Gate::allows()` AND `$viewer->can('update'/'delete'/'managePermissions', $user)` — so the frontend never re-derives these
- `AuthorizesRequests` trait is on the base `Controller` class — required for `$this->authorize()` to exist (Laravel 12+ omits it by default)

Password rules differ by environment: production requires 12+ chars, mixed case, numbers, symbols, not compromised.
