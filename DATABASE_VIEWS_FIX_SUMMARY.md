# Database Views Fix Summary

## Issue Description

The advanced dashboard was not functioning correctly due to errors in three database views that start with `v_`:

- `v_yoy_event_stats` (Year-over-Year Event Statistics)
- `v_yoy_member_stats` (Year-over-Year Member Statistics)
- `v_intervention_geographic_stats` (Intervention Geographic Statistics)

These views are correctly defined as MySQL VIEWs (not tables), but had data inconsistencies that caused runtime errors.

## Root Causes Identified

### 1. v_yoy_event_stats - Status Enum Mismatch

**Problem:**
The view was checking for status value `'completato'`, but the `events` table only has the following enum values:
- `'in_corso'`
- `'concluso'`
- `'annullato'`

**Impact:**
The `completed_events` count was always 0, even when events were completed.

**Fix:**
Changed the status check from `'completato'` to `'concluso'` to match the actual enum value in the `events` table.

```sql
-- Before:
SUM(CASE WHEN status = 'completato' THEN 1 ELSE 0 END) as completed_events

-- After:
SUM(CASE WHEN status = 'concluso' THEN 1 ELSE 0 END) as completed_events
```

### 2. v_intervention_geographic_stats - Missing Column

**Problem:**
The `DashboardController.php` expects a `province` column in the query results:
```php
SELECT 
    intervention_id,
    title,
    municipality,
    province,  // <-- Expected but not provided by view
    start_date,
    event_type,
    latitude,
    longitude,
    volunteer_count,
    total_hours
FROM v_intervention_geographic_stats
```

However, neither the `events` table nor the `interventions` table have a `province` column - they only have `municipality`.

**Impact:**
SQL errors when querying the view from the dashboard controller.

**Fix:**
Added `province` as a NULL column in the view since the underlying tables don't have this field:

```sql
-- After:
SELECT 
    i.id as intervention_id,
    i.title,
    e.municipality,
    NULL as province,  // <-- Added this line
    e.start_date,
    ...
```

The JavaScript in `dashboard_advanced.php` already handles NULL/undefined values with the `||` operator:
```javascript
${intervention.municipality || ''}, ${intervention.province || ''}
```

### 3. v_yoy_member_stats - No Issues Found

This view is correctly implemented and no changes were needed.

## Files Modified

### 1. migrations/013_add_advanced_dashboard_features.sql
- Fixed the original migration file to have correct SQL
- Changed status check from 'completato' to 'concluso'
- Added NULL as province column

### 2. migrations/015_fix_dashboard_views.sql (NEW)
- Created new migration to fix existing databases
- Includes DROP VIEW IF EXISTS for safe re-creation
- Fully documented with comments explaining each fix

### 3. database_schema.sql
- Updated to reflect the corrected view definitions
- Ensures new installations have correct views from the start

## Testing Recommendations

To test these fixes:

1. **For new installations:**
   - Install from scratch using updated `database_schema.sql`
   - Navigate to "Dashboard Statistiche Avanzate"
   - Verify all charts and geographic maps work correctly

2. **For existing installations:**
   - Run migration 015: `mysql -u [user] -p [database] < migrations/015_fix_dashboard_views.sql`
   - Clear any dashboard caches in `dashboard_stats_cache` table
   - Navigate to "Dashboard Statistiche Avanzate"
   - Verify year-over-year event statistics show correct completed events count
   - Verify geographic intervention map displays correctly

3. **Verify view structure:**
   ```sql
   DESCRIBE v_yoy_event_stats;
   DESCRIBE v_intervention_geographic_stats;
   DESCRIBE v_yoy_member_stats;
   ```

4. **Check data integrity:**
   ```sql
   -- Should show completed events count
   SELECT * FROM v_yoy_event_stats WHERE year = YEAR(CURDATE());
   
   -- Should include province column (will be NULL)
   SELECT * FROM v_intervention_geographic_stats LIMIT 5;
   
   -- Should work without errors
   SELECT * FROM v_yoy_member_stats WHERE year = YEAR(CURDATE());
   ```

## Impact Assessment

### Positive Impacts:
- ✅ Advanced dashboard will now display correct statistics
- ✅ Year-over-year event comparisons will show accurate completed event counts
- ✅ Geographic intervention maps will render without SQL errors
- ✅ No breaking changes to existing functionality
- ✅ Future-proof: if province field is added to tables later, just update the view

### No Negative Impacts:
- ✅ Views remain as VIEWs (not tables) - correct approach
- ✅ No data loss
- ✅ No schema breaking changes
- ✅ JavaScript already handles NULL province values gracefully

## Maintenance Notes

### If Province Field is Added in Future:

If `province` is added to `events` or `interventions` tables in the future:

```sql
-- Update the view to use real province data instead of NULL
CREATE OR REPLACE VIEW `v_intervention_geographic_stats` AS
SELECT 
    i.id as intervention_id,
    i.title,
    e.municipality,
    e.province,  -- Change from NULL to e.province
    e.start_date,
    e.event_type,
    i.latitude,
    i.longitude,
    COUNT(DISTINCT im.member_id) as volunteer_count,
    SUM(im.hours_worked) as total_hours
FROM interventions i
LEFT JOIN events e ON i.event_id = e.id
LEFT JOIN intervention_members im ON i.id = im.intervention_id
WHERE i.latitude IS NOT NULL AND i.longitude IS NOT NULL
GROUP BY i.id, i.title, e.municipality, e.province, e.start_date, e.event_type, i.latitude, i.longitude;
```

## Summary

The issue was not that the views were "incorrectly created" as tables - they are correctly defined as VIEWs. The problem was:
1. **Data inconsistency**: Using wrong enum value for event status
2. **Schema mismatch**: Controller expecting a column that doesn't exist

Both issues have been resolved with minimal changes that maintain backward compatibility while fixing the advanced dashboard functionality.
