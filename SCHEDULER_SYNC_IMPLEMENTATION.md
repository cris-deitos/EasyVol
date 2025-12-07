# Scheduler Sync and Vehicle Maintenance Implementation

## Overview

This implementation addresses three main problems:

1. **Automatic synchronization of expiry dates to the scheduler** - Qualifications, licenses, vehicle insurance, and inspections now automatically appear in the scheduler
2. **Automatic calculation of inspection expiry dates** - When a "revisione" (inspection) is recorded, the system calculates the new expiry date as "last day of month + 2 years"
3. **Enhanced vehicle maintenance form** - New comprehensive form for recording all types of maintenance with vehicle status updates

## Database Changes

### Migration File: `migrations/add_scheduler_references.sql`

This migration adds:

1. **Scheduler References** - Links scheduler items to their source records:
   - `reference_type` VARCHAR(50) - Type of reference: 'qualification', 'license', 'insurance', 'inspection'
   - `reference_id` INT - ID of the referenced record
   - Index on (reference_type, reference_id) for efficient lookups

2. **Vehicle Maintenance Types** - Adds 'revisione' to the ENUM:
   - revisione
   - manutenzione_ordinaria
   - manutenzione_straordinaria
   - anomalie
   - guasti
   - riparazioni
   - sostituzioni

3. **Vehicle Maintenance Tracking**:
   - `status` ENUM - Vehicle status after maintenance
   - `created_by` INT - User who created the record
   - `created_at` TIMESTAMP

## New Controller: SchedulerSyncController

Location: `src/Controllers/SchedulerSyncController.php`

### Key Methods

#### `syncQualificationExpiry($courseId, $memberId)`
Synchronizes member qualification/course expiry dates to the scheduler.
- Creates title: "Scadenza Qualifica: [course_name] - [member_name]"
- Category: "qualifiche"
- Reference: qualification:[courseId]

#### `syncLicenseExpiry($licenseId, $memberId)`
Synchronizes member license expiry dates to the scheduler.
- Creates title: "Scadenza Patente [license_type]: [member_name]"
- Category: "patenti"
- Reference: license:[licenseId]

#### `syncInsuranceExpiry($vehicleId)`
Synchronizes vehicle insurance expiry dates to the scheduler.
- Creates title: "Scadenza Assicurazione: [vehicle_name]"
- Category: "veicoli"
- Reference: insurance:[vehicleId]

#### `syncInspectionExpiry($vehicleId)`
Synchronizes vehicle inspection expiry dates to the scheduler.
- Creates title: "Scadenza Revisione: [vehicle_name]"
- Category: "veicoli"
- Reference: inspection:[vehicleId]

### Priority Calculation

The system automatically calculates priority based on days until expiry:
- **Urgente**: Already expired or expires within 7 days
- **Alta**: Expires within 8-30 days
- **Media**: Expires within 31-60 days
- **Bassa**: Expires in more than 60 days

## Updated Controllers

### VehicleController

#### Updated `addMaintenance()` Method

New features:
1. **Transaction support** - Ensures data consistency
2. **Revisione handling** - When maintenance_type is 'revisione':
   - Calculates new inspection_expiry using `calculateInspectionExpiry()`
   - Updates vehicles.inspection_expiry
   - Syncs with scheduler
3. **Status updates** - Updates vehicle status if specified
4. **Audit trail** - Records created_by and created_at

#### New `calculateInspectionExpiry()` Method

Calculates inspection expiry as: **last day of month + 2 years**

Examples:
- Revision on 2025-12-15 → Expires 2027-12-31
- Revision on 2024-02-15 → Expires 2026-02-28
- Revision on 2025-06-15 → Expires 2027-06-30

#### Updated `create()` and `update()` Methods

Now automatically sync insurance and inspection expiry dates with the scheduler when vehicles are created or updated.

### Member Model

#### Updated `addLicense()` Method

