# MASTER PROJECT REQUIREMENTS
# LARAVEL E-COMMERCE, VIP MEMBERSHIP, VIP PROGRAMS & APPOINTMENT BOOKING SYSTEM

**Project Type:** Full-Stack Web Application  
**Backend:** Laravel  
**Frontend:** Inertia.js + Vue 3  
**Programming Language:** PHP + TypeScript  
**Database:** MySQL  
**Website Language:** English  
**Currency:** Malaysian Ringgit (MYR)  
**Timezone:** Asia/Kuala_Lumpur  

---

# 1. PROJECT OVERVIEW

Build a complete, modern, professional, secure, and production-ready E-Commerce website using Laravel with Inertia.js and Vue 3.

The system must combine the following core functionalities:

1. E-Commerce website.
2. Multiple physical product management.
3. Shopping cart.
4. Customer registration and authentication.
5. Product detail page management with a rich text editor.
6. Multiple product image uploads.
7. Checkout and order management.
8. ToyyibPay payment gateway.
9. Annual VIP Membership subscription.
10. Automatic VIP activation after successful payment.
11. Membership renewal and expiration management.
12. VIP-exclusive programs and event registration.
13. VIP appointment booking.
14. Automatic and manual appointment slot generation.
15. Administrator approval of appointment requests.
16. Customer dashboard.
17. VIP dashboard.
18. Complete administrator dashboard.
19. Notification system.
20. Reports, analytics, and content management.

This must be a fully functional application, not a static prototype or UI demonstration.

All buttons, forms, database operations, payments, access controls, and workflows must work correctly.

The final application must be optimized for desktop, tablet, and mobile devices.

---

# 2. IMPORTANT LANGUAGE REQUIREMENTS

**THE ENTIRE WEBSITE MUST USE ENGLISH.**

This applies to:

- Public website.
- Homepage.
- Product pages.
- Product descriptions entered through the admin panel.
- Navigation menus.
- Login and registration.
- Customer dashboard.
- VIP dashboard.
- Administrator dashboard.
- Appointment booking pages.
- Program registration pages.
- Checkout.
- Payment pages.
- Error messages.
- Validation messages.
- Success messages.
- Toast notifications.
- Email notifications.
- Status labels.
- System-generated content.
- Reports.
- Invoices.

Examples of required interface labels:

- My Account
- My Orders
- My Membership
- Upgrade to VIP
- Renew Membership
- VIP Programs
- Book Appointment
- My Appointments
- Pending Approval
- Appointment Approved
- Appointment Rejected
- Membership Expired
- Continue Shopping
- Proceed to Checkout

Do not generate Malay or Indonesian interface text.

All default seed data, demonstration content, and system-generated messages must be in English.

Use English for database field names, code comments, class names, functions, routes, and technical documentation.

Keep user-facing text centralized so localization can be added in the future without rewriting the application.

---

# 3. REQUIRED TECHNOLOGY STACK

Use the following technology stack.

## Backend

- Laravel, using the latest compatible stable version.
- PHP with the appropriate supported version.
- MySQL.
- Eloquent ORM.
- Laravel Authentication.
- Laravel Policies.
- Laravel Form Requests.
- Laravel Notifications.
- Laravel Queue.
- Laravel Scheduler.
- Laravel Storage.
- Laravel Cache.
- Laravel HTTP Client.
- Laravel Events and Listeners.

## Frontend

- Inertia.js.
- Vue 3.
- Vue Composition API.
- TypeScript.
- Tailwind CSS.
- Vite.
- Vue Single File Components.
- Inertia `useForm` and router.
- Reusable Vue components.

## UI Components

Prefer:
- shadcn-vue or an equivalent compatible Vue component library.
- Lucide icons.
- Vue-compatible date and time picker.
- Vue-compatible calendar component.
- Chart.js or an equivalent Vue-compatible chart library.
- Tiptap Rich Text Editor or another actively maintained Vue 3 editor.

## Payment

- ToyyibPay official API.
- Sandbox and Production support.

## Architecture

Use Laravel Monolith Architecture with Inertia.js.

Laravel handles:
- Routing.
- Authentication.
- Authorization.
- Validation.
- Database operations.
- Business logic.
- Payment processing.
- Notifications.
- Scheduled tasks.

Vue 3 handles:
- Public website interface.
- Customer interface.
- VIP interface.
- Administrator interface.
- Forms.
- Tables.
- Modal dialogs.
- Calendars.
- Interactive components.
- Charts.

Inertia.js connects Laravel controllers to Vue pages.

Do not create a separate frontend application or unnecessary REST API.

Use Inertia server responses for normal pages and dedicated JSON endpoints only when necessary for dynamic functionality.

**Do not use Livewire.**

**Do not use Filament because the administrator interface must also use Inertia.js and Vue 3.**

Blade may only be used for the minimal Inertia application root template or necessary server-rendered templates such as emails.

Use the official Laravel Vue Starter Kit as the preferred project foundation.

Verify current package compatibility before installing dependencies.

---

# 4. OVERALL APPLICATION STRUCTURE

The application must have three main sections.

## 4.1 Public Website

Accessible to visitors.

Features:
- Homepage.
- Products.
- Product categories.
- Product details.
- Shopping cart.
- VIP Membership information.
- About Us.
- Contact Us.
- Login.
- Registration.
- Informational pages.

## 4.2 Customer Portal

Accessible to authenticated customers.

Normal members can:
- Manage their account.
- Purchase physical products.
- View orders.
- Track purchases.
- Manage shipping addresses.
- Upgrade to VIP.

Active VIP members can additionally:
- Access the VIP dashboard.
- Access VIP-exclusive programs.
- Register for VIP programs.
- Book appointment slots.
- View appointment status.
- Renew their membership.

## 4.3 Administrator Portal

Accessible only to administrators.

Administrators can manage:
- Products.
- Categories.
- Customers.
- Orders.
- Payments.
- Memberships.
- VIP Programs.
- Appointment services.
- Appointment slots.
- Booking approvals.
- Website content.
- Reports.
- Notifications.
- System settings.

All sections must use Vue 3 and Inertia.js.

---

# PART A: AUTHENTICATION & USER MANAGEMENT

# 5. USER ROLES

The system must have two primary user roles:

### Administrator

Has access to the administrator portal and system management functions.

### Customer

Has access to the customer portal and E-Commerce features.

Customer membership levels:

**Normal Member**
- Default membership status after registration.
- Can purchase physical products.
- Can view order history.
- Can track orders.
- Can manage profile and addresses.
- Can purchase VIP Membership.
- Cannot access VIP-exclusive programs.
- Cannot make VIP appointment bookings.

**VIP Member**
- Has all Normal Member capabilities.
- Can access VIP-exclusive pages.
- Can register for VIP programs.
- Can book VIP appointments.
- Can view membership details.
- Can renew membership.
- Can view membership payment history.

Important:

User role and membership level must be separate concepts.

Do not use the administrator role to determine VIP membership.

VIP privileges must be determined through valid membership records and expiration dates.

When VIP membership expires, the user remains a customer and automatically loses access to VIP-exclusive features.

# 6. AUTHENTICATION SYSTEM

Implement:

- User registration.
- Login.
- Logout.
- Forgot password.
- Reset password.
- Email verification where enabled.
- Change password.
- Edit profile.
- Session management.
- Login rate limiting.

Registration fields:
- Full Name.
- Email Address.
- Phone Number.
- Password.
- Confirm Password.

Email addresses must be unique.

Normalize Malaysian phone numbers consistently.

Use secure password hashing and Laravel's built-in authentication mechanisms.

# 7. CUSTOMER PROFILE

Allow customers to manage:

- Full Name.
- Email.
- Phone Number.
- Password.
- Profile Information.
- Shipping Addresses.

Support multiple shipping addresses.

One address can be selected as the default shipping address.

All account details must be accessible only to the account owner or authorized administrators.

---

# PART B: PUBLIC WEBSITE & DESIGN

# 8. DESIGN REQUIREMENTS

Create a modern and professional E-Commerce website.

