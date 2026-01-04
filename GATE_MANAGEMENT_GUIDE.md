# Gate Management System - Installation and Testing Guide

## Database Migration

To apply the database migration for the Gate Management System, execute the following SQL script:

```bash
mysql -u [username] -p [database_name] < migrations/20260104_gate_management_system.sql
```

Or import it via phpMyAdmin or your preferred database management tool.

The migration creates:
1. `gate_system_config` - System configuration table (active/inactive state)
2. `gates` - Gates table with all fields for people counting
3. `gate_activity_log` - Activity log for tracking all gate operations
4. Permissions entries for gate management module

## Required Permissions

The following permissions are added by the migration:
- `gate_management` / `view` - View gate management system
- `gate_management` / `edit` - Edit gates and system configuration
- `gate_management` / `delete` - Delete gates

Assign these permissions to the appropriate roles for admin users.

## File Structure

### Backend
- `/src/Controllers/GateController.php` - Main controller for gate operations
- `/public/api/gates.php` - API endpoint for all gate operations

### Admin Interface
- `/public/gate_management.php` - Main gate management page (requires login)
- `/public/gate_map_fullscreen.php` - Fullscreen map view (requires login)

### Public Interfaces
- `/public/public_gate_manage.php` - Mobile-optimized gate management (no login)
- `/public/public_gate_display.php` - Public display board for large screens (no login)

## Features Implemented

### Admin Interface (`gate_management.php`)
- ✅ System on/off toggle with status indicator
- ✅ Gates list with inline editing:
  - Add/Edit/Delete gates
  - Modify limit manual inline
  - Change limit in use inline
  - Update people count inline
- ✅ OpenStreetMap integration with colored markers:
  - Green = Open gate
  - Red = Closed gate
  - Gray = Unmanaged gate
- ✅ Auto-refresh every 5 seconds on map tab
- ✅ Fullscreen map view option

### Public Mobile Interface (`public_gate_manage.php`)
- ✅ Gate selection dropdown
- ✅ Mobile-optimized responsive layout
- ✅ Fixed viewport (no scrolling)
- ✅ Add Person button (green) - disabled when gate is closed
- ✅ Remove Person button (orange) - disabled when gate is closed
- ✅ Open Gate button (dark green) - disabled when gate is open
- ✅ Close Gate button (red) - disabled when gate is closed
- ✅ Real-time updates without page refresh (every 2 seconds)
- ✅ Flashing red warning when limit is exceeded
- ✅ System disabled message

### Public Display Board (`public_gate_display.php`)
- ✅ Association logo and name in header
- ✅ Total people count (Open + Closed gates only, excludes Non Gestito)
- ✅ Table with gate information:
  - Gate number and name
  - Status badge (colored)
  - Current limit value
  - People count (bold)
- ✅ OpenStreetMap with gate markers
- ✅ Auto-refresh every 1 second
- ✅ Fullscreen layout optimized for large screens
- ✅ System disabled message

## Testing Steps

### 1. Database Setup
```bash
# Apply migration
mysql -u root -p easyvol < migrations/20260104_gate_management_system.sql

# Verify tables were created
mysql -u root -p easyvol -e "SHOW TABLES LIKE 'gate%';"
```

### 2. Permission Setup
Grant permissions to admin roles:
```sql
-- Get permission IDs
SELECT id, module, action FROM permissions WHERE module = 'gate_management';

-- Grant to role (replace role_id with actual admin role ID)
INSERT INTO role_permissions (role_id, permission_id) 
SELECT 1, id FROM permissions WHERE module = 'gate_management';
```

### 3. Test Admin Interface
1. Login as admin user
2. Go to Centrale Operativa (Dispatch)
3. Click "Gestione Varchi" button (should be visible if permissions are correct)
4. Toggle system on/off
5. Add test gates with GPS coordinates
6. Test inline editing of limits and people count
7. View gates on map
8. Test fullscreen map view

### 4. Test Public Mobile Interface
1. Open `public_gate_manage.php` (no login required)
2. Select a gate from dropdown
3. Test Add/Remove person buttons
4. Test Open/Close gate buttons
5. Verify real-time updates
6. Verify limit warning appears when exceeded
7. Turn system off and verify disabled message

### 5. Test Public Display Board
1. Open `public_gate_display.php` on large screen
2. Verify association logo and name appear
3. Verify total count shows sum of Open + Closed gates
4. Verify table displays all gates correctly
5. Verify map shows all gates with correct colors
6. Verify auto-refresh works (1 second interval)
7. Turn system off and verify disabled message

## API Endpoints

### Public Endpoints (No Authentication)
- `GET /api/gates.php?action=list` - Get all gates and system status
- `GET /api/gates.php?action=get&id={id}` - Get single gate
- `GET /api/gates.php?action=system_status` - Get system status
- `GET /api/gates.php?action=total_count` - Get total people count
- `POST /api/gates.php` with `action=add_person` - Add person to gate
- `POST /api/gates.php` with `action=remove_person` - Remove person from gate
- `POST /api/gates.php` with `action=open_gate` - Open gate
- `POST /api/gates.php` with `action=close_gate` - Close gate

### Admin Endpoints (Authentication Required)
- `POST /api/gates.php` with `action=toggle_system` - Toggle system on/off
- `POST /api/gates.php` with `action=create` - Create new gate
- `POST /api/gates.php` with `action=update` - Update gate
- `POST /api/gates.php` with `action=delete` - Delete gate
- `POST /api/gates.php` with `action=set_count` - Set manual people count

## Security Notes

1. Public endpoints check system status before allowing operations
2. Admin endpoints require authentication and permissions
3. All gate operations are logged in `gate_activity_log`
4. Input validation is performed on all data
5. People count cannot go below 0

## Browser Compatibility

Tested and working on:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Android)

## Mobile Device Notes

The public gate management interface (`public_gate_manage.php`) is optimized for:
- Viewport fixed to screen size (no scrolling)
- Large touch-friendly buttons
- Auto-updates without manual refresh
- Works in portrait mode on smartphones
- Tested viewport sizes: 360x640 to 414x896

## Troubleshooting

### Issue: "Accesso negato" when accessing gate_management.php
**Solution:** Ensure user has `gate_management` / `view` permission

### Issue: Gates not appearing on map
**Solution:** Verify gates have valid latitude/longitude coordinates

### Issue: Real-time updates not working
**Solution:** Check browser console for JavaScript errors, verify API endpoint is accessible

### Issue: Total count shows incorrect value
**Solution:** Total count only includes gates with status 'aperto' or 'chiuso', not 'non_gestito'

### Issue: Buttons don't disable properly
**Solution:** Clear browser cache and refresh page

## Future Enhancements

Possible improvements for future versions:
- Export gate activity logs
- Historical reports of people flow
- SMS/Email notifications when limits are reached
- Multiple limit profiles (e.g., normal/emergency)
- Gate scheduling (auto-open/close at specific times)
- QR code scanning for faster gate management
- Integration with turnstiles or automatic counters
- Dashboard statistics and charts
- Multi-event support (multiple events with different gates)
