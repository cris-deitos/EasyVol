# Database Migrations

## How to apply migrations

### Using MySQL command line:

```bash
mysql -u your_username -p your_database_name < migrations/add_member_fields.sql
```

### Using phpMyAdmin:
1. Login to phpMyAdmin
2. Select your database
3. Click on "SQL" tab
4. Copy and paste the contents of the migration file
5. Click "Go" to execute

### Using PHP script:

Create a script to run the migration:

```php
<?php
require_once 'src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

$app = App::getInstance();
$db = $app->getDb();

$sql = file_get_contents('migrations/add_member_fields.sql');
$db->execute($sql);

echo "Migration completed successfully!\n";
```

## Available Migrations

### fix_user_creation_issues.sql (RECOMMENDED - Run This First)
**Date**: 2025-12-07
**Purpose**: Comprehensive fix for user creation and email issues

This migration fixes all issues related to:
- User creation errors ("Errore durante il salvataggio dell'utente")
- Missing `must_change_password` column in users table
- Missing `email_logs` table for email logging
- Missing `password_reset_tokens` table
- Email template configuration

**What it does**:
- Adds `must_change_password` column to users table
- Creates `email_logs` table for email tracking
- Creates `password_reset_tokens` table for password reset functionality
- Inserts email templates for user_welcome and password_reset

**Required**: YES - This is the most important migration to run. It consolidates all user management fixes and ensures user creation works properly even if email is not configured or PHPMailer is not installed.

### add_registration_applications_fields.sql
**Date**: 2025-12-07
**Purpose**: Add missing fields to registration applications tables

This migration adds the following columns to handle registration applications.

**Required**: Yes - This fixes database insertion errors where the application tries to save registration application fields.

### add_password_reset_functionality.sql (DEPRECATED - Use fix_user_creation_issues.sql instead)
**Date**: 2025-12-07
**Purpose**: Add password reset functionality and force password change on first login

**Note**: This migration is included in `fix_user_creation_issues.sql`. You only need to run one or the other, not both.

### add_member_notes_table.sql
**Date**: 2025-12-07
**Purpose**: Add member_notes table that was missing from the database schema

This migration adds:
- `member_notes` table (for storing notes about members)

**Required**: Yes - This fixes the error "Table 'member_notes' doesn't exist" when viewing or creating member records.

### add_email_config_to_database.sql
**Date**: 2025-12-08
**Purpose**: Add email configuration to database for web-based management

This migration adds email configuration settings to the `config` table, allowing administrators to manage email settings from the web interface (Settings > Email) instead of editing the `config.php` file.

**What it adds**:
- `email_from_address` - Sender email address
- `email_from_name` - Sender name
- `email_reply_to` - Reply-to address
- `email_return_path` - Return path for bounces
- `email_charset` - Character encoding (UTF-8, ISO-8859-1, etc.)
- `email_encoding` - Content encoding (8bit, 7bit, base64, quoted-printable)
- `email_sendmail_params` - Additional sendmail parameters
- `email_additional_headers` - Custom email headers

**Required**: No - But highly recommended for easier email configuration management

**Benefits**:
- Configure email settings via web interface
- No need to edit PHP files
- Supports additional sendmail headers and parameters
- Database values override config.php values

See `EMAIL_CONFIG_DATABASE_GUIDE.md` for complete documentation.

## Important Notes

- Always backup your database before applying migrations
- Migrations should be applied in order
- After applying migrations, update your `database_schema.sql` to reflect the changes for new installations