Design goals:
- Clean layout.
- Modern typography.
- Consistent colors.
- Professional appearance.
- Smooth interactions.
- Mobile-first design.
- Accessible navigation.
- Clear calls to action.
- Responsive components.

Avoid overly complicated animations and unnecessary visual effects.

Use subtle transitions and loading indicators.

Use a consistent design system for all pages.

## 8.1 Header

Include:
- Website Logo.
- Home.
- Products.
- VIP Membership.
- About Us.
- Contact Us.
- Product Search.
- Shopping Cart.
- Login / Register.
- My Account.

Display the cart item count dynamically.

For authenticated VIP members, optionally display a VIP badge in the account navigation.

## 8.2 Homepage

Include:

1. Hero Banner.
2. Featured Products.
3. Latest Products.
4. Product Categories.
5. VIP Membership Promotion.
6. VIP Benefits.
7. Call to Action.
8. Footer.

Homepage banners must be manageable through the administrator portal.

## 8.3 Footer

Include:
- Company Name.
- Company Address.
- Contact Email.
- Phone Number.
- Social Media Links.
- About Us.
- Privacy Policy.
- Terms & Conditions.
- Shipping Policy.
- Refund Policy.

All displayed business information must be configurable by the administrator.

---

# PART C: PRODUCT MANAGEMENT

# 9. MULTIPLE PRODUCT MANAGEMENT

The administrator must be able to create and manage unlimited physical products, subject to server capacity.

Provide full product CRUD functionality.

Administrator actions:
- Create Product.
- Edit Product.
- Duplicate Product.
- Archive Product.
- Activate Product.
- Deactivate Product.
- Manage Inventory.
- Manage Product Images.
- Manage Product Categories.
- Configure Product Prices.

Support multiple independent physical products.

Do not limit the application to a single product.

## 9.1 Product Fields

Each product should include:

- ID.
- Product Name.
- Unique Slug.
- SKU.
- Category.
- Product Type.
- Short Description.
- Full Description.
- Regular Price.
- Sale Price.
- Sale Start Date.
- Sale End Date.
- Featured Image.
- Product Gallery.
- Stock Quantity.
- Stock Status.
- Product Weight.
- Product Status.
- Featured Status.
- SEO Title.
- Meta Description.
- Created At.
- Updated At.

Product Types:
1. Physical Product.
2. Membership Product.

Membership products must follow their own business rules and must not require stock or shipping.

## 9.2 Product Status

Support:
- Draft.
- Active.
- Inactive.
- Archived.

Archived products must not appear in public listings.

Preserve historical order records even when a product is archived.

## 9.3 Product Variations

Provide optional support for:
- Size.
- Color.
- Other Attributes.

Variation properties:
- SKU.
- Price.
- Stock Quantity.
- Image.
- Availability.

Variation functionality should only appear when configured for a product.

# 10. MULTIPLE PRODUCT IMAGE UPLOAD

A product can have multiple images.

Administrator features:
- Upload multiple files.
- View image previews.
- Reorder images using drag and drop.
- Set primary image.
- Replace images.
- Remove images.
- Automatic image optimization.
- Responsive image display.

Supported formats:
- JPG.
- PNG.
- WebP.

Validate file types, file sizes, and image contents.

Use Laravel Storage.

Do not store large image binaries directly in the database.

Store paths and metadata in the database.

Use a reliable image optimization process.

# 11. RICH TEXT PRODUCT DETAIL EDITOR

**This is a critical requirement.**

The administrator must be able to design each product's detail page using a rich text editor.

Use Tiptap with Vue 3 or an equivalent compatible WYSIWYG editor.

Editor features:
- Headings.
- Paragraphs.
- Bold.
- Italic.
- Underline.
- Bullet Lists.
- Numbered Lists.
- Text Alignment.
- Hyperlinks.
- Images.
- Image Upload.
- Image Alignment.
- Image Resize where supported.
- Tables where supported.
- Content Preview.

The administrator must be able to insert images between text sections.

Example product content:

Product Introduction

Product Image

Product Benefits

Additional Images

How to Use

Product Specifications

Important Information

All content must be saved and rendered dynamically.

The product detail page must preserve the content structure while remaining responsive.

Sanitize editor-generated HTML on the server.

Do not allow unsafe scripts or arbitrary executable HTML.

# 12. PRODUCT LISTING PAGE

Display products in a responsive grid.

Each product card includes:
- Product Image.
- Product Name.
- Regular Price.
- Sale Price.
- Stock Status.
- View Details.
- Add to Cart.

Provide:
- Search.
- Category Filter.
- Price Filter.
- Sort by Newest.
- Sort by Price: Low to High.
- Sort by Price: High to Low.
- Pagination.

Support proper empty states and loading states.

# 13. PRODUCT DETAIL PAGE

Use dynamic routes.

Example:

`/products/{slug}`

Display:
- Product Name.
- Product Image Gallery.
- Product Price.
- Promotional Price.
- Product Variations.
- Availability.
- Quantity Selector.
- Add to Cart.
- Buy Now.
- Rich Text Description.
- Related Products.

Use Vue 3 components.

Adding a product to the cart must not require a full page reload.

Show clear success or failure feedback.

---

# PART D: SHOPPING CART

# 14. SHOPPING CART SYSTEM

Develop a fully functional Shopping Cart using Inertia.js and Vue 3.

Features:
- Add to Cart.
- Update Quantity.
- Remove Product.
- Clear Cart.
- View Cart.
- Cart Subtotal.
- Shipping Fee.
- Order Total.
- Continue Shopping.
- Proceed to Checkout.

Shopping cart requirements:

- Smooth interactions.
- Real-time visual updates.
- No unnecessary full page reload.
- Cart data persists after refreshing.
- Cart survives login when appropriate.
- Quantity is validated.
- Stock is validated.
- Pricing is calculated by Laravel.
- Prevent duplicate submissions.

Use server-side cart persistence for authenticated users.

Guest carts may use secure session-based storage.

Merge guest cart with the authenticated customer's cart after login, with clearly defined quantity rules.

Do not trust prices sent by the browser.

## 14.1 Membership Product in Cart

VIP Membership is a special product.

Rules:
- No shipping required.
- No physical inventory.
- Quantity must be one per membership purchase.
- Only purchased for the logged-in user's own account.
- If membership is active, purchasing again should be treated as renewal.
- If membership is expired, purchasing it should reactivate membership.
- Duplicate pending membership purchases must be handled safely.

Allow physical products and VIP Membership in the same cart.

A successful combined payment must activate the membership and create the physical product fulfillment requirements.

---

# PART E: CHECKOUT & ORDER MANAGEMENT

# 15. CHECKOUT SYSTEM

Checkout should be clean and straightforward.

Customer details:
- Full Name.
- Email Address.
- Phone Number.
- Shipping Address.
- City.
- State.
- Postcode.
- Order Notes.

Provide an order summary:
- Products.
- Quantities.
- Unit Prices.
- Subtotal.
- Shipping Fee.
- Discount if applicable.
- Grand Total.

If the order only contains VIP Membership, do not require a shipping address.

## 15.1 Checkout Flow

1. Customer selects products.
2. Customer adds products to cart.
3. Customer proceeds to checkout.
4. Authentication is required before payment.
5. Customer confirms order details.
6. Laravel validates stock and prices.
7. Laravel creates an order.
8. Laravel creates a payment attempt.
9. The system generates a ToyyibPay bill.
10. Customer completes payment.
11. Backend verifies payment.
12. Order status is updated.
13. Customer views the payment result.

Generate a unique order reference.

Prevent duplicate order creation caused by repeated button clicks.

## 15.2 Shipping Management

For physical products:

Administrators can configure:
- Shipping Charges.
- Shipping Availability.
- Courier Name.
- Tracking Number.
- Shipping Status.

Use configurable flat-rate shipping for the initial version.

Design the system so weight-based and location-based shipping can be added later.

Membership purchases must not incur shipping charges.

# 16. ORDER STATUS

Separate payment status from fulfillment status.

Payment statuses:
- Pending.
- Paid.
- Failed.
- Cancelled.
- Refunded.

