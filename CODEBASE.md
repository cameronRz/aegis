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

- **`aegis-models`** — User/Permission models, Role enum, gates, `user_permissions` pivot, database tables, Fortify auth config, form validation
- **`aegis-routes`** — All route definitions with middleware and named route index
- **`aegis-frontend`** — React pages, component catalog, TypeScript types, Inertia shared props, Tailwind design tokens

---

## Keeping Documentation Current

After making any change to the app, update the relevant project skill file if the change affects documented domain knowledge:

- New/modified model, relationship, gate, pivot, or validation rule → update `aegis-models`
- New/modified route, middleware, or controller → update `aegis-routes`
- New/modified page, component, TypeScript type, or shared Inertia prop → update `aegis-frontend`

If a change introduces an entirely new domain area that doesn't fit an existing skill, create a new skill under `.claude/skills/<name>/SKILL.md` and add a one-liner for it in the **Project Skills** section above.

---

## Conventions

- **Named routes always** — use `route('name')` in PHP, Wayfinder functions in TypeScript
- **Breadcrumbs on every page** — set via `.layout` property on Inertia page components
- **Pagination** — use `PaginatedData<T>` type for paginated responses; 15 per page standard
- **Permissions as strings** — permission names are slugs (e.g. `view_users`); add gates in `AppServiceProvider` and a corresponding `permissions` row
- **Pivot audit trail** — `granted_by` on `user_permissions` tracks who granted each permission; follow this pattern for future auditable pivots
