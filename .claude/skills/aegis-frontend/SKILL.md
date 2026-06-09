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

### Admin pages

#### `users/`
| File | Description |
|---|---|
| `users/index.tsx` | User list with search, pagination (15/page), role badges; "Create User" button shown when `auth.can.create_user`; Actions column with "Edit" button shown per-row when `auth.can.edit_user` (hidden for self and privileged targets) |
| `users/show.tsx` | User detail with role badge and permission toggle controls; "Edit" button in card header and subtle "Delete user" text link (opens a Dialog with destructive Alert for confirmation) shown when `auth.can.edit_user` / `auth.can.delete_user` (hidden for self and privileged targets) |
| `users/create.tsx` | Create user form: name, email, role select, optional permissions (admins only); sends password reset email on creation |
| `users/edit.tsx` | Edit user form: same fields/permission UI as create, pre-filled with existing user data; uses PATCH; self-editing blocked (403) |

#### `categories/`
| File | Description |
|---|---|
| `categories/index.tsx` | Category list with search (name/slug), pagination (15/page), parent name column; "Create Category" button shown when `auth.can.create_category`; Edit/Delete action buttons per row shown when `auth.can.edit_category` / `auth.can.delete_category`; single shared delete Dialog populated by row state |
| `categories/create.tsx` | Create category form; uses `CategoryFormFields`; `sort_order` is auto-assigned server-side and not shown |
| `categories/edit.tsx` | Edit category form; pre-fills from `category` prop; uses PATCH via `update(category).url`; uses `CategoryFormFields`; parent options exclude the category itself (server-side) |
| `categories/category-form-fields.tsx` | **Shared domain component** — exports `CategoryFormData` type, `ParentCategory` type, and `CategoryFormFields` component. Owns slug auto-sync logic (`slugAutoSync` ref starts `true` when slug is empty → auto-generates from name; becomes `false` once user edits slug or on edit page where slug is pre-filled). Used by both `create.tsx` and `edit.tsx`. |

#### `products/`
| File | Description |
|---|---|
| `products/index.tsx` | Product list with search (name/SKU), pagination (15/page), Type badge column (Physical/Digital/Subscription), price formatted via `formatCents`, Category name column (dash when uncategorised); inactive rows rendered at `opacity-50`; Edit action button shown when `auth.can.edit_product` (disabled — not yet wired) |

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
- Breadcrumbs are **always static arrays** on `.layout` — there is no function-based or dynamic breadcrumb pattern. Pages for specific entities (show, edit) use a static label (`'User Details'`, `'Edit User'`), not the entity's name. Do not reach for `setLayoutProps` for breadcrumbs; `setLayoutProps` is only used for auth layout props (`title`, `description`).

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
| `app-sidebar.tsx` | Full sidebar with nav, user menu; nav items filtered by `auth.can[permission]`; currently: Dashboard (no gate), Users (`view_users`), Categories (`view_categories`), Products (`view_products`) |
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

const PRIVILEGED_ROLES: Role[] = ['site_admin', 'admin'];  // import from @/types, never redefine locally

type Permission = { id, name, display_name, description, created_at, updated_at };

type User = {
    id, first_name, last_name, full_name, email,
    role: Role, avatar?, email_verified_at,
    two_factor_enabled?, permissions?: Permission[],
    created_at, updated_at
};

type Category = {
    id: number; parent_id: number | null; name: string; slug: string;
    sort_order: number; is_active: boolean;
    parent?: { id: number; name: string } | null;
    created_at: string; updated_at: string;
};

type ProductType = 'physical' | 'digital' | 'subscription';
type PriceType = 'one_time' | 'recurring';
type BillingInterval = 'weekly' | 'monthly' | 'yearly';

type Product = {
    id: number; category_id: number | null; name: string; type: ProductType;
    sku: string; is_active: boolean; description: string; price: number;  // cents
    price_type: PriceType; billing_interval: BillingInterval | null;
    billing_interval_count: number | null; trial_period_days: number | null;
    stock_quantity: number | null; track_inventory: boolean; sort_order: number;
    image: string; category?: { id: number; name: string } | null;
    created_at: string; updated_at: string;
};

type Can = {
    view_users, create_user, edit_user, delete_user,
    view_categories, create_category, edit_category, delete_category,
    view_products, create_product, edit_product, delete_product,
    [key: string]: boolean
};  // gates shared via Inertia; auto-derived from permissions table in HandleInertiaRequests

type Auth = { user: User; can: Can };