Fulfillment statuses:
- New.
- Processing.
- Packed.
- Shipped.
- Delivered.
- Cancelled.

For membership-only orders, shipping fulfillment does not apply.

# 17. ADMIN ORDER MANAGEMENT

Administrator features:
- View All Orders.
- Search Orders.
- Filter Orders.
- View Order Details.
- View Customer Information.
- View Payment Records.
- Update Fulfillment Status.
- Add Tracking Number.
- Print Invoice.
- Export Orders.

Order records must preserve:
- Product Name.
- SKU.
- Quantity.
- Purchased Unit Price.
- Discount.
- Shipping Fee.
- Total Amount.
- Payment Reference.

Store order item snapshots.

Historical orders must remain accurate even if the administrator changes product prices later.

## 17.1 Inventory Control

Implement safe inventory management.

Requirements:
- Validate available stock.
- Prevent overselling.
- Use database transactions.
- Use row locking where required.
- Support stock reservation during checkout.
- Release expired reservations.
- Commit stock deduction only once when payment succeeds.
- Never allow negative stock.

Handle simultaneous checkout requests correctly.

---

# PART F: TOYYIBPAY PAYMENT GATEWAY

# 18. TOYYIBPAY INTEGRATION

Integrate ToyyibPay using its official API documentation.

Support:
- Sandbox Environment.
- Production Environment.
- Bill Creation.
- Payment Redirect.
- Server Callback.
- Return URL.
- Payment Verification.
- Transaction Status Checking.
- Payment Retry.
- Payment Logging.

Administrator settings:
- Payment Environment.
- User Secret Key.
- Category Code.
- Callback Configuration.
- Return URL Configuration.

Store sensitive credentials securely.

Do not expose payment secrets to Vue components.

## 18.1 Payment Processing Flow

1. Customer clicks Pay Now.
2. Laravel creates the order and payment attempt.
3. Laravel generates a bill through ToyyibPay.
4. Save the Bill Code.
5. Redirect the customer to the ToyyibPay payment page.
6. Customer completes payment.
7. ToyyibPay sends its callback.
8. Laravel validates the callback using the current official verification mechanism.
9. Laravel checks the transaction through ToyyibPay when required.
10. Verify Bill Code, external reference, payment amount, and payment status.
11. If successful, mark the payment as Paid.
12. Update order status.
13. Activate or renew VIP Membership if included in the order.
14. Dispatch notifications.

Use ToyyibPay's documented transaction-status API for server-side payment reconciliation.

## 18.2 Payment Security Rules

Mandatory:
- Validate the callback signature/hash.
- Compare hashes safely.
- Verify transaction status.
- Verify payment amount.
- Verify transaction reference.
- Verify Bill Code.
- Prevent duplicate callback processing.
- Use idempotent payment handlers.
- Use database transactions.
- Preserve payment logs.
- Handle delayed callbacks.
- Handle failed payments.
- Handle expired payment attempts.

Never mark an order Paid merely because the customer returns to the website.

Never activate membership based only on a browser redirect.

Payment handling must tolerate repeated or out-of-order callbacks.

Do not activate a membership twice for the same verified transaction.

## 18.3 Payment Pages

Provide:
- Payment Successful.
- Payment Pending.
- Payment Failed.
- Retry Payment.

Display correct payment information and order references.

## 18.4 Payment Administration

Administrators can view:
- Order Number.
- Customer.
- Payment Date.
- Amount.
- Payment Status.
- Bill Code.
- Transaction Reference.
- Payment Attempts.

Support manual reconciliation by authorized administrators.

Refund records may be managed manually in the initial release.

Do not assume automatic refunds are supported without checking the official API.

---

# PART G: ANNUAL VIP MEMBERSHIP

# 19. VIP MEMBERSHIP OVERVIEW

Create a special product:

**Annual VIP Membership**

Customers pay an annual membership fee to become VIP Members.

Membership benefits include:
- VIP Dashboard.
- VIP Programs.
- VIP Program Registration.
- Appointment Booking.
- Membership History.
- Membership Renewal.

The administrator must be able to manage membership pricing and benefits.

# 20. VIP MEMBERSHIP PRODUCT

Fields:
- Membership Plan Name.
- Annual Price.
- Description.
- Benefits.
- Featured Image.
- Duration.
- Status.

Default duration:
12 calendar months.

Allow the administrator to configure the annual fee.

Changing the current membership price must not change historical payment records.

The system initially requires one standard VIP membership plan, but the database should support future expansion to multiple plans.

# 21. VIP ACTIVATION

When a Normal Member successfully pays for VIP Membership:

1. Verify payment.
2. Create a membership record.
3. Set membership status to Active.
4. Record the activation date.
5. Calculate the expiration date.
6. Enable VIP permissions.
7. Show the VIP Dashboard.
8. Send a membership activation notification.

Example:

Activation Date: November 14, 2026.

Expiration Date: November 14, 2027.

Use consistent timezone and date calculations.

Handle calendar-month edge cases properly.

Treat the expiration timestamp as the exclusive end of the membership validity period.

# 22. VIP MEMBERSHIP RENEWAL

VIP Members can renew their membership.

## Renewal Before Expiration

If the current membership is still active, extend the expiration date by another 12 calendar months.

Example:

Current Expiry: November 14, 2027.

Renewal Payment: November 1, 2027.

New Expiry: November 14, 2028.

## Renewal After Expiration

If the membership has expired, start the new period from the successful renewal payment timestamp.

Example:

Previous Expiry: November 14, 2027.

Renewal Payment: November 20, 2027.

New Expiry: November 20, 2028.

## Renewal Rules

- Renewal requires successful payment.
- Preserve all renewal history.
- Never renew twice for the same transaction.
- Failed payments do not change membership validity.
- Prevent duplicate pending renewal purchases from causing double extensions.
- Multiple successful renewals must be processed in a consistent, serialized manner.

Default renewal method is manual.

Do not implement automatic recurring billing unless an explicitly supported recurring payment integration is added later.

# 23. VIP MEMBERSHIP EXPIRATION

Use Laravel Scheduler to process membership expiration.

Membership states:
- Active.
- Expired.
- Revoked.

Track pending purchases separately through the payment records.

When membership expires:
- VIP privileges are disabled.
- The customer retains Normal Member access.
- Order history remains available.
- Membership history remains available.
- Appointment history remains available.
- Renewal becomes available.

Do not delete expired membership records.

VIP authorization must check the actual expiration timestamp, not only a scheduled status flag.

# 24. MEMBERSHIP REMINDERS

Send automatic notifications:
- 30 days before expiry.
- 7 days before expiry.
- 1 day before expiry.
- After membership expiration.

Use Laravel Queue and Scheduler.

Track reminder deliveries to avoid duplicate messages.

# 25. VIP DASHBOARD

The VIP Dashboard must display:
- Welcome Message.
- VIP Status.
- Membership Start Date.
- Membership Expiration Date.
- Days Remaining.
- Renew Membership Button.
- Upcoming VIP Programs.
- Registered Programs.
- Upcoming Appointments.
- Pending Appointment Requests.
- Appointment History.
- Membership Payment History.
- Purchase History.
- Notifications.

Show a clear renewal message when membership is close to expiration.

---

# PART H: VIP PROGRAM MANAGEMENT

# 26. VIP PROGRAMS

Administrators can create programs exclusively for VIP Members.

Examples:
- Seminars.
- Training Programs.
- Special Events.
- VIP Gatherings.
- Exclusive Activities.

## 26.1 Program Fields

- Program ID.
- Program Title.
- Unique Slug.
- Short Description.
- Full Description.
- Featured Image.
- Additional Images.
- Program Date.
- Start Time.
- End Time.
- Location.
- Google Maps URL.
- Registration Opening Date.
- Registration Closing Date.
- Maximum Participants.
- Registration Status.
- Program Status.

Use the Vue-compatible Rich Text Editor for program descriptions.

## 26.2 Program Administration

