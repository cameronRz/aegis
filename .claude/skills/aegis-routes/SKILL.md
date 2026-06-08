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

GET   /admin/users                                         → admin.users                          (can:view_users)
GET   /admin/users/create                                  → admin.users.create                   (can:create_user)
POST  /admin/users                                         → admin.users.store                    (can:create_user)
GET    /admin/users/{user}/edit                             → admin.users.edit                     (can:edit_user)
PATCH  /admin/users/{user}                                 → admin.users.update                   (can:edit_user)
DELETE /admin/users/{user}                                 → admin.users.destroy                  (can:delete_user)
GET    /admin/users/{user}                                 → admin.users.show                     (can:view_users)
POST  /admin/users/{user}/permissions/{permission}/toggle  → admin.users.permissions.toggle       (can:admin)

GET  /admin/categories                                     → admin.categories                     (can:view_categories)
GET  /admin/categories/create                              → admin.categories.create              (can:create_category)
POST /admin/categories                                     → admin.categories.store               (can:create_category)

**Route ordering note:** `users/create` is declared before `users/{user}` to prevent route model binding from treating the literal "create" segment as a user ID. `users/{user}/edit` is declared before `users/{user}` for the same reason. The same ordering applies to `categories/create` before any future `categories/{category}` routes.
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

## Fortify Auth Routes (auto-registered)

Fortify registers all auth routes automatically: login, register, password reset, email verification, 2FA, passkeys, confirm password.
