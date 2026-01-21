# Newsletter Management System Implementation

## Overview
This document describes the implementation of the complete internal newsletter management system for EasyVol.

## Features Implemented

### 1. Database Schema
Created three new tables:

#### `newsletters` Table
Stores newsletter information including:
- Subject and HTML body content
- Reply-to email address
- Status (draft, scheduled, sent, failed)
- Recipient filter configuration
- Sending statistics (total recipients, sent count, failed count)
- Timestamps for creation, scheduling, and sending
- User tracking (created by, sent by)
- Clone tracking (cloned from)

#### `newsletter_attachments` Table
Stores file attachments for newsletters:
- Original filename
- Server filepath
- File size

#### `newsletter_recipients` Table
Tracks individual email sends:
- Email address and recipient name
- Recipient type (member, junior_member, guardian)
- Link to email_queue entry
- Send status and error messages

### 2. Permission System
Added 5 new permissions under the `newsletters` module:
- **view**: View newsletters
- **create**: Create new newsletters
- **edit**: Edit draft newsletters
- **delete**: Delete draft newsletters (sent newsletters cannot be deleted)
- **send**: Send newsletters

These permissions can be assigned to:
- Individual users via user permissions
- Roles via role permissions

### 3. UI Components

#### Main Newsletter List (`newsletters.php`)
- Displays all newsletters with filtering capabilities
- Filters: Status, Date range, Search by subject
- Shows: ID, Subject, Status, Creation date, Send date/time, Recipients count, Send results, Created by, Sent by
- Actions: View, Edit (drafts only), Clone, Delete (drafts only)
- Pagination support

#### Newsletter Edit Page (`newsletter_edit.php`)
- Create new newsletters or edit drafts
- Rich HTML editor (TinyMCE) for composing content
- Subject and Reply-to fields
- File attachment support (max 10MB per file)
- Recipient selection:
  - All active members
  - All active cadets
  - All active cadets + their parents/guardians
  - All active members + active cadets
  - All active members + active cadets + parents/guardians
  - Custom selection of members (with search by name, surname, registration number, or fiscal code)
  - Custom selection of cadets (with search by name, surname, registration number, or fiscal code)
  - Custom selection of members + cadets (combined with separate search fields)
- Send options:
  - Save as draft
  - Send immediately
  - Schedule for specific date/time
- Clone functionality to duplicate existing newsletters

#### Newsletter View Page (`newsletter_view.php`)
- View all newsletter details
- Display HTML content
- List attachments
- Show recipient information
- Display send statistics
- List all recipients with their send status

### 4. Backend Components

#### Newsletter Model (`src/Models/Newsletter.php`)
Handles all database operations:
- CRUD operations for newsletters
- Attachment management
- Recipient selection based on filters
- Status updates (draft → scheduled → sent)
- Clone functionality
- Security features:
  - Empty custom recipient list protection
  - Proper SQL parameter binding

#### Newsletter Controller (`src/Controllers/NewsletterController.php`)
Business logic layer:
- Newsletter management operations
- File upload handling with security:
  - File extension whitelist (pdf, doc, docx, xls, xlsx, jpg, jpeg, png, gif, txt, zip)
  - Directory traversal protection
  - File size validation
  - Safe filename generation
- Email queue integration
- Recipient filtering and selection
- Send/schedule operations

### 5. Integration with Existing Systems

#### Email Queue Integration
- Newsletters use the existing `email_queue` table and cron job
- Each recipient gets a separate email queue entry
- Supports immediate sending and scheduled sending
- Tracks send status per recipient

#### Permission System Integration
- Fully integrated with existing permission checks
- Uses `$app->checkPermission()` for access control
- Supports both user and role-based permissions

#### Sidebar Menu Integration
- Added under "Gestione" (Management) section
- Only visible to users with `newsletters.view` permission
- Uses Bootstrap icons for consistency

## Security Features

1. **Input Validation**
   - All user inputs are validated and sanitized
   - Email addresses validated
   - Date/time validation for scheduling

2. **File Upload Security**
   - Whitelist of allowed file extensions
   - File size limits (10MB max)
   - Safe filename generation
   - Directory traversal protection
   - Validation of upload paths

3. **Access Control**
   - Permission checks on all operations
   - Draft-only editing (sent newsletters cannot be modified)
   - Draft-only deletion
   - CSRF protection on forms

4. **SQL Injection Prevention**
   - All queries use prepared statements
   - Parameter binding for all user inputs

5. **Recipient Selection Security**
   - Empty custom recipient lists return no results (prevents sending to all)
   - Validation of recipient IDs

## Usage Instructions

### Creating a Newsletter

1. Navigate to **Gestione → Newsletter**
2. Click **"Nuova Newsletter"**
3. Fill in:
   - Subject (required)
   - Reply-to email (optional)
   - HTML content using the visual editor (required)
   - Add attachments if needed
