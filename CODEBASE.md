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
| DB | PostgreSQL via pgvector/pgvector:pg18 (Docker via Sail) |

The app is a classic Inertia SPA: Laravel handles routing, auth, and data; React renders the UI with no full-page reloads. Pages live in `resources/js/pages/`. Wayfinder auto-generates typed functions from Laravel controllers/routes — import from `@/actions/` (controllers) or `@/routes/` (named routes).

---

## Development Environment (Sail)

The app runs in Docker via Laravel Sail. All commands must be prefixed with `./vendor/bin/sail` (or `sail` if you've configured the shell alias).

| Instead of | Use |
|---|---|
| `php artisan <cmd>` | `./vendor/bin/sail artisan <cmd>` |
| `composer <cmd>` | `./vendor/bin/sail composer <cmd>` |
| `npm <cmd>` | `./vendor/bin/sail npm <cmd>` |
| `php artisan tinker` | `./vendor/bin/sail tinker` |
| `php artisan test` | `./vendor/bin/sail test` |
| `vendor/bin/pint` | `./vendor/bin/sail php vendor/bin/pint` |

**Starting / stopping:**
```bash
./vendor/bin/sail up -d   # start in background
./vendor/bin/sail stop    # stop containers
./vendor/bin/sail shell   # bash session inside the container
```

**Shell alias (recommended):** Add `alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'` to `~/.zshrc` or `~/.bashrc` so you can type `sail` instead of `./vendor/bin/sail`.

**NEVER use the shadcn CLI** (`npx shadcn@latest ...`) — it always invokes pnpm internally, which creates a `.pnpm-store` directory (28k+ files, ~300MB) and a `pnpm-lock.yaml` inside the project. To add a shadcn component: copy the source directly from `ui.shadcn.com` and save it to `resources/js/components/ui/<name>.tsx`. Most components need no new npm packages since the required Radix primitives are already in `package.json`. If a new `@radix-ui/*` package is needed, install it with `./vendor/bin/sail npm install @radix-ui/react-<name>`. If `.pnpm-store` or `pnpm-lock.yaml` ever appear, delete them immediately with `rm -rf .pnpm-store pnpm-lock.yaml`.

**Mailpit** — intercepts all outgoing email during local development. UI available at [http://localhost:8025](http://localhost:8025). Configured in `.env` with `MAIL_HOST=mailpit`, `MAIL_PORT=1025`, `MAIL_ENCRYPTION=null`.

**pgvector** — the PostgreSQL image is `pgvector/pgvector:pg18`, which bundles the `vector` extension for future RAG/semantic search. The extension is enabled via `0001_01_01_000003_enable_pgvector_extension.php`, which runs before all application table migrations so any future table can add a `vector` column.

**Storage symlink** — `public/storage` must be a **relative** symlink (`../storage/app/public`). Never run `php artisan storage:link` directly on the host — it writes an absolute macOS path that the Sail container cannot resolve, causing 403s on all uploaded files. If the symlink is broken, fix it with:
```bash
rm public/storage && ln -s ../storage/app/public public/storage
```

**Reseeding and Stripe test data** — every `migrate:fresh --seed` creates new Stripe objects (customers, products, prices) without deleting the old ones. There is no meaningful rate limit concern (154 API calls per reseed is well under Stripe's 100 req/sec limit). The real consequence is **accumulating orphaned test objects** in the Stripe sandbox — old products, prices, and customers that the app no longer knows about. A few reseeds a day is fine; clean up the Stripe test dashboard periodically if it gets cluttered.

---

## Project Skills

Detailed domain knowledge is in project skills — activate them when working in that area:

- **`aegis-plan`** — Plan orientation and phase kick-off. `/aegis-plan` summarises MVP progress and current step. `/aegis-plan <N>` analyses step N of the active plan, asks implementation questions, then implements.
- **`aegis-models`** — User/Permission/Role/Category models, Tier and PermissionName enums, Sortable trait, gates, `role_user`/`role_permissions` pivots, database tables, Fortify auth config, form validation
- **`aegis-routes`** — All route definitions with middleware and named route index
- **`aegis-frontend`** — React pages, component catalog, TypeScript types, Inertia shared props, Tailwind design tokens

## Active Planning

See **`.claude/planning/PLANS.md`** for the full index, current focus, and sequence. Each plan file has a 4-line header (Status / Current step / Depends on / Summary) for fast orientation.

| File | Summary |
|---|---|
| `PLANS.md` | Master index — start here each session |
| `TEMPLATE.md` | Copy this when starting a new plan |
| `03-cart-checkout.md` | Cart/checkout/webhooks/orders/subscriptions (Phases 5–7) — current focus |
| `completed/` | Finished plans kept for reference |

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

- **`ILIKE` not `LIKE` for search** — PostgreSQL's `LIKE` is case-sensitive (unlike SQLite). All user-facing search queries must use `ilike` so searching "wireless" matches "Wireless Mouse". This applies to every `where('column', 'like', ...)` — always use `'ilike'` instead.
- **Named routes always** — use `route('name')` in PHP, Wayfinder functions in TypeScript
- **Breadcrumbs on every page** — set via `.layout` property on Inertia page components
- **Pagination** — use `PaginatedData<T>` type for paginated responses; 15 per page standard
- **Permissions via `PermissionName` enum** — permission names are cases on `App\Enum\PermissionName` (e.g. `PermissionName::EditUser`). Adding a new permission requires: a new enum case, a gate in `AppServiceProvider` (auto-registered via `PermissionName::cases()` loop), and a corresponding row in `PermissionSeeder`. The `->value` is the slug stored in the `permissions` table `name` column.
- **Pivot audit trail** — `assigned_by` on `role_user` tracks who assigned each role; follow this pattern for future auditable pivots
- **Pivot table naming vs entity table naming** — Laravel's alphabetical pivot convention (`role_user`, `cart_product`) applies only to `belongsToMany` intermediate tables with no dedicated model. If the join record has its own model, its own `id`, domain-specific columns, or is referenced in routes, name the table after the model instead (`cart_items`, `order_items`). The test: is it a `belongsToMany` (pivot) or a `hasMany`/`belongsTo` chain (entity)?
- **Index pages** — use `DataTable` + `DataTablePagination` components and the `useDebouncedSearch` hook; do not inline the TanStack table rendering, pagination buttons, or debounce logic
- **Destructive confirmations** — always use `ConfirmDialog` from `@/components/confirm-dialog`; do not inline the `Dialog + Alert[destructive]` pattern
- **Tier assignment logic** — call `$user->assignableTiers()` (returns `Tier[]`); never re-derive inline. Site admins get all three tiers; everyone else gets only `[Tier::User]`.
- **Role assignment — no privilege escalation** — call `$user->canAssignRole(Role $role): bool` to check before assigning any role. A non-admin may only assign roles whose permissions are a subset of their own. Enforce this at three layers: (1) filter the roles list passed to the view (create/edit), (2) validate in the form request `after()` hook (`ValidatesAssignableRoles` trait), (3) in the controller, preserve existing roles the actor cannot assign when syncing (use `sync()` with a merged payload, not `syncWithPivotValues()`).
- **File upload MIME validation** — always pair `mimes:ext` with `mimetypes:mime/type`. `mimes` checks only the file extension; `mimetypes` inspects the actual file content. Using only `mimes` allows extension spoofing.
- **Webhook idempotency** — wrap Stripe webhook state changes in `DB::transaction()` with `lockForUpdate()` on the relevant record; re-check status inside the transaction. Never check order status before the lock — that is a TOCTOU race.
- **Product search** — use `->search($request->input('search'))` scope on the `Product` query builder
- **Narrowing nullable relationship types** — when a controller guarantees a relationship is always loaded, use `Omit` to replace the base nullable field rather than intersecting: `type PageItem = Omit<CartItem, 'product'> & { product: Product }`. Intersection leaves the optional `?` in play; `Omit` removes it entirely.
