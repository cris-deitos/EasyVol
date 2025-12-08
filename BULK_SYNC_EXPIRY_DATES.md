# Bulk Sync Expiry Dates to Scheduler

## Overview

This document describes the bulk synchronization utility that populates the scheduler with all expiry dates from various tables in the system.

## Purpose

The scheduler should automatically display all expiration dates from:
- Member licenses (patenti)
- Member courses/qualifications (corsi/qualifiche)
- Vehicle insurance (assicurazione veicoli)
- Vehicle inspections (revisione veicoli)
- Vehicle documents (documenti veicoli)

This utility ensures that all existing expiry dates are synchronized to the scheduler, even if they were created before the sync functionality was implemented.

## Running the Bulk Sync

### One-Time Sync

To sync all existing expiry dates to the scheduler:

```bash
cd /path/to/EasyVol
php cron/sync_all_expiry_dates.php
```

### Periodic Sync (Optional)

You can also run this script periodically via cron to ensure consistency:

```bash
# Run daily at 2 AM
0 2 * * * cd /path/to/EasyVol && php cron/sync_all_expiry_dates.php >> /var/log/easyvol_sync.log 2>&1
```

## What the Script Does

1. **Member Qualifications/Courses**: Syncs all `member_courses.expiry_date` to scheduler
2. **Member Licenses**: Syncs all `member_licenses.expiry_date` to scheduler
3. **Vehicle Insurance**: Syncs all `vehicles.insurance_expiry` to scheduler
4. **Vehicle Inspections**: Syncs all `vehicles.inspection_expiry` to scheduler
5. **Vehicle Documents**: Syncs all `vehicle_documents.expiry_date` to scheduler

## Features

- **Automatic Links**: Each scheduler item includes a link back to the source record
- **Smart Priority**: Priority is automatically calculated based on days until expiry:
  - **Urgente**: Already expired or expires within 7 days
  - **Alta**: Expires within 8-30 days
  - **Media**: Expires within 31-60 days
  - **Bassa**: Expires in more than 60 days
- **No Duplicates**: If a scheduler item already exists for a reference, it will be updated instead of creating a duplicate
- **Error Handling**: Errors are logged and reported in the summary

## Output

The script provides detailed output showing:
- Number of items synced for each category
- Number of errors encountered
- Total items synced
- Timestamps for start and completion

Example output:
```
=== Bulk Sync All Expiry Dates to Scheduler ===
Started at: 2025-12-08 16:00:00

1. Syncing member qualifications/courses...
   Synced: 15, Errors: 0

2. Syncing member licenses...
   Synced: 23, Errors: 0

3. Syncing vehicle insurance...
   Synced: 8, Errors: 0

4. Syncing vehicle inspection...
   Synced: 8, Errors: 0

5. Syncing vehicle documents...
   Synced: 5, Errors: 0

=== Summary ===
Total items synced: 59
Total errors: 0
Completed at: 2025-12-08 16:00:05
```

## Automatic Sync

Once the bulk sync is complete, new or updated expiry dates are automatically synchronized:

- When a member license is added/updated
- When a member course is added/updated
- When a vehicle is created/updated with insurance or inspection dates
- When vehicle maintenance of type "revisione" is recorded (automatically calculates new inspection expiry)

## Viewing Synced Items

All synced items appear in the scheduler at `/public/scheduler.php` with:
- A clickable link to the source record (member or vehicle)
- Category badge (qualifiche, patenti, veicoli, documenti_veicoli)
- Priority indicator
- Days until expiry (for upcoming items)

## Troubleshooting

### Script Fails with "Configuration not found"

Make sure you have a valid `config/config.php` file. If not, copy from the sample:

```bash
cp config/config.sample.php config/config.php
# Edit config.php with your database credentials
```

### No Items Synced

This means there are no expiry dates in the database. This is normal for a fresh installation.

### Errors Reported

Check the PHP error log for details. Common issues:
- Database connection problems
- Missing reference_type/reference_id columns (run the migration first)
- Invalid date formats

## Migration Required

Before running the bulk sync, ensure the database migration has been applied:

```bash
php migrations/run_migration.php migrations/add_scheduler_references.sql
```

This adds the `reference_type` and `reference_id` columns to the `scheduler_items` table.
