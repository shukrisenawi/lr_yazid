# Yazid Collective

A Laravel 13 + Inertia 3 + Vue 3 / TypeScript application, built incrementally
against [PROJECT_REQUIREMENTS.md](PROJECT_REQUIREMENTS.md).

**Current scope: Phase 1 foundation and Phase 2 catalog implementation.**
This is not a completed commerce system. Cart, checkout, ToyyibPay, paid membership
activation, programs, appointments and CMS/reporting are not implemented yet.
No payments are simulated. See [implementation status](docs/IMPLEMENTATION_STATUS.md).

## Requirements

- PHP 8.3+ with GD/WebP, PDO MySQL, PDO SQLite (tests), mbstring, OpenSSL, XML and ctype.
- Composer 2, Node.js 22.12+ and npm.
- MySQL-compatible server (the local XAMPP MariaDB server is supported).

## Installation

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Create an empty `lr_yazid` database with utf8mb4 encoding. Set database
credentials and `APP_URL` in `.env`, then:

```sh
php artisan migrate
php artisan storage:link
npm install
npm run build
php artisan app:create-admin
php artisan serve
```

Open http://localhost:8000. Use `npm run dev` for asset development.
The administrator command prompts for a unique email and a hidden strong
password. It does not overwrite existing accounts. Never commit `.env`.
Registration always creates customers, regardless of submitted role fields.

## Authentication

The official starter kit provides Fortify login, logout, email verification,
password reset, profile editing, password changes and two-factor authentication.
Register using a Malaysian phone number (normalized to E.164).
New accounts must verify email before accessing dashboards.
`MAIL_MAILER=log` writes local verification/reset messages to
`storage/logs/laravel.log`; configure SMTP for real email delivery.

Routes: `/`, `/register`, `/login`, `/forgot-password`, `/dashboard`,
`/settings/profile`, `/settings/security`, `/admin/dashboard`, `/vip/dashboard`.
VIP access is denied unless a valid membership record exists; being an
administrator alone does not grant VIP access. No public membership-granting
endpoint exists.

## Product catalog

Public browsing: `/products`, `/products/{slug}`.
Administrator management: `/admin/products`, `/admin/categories`.
Features include search/category/price filters, sorting, pagination, sale windows,
inventory editing, archiving, duplication, optional variants, multiple image uploads,
gallery reordering, primary image selection and a Tiptap editor.
HTML is sanitized on the server. Uploads are validated and converted to WebP;
uploaded files live under `storage/app/public`, not in Git.

For clearly labeled local sample products only:

```sh
php artisan db:seed --class=CatalogSeeder
```

This seeder does nothing outside local/testing environments and creates no
administrator accounts. Prices are entered as integer sen (8900 = RM 89.00).
Sale times use Asia/Kuala_Lumpur. Archiving never deletes product records.

## Verification

```sh
php artisan test
composer lint:check
composer types:check
npm run check
npm run types:check
npm run build
```

Tests use an isolated SQLite in-memory database, never the application's MySQL
database. Migrations are also verified against local MySQL/MariaDB.

## Deployment foundation

Serve only `public/`, enable HTTPS, disable `APP_DEBUG`, set `APP_ENV=production`,
use secure database credentials and `SESSION_SECURE_COOKIE=true`.
Configure SMTP, backups and a supervised queue worker before enabling queued
business notifications. Run `php artisan migrate --force`, `npm ci`,
`npm run build`, and `php artisan optimize` during deployment.
Do not deploy as a production store until later phases and sandbox payment
acceptance tests are complete.

## Design and planning

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
The homepage photograph is downloaded locally from Unsplash:
https://unsplash.com/photos/turned-on-pendant-lamp-f10nL_6OHB8
(source image `photo-1497366754035-f200968a6e72`).
Sample brand/copy must be reviewed before launch.
Additional sample photography comes from Unsplash source IDs:
`photo-1602143407151-7111542de6e8`, `photo-1553062407-98eeb64c6a62`,
and `photo-1531346878377-a5be20888e57`. Images are illustrative, not verified
commercial product photographs.
