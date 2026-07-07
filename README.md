# Aegis

A B2B admin and client portal for small business owners. Admins manage products, subscriptions, and clients. Clients purchase products, manage subscriptions, chat with support, and interact with an AI assistant trained on company documentation.

**Stack:** Laravel 13 · React 19 · Inertia.js v3 · PostgreSQL · Stripe · OpenAI · Laravel Reverb · Docker (Sail)

---

## Requirements

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- Git

> **No PHP or Composer locally?** See [Bootstrapping without PHP](#bootstrapping-without-php) before step 1.

---

## Setup

### 1. Clone and install dependencies

```bash
git clone <repo-url>
cd aegis
composer install
```

### 2. Configure environment

```bash
cp .env.example .env
```

Make the following changes to `.env`:

**Database** — swap the default SQLite config for Sail's built-in Postgres service:

```env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=aegis
DB_USERNAME=sail
DB_PASSWORD=password
```

**Admin account** — the seeder uses these to create the initial site-admin user:

```env
SITE_ADMIN_FIRST_NAME=Your
SITE_ADMIN_LAST_NAME=Name
SITE_ADMIN_EMAIL=admin@example.com
SITE_ADMIN_PASSWORD=your-password
```

**Stripe** — a [Stripe test-mode account](https://dashboard.stripe.com/test/apikeys) is required. The seeder calls the Stripe API to create customers, so this must be set before seeding:

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

> Leave `STRIPE_WEBHOOK_SECRET` blank for now — you'll get it after setting up the local webhook listener (see [Stripe Webhooks](#stripe-webhooks)).

**OpenAI** — required for the AI assistant:

```env
OPENAI_API_KEY=sk-...
```

### 3. Start Sail

```bash
./vendor/bin/sail up -d
```

> **Shell alias** — add `alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'` to your shell config (`~/.zshrc`, `~/.bashrc`, etc.) to type `sail` instead of `./vendor/bin/sail`. The rest of this guide assumes the alias is set.

### 4. Generate the app key

```bash
sail artisan key:generate
```

### 5. Migrate and seed

```bash
sail artisan migrate --seed
```

The seeder creates ~130 users, 9 product categories, 23 sample products, 3 subscription plans, and Stripe customer records for named test accounts.

### 6. Fix the storage symlink

The storage link must be relative so it resolves correctly both on your host and inside the Docker container. Run this once:

```bash
rm -f public/storage && ln -s ../storage/app/public public/storage
```

> Never run `php artisan storage:link` directly — it writes an absolute macOS path that breaks inside the container.

### 7. Install frontend dependencies and build assets

```bash
sail npm install
sail npm run build
```

---

## Running Locally

Start all services (web server, queue worker, Vite, Reverb, log tail) with:

```bash
sail composer run dev
```

The app is available at **http://localhost**.

| Service | URL |
|---|---|
| App | http://localhost |
| Mailpit (email) | http://localhost:8025 |
| Reverb (WebSockets) | ws://localhost:8080 |
| Postgres | localhost:5432 |

All outgoing email is intercepted by Mailpit in local development — nothing is sent to real addresses.

---

## Stripe Webhooks

To receive webhook events locally, install the [Stripe CLI](https://stripe.com/docs/stripe-cli) and run:

```bash
stripe listen --forward-to localhost/webhooks/stripe
```

Copy the `whsec_...` signing secret it prints into your `.env` as `STRIPE_WEBHOOK_SECRET`, then restart the app.

---

## Seed Accounts

After seeding, these accounts are ready to use (password: **`password`** for all):

| Role | Email |
|---|---|
| Site Admin | *(your `SITE_ADMIN_EMAIL`)* |
| Admin | dora@email.com |
| Client | benny@email.com |

---

## Testing

```bash
sail test
```

Filter to a specific test or file:

```bash
sail test --filter=SomeTestName
```

---

## Bootstrapping without PHP

If you don't have PHP and Composer installed locally, use Docker to install vendor dependencies before Sail is available:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php85-composer:latest \
    composer install --ignore-platform-reqs
```

Then continue from [step 2](#2-configure-environment).
