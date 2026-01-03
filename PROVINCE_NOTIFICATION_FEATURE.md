# Province Notification Feature - Implementation Guide

This document provides instructions for setting up and using the Provincial Civil Protection notification feature in EasyVol.

## Overview

This feature allows associations to send event notifications to Provincial Civil Protection offices via email. The province receives:
- Event details (title, type, date, location, description)
- Secure access link with authentication
- Ability to view interventions and volunteer participation
- Excel export of volunteer fiscal codes (for privacy, only fiscal codes are shared, not names)

## Installation Steps

### 1. Database Migration

Run the database migration to add required fields:

```bash
mysql -u your_username -p your_database < migrations/add_province_notification_feature.sql
```

Or manually execute the SQL commands in the file.

### 2. Configure Provincial Email

1. Log in to EasyVol as an administrator
2. Navigate to **Settings** → **Association Data**
3. Find the field **"Email Ufficio Provinciale di Protezione Civile"**
4. Enter the email address of the Provincial Civil Protection office
5. Click **Save Changes**

### 3. Configure Email Settings

Ensure that email settings are properly configured in **Settings** → **Email Configuration** so that notification emails can be sent.

## Usage

### Creating an Event with Province Notification

1. Go to **Events** → **New Event**
2. Fill in all event details (title, type, date, location, description)
3. Check the box **"Invia email alla Provincia all'apertura dell'evento"** (Send email to Province on event opening)
4. Click **Save**
5. A confirmation popup will appear: "Sicuro di voler inviare una mail alla Provincia con le informazioni dell'Evento?"
6. Click **Confirm** to send the email, or **Cancel** to save without sending

### Sending Province Notification Later

If you didn't send the notification when creating the event:

1. Open the event by clicking on it in the event list
2. In the **Province Notification** card, you'll see "Email non ancora inviata" (Email not yet sent)
3. Click the button **"Invia Email alla Provincia"** (Send Email to Province)
4. Confirm in the popup
5. The email will be sent and the status will be updated

### Viewing Email Status

In the event view page, the **Province Notification** card shows:
- Whether the email was sent
- Date and time of sending
- Outcome (success/failure)
- User who sent the email

## Province Access

### For Provincial Civil Protection Officers

When the province receives the notification email, it contains:

1. **Event details**: Title, type, dates, location, description
2. **Access link**: A secure URL to view complete event data
3. **Access code**: An 8-character alphanumeric code for authentication

### Accessing the Event Data

1. Click the access link in the email
2. Enter the 8-character access code provided in the email
3. Click **Access**
4. You will see:
   - Complete event information
   - List of all interventions
   - Volunteer participation (fiscal codes only for privacy)
   - Option to download Excel report

### Downloading Excel Report

The Excel report contains:
- One sheet per day of the event
- List of volunteer fiscal codes for each day
- Hours worked per volunteer
- Interventions participated in
- Summary totals per day

## Security Features

- **Token-based access**: Each event has a unique 64-character secure token
- **Access code**: Additional 8-character authentication code
- **Session management**: Authentication is maintained in PHP sessions
- **Privacy protection**: Only fiscal codes are shown, not volunteer names
- **No login required**: Province can access without creating an EasyVol account

## Technical Details

### Database Schema Changes

New fields in `association` table:
- `provincial_civil_protection_email` - Email address for province notifications

New fields in `events` table:
- `province_email_sent` - Boolean flag
- `province_email_sent_at` - Timestamp of sending
- `province_email_sent_by` - User ID who sent
- `province_email_status` - Success/failure status
- `province_access_token` - 64-char secure token
- `province_access_code` - 8-char access code

### Files Added

- `migrations/add_province_notification_feature.sql` - Database migration
- `public/province_event_view.php` - Public access page for province
- `public/province_export_excel.php` - Excel export functionality

### Files Modified

- `public/settings.php` - Added province email configuration field
- `public/event_edit.php` - Added checkbox for sending notification
- `public/event_view.php` - Added status display and send button
- `public/event_ajax.php` - Added AJAX endpoint for sending email
- `src/Controllers/EventController.php` - Added email sending logic

## Troubleshooting

### Email not being sent

1. Check that the province email is configured in Settings
2. Verify email configuration is correct (SMTP settings)
3. Check the event view page for error details in the email status
4. Review server logs for error messages

### Province cannot access the page

1. Verify the access code was entered correctly (8 uppercase alphanumeric characters)
2. Check that the token in the URL is complete (64 characters)
3. Ensure the event still exists and hasn't been deleted
4. Check PHP session configuration on the server

### Excel export issues

1. Verify PhpSpreadsheet library is installed (`composer install`)
2. Check that interventions have members assigned
3. Ensure volunteer fiscal codes are properly recorded in the database

## Support

For issues or questions, please contact the development team or create an issue in the repository.
