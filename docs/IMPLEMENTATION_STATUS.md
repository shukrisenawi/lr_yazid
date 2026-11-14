# Implementation status

The master requirements remain the source of truth. This delivery is a working
commerce, membership, programs and scheduling milestone, **not the complete production-ready system**.

## Phase 1: Complete

- Official Laravel Vue starter kit with PHP 8.3, Laravel 13, Inertia 3,
  Vue 3, TypeScript, Tailwind 4 and accessible shadcn-vue components.
- Real MySQL-compatible database; sessions, cache and queue migrations.
- Fortify registration/login/logout, rate limiting, password recovery,
  verification, profile/security settings, two-factor authentication.
- Customer/admin roles protected from registration-based privilege escalation.
- Malaysian phone normalization and validation.
- Public, customer, VIP and administrator layout entry points.
- Server-enforced admin gate and expiry-aware membership eligibility gate.
- Interactive administrator provisioning without shared credentials.
- Architecture, installation and preliminary deployment guidance.

Main files: `app/Actions/Fortify/CreateNewUser.php`, `app/Enums/UserRole.php`,
`app/Models/User.php`, `app/Models/Membership.php`, `app/Models/MembershipPlan.php`,
`app/Support/MalaysianPhone.php`, `app/Console/Commands/CreateAdmin.php`,
`app/Providers/AppServiceProvider.php`, `routes/web.php`,
`database/migrations/`, `resources/js/layouts/`, `tests/Feature/FoundationTest.php`.

## Phase 2: Core catalog implemented; final acceptance remains open

- Product/category relational schema, policies and validated admin operations.
- Create/edit/activate/deactivate/archive/duplicate products.
- Integer-sen prices, date-bounded sale prices and editable inventory.
- Optional named variants with SKU, stock, price and active status.
- Multiple validated uploads, GD/WebP optimization, primary image ordering,
  drag-and-drop and keyboard-accessible primary selection, removal and re-upload.
- Tiptap headings, formatting, lists, hyperlinks, images, text/image alignment,
  image width controls, tables and preview.
- Server-side HTML and style sanitization, tested against unsafe scripts,
  attributes, URLs and arbitrary CSS.
- Public product galleries, variant selection, related products, filters,
  search, current-price sorting and pagination.
- Database-backed homepage selections and local-only labeled sample data.

Main files: `app/Models/{Product,ProductImage,ProductVariant,Category}.php`,
`app/Http/Controllers/CatalogController.php`, `app/Http/Controllers/Admin/`,
`app/Http/Requests/ProductRequest.php`, `app/Policies/ProductPolicy.php`,
`app/Services/{ProductService,ProductImageService,RichTextService}.php`,
`app/Support/EditorStyleSanitizer.php`, `database/seeders/CatalogSeeder.php`,
`resources/js/pages/{public,admin}/`, `resources/js/components/RichTextEditor.vue`,
`resources/js/types/catalog.ts`, `tests/Feature/CatalogTest.php`.

Variant-specific images/structured attributes, direct image replacement and accessible
image ordering are implemented and covered by backend/Vue tests.
Complete UI string centralization and broader accessibility/responsive acceptance
remain outstanding.

## Phase 3: Shopping core implemented

- Persistent guest/authenticated carts, safe login merge, server pricing/stock validation.
- Owner-protected multiple/default shipping addresses and configurable flat-rate shipping.
- User-scoped idempotent checkout, immutable order snapshots and expiring reservations.
- Row-locked inventory protection, including MySQL REPEATABLE READ locking reads.
- Customer orders/cancellation; administrator order fulfillment, invoice and export
  remain outstanding.

## Phase 4: Payment integration implemented; external acceptance blocked

- ToyyibPay sandbox/production configuration with encrypted secrets.
- Bill creation, signed callbacks, transaction API reconciliation, retries and logs.
- Verified amount/reference/status checks; idempotent settlement and inventory deduction.
- Browser returns never activate membership or mark orders paid.
- Ambiguous bill-creation outcomes block blind retries. Late/additional payments
  are recorded for review without repeated fulfillment.
- Payment administration and scheduled reconciliation.
- Genuine sandbox acceptance needs credentials and a publicly reachable HTTPS APP_URL.
  Automated HTTP responses are mocked; no real provider payment has been performed.

## Phase 5: VIP purchase and lifecycle core implemented

