# Testing Instructions - Print Templates Restoration

## Pre-Testing Checklist

Before testing, ensure:
- [ ] Database is accessible and running
- [ ] You have admin/settings edit permissions
- [ ] The application is running (via web server)
- [ ] Files are in place:
  - [ ] `seed_print_templates.sql` (root directory)
  - [ ] `public/restore_print_templates.php`
  - [ ] `SEED_TEMPLATES_README.md`

---

## Test Case 1: Web Interface Restoration (Primary Method)

### Prerequisites
- Empty `print_templates` table OR simulate by checking settings page

### Steps
1. **Login** to EasyVol as administrator
2. **Navigate** to: Impostazioni (Settings) → Modelli di Stampa section
3. **Observe**: "Nessun modello di stampa trovato" message
4. **Click**: Blue button "Ripristina Template Predefiniti"
5. **Review**: Information page showing:
   - List of 10 templates to be restored
   - Warning about non-overwriting
   - Confirmation form
6. **Click**: "Ripristina Template" button
7. **Confirm**: Browser confirmation dialog

### Expected Results
✅ Success message: "Ripristinato X template di stampa predefiniti"  
✅ Two buttons visible: "Torna alle Impostazioni" and "Visualizza Template"  
✅ Activity log entry created with user_id, action='restore_templates'

### Verification
1. Click "Torna alle Impostazioni"
2. Verify 10 templates are listed in the table
3. Check each template has:
   - Name (Italian)
   - Entity type (members, vehicles, meetings, events)
   - Template type (single, list, relational, multi_page)
   - Active status = ✅
   - Actions buttons (Edit, Delete, Preview)

---

## Test Case 2: SQL File Import (Alternative Method)

### Prerequisites
- MySQL client access OR phpMyAdmin
- `seed_print_templates.sql` file accessible

### Steps (Command Line)
```bash
mysql -u [username] -p [database_name] < seed_print_templates.sql
# Enter password when prompted
```

### Steps (phpMyAdmin)
1. Login to phpMyAdmin
2. Select EasyVol database
3. Click "Import" tab
4. Choose file: `seed_print_templates.sql`
5. Click "Go" or "Execute"

### Expected Results
✅ No SQL errors  
✅ Query executed successfully message  
✅ 10 INSERT statements executed

### Verification
```sql
SELECT COUNT(*) FROM print_templates;
-- Expected: 10 (or more if you had existing templates)

SELECT name, entity_type, is_active FROM print_templates ORDER BY entity_type, name;
-- Should show all 10 templates listed
```

---

## Test Case 3: Duplicate Prevention

### Prerequisites
- Templates already restored once

### Steps
1. Navigate to `restore_print_templates.php` again
2. Click "Ripristina Template" button
3. Confirm

### Expected Results
✅ Message: "Nessun template da ripristinare. Potrebbero essere già presenti nel database."  
✅ No duplicate entries created  
✅ Original templates unchanged

### Verification
```sql
SELECT name, COUNT(*) as count 
FROM print_templates 
GROUP BY name 
HAVING count > 1;
-- Expected: No results (no duplicates)
```

---

## Test Case 4: Template Functionality

### Prerequisites
- Templates restored
- At least one member in database

### Steps (Test "Tessera Socio")
1. Go to Soci (Members)
2. Click on a member
3. Click "Stampa" or "Genera PDF" button
4. Select template "Tessera Socio"
5. Click "Genera"

### Expected Results
✅ PDF preview or download  
✅ Card format (8.5cm x 5.4cm)  
✅ Member data populated correctly  
✅ No template parsing errors  
✅ Variables replaced (no {{variable_name}} visible)

### Repeat for other templates
- [ ] Scheda Socio (with member relations)
- [ ] Libro Soci (list of all members)
- [ ] Scheda Mezzo (vehicle with maintenance history)
- [ ] Verbale di Riunione (meeting with participants)

---

## Test Case 5: Permissions

### Prerequisites
- Non-admin user account

