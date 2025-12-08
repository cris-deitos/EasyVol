# Scheduler Expiry Date Sync - Complete Implementation

## Overview

This document describes the complete implementation of automatic expiry date synchronization to the scheduler (scadenzario) in EasyVol. Every expiration date from any table is now automatically visible in the scheduler with clickable links back to the source records.

## Features

### ✅ Automatic Synchronization
All expiry dates from the following sources are automatically synchronized:
- **Member Licenses** (patenti) - `member_licenses.expiry_date`
- **Member Courses/Qualifications** (corsi/qualifiche) - `member_courses.expiry_date`
- **Vehicle Insurance** (assicurazione veicoli) - `vehicles.insurance_expiry`
- **Vehicle Inspections** (revisione veicoli) - `vehicles.inspection_expiry`
- **Vehicle Documents** (documenti veicoli) - `vehicle_documents.expiry_date`

### ✅ Clickable Links
Each scheduler item includes a clickable link that opens the source record:
- License/Course expiries → Link to member detail page
- Insurance/Inspection expiries → Link to vehicle detail page
- Document expiries → Link to vehicle detail page with documents tab

### ✅ Smart Priority Calculation
Priority is automatically calculated based on days until expiry:
- **Urgente** (Urgent) - Already expired or expires within 7 days
- **Alta** (High) - Expires within 8-30 days
- **Media** (Medium) - Expires within 31-60 days
- **Bassa** (Low) - Expires in more than 60 days

### ✅ Full CRUD Support
Synchronization happens automatically for all operations:
- **Create** - New expiry dates create scheduler items
- **Update** - Changed expiry dates update scheduler items
- **Delete** - Deleted records remove scheduler items

## Database Changes

### Required Migration

Before using this feature, run the migration to add reference tracking:

```bash
php migrations/run_migration.php migrations/add_scheduler_references.sql
```

This adds:
- `reference_type` VARCHAR(50) - Type: 'qualification', 'license', 'insurance', 'inspection', 'vehicle_document'
- `reference_id` INT - ID of the referenced record
- Index on (reference_type, reference_id) for efficient lookups

## Usage

### Initial Setup - Bulk Sync

To populate the scheduler with all existing expiry dates:

```bash
cd /path/to/EasyVol
php cron/sync_all_expiry_dates.php
```

This will:
1. Find all records with expiry dates across all tables
2. Create scheduler items for each one
3. Set appropriate priorities based on expiry dates
4. Provide a detailed summary of synced items and errors

### Ongoing Automatic Sync

After the initial bulk sync, all new and updated records automatically sync:

#### Member Licenses
```php
// When adding or updating a license
$member->addLicense($memberId, [
    'license_type' => 'B',
    'expiry_date' => '2025-12-31'
]);
// → Automatically creates/updates scheduler item
```

#### Member Courses
```php
// When adding or updating a course
$member->addCourse($memberId, [
    'course_name' => 'First Aid',
    'expiry_date' => '2026-06-30'
]);
// → Automatically creates/updates scheduler item
```

#### Vehicle Insurance/Inspection
```php
// When creating or updating a vehicle
$vehicleController->create([
    'name' => 'Ambulanza 1',
    'insurance_expiry' => '2025-12-31',
    'inspection_expiry' => '2026-12-31'
], $userId);
// → Automatically creates/updates scheduler items for both
```

#### Vehicle Documents
```php
// When adding a document with expiry
$vehicleController->addDocument($vehicleId, [
    'document_type' => 'Collaudo',
    'file_name' => 'collaudo.pdf',
    'file_path' => '/uploads/...',
    'expiry_date' => '2027-12-31'
], $userId);
// → Automatically creates/updates scheduler item
```

## UI Changes

### Scheduler View (scheduler.php)

The scheduler now shows:
- **Clickable titles** with an arrow icon (↗) for items linked to source records
- **Color coding**: Red for overdue, yellow for due within 7 days
- **Category badges**: qualifiche, patenti, veicoli, documenti_veicoli
- **Priority badges**: Urgente, Alta, Media, Bassa
- Clicking a linked item opens the source record in a new context

### Vehicle Documents View (vehicle_view.php)