Administrators can:
- Create Program.
- Edit Program.
- Publish Program.
- Unpublish Program.
- Archive Program.
- Cancel Program.
- View Registrations.
- View Participants.
- Export Participant List.
- Update Attendance Status.

Program statuses:
- Draft.
- Published.
- Cancelled.
- Completed.

# 27. VIP PROGRAM LISTING

Only eligible VIP Members can access VIP-exclusive programs.

Display:
- Program Image.
- Program Name.
- Program Date.
- Program Time.
- Location.
- Description.
- Registration Availability.
- Available Capacity.
- Register Button.

Use dynamic program routes.

Example:

`/vip/programs/{slug}`

# 28. VIP PROGRAM REGISTRATION

Active VIP Members can register for eligible programs.

Validation:
- VIP status must be Active.
- Registration must be open.
- Program must be Published.
- Capacity must be available.
- User must not already be registered.

Once registered:
- Save registration.
- Display confirmation.
- Send notification.
- Add program to My Programs.

Use database transactions and locking to prevent registration above program capacity.

Default policy: VIP membership must remain valid on the program date.

Administrators must be able to review program participants and mark attendance.

Programs are free for VIP Members in the first version.

Allow the database structure to support paid programs in the future.

---

# PART I: VIP APPOINTMENT BOOKING

# 29. APPOINTMENT BOOKING OVERVIEW

Build a complete appointment booking system exclusively for Active VIP Members.

VIP Members can:
- View Appointment Calendar.
- View Available Dates.
- View Available Time Slots.
- Select Appointment Date.
- Select Appointment Time.
- Submit Booking Request.
- View Booking Status.
- View Appointment History.
- Cancel Appointment.
- Request Rescheduling.

**Every appointment requires administrator approval.**

Appointments are free for VIP Members in the initial version.

No ToyyibPay payment is required for appointments.

# 30. APPOINTMENT SERVICES

The administrator can create different appointment services.

Example:
- General Consultation.
- Private Consultation.
- VIP Meeting.
- Personal Appointment.

Service fields:
- Service Name.
- Description.
- Duration.
- Default Capacity.
- Location.
- Active Status.

Administrators can create, edit, activate, or deactivate services.

A service can have its own appointment schedule if needed.

# 31. MANUAL APPOINTMENT SLOT CREATION

Administrators can manually create appointment slots.

Fields:
- Appointment Service.
- Appointment Date.
- Start Time.
- End Time.
- Capacity.
- Location.
- Notes.
- Availability Status.

Admin actions:
- Create Slot.
- Edit Slot.
- Disable Slot.
- Delete Unused Slot.
- View Slot Bookings.

Do not allow modification of approved appointments without appropriate rescheduling or cancellation handling.

# 32. AUTOMATIC APPOINTMENT SLOT GENERATOR

**This is a required feature.**

Administrators must be able to generate multiple appointment slots automatically.

Configuration:
- Appointment Service.
- Working Days.
- Working Hours.
- Break Time.
- Appointment Duration.
- Slot Capacity.
- Date Range.
- Minimum Booking Notice.
- Maximum Advance Booking Period.
- Blocked Dates.

Example:

Working Days: Monday to Thursday.

Working Hours: 9:00 AM to 12:00 PM.

Appointment Duration: 30 Minutes.

Generated Slots:
- 9:00 AM – 9:30 AM.
- 9:30 AM – 10:00 AM.
- 10:00 AM – 10:30 AM.
- 10:30 AM – 11:00 AM.
- 11:00 AM – 11:30 AM.
- 11:30 AM – 12:00 PM.

The system must:
- Generate slots based on the configuration.
- Skip break periods.
- Skip blocked dates.
- Prevent duplicate slot generation.
- Detect overlapping slots for the same service/resource.
- Preserve existing confirmed appointments.
- Allow administrators to review generated slots.

Use database constraints where appropriate to prevent duplicate records.

# 33. VIP APPOINTMENT CALENDAR

Create a Vue 3 interactive appointment booking calendar.

Page:

**Book Appointment**

Features:
- Monthly Calendar.
- Available Date Indicators.
- Time Slot Selection.
- Appointment Service Selection.
- Location Details.
- Booking Form.
- Booking Confirmation.

When selecting a date, dynamically display the available time slots.

Do not reload the entire page unnecessarily.

Display loading indicators while fetching availability.

Clearly indicate:
- Available.
- Fully Booked.
- Unavailable.
- Selected.

# 34. APPOINTMENT BOOKING FORM

Fields:
- Appointment Service.
- Appointment Date.
- Appointment Time.
- Purpose of Appointment.
- Additional Notes.

Use authenticated customer information automatically.

After submission:
1. Validate Active VIP membership.
2. Validate booking permissions.
3. Validate appointment date and time.
4. Validate availability.
5. Prevent duplicate active requests.
6. Save appointment.
7. Generate unique booking reference.
8. Set status to Pending.
9. Notify administrator.
10. Display confirmation.

Confirmation message:

"Your appointment request has been submitted successfully and is awaiting administrator approval."

Do not display an appointment as Confirmed until it has been approved.

# 35. APPOINTMENT ADMIN APPROVAL

Create an administrator page:

**Appointment Requests**

Display:
- Booking Reference.
- VIP Name.
- Email.
- Phone.
- VIP Expiration Date.
- Appointment Service.
- Appointment Date.
- Appointment Time.
- Purpose.
- Booking Status.
- Request Date.

Administrator actions:
- View Details.
- Approve Booking.
- Reject Booking.
- Propose Alternative Slot.
- Cancel Appointment.
- Mark as Completed.
- Mark as No Show.

## 35.1 Approval Workflow

1. VIP submits a booking request.
2. Status is Pending.
3. Administrator receives notification.
4. Administrator reviews the request.
5. Administrator approves or rejects the request.

## 35.2 When Approved

- Revalidate membership eligibility.
- Revalidate appointment capacity.
- Check for time conflicts.
- Lock the relevant database records.
- Update status to Approved.
- Update effective availability.
- Store approval timestamp and administrator ID.
- Send approval notification.
- Show appointment in the customer's Upcoming Appointments.

## 35.3 When Rejected

- Administrator may enter a rejection reason.
- Status becomes Rejected.
- Store reviewer information.
- Release any relevant hold.
- Notify the customer.
- Allow the customer to request another slot.

# 36. APPOINTMENT STATUS

Supported statuses:

- Pending.
- Approved.
- Rejected.
- Cancelled.
- Completed.
- No Show.

Use clear status badges.

Store appointment status history:
- Previous Status.
- New Status.
- Changed By.
- Change Timestamp.
- Remarks.

# 37. DOUBLE BOOKING PREVENTION

This is a critical requirement.

Rules:
- Approved bookings must never exceed the configured slot capacity.
- A VIP Member cannot have overlapping active appointments.
- Fully booked slots cannot accept new requests.
- Pending requests are not considered approved appointments.
- Multiple pending requests may exist for the same slot.
- Approval must revalidate remaining capacity.
- Concurrent approvals must be handled safely.
- A VIP Member cannot submit duplicate active requests for the same slot.

Default behavior:

Pending bookings do not reserve a slot.

If two members request the final available appointment slot, both may remain Pending, but only one can be Approved.

Use database transactions and row-level locking during approval.

Do not rely only on frontend availability checks.

A slot that becomes full must return a clear error if another approval is attempted.

# 38. APPOINTMENT ELIGIBILITY

Only Active VIP Members can book appointments.

Normal Members:
- Cannot access VIP booking pages.
- Cannot submit appointment requests.
- Cannot access protected booking endpoints.

Expired VIP Members:
- Cannot create new appointments.
- Cannot request rescheduling.
- Can view appointment history.
- Can renew VIP Membership.

Default policy:

Membership must be Active when the booking is submitted and remain valid on the scheduled appointment date.

If membership expires before the appointment date, the user must renew before the appointment can be approved.

Validate all rules on the Laravel backend.

# 39. APPOINTMENT RESCHEDULING

VIP Members can request a different appointment slot.

Workflow:
1. Open an existing appointment.
2. Click Request Reschedule.
3. Select a new date and time.
4. Enter a reason.
5. Submit request.
6. Administrator reviews.
7. Administrator approves or rejects.

