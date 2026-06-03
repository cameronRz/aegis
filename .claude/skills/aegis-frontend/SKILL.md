---
name: aegis-frontend
description: "Activate when working with Aegis React pages, components, TypeScript types, or Inertia shared data. Triggers: creating or modifying pages in `resources/js/pages/`, components in `resources/js/components/`, TypeScript types in `resources/js/types/`, Inertia shared props, or Tailwind design tokens in `resources/css/app.css`. Do NOT activate for backend-only PHP changes."
license: MIT
metadata:
  author: Cameron
---

# Aegis — Frontend

## React Pages (`resources/js/pages/`)

### App pages
| File | Description |
|---|---|
| `welcome.tsx` | Public landing page with auth links |
| `dashboard.tsx` | Authenticated home; placeholder grid (3-col desktop) |

### Admin pages (`users/`)
| File | Description |
|---|---|
| `users/index.tsx` | User list with search, pagination (15/page), role badges; "Create User" button shown when `auth.can.create_user` |
| `users/show.tsx` | User detail with role badge and permission toggle controls |
| `users/create.tsx` | Create user form: name, email, role select, optional permissions (admins only); sends password reset email on creation |

### Settings pages (`settings/`)
| File | Description |
|---|---|
| `settings/profile.tsx` | Edit first/last name, email; delete account |
| `settings/security.tsx` | Change password, manage 2FA, manage passkeys |
| `settings/appearance.tsx` | Theme toggle (light/dark) |

### Auth pages (`auth/`)
| File | Description |
|---|---|
| `auth/login.tsx` | Email/password + passkey login |
| `auth/register.tsx` | New account registration |
| `auth/forgot-password.tsx` | Request password reset link |
| `auth/reset-password.tsx` | Set new password with token |
| `auth/verify-email.tsx` | Email verification prompt |
| `auth/two-factor-challenge.tsx` | TOTP code entry during login |
| `auth/confirm-password.tsx` | Re-confirm password for sensitive actions |

**Page conventions:**
- Each page sets a `.layout` property that passes breadcrumbs and titles to the app layout
- Auth pages use `AuthLayout`; all other pages use `AppLayout`

---

## React Components (`resources/js/components/`)

### Organization
- **`ui/`** — Generic, unstyled-first primitives (shadcn-style). Not app-specific. Examples: `Button`, `Badge`, `Card`, `Table`, `Dialog`, `Input`, `Select`, `Tooltip`, `Sheet`, `Sidebar`, `Skeleton`, `Sonner`.
- **`components/` root** — App/feature-specific components that know about domain and layout. Examples: `app-sidebar.tsx`, `app-header.tsx`, `nav-main.tsx`, `manage-passkeys.tsx`, `delete-user.tsx`.

### Key app-shell components
| Component | Role |
|---|---|
| `app-layout.tsx` | Wraps authenticated pages; accepts `breadcrumbs` prop |
| `auth-layout.tsx` | Wraps auth pages; accepts `title` and `description` props |
| `app-shell.tsx` | Root app wrapper |
| `app-sidebar.tsx` | Full sidebar with nav, user menu |
| `app-header.tsx` | Top bar |
| `app-content.tsx` | Main content area wrapper |
| `breadcrumbs.tsx` | Breadcrumb trail |
| `heading.tsx` | Page heading with optional description |
| `nav-main.tsx` | Primary nav items |
| `nav-user.tsx` | User avatar/menu in navbar |
| `alert-error.tsx` | Inline error alert |

### Auth-specific components
- `manage-passkeys.tsx`, `passkey-register.tsx`, `passkey-verify.tsx`, `passkey-item.tsx`
- `manage-two-factor.tsx`, `two-factor-setup-modal.tsx`, `two-factor-recovery-codes.tsx`
- `delete-user.tsx`, `password-input.tsx`, `appearance-tabs.tsx`

---

## TypeScript Types (`resources/js/types/`)

### `auth.ts` — Core domain types
```ts
type Role = 'site_admin' | 'admin' | 'manager' | 'user';

type Permission = { id, name, display_name, description, created_at, updated_at };

type User = {
    id, first_name, last_name, full_name, email,
    role: Role, avatar?, email_verified_at,
    two_factor_enabled?, permissions?: Permission[],
    created_at, updated_at
};

type Can = { view_users: boolean; create_user: boolean; [key: string]: boolean };  // gates shared via Inertia

type Auth = { user: User; can: Can };

type Passkey = { id, name, authenticator, created_at_diff, last_used_at_diff };
```

### `index.ts` — Utilities
```ts
type PaginatedData<T> = { data: T[]; current_page, last_page, total, links, ... };
```

`Can` is shared from the server via `HandleInertiaRequests` middleware and reflects which gates pass for the authenticated user. Extend it as new gates are added.

---

## Inertia Shared Data

`HandleInertiaRequests` middleware shares these props on every page:
- `auth.user` — authenticated user (with `full_name`, `role`)
- `auth.can` — gate results (e.g., `view_users`)
- `name` — app name
- `sidebarOpen` — cookie-persisted sidebar state

---

## Styling

**Stack:** Tailwind CSS v4 via `@tailwindcss/vite`. No `tailwind.config.js` — configuration is in `resources/css/app.css`.

**Font:** Instrument Sans (via Bunny Fonts), weights 400/500/600.

**Design tokens** — CSS custom properties in OKLch color space. Current palette is intentionally neutral/monochrome (brand colors are a placeholder).

Key token groups:
- `--background` / `--foreground` — page surfaces
- `--primary` / `--primary-foreground` — primary actions
- `--secondary`, `--muted`, `--accent` — supporting surfaces
- `--destructive` — danger/delete actions
- `--border`, `--input`, `--ring` — form and structural chrome
- `--sidebar-*` — sidebar-specific variants
- `--chart-*` — 5 data visualization colors
- `--radius` — base border radius (`0.625rem`); `lg`/`md`/`sm` derived from it

Dark mode is class-based: `.dark` on the root. Custom variant: `@custom-variant dark (&:is(.dark *))`.
