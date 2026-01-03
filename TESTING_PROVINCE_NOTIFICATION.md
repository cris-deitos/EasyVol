# Testing Checklist for Province Notification Feature

## Prerequisites

1. **Database Setup**
   - **For new installations**: The schema is already included in `database_schema.sql`
   - **For existing installations**: Run the migration:
     ```bash
     mysql -u your_username -p your_database_name < migrations/add_province_notification_feature.sql
     ```

2. **Email Configuration**
   - Ensure SMTP settings are configured in Settings → Email
   - Verify that emails can be sent from the system

3. **Province Email Configuration**
   - Go to Settings → Association Data
   - Fill in "Email Ufficio Provinciale di Protezione Civile"
   - Save settings

## Test Scenarios

### Test 1: Send Email During Event Creation

**Steps:**
1. Log in as a user with event creation permissions
2. Navigate to Events → New Event
3. Fill in all event details:
   - Title: "Test Event Province Notification"
   - Event Type: Select any
   - Start Date: Set a date/time
   - Location: Add a location
   - Description: Add some description
4. Check the box "Invia email alla Provincia all'apertura dell'evento"
5. Click Save
6. Verify confirmation popup appears
7. Click Confirm

**Expected Results:**
- Event is created successfully
- You are redirected to the event view page
- In the "Notifica Provincia" card, you see:
  - Green header with "Email inviata alla Provincia"
  - Send date/time is displayed
  - Status shows "Inviata con successo"
  - Your username is shown as sender

**Check Email:**
- Province email address should receive an email with:
  - Event details
  - Access link
  - 8-character access code
  - Instructions

### Test 2: Send Email After Event Creation

**Steps:**
1. Create a new event WITHOUT checking the province notification checkbox
2. Save the event
3. View the event details
4. In the "Notifica Provincia" card, verify you see "Email non ancora inviata"
5. Click the button "Invia Email alla Provincia"
6. Confirm in the popup
7. Wait for the page to reload

**Expected Results:**
- Email is sent successfully
- Page reloads and shows email was sent
- Status card updates with timestamp and sender

### Test 3: Province Access - Authentication

**Steps:**
1. Open the access link from the email in a new browser (or incognito)
2. You should see a login form requesting the access code
3. Enter the wrong code → Should show error
4. Enter the correct 8-character code from the email
5. Click "Accedi"

**Expected Results:**
- Authentication succeeds
- Event details page loads
- Shows event information and interventions list

### Test 4: Province Access - View Event Data

**Steps:**
1. After successful authentication, verify you can see:
   - Event title, type, dates, location
   - Event description
   - Event status
   - Number of interventions

2. Scroll down to interventions section
3. Click on an intervention to expand it

**Expected Results:**
- All event data is visible and correctly formatted
- Interventions show with accordion functionality
- For each intervention, you can see:
  - Title, description, location, status
  - List of volunteers (ONLY fiscal codes, NO names)
  - Hours worked per volunteer

### Test 5: Excel Export

**Steps:**
1. While viewing the province access page
2. Click "Scarica Excel" button
3. Save the downloaded file
4. Open in Excel or LibreOffice

**Expected Results:**
- Excel file downloads successfully
- File name includes event title and date
- Contains one sheet per day of the event
- Each sheet shows:
  - Header with event title and date
  - Table with volunteer fiscal codes
  - Hours worked per volunteer
  - Interventions participated in
  - Total summary at bottom

### Test 6: Privacy Verification

**Steps:**
1. Create an event with at least one intervention
2. Assign some volunteers to the intervention
3. Send notification to province
4. Access the province page
5. View the intervention details

**Expected Results:**
- ONLY fiscal codes (codice fiscale) are visible
- NO volunteer names (first name, last name) are shown anywhere
- This applies to both the web view and Excel export

### Test 7: Session Management

**Steps:**
1. Access the province page with valid credentials
2. Note you are logged in
3. Close the browser completely
4. Open browser again and visit the same URL (with token)
5. Try to access without entering code again

**Expected Results:**
- Session is maintained (you should remain authenticated)
- If session expired, you're asked for the access code again

### Test 8: Logout Functionality

**Steps:**
1. Access province page and authenticate
2. Click "Esci" (logout) button in top-right
3. Confirm logout

**Expected Results:**
- You are logged out
- Redirected to authentication page
- Must enter access code again to view data

## Error Scenarios to Test

### Invalid Token
- Try accessing: `province_event_view.php?token=invalid`
- Should show "Token non valido" error

### Wrong Access Code
- Use correct token but wrong code
- Should show "Codice di accesso non valido" error

### No Province Email Configured
- Try sending notification without configuring province email in settings
- Should show appropriate error message

### Event Without Interventions
- Create event with notification
- Access province page
- Excel export should still work (empty or no data message)

## Post-Testing Verification

After all tests pass:
- [ ] Check database to verify all fields are populated correctly
- [ ] Review email logs to ensure emails were sent
- [ ] Verify no errors in PHP error log
- [ ] Test with real province email if available

## Notes

- Keep track of all access codes and tokens generated during testing
- Test with different event types (emergenza, esercitazione, attività)
- Verify behavior with events that have no end date (ongoing events)
- Test Excel export with events spanning multiple days

## Support

If any test fails, check:
1. PHP error logs
2. Database for correct data
3. Email configuration
4. Browser console for JavaScript errors
5. Network tab for failed API calls
