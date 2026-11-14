# Implementation status

The master requirements remain the source of truth. This delivery is a working
foundation and catalog milestone, **not the complete production-ready system**.

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

Outstanding catalog refinements before declaring full Phase 2 acceptance:
variant-specific images/structured attribute definitions; dedicated image replacement
instead of remove/re-upload; complete UI string centralization; automated Vue
component tests; broader keyboard, upload-failure and responsive regression coverage.
Cart buttons belong to Phase 3 and are intentionally not fake/disabled actions.

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
- No real or simulated payments were performed.

## Not implemented: Phases 3–10

Cart persistence/merge, address management, checkout/order snapshots/reservations,
ToyyibPay callbacks/reconciliation, verified VIP activation/renewal, expiry reminders,
program registration/capacity, appointment generation/approval/rescheduling,
notifications, CMS, reports, production hardening and final deployment acceptance.
An initial VIP schema/gate is not a completed paid-membership module.

Continue in roadmap order. ToyyibPay sandbox acceptance will require sandbox
credentials and a reachable callback URL, not real payments. Do not advertise
checkout, VIP purchases or appointment booking as available until implemented.
