# EasyCO - Centrale Operativa Guide

## Overview

**EasyCO** (Easy Centrale Operativa) is a parallel access system designed specifically for Operations Center staff. It provides a simplified, emergency-focused interface with limited functionality tailored for operational needs.

## Key Features

### 1. Separate Login System
- **Login URL**: `login_co.php`
- **Dedicated branding**: Orange/yellow emergency theme
- **Separate logout**: `logout_co.php`
- **Restricted access**: Only users with `is_operations_center_user` flag can access

### 2. Limited Interface
EasyCO users have access to:
- **Dashboard CO**: Real-time operational overview
- **Eventi**: View and manage emergency events
- **Radio Rubrica**: Manage radio equipment directory
- **Volontari Attivi**: View active volunteers (read-only)
- **Mezzi Attivi**: View active vehicles (read-only)

### 3. Read-Only Member Views
- List of active volunteers only
- Basic contact information
- Operational qualifications
- Courses and licenses
- **No editing capabilities**

### 4. Read-Only Vehicle Views
- List of all vehicles
- Filter by operational status
- Technical specifications
- **No editing capabilities**

## Setup Instructions

### 1. Database Migration

Run the migration to add the operations center user flag:

```bash
mysql -u username -p database_name < migrations/add_operations_center_user_flag.sql
```

Or use the migration runner:

```bash
php migrations/run_migration.php add_operations_center_user_flag.sql
```

### 2. Enable EasyCO for Users

1. Go to **Gestione Utenti** (Users Management)
2. Edit a user or create a new one
3. Check the box: **"Utente Centrale Operativa (EasyCO)"**
4. Save the user

The user will now be restricted to using the EasyCO login system.

### 3. Access EasyCO

Operations Center users should access the system via:
```
https://your-domain.com/public/login_co.php
```

## User Behavior

### For EasyCO Users
- **Cannot** access the main EasyVol login (`login.php`)
- **Must** use the dedicated EasyCO login (`login_co.php`)
- **See** orange/yellow branding throughout the interface
- **Have** limited read-only access to members and vehicles
- **Can** fully manage radio equipment and events

### For Regular Users
- **Cannot** access EasyCO if they don't have the flag
- **Use** the standard EasyVol interface
- **Have** full access based on their role permissions

## Technical Details

### New Database Fields
- `users.is_operations_center_user` (TINYINT(1) DEFAULT 0)

### New Files

#### Login System
- `public/login_co.php` - EasyCO login page
- `public/logout_co.php` - EasyCO logout handler

#### Navigation Components
- `src/Views/includes/navbar_operations.php` - EasyCO navbar
- `src/Views/includes/sidebar_operations.php` - EasyCO sidebar

#### Member Views
- `public/operations_members.php` - Members list (read-only)
- `public/operations_member_view.php` - Member detail (read-only)

#### Vehicle Views
- `public/operations_vehicles.php` - Vehicles list (read-only)
- `public/operations_vehicle_view.php` - Vehicle detail (read-only)

#### Styling
- `assets/css/easyco.css` - EasyCO theme (orange/yellow)

### Modified Files
- `public/login.php` - Redirects CO users to `login_co.php`
- `public/user_edit.php` - Added CO user checkbox
- `src/Controllers/UserController.php` - Handles `is_operations_center_user` field
- `public/operations_center.php` - Uses EasyCO components for CO users
- Radio pages - Use EasyCO components for CO users

## Branding

### Color Scheme
- **Primary Orange**: #ff8c00
- **Secondary Orange**: #ffa500
- **Accent Light Orange**: #ffb84d
- **Warning Red-Orange**: #ff6b00

### Design Philosophy
The orange/yellow color scheme was chosen to:
- Differentiate EasyCO from standard EasyVol (purple/blue)
- Represent emergency and operational urgency
- Provide high visibility for quick recognition

## Security Considerations

1. **Access Control**: CO users are restricted to their designated areas
2. **Read-Only Access**: Members and vehicles data is view-only for CO users
3. **Separate Sessions**: Each system maintains independent login sessions
4. **Activity Logging**: All CO logins are logged with dedicated activity types

## Permissions

EasyCO respects the standard EasyVol permission system. CO users still need appropriate permissions for:
- `operations_center::view` - View operations center dashboard
- `operations_center::edit` - Edit radio equipment
- `events::view` - View events
- `events::create` - Create new events

## Troubleshooting

### CO User Cannot Login
- Verify the `is_operations_center_user` flag is set to 1
- Ensure the user is active (`is_active = 1`)
- Check that they're using `login_co.php` not `login.php`

### Regular User Sees Error
- Regular users trying to access `login_co.php` will see an error
- They should use the standard `login.php` instead

### Branding Not Showing
- Clear browser cache
- Check that `easyco.css` is loaded
- Verify file permissions on `assets/css/easyco.css`

## Future Enhancements

Potential improvements for EasyCO:
- Real-time resource availability dashboard
- Push notifications for new events
- Quick contact features (call/SMS directly from interface)
- GPS tracking integration for vehicles and volunteers
- Radio frequency management
- Incident logging and reporting
