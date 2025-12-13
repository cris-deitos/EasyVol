# Migration Guide: Fix Missing Event Tables

## Problem
When inserting a new event, the system was returning the following error:

```
Fatal error: Uncaught Exception: Query failed: SQLSTATE[42S02]: Base table or view not found: 
1146 Table 'Sql1905151_1.event_participants' doesn't exist
```

This error occurred because the database schema was missing two essential tables:
- `event_participants` - to track members participating in events
- `event_vehicles` - to track vehicles used in events

## Solution

### For Existing Installations

If you already have an EasyVol installation with data, run the migration:

```bash
cd /path/to/EasyVol
php migrations/run_migration.php add_event_participants_and_vehicles_tables.sql
```

Alternatively, you can apply the migration manually using MySQL:

```bash
mysql -u username -p database_name < migrations/add_event_participants_and_vehicles_tables.sql
```

Or using phpMyAdmin:
1. Open phpMyAdmin
2. Select your database
3. Go to the "SQL" tab
4. Copy and paste the contents of `migrations/add_event_participants_and_vehicles_tables.sql`
5. Click "Go"

### For New Installations

If you are setting up a new EasyVol installation, the tables are already included in `database_schema.sql`. 
Simply import the full schema:

```bash
mysql -u username -p database_name < database_schema.sql
```

## What Changed

### New Tables Added

#### event_participants
This table tracks member participation in events with the following fields:
- `id` - Primary key
- `event_id` - Foreign key to events table
- `member_id` - Foreign key to members table
- `role` - Role of the member in the event (optional)
- `hours` - Hours of service (decimal)
- `notes` - Additional notes (optional)
- `created_at`, `updated_at` - Timestamps

#### event_vehicles
This table tracks vehicle usage in events with the following fields:
- `id` - Primary key
- `event_id` - Foreign key to events table
- `vehicle_id` - Foreign key to vehicles table
- `driver_name` - Name of the driver (optional)
- `hours` - Hours of vehicle usage (decimal)
- `km_traveled` - Kilometers traveled
- `km_start` - Starting odometer reading (optional)
- `km_end` - Ending odometer reading (optional)
- `notes` - Additional notes (optional)
- `created_at`, `updated_at` - Timestamps

### Code Changes

Minor fix in `public/event_view.php`:
- Updated the participant display to use `first_name` and `last_name` fields from the database query

## Verification

After applying the migration, verify the tables exist:

```sql
SHOW TABLES LIKE 'event_%';
```

You should see:
- event_participants
- event_vehicles

Verify the structure:

```sql
DESCRIBE event_participants;
DESCRIBE event_vehicles;
```

## Testing

1. Log in to EasyVol
2. Navigate to Events section
3. Create a new event
4. View the event details - it should now display without errors
5. The Participants and Vehicles tabs should be functional (even if empty)

## Support

If you encounter any issues after applying this migration, please:
1. Check the MySQL error log for detailed error messages
2. Verify the migration was applied successfully
3. Ensure all foreign key references (events, members, vehicles tables) exist
4. Contact support with the specific error message

## Technical Details

- Migration file: `migrations/add_event_participants_and_vehicles_tables.sql`
- Affected files:
  - `database_schema.sql` - Updated with new tables
  - `public/event_view.php` - Fixed participant name display
  - `migrations/README.md` - Added migration documentation
- Compatible with MySQL 5.6+ and MySQL 8.x
- Uses InnoDB engine with foreign key constraints
- Character set: utf8mb4 with utf8mb4_unicode_ci collation
