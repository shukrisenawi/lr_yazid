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

## Homepage banners and website settings implemented

- Administrator-managed raster image uploads, title/subtitle, accessible image
  description, internal CTA, draft/publication, ordering and optional start/end
  schedule. Active display uses inclusive start and exclusive end in MYT.
- Maximum six eligible banners; deterministic order and manual accessible controls,
  no autoplay. Default homepage remains when no published banner is eligible.
- Website name, logo, favicon, email/phone/address, HTTPS social links and footer
  are configurable without touching invoice, mail, payment or currency configuration.
- SVG and oversized/invalid uploads are rejected; accepted files are re-encoded.
  Internal-only banner paths reject protocol-relative, external and encoded redirects.
- Locked version checks reject stale writes. Administrator-attributed snapshots
  preserve prior values/assets; duplicate unchanged saves do not add history.
  Failed writes clean up only new assets. No destructive history/asset purge.
- Browser: website/contact/logo/favicon save, social link save/render, image banner
  creation/publication with schedule, manual second-banner selection and unpublishing.
  Empty multipart arrays and favicon head deduplication were corrected.
- QA branding/contact/social placeholders were cleared, uploaded branding removed,
  and both test banners returned to drafts. Public homepage restored to its defaults.
- Desktop light and mobile dark checked; no document overflow or browser console
  errors. Guest authentication branding and rejected invalid login were also checked.
- Final default-home local Lighthouse: performance 76, accessibility 100, best
  practices 100, SEO 100. The development-server performance target remains unmet;
  these checks do not establish whole-site production readiness.

## Administrator dashboard and reporting: Core implemented

- Dashboard/report date filters (inclusive MYT, maximum 366 days), Production by
  default and an explicitly labeled Sandbox test-data view.
- Financial aggregates require a single amount-matched paid MYR order/payment,
  provider-check timestamp, transaction reference and paid timestamp, without any
  review flags or duplicate paid attempts. Pending/failed/refunded records are excluded.
- Collected sales include shipping; membership item revenue is a component of that
  total. No profit, net-refund ledger or accounting/regulatory compliance is claimed.
- Current customer/VIP/product/order/program/appointment counts are separated from
  historical period figures. VIP customers are distinct and expiry is exclusive.
- Accessible financial bars and selectable activity charts, monthly tables, appointment
  request-cohort status breakdown and aggregate-only CSV exports with exact money.
  Exports regenerate applied filters with their own generation timestamp.
- Reporting indexes support payment-period/duplicate checks and order/request cohorts.
- Browser acceptance: report/dashboard date/environment submissions, Sandbox warning,
  real zero-sales state, filter-preserving report links and CSV status/type/content.
  Activity-series switching, desktop light and mobile dark layouts were checked;
  the monthly table scrolls within its container without document overflow.
  Local unpaid QA orders remain unpaid; no fake live sales were introduced.
- Positive financial/membership aggregates are covered in SQLite tests and a disposable
  MySQL reporting database. This is not evidence of genuine ToyyibPay payment acceptance.

## ToyyibPay administration settings enhanced

- Direct admin sidebar entry and Website settings link to `/admin/payment-settings`.
  Sandbox/Production Secret Keys and Category Codes, environment and collection
  enable/disable are configurable in the UI, without source-code credential edits.
- Encrypted DB settings override environment defaults after saving. Blank keys preserve
  saved values; replacement/removal is explicit. Keys never appear in Inertia props,
  rendered HTML, validation old-input sessions or audit payloads.
- Locked version checks protect both first-time and existing configuration from lost
  updates. Safe before/after metadata and administrator identity are audited.
- Collection requires the selected credentials and a public HTTPS application address.
  Local/private/unsafe URL configurations are rejected, but credentials can still be
  saved while disabled. The URL screen does not prove external callback reachability.
- Key rotation does not rewrite credential snapshots on existing payment attempts.
  Saving settings makes no provider request and does not validate provider acceptance.
- Browser: dummy Sandbox credentials saved with collection disabled, input/payload
  secrecy checked, localhost enable rejected, explicit key removal and settings-menu
  navigation verified. Dummy credentials/category were cleared; collection remains disabled.
- Desktop light and 390px mobile dark snapshots checked without document overflow
  or browser console errors. Positive provider requests use automated HTTP fakes only.

## Commerce notifications: Core implemented

- Verified-account welcome, order creation and verified payment success/failure
  notices use queued database/mail channels. Signup before email verification
  does not send welcome. No mass backfill of historical events is performed.
- An owner/event-unique outbox with immutable minimal message/recipient snapshots
  and stable notification UUIDs prevents duplicate enqueueing/in-app records.
- Order/payment notices and queue inserts share the business DB transaction,
  including when the queue's global after-commit setting is enabled.
  Cross-connection queue configurations are rejected; rollback coverage verifies
  cart/order/outbox/job consistency.
- Payment notices originate from authenticated provider reconciliation, never
  browser-return parameters. Review/late payments do not generate success notices.
  Sandbox payment messages explicitly say they are tests.
- Current-state checks suppress stale failure/success messages, changed/unverified
  mail recipients and newer-attempt failures. Per-channel processed/suppressed
  timestamps and reasons are retained; completed retries do not create another
  in-app entry. External mail remains at-least-once, not guaranteed exactly-once.
- Queued deliveries are configured to discard deleted recipients; welcome-only
  outbox rows follow nonfinancial account deletion without relaxing order/payment history constraints.