- Configurable plans, membership-only/mixed carts, no membership stock/shipping.
- Verified activation and serialized renewal; purchased duration/price snapshots.
- Exclusive expiry timestamps, calendar-month/leap-day behavior, revoked-access guards.
- Membership history, dashboard and owner-protected database notifications.
- Administrator expiry adjustments require reasons and create permanent audit records.
- Queued database/mail notices and deduplicated 30/7/1-day/expired reminders.
- Full administrative member-detail/history views and broader dashboard summaries
  remain outstanding.

## Phase 6: VIP program core implemented

- Administrator create/edit/publish/unpublish/cancel/complete/archive operations.
- Sanitized Tiptap content, optimized featured/additional images and HTTPS map links.
- VIP-only listings/details; registration windows and whole-event membership eligibility.
- Row-locked capacity checks and idempotent registration; customer withdrawal releases capacity.
- Owner-scoped registration history survives expiry and program cancellation.
- Participant filters, attendance with administrator/timestamp, spreadsheet-safe CSV export.
- Publication, registration and cancellation notices with transactional queueing,
  event deduplication and database/mail delivery tracking.
- Schedule/location changes are blocked once registrations exist to prevent silent
  changes to confirmed plans.

## Phase 7A: Appointment scheduling administration implemented

- Service management with duration/capacity/location and persisted booking-policy defaults.
- Manual future slots, editing, availability controls, filtering and unused-slot deletion.
- Automatic generation with date range, working days/hours, optional breaks,
  blocked dates, duration/capacity/location, read-only preview and run history.
- Service row locks serialize manual creation and generator runs; overlapping and
  existing disabled slots are preserved. Sorted interval scanning and batched
  insertion keep generation bounded.
- Maximum 93 days and 5,000 new slots per run; previews display at most 100.
- Approved-appointment guards prevent silent slot changes/date blocking;
  any appointment history prevents deletion. Removing a date block does not
  automatically reactivate disabled slots.
- Appointment schema is present for the next workflow, but customer submission,
  administrator approval, calendars, rescheduling, status history and reminders
  are **not implemented**. No customer-facing booking action is advertised.

## Verification

- Foundation: 50 backend tests / 169 assertions passed.
- Foundation + catalog: 60 backend tests / 216 assertions passed.
- PHPStan, Pint, Vue TypeScript, frontend lint/format and production Vite build passed.
- MySQL/MariaDB migrations and local development sample seeding succeeded.
- Chrome: real registration and signed email verification; administrator login;
  real product creation with two image uploads and rich text; category creation;
  inline editor image upload/save/render; public search and price filtering.
- Desktop light and 390px mobile dark layouts checked; no horizontal overflow.
- Local mobile Lighthouse: Performance 78, Accessibility 100, CLS 0.
  Simulated mobile LCP remains 5.3 seconds on the uncompressed PHP development
  server; the production performance target is not yet met.
- Composer audit and npm production-dependency audit reported no advisories.
- Temporary browser test accounts were removed and the browser-created test
  product was archived after verification.
- Latest checks: 146 backend tests / 740 assertions passed; seven opt-in MySQL
  concurrency tests / 69 assertions passed separately. PHPStan and Pint passed.
- Twenty-one Vue component tests, TypeScript, frontend formatting/lint and production build passed.
- Browser: plan creation and membership-only checkout produced an unpaid RM199
  order with no shipping address or fee. Gateway collection remains disabled.
- Verified activation/renewal/reminder behavior is automated-test coverage, not
  a claim of real sandbox payment acceptance.
- Program browser acceptance: actual create/upload/publish, rich-text rendering,
  VIP registration, participant filtering, program cancellation and retained history.
  A local-only QA membership was provisioned with an explicit administrator audit;
  it was not represented as a paid membership.
- Database queue jobs delivered program/membership notices and local log-mail;
  notification reading was browser-verified. No real SMTP delivery is claimed.
- Program details and history checked at 390px dark/mobile with no horizontal overflow;
  no browser console errors were observed.
- Scheduling browser acceptance: service creation, filtered listing, manual slot,
  blocked date, read-only generation preview and generation of 20 slots.
  Break and blocked-day skip counts matched expectations; mobile 390px inspection
  showed no document overflow or console errors.

## Remaining phases and acceptance

Customer appointment submission/approval/calendars/rescheduling/reminders,
remaining commerce notifications, administrator order/customer management,
CMS, reports, production hardening and final deployment acceptance.

Continue in roadmap order. ToyyibPay sandbox acceptance will require sandbox
credentials and a reachable callback URL, not real payments. Do not advertise
live payment collection or appointment booking as production-ready.
