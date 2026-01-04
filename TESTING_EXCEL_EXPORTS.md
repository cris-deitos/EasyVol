# Testing Guide - Excel Export Modifications

## Overview
This document describes how to test the Excel export modifications for the Provincial Civil Protection feature and the new internal management export.

## Changes Summary

### 1. Province Export (province_export_excel.php)
**Modified**: Simplified columns to show only fiscal codes

**What Changed:**
- ✂️ Removed: "ORE TOTALI" column
- ✂️ Removed: "INTERVENTI" column
- ✅ Kept: "N°" column (sequential number)
- ✅ Kept: "CODICE FISCALE" column (fiscal code)
- Data is still grouped by day with separate sheets

### 2. Internal Management Export (event_export_excel.php)
**New**: Full volunteer details export

**What's Included:**
- N° (sequential number)
- MATRICOLA (registration number)
- NOME (first name)
- COGNOME (last name)
- CODICE FISCALE (fiscal code)
- Data is grouped by day with separate sheets

### 3. Event View Page (event_view.php)
**Modified**: Added export button

**What Changed:**
- Added "Esporta Excel" button in the toolbar
- Button opens the internal management export

## Testing Instructions

### Prerequisites
1. Access to the EasyVol system
2. User with permissions to view events
3. At least one event with interventions and assigned volunteers

### Test Case 1: Province Export (Public Access)
**Purpose**: Verify simplified export with only fiscal codes

**Steps:**
1. Navigate to an event in the system
2. Send province notification email (if not already sent)
3. Open the province access link from the email
4. Enter the access code
5. Click "Scarica Excel" button
6. Open the downloaded Excel file

**Expected Results:**
- ✅ Excel file downloads successfully
- ✅ File contains sheets named by date (e.g., "04-01-2026")
- ✅ Each sheet has only 2 columns: "N°" and "CODICE FISCALE"
- ✅ NO columns for "ORE TOTALI" or "INTERVENTI"
- ✅ Each volunteer appears once per day (no duplicates)
- ✅ Summary row shows total volunteer count
- ✅ Styling is clean and professional

**Test Data Verification:**
- Count volunteers manually and compare with Excel totals
- Check that fiscal codes are correctly displayed
- Verify day grouping matches intervention dates

### Test Case 2: Internal Management Export
**Purpose**: Verify full details export for internal use

**Steps:**
1. Log in to the EasyVol system
2. Navigate to Events menu
3. Select an event with volunteers
4. Click "Esporta Excel" button in the toolbar
5. Excel file should download in a new tab
6. Open the downloaded Excel file

**Expected Results:**
- ✅ Excel file downloads successfully
- ✅ File contains sheets named by date
- ✅ Each sheet has 5 columns:
  - N° (sequential number)
  - MATRICOLA (registration number or "-" if empty)
  - NOME (first name)
  - COGNOME (last name)
  - CODICE FISCALE (fiscal code)
- ✅ Each volunteer appears once per day
- ✅ Summary row shows total volunteer count
- ✅ Styling matches the simplified export

**Test Data Verification:**
- Verify registration numbers match member records
- Check names and surnames are correctly displayed
- Verify fiscal codes match member records
- Count should match the simplified export

### Test Case 3: Access Control
**Purpose**: Verify security and permissions

**For Province Export:**
1. Try to access without token - should fail
2. Try to access with invalid token - should fail
3. Try to access with valid token but wrong code - should fail
4. Try to access after logout - should require re-authentication

**For Internal Export:**
1. Log out and try to access directly - should redirect to login
2. Log in with user without event view permissions - should be denied
3. Try with invalid event_id parameter - should show error

**Expected Results:**
- ✅ All unauthorized access attempts fail gracefully
- ✅ Error messages are appropriate
- ✅ No sensitive data exposed in errors

### Test Case 4: Data Consistency
**Purpose**: Verify both exports show the same volunteers

**Steps:**
1. Download both the province export and internal export
2. For each day sheet, compare:
   - Number of volunteers
   - Fiscal codes

**Expected Results:**
- ✅ Same number of volunteers in both exports per day
- ✅ Fiscal codes match between exports
- ✅ No missing or extra volunteers

### Test Case 5: Edge Cases
**Purpose**: Test unusual scenarios

**Scenarios to test:**
1. Event with no volunteers assigned
   - ✅ Should show "Nessun volontario registrato per questo evento"
   
2. Event with volunteers but no registration number
   - ✅ Internal export should show "-" for MATRICOLA
   
3. Event spanning multiple days
   - ✅ Should have multiple sheets, one per day
   
4. Volunteer participating on multiple days
   - ✅ Should appear once per day sheet
   
5. Multiple interventions on same day
   - ✅ Volunteer should appear only once per day

### Test Case 6: File Format
**Purpose**: Verify Excel file quality

**Checks:**
- ✅ Opens correctly in Microsoft Excel
- ✅ Opens correctly in LibreOffice Calc
- ✅ Opens correctly in Google Sheets
- ✅ Cell formatting is preserved
- ✅ Colors and borders display correctly
- ✅ Column widths are appropriate
- ✅ No hidden columns or sheets

## Troubleshooting

### Problem: Excel file doesn't download
**Solution:** Check PHP error logs, verify PhpSpreadsheet library is installed

### Problem: Empty export
**Solution:** Verify event has interventions with assigned volunteers

### Problem: Missing volunteers
**Solution:** Check that volunteers are assigned to interventions, not just to the event

### Problem: Access denied
**Solution:** Verify user has 'events' -> 'view' permission

## Success Criteria

All tests pass when:
- ✅ Province export shows only fiscal codes (no hours/interventions)
- ✅ Internal export shows complete volunteer details
- ✅ Both exports group data by day correctly
- ✅ Security controls work properly
- ✅ Data is consistent between exports
- ✅ Files open correctly in Excel applications

## Related Files
- `/public/province_export_excel.php` - Province export (simplified)
- `/public/event_export_excel.php` - Internal management export (full details)
- `/public/event_view.php` - Event detail page with export button
- `/public/province_event_view.php` - Public province access page

## Notes
- Both exports require the event to have interventions with assigned volunteers
- Province export requires token authentication
- Internal export requires user login and permissions
- Data privacy: Province export shows only fiscal codes (minimal personal data)
- Internal export shows full details (for authorized personnel only)