The original appointment remains confirmed until the reschedule is approved.

Upon approval:
- Validate the new slot.
- Lock affected records.
- Move the booking.
- Release the old slot.
- Update availability.
- Send notification.

Use one database transaction for the operation.

# 40. APPOINTMENT CANCELLATION

Allow VIP Members to cancel appointments according to configurable rules.

Administrator cancellation must also be supported.

Save:
- Cancelled By.
- Cancellation Reason.
- Cancellation Timestamp.

When a confirmed appointment is cancelled, the slot becomes available again.

Default cancellation notice may be 24 hours.

Allow administrators to configure the cancellation notice.

# 41. ADMIN APPOINTMENT CALENDAR

Build an interactive Vue 3 administrator calendar.

Support:
- Month View.
- Week View.
- Day View.
- List View.

Display:
- Appointment Requests.
- Approved Appointments.
- Completed Appointments.
- Blocked Dates.
- Fully Booked Slots.

Allow clicking calendar entries to open details.

Provide filters:
- Appointment Service.
- Status.
- Date Range.

# 42. MY APPOINTMENTS

VIP Dashboard menu:

**My Appointments**

Display:
- Pending Requests.
- Upcoming Appointments.
- Approved Appointments.
- Past Appointments.
- Cancelled Appointments.

Each appointment displays:
- Booking Reference.
- Date.
- Time.
- Service.
- Location.
- Purpose.
- Status.
- Administrator Remarks.

Available actions:
- View Appointment.
- Cancel Appointment.
- Request Reschedule.

Only show actions permitted by the appointment status and booking policy.

---

# PART J: CUSTOMER DASHBOARD

# 43. NORMAL MEMBER DASHBOARD

Provide:
- Dashboard Overview.
- My Profile.
- My Addresses.
- My Orders.
- Order Details.
- Payment History.
- Shipping Tracking.
- Change Password.
- Upgrade to VIP.

Normal Members must not access VIP Programs or VIP Appointments.

Show an attractive Upgrade to VIP section with benefits and annual membership pricing.

# 44. VIP MEMBER DASHBOARD

Provide:
- VIP Status.
- Membership Expiration Date.
- Days Remaining.
- Renew Membership.
- VIP Programs.
- My Program Registrations.
- Book Appointment.
- My Appointments.
- Upcoming Appointments.
- Membership Payments.
- Order History.
- Profile Settings.
- Notifications.

Use an organized dashboard layout with easy navigation.

---

# PART K: ADMINISTRATOR PANEL

# 45. CUSTOM ADMIN PANEL

Build the entire administrator interface using:

**Laravel + Inertia.js + Vue 3 + TypeScript + Tailwind CSS**

Do not use Livewire or Filament.

Use a reusable administrator layout.

Recommended layout:
- Left Sidebar.
- Top Navigation.
- Main Content Area.
- User Profile Menu.
- Notification Icon.
- Collapsible Navigation.
- Responsive Mobile Sidebar.

Create reusable Vue components for:
- Data Tables.
- Search Inputs.
- Filters.
- Pagination.
- Forms.
- Dialogs.
- Confirmation Modals.
- Date Pickers.
- File Uploads.
- Rich Text Editors.
- Status Badges.
- Dashboard Cards.
- Charts.
- Calendar Views.

Use consistent styling.

# 46. ADMIN DASHBOARD OVERVIEW

Display summary cards:

- Total Customers.
- Total Normal Members.
- Active VIP Members.
- Expired VIP Members.
- VIP Expiring Soon.
- Total Products.
- Total Orders.
- Pending Orders.
- Total Sales.
- Membership Revenue.
- Upcoming VIP Programs.
- Pending Appointment Requests.
- Today's Appointments.
- Upcoming Appointments.

Charts:
- Monthly Sales.
- Membership Revenue.
- New VIP Members.
- VIP Renewals.
- Order Trends.
- Appointment Statistics.

Provide date range filters.

Calculate financial KPIs using verified payment records.

Exclude failed or pending payments from paid revenue.

# 47. ADMIN MENU STRUCTURE

### Dashboard

### Products
- All Products.
- Add Product.
- Categories.
- Inventory.

### Orders
- All Orders.
- Pending Orders.
- Shipping Management.

### Payments
- Transactions.
- Payment Attempts.
- Payment History.

### Customers
- All Customers.
- Customer Profiles.

### VIP Memberships
- Active VIP Members.
- Expired VIP Members.
- Expiring Soon.
- Membership History.
- Membership Plans.

### VIP Programs
- All Programs.
- Add Program.
- Registrations.
- Participants.

### Appointments
- Appointment Calendar.
- Booking Requests.
- Approved Appointments.
- Appointment Services.
- Appointment Slots.
- Generate Slots.
- Blocked Dates.
- Appointment Settings.

### Reports
- Sales Reports.
- Membership Reports.
- Customer Reports.
- Appointment Reports.

### Website Content
- Pages.
- Homepage Banners.

### Notifications

### Settings

# 48. CUSTOMER MANAGEMENT

Administrators can:
- View Customers.
- Search Customers.
- Filter by Membership.
- View Customer Profile.
- View Orders.
- View Membership History.
- View Program Registrations.
- View Appointment History.

Customer table columns:
- Name.
- Email.
- Phone.
- Membership Status.
- Registration Date.
- Total Orders.

# 49. VIP MANAGEMENT

Administrators must be able to clearly identify Active and Expired VIP Members.

VIP table columns:
- Member Name.
- Email.
- Phone.
- Membership Start Date.
- Membership Expiration Date.
- Days Remaining.
- Status.
- Last Payment.
- Renewal Count.

Filters:
- All VIP Members.
- Active.
- Expired.
- Expiring Within 30 Days.
- Expiring Within 7 Days.

Administrator actions:
- View Details.
- View Membership History.
- View Payments.
- View Programs.
- View Appointments.

Support authorized manual membership expiry adjustments.

Every manual adjustment must:
- Require a reason.
- Record the administrator.
- Record old and new values.
- Create an audit log entry.

Do not permit unrecorded membership changes.

---

# PART L: CONTENT MANAGEMENT SYSTEM

# 50. WEBSITE CONTENT MANAGEMENT

Administrators must be able to update public website content without editing source code.

Pages:
- Homepage Banners.
- About Us.
- Contact Us.
- Terms & Conditions.
- Privacy Policy.
- Shipping Policy.
- Refund Policy.
- VIP Membership Benefits.

Page fields:
- Title.
- Slug.
- Rich Text Content.
- SEO Title.
- Meta Description.
- Publish Status.

Use the same Vue-compatible Rich Text Editor where appropriate.

A complex drag-and-drop page builder is not required for the first release.

---

# PART M: NOTIFICATION SYSTEM

# 51. NOTIFICATIONS

Implement:
- In-App Notifications.
- Email Notifications.

Use Laravel Notifications and Queue.

## E-Commerce Notifications

- Welcome Email.
- Order Confirmation.
- Payment Successful.
- Payment Failed.
- Order Shipped.

## VIP Membership Notifications

- VIP Membership Activated.
- Membership Renewal Successful.
- Membership Expiring Soon.
- Membership Expired.

## VIP Program Notifications

- New VIP Program Published.
- Program Registration Confirmed.
- Program Cancelled.

## Appointment Notifications

- Booking Request Submitted.
- New Booking Request for Admin.
- Appointment Approved.
- Appointment Rejected.
- Reschedule Requested.
- Reschedule Approved.
- Reschedule Rejected.
- Appointment Cancelled.
- Appointment Reminder: 24 Hours Before.
- Appointment Reminder: 1 Hour Before.

Track notification deliveries.

Avoid duplicate messages.

Use background queues for email delivery.

WhatsApp API integration is optional for future development.

---

# PART N: DATABASE ARCHITECTURE

# 52. DATABASE DESIGN

Use normalized MySQL relational tables.

Recommended tables:

## Users
- users
- addresses

## Products
- categories
- products
- product_images
- product_variants

