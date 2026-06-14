---
name: aegis-models
description: "Activate when working with Aegis domain models, database schema, or the role/permission system. Triggers: User model, Permission model, Role model (RBAC), Category model, Tier enum, PermissionName enum, Sortable trait, gates, `role_user` / `role_permissions` pivots, `hasPermission`, database migrations or table structure, form requests, or validation rules. Do NOT activate for frontend-only changes or route definitions."
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
| `role` | `Tier` enum | default: `user`; coarse access tier, not RBAC |
| `email_verified_at` | datetime\|null | |
| `two_factor_secret` | text\|null | Fortify managed |
| `two_factor_recovery_codes` | text\|null | Fortify managed |
| `two_factor_confirmed_at` | timestamp\|null | |
| `remember_token` | string\|null | |
| `stripe_customer_id` | string\|null | unique; set on registration and admin user creation |
| `deleted_at` | timestamp\|null | soft deletes |

**Appended attributes:**
- `full_name` — computed: `"{first_name} {last_name}"`

**Relationships:**
- `roles()` → `BelongsToMany(Role, 'role_user')->withPivot('assigned_by')->withTimestamps()` — the user's RBAC roles (many-to-many); use `syncWithPivotValues()` to update
- Passkeys → managed by Fortify via `passkeys` table (user_id FK, cascade delete)

**Key methods:**
- `isAdmin(): bool` — returns `true` for `site_admin` and `admin` tiers; use this instead of inline `in_array($role, [...])` checks
- `hasPermission(PermissionName $permission): bool` — returns `true` if user has the named permission. Calls `isAdmin()` first (short-circuits for admins), then loads `roles.permissions` and checks if any role's permissions contain the slug: `$this->loadMissing('roles.permissions')->roles->flatMap->permissions->pluck('name')->contains($permission->value)`. No roles assigned → `false`.
- `assignableTiers(): Tier[]` — returns the Tier cases this user is allowed to assign to others. `site_admin` gets all tiers (`Tier::cases()`); everyone else gets `[Tier::User]`. Used by `UserController`, `StoreUserRequest`, and `UpdateUserRequest` — call `array_column($user->assignableTiers(), 'value')` to get string values for validation/view props.

**Fillable:** `first_name`, `last_name`, `email`, `password`, `stripe_customer_id` — `role` and `email_verified_at` are intentionally NOT fillable; set them directly on the model instance after create to prevent mass assignment escalation.

**Traits:** `HasFactory`, `Notifiable`, `PasskeyAuthenticatable`, `SoftDeletes`, `TwoFactorAuthenticatable`

**Soft delete cleanup (`booted()`):** on `deleting()`, deletes the user's `passkeys` and any rows in `sessions` for `user_id` (invalidates active sessions and blocks re-auth). The `role_user` pivot rows cascade-delete automatically. The `role` column is a plain `string` cast to `Tier` (not a Postgres enum).

**Auth + soft delete:** the `SoftDeletes` global scope (`whereNull('deleted_at')`) means `User::find($id)` returns `null` for soft-deleted users, so Laravel's session-based re-auth treats them as logged out and login attempts fail validation. Use `User::onlyTrashed()` / `withTrashed()` for trash/restore/force-delete flows.

---

## Product Model

Represents items available for purchase. Supports physical goods, digital downloads, and subscriptions.

| Field | Type | Notes |
|---|---|---|
| `id` | int | |
| `category_id` | int\|null | FK → categories (null on delete) |
| `name` | string | |
| `type` | `ProductType` enum | `physical`, `digital`, `subscription` |
| `sku` | string | unique |
| `is_active` | boolean | default true |
| `description` | string | |
| `price` | unsignedInteger | stored in cents |
| `price_type` | `PriceType` enum | `one_time`, `recurring` |
| `billing_interval` | `BillingInterval` enum\|null | `weekly`, `monthly`, `yearly`; null for non-subscriptions |
| `billing_interval_count` | int\|null | e.g. 2 + `weekly` = every two weeks |
| `trial_period_days` | int\|null | |
| `stock_quantity` | int\|null | null = unlimited |
| `track_inventory` | boolean | default false |
| `sort_order` | int | default 0; auto-assigned via `Sortable` trait |
| `image` | string\|null | path under `storage/app/public/products/`; null if no image uploaded |
| `stripe_product_id` | string\|null | Stripe Product ID; set by `ProductObserver` on creation |
| `stripe_price_id` | string\|null | Stripe Price ID; set by `ProductObserver` on creation; rotated when price/interval changes |
| `deleted_at` | timestamp\|null | soft deletes |