The vehicle documents table now includes:
- **Expiry Date column** showing document expiration dates
- **Color coding**: Red row for expired documents, yellow for expiring within 30 days
- **Visual indicators**: Warning icons and countdown for soon-to-expire items

## Technical Implementation

### Core Components

#### 1. SchedulerSyncController
Location: `src/Controllers/SchedulerSyncController.php`

Key methods:
- `syncQualificationExpiry($courseId, $memberId)` - Sync course expiry
- `syncLicenseExpiry($licenseId, $memberId)` - Sync license expiry
- `syncInsuranceExpiry($vehicleId)` - Sync vehicle insurance expiry
- `syncInspectionExpiry($vehicleId)` - Sync vehicle inspection expiry
- `syncVehicleDocumentExpiry($documentId, $vehicleId)` - Sync document expiry
- `getReferenceLink($referenceType, $referenceId)` - Get URL to source record
- `removeSchedulerItem($referenceType, $referenceId)` - Remove scheduler item

#### 2. Member Model
Location: `src/Models/Member.php`

Enhanced methods:
- `addLicense()` - Adds license and syncs expiry
- `updateLicense()` - Updates license and re-syncs expiry
- `deleteLicense()` - Deletes license and removes from scheduler
- `addCourse()` - Adds course and syncs expiry
- `updateCourse()` - Updates course and re-syncs expiry
- `deleteCourse()` - Deletes course and removes from scheduler

#### 3. VehicleController
Location: `src/Controllers/VehicleController.php`

Enhanced methods:
- `create()` - Creates vehicle and syncs insurance/inspection
- `update()` - Updates vehicle and re-syncs insurance/inspection
- `delete()` - Deletes vehicle and removes from scheduler
- `addDocument()` - Adds document and syncs expiry
- `updateDocument()` - Updates document and re-syncs expiry
- `deleteDocument()` - Deletes document and removes from scheduler

### Data Flow

```
User Action (Create/Update/Delete)
        ↓
Controller Method
        ↓
Database Operation
        ↓
SchedulerSyncController
        ↓
- Check if scheduler item exists (by reference_type + reference_id)
- If exists → Update with new data
- If not exists → Create new item
- If expiry removed → Delete scheduler item
        ↓
Calculate Priority (based on days until expiry)
        ↓
Save to scheduler_items table
```

### Reference System

Each scheduler item stores:
- `reference_type` - Type of source record
- `reference_id` - ID of source record

Reference types:
- `'qualification'` → Points to `member_courses.id`
- `'license'` → Points to `member_licenses.id`
- `'insurance'` → Points to `vehicles.id`
- `'inspection'` → Points to `vehicles.id`
- `'vehicle_document'` → Points to `vehicle_documents.id`

### Link Generation

When displaying a scheduler item, the system:
1. Checks if it has a `reference_type` and `reference_id`
2. Calls `getReferenceLink()` to determine the URL
3. Generates appropriate URL with anchor tags:
   - Qualifications → `member_view.php?id={member_id}#courses`
   - Licenses → `member_view.php?id={member_id}#licenses`
   - Insurance → `vehicle_view.php?id={vehicle_id}`
   - Inspection → `vehicle_view.php?id={vehicle_id}`
   - Documents → `vehicle_view.php?id={vehicle_id}#documents`

## Benefits

### For Users
1. **No Manual Entry** - All expiry dates automatically appear in scheduler
2. **Quick Navigation** - One click to view the source record
3. **Visual Alerts** - Color coding and priorities highlight urgent items
4. **Consistency** - Scheduler always reflects current data

### For Administrators
1. **Reduced Errors** - No manual scheduler entry eliminates human error
2. **Automatic Updates** - Changes propagate automatically
3. **Complete Tracking** - Never miss an expiration
4. **Easy Maintenance** - Bulk sync available for data corrections

### For the System
1. **Data Integrity** - Single source of truth for expiry dates
2. **Referential Integrity** - Links maintained via reference system
3. **Performance** - Indexed lookups for fast queries
4. **Scalability** - Efficient sync even with thousands of records

## Maintenance

### Periodic Bulk Sync (Optional)

To ensure consistency, you can run the bulk sync periodically:

