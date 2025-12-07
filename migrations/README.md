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

### add_registration_applications_fields.sql
**Date**: 2025-12-07
**Purpose**: Add missing fields to registration applications tables

This migration adds the following columns to handle registration applications.

**Required**: Yes - This fixes database insertion errors where the application tries to save registration application fields.

### add_password_reset_functionality.sql
**Date**: 2025-12-07
**Purpose**: Add password reset functionality and force password change on first login

This migration adds:
- `must_change_password` column to users table (to force password change on first login)
- `password_reset_tokens` table (for password reset functionality)
- Email templates for user welcome and password reset

**Required**: Yes - This enables the new user management features including:
  - Welcome emails with default credentials
  - Forced password change on first login
  - Password reset functionality from login page

## Important Notes

- Always backup your database before applying migrations
- Migrations should be applied in order
- After applying migrations, update your `database_schema.sql` to reflect the changes for new installations
