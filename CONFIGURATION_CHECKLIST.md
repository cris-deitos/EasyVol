# Configuration Checklist for EasyVol

This document provides a checklist for configuring EasyVol, especially for the member application system.

## Database Migration

### Add Junior Member Type Column
The system now supports member types for junior members. Run the following migration:

```bash
php migrations/run_migration.php add_junior_member_type.sql
```

Or manually execute:

```sql
ALTER TABLE `junior_members` 
ADD COLUMN `member_type` enum('ordinario') DEFAULT 'ordinario' 
AFTER `registration_number`;
```

## PDF Generation Configuration

The system uses **mPDF** library for generating registration application PDFs.

### Requirements:
1. Composer dependencies installed:
   ```bash
   composer install
   ```
   
2. The `mpdf/mpdf` package must be available (already in composer.json)

3. Uploads directory with write permissions:
   ```bash
   mkdir -p uploads/applications
   chmod 755 uploads/applications
   ```

4. Configuration in `config/config.php`:
   ```php
   'uploads' => [
       'path' => __DIR__ . '/../uploads'
   ],
   'association' => [
       'name' => 'Your Association Name',
       'address' => 'Via Example, 123',
       'city' => 'City, Province',
       'phone' => '+39 123 456 7890',
       'email' => 'info@yourorg.org',
       'logo_path' => 'assets/images/logo.png' // Optional
   ]
   ```

### Troubleshooting PDF Generation:
- Check server error logs for detailed error messages
- Ensure mPDF library is installed via Composer
- Verify write permissions on uploads/applications directory
- Check that PHP has enough memory (mPDF requires at least 64MB)

## Email Configuration

The system uses **PHPMailer** for sending emails with PDF attachments.

### Requirements:
1. PHPMailer installed via Composer (already in composer.json)

2. Email configuration in `config/config.php`:
   ```php
   'email' => [
       'enabled' => true,
       'from_address' => 'noreply@yourorg.org',
       'from_name' => 'Your Association Name',
       'reply_to' => 'info@yourorg.org',
       'charset' => 'UTF-8',
       'encoding' => '8bit'
   ]
   ```

### For SMTP Configuration:
If using SMTP instead of PHP's mail() function, you'll need to modify EmailSender.php or configure your server's mail settings.

### Troubleshooting Email Sending:
- Check that email is enabled in configuration
- Verify email addresses are valid
- Check server's ability to send emails (many hosting providers block outbound emails)
- Review server error logs for detailed error messages
- Test with a simple script: `php -r "mail('test@example.com', 'Test', 'Test');"`

## Member and Junior Member Forms

### Changes Applied:

1. **Members (Soci)**:
   - Volunteer status dropdown now ordered: Operativo, In Formazione, Non Operativo
   - Nationality is now a dropdown with common countries
   - Provisions (provvedimenti) show "Attivo" instead of "Operativo"

2. **Junior Members (Cadetti)**:
   - Member type default is "Ordinario"
   - Status options: Attivo, Sospeso, Decaduto, Dimesso
   - Nationality is now a dropdown with common countries
   - Provisions work the same way as for regular members
   - Guardian fields required only for new entries

## Registration Application System

### How It Works:
1. User fills out public registration form (register_adult.php or register_junior.php)
2. Application is saved to database (always succeeds)
3. System attempts to generate PDF (may fail if not configured)
4. System attempts to send email (may fail if not configured)
5. User is shown success message with any warnings about PDF/email failures

### If PDF/Email Fails:
- The application is still saved in the database
- Administrators can regenerate PDF from the applications management page
- Check the error logs for specific failure reasons

## Testing

### Test Member Management:
1. Create a new member - verify volunteer status order
2. Create a new junior member - verify member_type and status options
3. Add a provision (provvedimento) - verify "Attivo" displays correctly
4. Check nationality dropdown has all countries

### Test Registration Applications:
1. Fill out adult registration form
2. Submit and check for success message
3. Check server logs for any PDF/email errors
4. Verify application appears in applications.php
5. Test PDF regeneration from applications management page

## Important Notes

- Application submissions will succeed even if PDF generation or email sending fails
- Error messages are logged to server error log
- Users will see warnings if PDF/email fails, but application is still saved
- PDF and email can be regenerated/resent from the admin panel (applications.php)