- Read-only admin `/admin/commerce-notices` has customer/order, event and channel
  filters. It exposes no secret/recipient snapshots and has no resend/financial
  mutation actions. Pending processing is not misrepresented as proof of delivery.
- Local `artisan dev` includes `commerce-notices,default`; production workers need
  the same queue list and the business DB connection.
- Browser acceptance used a separate `Commerce Notice QA` customer (ID 5), local
  signed email verification, a real pending RM99 order and four log/database queue
  jobs. Welcome/order notices appeared, links worked, and marking read was verified.
- QA order `YC-20261115-M2HVALCF005L` (ID 3) was cancelled through the customer UI
  after testing to release reservations; records were retained. No payment was
  started or fabricated, and the pre-existing administrator cart was untouched.
- Admin processed-channel filtering, desktop light and 390px dark/mobile table containment were
  verified. Positive payment notice cases use automated provider HTTP fakes only;
  local log mail is not real SMTP delivery evidence.

## PHP mail and unpaid order cancellation: Core implemented

- `MAIL_MAILER=mail` registers a direct PHP `mail()` transport without application
  SMTP credentials. Server `php.ini` supplies the mail service. MIME HTML/text and
  attachments are preserved; unsupported Bcc, To-less, raw MIME and custom
  recipient envelopes fail closed. Failed hand-offs throw and remain retryable
  without a processed timestamp. Successful hand-off is not inbox-delivery proof.
- Local QA remains on the log mailer deliberately. CLI PHP has `mail()` available,
  `SMTP=localhost`, port 25 and no sendmail path; external delivery is unverified.
  Production must configure both web and worker PHP and use `MAIL_MAILER=mail`.
- Administrator unpaid cancellation has a required customer-visible reason,
  confirmation and a unique audit row with actor/source/previous states/time.
  Customer/expiry paths share the same transactional audit and stock release.
  Historical cancellations are not backfilled with invented actors.
- Owner -> order -> payment locking serializes cancellation with settlement.
  Paid/review/fulfillment-progress cases are rejected; reservation release never
  increments physical inventory or deletes financial records.
- Existing provider bills are not voided and no refund is issued. Verified money
  received after cancellation remains recorded as paid with review required,
  fulfillment cancelled, no stock consumption and no automatic VIP activation.
- Chrome acceptance: isolated local QA administrator ID 6 cancelled customer QA
  order ID 5 (`YC-20261115-IIDEIREQSIAV`, RM99). Its audit reason/actor, customer
  reason display, one released reservation and zero payments were verified.
  Desktop light and 390px dark/mobile audit views had no document overflow or console errors.
  No real provider payment/mail occurred. Existing orders/cart were not modified.
- Native mail tests use injected hand-off callbacks, not external delivery.
  MySQL independent-process tests cover duplicate cancellation and a verified
  settlement/cancellation race; frontend tests cover confirmation and escaped audit.

## Manual refund ledger: Initial release implemented

- Authorized admins record completed external refunds on order details. Exact
  integer-sen amounts, external reference/method, MYT completion time, required
  audit reason, acknowledgement and confirmation are enforced.
- Only matching server-verified paid MYR attempts are eligible. Review-flagged
  and additional paid attempts are supported without clearing review flags.
  Amounts are capped independently per payment, so duplicate received payments
  may each have a refund record; unpaid/foreign/mismatched attempts are rejected.
- Owner/order/payment locks, locking refund reads and database uniqueness protect
  retries/concurrent submissions. Identical same-actor UUID retries return the
  original row; mismatched reuse is rejected. External refs are uppercased and
  unique per environment. The audit ledger has no edit/delete/correction routes.
- This module sends no money and invokes no ToyyibPay API. Original paid status,
  invoice, fulfillment, stock, membership and review flags are not rewritten.
  There is no automatic credit note or tax/e-Invoice adjustment.
- Owner-only customer details expose minimal reference/amount/date/environment
  summaries; external bank references, admin identities and audit notes are private.
- Reports and aggregate CSV add separate manual refund amount/count/monthly totals
  by completion date/environment; verified collections remain gross, not net.
  Manually declared refunds are not provider proof of transfer or customer receipt.
- Chrome acceptance used port 8001 and a disposable `lr_yazid_test_*` MySQL DB,
  never fabricated settlement in the application DB. A clearly synthetic Sandbox
  review payment received one synthetic RM25.10 ledger entry. The admin form,
  acknowledgement/confirmation, remaining RM74.90 balance, audit, customer privacy
  projection and Sandbox-versus-Production reporting were verified.
- 390px dark/mobile ledger had no document overflow. No browser console warnings
  or errors were observed. Synthetic data are not genuine provider/refund acceptance.
  The temporary browser server was stopped and its synthetic database removed.

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
- Latest checks: 284 backend tests / 2077 assertions passed; twenty-two opt-in MySQL
  checks / 227 assertions passed separately (21 concurrency tests and one reporting test).
  PHPStan and Pint passed.
- Seventy-one Vue/component/helper tests, TypeScript, frontend formatting/lint and production build passed.
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

Advanced customer
administration, deeper reporting/production hardening and
final deployment acceptance.

Continue in roadmap order. ToyyibPay sandbox acceptance will require sandbox
credentials and a reachable callback URL, not real payments. Do not advertise
live payment collection or appointment booking as production-ready.