4. Select recipients:
   - Choose recipient type from dropdown
   - For custom selection, check individual recipients
5. Choose send option:
   - **Save as Draft**: Save for later editing
   - **Send Immediately**: Queue for immediate sending
   - **Schedule**: Set specific date and time
6. Click **"Salva"** to save

### Editing a Draft Newsletter

1. From the newsletter list, click the edit icon (pencil) on a draft
2. Make your changes
3. Save or send using the same options as creation

### Cloning a Newsletter

1. From the newsletter list, click the clone icon (files) on any newsletter
2. The newsletter will be duplicated as a new draft
3. Edit as needed and send

### Deleting a Newsletter

1. Only drafts can be deleted
2. Click the trash icon on a draft
3. Confirm deletion
4. Attachments are also deleted from the server

### Viewing Newsletter Details

1. Click the newsletter subject or the view icon (eye)
2. See all details including:
   - Full content
   - Attachments
   - Recipient list
   - Send statistics

## Migration Instructions

### Running the Migration

1. Execute the migration script:
   ```bash
   mysql -u username -p database_name < migrations/020_add_newsletter_system.sql
   ```

2. The migration will:
   - Create the three newsletter tables
   - Add newsletter permissions
   - Set up foreign key constraints

### Granting Permissions

After migration, grant permissions to appropriate roles/users:

```sql
-- Example: Grant all newsletter permissions to admin role (role_id = 1)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE module = 'newsletters';
```

## Email Queue Configuration

The newsletter system uses the existing email queue. Ensure:

1. Email configuration is set in your config
2. The email queue cron job is running:
   ```bash
   */5 * * * * php /path/to/easyvol/cron/email_queue.php
   ```

## File Storage

Attachments are stored in:
```
/uploads/newsletters/{newsletter_id}/
```

Ensure the web server has write permissions to the `uploads` directory.

## Technical Notes

### TinyMCE Editor
- Uses TinyMCE 6 from CDN
- Note: Replace 'no-api-key' with a valid API key for production
- Alternative: Host TinyMCE locally

### Recipient Filtering
Recipient filters are stored as JSON in the database:
```json
{
  "type": "all_members|all_cadets|all_cadets_with_parents|all_members_cadets|all_members_cadets_parents|custom_members|custom_cadets|custom_combined",
  "ids": [1, 2, 3],  // For custom_members and custom_cadets
  "member_ids": [1, 2, 3],  // For custom_combined (members)
  "cadet_ids": [4, 5, 6]  // For custom_combined (cadets)
}
```

### Send Process
1. Newsletter status is set to 'scheduled' or remains 'sent'
2. Each recipient gets an `email_queue` entry
3. Each recipient gets a `newsletter_recipients` entry
4. The email queue cron processes the emails
5. Recipient status is updated as emails are sent

## Database Schema Changes

All changes are documented in:
- `migrations/020_add_newsletter_system.sql` (migration script)
- `database_schema.sql` (updated schema file)

## Files Added

### Models
- `src/Models/Newsletter.php`

### Controllers
- `src/Controllers/NewsletterController.php`

### Views/Pages
- `public/newsletters.php` (main list)
- `public/newsletter_edit.php` (create/edit)
- `public/newsletter_view.php` (view details)

### API Endpoints
- `public/newsletter_delete.php` (delete newsletter)
- `public/newsletter_attachment_delete.php` (delete attachment)

### Database
- `migrations/020_add_newsletter_system.sql` (migration)
- `database_schema.sql` (updated)

### UI
- `src/Views/includes/sidebar.php` (updated with menu item)

## Testing Checklist

- [ ] Create a draft newsletter
- [ ] Edit a draft newsletter
- [ ] Add attachments to a newsletter
- [ ] Remove attachments from a newsletter
- [ ] Select all members as recipients
- [ ] Select all cadets as recipients
- [ ] Select cadets with parents as recipients
- [ ] Select custom members as recipients
- [ ] Select custom cadets as recipients
- [ ] Send a newsletter immediately
- [ ] Schedule a newsletter for later
- [ ] Clone an existing newsletter
- [ ] Delete a draft newsletter
- [ ] Verify sent newsletters cannot be edited
- [ ] Verify sent newsletters cannot be deleted
- [ ] Check permission restrictions work correctly
- [ ] Verify email queue entries are created
- [ ] Check recipient tracking
- [ ] Verify file upload restrictions

## Future Enhancements (Optional)

Potential improvements for future versions:
1. Newsletter templates
2. A/B testing capabilities
3. Read/open tracking
4. Click tracking for links
5. Unsubscribe management
6. Newsletter analytics dashboard
7. Preview mode before sending
8. Test send to specific email
9. Draft auto-save
10. Newsletter categories/tags