**Fillable:** `category_id`, `name`, `type`, `sku`, `is_active`, `description`, `price`, `price_type`, `billing_interval`, `billing_interval_count`, `trial_period_days`, `stock_quantity`, `track_inventory`, `sort_order`, `image`, `stripe_product_id`, `stripe_price_id`

**Casts:** `type` → `ProductType`, `price_type` → `PriceType`, `billing_interval` → `BillingInterval`, `is_active` → `boolean`, `track_inventory` → `boolean`

**Traits:** `HasFactory`, `SoftDeletes`, `Sortable`

**Image storage:** uploaded files are stored on the `public` disk under `products/` via `storage:link`. The `image` column holds the relative path (e.g. `products/abc123.jpg`). Use `Storage::url($product->image)` or `asset('storage/' . $product->image)` to build the public URL.

**Relationships:**
- `category()` → `BelongsTo(Category)`

**Scopes:**
- `scopeActive()` — filters to `is_active = true`
- `scopeOrdered()` — orders by `sort_order` then `id` (provided by `Sortable` trait)

**`sortableScope()` override:** returns `['category_id']` — sort sequence is scoped per category.

**Scopes (continued):**
- `scopeSearch(?string $search)` — filters by `name LIKE` or `sku LIKE` when `$search` is non-null. Used in `ProductController` for both `index()` and `trash()`. Call as `->search($request->input('search'))`.

**`ProductValidationRules` trait (`app/Concerns/ProductValidationRules.php`):** shared validation rules for `StoreProductRequest` and `UpdateProductRequest`. The `productRules()` method returns all shared rules. Each request adds its own SKU uniqueness rule on top. Pattern mirrors `ProfileValidationRules`.