type Passkey = { id, name, authenticator, created_at_diff, last_used_at_diff };
```

### `index.ts` — Utilities
```ts
type PaginatedData<T> = { data: T[]; current_page, last_page, total, links, ... };
```

### `lib/money.ts` — Price formatting
```ts
formatCents(cents: number, currency?: string, locale?: string): string
// e.g. formatCents(2999) → "$29.99"
```
Use this for all client-side price display. The raw `price` integer (cents) always travels in JSON; format only at the point of display. The PHP equivalent for server-side use (emails, PDFs) is `App\Support\Money::format(int $cents): string`.

`Can` is shared from the server via `HandleInertiaRequests` middleware and reflects which gates pass for the authenticated user. Auto-derived from `Permission::all()` — no manual list to maintain.

### Server-side authorization props (show pages)
Per-model authorization decisions (can this viewer edit/delete THIS specific user?) are computed on the server and passed as Inertia props — never re-derived on the client. The `users/show` page receives `canEdit`, `canDelete`, `canManagePermissions` as boolean props. The `Can` type in `auth` only covers global capabilities (can the user edit users at all), not per-record ones.

---

## Confirmation Dialogs

There is **no `AlertDialog`** component in this UI library. Do not try to add or import one. The established pattern for all destructive confirmations is `Dialog` + `Alert variant="destructive"` inside the content.

**Pattern — subtle trigger + Dialog confirmation:**
```tsx
// Trigger: subtle text link, not a Button
<button
    onClick={() => setOpen(true)}
    className="text-muted-foreground hover:text-destructive text-sm transition-colors"
>
    Delete something
</button>

// Dialog: DialogContent must include aria-describedby={undefined} when using
// Alert instead of DialogDescription — Radix requires one or the other, and
// omitting both causes a console warning.
<Dialog open={open} onOpenChange={setOpen}>
    <DialogContent aria-describedby={undefined}>
        <DialogTitle>Delete something</DialogTitle>
        <Alert variant="destructive">
            <AlertTitle>Are you sure?</AlertTitle>
            <AlertDescription>
                This action is permanent and cannot be undone.
            </AlertDescription>
        </Alert>
        <DialogFooter>
            <DialogClose asChild>
                <Button variant="outline">Cancel</Button>
            </DialogClose>
            <Button variant="destructive" disabled={processing} onClick={handleDelete}>
                Delete
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
```

**`aria-describedby={undefined}` rule:** Radix UI's `DialogContent` always expects either a `DialogDescription` child or an explicit `aria-describedby={undefined}` prop. When the `Alert` is serving as the description, pass `aria-describedby={undefined}` to suppress the warning. Only use `DialogDescription` when there is no other self-describing content in the dialog.

---

## Select with Nullable Values

Radix UI's `SelectItem` does not allow an empty string `value` — it is reserved internally to mean "clear/show placeholder". For optional foreign-key selects (e.g. `parent_id: number | null`), use a `"none"` sentinel:

```tsx
<Select
    value={data.parent_id?.toString() ?? 'none'}
    onValueChange={(value) =>
        setData('parent_id', value === 'none' ? null : Number(value))
    }
>
    <SelectTrigger>
        <SelectValue />
    </SelectTrigger>
    <SelectContent>
        <SelectItem value="none">None</SelectItem>
        {items.map((item) => (
            <SelectItem key={item.id} value={item.id.toString()}>{item.name}</SelectItem>
        ))}
    </SelectContent>
</Select>
```

The form state stores `number | null`; the Select converts via the sentinel. Do not use `value=""` on any `SelectItem`.

---

## Wayfinder URL Patterns

Two distinct call forms exist — mixing them up compiles silently and fails at runtime.

**Regenerating Wayfinder:** Always run `php artisan wayfinder:generate --with-form`. The `--with-form` flag is required because `vite.config.ts` sets `formVariants: true`. Omitting it strips `.form()` from every action file, breaking any page that uses the Inertia `<Form>` component (login, register, settings, etc.).

**No-arg actions** (e.g. `store`, `create`, `index`): the action is not called first; `.url()` is a method on the function itself.
```ts
import { store as storeUser } from '@/actions/App/Http/Controllers/UserController';
post(storeUser.url());
```

**Parametric actions** (e.g. `show`, `edit`, `update`): call the function with args first; `.url` is a string property on the returned `RouteDefinition` object — do not invoke it as a function.
```ts
import { edit as editUser, show as showUser } from '@/actions/App/Http/Controllers/UserController';
router.visit(showUser(user).url);       // ✓ string property
router.visit(editUser(user).url);       // ✓ string property
router.visit(editUser(user).url());     // ✗ TypeError — url is not a function
```

## Named Routes (`@/routes/admin`)

`users`, `categories`, and `products` are the named exports. Sub-routes are properties attached to each function — they are not separate named exports.

```ts
import { users as adminUsersRoute, categories as adminCategoriesRoute, products as adminProductsRoute } from '@/routes/admin';

adminUsersRoute.url()               // GET /admin/users
adminUsersRoute.show(user).url      // GET /admin/users/{user}
adminUsersRoute.edit(user).url      // GET /admin/users/{user}/edit

adminCategoriesRoute.url()          // GET /admin/categories
adminCategoriesRoute.create.url()   // GET /admin/categories/create

adminProductsRoute.url()            // GET /admin/products

import { edit as editCategory, update as updateCategory, destroy as destroyCategory } from '@/actions/App/Http/Controllers/CategoryController';

editCategory(category).url          // GET /admin/categories/{id}/edit  (string property)
updateCategory(category).url        // PATCH /admin/categories/{id}      (string property)
destroyCategory(category).url       // DELETE /admin/categories/{id}     (string property)
```

Destructuring sub-routes (`show`, `edit`, `create`) directly from `@/routes/admin` yields `undefined` — always access them as properties on the parent route function.

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
