# Cron Job Fixes - 2026-01-11

## Summary of Issues and Resolutions

### 1. member_expiry_alerts.php - FIXED ✅

**Issue:**
```
ERROR: Query failed: SQLSTATE[42S02]: Base table or view not found: 1146 Table 'Sql1905151_1.member_qualifications' doesn't exist
```

**Root Cause:**
The script was trying to query a non-existent table `member_qualifications`. The actual table is `member_courses`.

**Resolution:**
- Removed the query and processing logic for `member_qualifications` table
- The script now only queries `member_courses` table for expiring courses/qualifications
- Updated activity log to reflect the change
- Updated output messages to be more accurate

**Files Modified:**
- `cron/member_expiry_alerts.php`

---

### 2. email_queue.php - FIXED ✅

**Issue:**
```
Error: Query failed: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'scheduled_at' in 'where clause'
```

**Root Cause:**
The `email_queue` table was missing several columns that the `EmailSender` class expected:
- `scheduled_at` - for scheduling emails to be sent at a specific time
- `priority` - for prioritizing email sending
- `body` - the table had `body_html` but the code used `body`
- `processing` status enum value was missing

**Resolution:**
- Updated `database_schema.sql` to include all required columns:
  - Changed `body_html` to `body`
  - Added `priority` column (int, default 3)
  - Added `scheduled_at` column (timestamp, nullable)
  - Added index on `scheduled_at`
  - Updated status enum to include 'processing'
- Created migration file `006_fix_email_queue_table.sql` with conditional column additions to safely update existing databases

**Files Modified:**
- `database_schema.sql`
- `migrations/006_fix_email_queue_table.sql` (new file)

**Migration Notes:**
The migration uses prepared statements with conditional logic to:
1. Check if each column exists before adding it
2. Rename `body_html` to `body` only if `body_html` exists
3. Add indexes safely
4. Update enum values

---

### 3. backup.php - IMPROVED ✅

**Issue:**
```
Errore 500
```

**Root Cause:**
The backup script had poor error handling and didn't provide diagnostic information when mysqldump failed. Potential issues include:
- mysqldump not in PATH
- mysqldump not installed
- Wrong database credentials
- Permission issues

**Resolution:**
- Added mysqldump path detection with fallback paths:
  - `/usr/bin/mysqldump`
  - `/usr/local/bin/mysqldump`
  - `/usr/local/mysql/bin/mysqldump`
- Added error output redirection (2>&1) to capture error messages
- Improved error messages to show:
  - Which mysqldump binary is being used
  - Return code from mysqldump
  - Output from mysqldump command
  - Whether backup file was created
- Added better handling of gzip compression failures
- More detailed logging throughout the process

**Files Modified:**
- `cron/backup.php`

**Testing Recommendations:**
The next time this cron runs, it will provide much more detailed error information in the logs, making it easier to diagnose the actual problem.

---

### 4. sync_all_expiry_dates.php - IMPROVED ✅

**Issue:**
```
4. Syncing vehicle inspection (active vehicles only)...
Synced: 0, Errors: 12
```

**Root Cause:**
The script was encountering errors when syncing 12 vehicle inspection expiry dates, but the error messages didn't include enough detail to diagnose the issue.

**Resolution:**
- Improved error logging in `SchedulerSyncController` to include specific IDs:
  - `syncInspectionExpiry()` - now logs vehicle ID in error messages
  - `syncInsuranceExpiry()` - now logs vehicle ID in error messages
  - `syncQualificationExpiry()` - now logs course ID in error messages
  - `syncLicenseExpiry()` - now logs license ID in error messages
  - `syncVehicleDocumentExpiry()` - now logs document ID in error messages
- Changed logic to distinguish between "not found" (returns false) and "no expiry date" (returns true)
- This will help identify which specific vehicles are causing errors

**Files Modified:**
- `src/Controllers/SchedulerSyncController.php`

**Next Steps:**
When the sync script runs again, check the PHP error log for messages like:
```
Errore sincronizzazione revisione veicolo ID 123: [detailed error message]
```
This will help identify:
- Which vehicles are causing the problem
- What the specific error is (missing data, constraint violations, etc.)

---

## Database Migration Instructions

To apply the email_queue table fixes, run the migration:

```bash
mysql -u [username] -p [database_name] < migrations/006_fix_email_queue_table.sql
```

Or use your database management tool to execute the SQL in `migrations/006_fix_email_queue_table.sql`.

The migration is idempotent - it can be run multiple times safely. It checks if columns exist before adding them.

---

## Testing Recommendations

### 1. Test email_queue.php
```bash
php /path/to/EasyVol/cron/email_queue.php
```
Should no longer show "Unknown column 'scheduled_at'" error.

### 2. Test member_expiry_alerts.php
```bash
php /path/to/EasyVol/cron/member_expiry_alerts.php
```
Should no longer show "Table 'member_qualifications' doesn't exist" error.

### 3. Test backup.php
```bash
php /path/to/EasyVol/cron/backup.php
```
Should show detailed output about:
- Which mysqldump is being used
- Success/failure of backup creation
- Detailed error messages if it fails

### 4. Test sync_all_expiry_dates.php
```bash
php /path/to/EasyVol/cron/sync_all_expiry_dates.php
```
Check PHP error logs for detailed error messages about which vehicles failed and why.

---

## Additional Notes

### scheduler_alerts.php - Working ✅
No errors reported. Output shows:
```
Updated 0 items to 'scaduto' status
No reminders to send today
Scheduler alerts job completed successfully
```

### health_surveillance_alerts.php - Working ✅
No errors reported. Output shows:
```
Found 0 expiring adult member health surveillance visits
Found 0 expiring junior member health surveillance visits
Total notifications sent: 0
```

---

## Future Recommendations

1. **Regular Database Backups**: Ensure backup.php runs successfully to maintain data safety
2. **Monitor Error Logs**: Check PHP error logs regularly for detailed sync error messages
3. **Data Quality**: Investigate and fix the 12 vehicles causing sync errors
4. **Email Queue Monitoring**: Monitor email_queue table to ensure emails are being sent
5. **Testing**: Test all cron jobs after applying the migration to ensure they work correctly
