# Radio Assignment Junior Members Support - Migration Guide

## Overview
This migration adds support for assigning radios to junior members (cadets) in addition to regular members.

## Changes Made

### Database Schema Changes
- Added `junior_member_id` column to `radio_assignments` table
- Added `assignee_type` column to track whether assignment is to member, cadet, or external personnel
- Added foreign key constraint from `radio_assignments` to `junior_members` table

### Application Changes
1. **Search functionality**: Members and cadets can be searched by name, surname, or badge number
2. **Assignment support**: Both members (active + operational) and cadets (active) can be assigned radios
3. **Backward compatibility**: The system works with or without the migration applied

## Installation Instructions

### For Fresh Installations
If you're installing EasyVol for the first time, the database schema already includes these changes. No migration needed.

### For Existing Installations
If you already have EasyVol installed and want to add support for assigning radios to cadets:

#### Method 1: Using MySQL Command Line
```bash
mysql -u your_username -p your_database_name < migrations/add_radio_assignments_junior_support.sql
```

#### Method 2: Using phpMyAdmin
1. Open phpMyAdmin
2. Select your EasyVol database
3. Click on "SQL" tab
4. Copy and paste the contents of `migrations/add_radio_assignments_junior_support.sql`
5. Click "Go" to execute

#### Method 3: Using MySQL Workbench
1. Open MySQL Workbench
2. Connect to your database
3. Open the migration file: `migrations/add_radio_assignments_junior_support.sql`
4. Execute the script

## Verification

After applying the migration, verify the changes:

```sql
-- Check if columns exist
SHOW COLUMNS FROM radio_assignments LIKE 'junior_member_id';
SHOW COLUMNS FROM radio_assignments LIKE 'assignee_type';

-- Check foreign key constraints
SHOW CREATE TABLE radio_assignments;
```

## Testing

1. Login to EasyVol
2. Navigate to Operations Center > Radio Directory
3. Select a radio and click on it to view details
4. Click "Assegna Radio" button
5. You should now see a search field instead of a dropdown
6. Type a name, surname, or badge number
7. The autocomplete should show both members and cadets (marked with [Cadetto])
8. Select a person and complete the assignment

## Rollback (if needed)

If you need to rollback the changes:

```sql
-- Remove foreign key constraint
ALTER TABLE radio_assignments DROP FOREIGN KEY radio_assignments_ibfk_3;

-- Remove columns
ALTER TABLE radio_assignments 
DROP COLUMN junior_member_id,
DROP COLUMN assignee_type;
```

**Warning**: Rolling back will not affect existing assignments, but you will lose the ability to track whether assignments are to cadets.

## Support

For issues or questions, please open an issue on the GitHub repository.

## Notes

- The application has backward compatibility built-in. It will work even if the migration is not applied (but cadets won't be assignable)
- Existing assignments are not affected by this migration
- The migration is non-destructive and does not modify existing data
