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
| `users/index.tsx` | User list with search, pagination (15/page), tier badges (`TierBadge`); "Create User" button shown when `auth.can.create_user`; "View Trash" subtle link shown for privileged tiers (`PRIVILEGED_TIERS`); Actions column with "Edit" button shown per-row when `auth.can.edit_user` (hidden for self and privileged targets) |
| `users/show.tsx` | User detail with tier badge and a "Roles" card (lists each assigned RBAC role by name + its permissions); "Edit" button in card header and subtle "Delete user" text link shown when `canEdit`/`canDelete`. |
| `users/trash.tsx` | Admin-only trash bin: paginated table of soft-deleted users with First Name, Last Name, Email, Tier badge, Deleted date columns. Restore button (POST, `can:delete_user`, no confirmation). Permanently Delete button (`can:admin`) opens `ConfirmDialog`. Search by name/email. "Users" breadcrumb links back to the index. |
| `users/create.tsx` | Create user form; props: `availableRoles: Tier[]`, `roles: Role[]`; uses `UserFormFields`; sends password reset email on creation |
| `users/edit.tsx` | Edit user form; props: `user`, `availableRoles: Tier[]`, `roles: Role[]`, `selectedRoleIds: number[]`; pre-fills from props; uses PATCH via `updateUser(user).url`; uses `UserFormFields`; self-editing blocked (403) |
| `users/user-form-fields.tsx` | **Shared domain component** — exports `UserFormData` type (`role: Tier`, `role_ids: number[]`) and `UserFormFields` component. Has a tier `Select` (access tier) + a checkbox list of RBAC `Role[]`. Below checkboxes shows combined permissions from all checked roles (union, deduplicated). Used by both `create.tsx` and `edit.tsx`. |

#### `admin/roles/`
| File | Description |
|---|---|
| `admin/roles/index.tsx` | Role list with `DataTable` + `DataTablePagination`; columns: Name, Description, Permissions count, Users count (`users_count`), Edit/Delete action buttons; "Create Role" button. Delete opens `ConfirmDialog`; if role is in use the backend returns a session error surfaced inline. |
| `admin/roles/create.tsx` | Create form; uses `RoleFormFields`; submits via POST to `storeRole.url()`. |
| `admin/roles/edit.tsx` | Edit form pre-filled from `role` prop (with `permissions`); submits via PATCH using `updateRole(role).url`. |
| `admin/roles/role-form-fields.tsx` | **Shared domain component** — exports `RoleFormData` type and `RoleFormFields` component. Renders name/description inputs + permission checkbox grid grouped by domain area. Used by both `create.tsx` and `edit.tsx`. |

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
| `products/index.tsx` | Product list with search (name/SKU), pagination (15/page), Type badge column (Physical/Digital/Subscription), price formatted via `formatCents`, Category name column (dash when uncategorised); inactive rows at `opacity-50`; rows are clickable (navigates to show page); "View Trash" subtle link shown for admins (`PRIVILEGED_TIERS`); "Create Product" button shown when `auth.can.create_product`; Edit button shown when `auth.can.edit_product` |
| `products/show.tsx` | Two-card detail view: Card 1 shows image (if present), name, SKU, type badge, active badge, category, description, Edit button (when `canEdit`), and "Delete product" subtle link (when `canDelete`); Card 2 shows pricing details with type-specific fields (billing interval + trial for subscriptions; inventory tracking for physical). `canEdit` and `canDelete` are computed server-side via `Gate::allows()`. |
| `products/trash.tsx` | Admin-only trash bin: paginated table of soft-deleted products with Name, SKU, Type badge, Price, Deleted date columns. Restore button (POST, no confirmation). Permanently Delete button opens a confirmation Dialog. Search by name/SKU. "Products" breadcrumb links back to the index. |
| `products/create.tsx` | Create product form; uses `ProductFormFields`; `sort_order` excluded — auto-assigned server-side; submits with `forceFormData: true` for image upload |
| `products/edit.tsx` | Edit product form; pre-fills all fields from `product` prop; passes `imageUrl` to `ProductFormFields` as `existingImageUrl`; submits via PATCH with `forceFormData: true` |
| `products/product-form-fields.tsx` | **Shared domain component** — exports `ProductFormData` type, `ProductCategory` type, and `ProductFormFields` component. Manages image preview state internally (object URL, cleaned up on unmount). Type change clears irrelevant fields and forces `price_type`. SKU auto-uppercased. Price stored as cents, displayed as dollars via local `priceDisplay` string state (normalised to 2dp on blur). Subscription fields (billing interval, trial days) shown only when `type === 'subscription'`; inventory fields shown only when `type === 'physical'`. `remove_image` checkbox shown on edit when `existingImageUrl` is set and no new file is selected; checking it clears the file input and hides the preview. |

