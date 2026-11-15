# Yazid Collective

A Laravel 13 + Inertia 3 + Vue 3 / TypeScript application, built incrementally
against [PROJECT_REQUIREMENTS.md](PROJECT_REQUIREMENTS.md).

**Current scope: foundation, catalog, shopping, payment integration, VIP lifecycle,
programs, appointment workflows, fulfillment/invoices, CMS, core reports and commerce notices.**
Production hardening and deployment acceptance remain unfinished. Payment logic
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

Most tests use isolated SQLite. Opt-in checkout/payment/program/appointment concurrency
and financial reporting checks create
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

### ToyyibPay configuration in Settings

Administrators open **Website settings → ToyyibPay settings**, or use the direct
sidebar entry at `/admin/payment-settings`. Configure Sandbox and Production
Secret Keys / Category Codes separately, choose the active environment and enable
or disable collection. No source-code or `.env` credential edits are required.

After the first save, encrypted database credentials take precedence over environment
defaults. A blank Secret Key preserves the existing value; enter a replacement to
rotate it or use the explicit removal checkbox. Removing the selected key requires
collection to be disabled. Secret values never return to the browser or validation
old-input sessions, and audits record metadata/availability only, not key values.
Concurrent stale edits are rejected rather than overwriting another administrator.
Existing payment attempts retain their original encrypted credential snapshot.

Credentials may be saved while collection is disabled. Enabling requires credentials
for the selected environment and a public HTTPS `APP_URL` configured on the server.
Callback/return URLs are displayed automatically; the URL screen is not an external
reachability check. Saving settings does not contact ToyyibPay, create a bill, verify
credentials with the provider or take payment. Complete genuine sandbox acceptance
before enabling Production.

Run the scheduler every minute and supervise the database queue worker:

```sh
php artisan schedule:work
php artisan queue:work database --queue=commerce-notices,default --tries=3
```

Production should use cron `schedule:run` instead of `schedule:work`. Scheduled
tasks expire reservations, reconcile payments and process VIP expiry/reminders.
Membership notices use database/mail channels; configure server PHP mail before launch.

### PHP mail delivery (no application SMTP credentials)

Set `MAIL_MAILER=mail` and a real `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` on the
deployment server. The application calls PHP `mail()` directly; it does not read
`MAIL_HOST`, `MAIL_USERNAME` or `MAIL_PASSWORD` for this transport.
Configure the delivery agent in server `php.ini`: Windows uses `SMTP`/`smtp_port`
or a configured `sendmail_path`; Unix normally requires a working `sendmail_path`.
Configure both web PHP and queue-worker/CLI PHP, clear/rebuild Laravel's config
cache and restart workers after deployment.

The application's current notices use To recipients. This transport deliberately
rejects Bcc, To-less messages, raw MIME and custom recipient envelopes rather than
risk silently redirecting or exposing recipients. Multipart HTML/text and MIME
attachments are preserved. A failed `mail()` hand-off throws so queued notices
can retry; a successful return means server hand-off only, not inbox delivery.

Development `.env.example` intentionally keeps `MAIL_MAILER=log` to prevent QA
mail leaving the machine. Local PHP currently has `SMTP=localhost`, port 25 and
no sendmail path; the availability of the `mail()` function alone does not prove
that a working mail service exists. Real recipient delivery still requires
deployment acceptance; no application SMTP account is required.

## Unpaid order cancellation

Administrators cancel eligible unpaid orders from `/admin/orders/{order}` with a
required 5–1,000 character reason and explicit confirmation. The reason is visible
to the customer. Paid/refunded/cancelled orders, payment review cases, paid attempts
and orders with fulfillment progress cannot use this action.

Owner/order/payment locks serialize cancellation against verified settlement.
Active reservations are released without increasing physical stock. Snapshots,
payment attempts and history remain intact. A unique cancellation record stores
source (`admin`, `customer` or `expiry`), actor, previous statuses, reason and time.
Existing historical cancellations keep their original reason; no actors are fabricated.

Cancellation does not void an existing ToyyibPay bill or issue a refund. A later
verified payment is recorded as received but requires review; fulfillment stays
cancelled, inventory is not consumed and membership is not activated.