### Steps
1. Login as non-admin (without settings:edit permission)
2. Try to access `restore_print_templates.php` directly

### Expected Results
✅ Error: "Accesso negato: necessari permessi di amministratore"  
✅ User cannot restore templates  
✅ Restoration button not visible in Settings page

---

## Test Case 6: Error Handling

### Test 6.1: Missing Seed File
```bash
# Temporarily rename seed file
mv seed_print_templates.sql seed_print_templates.sql.bak

# Try restoration via web
```

**Expected**: Error message "File seed_print_templates.sql non trovato..."

### Test 6.2: Database Connection Error
```bash
# Stop MySQL temporarily or use wrong credentials
```

**Expected**: Database error message displayed, no partial inserts

### Test 6.3: Invalid SQL
```bash
# Corrupt the seed file (remove COMMIT; or add syntax error)
```

**Expected**: Transaction rolled back, error message shown

---

## Visual Testing (Screenshots Needed)

Please capture screenshots of:

1. **Before Restoration**
   - [ ] Settings page showing "Nessun modello di stampa trovato" with blue button

2. **Restoration Page**
   - [ ] `restore_print_templates.php` information page
   - [ ] List of 10 templates to be restored
   - [ ] Warning box

3. **Success**
   - [ ] Success message after restoration
   - [ ] Settings page with 10 templates listed

4. **Template List**
   - [ ] Full table showing all 10 templates with details

5. **Generated PDF**
   - [ ] Example of "Tessera Socio" PDF output
   - [ ] Example of "Libro Soci" PDF output

---

## Performance Testing

### Metrics to Check
```bash
# Time to restore (should be < 2 seconds)
time mysql -u username -p database_name < seed_print_templates.sql

# Database size increase
SELECT 
  table_name,
  ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)"
FROM information_schema.TABLES
WHERE table_name = 'print_templates';
# Expected: Small increase (~0.1 MB)
```

---

## Cleanup After Testing

If you want to reset for re-testing:

```sql
-- Delete all restored templates
DELETE FROM print_templates WHERE created_by IS NULL;

-- Or delete specific templates by name
DELETE FROM print_templates WHERE name IN (
  'Tessera Socio',
  'Scheda Socio',
  'Attestato di Partecipazione',
  'Libro Soci',
  'Tessere Multiple',
  'Scheda Mezzo',
  'Elenco Mezzi',
  'Verbale di Riunione',
  'Foglio Presenze Riunione',
  'Elenco Eventi'
);
```

---

## Regression Testing

After restoration, verify existing functionality still works:

- [ ] Member CRUD operations
- [ ] Vehicle management
- [ ] Meeting management
- [ ] Custom template creation (if any)
- [ ] Template editing
- [ ] Template deletion
- [ ] PDF generation with custom templates
- [ ] User permissions and roles

---

## Browser Compatibility

Test on:
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (if applicable)
- [ ] Mobile browsers (responsive design)

---

## Acceptance Criteria

The solution is accepted when:

✅ All 10 templates restore successfully  
✅ "Nessun modello di stampa trovato" message disappears  
✅ Templates are usable (generate PDFs correctly)  
✅ No duplicates created on multiple restorations  
✅ Permissions work correctly  
✅ Activity is logged  
✅ No SQL errors or warnings  
✅ Documentation is clear and accurate  
✅ Error messages are helpful  
✅ User interface is intuitive

---

## Issue Resolution Confirmation

This solution addresses the original issue:
> "Nessun modello di stampa trovato. avevi creato una ventina di template direttamente nel database, dove sono finiti? ripristinali."

✅ **Root Cause**: Print templates table was empty  
✅ **Solution**: Created restoration system with 3 methods  
✅ **Prevention**: Added prominent restoration button in UI  
✅ **Documentation**: Comprehensive guides in Italian  
✅ **User Experience**: One-click restoration via web interface

---

**Test Date**: _____________  
**Tester**: _____________  
**Environment**: _____________  
**Result**: ⬜ PASS ⬜ FAIL  
**Notes**: _____________