#### `cart/` (client-facing)
| File | Description |
|---|---|
| `cart/index.tsx` | Cart page. Two-column layout: line items list (left) + order summary sidebar (right, `lg:w-72`). Each item shows: thumbnail (`object-contain`), name, `ProductTypeBadge`, unit price, qty stepper (−/+), line total, Remove link. Cart error (`errors.cart`) rendered inline above items. "Clear cart" subtle link opens `ConfirmDialog`. "Proceed to Checkout" button POSTs to `checkout.store` via Wayfinder; shows "Redirecting…" while processing; surfaces `errors.checkout` below the button. Empty state shows link back to Shop. |

#### `admin/orders/` (admin-facing)
| File | Description |
|---|---|
| `admin/orders/index.tsx` | Paginated order management table for admins. Uses `DataTable` + `DataTablePagination` + `useDebouncedSearch`. Columns: order number (`font-mono`), client name + email (two-line cell), date, status badge, item count, total. Row click navigates to `admin/orders/show.tsx`. Search filters by order number, client first/last name, or email (server-side `ilike`). Receives `orders: PaginatedData<Order & { items_count: number; user: User | null }>` and `filters: { search? }`. |
| `admin/orders/show.tsx` | Read-only order detail page. Header: order number + status badge + date. Client card (`Card`) showing full name + email, rendered only when `order.user` is present. Line items table (same structure as client `orders/show.tsx`). Back button to admin orders index. |

**Sidebar (Phase 7):** Admin "Orders" nav item added with `ClipboardList` icon, `permission: 'admin'`, positioned between Products and Roles. Imported as `orders as adminOrdersRoute` from `@/routes/admin`.

**Wayfinder imports for admin orders:**
- Index action: `import { index as adminOrdersIndex } from '@/actions/App/Http/Controllers/Admin/OrderController'` — no-arg, `.url()` method
- Show action: `import { show as showAdminOrder } from '@/actions/App/Http/Controllers/Admin/OrderController'` — parametric, `.url` string property
- Route: `import { orders as adminOrdersRoute } from '@/routes/admin'` — `.url()` method for the index URL

#### `orders/` (client-facing)
| File | Description |
|---|---|
| `orders/index.tsx` | Paginated order history table using `DataTable` + `DataTablePagination`. Columns: order number (`font-mono`), date, status `Badge`, item count, total (`tabular-nums`). Row click navigates to `orders/show.tsx`. Status badge config: `pending` → secondary, `paid` → default, `failed` → destructive, `refunded`/`expired` → outline. Receives `orders: PaginatedData<Order & { items_count: number }>`. |
| `orders/show.tsx` | Order detail page. Header: order number in `font-mono`, status badge, date. Line items rendered as a `<table>` with columns: Item (name + SKU), Type, Unit price, Qty, Total. Footer row shows order total. If any item is `product_type === 'subscription'`, shows a link to `/subscriptions`. Back button to orders index. |

#### `subscriptions/` (client-facing)
| File | Description |
|---|---|
| `subscriptions/index.tsx` | Subscription management page. Active and past subscriptions split at render time (active: `status !== 'canceled'`; past: `status === 'canceled'`). Each subscription is a `SubscriptionCard` sub-component. Shows: product name, billing label (from `product.billing_interval` + `billing_interval_count`), status badge, renewal/cancellation/trial dates. "Cancel subscription" subtle button opens `ConfirmDialog` → POSTs to `subscriptions.cancel`. `cancel_at_period_end = true` replaces the cancel button with a "Cancels on [date]" `Badge`. "Manage billing" button POSTs to `billing.portal` and shows "Redirecting…" while loading. Empty state shown when no subscriptions exist. Receives `subscriptions: Subscription[]`. |