## Manual refund records

Admins record already-completed external refunds from the order details page.
This is an append-only ledger, **not a ToyyibPay refund API**. The application
does not transfer money, void bills, alter original payment/invoice records,
return stock, cancel fulfillment or revoke VIP; handle those decisions separately.
It also does not create a credit note or tax/e-Invoice adjustment.

Choose a matching server-verified paid MYR payment, enter an exact decimal MYR
amount, external receipt/reference, method, completed time in Malaysia and an
admin-only reason, then acknowledge external completion and confirm. Server checks
reject future/pre-payment dates and cap cumulative records per payment, not per
order (additional actual paid attempts have independent refundable balances).
Paid review-flagged attempts are supported, but the review flag is never cleared.

Submission UUIDs are retry-idempotent only for identical data and the same actor.
References are normalized to uppercase and unique within each payment environment;
use an institution prefix where needed to disambiguate external references.
Owner/order/payment and refund locking plus unique constraints prevent duplicate
or excess records under concurrent requests. Records retain actor, original
payment, environment, reason and both refund/recording timestamps. No edit/delete
or correction endpoint is provided; investigate erroneous records through an
audited maintenance process rather than creating fictitious offset refunds.

Customers see only their own reference, amount, environment and refund date,
not external bank references or private admin audit notes. Sandbox entries are
explicitly test records. Manual entries do not prove receipt in a customer account.

Reports/aggregate CSV show manual refund amount/count separately from gross
verified collections, by refund-completion date and environment. Gross sales are
not netted or rewritten. Refunds against reviewed payments can be included in
the manual ledger even when those payments are excluded from gross collections.

## Commerce notifications

Verified-account welcome, order creation, provider-verified payment success and
verified unsuccessful payment attempts use database/in-app and mail channels.
Welcome is triggered by email verification, not by an unverified signup.
No automatic backfill of past account/payment events is performed.
Order confirmation explicitly confirms creation only; it is not a paid receipt.
Sandbox payment notices are labeled as tests.

Commerce jobs use the `commerce-notices` queue. Local `artisan dev` includes this
queue alongside `default`; production workers must also listen to both (see above).
Restart existing workers after updating their queue list during deployment.
The database queue must use the same Laravel DB connection as business writes:
leave `DB_QUEUE_CONNECTION` unset or match the business `DB_CONNECTION`. A different
connection is rejected rather than compromising transactional queueing.

Order/payment notice records and queue inserts commit or roll back with their
business transaction. Owner/event uniqueness prevents repeated checkout/callback
enqueueing, and stable notification UUIDs protect in-app records on retry. Processed
channel markers reduce repeats, but external mail still has at-least-once semantics;
there is no exactly-once recipient-inbox guarantee.
Queued failures are suppressed if payment state changes or a newer attempt starts.
Mail is suppressed if its original verified recipient email changes or becomes
unverified. Suppression reasons remain available for audit.

Admins use `/admin/commerce-notices` to filter by customer/order, event and channel
state. This is read-only, without resend/payment-mutation controls or exposed secret
snapshots. Awaiting processing can include failed jobs; inspect worker/failed-job
logs. A mail processed timestamp includes acceptance by the local log transport and
does not establish external delivery. Users read their own notices at
`/notifications`. Genuine provider/server-mail acceptance remains outstanding.

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
after cancellation or a change of appointment time. Configure server PHP mail and supervise
workers before launch. The database queue must share the business database
connection for transactional enqueueing. External mail delivery remains unverified.

## Customer directory

Administrators browse `/admin/customers` with search and membership filters.
Profiles provide paginated order, membership-payment, program and appointment
history, scoped to the selected customer. History remains read-only; credentials
and administrator accounts are not exposed, and payments/roles cannot be modified here.

Admins may correct a customer's current name and Malaysian phone with a required
reason and explicit confirmation. An independently paginated, admin-only contact
audit records old/new values, actor, profile version and time. Email/login,
password, verification, role, VIP access and addresses are not editable through
this action. Existing commercial and booking snapshots are never rewritten.

