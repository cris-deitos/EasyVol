# User Management System Updates

## Overview

This document describes the comprehensive updates made to the EasyVol user management system to enhance security, usability, and administrative capabilities.

## Changes Implemented

### 1. Username Validation Updates ✓

**What Changed:**
- Usernames can now include dots (.) in addition to letters, numbers, and underscores
- Updated validation pattern from `[a-zA-Z0-9_]{3,}` to `[a-zA-Z0-9_.]{3,}`

**Files Modified:**
- `public/user_edit.php` - Updated validation regex and form input pattern
- `src/Controllers/UserController.php` - Username validation logic

**Example Valid Usernames:**
- `john.doe`
- `mario.rossi`
- `user_123`
- `admin.sistema`

### 2. Default Password for New Users ✓

**What Changed:**
- All new users are automatically assigned the password: `Pw@12345678`
- Password is automatically sent via email to the user
- Users are required to change this password on first login
- Default password is defined as a constant (`App::DEFAULT_PASSWORD`) for easy maintenance

**Files Modified:**
- `public/user_edit.php` - Automatic password assignment for new users
- `src/Controllers/UserController.php` - Default password logic in create method
- `src/App.php` - Added DEFAULT_PASSWORD constant

**Benefits:**
- Consistent, secure default password
- Reduces administrative overhead
- Users receive credentials via email automatically
- Easy to change default password in one place if needed

### 3. Welcome Email Notification ✓

**What Changed:**
- New users receive a welcome email containing:
  - Username
  - Default password
  - Link to login page
  - Instructions about required password change

**Files Modified:**
- `src/Controllers/UserController.php` - Added `sendWelcomeEmail()` method
- `src/Utils/EmailSender.php` - Fixed template column name from `name` to `template_name`
- `migrations/add_password_reset_functionality.sql` - Added email template

**Email Template:**
The welcome email includes:
- Greeting with user's full name
- Username and password in a highlighted box
- Security notice about password change requirement
- Direct link to login page

### 4. Forced Password Change on First Login ✓

**What Changed:**
- Added `must_change_password` flag to users table
- Users with this flag are redirected to password change page before accessing the system
- Cannot use the default password `Pw@12345678` as their new password

**Files Modified:**
- `public/login.php` - Check for `must_change_password` flag and redirect
- `public/change_password.php` - New page for forced password change
- `src/Controllers/UserController.php` - Set flag for new users and password resets
- `migrations/add_password_reset_functionality.sql` - Database schema update

**User Flow:**
1. User logs in with default credentials
2. System checks `must_change_password` flag
3. If set, redirects to change password page
4. User must choose a new password (different from default)
5. Upon success, user is logged in and flag is cleared

### 5. Password Reset Functionality ✓

**What Changed:**
- Added "Password dimenticata?" link on login page
- New password reset form accessible without login
- Users can reset password using username or email
- Password is reset to default `Pw@12345678`
- Reset password is sent via email (not shown in browser)
- `must_change_password` flag is set, forcing password change on next login

**Files Modified:**
- `public/login.php` - Added password reset link
- `public/reset_password.php` - New password reset page
- `src/Controllers/UserController.php` - Added `resetPassword()` and `sendPasswordResetEmail()` methods
- `migrations/add_password_reset_functionality.sql` - Added password reset email template

**Security Features:**
- Password is never displayed in the browser
- Password is only sent via email to the user's registered email address
- User is forced to change password on next login
- Activity is logged in the system

### 6. Role Management with Custom Permissions ✓

**What Changed:**
- Added ability to create new roles from UI
- Added ability to edit existing roles
- Custom permission assignment per role
- Visual permission selector organized by module
- Module-level checkbox for quick selection

**Files Modified:**
- `public/roles.php` - Added "Nuovo Ruolo" button and "Modifica" action
- `public/role_edit.php` - New role creation/editing page
- `src/Controllers/UserController.php` - Already had necessary methods