**`statusConfig` for `SubscriptionStatus`:** `active` → default, `trialing` → secondary, `past_due`/`unpaid` → destructive, `canceled`/`incomplete_expired`/`paused` → outline, `incomplete` → secondary.

**Sidebar additions (Phase 6):** Orders (`Receipt` icon, `ordersRoute.url()`) and Subscriptions (`RefreshCcw` icon, `subscriptionsRoute.url()`) added to `ALL_NAV_ITEMS` between Cart and Users. No permission gate — visible to all authenticated users. Imported from `@/routes` as `orders as ordersRoute` and `subscriptions as subscriptionsRoute`.

**`SubscriptionStatus` + `Subscription` types** added to `resources/js/types/auth.ts` in Phase 6. See the types file for the full shape.

#### `checkout/` (client-facing)
| File | Description |
|---|---|
| `checkout/success.tsx` | Order confirmation page. Receives `order: Order` prop. Pending state: pulsing `Clock` icon + skeleton rows with "Confirming your payment…". Paid state: `CheckCircle`, order number in `font-mono`, `Badge` with status, item list (name, SKU × qty, line total), grand total row, "View order history" link to `/orders`. `statusConfig` maps `OrderStatus` to badge label + variant. |
| `checkout/cancel.tsx` | Checkout cancelled page. `XCircle` icon, message, "Back to cart" button via `cartRoute.url()`. Cart is untouched. |

**Cart `errors.cart`:** `CartService` throws `CartException` on business rule violations; `CartController` catches it and calls `back()->withErrors(['cart' => $e->getMessage()])`. The cart page surfaces this above the item list.

**Cart count badge in sidebar:** `cartItemCount` is shared via `HandleInertiaRequests` from `session('cart_count', 0)`. `CartService` writes this to the session after every mutation. `NavItem.badge` renders as a muted number on the right of the nav label. Badge is omitted when count is 0 (`badge: cartItemCount || undefined`).

**`prefetch: false` on the Cart nav item:** Inertia prefetches nav links on hover. Because the cart mutates from other pages (Add to Cart POSTs), a prefetched `/cart` would be stale after items are added. The Cart nav item sets `prefetch: false` so clicking it always fetches fresh data. Apply the same to any nav item whose state is frequently mutated by other pages.

**Page-specific type narrowing with `Omit`:** When a controller guarantees a relationship is always loaded (e.g. `items.product` on the cart page), use `Omit` to replace the nullable base type rather than intersecting with `NonNullable`:
```ts
// ✗ Intersection — TypeScript may still see the optional product? from CartItem
type CartPageItem = CartItem & { product: Product };

// ✓ Omit — removes the nullable field entirely before adding the required one
type CartPageItem = Omit<CartItem, 'product'> & { product: Product };
type CartPageCart = Omit<Cart, 'items'> & { items: CartPageItem[] };
```

#### `shop/` (client-facing)
| File | Description |
|---|---|
| `shop/index.tsx` | Product grid for authenticated clients. `auto-fill` responsive grid (`minmax(220px,1fr)`). Category filter (Select, server-side via `?category=slug`) + debounced search — both filters are passed together in every router call so neither drops the other. Product cards: `bg-muted` image container with `object-contain` (no cropping), name, `ProductTypeBadge`, formatted price (subscriptions show `$X/month`). Cards are clickable → `shop/show.tsx`. Uses `DataTablePagination`. Inline debounce instead of `useDebouncedSearch` — see comment in file for why. |
| `shop/show.tsx` | Client-facing product detail. Two-column layout on desktop (image left, details right). `object-contain` image with `bg-muted` background. Shows: name, `ProductTypeBadge`, category, price with billing interval (subscriptions), free trial days, inventory badge ("In stock"/"Out of stock" when `track_inventory = true`), unit count. No edit/delete controls — admin-only. Returns 404 for inactive or soft-deleted products (enforced in controller). |

**Shop image pattern:** All shop image containers use `bg-muted` as a wrapper with `object-contain` on the `<img>` — full image visible, letterbox fills with muted background. Do not use `object-cover` in the shop (clips product images).