## Shopping
- carts
- cart_items
- orders
- order_items
- payments
- payment_callbacks
- shipments
- stock_reservations

## Memberships
- membership_plans
- memberships
- membership_transactions
- membership_adjustments

## VIP Programs
- vip_programs
- vip_program_images
- vip_program_registrations

## Appointments
- appointment_services
- appointment_schedules
- appointment_slots
- appointment_blocked_dates
- appointments
- appointment_reschedule_requests
- appointment_status_histories

## Content
- pages
- banners
- settings

## System
- notifications
- activity_logs

Create framework-required authentication, session, cache, queue, and failed-job tables as appropriate.

# 53. DATABASE REQUIREMENTS

Use:
- Primary Keys.
- Foreign Keys.
- Indexes.
- Unique Constraints.
- Eloquent Relationships.
- Proper Column Types.
- Timestamps.
- Soft Deletes where appropriate.

Store monetary values in integer minor units or accurately defined decimals.

Never use floating-point values for financial calculations.

Store prices as historical snapshots in order items.

Store payment provider references separately from internal order references.

Maintain membership renewal history.

Maintain appointment status history.

Do not permanently delete critical financial records.

# 54. REQUIRED MODEL RELATIONSHIPS

Design relationships including:

User:
- Has many Orders.
- Has many Addresses.
- Has many Membership Records.
- Has many Appointments.
- Has many Program Registrations.

Product:
- Belongs to Category.
- Has many Product Images.
- Has many Variants.
- Has many Order Items.

Order:
- Belongs to User.
- Has many Order Items.
- Has many Payment Attempts.
- May have a Shipment.

Membership:
- Belongs to User.
- Belongs to Membership Plan.
- Has related Membership Transactions.

VIP Program:
- Has many Registrations.
- Has many Images.

Appointment Service:
- Has many Appointment Slots.
- Has many Appointments.

Appointment:
- Belongs to User.
- Belongs to Appointment Service.
- Belongs to Appointment Slot.
- Has many Status History Records.
- Has many Reschedule Requests.

Use appropriate constraints to maintain data integrity.

---

# PART O: APPLICATION ARCHITECTURE

# 55. LARAVEL ARCHITECTURE

Use a clean and maintainable structure.

Recommended backend directories:

`app/Http/Controllers`

`app/Http/Requests`

`app/Http/Middleware`

`app/Models`

`app/Services`

`app/Policies`

`app/Actions`

`app/Events`

`app/Listeners`

`app/Jobs`

`app/Notifications`

`app/Enums`

`app/Observers`

`app/Console/Commands`

Separate important business logic into dedicated services or actions.

Suggested services:
- CartService.
- CheckoutService.
- OrderService.
- InventoryService.
- ToyyibPayService.
- PaymentVerificationService.
- MembershipService.
- MembershipRenewalService.
- VIPProgramService.
- AppointmentService.
- AppointmentSlotGeneratorService.
- AppointmentApprovalService.
- NotificationService.

Controllers should remain thin.

Avoid placing all business logic directly in controllers.

# 56. INERTIA.JS + VUE 3 STRUCTURE

Recommended frontend structure:

`resources/js/`

Include:

- app.ts
- Pages/
- Components/
- Layouts/
- Composables/
- Types/
- Utils/
- Stores/

Suggested page organization:

`Pages/Public/`

- Home.vue
- About.vue
- Contact.vue

`Pages/Products/`

- Index.vue
- Show.vue

`Pages/Cart/`

- Index.vue

`Pages/Checkout/`

- Index.vue
- Success.vue
- Pending.vue
- Failed.vue

`Pages/Auth/`

- Login.vue
- Register.vue
- ForgotPassword.vue

`Pages/Customer/`

- Dashboard.vue
- Profile.vue
- Addresses.vue
- Orders.vue
- OrderDetails.vue

`Pages/VIP/`

- Dashboard.vue
- Membership.vue
- Renew.vue
- Programs/
- Appointments/

`Pages/Admin/`

- Dashboard.vue
- Products/
- Categories/
- Orders/
- Payments/
- Customers/
- Memberships/
- Programs/
- Appointments/
- Reports/
- Pages/
- Settings/

Suggested layouts:

`Layouts/PublicLayout.vue`

`Layouts/CustomerLayout.vue`

`Layouts/VIPLayout.vue`

`Layouts/AdminLayout.vue`

Suggested reusable components:
- AppHeader.vue
- AppFooter.vue
- ProductCard.vue
- ProductGallery.vue
- ShoppingCartDrawer.vue
- DataTable.vue
- Pagination.vue
- StatusBadge.vue
- ConfirmDialog.vue
- ImageUploader.vue
- RichTextEditor.vue
- AppointmentCalendar.vue
- TimeSlotPicker.vue
- DashboardStatCard.vue

Use Vue Composition API with `<script setup lang="ts">`.

Use TypeScript consistently for frontend business models and component props.

# 57. FRONTEND STATE MANAGEMENT

Use Inertia.js for server-driven navigation and page data.

Use Vue reactive state, composables, and an optional lightweight store such as Pinia when shared client-side state is truly needed.

Examples:
- Cart UI State.
- Mobile Navigation.
- Notification UI.
- Appointment Calendar Selection.

Keep authoritative business data in Laravel and MySQL.

Never treat frontend state as the source of truth for:
- Product Prices.
- Payment Status.
- Stock Availability.
- Membership Eligibility.
- Appointment Approval.
- VIP Privileges.

Use Inertia `useForm` for forms.

Support:
- Form Validation.
- Loading State.
- Error State.
- Success State.
- Disabled Submit Buttons.
- Error Persistence.
- Navigation State Preservation where appropriate.

---

# PART P: SECURITY

# 58. SECURITY REQUIREMENTS

Implement:

- Secure Authentication.
- Password Hashing.
- CSRF Protection.
- Laravel Policies.
- Role-Based Access Control.
- VIP Membership Authorization.
- Request Validation.
- Rate Limiting.
- SQL Injection Protection.
- XSS Protection.
- Secure File Upload.
- Session Security.
- Audit Logging.
- Secure Payment Verification.

Customers must not be able to access:
- Other customers' orders.
- Other customers' appointments.
- Other customers' profiles.
- Administrator functions.

Normal Members must not access VIP-only functionality.

Expired VIP Members must not use restricted VIP functionality.

Use Laravel Policies and middleware for authorization.

Every restricted action must be checked on the server.

Do not rely on hidden Vue buttons or frontend route guards as the only security mechanism.

Protect ToyyibPay credentials.

Never commit `.env` or secrets to source control.

Avoid exposing unnecessary personal information through Inertia shared props.

---

# PART Q: PERFORMANCE OPTIMIZATION

# 59. PERFORMANCE REQUIREMENTS

The website must feel smooth and responsive.

Implement:
- Inertia Navigation.
- Vue Component Reusability.
- Lazy Loading.
- Image Optimization.
- Database Indexing.
- Eloquent Eager Loading.
- Pagination.
- Caching.
- Queue Processing.
- Optimized Assets.
- Vite Production Build.

Avoid:
- N+1 Queries.
- Large Unpaginated Queries.
- Duplicate API Requests.
- Unnecessary Full Page Reloads.
- Unnecessary Frontend Re-renders.
- Blocking Network Requests.
- Repeated Payment Processing.

Use efficient queries for dashboards and reports.

Do not cache sensitive payment or membership authorization data without correct expiration and invalidation.

# 60. USER EXPERIENCE REQUIREMENTS

The application must provide:

- Loading Indicators.
- Skeleton Loaders.
- Toast Notifications.
- Confirmation Dialogs.
- Helpful Error Messages.
- Success Feedback.
- Empty States.
- Responsive Tables.
- Mobile-Friendly Forms.
- Clear Navigation.
- Accessible Interactive Controls.

Examples:

When a customer clicks Add to Cart:
- Update the cart without a full page refresh.
- Update the cart counter.
- Display a success message.

When a VIP submits an appointment:
- Disable the submit button while processing.
- Show loading feedback.
- Save the booking.
- Display Pending Approval.

