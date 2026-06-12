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
| `stripe_customer_id` | string\|null | unique; set on registration and admin user creation |

**Appended attributes:**
- `full_name` — computed: `"{first_name} {last_name}"`

**Relationships:**
- `permissions()` → `BelongsToMany(Permission)` via `user_permissions` pivot; pivot has `granted_by` (user_id FK) and timestamps
- Passkeys → managed by Fortify via `passkeys` table (user_id FK, cascade delete)

**Key methods:**
- `isAdmin(): bool` — returns `true` for `site_admin` and `admin` roles; use this instead of inline `in_array($role, [...])` checks
- `hasPermission(PermissionName $permission): bool` — returns `true` if user has the named permission. Calls `isAdmin()` first (short-circuits for admins), then checks `$this->loadMissing('permissions')->permissions->pluck('name')->contains($permission->value)` (in-memory, no additional DB query once loaded)
- `assignableRoles(): Role[]` — returns the Role cases this user is allowed to assign to others. `site_admin` gets all roles; everyone else gets `[Manager, User]`. Used by `UserController`, `StoreUserRequest`, and `UpdateUserRequest` — call `array_column($user->assignableRoles(), 'value')` to get string values for validation/view props.

**Fillable:** `first_name`, `last_name`, `email`, `password`, `stripe_customer_id` — `role` and `email_verified_at` are intentionally NOT fillable; set them directly on the model instance after create to prevent mass assignment escalation.

**Traits:** `HasFactory`, `Notifiable`, `PasskeyAuthenticatable`, `TwoFactorAuthenticatable`

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
| `users` | All user accounts (staff and future clients); includes `stripe_customer_id` |
| `permissions` | Named permission definitions |
| `user_permissions` | Many-to-many: which users have which permissions |
| `categories` | Product category tree (self-referential via `parent_id`) |
| `products` | Products available for purchase (physical, digital, subscription); includes `stripe_product_id`, `stripe_price_id` |
| `carts` | One cart per user (`user_id` nullable, nullOnDelete) |
| `cart_items` | Line items in a cart: `cart_id`, `product_id`, `quantity`; unique(`cart_id`, `product_id`) |
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
- `StoreUserRequest` — validates new user creation; uses `profileRules()` + role + optional permissions
- `UpdateUserRequest` — validates user edits; same shape as `StoreUserRequest` but passes `$userId` to `profileRules()` so the email uniqueness check ignores the current user
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

### Sort order on product update
The `Sortable` trait only fires on `creating`. On update, `ProductController::update()` handles sort order manually: if `category_id` changed, it sets `sort_order = max(sort_order) + 1` within the new category (using `Product::where('category_id', $newId)->max('sort_order') + 1`, which handles `null` correctly via Laravel's query builder). If category is unchanged, sort_order is not modified.

### Authorization in `UserController`
- Route-level: `can:edit_user` / `can:delete_user` gates control access to routes
- Controller-level: `$this->authorize('update', $user)` / `$this->authorize('delete', $user)` enforce per-model policy (e.g., admin can't edit another admin)
- `show()` passes `canEdit`, `canDelete`, `canManagePermissions` as server-side Inertia props — computed via `Gate::allows()` AND `$viewer->can('update'/'delete'/'managePermissions', $user)` — so the frontend never re-derives these
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
