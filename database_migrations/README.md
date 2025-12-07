# Database Migrations

This directory contains database migration files to update existing installations.

## How to Apply Migrations

### Method 1: MySQL Command Line

```bash
mysql -u username -p database_name < 001_add_user_permissions.sql
```

### Method 2: phpMyAdmin

1. Login to phpMyAdmin
2. Select your database
3. Go to the "SQL" tab
4. Copy and paste the contents of the migration file
5. Click "Go"

### Method 3: MySQL Workbench

1. Open MySQL Workbench
2. Connect to your database
3. Open the migration file
4. Execute the queries

## Migration Files

### 001_add_user_permissions.sql

**Created**: 2025-12-07

**Purpose**: Add support for individual user permissions beyond role-based permissions.

**Changes**:
- Creates `user_permissions` table
- Allows assigning specific permissions to individual users

**Required**: Yes, for new user permission management feature

## Notes

- Migrations are designed to be idempotent (safe to run multiple times)
- Always backup your database before applying migrations
- New installations already include all migrations in the main schema file