When an administrator approves an appointment:
- Update the status.
- Refresh affected appointment data.
- Show confirmation.
- Trigger notification.

Prevent duplicate requests caused by repeated clicks.

---

# PART R: ADMIN SETTINGS

# 61. GENERAL SETTINGS

Allow administrators to configure the website.

## Website Settings
- Website Name.
- Logo.
- Favicon.
- Contact Email.
- Contact Phone.
- Address.
- Social Media Links.
- Footer Information.

## E-Commerce Settings
- Currency.
- Shipping Charges.
- Inventory Settings.
- Order Settings.

## Membership Settings
- Annual Membership Price.
- Membership Duration.
- Membership Benefits.
- Expiration Reminder Settings.

## Appointment Settings
- Working Days.
- Working Hours.
- Break Time.
- Default Appointment Duration.
- Default Slot Capacity.
- Booking Advance Limit.
- Minimum Booking Notice.
- Cancellation Policy.
- Appointment Reminders.
- Maximum Active Bookings per VIP.
- Membership Eligibility Policy.

## Payment Settings
- ToyyibPay Environment.
- Category Code.
- Payment Configuration.

Handle payment credentials securely and prevent unauthorized disclosure.

---

# PART S: TESTING REQUIREMENTS

# 62. AUTOMATED BACKEND TESTS

Use Pest or PHPUnit.

Required tests:

## Authentication
1. Registration.
2. Login.
3. Password Reset.
4. Administrator Access Restriction.

## Product Management
5. Create Product.
6. Update Product.
7. Archive Product.
8. Multiple Image Upload.
9. Product Detail Editor.
10. Inventory Validation.

## Shopping
11. Add to Cart.
12. Update Quantity.
13. Remove from Cart.
14. Checkout Calculation.
15. Stock Reservation.
16. Prevent Overselling.

## Payments
17. Create ToyyibPay Bill.
18. Payment Success.
19. Payment Failure.
20. Duplicate Callback.
21. Invalid Callback.
22. Amount Mismatch.
23. Retry Payment.
24. Payment Reconciliation.

## Membership
25. VIP Activation.
26. VIP Renewal Before Expiry.
27. VIP Renewal After Expiry.
28. Membership Expiration.
29. Duplicate Renewal Prevention.
30. Expired VIP Access Restriction.

## VIP Programs
31. Program Registration.
32. Duplicate Registration Prevention.
33. Program Capacity.
34. Expired VIP Restriction.

## Appointments
35. Normal Member Cannot Book.
36. Expired VIP Cannot Book.
37. Active VIP Can Submit Booking.
38. Booking Defaults to Pending.
39. Administrator Can Approve.
40. Administrator Can Reject.
41. Double Booking Prevention.
42. Concurrent Approval.
43. Slot Capacity.
44. Rescheduling.
45. Cancellation.
46. Appointment Reminder.
47. Blocked Date Validation.
48. Membership Expiry Validation.

# 63. FRONTEND TESTING

Include testing for Vue components and important user flows.

Test:
- Product Listing.
- Add to Cart.
- Cart Updates.
- Checkout Forms.
- Membership Upgrade.
- Appointment Calendar.
- Booking Submission.
- Admin Approval Actions.
- Status Updates.
- Mobile Navigation.

Use a compatible Vue testing solution and browser end-to-end testing framework where appropriate.

Run payment integration tests in ToyyibPay Sandbox.

Do not make real payments during development.

---

# PART T: DEVELOPMENT PHASES

# 64. DEVELOPMENT ROADMAP

Build the system in phases.

## PHASE 1: PROJECT FOUNDATION

Tasks:
- Create Laravel Project.
- Install Official Vue Starter Kit.
- Configure Inertia.js.
- Configure Vue 3 and TypeScript.
- Configure Tailwind CSS.
- Configure MySQL.
- Implement Authentication.
- Implement Administrator Role.
- Create Public Layout.
- Create Customer Layout.
- Create VIP Layout.
- Create Admin Layout.
- Configure Routes.
- Create Initial Migrations.

Deliverable:

A working Laravel application with authentication, user roles, Vue layouts, and administrator access.

## PHASE 2: PRODUCT MANAGEMENT

Tasks:
- Product Database.
- Category Database.
- Product CRUD.
- Multiple Image Upload.
- Image Reordering.
- Rich Text Editor.
- Product Variations.
- Product Listing.
- Product Details.
- Product Search.
- Product Filters.

Deliverable:

Administrators can manage real products and customers can browse product pages.

## PHASE 3: SHOPPING CART & CHECKOUT

Tasks:
- Shopping Cart.
- Cart Persistence.
- Quantity Updates.
- Checkout.
- Address Management.
- Order Creation.
- Stock Reservation.
- Shipping Settings.

Deliverable:

Customers can create valid orders.

## PHASE 4: TOYYIBPAY PAYMENT GATEWAY

Tasks:
- ToyyibPay Configuration.
- Bill Creation.
- Payment Redirect.
- Callback Handling.
- Payment Verification.
- Payment Reconciliation.
- Payment Result Pages.
- Payment Retry.
- Payment History.

Deliverable:

Customers can complete sandbox payments and the system reliably updates order payment status.

## PHASE 5: VIP MEMBERSHIP

Tasks:
- Membership Product.
- Membership Checkout.
- Membership Activation.
- VIP Dashboard.
- Membership Renewal.
- Membership Expiration.
- Expiry Notifications.
- VIP Member Administration.

Deliverable:

Membership purchases, renewals, and expiration work automatically.

## PHASE 6: VIP PROGRAMS

Tasks:
- VIP Program CRUD.
- Rich Text Program Description.
- Program Images.
- Program Listing.
- VIP Access Restriction.
- Program Registration.
- Participant Management.

Deliverable:

Administrators can organize programs and VIP Members can register.

## PHASE 7: VIP APPOINTMENT BOOKING

Tasks:
- Appointment Services.
- Appointment Schedules.
- Manual Slot Creation.
- Automatic Slot Generation.
- Appointment Calendar.
- VIP Booking Form.
- Pending Requests.
- Administrator Approval.
- Administrator Rejection.
- Double Booking Protection.
- Rescheduling.
- Cancellation.
- Appointment Notifications.
- Admin Calendar.

Deliverable:

VIP Members can book appointments and administrators can approve or reject requests safely.

## PHASE 8: CMS & REPORTING

Tasks:
- Homepage Banner Management.
- Website Content Pages.
- Reports.
- Dashboard Statistics.
- Sales Charts.
- Membership Reports.
- Appointment Reports.

Deliverable:

Administrators can manage website content and view reports.

## PHASE 9: SECURITY & PERFORMANCE

Tasks:
- Authorization Review.
- Security Testing.
- Payment Security Review.
- Database Optimization.
- Query Optimization.
- Frontend Optimization.
- Responsive UI Review.
- Queue Reliability.
- Scheduler Configuration.

Deliverable:

A secure and optimized application.

## PHASE 10: FINAL TESTING & DEPLOYMENT

Tasks:
- Automated Tests.
- End-to-End Testing.
- ToyyibPay Sandbox Testing.
- Bug Fixes.
- Production Configuration.
- Database Backup Setup.
- Queue Worker Setup.
- Scheduler Setup.
- Deployment Documentation.

Deliverable:

A production-ready Laravel application with deployment instructions.

---

# PART U: REQUIRED DELIVERABLES

# 65. PROJECT OUTPUT

Generate a complete, functional Laravel project.

Required files and components:
- Laravel Application.
- Inertia.js Configuration.
- Vue 3 Pages.
- TypeScript Types.
- Vue Components.
- Vue Layouts.
- Laravel Controllers.
- Laravel Form Requests.
- Laravel Services.
- Laravel Models.
- Laravel Policies.
- Laravel Middleware.
- Database Migrations.
- Seeders.
- Routes.
- Notifications.
- Queue Jobs.
- Scheduled Tasks.
- Payment Integration.
- Automated Tests.
- `.env.example`.
- README.
- Installation Instructions.
- Deployment Instructions.

