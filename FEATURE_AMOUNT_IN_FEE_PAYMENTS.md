# Feature: Amount Field in Fee Payment System

## Overview
This feature adds the ability to track payment amounts in the fee payment system. When members upload their payment receipts through the public form, they can now specify the amount paid, and this amount is displayed in the admin management interface and transferred to the member's record when approved.

## Changes Made

### 1. Database Schema
- **Added field**: `amount` (DECIMAL 10,2) to `fee_payment_requests` table
- **Location**: After `payment_date` field
- **Migration**: `migrations/add_amount_to_fee_payment_requests.sql`

```sql
ALTER TABLE `fee_payment_requests` 
ADD COLUMN `amount` DECIMAL(10,2) DEFAULT NULL COMMENT 'Importo pagato' 
AFTER `payment_date`;
```

### 2. Public Upload Form (`public/pay_fee.php`)

#### New Field Added (Step 2)
```html
<div class="mb-3">
    <label for="amount" class="form-label">Importo Pagato (€) *</label>
    <input type="number" class="form-control" id="amount" 
           name="amount" required step="0.01" min="0.01"
           value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
    <div class="form-text">
        Inserire l'importo effettivamente pagato per questa quota
    </div>
</div>
```

#### New Warning Notice
A yellow alert box has been added to inform users about shared payments:
```html
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i> <strong>Attenzione:</strong><br>
    Nel caso in cui un pagamento unico copra la quota di due o più soci, 
    la ricevuta dovrà essere caricata per ogni socio singolarmente.
</div>
```

#### Validation
- Amount field is required
- Must be numeric
- Must be greater than 0
- Error message: "L'importo pagato è obbligatorio e deve essere maggiore di zero"

### 3. FeePaymentController Updates

#### Method: `createPaymentRequest()`
- Now accepts `amount` in the `$data` array
- Stores the amount in the database

#### Method: `approvePaymentRequest()`
- Transfers the amount to the `member_fees` table when approving a request
- The amount is linked to the member's fee record

#### Method: `sendSubmissionEmails()`
- Includes amount in confirmation emails to both member and association
- Format: "Importo: €XX,XX" (Italian number format)
- Properly escaped with `htmlspecialchars()` for security

### 4. Admin Management Page (`public/fee_payments.php`)

#### New Column
A new "Importo" column has been added to the requests table:
- Position: Between "Data Pagamento" and "Data Invio"
- Display format: €XX,XX (Italian currency format)
- Shows "N/A" if amount is not provided (for backward compatibility)

#### Table Headers
```
Matricola | Socio | Anno | Data Pagamento | Importo | Data Invio | Stato | Ricevuta | Azioni
```

## User Flow

### Public User (Member) Flow:
1. Navigate to `pay_fee.php`
2. Enter registration number and last name
3. Click "Continua"
4. See identified member information
5. See warning about shared payments
6. Enter payment date
7. **Enter amount paid (new)**
8. Select payment year
9. Upload receipt file
10. Submit

### Admin Flow:
1. Navigate to `fee_payments.php`
2. View pending requests
3. **See amount column in the table (new)**
4. Click "Visualizza" to view receipt
5. Click "Approva" to approve
6. **Amount is transferred to member's fee record (new)**

## Security Considerations
- All amount values are escaped with `htmlspecialchars(ENT_QUOTES, 'UTF-8')` to prevent XSS attacks
- Server-side validation ensures amount is numeric and positive
- Database field is DECIMAL(10,2) to prevent precision issues

## Backward Compatibility
- The `amount` field is nullable (DEFAULT NULL)
- Existing records without amounts will display "N/A"
- Old receipts can still be processed without amounts

## Database Migration Instructions

To apply the migration:
```bash
cd /path/to/EasyVol
php migrations/run_migration.php migrations/add_amount_to_fee_payment_requests.sql
```

Or manually run the SQL:
```sql
ALTER TABLE `fee_payment_requests` 
ADD COLUMN `amount` DECIMAL(10,2) DEFAULT NULL COMMENT 'Importo pagato' 
AFTER `payment_date`;
```

## Testing Checklist
- [ ] Upload a fee payment receipt with an amount through the public form
- [ ] Verify the warning notice is displayed
- [ ] Verify the amount appears in the admin management page
- [ ] Approve a payment request and verify the amount is transferred to member_fees
- [ ] Verify email notifications include the amount
- [ ] Test with NULL amounts (backward compatibility)
- [ ] Test validation (empty, zero, negative amounts)

## Italian Language Notes
- "Importo Pagato" = "Amount Paid"
- "Importo" = "Amount"
- "Attenzione" = "Warning/Attention"
- Number format: Italian uses comma for decimals (XX,XX) and period for thousands
