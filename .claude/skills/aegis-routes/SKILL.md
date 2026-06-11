---
name: aegis-routes
description: "Activate when working with Aegis routes, controllers, or middleware. Triggers: adding or modifying routes, checking route names or middleware, wiring Wayfinder, or referencing any route in `routes/web.php` or `routes/settings.php`. Do NOT activate for model changes or frontend-only work."
license: MIT
metadata:
  author: Cameron
---

# Aegis — Routes

## `routes/web.php`

```
GET  /                          → welcome                              (public)
GET  /dashboard                 → dashboard                            (auth + verified)

GET  /shop                      → shop                                 (auth + verified)
GET  /shop/{product}            → shop.show                            (auth + verified) — 404 if product inactive or soft-deleted

GET   /admin/users                                         → admin.users                          (can:view_users)
GET   /admin/users/create                                  → admin.users.create                   (can:create_user)
POST  /admin/users                                         → admin.users.store                    (can:create_user)
GET    /admin/users/{user}/edit                             → admin.users.edit                     (can:edit_user)
PATCH  /admin/users/{user}                                 → admin.users.update                   (can:edit_user)
DELETE /admin/users/{user}                                 → admin.users.destroy                  (can:delete_user)
GET    /admin/users/{user}                                 → admin.users.show                     (can:view_users)
POST  /admin/users/{user}/permissions/{permission}/toggle  → admin.users.permissions.toggle       (can:admin)

GET   /admin/categories                                    → admin.categories                     (can:view_categories)
GET   /admin/categories/create                             → admin.categories.create              (can:create_category)
POST  /admin/categories                                    → admin.categories.store               (can:create_category)
GET   /admin/categories/{category}/edit                    → admin.categories.edit                (can:edit_category)
PATCH /admin/categories/{category}                         → admin.categories.update              (can:edit_category)
DELETE /admin/categories/{category}                        → admin.categories.destroy             (can:delete_category)

GET   /admin/products                                      → admin.products                       (can:view_products)
GET   /admin/products/create                               → admin.products.create                (can:create_product)
POST  /admin/products                                      → admin.products.store                 (can:create_product)
GET   /admin/products/trash                                → admin.products.trash                 (can:admin)
GET   /admin/products/{product}/edit                       → admin.products.edit                  (can:edit_product)
PATCH /admin/products/{product}                            → admin.products.update                (can:edit_product)
DELETE /admin/products/{product}                           → admin.products.destroy               (can:delete_product)
POST  /admin/products/{product}/restore  [withTrashed]     → admin.products.restore               (can:delete_product)
DELETE /admin/products/{product}/force   [withTrashed]     → admin.products.force-destroy         (can:admin)
GET   /admin/products/{product}                            → admin.products.show                  (can:view_products)

**`withTrashed` routes:** `restore` and `force-destroy` use `->withTrashed()` on the route definition so that Laravel's route model binding resolves soft-deleted `{product}` records. Without it, binding would 404 on trashed products.

**Route ordering note:** `users/create` is declared before `users/{user}` to prevent route model binding from treating the literal "create" segment as a user ID. `users/{user}/edit` is declared before `users/{user}` for the same reason. The same pattern applies to categories and products: `products/create` and `products/trash` are declared before `products/{product}`, and `products/{product}/edit` before `products/{product}` (show). Always declare literal-segment routes before parametric routes at the same depth.
```

## `routes/settings.php`

```
GET    /settings                → redirect to /settings/profile
GET    /settings/profile        → profile.edit                         (auth)
PATCH  /settings/profile        → profile.update                       (auth)
DELETE /settings/profile        → profile.destroy                      (auth + verified)
GET    /settings/security       → security.edit                        (auth + verified + password confirmed)
PUT    /settings/password       → user-password.update                 (throttle 6/min)
GET    /settings/appearance     → appearance.edit                      (auth + verified)
```

## Wayfinder Import Pattern

Named exports from `@/routes/admin` (e.g. `products`, `categories`, `users`) only carry the base function's type. Sub-routes (`.trash`, `.create`, `.edit`, etc.) are merged onto the default export via `Object.assign` and are **not** visible to TypeScript on the named export.

Import sub-routes directly from their sub-module:

```ts
// ✗ TS error — .trash not on the named export type
import { products } from '@/routes/admin';
products.trash.url();

// ✓ import the sub-route directly from the sub-module
import { trash } from '@/routes/admin/products';
trash.url();
```

Sub-module paths mirror the route structure: `@/routes/admin/products`, `@/routes/admin/categories`, `@/routes/admin/users`. Keep the named import from `@/routes/admin` for the index route (`.url()`), and import any sub-routes separately.

The same pattern applies to shop routes:
```ts
import { shop } from '@/routes';           // GET /shop (no-arg)
import { show } from '@/routes/shop';      // GET /shop/{product} (parametric)
show(product).url                          // string property — do not call as function
```

## Fortify Auth Routes (auto-registered)

Fortify registers all auth routes automatically: login, register, password reset, email verification, 2FA, passkeys, confirm password.