Provide realistic sample data for development.

Create a secure administrator seeding process without hardcoded production passwords.

Do not produce only UI mockups.

The application must work with a real database and functional business logic.

---

# PART V: IMPORTANT AI CODING INSTRUCTIONS

# 66. CODING RULES

Follow these rules strictly:

1. Use Laravel as the backend.
2. Use Inertia.js and Vue 3 for the frontend.
3. Use TypeScript for Vue development.
4. Use Tailwind CSS for styling.
5. Build the administrator panel using Vue 3 and Inertia.js.
6. Do not use Livewire.
7. Do not use Filament.
8. Use English for every user-facing interface.
9. Use English for validation and error messages.
10. Use clean Laravel architecture.
11. Use reusable Vue components.
12. Use service classes for complex business logic.
13. Use Laravel Form Requests for validation.
14. Use Policies for authorization.
15. Use database transactions for critical operations.
16. Keep controllers lightweight.
17. Never hardcode payment credentials.
18. Never trust product prices sent from the browser.
19. Prevent duplicate checkout submissions.
20. Prevent duplicate payment processing.
21. Prevent duplicate membership activation.
22. Prevent incorrect membership renewal.
23. Prevent double appointment booking.
24. Enforce VIP access restrictions on the server.
25. Preserve historical orders and payments.
26. Implement real database functionality.
27. Do not generate fake buttons or placeholder actions.
28. Handle loading, success, and error states.
29. Optimize the website for mobile.
30. Use consistent styling across all pages.
31. Validate all file uploads.
32. Sanitize rich text content.
33. Use database indexes and efficient queries.
34. Document important implementation decisions.
35. Ensure code can be maintained and extended.
36. Use the latest compatible and supported package versions.
37. Do not introduce unnecessary dependencies.
38. Do not implement unverified payment gateway capabilities.
39. Test each completed module.
40. Fix errors before moving to the next phase.

# 67. IMPLEMENTATION PROCESS

Before writing application code, perform the following:

### Step 1: Architecture Planning

Provide:
- Overall System Architecture.
- Technology Compatibility Review.
- Module Structure.
- Database Schema.
- Model Relationships.
- Authentication Design.
- Authorization Design.
- Payment Flow.
- Membership Lifecycle.
- Appointment Booking Flow.

### Step 2: Project Structure

Provide:
- Laravel Folder Structure.
- Vue Folder Structure.
- Inertia Page Structure.
- Controller Structure.
- Service Structure.
- Route Structure.
- Middleware Structure.

### Step 3: Database Design

Create migrations with:
- Proper Foreign Keys.
- Unique Constraints.
- Relevant Indexes.
- Appropriate Data Types.
- Referential Integrity.

### Step 4: Module Implementation

Implement one phase at a time.

For each phase:
1. Build the backend.
2. Build the Vue frontend.
3. Connect frontend to backend.
4. Implement validation.
5. Implement authorization.
6. Implement actual database operations.
7. Run tests.
8. Fix errors.
9. Verify functionality before continuing.

### Step 5: Integration

Ensure all modules work together:

- Shopping Cart integrates with Checkout.
- Checkout integrates with ToyyibPay.
- ToyyibPay integrates with VIP Activation.
- Membership integrates with VIP Programs.
- Membership integrates with Appointment Booking.
- Appointment Booking integrates with Admin Approval.
- Notifications integrate with all important events.

Do not rebuild completed modules unnecessarily.

Do not remove existing working functionality when implementing new modules.

---

# 68. FINAL ACCEPTANCE CRITERIA

The project is considered complete only when all the following scenarios work.

### Scenario 1: Physical Product Purchase

A visitor can browse products, register, add several different products to the cart, pay using ToyyibPay, and view the completed order.

### Scenario 2: VIP Membership Purchase

A Normal Member can purchase VIP Membership, successfully pay through ToyyibPay, and automatically receive Active VIP access.

### Scenario 3: VIP Membership Renewal

An existing VIP Member can renew membership and receive the correct extension of their expiration date.

### Scenario 4: Membership Expiration

An expired VIP Member automatically loses VIP privileges while retaining the ability to log in and view purchase history.

### Scenario 5: VIP Program Registration

An Active VIP Member can view VIP programs and register for an available event.

### Scenario 6: VIP Appointment Booking

An Active VIP Member can open the appointment calendar, choose an available slot, and submit a booking request.

The booking status must initially be Pending.

### Scenario 7: Administrator Appointment Approval

An administrator can review the request and approve it.

The booking status becomes Approved and the VIP Member receives a notification.

### Scenario 8: Double Booking Prevention

If a slot has a capacity of one, two pending requests must not both be approved.

### Scenario 9: Administrator Product Editing

An administrator can create multiple products, upload multiple images, edit detailed product descriptions with a rich text editor, and publish the products.

### Scenario 10: Fully English Website

Every public page, customer dashboard, VIP dashboard, administrator page, notification, validation message, and system-generated label is displayed in English.

### Scenario 11: Fully Vue-Based Interface

Public pages, customer pages, VIP pages, and administrator pages all use Inertia.js and Vue 3.

No Livewire or Filament components are used.

### Scenario 12: Production Readiness

The system passes the required tests, has correct authorization, handles payment callbacks safely, performs efficiently, and includes installation and deployment documentation.

---

# 69. FINAL PROJECT OBJECTIVE

Build a complete:

**LARAVEL E-COMMERCE + ANNUAL VIP MEMBERSHIP + VIP PROGRAMS + VIP APPOINTMENT BOOKING SYSTEM**

Required core features:

- English Website.
- Laravel Backend.
- Inertia.js.
- Vue 3.
- TypeScript.
- Tailwind CSS.
- Custom Vue Administrator Panel.
- Multiple Physical Products.
- Multiple Product Images.
- Rich Text Product Detail Editor.
- Shopping Cart.
- Checkout.
- ToyyibPay Integration.
- Customer Registration.
- Normal Membership.
- Annual VIP Membership.
- VIP Activation.
- VIP Renewal.
- Automatic VIP Expiration.
- VIP Management.
- VIP Programs.
- VIP Program Registration.
- Appointment Services.
- Automatic Slot Generation.
- Manual Slot Management.
- VIP Appointment Booking.
- Administrator Booking Approval.
- Appointment Rejection.
- Double Booking Prevention.
- Rescheduling.
- Cancellation.
- Appointment Notifications.
- Customer Dashboard.
- VIP Dashboard.
- Administrator Dashboard.
- Order Management.
- Reports.
- Website Content Management.

The final system must be secure, fast, responsive, maintainable, scalable, and suitable for real business operations.

---

# 70. FINAL INSTRUCTIONS TO AI CODING AGENT

You are acting as a Senior Laravel Full-Stack Developer and Software Architect.

Your responsibility is to build the application described in this document.

Use the following mandatory technology stack:

**Laravel + Inertia.js + Vue 3 + TypeScript + Tailwind CSS + MySQL.**

Do not use Livewire or Filament.

The entire application interface must be in English.

Follow Laravel best practices and use clean, modular, production-quality code.

Do not produce a static prototype.

Do not skip important backend functionality.

Do not use dummy data as a replacement for real business logic.

Do not assume that a feature works without testing it.

Start by inspecting the existing project directory, if one exists.

Preserve existing working code unless changes are required by these specifications.

Before implementation, prepare:
1. System Architecture.
2. Database Schema.
3. Model Relationships.
4. Folder Structure.
5. Route Structure.
6. Administrator Panel Architecture.
7. Package Dependencies.
8. Development Plan.

After presenting the architecture, begin implementing Phase 1 in the actual project.

Continue phase by phase in the order specified.

At the end of each phase:
- Summarize the implementation.
- List created or modified files.
- Run relevant tests.
- Fix any detected errors.
- State whether the phase is completed.

Use the requirements in this document as the single source of truth.

**The ultimate goal is a fully functional, production-ready E-Commerce and VIP Membership Management System with ToyyibPay payments, VIP-exclusive programs, and administrator-approved appointment bookings, using Laravel, Inertia.js, and Vue 3.**
