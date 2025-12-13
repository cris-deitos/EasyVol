# Database Migrations

## How to apply migrations

### Using MySQL command line:

```bash
mysql -u your_username -p your_database_name < migrations/migration_file.sql
```

### Using phpMyAdmin:
1. Login to phpMyAdmin
2. Select your database
3. Click on "SQL" tab
4. Copy and paste the contents of the migration file
5. Click "Go" to execute

### Using PHP script:

Use the provided migration runner:

```bash
php migrations/run_migration.php migration_file.sql
```

## Available Migrations

Migration files add new features and columns to an existing database. For new installations, use `database_schema.sql` which contains the complete schema.

### add_registration_applications_fields.sql
Add fields to registration applications tables.

### add_password_reset_functionality.sql
Add password reset functionality and force password change on first login.

### add_member_notes_table.sql
Add member_notes table.

### add_email_config_to_database.sql
Add email configuration to database for web-based management.

### add_worker_type_and_education_level.sql
Add worker type and education level fields.

### add_amount_to_fee_payment_requests.sql
Add amount field to fee payment requests.

### add_print_templates_table.sql
Add print templates table.

### add_scheduler_references.sql
Add scheduler reference fields.

### add_smtp_config_to_database.sql
Add SMTP configuration to database.

### add_sspc_course_fields.sql
Add SSPC course fields.

### add_training_sessions_and_exam_fields.sql
Add training sessions and exam fields.

### add_junior_member_type.sql
Add junior member type.

### add_member_badge_number.sql
Add member badge number field.

### add_notes_to_member_availability.sql
Add notes to member availability.

### add_operativo_sanction_and_junior_sanctions.sql
Add operativo sanction and junior sanctions.

### add_association_phone.sql
Add association phone field.

### add_application_pdf_download_token.sql
Add application PDF download token.

### create_import_logs_table.sql
Create import logs table.

### create_member_availability_table.sql
Create member availability table.

### insert_default_print_templates.sql
Insert default print templates.

### make_maintenance_description_optional.sql
Make maintenance description optional.

### add_operations_center_user_flag.sql
Add `is_operations_center_user` flag to users table for EasyCO (Centrale Operativa) access system. This migration enables a parallel login system for operations center users with limited functionality.

### add_event_participants_and_vehicles_tables.sql
Add `event_participants` and `event_vehicles` tables to track member participation and vehicle usage in events. These tables are essential for the events management system to function properly.

## Important Notes

- Always backup your database before applying migrations
- For new installations, use `database_schema.sql` instead of individual migrations
- Migrations are for updating existing databases only