**Enums (all in `App\Enum\`):**
- `ProductType` — `Physical`, `Digital`, `Subscription`
- `PriceType` — `OneTime`, `Recurring`
- `BillingInterval` — `Weekly`, `Monthly`, `Yearly`

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

Named, reusable permissions. Grouped into RBAC roles by admins.

| Field | Type | Notes |
|---|---|---|
| `id` | int | |
| `name` | string | unique slug, e.g. `view_users` |
| `display_name` | string | human-readable label |
| `description` | string\|null | |

`Role::permissions()` is the relationship used to query/sync; `Permission` itself has no inverse `belongsToMany`.

---

## `Role` Model (`app/Models/Role.php`) — RBAC

A named, admin-managed bundle of permissions (classic RBAC role). Users can hold multiple roles.

| Field | Type | Notes |
|---|---|---|
| `id` | int | |
| `name` | string | unique |
| `description` | string\|null | |

**Fillable:** `name`, `description`

**Factory:** `RoleFactory`

**Relationships:**
- `permissions()` → `BelongsToMany(Permission)` via `role_permissions` (pure pivot, composite PK on `(role_id, permission_id)`, no timestamps)
- `users()` → `BelongsToMany(User)` via `role_user`

**Key methods:**
- `isAssigned(): bool` — `true` if any user has this role (`DB::table('role_user')->where('role_id', $id)->exists()`). `RoleController::destroy()` checks this and returns a user-facing error (the DB also enforces via `restrictOnDelete`).

---

## Tier & Permission System

### Tiers (`App\Enum\Tier`) — coarse access levels

```
site_admin > admin > user
```

| Tier | Access |
|---|---|
| `site_admin` | Full access to everything, bypasses all permission checks |
| `admin` | Can perform actions explicitly enabled for admins; cannot be edited by regular admins |
| `user` | Base-level access; gains additional permissions only via assigned RBAC roles |

The `manager` tier was removed in an earlier migration; existing `manager` users were migrated to `user`.

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
| `ViewProducts` | `view_products` |
| `CreateProduct` | `create_product` |
| `EditProduct` | `edit_product` |
| `DeleteProduct` | `delete_product` |

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

`managePermissions` was removed along with individual permission toggles — permission set assignment happens through the user create/edit form (`permission_set_id`), gated the same as `update`.

**Why no `before()` on the policy:** The route-level `can:edit_user` gate already lets admins through (via `hasPermission → isAdmin`). Adding a `before()` on the policy would also let them edit *other* admins, which is intentionally restricted.

### How permissions work in practice
- Permissions are defined as `PermissionName` enum cases; their `->value` is the slug stored in the `permissions` table `name` column
- Permissions are grouped into RBAC `Role`s (admin/site_admin managed); users can hold **multiple** roles via `role_user` pivot
- `User::hasPermission(PermissionName $permission)` loads all assigned roles with their permissions and checks if any contain the slug; admins bypass via `isAdmin()`; no roles assigned → `false`
- Adding a new permission: add a `PermissionName` case → add a `PermissionSeeder` row → gate auto-registers (no `AppServiceProvider` edit needed) → add it to relevant `Role`s via the admin UI

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

## Cart Models

**`Cart`** (`app/Models/Cart.php`)
- `belongsTo(User::class)`
- `hasMany(CartItem::class)`
- Fillable: `user_id`

**`CartItem`** (`app/Models/CartItem.php`)
- `belongsTo(Cart::class)`
- `belongsTo(Product::class)`
- Fillable: `cart_id`, `product_id`, `quantity`

**`CartException`** (`app/Exceptions/CartException.php`) — thrown by `CartService` on business rule violations. Named constructors: `::productInactive()`, `::subscriptionQuantityExceeded()`, `::insufficientStock(int $available)`. Controllers catch it and return `back()->withErrors(['cart' => $e->getMessage()])`.

**`CartService`** (`app/Services/CartService.php`) — all cart mutations go through here. Key methods: `getOrCreate(User)`, `add(Cart, Product, int $qty=1)`, `updateQuantity(CartItem, int)`, `remove(CartItem)`, `clear(Cart)`, `total(Cart): int`, `isEmpty(Cart): bool`, `hasSubscription(Cart): bool`. Every mutation calls `syncCartCount()` which writes `cart_count` to the session. The `add()` method queries from DB (not the in-memory collection) to detect existing items — the collection is stale after first add.

**`CartController::show()`** filters out cart items whose product is `null` before passing to Inertia. Force-deleting a product cascades and removes the cart item, but soft-deleting leaves the item with an unresolvable `belongsTo` (null). The filter strips these silently; checkout validation catches them separately.

**Business rules enforced in `CartService::add()`:**
- Product must be `is_active`
- Subscription products: max quantity of 1 (across existing + new)
- Physical products with `track_inventory`: `stock_quantity` must cover requested quantity

---

## Order Models

**`OrderStatus`** enum (`app/Enum/OrderStatus.php`): `Pending`, `Paid`, `Failed`, `Refunded`, `Expired`

**`Order`** (`app/Models/Order.php`)
- `belongsTo(User::class)` (nullable)
- `hasMany(OrderItem::class)`
- Fillable: `user_id`, `status`, `subtotal`, `total`, `stripe_checkout_session_id`, `stripe_payment_intent_id`
- Casts: `status` → `OrderStatus`, `subtotal`/`total` → `integer`
- `order_number` auto-generated in `booted()` `created` event: `'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT)`. Column is nullable in the DB to allow the initial insert, then immediately filled via `saveQuietly()`.

**`OrderItem`** (`app/Models/OrderItem.php`)
- `belongsTo(Order::class)`
- `belongsTo(Product::class)` (nullable — product may be soft-deleted)
- Fillable: `order_id`, `product_id`, `product_name`, `product_sku`, `product_type`, `price`, `quantity`
- Snapshot columns (`product_name`, `product_sku`, `product_type`, `price`) preserve the product state at time of purchase — never read from the live `Product` for order display.

**Factories:** `OrderFactory` (states: `paid()`, `expired()`); `OrderItemFactory` (state: `forProduct(Product $product)`)

**`CheckoutController`** (`app/Http/Controllers/CheckoutController.php`)

- **`store()`**: validates cart not empty → all items active → all items have `stripe_price_id` → lazy Stripe customer creation → DB transaction creates `Order` + `OrderItem` snapshots → builds Stripe session params (payment vs subscription mode, trial days, one-time items in `add_invoice_items`) → creates Stripe session → saves `stripe_checkout_session_id` → `Inertia::location($session->url)`
- **`success()`**: looks up `Order` by `stripe_checkout_session_id` from `?session_id=` query param → enforces ownership → renders `checkout/success`; returns 400 if no `session_id`
- **`cancel()`**: renders `checkout/cancel`; cart is untouched

**`ExpireStaleOrders`** command (`app/Console/Commands/ExpireStaleOrders.php`): bulk-updates `pending` orders older than 25 hours to `expired`. Scheduled hourly in `routes/console.php`. Does not cancel Stripe sessions (they auto-expire after 24h).

---

## Stripe Integration

### `StripeService` (`app/Services/StripeService.php`)

Wraps the Stripe PHP SDK (`stripe/stripe-php ^20`). **All Stripe API calls go through here** — never call `\Stripe\*` directly from controllers or observers.

Bound as a singleton in `AppServiceProvider::register()` with a pinned API version (`2024-06-20`) and key from `config('services.stripe.secret')`.

| Method | Returns | Notes |
|---|---|---|
| `createCustomer(User)` | `Customer` | Creates Stripe customer with user's name and email |
| `createProduct(Product)` | `StripeProduct` | Creates Stripe product with name and description |
| `createPrice(Product, string $stripeProductId)` | `Price` | One-time or recurring; maps `BillingInterval` to Stripe intervals |
| `archivePrice(string $stripePriceId)` | `void` | Sets `active: false` — prices are immutable, archive before replacing |
| `archiveProduct(string $stripeProductId)` | `void` | Sets `active: false` on the Stripe product |
| `updateProduct(string $stripeProductId, array)` | `StripeProduct` | Updates name/description on the Stripe product |
| `createCheckoutSession(array)` | `CheckoutSession` | Passes raw params; built by `CheckoutController` |
| `retrieveCheckoutSession(string $sessionId)` | `CheckoutSession` | |
| `cancelSubscription(string $id, bool $atPeriodEnd=true)` | `Subscription` | Sets `cancel_at_period_end` |
| `createBillingPortalSession(string $customerId, string $returnUrl)` | `BillingPortalSession` | |
| `constructEvent(string $payload, string $signature)` | `Event` | Verifies webhook signature using `STRIPE_WEBHOOK_SECRET` |

**Error handling:** all methods declare `@throws ApiErrorException`. Callers decide whether to surface the error (checkout) or swallow it (registration, observer). Errors are logged to `Log::channel('stripe')`.

**`BillingInterval` → Stripe interval mapping:** `Weekly` → `week`, `Monthly` → `month`, `Yearly` → `year`.

**Mocking in tests:** `StripeService` is not `readonly class` (Mockery subclasses it). Use `$this->mock(StripeService::class, fn (MockInterface $mock) => ...)` in each test file's `beforeEach` — the global Pest.php hook does not reliably intercept before factory creates. For tests that just need to prevent real API calls (fixture-only product creation), use `$mock->allows(...)`. For tests asserting Stripe behaviour, use `$mock->expects(...)->once()`.

### `ProductObserver` (`app/Observers/ProductObserver.php`)

Registered in `AppServiceProvider::boot()` via `Product::observe(ProductObserver::class)`. Injects `StripeService`.

| Event | Behaviour |
|---|---|
| `created` | `createProduct` → `createPrice` → saves both IDs back via `withoutEvents()` to avoid recursive observer trigger |
| `updated` | If `name`/`description` changed: `updateProduct`. If `price`/`billing_interval`/`billing_interval_count` changed: `archivePrice` (old) → `createPrice` → save new `stripe_price_id` via `withoutEvents()`. Skips if `stripe_product_id` is null. |
| `forceDeleted` | `archiveProduct` (sets active: false on Stripe). Skips if `stripe_product_id` is null. Soft-delete does NOT archive — product may be restored. |

**Key rules:**
- Stripe Prices are immutable — never edit, always archive + create new when price/interval changes
- Use `$product->withoutEvents(fn () => $product->update([...]))` when saving Stripe IDs back to avoid re-triggering the observer
- `wasChanged(['price', 'billing_interval', 'billing_interval_count'])` detects price-affecting changes in `updated`
- All failures are caught, logged to `Log::channel('stripe')`, and do not bubble — the product save must not be rolled back by a Stripe failure

**`stripe_customer_id` on admin-created users:** `UserController::store()` also calls `StripeService::createCustomer()` after `User::create()`, following the same catch-log-continue pattern as `CreateNewUser`.

---

## Database Tables

| Table | Purpose |
|---|---|
| `users` | All user accounts (staff and future clients); includes `stripe_customer_id`, `deleted_at` (soft deletes) |
| `permissions` | Named permission definitions |
| `roles` | Named, admin-managed RBAC roles (bundles of permissions) |
| `role_permissions` | Pure pivot: which permissions belong to which role (composite PK `(role_id, permission_id)`, no timestamps) |
| `role_user` | Many-to-many: user→role assignments with `assigned_by` (nullable, nullOnDelete) and timestamps; composite PK `(user_id, role_id)` |
| `categories` | Product category tree (self-referential via `parent_id`) |
| `products` | Products available for purchase (physical, digital, subscription); includes `stripe_product_id`, `stripe_price_id` |
| `carts` | One cart per user (`user_id` nullable, nullOnDelete) |
| `cart_items` | Line items in a cart: `cart_id`, `product_id`, `quantity`; unique(`cart_id`, `product_id`) |
| `orders` | Purchase records: `order_number` (auto-generated `ORD-000001` in `created` event), `user_id` (nullable, nullOnDelete), `status` (`OrderStatus` enum), `subtotal`/`total` (cents), `stripe_checkout_session_id`, `stripe_payment_intent_id` |
| `order_items` | Snapshot line items per order: `order_id` (cascadeDelete), `product_id` (nullable, nullOnDelete), `product_name`, `product_sku`, `product_type`, `price` (cents), `quantity` |
| `stripe.log` | Dedicated daily log channel (`storage/logs/stripe-YYYY-MM-DD.log`) for all Stripe errors; 14-day rotation |
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
- `CreateNewUser` — validates and creates new users; after `User::create()`, calls `StripeService::createCustomer()` synchronously and saves `stripe_customer_id`. Stripe failure is caught, logged to the `stripe` channel, and does not block registration — the ID is created lazily at checkout if null.
- `ResetUserPassword` — handles password reset

---

## Form Requests & Validation

Shared validation traits (in `app/Http/Requests/Concerns/`):
- `PasswordValidationRules` — `passwordRules()` (required, confirmed, Password::default()), `currentPasswordRules()`
- `ProfileValidationRules` — `profileRules(?int $userId)` for first/last name and unique email

Form requests:
- `StoreUserRequest` — validates new user creation; uses `profileRules()` + `role` (validated against `assignableTiers()`) + `role_ids` (`['nullable', 'array']`, `role_ids.*` → `['integer', 'exists:roles,id']`)
- `UpdateUserRequest` — same shape as `StoreUserRequest` but passes `$userId` to `profileRules()` so email uniqueness ignores the current user
- `StoreRoleRequest` — validates `name` (required, unique on `roles`), `description` (nullable, max 1000), `permissions` (nullable array of permission IDs, `exists:permissions,id`)
- `UpdateRoleRequest` — same as `StoreRoleRequest` but `name` uniqueness ignores the current role via `->ignore($this->route('role'))`
- `StoreCategoryRequest` — validates `name` (required string), `slug` (required, unique, lowercase-kebab regex), `parent_id` (nullable FK → categories), `is_active` (boolean). `sort_order` is intentionally excluded — auto-assigned by the `Sortable` trait.
- `UpdateCategoryRequest` — same rules as `StoreCategoryRequest` except the slug uniqueness check ignores the current category via `Rule::unique('categories', 'slug')->ignore($this->route('category'))`.
- `StoreProductRequest` — validates `name`, `sku` (unique), `description`, `category_id` (nullable FK), `type` (enum), `is_active`, `price` (integer cents), `price_type` (enum), `billing_interval` + `billing_interval_count` (required when `type = subscription`, via `Rule::requiredIf`), `trial_period_days` (nullable int), `track_inventory`, `stock_quantity` (required when `track_inventory = true`), `image` (nullable image file, max 2 MB). `sort_order` excluded — auto-assigned scoped to `category_id`.
- `UpdateProductRequest` — same rules as `StoreProductRequest` except: SKU uniqueness ignores the current product via `Rule::unique()->ignore($this->route('product'))`; adds `remove_image` (boolean). `sort_order` excluded — controller resets it to end of new category when `category_id` changes, otherwise preserves it.

### Delete / Trash / Restore behaviour

| Action | Method | Image | Redirect |
|---|---|---|---|
| Soft-delete (show page) | `destroy()` — `$product->delete()` | Kept on disk | `admin.products` |
| Restore (trash page) | `restore()` — `$product->restore()` | Unchanged | `admin.products.trash` |
| Force delete (trash page) | `forceDestroy()` — `$product->forceDelete()` | Deleted from public disk | `admin.products.trash` |

Soft-delete keeps the image on disk because the record may be restored. Force delete cleans up the image since the record is gone permanently.

`restore` and `forceDestroy` routes use `->withTrashed()` so route model binding finds soft-deleted records. Without it, the binding 404s on trashed products.

### User delete / trash / restore

Same pattern as products, minus image handling:

| Action | Method | Redirect |
|---|---|---|
| Soft-delete (index page) | `destroy()` — `$user->delete()` | `admin.users` |
| Restore (trash page) | `restore()` — `$user->restore()` | `admin.users.trash` |
| Force delete (trash page) | `forceDestroy()` — `$user->forceDelete()` | `admin.users.trash` |

`UserController::trash()` (`can:admin`) lists `User::onlyTrashed()`, scoped by the same role-visibility rules as the active index (site_admin sees everyone; admin can't see site_admins), with `ilike` search on first/last name and email. `restore` is gated `can:delete_user`; `trash` and `forceDestroy` are gated `can:admin`. Both `restore` and `force` routes use `->withTrashed()`.

### Sort order on product update
The `Sortable` trait only fires on `creating`. On update, `ProductController::update()` handles sort order manually: if `category_id` changed, it sets `sort_order = max(sort_order) + 1` within the new category (using `Product::where('category_id', $newId)->max('sort_order') + 1`, which handles `null` correctly via Laravel's query builder). If category is unchanged, sort_order is not modified.

### Authorization in `UserController`
- Route-level: `can:edit_user` / `can:delete_user` gates control access to routes
- Controller-level: `$this->authorize('update', $user)` / `$this->authorize('delete', $user)` enforce per-model policy (e.g., admin can't edit another admin)
- `show()` passes `canEdit`, `canDelete` as server-side Inertia props — computed via `Gate::allows()` AND `$viewer->can('update'/'delete', $user)` — so the frontend never re-derives these.
- `create()` / `edit()` pass `roles` (all available RBAC roles) and `selectedRoleIds` (array of currently assigned role IDs) to the view. Role assignment syncs via `$user->roles()->syncWithPivotValues($roleIds, ['assigned_by' => auth()->id()])`.
- `show()` loads `roles.permissions` for display.
- `AuthorizesRequests` trait is on the base `Controller` class — required for `$this->authorize()` to exist (Laravel 12+ omits it by default)

Password rules differ by environment: production requires 12+ chars, mixed case, numbers, symbols, not compromised.

---

## `Money` Helper (`App\Support\Money`)

Formats a cents integer as a localised currency string for server-side use (emails, PDFs, queue jobs, Artisan output).

```php
Money::format(int $cents, string $currency = 'USD', string $locale = 'en_US'): string
// e.g. Money::format(2999) → "$29.99"
```

Uses PHP's `NumberFormatter` (intl extension). The frontend equivalent is `formatCents()` in `resources/js/lib/money.ts`. Raw cents always travel in JSON; format only at the point of output.
