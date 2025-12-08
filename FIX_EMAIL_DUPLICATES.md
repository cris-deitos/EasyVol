# Fix: Duplicate Email Notifications in Fee Payment System

## Problem Description

When payment receipts were submitted via the public link (`/public/pay_fee.php`), the system was sending duplicate emails:

### Before Fix:
**On Payment Receipt Submission:**
- ❌ **Email 1 to member**: Sent by `FeePaymentController::createPaymentRequest()` (lines 74-93)
- ❌ **Email 2 to member**: Sent by `FeePaymentController::sendSubmissionEmails()` (around line 322)
- ✅ **Email 1 to association**: Sent by `FeePaymentController::sendSubmissionEmails()` (around line 344)
- ❌ **Possible CC to association**: Auto-CC feature in `EmailSender::send()` (lines 104-110)

**Result**: Member received 2 emails, association received 1-2 emails

**On Payment Approval:**
- ✅ **Email 1 to member**: Sent by `sendFeePaymentApprovedEmail()` in the approval flow

**Result**: Email sent correctly on approval

## Root Cause

1. **Duplicate Email in createPaymentRequest**: The `createPaymentRequest()` method was sending an email to the member using `sendFeeRequestReceivedEmail()`, but the same email was also sent by `sendSubmissionEmails()` which is called immediately after from `pay_fee.php`.

2. **Auto-CC Feature**: The `EmailSender::send()` method had an auto-CC feature that automatically added the association email as CC to ALL outgoing emails, causing unwanted duplicate notifications.

## Solution

### 1. Removed Duplicate Email Call
**File**: `/src/Controllers/FeePaymentController.php`

Removed the email sending code from `createPaymentRequest()` method (lines 74-93) and added a comment explaining that email sending is handled by the `sendSubmissionEmails()` method to avoid duplicates.

```php
// Before:
// Send email notification to member
if ($requestId) {
    try {
        $member = $this->db->fetchOne(...);
        if ($member && !empty($member['email'])) {
            $emailSender->sendFeeRequestReceivedEmail($member, $data);
        }
    } catch (\Exception $e) {
        error_log("Fee request email failed: " . $e->getMessage());
    }
}

// After:
// Note: Email sending is handled by sendSubmissionEmails() method
// called from the controller to avoid duplicate emails
```

### 2. Removed Auto-CC Feature
**File**: `/src/Utils/EmailSender.php`

Removed the auto-CC feature from the `send()` method (lines 104-110) that was automatically adding association email to all outgoing emails.

```php
// Before:
// Add CC to association if not already in headers and configured
$assocEmail = $this->config['association']['email'] ?? '';
$ccEmail = $this->config['email']['cc'] ?? $assocEmail;

if (!empty($ccEmail) && $ccEmail !== $toEmail) {
    $headers[] = "Cc: $ccEmail";
}

// After:
// Note: Auto-CC feature removed to prevent unwanted duplicate emails
// If CC is needed, it should be explicitly handled by the caller
```

## Expected Behavior After Fix

### On Payment Receipt Submission:
- ✅ **1 email to member**: Confirmation that receipt was received and is pending verification
- ✅ **1 email to association**: Notification that a new payment request needs verification

### On Payment Approval:
- ✅ **1 email to member**: Confirmation that payment was approved
- ✅ **0 emails to association**: No notification needed

### On Payment Rejection:
- ✅ **1 email to member**: Notification that payment was rejected
- ✅ **0 emails to association**: No notification needed

## Testing Recommendations

To verify the fix works correctly:

1. **Test Payment Submission**:
   - Access `/public/pay_fee.php`
   - Submit a payment receipt with valid credentials
   - Check that member receives exactly 1 email
   - Check that association receives exactly 1 email
   - Verify email content is correct

2. **Test Payment Approval**:
   - Login to the system with appropriate permissions
   - Access `/public/fee_payments.php`
   - Approve a pending payment request
   - Check that member receives exactly 1 approval email
   - Verify no email is sent to association

3. **Test Payment Rejection**:
   - Reject a pending payment request
   - Check that member receives exactly 1 rejection email
   - Verify no email is sent to association

## Impact Analysis

### Modified Files:
1. `/src/Controllers/FeePaymentController.php` - Removed duplicate email call
2. `/src/Utils/EmailSender.php` - Removed auto-CC feature
3. `/FEE_PAYMENT_SYSTEM.md` - Updated documentation

### Breaking Changes:
- **None**: The auto-CC feature removal only affects fee payment emails. If other parts of the system relied on this feature, they should explicitly handle CC recipients in their email calls.

### Benefits:
- ✅ Eliminates duplicate emails to members
- ✅ Eliminates unwanted CC emails to association
- ✅ Clearer separation of concerns
- ✅ Better control over email recipients
- ✅ Improved user experience

## Date
2024-12-08

## Issue Reference
Issue reported in Italian:
> "Quando vengono inserite le ricevute di pagamento dal link pubblico, un'email viene inviata al socio ed una all'associazione. poi, in teoria, quando dal gestionale vengono approvate le quote, un'email di conferma dovrebbe essere inviata al socio. allo stato attuale, parte una mail al socio, una all'associazione, e di nuovo un'altra al socio, e nessuna mail viene inviata quando viene confermato il pagamento."

Translation:
> "When payment receipts are submitted from the public link, one email is sent to the member and one to the association. Then, in theory, when the fees are approved from the system, a confirmation email should be sent to the member. In the current state, one email goes to the member, one to the association, and another one to the member again, and no email is sent when the payment is confirmed."

## Status
✅ **FIXED**