**`formatProductPrice` helper:** Defined locally in both `shop/index.tsx` and `shop/show.tsx` — formats cents and appends billing interval for subscriptions (e.g. `$29.99/month`). Uses `intervalLabels` from `@/lib/billing`.

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

**React 19 type convention:** Do not use `React.FormEvent`, `React.ChangeEvent`, etc. — the `React` namespace is deprecated in React 19. Either use inline arrow functions so TypeScript infers the event type from the prop, or import named types directly (`import type { FormEvent } from 'react'`).

**Page conventions:**
- Each page sets a `.layout` property that passes breadcrumbs and titles to the app layout
- Auth pages use `AuthLayout`; all other pages use `AppLayout`
- Breadcrumbs are **always static arrays** on `.layout` — there is no function-based or dynamic breadcrumb pattern. Pages for specific entities (show, edit) use a static label (`'User Details'`, `'Edit User'`), not the entity's name. Do not reach for `setLayoutProps` for breadcrumbs; `setLayoutProps` is only used for auth layout props (`title`, `description`).

---

## React Components (`resources/js/components/`)

### Organization
- **`ui/`** — Generic, unstyled-first primitives (shadcn-style). Not app-specific. Examples: `Button`, `Badge`, `Card`, `Table`, `Dialog`, `Input`, `Select`, `Tooltip`, `Sheet`, `Sidebar`, `Skeleton`, `Sonner`.
- **`components/` root** — App/feature-specific components that know about domain and layout. Examples: `app-sidebar.tsx`, `app-header.tsx`, `nav-main.tsx`, `manage-passkeys.tsx`, `delete-user.tsx`.

### Shared data-table components
| Component | File | Props |
|---|---|---|
| `DataTable` | `data-table.tsx` | `table: Table<TData>`, `emptyMessage?`, `onRowClick?`, `getRowClassName?` |
| `DataTablePagination` | `data-table-pagination.tsx` | `paginatedData: PaginatedData<T>` |

All index pages pass a pre-configured TanStack `table` instance into `DataTable`. `DataTablePagination` owns the `goToPage` logic and the "Showing X–Y of Z" text. Do not inline these patterns in new index pages.

### Shared domain components
| Component | File | Exports |
|---|---|---|
| `ProductTypeBadge` | `product-type-badge.tsx` | `ProductTypeBadge` component + `productTypeConfig` record |
| `TierBadge` | `tier-badge.tsx` | `TierBadge` component + `tierConfig` record |
| `ConfirmDialog` | `confirm-dialog.tsx` | `ConfirmDialog` component |

**`ConfirmDialog` props:** `open`, `onOpenChange`, `title`, `alertTitle?` (default: "Are you sure?"), `description: ReactNode`, `confirmLabel?` (default: "Delete"), `processing?`, `onConfirm`. Use for all destructive confirmations — do not inline the `Dialog + Alert[destructive]` pattern.

**`productTypeConfig` / `tierConfig`:** Import the config objects when you need to destructure label/variant for a single value; import the badge component when rendering a `<Badge>` directly.

### Shared hook
`useDebouncedSearch(serverValue, route, delay?)` — `resources/js/hooks/use-debounced-search.ts`. Returns `[search, setSearch]`. Fires a debounced `router.get` after the user stops typing. Use in every index page that has a search input.

### Key app-shell components
| Component | Role |
|---|---|
| `app-layout.tsx` | Wraps authenticated pages; accepts `breadcrumbs` prop |
| `auth-layout.tsx` | Wraps auth pages; accepts `title` and `description` props |
| `app-shell.tsx` | Root app wrapper |
| `app-sidebar.tsx` | Full sidebar with nav, user menu; nav items filtered by `auth.can[permission]`; currently: Dashboard (no gate), Shop (no gate — all authenticated users), Users (`view_users`), Roles (`admin`), Categories (`view_categories`), Products (`view_products`) |
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
type Tier = 'site_admin' | 'admin' | 'user';

const PRIVILEGED_TIERS: Tier[] = ['site_admin', 'admin'];  // import from @/types, never redefine locally

type Permission = { id, name, display_name, description, created_at, updated_at };

