# Photo Display and Sanctions System Fix - Implementation Guide

## Summary
This document describes the changes made to fix photo display issues and enhance the sanctions (provvedimenti) system for both adult members and junior members (cadetti).

## Changes Made

### 1. Photo Display Fix

**Problem**: Photos uploaded via the member form were not displaying in the profile view or listing icons because the system was storing absolute filesystem paths in the database instead of web-accessible relative paths.

**Solution**: Modified both `MemberController` and `JuniorMemberController` to convert absolute paths to relative paths before storing in the database.

**Files Modified**:
- `src/Controllers/MemberController.php` (line ~223)
- `src/Controllers/JuniorMemberController.php` (line ~335)

**Change**: Added path conversion before database update:
```php
// Convert absolute path to relative path for web display
$relativePath = str_replace(__DIR__ . '/../../', '../', $result['path']);
```

### 2. Sanctions System Enhancements

#### 2.1 Database Schema Changes

**File**: `migrations/add_operativo_sanction_and_junior_sanctions.sql`

**Changes**:
1. Added 'operativo' to the `member_sanctions` table's `sanction_type` enum
2. Created new `junior_member_sanctions` table with the same structure as `member_sanctions`

**To Apply Migration**:
```bash
cd /path/to/EasyVol
php migrations/run_migration.php migrations/add_operativo_sanction_and_junior_sanctions.sql
```

#### 2.2 Adult Members Sanctions Logic

**File**: `public/member_sanction_edit.php`

**Changes**:
1. Added 'operativo' to valid sanction types
2. Implemented logic where:
   - **Operativo** sanction inserted AFTER a suspending sanction (in_aspettativa, sospeso, in_congedo) returns member status to 'attivo'
   - **In Aspettativa** or **In Congedo** sanctions set status to 'sospeso' (consolidation)
   - **Sospeso** remains as 'sospeso'
   - **Decaduto** remains as 'decaduto'
   - **Dimesso** remains as 'dimesso'

#### 2.3 Junior Members Sanctions System

**New Files Created**:
- `public/junior_member_sanction_edit.php` - Complete sanction management for junior members with same logic as adult members

**Files Modified**:
- `src/Models/JuniorMember.php` - Added sanction methods (getSanctions, addSanction, updateSanction, deleteSanction)
- `public/junior_member_view.php` - Added "Provvedimenti" tab to display sanctions
- `public/junior_member_data.php` - Added delete_sanction action handler

#### 2.4 Status Counter Updates

**Files Modified**:
- `public/members.php`
- `public/junior_members.php`

**Changes**:
1. Combined "Dimessi" and "Decaduti" into single counter "Dimessi/Decaduti"
2. Updated "Sospesi" counter to include in_aspettativa, sospeso, and in_congedo statuses
3. Added explanatory text showing what's included in each counter

## How the New System Works

### Status Consolidation Logic

When a sanction is added or updated:

1. **For suspending sanctions** (in_aspettativa, in_congedo):
   - Member status is set to 'sospeso'
   - This provides a unified view of all temporarily unavailable members

2. **For operativo sanction**:
   - System checks if there's a previous suspending sanction with an earlier date
   - If yes, member status is returned to 'attivo'
   - If no, status remains unchanged
   - This allows reactivating members after their suspension period

3. **For final sanctions** (decaduto, dimesso):
   - Status is set as-is
   - These are terminal states

### Counter Display

The dashboard now shows:
- **Soci Attivi**: Members with status 'attivo'
- **Soci Sospesi**: Members with status 'sospeso', 'in_aspettativa', or 'in_congedo'
  - Shows subtitle: "Include: In Aspettativa, In Congedo"
- **Dimessi/Decaduti**: Combined count of 'dimesso' and 'decaduto' statuses

## Testing Checklist

After applying the migration, test the following:

### Photo Display Testing
- [ ] Upload a photo for an adult member via member_edit.php
- [ ] Verify photo displays correctly in member_view.php profile
- [ ] Verify photo displays correctly as thumbnail in members.php listing
- [ ] Upload a photo for a junior member via junior_member_edit.php
- [ ] Verify photo displays correctly in junior_member_view.php profile
- [ ] Verify photo displays correctly as thumbnail in junior_members.php listing

### Sanctions Testing - Adult Members
- [ ] Add a "Sospeso" sanction to an active member
  - Verify status changes to "Sospeso"
- [ ] Add an "In Aspettativa" sanction to an active member
  - Verify status changes to "Sospeso" (consolidated)
- [ ] Add an "Operativo" sanction AFTER the suspension
  - Verify status changes back to "Attivo"
- [ ] Add a "Dimesso" sanction
  - Verify status changes to "Dimesso"
- [ ] Verify the "Dimessi/Decaduti" counter increases

### Sanctions Testing - Junior Members
- [ ] Add a "Sospeso" sanction to an active junior member
  - Verify status changes to "Sospeso"
- [ ] Add an "In Congedo" sanction
  - Verify status changes to "Sospeso" (consolidated)
- [ ] Add an "Operativo" sanction AFTER the suspension
  - Verify status changes back to "Attivo"
- [ ] Verify "Provvedimenti" tab appears in junior_member_view.php
- [ ] Test editing and deleting sanctions for junior members

### Counter Testing
- [ ] Verify "Soci Sospesi" counter includes in_aspettativa and in_congedo
- [ ] Verify "Dimessi/Decaduti" counter combines both statuses
- [ ] Verify counters update correctly when sanctions are added/removed

## Notes

1. **Photo Path Migration**: Existing photos stored with absolute paths will need manual correction. A migration script could be created to update existing records:

```php
// Example migration script (not included)
$members = $db->fetchAll("SELECT id, photo_path FROM members WHERE photo_path IS NOT NULL");
foreach ($members as $member) {
    if (strpos($member['photo_path'], '/home/') === 0) {
        $relativePath = str_replace('/path/to/project/', '../', $member['photo_path']);
        $db->execute("UPDATE members SET photo_path = ? WHERE id = ?", [$relativePath, $member['id']]);
    }
}
```

2. **Sanction Date Validation**: The system uses sanction dates to determine order. Ensure dates are entered correctly when adding sanctions.

3. **Permission Requirements**: All sanction operations require 'edit' permission on the respective member type (members or junior_members).

## Deployment Steps

1. **Backup Database**: Always backup before applying schema changes
2. **Apply Migration**: Run the migration script to add the 'operativo' type and create junior_member_sanctions table
3. **Deploy Code**: Update all modified files
4. **Test**: Follow the testing checklist above
5. **Optional**: Run photo path migration if there are existing photos with absolute paths

## Support

For issues or questions, refer to the main project documentation or contact the development team.