Admin corrections and customer self-service profile updates use the same row lock
and contact profile version. Stale forms are rejected even when the competing edit
came from the other interface. Reload to review the new details; a conflict does
not silently replace the form's expected version or discard the unsaved draft.
No-op contact saves do not advance versions or create audit noise. Actual Eloquent
name/email/phone changes advance the version; unrelated security/verification
updates do not. Custom maintenance scripts must preserve this concurrency policy.

Contact history lists administrator corrections only, not customer self-service
events. Existing financial/appointment deletion guards remain. For nonfinancial
accounts still eligible for owner deletion, contact-only audit rows cascade with
the deleted account instead of blocking the existing deletion/privacy workflow.

## Order fulfillment

Administrators use `/admin/orders` to search orders, view purchased snapshots and
advance verified paid physical orders through processing, packed, shipped and
delivered. Courier/tracking are required before shipping. Each change requires a
reason and records an audit. Unpaid, membership-only or review-flagged orders are
blocked; fulfillment never marks payments paid or modifies stock. Customer order
pages show tracking. Shipped, tracking-correction and delivered notices are queued
once per actual fulfillment change and track database/mail delivery. Email delivery
is at-least-once; external mail acceptance remains outstanding.

## Administrator dashboards and reports

Use `/admin/dashboard` or `/admin/reports` with inclusive MYT date filters (maximum
366 days) and a Production/Sandbox payment selector. Production is the default.
Financial summaries group provider-checked payments by payment date, require one
amount-matched paid MYR transaction per paid order and exclude review flags,
duplicates, pending/failed/refunded orders and missing verification markers.
Collected sales include shipping; membership item revenue is a component, not an
additional total. This is not a profit statement or a net-refund/accounting ledger.

Current operational counts are explicitly separate from historical period metrics.
They include all environments and manual memberships; review/undated-payment counts
use the selected environment. VIP counts are distinct customers with timestamp-based
validity. Expired VIPs are included within customers without active VIP.

Monthly charts and tables show collected sales, membership revenue, activation
customers, renewals, order creation and appointment requests. Appointment status
breakdowns reflect the current status of requests created in the date range.
Activation customer counts are unique per period/month and are not always additive.
CSV export regenerates the same applied filters with its own generation timestamp,
uses exact MYR decimals and contains aggregates only, not customer details/secrets.
No real provider payment or external SMTP delivery is implied by reporting tests.

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
against spreadsheet formula injection. Unpaid cancellation and manual refund ledgers
are described above; no automatic refund API is implemented.

## Deployment foundation

See [docs/DEPLOYMENT_RUNBOOK.md](docs/DEPLOYMENT_RUNBOOK.md) for the release,
proxy/TLS, supervised worker, cron, encrypted backup and isolated-restore checklist.
The examples are operator instructions, not installed infrastructure or proof of
an actual backup/restore, provider payment or mail delivery.

```sh
php artisan app:check-deployment
php artisan app:check-deployment --json
```

These commands are read-only and emit no credentials, DB exception text or private
job payloads. Exit 1 means automatic checks failed; exit 0 is not go-live approval:
the report always lists separate manual acceptance requirements. Local development
is expected to fail production checks. Do not enable payment collection or invoice
issuance just to make the report pass.

Production HTTP requests must use the HTTPS host/port in `APP_URL`; other origins
return 400. Handle HTTP/alias redirects at the web server. Behind TLS termination,
configure explicit `TRUSTED_PROXIES` IPs/CIDRs and sanitized forwarded scheme/port/IP.
Wildcards, hostnames and `/0` are rejected; forwarded Host is not trusted.
Use Secure/HttpOnly/encrypted database sessions with SameSite=Lax. Session encryption
defaults on and Secure cookies default on in Production; configure the real host
explicitly and inspect actual cookies, not just configuration.

Dynamic responses have private/no-store, nosniff, frame-denial, no-referrer and a
baseline CSP. This includes public Inertia pages with personalized auth/cart props
and handled errors. It is not a complete script-source CSP. Static hashed assets
may be cached separately at the web server. HSTS is only emitted for secure
production requests, without subdomain/preload assumptions.

Configure server PHP `mail()`, supervised workers on `commerce-notices,default`,
scheduler execution and verified off-host backups before launch. Real Sandbox,
mail-inbox, restore and production performance acceptance remain outstanding.

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
