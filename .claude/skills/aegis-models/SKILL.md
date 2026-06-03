---
name: aegis-models
description: "Activate when working with Aegis domain models, database schema, or the role/permission system. Triggers: User model, Permission model, Role enum, gates, `user_permissions` pivot, `hasPermission`, database migrations or table structure, form requests, or validation rules. Do NOT activate for frontend-only changes or route definitions."
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
- `hasPermission(string $permission): bool` — returns `true` if user has the named permission. Site admins and admins bypass this check via the `admin` gate.

**Fillable:** `first_name`, `last_name`, `email`, `password` — `role` and `email_verified_at` are intentionally NOT fillable; set them directly on the model instance after create to prevent mass assignment escalation.

**Traits:** `HasFactory`, `Notifiable`, `PasskeyAuthenticatable`, `TwoFactorAuthenticatable`

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

### Gates (defined in `AppServiceProvider`)
- `admin` — passes for `site_admin` and `admin` roles
- `view_users` — calls `User::hasPermission('view_users')`
- `create_user` — calls `User::hasPermission('create_user')`
- **Before gate:** site_admin and admin automatically pass all gates (short-circuit)

### How permissions work in practice
- Named permissions (e.g., `view_users`) are rows in the `permissions` table
- Granted to individual users via the `user_permissions` pivot
- `User::hasPermission()` checks the pivot; admins bypass via the before-gate
- New feature gates should be added to `AppServiceProvider` and correspond to a `permissions` row

---

## Database Tables

| Table | Purpose |
|---|---|
| `users` | All user accounts (staff and future clients) |
| `permissions` | Named permission definitions |
| `user_permissions` | Many-to-many: which users have which permissions |
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

Password rules differ by environment: production requires 12+ chars, mixed case, numbers, symbols, not compromised.