type Role = {   // RBAC role (admin-managed bundle of permissions)
    id: number;
    name: string;
    description: string | null;
    permissions?: Permission[];
    users_count?: number;
    created_at: string;
    updated_at: string;
};

type User = {
    id, first_name, last_name, full_name, email,
    role: Tier,  // coarse access tier
    avatar?, email_verified_at,
    two_factor_enabled?,
    roles?: Role[],  // RBAC roles assigned via role_user pivot
    deleted_at: string | null,
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
    image: string | null; category?: { id: number; name: string } | null;
    deleted_at: string | null; created_at: string; updated_at: string;
};

type Can = {
    view_users, create_user, edit_user, delete_user,
    view_categories, create_category, edit_category, delete_category,
    view_products, create_product, edit_product, delete_product,
    [key: string]: boolean
};  // gates shared via Inertia; auto-derived from permissions table in HandleInertiaRequests

type Auth = { user: User; can: Can };

type CartItem = { id, cart_id, product_id, quantity, product?: Product | null, created_at, updated_at };
type Cart = { id, user_id: number | null, items: CartItem[], created_at, updated_at };

type OrderStatus = 'pending' | 'paid' | 'failed' | 'refunded' | 'expired';

type OrderItem = {
    id: number; order_id: number; product_id: number | null;
    product_name: string; product_sku: string; product_type: string;
    price: number; quantity: number;  // price in cents
    product?: Product | null; created_at: string; updated_at: string;
};

type Order = {
    id: number; order_number: string; user_id: number | null;
    status: OrderStatus; subtotal: number; total: number;  // cents
    stripe_checkout_session_id: string | null; stripe_payment_intent_id: string | null;
    items?: OrderItem[]; user?: User | null; created_at: string; updated_at: string;
};

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

### `lib/billing.ts` — Billing interval labels
```ts
intervalLabels: Record<BillingInterval, string>
// { weekly: 'week', monthly: 'month', yearly: 'year' }
```
Import for displaying billing intervals in subscription-related UI (product show, subscription list, etc.).

`Can` is shared from the server via `HandleInertiaRequests` middleware and reflects which gates pass for the authenticated user. Auto-derived from `Permission::all()` — no manual list to maintain.

### Server-side authorization props (show pages)
Per-model authorization decisions (can this viewer edit/delete THIS specific user?) are computed on the server and passed as Inertia props — never re-derived on the client. The `users/show` page receives `canEdit` and `canDelete` as boolean props. The `Can` type in `auth` only covers global capabilities (can the user edit users at all), not per-record ones.

---

## Confirmation Dialogs

There is **no `AlertDialog`** component in this UI library. Do not try to add or import one. All destructive confirmations use the shared `ConfirmDialog` component from `@/components/confirm-dialog`:

```tsx
<ConfirmDialog
    open={open}
    onOpenChange={setOpen}
    title="Delete Something"
    description={<><strong>{item.name}</strong> will be permanently deleted.</>}
    confirmLabel="Delete"        // optional, defaults to "Delete"
    alertTitle="Are you sure?"   // optional, defaults to "Are you sure?"
    processing={deleting}
    onConfirm={handleDelete}
/>
```

The underlying pattern it wraps — for reference only, do not replicate inline:

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

## File Uploads

When a form includes a file, pass `{ forceFormData: true }` to `post()`:
```tsx
post(storeProduct.url(), { forceFormData: true });
```
Store the file as `File | null` in `useForm` state. Image previews use a local `useState<string | null>` for the object URL — always revoke it in a `useEffect` cleanup to avoid memory leaks:
```tsx
useEffect(() => () => { if (previewUrl) URL.revokeObjectURL(previewUrl); }, [previewUrl]);
```
On the backend, store with `$request->file('image')->store('products', 'public')` and guard with `$request->hasFile('image')` before calling `->store()` when the field is nullable.

## Price Inputs (cents → dollars)

Never use a fully controlled `type="number"` input with a formatted value — it fights the browser cursor and causes digits to increment rather than append. The established pattern:

