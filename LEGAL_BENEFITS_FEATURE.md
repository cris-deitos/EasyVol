# Legal Benefits Recognition Feature

## Overview
This feature adds the ability to indicate if legal benefits (Art. 39 and 40 D. Lgs. n. 1 del 2018) are recognized for an event.

## Database Changes

### Migration Required
Run the following SQL migration to add the new field to the `events` table:

```bash
mysql -u [username] -p [database_name] < migrations/add_legal_benefits_to_events.sql
```

Or apply the following SQL directly:

```sql
ALTER TABLE `events` 
ADD COLUMN `legal_benefits_recognized` ENUM('no', 'si') NOT NULL DEFAULT 'no' 
COMMENT 'Benefici di Legge riconosciuti (Art. 39 e 40 D. Lgs. n. 1 del 2018)' 
AFTER `province_access_code`;
```

## Features Added

### 1. Event Creation/Editing Form (`event_edit.php`)
- Added dropdown field "Benefici di Legge" with options: NO (default), SI
- Field label includes reference to "Art. 39 e 40 D. Lgs. n. 1 del 2018"
- Field is positioned in the "Stato" (Status) section alongside the event status

### 2. Event Detail View (`event_view.php`)
- Displays legal benefits status as a badge (green for SI, gray for NO)
- Shows reference to the legal article
- Located in the "Dati Evento" (Event Data) section

### 3. Event List Page (`events.php`)
- Added "Benefici" column to the events table
- Shows badge with current status (SI/NO)
- Tooltip displays the full legal reference

### 4. Province Event View (`province_event_view.php`)
- Displays legal benefits status for provincial office viewing
- Shows badge with current status and legal reference
- Located in the event details section

### 5. Annual Event Report
- Updated `ReportController::eventsByTypeAndCount()` method
- Includes `legal_benefits_recognized` field in the exported Excel report
- Available in downloadable annual event reports

## Technical Implementation

### Backend Changes
1. **EventController.php**
   - Updated `create()` method to handle `legal_benefits_recognized` field
   - Updated `update()` method to handle `legal_benefits_recognized` field
   - Field defaults to 'no' if not provided

2. **ReportController.php**
   - Updated `eventsByTypeAndCount()` method to include the new field in reports
   - Field is included in SQL SELECT and GROUP BY clauses

### Frontend Changes
1. **event_edit.php**
   - Added form select field for legal benefits recognition
   - Defaults to 'no' when creating new events
   - Preserves existing value when editing events

2. **event_view.php**
   - Displays legal benefits status with visual badge
   - Shows legal article reference

3. **events.php**
   - Added table column for quick status viewing
   - Badge indicates SI (green) or NO (gray)

4. **province_event_view.php**
   - Provincial office view includes the field
   - Consistent badge styling with other pages

## Usage

### Creating a New Event
1. Navigate to Events > New Event
2. Fill in event details
3. In the "Stato" section, select "SI" or "NO" for "Benefici di Legge"
4. Default is "NO" if not explicitly set
5. Save the event

### Editing an Event
1. Navigate to Events > Select Event > Edit
2. The current value is pre-selected in the dropdown
3. Change to "SI" or "NO" as needed
4. Save changes

### Viewing Legal Benefits Status
- **Event List**: Check the "Benefici" column
- **Event Detail**: View in the "Dati Evento" section
- **Province View**: Visible in the event information panel
- **Annual Report**: Included in the exported Excel file

## Default Behavior
- All new events default to "NO" for legal benefits recognition
- Existing events (created before this feature) will have "NO" as the default value after migration
- The field is required and cannot be left empty

## Legal Reference
This feature implements tracking for benefits as defined in:
- **Articolo 39** - D. Lgs. n. 1 del 2018
- **Articolo 40** - D. Lgs. n. 1 del 2018

These articles relate to legal benefits for volunteers in civil protection activities.
