# Aegis — Codebase Reference

## Application Overview

Aegis is a B2B admin and client portal for small business owners. Admins list products and subscriptions for their clients to purchase and manage. Clients can make purchases, manage subscriptions, chat with customer support, and interact with a context-aware AI assistant trained on company documentation uploaded by admins.

**Two primary user groups:**
- **Admins/Managers** — business owners and staff who manage clients, products, subscriptions, and support
- **Clients (end users)** — customers of the business who buy, subscribe, and communicate

---

## Architecture

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.5 |
| Frontend | React 19 + TypeScript, via Inertia.js v3 |
| Auth | Laravel Fortify v1 (passkeys, 2FA, email verification) |
| Routing | Named routes + Wayfinder for typed TS route functions |
| Styling | Tailwind CSS v4 (OKLch design tokens, dark mode) |
| Testing | Pest v4 |
| DB | MySQL/SQLite per env |

The app is a classic Inertia SPA: Laravel handles routing, auth, and data; React renders the UI with no full-page reloads. Pages live in `resources/js/pages/`. Wayfinder auto-generates typed functions from Laravel controllers/routes — import from `@/actions/` (controllers) or `@/routes/` (named routes).

---

## Project Skills

Detailed domain knowledge is in project skills — activate them when working in that area:

- **`aegis-models`** — User/Permission/Category models, Role and PermissionName enums, Sortable trait, gates, `user_permissions` pivot, database tables, Fortify auth config, form validation
- **`aegis-routes`** — All route definitions with middleware and named route index
- **`aegis-frontend`** — React pages, component catalog, TypeScript types, Inertia shared props, Tailwind design tokens

## Active Planning

- **`.claude/planning/cart-checkout-plan.md`** — Cart, checkout, and subscription implementation (Phases 1–6). Check the "Currently on" line at the top to find current position. Run the Phase Exit Checklist before moving between phases.
- **`.claude/planning/phase-one-polishing.md`** — Completed refactoring reference. Describes shared components and abstractions introduced during the polishing pass.

---

## Keeping Documentation Current

After completing a phase or feature, update the relevant skill file as a final step — not mid-implementation:

- New/modified model, relationship, gate, pivot, or validation rule → update `aegis-models`
- New/modified route, middleware, or controller → update `aegis-routes`
- New/modified page, component, TypeScript type, or shared Inertia prop → update `aegis-frontend`

If a change introduces an entirely new domain area that doesn't fit an existing skill, create a new skill under `.claude/skills/<name>/SKILL.md` and add a one-liner for it in the **Project Skills** section above.

---

## Collaboration Preferences

- **Ask clarifying questions before implementing** — for any non-trivial change, ask targeted questions with recommended options (pre-selected defaults) before writing code. Surface name collisions, backwards-compatibility trade-offs, and scope decisions upfront.
- **Q&A filter** — if a decision can be reversed in under an hour, just make a call. Ask first when the decision touches the DB schema, a public API surface, or a naming convention that spreads across many files.
- **Update at sub-section boundaries, not mid-file** — after each numbered step (e.g. 2.3 CartService) is fully working: run Pint, run tests, check off that box in the plan, and update the "Currently on" line to the next step. This keeps context recoverable across sessions without interrupting implementation flow mid-task. Do a full skills update when the entire phase is done.
- **Definition of done** — before starting any phase, confirm what "done" means: which tests must pass, which routes need Wayfinder regeneration, which skills need updating. Use the exit checklist in the active plan file.

---

## Conventions

- **Named routes always** — use `route('name')` in PHP, Wayfinder functions in TypeScript
- **Breadcrumbs on every page** — set via `.layout` property on Inertia page components
- **Pagination** — use `PaginatedData<T>` type for paginated responses; 15 per page standard
- **Permissions via `PermissionName` enum** — permission names are cases on `App\Enum\PermissionName` (e.g. `PermissionName::EditUser`). Adding a new permission requires: a new enum case, a gate in `AppServiceProvider` (auto-registered via `PermissionName::cases()` loop), and a corresponding row in `PermissionSeeder`. The `->value` is the slug stored in the `permissions` table `name` column.
- **Pivot audit trail** — `granted_by` on `user_permissions` tracks who granted each permission; follow this pattern for future auditable pivots
- **Pivot table naming vs entity table naming** — Laravel's alphabetical pivot convention (`role_user`, `cart_product`) applies only to `belongsToMany` intermediate tables with no dedicated model. If the join record has its own model, its own `id`, domain-specific columns, or is referenced in routes, name the table after the model instead (`cart_items`, `order_items`). The test: is it a `belongsToMany` (pivot) or a `hasMany`/`belongsTo` chain (entity)?
- **Index pages** — use `DataTable` + `DataTablePagination` components and the `useDebouncedSearch` hook; do not inline the TanStack table rendering, pagination buttons, or debounce logic
- **Destructive confirmations** — always use `ConfirmDialog` from `@/components/confirm-dialog`; do not inline the `Dialog + Alert[destructive]` pattern
- **Role assignment logic** — call `$user->assignableRoles()` (returns `Role[]`); never re-derive inline
- **Product search** — use `->search($request->input('search'))` scope on the `Product` query builder
- **Narrowing nullable relationship types** — when a controller guarantees a relationship is always loaded, use `Omit` to replace the base nullable field rather than intersecting: `type PageItem = Omit<CartItem, 'product'> & { product: Product }`. Intersection leaves the optional `?` in play; `Omit` removes it entirely.
