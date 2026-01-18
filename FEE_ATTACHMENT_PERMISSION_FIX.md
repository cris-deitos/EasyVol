# Fee Attachment Permission Fix

## Problem
Non-admin users with permission to view pending fee payments (quote associative) could not access the attachments to payment requests. When attempting to open an attachment, they received the error:
> "Non hai i permessi per accedere a questo file"

## Root Cause
Permission mismatch between `fee_payments.php` and `download.php`:
- **fee_payments.php** (line 25): Requires `members.edit` permission to access the page
- **download.php** (line 72): Was checking for `fees.view` permission (which doesn't exist in the permissions table)

## Solution
Updated `download.php` to check for `members.edit` permission for fee receipts, aligning with the permission required by `fee_payments.php`.

### Changed File
- `/public/download.php` (lines 71-72)

### Before
```php
// Admin can access
$canAccess = $app->checkPermission('fees', 'view');
```

### After
```php
// Users with members edit permission can access (same as fee_payments.php)
$canAccess = $app->checkPermission('members', 'edit');
```

## Verification
To verify this fix works correctly:

### Test Case 1: Admin User
1. Log in as an admin user
2. Navigate to "Quote Associative" page
3. View pending payment requests
4. Click on "Visualizza" button for a receipt attachment
5. **Expected**: Attachment opens successfully ✓

### Test Case 2: Non-Admin User with members.edit Permission
1. Create or use a non-admin user with `members.edit` permission
2. Log in with this user
3. Navigate to "Quote Associative" page (should be visible in sidebar)
4. View pending payment requests
5. Click on "Visualizza" button for a receipt attachment
6. **Expected**: Attachment opens successfully ✓

### Test Case 3: User without members.edit Permission
1. Create or use a user without `members.edit` permission
2. Log in with this user
3. **Expected**: "Quote Associative" menu item not visible in sidebar
4. If user tries to access the page directly via URL
5. **Expected**: "Accesso negato" error
6. If user tries to access attachment directly via download.php
7. **Expected**: "Non hai i permessi per accedere a questo file" error ✓

## Related Files
- `/public/fee_payments.php` - Main fee payment management page
- `/src/Controllers/FeePaymentController.php` - Controller for fee payments
- `/src/Views/includes/sidebar.php` - Shows menu only to users with members.edit
- `/src/Utils/NotificationHelper.php` - Shows pending fee notifications only to users with members.edit

## Permission Structure
The following permission structure is consistently used across the codebase:
- `members.edit` - Required to view and manage fee payments (quote associative)
- All fee-related functionality uses `members.edit` permission
- No separate `fees` module exists in the permissions table

## Database Changes
**None required** - This is a code-only fix that aligns permission checks without modifying the database schema.
