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
- The customer workflow is implemented in Phase 7B below.

## Phase 7B: Appointment workflow core implemented

- VIP monthly booking calendar with dynamic date/slot selection and server-side
  membership, advance/notice, capacity, conflict and active-booking-limit checks.
- Idempotent submissions start Pending Approval; pending requests do not reserve capacity.
- Administrator request filters/details and month/week/day/list calendar with
  blocked dates, full slots and links to review. Calendar results are capped at
  500 entries with a visible limit warning; the request list is paginated.
- Row-locked approval/rejection, cancellation, completion and no-show transitions.
- Same-service rescheduling preserves the original approval until an atomic move.
  Administrator alternatives need customer consent followed by administrator approval.
- Owner-only history remains accessible after expiry; expired customers cannot
  submit new bookings or reschedule. Cancellation cutoff is server-enforced;
  administrators can cancel with a reason.
- Immutable schedule/location and history-time snapshots; reviewer/actor audit records.
- Transactional, deduplicated database/mail notices and scheduled 24-hour/1-hour
  reminders. Stale reminder deliveries are suppressed after cancellation/time changes.
- Active booking/reschedule slots and retained history are protected from unsafe edits/deletion.
- Administrator accounts and accounts with retained appointment/business history
  cannot self-delete through profile settings.

## Customer administration: Directory and profile history implemented

- Admin-only customer directory with name/email/phone search and timestamp-aware
  active, expired, expiring and no-membership-history filters.
- Paginated customer profiles link orders, membership payments, programs and appointments.
- Passwords, two-factor secrets and administrator accounts are excluded from the directory.
- Read-only records cannot grant roles, mark payments paid or change membership validity.
- Customer account mutation and advanced reporting remain separate unfinished work.
- Browser acceptance verified real customer search/filter submission and customer
  profile navigation using a labeled local QA account. Mobile 390px inspection
  found no document overflow or browser console errors.

## Administrator order fulfillment: Core implemented

- Search and payment/fulfillment filters, paginated orders and snapshot-based details.
- Verified paid physical orders advance new -> processing -> packed -> shipped -> delivered.
- Courier/tracking required before shipping; updates require an administrator reason
  and retain old/new status and tracking in an audit record.
- Unpaid, membership-only and review-flagged payments cannot be fulfilled.
- Repeated identical updates are idempotent; stock and payment state are not changed.
- Customer order pages display courier/tracking. Printable summaries are separate
  from the commercial invoice workflow below. Refund/cancellation handling remains unfinished.
- Browser verification confirmed an attempted update of a real unpaid QA order
  was rejected without changing its fulfillment or payment state.

## Invoices, exports and shipment notices implemented

- Admin-configured seller details, disabled by default; no invented issuer details
  are used for production invoicing. Invoices require verified paid MYR orders,
  matching payment amount/date, consistent totals and no payment review flags.
- One invoice per order, protected by owner/order locks and a unique constraint.
  Seller/buyer/line/total/payment snapshots remain unchanged after later edits.
- Owner/admin-only printable commercial invoice with browser Print / Save PDF.
  This is not LHDN e-Invoice validation, SST/tax-invoice support or tax advice.
- CSV exports share the search/payment/fulfillment/date filters, stream in batches,
  cap at 10,000 orders and neutralize spreadsheet formula cells. No secrets exported.
- Shipped, tracking-correction and delivered notices use transactional database
  queueing, immutable shipment snapshots and per-channel delivery tracking.
  Identical retried updates do not enqueue duplicate notices.
- Browser acceptance checked issuer form submission while disabled, unpaid invoice
  rejection and filtered CSV response content. A first-visit default hydration bug
  and nullable-filter export bug were fixed and regression-tested.
- Local QA issuer placeholders were cleared after testing; issuance remains disabled.
  Positive issuance/print rendering/shipment behavior is automated-test coverage,
  not a claim of a genuine provider payment or external SMTP delivery.

## Content pages: Core implemented

- Admin-managed About/Contact, policies and membership information via general
  content pages: title, slug, sanitized rich text, SEO title, meta description,
  draft/publication status and optional public footer navigation.
- Public `/pages/{slug}` returns published pages only, without internal audit data.
  Empty/unsafe-only content cannot be published. No legal policies are invented.
- Version-checked updates reject stale edits; each actual save retains an immutable
  revision snapshot and administrator identity. No destructive page deletion.
- Browser acceptance: create/publish with the rich text editor, public SEO/footer
  rendering, return to draft, retained revisions and public 404 after unpublishing.
  Search/status filter submitted successfully. QA page remains a nonpublic draft.
- Desktop light and 390px mobile dark inspected without document overflow.
  Local public-page Lighthouse: performance 91, accessibility 100, best practices
  100, SEO 100. This is a local content-page check, not whole-site production acceptance.
- Homepage banners and general website settings remain outstanding.

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
- Latest checks: 196 backend tests / 1295 assertions passed; fourteen opt-in MySQL
  concurrency tests / 135 assertions passed separately. PHPStan and Pint passed.
- Thirty-nine Vue/component/helper tests, TypeScript, frontend formatting/lint and production build passed.
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
- Workflow browser acceptance: pending booking, approval, requested/approved move,
  administrator alternative, customer acceptance, rejection preserving the existing
  approval, cancellation and retained audit history. QA booking
  `APT-ZPDP37ITGHU7AJDU1YXL` was cancelled after acceptance, not deleted.
- Administrator request filtering and calendar views, notification reading and
  local database/log-mail delivery checked. Reminders and completion/no-show time
  rules have automated-test coverage; real SMTP delivery is not claimed.
- MySQL races verify duplicate submissions/approvals, same-user overlapping services,
  final approved place and final reschedule place, alongside previous commerce tests.

## Remaining phases and acceptance

Remaining commerce notifications, refund/cancellation administration, advanced customer
administration, homepage banners/general settings, reports, production hardening and
final deployment acceptance.

Continue in roadmap order. ToyyibPay sandbox acceptance will require sandbox
credentials and a reachable callback URL, not real payments. Do not advertise
live payment collection or appointment booking as production-ready.
