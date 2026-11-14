# Architecture and delivery plan

## Foundation

Laravel 13 (PHP 8.3+), the official Laravel Vue starter kit, Inertia 3,
Vue 3 Composition API, TypeScript, Tailwind 4, shadcn-vue/Reka components,
Vite and MySQL. Package lockfiles record the actual compatible versions.
The application is a monolith, not a separate SPA/API.
Currency is MYR; monetary values use integer sen. Timezone is Asia/Kuala_Lumpur.

## Structure

- `app/Http/Controllers`: public, customer, VIP and administrator controllers.
- `app/Http/Requests`: validated form inputs.
- `app/Models`: Eloquent relationships and persistence.
- `app/Policies`: resource ownership and administrator permissions.
- `app/Services`: checkout, inventory, payments, membership and appointment workflows.
- `app/Notifications`, `app/Jobs`: queued delivery and deduplication.
- `routes/web.php`: public routes, authenticated account routes, administrator routes.
- `routes/settings.php`: profile, password and security settings.
- `routes/console.php`: scheduled reconciliation, expiry and reminders.
- `resources/js/pages`: Inertia pages grouped by public, customer, VIP and admin.
- `resources/js/layouts`: shared layouts for each audience.
- `resources/js/components`: accessible reusable controls.
- `resources/js/types`: typed server props and business entities.
- `lang/en`: centralized application text.

## Database and relationships

1. Users have addresses, orders, memberships, appointments and registrations.
   Roles (`customer`, `admin`) do not grant VIP membership.
2. Categories have products. Products have images and optional variants.
   Slugs and SKUs are unique. Archived products remain referenced by order items.
3. Carts have items. Orders belong to users and have immutable item snapshots,
   payment attempts, stock reservations and optional shipments.
4. Membership plans have memberships. Membership transactions reference verified
   payments uniquely; adjustments record administrator, reason and before/after dates.
5. VIP programs have images and registrations, unique per user/program.
6. Services have schedules, blocked dates and slots. Appointments belong to users
   and slots and have status histories and reschedule requests.
7. Pages, banners and settings provide CMS content. Notifications and activity logs
   retain delivery and administrative audit history.

Migrations are introduced with their owning phase, using foreign keys, indexes,
unique constraints and restricted deletion of financial records. Phase 1 creates
users, sessions, cache, queue, role/phone fields and initial membership records.

## Authentication and authorization

Fortify handles registration, login, logout, rate limiting, password reset,
email verification and two-factor security. Registration cannot set a role.
Phone numbers are normalized to Malaysian E.164 format.
Administrator access is checked server-side through an authorization gate.
Resource policies check ownership. VIP eligibility checks active status and
the exclusive expiry timestamp, including eligibility on a future event date.
Administrator provisioning is an interactive command, never a seeded password.

## Payment and membership lifecycle

Checkout recalculates prices, locks inventory and reserves stock transactionally.
An idempotency key prevents duplicate orders. A payment attempt creates a
ToyyibPay sandbox/production bill using server-only credentials.
Callbacks must pass official hash verification and server-side reconciliation,
including amount, reference and bill code. Browser return routes never mark paid.
Payment completion locks the order and applies stock/membership effects once.
Renewal starts at the later of verified payment time and current expiry, adding
calendar months without overflowing. Expiry disables VIP access without deleting history.

## Appointment workflow

Generators skip breaks and blocked dates and reject duplicate/overlapping slots.
Eligible VIP users submit pending requests; pending requests do not reserve capacity.
Approval locks the slot and user, rechecks membership, conflicts and capacity,
then records reviewer and history. Rescheduling preserves the original appointment
until a single transaction approves the replacement.

## Administrator interface

A reusable sidebar/topbar shell hosts dashboard, products, orders, payments,
customers, memberships, programs, appointments, reports, content and settings.
Only implemented modules are linked. No placeholder actions or fabricated KPIs.
Financial reporting counts verified paid transactions only.

## Delivery and verification

Follow PROJECT_REQUIREMENTS.md phases 1–10 in order:
foundation; catalog; cart/checkout; ToyyibPay; membership; programs;
appointments; CMS/reports; hardening; deployment.
Each phase requires backend tests, TypeScript checks, a production asset build,
and browser verification (including real form submission).
External payment tests require sandbox credentials and a reachable callback URL.
No live payment is performed during development.
