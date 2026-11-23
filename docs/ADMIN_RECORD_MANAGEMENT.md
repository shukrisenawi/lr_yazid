# Administrator record management

All management endpoints require authentication, verified email and the administrator gate.
Existing edit screens remain the source of validation for each module.

## Delete

Edit and Delete icon buttons are available in administrator data lists. Delete
is not shown on the banner, content page, VIP program, customer, appointment or
product detail/edit screens. Products retain their existing archive-on-delete
behavior; categories retain their existing deletion rules.

Deletion opens an accessible confirmation modal with Confirm delete and Cancel
buttons, with no reason input. The administrator, module, record ID, action,
system-generated confirmation note and timestamp are recorded in `admin_record_actions`
for the record-management endpoints. Archive/Restore actions still require a reason.
Content, banners, programs, services, plans, memberships and appointments use soft
deletion, preserving images, revisions and related history.

Safety restrictions:

- Customers with transaction, membership, program or booking history cannot be deleted.
- Pending/approved appointments and pending reschedule requests must be resolved first.
- Active, unexpired memberships must have their expiry adjusted first.
- Programs with registered participants must have their registrations cancelled first.
- Services with pending/approved appointments cannot be deleted.
- Deleting a membership plan disables its associated product; existing membership
  and payment history remain intact.
- Administrators cannot be deleted through customer management.

## Orders and payments

Transactions cannot be deleted. Existing fulfillment, cancellation, refund and
payment reconciliation workflows are unchanged. Provider payment amounts and
statuses are not directly editable.

Orders and payments have Archive/Restore actions and Active/Archived/All list
filters. Archiving does not alter transaction amounts, statuses, reports,
customer history or gateway callback processing.

Orders must be cancelled, refunded or delivered before archiving. Pending or
uncertain payments and unresolved payment reviews block archival. Payment
attempts must be reconciled before archival; cancelling an order does not claim
to cancel an external gateway bill.

Singleton settings remain editable, not deletable. Reports, notifications and
audit/delivery logs are generated history rather than CRUD master data.

## Deployment and verification

Run `php artisan migrate` before serving the new frontend build.
`tests/Feature/AdminRecordManagementTest.php` covers access control, validation,
deletion guards, preserved relations, archive filters and restoration.
