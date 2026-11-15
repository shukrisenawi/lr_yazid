# Yazid Collective

A Laravel 13 + Inertia 3 + Vue 3 / TypeScript application, built incrementally
against [PROJECT_REQUIREMENTS.md](PROJECT_REQUIREMENTS.md).

**Current scope: foundation, catalog, shopping, payment integration, VIP lifecycle,
programs and appointment booking/approval/rescheduling.**
CMS/reporting and complete order/customer administration remain unfinished. Payment logic
has automated tests with mocked provider responses; real ToyyibPay sandbox
acceptance remains blocked on credentials and a public HTTPS callback URL.
This is not production-ready. See [implementation status](docs/IMPLEMENTATION_STATUS.md).

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
composer test
composer lint:check
composer types:check
npm run check
npm run types:check
npm test
npm run build
```

Most tests use isolated SQLite. Opt-in concurrent checkout/payment/program/appointment tests create
and drop uniquely named disposable MySQL databases, never the application database.
The database user needs CREATE/DROP DATABASE privileges:

```powershell
$env:MYSQL_CONCURRENCY_TEST='1'
php vendor/phpunit/phpunit/phpunit --filter=MySql --testdox
```

Run the frontend build before backend tests, not simultaneously: the starter kit
font directive reads the generated font manifest.

## Shopping, payments and membership

Customers use `/cart`, `/checkout`, `/addresses`, `/orders`, `/membership`
and `/notifications`. Administrators configure `/admin/shipping`,
`/admin/payment-settings`, `/admin/membership-plans` and `/admin/memberships`.
Prices are server-calculated; checkout reserves physical stock and snapshots
membership duration/prices. Verified payments settle inventory and membership
once. Browser returns cannot mark an order paid.

Payment collection defaults disabled. Use genuine sandbox credentials and a public
HTTPS `APP_URL` before enabling sandbox collection. Secrets are encrypted using
`APP_KEY`; preserve that key securely. Ambiguous bill-creation failures and
late/additional payments require review, not blind retry or manual activation.
No live payments have been tested.

Run the scheduler every minute and supervise the database queue worker:

```sh
php artisan schedule:work
php artisan queue:work database --tries=3
```

Production should use cron `schedule:run` instead of `schedule:work`. Scheduled
tasks expire reservations, reconcile payments and process VIP expiry/reminders.
Membership notices use database/mail channels; configure SMTP before launch.

## VIP programs

Administrators manage programs at `/admin/programs`: sanitized rich text,
optimized images, registration windows, capacity, publication/cancellation,
participant filtering, CSV export and attendance. Active VIPs browse
`/vip/programs`; `/my-programs` preserves owner-scoped history after expiry.
Membership must cover the entire event. Program locks serialize registration;
duplicate submissions do not consume another place or repeat confirmation.
Schedule/location changes are blocked after registration: cancel the event and
create a replacement. Pending queue jobs announce publications and deliver notices
with database/mail delivery tracking. Email uses the configured mailer; local
acceptance used the log driver, not external SMTP delivery.

## Appointment scheduling

Administrators use `/admin/appointment-services` to manage services, booking-policy
defaults, manual slots, blocked dates and automatic generation. Preview is read-only.
Generation skips breaks, blocked/non-working dates and overlapping existing slots;
repeating a run does not create duplicates. Runs are limited to 93 days and 5,000
new slots, with configuration/count history retained.

Active bookings and pending reschedule requests protect their slots against silent
schedule changes; approved appointments also prevent date blocking. Any booking,
reschedule or status history prevents slot deletion. Unblocking a date does not
automatically re-enable its disabled slots.

Active VIPs book through `/vip/appointments/book`. All requests start as Pending
Approval and do not reserve capacity. `/appointments` retains owner-scoped history
after membership expiry. Administrators use `/admin/appointments` and
`/admin/appointments/calendar` (month/week/day/list) to review requests, propose
alternatives, approve/reject/cancel and mark completed/no-show.

Approval rechecks current membership through the appointment end, availability,
capacity and overlapping active requests across services. Same-service rescheduling
keeps the original approval until an atomic move is approved. Administrator
alternatives require customer consent followed by administrator approval.
Schedule/location snapshots and timestamped actor/status history are retained.

`appointments:send-reminders` runs every five minutes for deduplicated 24-hour and
1-hour notices. Database/mail delivery is tracked; stale reminders are suppressed
after cancellation or a change of appointment time. Configure SMTP and supervise
workers before launch. The database queue must share the business database
connection for transactional enqueueing. External SMTP delivery remains unverified.

## Customer directory

Administrators browse `/admin/customers` with search and membership filters.
Profiles provide paginated order, membership-payment, program and appointment
history, scoped to the selected customer. This is read-only administration:
credentials and administrator accounts are not exposed, and payments/roles
cannot be modified here.

## Order fulfillment

Administrators use `/admin/orders` to search orders, view purchased snapshots and
advance verified paid physical orders through processing, packed, shipped and
delivered. Courier/tracking are required before shipping. Each change requires a
reason and records an audit. Unpaid, membership-only or review-flagged orders are
blocked; fulfillment never marks payments paid or modifies stock. Customer order
pages show tracking. Shipped, tracking-correction and delivered notices are queued
once per actual fulfillment change and track database/mail delivery. Email delivery
is at-least-once; external SMTP acceptance remains outstanding.

## Website content

Manage content at `/admin/content-pages`: About Us, Contact Us, policies and
membership information can be created without source-code changes. The shared rich
text editor supports formatted content/images; HTML is sanitized server-side.
Set SEO title/description, publication status and optional footer visibility.
Only published pages appear at `/pages/{slug}`; drafts return 404, including to admins.
Every changed save records an administrator-attributed revision. Stale form versions
are rejected rather than overwriting newer content. Review actual business/legal
details before publishing policies.

Manage ordered, scheduled homepage banners at `/admin/banners`. Publishing requires
an image and accessible image description. Banner destinations are internal paths
only; up to six currently published banners appear in order with manual controls,
never autoplay. Start is inclusive and end is exclusive in Asia/Kuala_Lumpur.
With no eligible banners the default homepage is retained.

At `/admin/website-settings`, configure the website name, logo, favicon, contact
details, social HTTPS links and footer. Logos/favicons/banner images accept bounded
JPEG/PNG/WebP uploads and are re-encoded to WebP. These public settings do not alter
invoice seller snapshots, payment credentials, currency, email sender or `APP_URL`.
Website name and favicon update across navigation; the logo is used on public,
account and authentication screens.

Settings/banner writes use locked version checks, reject stale forms and retain
administrator-attributed snapshots in `website_changes`. Identical saves do not
create extra revisions. Old images remain available for audit history; failed
database writes remove only their newly uploaded assets. Do not purge retained
website assets without a separately reviewed retention policy.

## Invoices and exports

Configure real seller details at `/admin/invoice-settings` before enabling invoice
issuance. Admins issue one invoice per verified paid MYR order from its details page.
Invoices freeze seller, buyer, item, totals and payment references; later changes
cannot rewrite an issued document. Customers and admins can open `/invoices/{id}`
and use Print / Save PDF. These are commercial invoices, **not tax invoices or
LHDN-validated e-Invoices**. No tax or regulatory compliance is implied.

Export CSV from the order list after applying search, payment, fulfillment and
date filters. Exports are capped at 10,000 orders, generated in batches and protected
against spreadsheet formula injection. Refund/cancellation workflows remain unfinished.

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