After inserting a license, automatically syncs the expiry date with the scheduler if present.

#### Updated `addCourse()` Method

After inserting a course/qualification, automatically syncs the expiry date with the scheduler if present.

## New Form Handler

### `public/vehicle_maintenance_save.php`

POST handler that:
1. Validates CSRF token
2. Validates required fields (maintenance_type, date, description)
3. Calls `VehicleController->addMaintenance()`
4. Redirects to vehicle_view.php with success/error message
5. Shows special message for revisione type

## Updated UI

### `public/vehicle_view.php`

Added comprehensive maintenance modal with:
- **Type selection** - All 7 maintenance types including revisione
- **Date picker** - Defaults to today
- **Description field** - Required
- **Cost field** - Optional, numeric
- **Performed by** - Optional text field
- **Vehicle status** - Option to update vehicle status (Operativo, In Manutenzione, Fuori Servizio)
- **Notes** - Optional additional notes

## Testing

### Inspection Expiry Calculation Tests

All tests pass for:
- Mid-month dates
- First/last day of month
- February handling (leap and non-leap years)
- Various months with different day counts

### Priority Calculation Tests

All tests pass for:
- Expired dates
- Current date
- Various future dates (3, 7, 15, 30, 45, 60, 90 days)

## Usage Instructions

### 1. Run the Migration

```bash
cd /path/to/EasyVol
php migrations/run_migration.php migrations/add_scheduler_references.sql
```

### 2. Adding a Vehicle Inspection

1. Go to vehicle detail page
2. Click "Manutenzioni" tab
3. Click "Aggiungi Manutenzione"
4. Select "Revisione" as type
5. Enter date, description, and other details
6. Submit form

Result:
- Maintenance record created
- Inspection expiry automatically calculated
- Scheduler item automatically created/updated
- Vehicle status updated if specified

### 3. Adding Member License

1. Go to member detail page
2. Add license with expiry date
3. License is saved

Result:
- License record created
- If expiry date exists, scheduler item automatically created/updated

### 4. Adding Member Qualification/Course

1. Go to member detail page
2. Add course with expiry date
3. Course is saved

Result:
- Course record created
- If expiry date exists, scheduler item automatically created/updated

## Maintenance Types

The system supports 7 types of maintenance:
1. **Revisione** - Inspection (triggers automatic expiry calculation)
2. **Manutenzione Ordinaria** - Routine maintenance
3. **Manutenzione Straordinaria** - Extraordinary maintenance
4. **Anomalie** - Anomalies
5. **Guasti** - Failures
6. **Riparazioni** - Repairs
7. **Sostituzioni** - Replacements

## Vehicle Status Options

When adding maintenance, you can optionally update the vehicle status to:
- **Operativo** - Operational
- **In Manutenzione** - Under maintenance
- **Fuori Servizio** - Out of service

## Benefits

1. **Automatic tracking** - No manual scheduler entry needed
2. **Consistency** - Scheduler always reflects actual expiry dates
3. **Priority management** - Automatic priority calculation helps prioritize urgent items
4. **Comprehensive audit** - Who created maintenance records and when
5. **User-friendly** - Simple forms with all necessary fields
6. **Accurate calculations** - Handles edge cases like February correctly

## Technical Details

### Transaction Safety

All operations use database transactions to ensure:
- Either all changes succeed or none do
- No partial updates
- Data consistency maintained

### Error Handling

- All methods have try-catch blocks
- Errors are logged to PHP error log
- User-friendly error messages in UI
- Failed operations roll back cleanly

### Performance

- Index on (reference_type, reference_id) for fast scheduler lookups
- Only syncs when expiry dates are present
- Efficient SQL queries with JOINs for data retrieval

## Future Enhancements

Possible improvements:
1. Email notifications before expiry
2. Bulk sync command for existing records
3. Dashboard widgets for upcoming expiries
4. Reports on maintenance history
5. Cost tracking and analytics