```bash
# Add to crontab for daily sync at 2 AM
0 2 * * * cd /path/to/EasyVol && php cron/sync_all_expiry_dates.php >> /var/log/easyvol_sync.log 2>&1
```

This is optional as the system handles ongoing sync automatically, but can be useful:
- After data imports
- After manual database changes
- As a consistency check

### Monitoring

Check the scheduler regularly for:
- Items with **Urgente** priority (immediate action needed)
- Items due within 7 days (yellow highlighting)
- Overdue items (red highlighting)

## Troubleshooting

### Scheduler Items Not Appearing

1. **Check Migration** - Ensure the migration has been run:
   ```bash
   php migrations/run_migration.php migrations/add_scheduler_references.sql
   ```

2. **Run Bulk Sync** - Sync existing records:
   ```bash
   php cron/sync_all_expiry_dates.php
   ```

3. **Check Expiry Dates** - Ensure records actually have expiry dates set

### Links Not Working

1. **Check Reference Fields** - Ensure `reference_type` and `reference_id` are set
2. **Check Permissions** - User must have permission to view the target record
3. **Check Record Exists** - Source record might have been deleted

### Sync Errors

Check PHP error log for details:
```bash
tail -f /var/log/php/error.log
```

Common issues:
- Database connection problems
- Missing reference columns (run migration)
- Invalid date formats

## Example Scenarios

### Scenario 1: Adding a New License

```
User: Admin adds "Patente C" for member "Mario Rossi" expiring 2026-12-31

System Actions:
1. License saved to member_licenses table
2. Member->addLicense() calls SchedulerSyncController
3. Scheduler item created:
   - Title: "Scadenza Patente C: Mario Rossi"
   - Due Date: 2026-12-31
   - Category: "patenti"
   - Priority: "bassa" (expires in >60 days)
   - Reference: type='license', id={license_id}

Result:
- License visible on member detail page
- Scheduler item visible in scadenzario
- Clicking scheduler item opens member page with licenses tab
```

### Scenario 2: Vehicle Insurance About to Expire

```
Current Date: 2025-12-20
Vehicle: "Ambulanza 1"
Insurance Expiry: 2025-12-25 (5 days away)

System Status:
- Scheduler shows "Scadenza Assicurazione: Ambulanza 1"
- Priority: "urgente" (expires within 7 days)
- Row highlighted in yellow
- Days remaining shown: "5 giorni"

User Action:
- Clicks on scheduler item
- Opens vehicle_view.php?id={vehicle_id}
- Sees insurance expiry date prominently displayed
- Can update insurance details
```

### Scenario 3: Updating a Course Expiry

```
User: Admin updates "First Aid" course expiry from 2025-06-30 to 2026-06-30

System Actions:
1. Course updated in member_courses table
2. Member->updateCourse() calls SchedulerSyncController
3. Existing scheduler item found (by reference)
4. Scheduler item updated:
   - Due date changed to 2026-06-30
   - Priority recalculated (was "alta", now "bassa")
   - Updated timestamp set

Result:
- Course shows new expiry on member page
- Scheduler shows updated due date and priority
- Link still works to same course record
```

## Security Considerations

- All database queries use prepared statements
- User permissions checked before showing links
- XSS prevention via htmlspecialchars in views
- No SQL injection vulnerabilities
- Audit trail maintained via activity logs

## Performance Considerations

- Index on (reference_type, reference_id) for fast lookups
- Sync operations use transactions for consistency
- Minimal database overhead (one scheduler insert/update per expiry)
- Bulk sync can handle thousands of records efficiently

## Future Enhancements

Possible improvements:
1. Email notifications X days before expiry
2. Dashboard widget showing upcoming expiries
3. Configurable reminder days per category
4. Export scheduler items to calendar (iCal)
5. Mobile app integration
6. Automated renewal workflows

## Support

For questions or issues:
1. Check this documentation
2. Review the BULK_SYNC_EXPIRY_DATES.md file
3. Check PHP error logs
4. Review the SCHEDULER_SYNC_IMPLEMENTATION.md file
5. Contact the development team

## Version History

- **v1.0** (2025-12-08) - Initial implementation with all features
  - Vehicle documents sync added
  - Clickable links implemented
  - Bulk sync utility created
  - Full CRUD support
  - Documentation complete