**Features:**
- Create unlimited custom roles
- Assign specific permissions from all available permissions
- Permissions organized by module (Soci, Utenti, Riunioni, etc.)
- Checkbox for each action (Visualizza, Crea, Modifica, Elimina)
- Module header checkbox to select/deselect all permissions for that module
- Visual indication of permission coverage

**Permission Management:**
- Role-based permissions (assigned to role)
- User-specific permissions (override/supplement role permissions)
- Merged permission system for flexible access control

### 7. Database Schema Updates ✓

**New Tables:**
- `password_reset_tokens` - Stores password reset tokens (for future enhancement)

**Modified Tables:**
- `users` - Added `must_change_password` TINYINT(1) column

**New Email Templates:**
- `user_welcome` - Welcome email for new users
- `password_reset` - Password reset notification email

**Files:**
- `migrations/add_password_reset_functionality.sql` - Complete migration script

## Installation Instructions

### Prerequisites
- EasyVol installed and configured
- Database access (MySQL/MariaDB)
- Email system configured in `config/config.php`

### Step 1: Apply Database Migration

**Option A: Using the migration runner script (Recommended)**
```bash
cd /path/to/easyvol
php migrations/run_migration.php add_password_reset_functionality.sql
```

**Option B: Using MySQL command line**
```bash
mysql -u your_username -p your_database_name < migrations/add_password_reset_functionality.sql
```

**Option C: Using phpMyAdmin**
1. Login to phpMyAdmin
2. Select your EasyVol database
3. Click "SQL" tab
4. Open `migrations/add_password_reset_functionality.sql` and copy contents
5. Paste into SQL query box
6. Click "Go"

### Step 2: Verify Email Configuration

Ensure your `config/config.php` has email properly configured:

```php
'email' => [
    'enabled' => true,
    'method' => 'smtp', // or 'sendmail' or 'mail'
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@example.com',
    'smtp_password' => 'your-password',
    'smtp_encryption' => 'tls',
    'from_email' => 'noreply@example.com',
    'from_name' => 'EasyVol',
],
```

### Step 3: Test the Features

1. **Test User Creation:**
   - Go to Gestione Utenti
   - Click "Nuovo Utente"
   - Fill in username (try using dots: `test.user`), email, and other details
   - Notice the password field is informational only
   - Save the user
   - Check the user's email for welcome message

2. **Test First Login:**
   - Logout if logged in
   - Login with the new username and default password `Pw@12345678`
   - You should be redirected to change password page
   - Try using the default password again (should fail)
   - Set a new password
   - Should be logged in successfully

3. **Test Password Reset:**
   - Logout
   - Click "Password dimenticata?" on login page
   - Enter username or email
   - Check email for reset password
   - Login with reset password
   - Should be forced to change password again

4. **Test Role Management:**
   - Go to Gestione Utenti > Gestione Ruoli
   - Click "Nuovo Ruolo"
   - Enter role name and description
   - Select permissions by module
   - Save role
   - Edit an existing role to verify changes are persisted

## Security Considerations

### Password Security
- Default password `Pw@12345678` meets complexity requirements
- Users cannot keep the default password
- Forced password change on first login
- Password reset always requires password change

### Email Security
- **Important Security Note**: Passwords are sent via email per requirements
  - Email transmission has inherent security risks (interception, server storage)
  - Mitigations in place:
    - Passwords are temporary/default only
    - Forced password change on first login
    - SMTP with TLS/SSL encryption should be configured
    - Users must change password immediately
    - Token-based reset is recommended for future enhancement
- No password display in browser during reset
- All password changes are logged in activity logs

### Access Control
- Role-based permission system
- User-specific permission overrides
- Granular permission control by module and action

### Audit Trail
- All user creation logged
- All password changes logged
- All password resets logged
- Activity logs include IP address and user agent

## Troubleshooting

### Users Not Receiving Emails