```tsx
// Local display state — not reformatted on every keystroke
const [priceDisplay, setPriceDisplay] = useState(data.price > 0 ? (data.price / 100).toFixed(2) : '');

<Input
    type="text"
    inputMode="decimal"
    placeholder="0.00"
    value={priceDisplay}
    onChange={(e) => {
        const val = e.target.value;
        if (!/^\d*\.?\d*$/.test(val)) return;  // reject non-numeric
        setPriceDisplay(val);
        setData('price', isNaN(parseFloat(val)) ? 0 : Math.round(parseFloat(val) * 100));
    }}
    onBlur={() => setPriceDisplay(data.price > 0 ? (data.price / 100).toFixed(2) : '')}
/>
```
`type="text"` + `inputMode="decimal"` gives the mobile numeric keyboard without the browser stepping behaviour. Normalise to 2dp only on blur.

---

## Wayfinder URL Patterns

Two distinct call forms exist — mixing them up compiles silently and fails at runtime.

**Regenerating Wayfinder:** Always run `./vendor/bin/sail artisan wayfinder:generate --with-form`. The `--with-form` flag is required because `vite.config.ts` sets `formVariants: true`. Omitting it strips `.form()` from every action file, breaking any page that uses the Inertia `<Form>` component (login, register, settings, etc.).

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
adminProductsRoute.create.url()     // GET /admin/products/create

import { edit as editProduct, update as updateProduct } from '@/actions/App/Http/Controllers/ProductController';

editProduct(product).url            // GET /admin/products/{id}/edit  (string property)
updateProduct(product).url          // PATCH /admin/products/{id}      (string property)

import { show as showProduct, destroy as destroyProduct } from '@/actions/App/Http/Controllers/ProductController';

showProduct(product).url            // GET /admin/products/{id}        (string property)
destroyProduct(product).url         // DELETE /admin/products/{id}     (string property)

import { trash as trashProducts, restore as restoreProduct, forceDestroy as forceDestroyProduct } from '@/actions/App/Http/Controllers/ProductController';

trashProducts.url()                 // GET /admin/products/trash        (no-arg, method on function)
adminProductsRoute.trash.url()      // same, via named route
restoreProduct(product).url         // POST /admin/products/{id}/restore (string property)
forceDestroyProduct(product).url    // DELETE /admin/products/{id}/force (string property)

import { trash as trashUsers, restore as restoreUser, forceDestroy as forceDestroyUser } from '@/actions/App/Http/Controllers/UserController';

trashUsers.url()                    // GET /admin/users/trash           (no-arg, method on function)
adminUsersRoute.trash.url()         // same, via named route
restoreUser(user).url               // POST /admin/users/{id}/restore   (string property)
forceDestroyUser(user).url          // DELETE /admin/users/{id}/force   (string property)

import { edit as editCategory, update as updateCategory, destroy as destroyCategory } from '@/actions/App/Http/Controllers/CategoryController';

editCategory(category).url          // GET /admin/categories/{id}/edit  (string property)
updateCategory(category).url        // PATCH /admin/categories/{id}      (string property)
destroyCategory(category).url       // DELETE /admin/categories/{id}     (string property)

import { roles as adminRolesRoute } from '@/routes/admin';
import { create as rolesCreateRoute } from '@/routes/admin/roles';
import { store as storeRole, edit as editRole, update as updateRole, destroy as destroyRole } from '@/actions/App/Http/Controllers/RoleController';

adminRolesRoute.url()               // GET /admin/roles        (no-arg, method on function)
rolesCreateRoute.url()              // GET /admin/roles/create  (no-arg, method on function)
storeRole.url()                     // POST /admin/roles        (no-arg, method on function)
editRole(role).url                  // GET /admin/roles/{id}/edit  (string property)
updateRole(role).url                // PATCH /admin/roles/{id}     (string property)
destroyRole(role).url               // DELETE /admin/roles/{id}    (string property)
```

Destructuring sub-routes (`show`, `edit`, `create`) directly from `@/routes/admin` yields `undefined` — always access them as properties on the parent route function.

---

## Inertia Shared Data

`HandleInertiaRequests` middleware shares these props on every page:
- `auth.user` — authenticated user (with `full_name`, `role`)
- `auth.can` — gate results (e.g., `view_users`)
- `name` — app name
- `sidebarOpen` — cookie-persisted sidebar state
- `cartItemCount` — integer, read from `session('cart_count', 0)`; updated by `CartService` after every mutation; 0 for guests

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
