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
require_once 'src/Database.php';

$db = new EasyVol\Database($config['database']);

$sql = file_get_contents('migrations/add_member_fields.sql');
$db->execute($sql);

echo "Migration completed successfully!\n";
```

## Available Migrations

### add_member_fields.sql
**Date**: 2025-12-07
**Purpose**: Add missing fields to members and junior_members tables

This migration adds the following columns:
- `gender` (enum: 'M', 'F')
- `nationality` (varchar, default: 'Italiana')
- `birth_province` (varchar)
- `photo_path` (varchar)
- `created_by` (int)
- `updated_by` (int)

**Required**: Yes - This fixes database insertion errors where the application tries to save these fields but they don't exist in the database.

## Important Notes

- Always backup your database before applying migrations
- Migrations should be applied in order
- After applying migrations, update your `database_schema.sql` to reflect the changes for new installations