**Problem:** New users or password reset users don't receive emails

**Solutions:**
1. Check email configuration in `config/config.php`
2. Verify email server is accessible from your server
3. Check spam/junk folders
4. Review error logs in application logs
5. Test email configuration separately:
   ```php
   $emailSender = new \EasyVol\Utils\EmailSender($config, $db);
   $result = $emailSender->send('test@example.com', 'Test', 'Test message');
   var_dump($result);
   ```

### User List Not Showing Users

**Problem:** User list page shows "Nessun utente trovato"

**Possible Causes & Solutions:**
1. Database connection issue - Check `config/config.php`
2. No users in database - Create initial user via install script
3. Permission issue - Ensure logged-in user has `users.view` permission
4. Database schema outdated - Apply migrations

### Password Change Page Not Showing

**Problem:** Users not redirected to change password page

**Solutions:**
1. Verify migration was applied: Check if `must_change_password` column exists in users table
2. Check if flag is set: `SELECT must_change_password FROM users WHERE username='...'`
3. Clear sessions and try again

### Cannot Create New Roles

**Problem:** "Nuovo Ruolo" button not visible or page not accessible

**Solutions:**
1. Check user permissions: Ensure you have `users.create` permission
2. Verify role_edit.php file exists in public folder
3. Check file permissions on server

## Migration Rollback

If you need to rollback the changes:

```sql
-- Remove email templates
DELETE FROM email_templates WHERE template_name IN ('user_welcome', 'password_reset');

-- Remove must_change_password column
ALTER TABLE users DROP COLUMN must_change_password;

-- Remove password_reset_tokens table
DROP TABLE IF EXISTS password_reset_tokens;
```

**Warning:** Rollback will cause errors in the updated code. Only rollback if reverting code changes as well.

## Future Enhancements

Potential improvements for future versions:

1. **Token-based Password Reset:** Use secure tokens instead of direct password reset
2. **Password History:** Prevent reuse of recent passwords
3. **Two-Factor Authentication:** Add 2FA support for enhanced security
4. **Password Expiration:** Require periodic password changes
5. **Account Lockout:** Lock accounts after multiple failed login attempts
6. **Role Templates:** Predefined role templates for common use cases

## Support

For issues or questions:
1. Check this documentation
2. Review error logs in application
3. Check GitHub issues
4. Contact system administrator

## Technical Details

### Code Structure

**Controllers:**
- `UserController::create()` - Creates user with default password and sends welcome email
- `UserController::resetPassword()` - Resets password and sends email
- `UserController::sendWelcomeEmail()` - Sends welcome email to new users
- `UserController::sendPasswordResetEmail()` - Sends password reset email
- `UserController::createRole()` - Creates new role
- `UserController::updateRole()` - Updates existing role

**Pages:**
- `public/user_edit.php` - User creation/editing with new password logic
- `public/change_password.php` - Forced password change page
- `public/reset_password.php` - Password reset request page
- `public/role_edit.php` - Role creation/editing page
- `public/roles.php` - Role management overview
- `public/login.php` - Login with password reset link

**Utilities:**
- `Utils/EmailSender.php` - Email sending with template support

### Database Schema

**users table changes:**
```sql
ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) DEFAULT 0;
```

**New table:**
```sql
CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Changelog

### Version 1.1.0 (2025-12-07)

**Added:**
- Username validation now allows dots (.)
- Default password `Pw@12345678` for new users
- Welcome email with credentials for new users
- Forced password change on first login
- Password reset functionality from login page
- Role creation and editing with custom permissions
- Database migration for new features
- Email templates for user welcome and password reset

**Changed:**
- EmailSender to use correct database column name
- User creation flow to auto-assign default password
- Login flow to check for password change requirement

**Fixed:**
- Email template loading in EmailSender class

## Credits

Developed for EasyVol - Sistema Gestionale Protezione Civile

---

For more information, see the main README.md and other documentation files.
