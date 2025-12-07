# Part 2: Warehouse and Operations Center Fixes

## Overview
This document describes the fixes applied to resolve warehouse item insertion and operations center undefined key errors.

## Migrations Required

The following migrations must be run in order:

### 1. Add Badge Number to Members Table
**File:** `add_member_badge_number.sql`

**Purpose:** Adds `badge_number` field to the members table to store volunteer badge/ID numbers.

**Command:**
```bash
php migrations/run_migration.php migrations/add_member_badge_number.sql
```

**Changes:**
- Adds `badge_number VARCHAR(20) NULL` column after `registration_number`
- Creates index on `badge_number` for faster lookups
- Non-breaking change (NULL allowed)

### 2. Create Member Availability Table
**File:** `create_member_availability_table.sql`

**Purpose:** Creates a new table to track volunteer availability status (available, limited, unavailable).

**Command:**
```bash
php migrations/run_migration.php migrations/create_member_availability_table.sql
```

**Changes:**
- Drops existing `member_availability` table if schema is incorrect
- Creates new table with proper schema:
  - `availability_type` ENUM('available', 'limited', 'unavailable')
  - Links to members table via `member_id`
  - One availability record per member (UNIQUE constraint)

**Note:** This migration automatically creates a backup table (`member_availability_backup_20241207`) before dropping the existing table. If you need to restore data, you can manually migrate from the backup table.

## Code Changes Summary

### Warehouse Controller (`src/Controllers/WarehouseController.php`)
**Problem:** Item insertion was failing silently with no debugging information.

**Solution:** Added comprehensive logging to `create()` method:
- Logs all input data received
- Logs transaction state (start, commit, rollback)
- Logs validation results
- Logs SQL and parameters
- Logs complete error traces on failure

**Testing:** Watch PHP error logs when creating warehouse items to see detailed execution flow.

**Note:** This detailed logging is intended for debugging. In production, consider:
- Using a debug configuration flag to enable/disable verbose logging
- Rotating and monitoring log file sizes
- Sanitizing sensitive data from logs if needed
- Once the issue is resolved, logging can be reduced to errors only

### Operations Center Controller (`src/Controllers/OperationsCenterController.php`)
**Problem:** Missing fields in volunteer query caused undefined key errors.

**Solution:** Added `getAvailableVolunteers()` method that:
- Includes `badge_number` field
- Includes `availability_type` with COALESCE default
- LEFT JOINs member_contacts for phone numbers
- LEFT JOINs member_availability for status

**Testing:** All volunteer queries now include required fields with safe defaults.

### Operations Center Page (`public/operations_center.php`)
**Problem:** Direct array key access without checking existence.

**Solution:**
- Changed `if ($member['badge_number'])` to `if (!empty($member['badge_number']))`
- Added null coalescing operator: `$member['availability_type'] ?? 'available'`
- Used `match()` expression for clean availability type mapping:
  - 'available' → 'Disponibile' (green badge)
  - 'limited' → 'Limitato' (yellow badge)
  - 'unavailable' → 'Non disponibile' (gray badge)
- Added `htmlspecialchars()` for XSS protection

**Testing:** Page will now display without errors even if volunteers don't have badge numbers or availability records.

## Testing Instructions

### 1. Test Warehouse Item Creation

1. Navigate to warehouse management page
2. Click "New Item" or similar
3. Fill in the form:
   - Name (required)
   - Code (optional)
   - Category (optional)
   - Quantity (default 0)
   - Minimum Quantity (default 0)
   - Unit (pz, kg, litri, metri)
   - Location (optional)
   - Status (disponibile, esaurito)
4. Submit the form
5. Check PHP error logs for detailed execution trace:
   - Look for "=== WAREHOUSE CREATE START ==="
   - Verify data received is correct
   - Check for "=== WAREHOUSE CREATE SUCCESS ===" with item ID
6. Verify item appears in warehouse list
7. Verify item details are correct in database

### 2. Test Operations Center Page

1. **Before migrations:** Page may show undefined key warnings
2. **Run migrations** (see commands above)
3. Navigate to operations center page
4. Check "Volontari Reperibili" section
5. Verify:
   - No PHP warnings/errors displayed
   - Volunteers display correctly
   - Badge numbers show when present, hidden when absent
   - Availability status shows with correct color badges:
     - Green = Disponibile (available)
     - Yellow = Limitato (limited)
     - Gray = Non disponibile (unavailable)

### 3. Test with Different Data States

Test that the page handles:
- ✅ Volunteers with badge_number
- ✅ Volunteers without badge_number (NULL)
- ✅ Volunteers with availability_type set
- ✅ Volunteers without availability records (defaults to 'available')

## Rollback Instructions

If you need to rollback these changes:

### Database Rollback

```sql
-- Remove badge_number column
ALTER TABLE members DROP COLUMN badge_number;
DROP INDEX idx_badge_number ON members;

-- Restore old member_availability table from backup (if needed)
DROP TABLE IF EXISTS member_availability;
CREATE TABLE member_availability AS SELECT * FROM member_availability_backup_20241207;

-- Restore indexes and constraints on the restored table
-- Add back any indexes or foreign keys that were on the original table
```

**Before running rollback:**
```bash
# Create a database backup first
mysqldump -u [username] -p [database_name] > backup_before_rollback.sql
```

### Code Rollback
```bash
git revert <commit_hash>
```

## Notes

- All changes are backward compatible except the member_availability table recreation
- The warehouse form was already correct; only logging was added
- No breaking changes to existing functionality
- PHP 8.0+ required for `match()` expressions (project requires PHP 8.3+)
- All string outputs use `htmlspecialchars()` for security

## Future Improvements

Consider:
1. Add UI for managing volunteer availability status
2. Add UI for assigning badge numbers to volunteers
3. Add validation for badge_number uniqueness if needed
4. Add audit logging for availability changes
5. Consider adding more availability types if needed
