# Radio Assignment Search - Testing Guide

## Overview
This document provides comprehensive testing instructions for the new radio assignment search functionality that allows searching for both members and cadets.

## Pre-requisites
1. EasyVol installation with access to Operations Center module
2. At least one active member with "operativo" status
3. At least one active cadet (optional but recommended for full testing)
4. At least one radio in "disponibile" status

## Test Scenarios

### Scenario 1: Basic Member Search
**Objective**: Verify that members can be searched and selected

**Steps**:
1. Login to EasyVol with a user who has operations_center edit permissions
2. Navigate to Operations Center → Radio Directory
3. Click on any available radio to view its details
4. Click "Assegna Radio" button
5. Ensure "Volontario dell'Associazione" is selected
6. In the search field, type part of a member's name (e.g., "ross" for "Rossi")
7. Verify that:
   - Autocomplete results appear within 300ms
   - Results show: "COGNOME NOME (Mat. XXXXX)"
   - Results are sorted by relevance (exact badge match > registration match > name match)
8. Click on a member from the results
9. Verify that:
   - The search field is populated with the member's name
   - The hidden member_id field has a value
   - The hidden member_type field is set to "member"
10. Click "Assegna" button
11. Verify that:
    - A success message appears
    - The radio status changes to "assegnata"
    - The assignment shows the correct member information

### Scenario 2: Badge Number Search
**Objective**: Verify that members can be found by badge number

**Steps**:
1. Follow steps 1-5 from Scenario 1
2. In the search field, type a badge number (matricola)
3. Verify that the member with that badge number appears at the top of results
4. Complete the assignment and verify success

### Scenario 3: Cadet Search (if available)
**Objective**: Verify that cadets can be searched and selected

**Steps**:
1. Follow steps 1-5 from Scenario 1
2. In the search field, type part of a cadet's name
3. Verify that:
   - Cadets appear in results marked with "[Cadetto]"
   - Results show: "COGNOME NOME (Mat. XXXXX) [Cadetto]"
4. Click on a cadet from the results
5. Verify that:
   - The search field is populated with the cadet's name
   - The hidden member_id field has a value
   - The hidden member_type field is set to "cadet"
6. Click "Assegna" button
7. Verify assignment success

### Scenario 4: Search with No Results
**Objective**: Verify graceful handling when no matches are found

**Steps**:
1. Follow steps 1-5 from Scenario 1
2. In the search field, type a non-existent name (e.g., "ZZZZZZ")
3. Verify that:
   - Message "Nessun volontario o cadetto trovato" appears
   - No error occurs
4. Clear the search field and verify the message disappears

### Scenario 5: Form Validation
**Objective**: Verify that the form prevents submission without proper selection

**Steps**:
1. Follow steps 1-5 from Scenario 1
2. Type in the search field but don't select anyone from the dropdown
3. Click "Assegna" button
4. Verify that:
   - An alert appears: "Per favore, seleziona un volontario o cadetto dalla ricerca"
   - The form is not submitted
   - The modal remains open

### Scenario 6: External Personnel Assignment (Regression)
**Objective**: Verify that external personnel assignment still works

**Steps**:
1. Follow steps 1-4 from Scenario 1
2. Select "Personale Esterno" radio option
3. Verify that:
   - Member search field is hidden
   - External personnel fields appear (Cognome, Nome, Ente, Telefono)
4. Fill in all external personnel fields
5. Click "Assegna" button
6. Verify assignment success

### Scenario 7: Mixed Search Results
**Objective**: Verify that both members and cadets appear together in search results

**Steps**:
1. Follow steps 1-5 from Scenario 1
2. Type a common first name (e.g., "Mario")
3. Verify that:
   - Both members and cadets with that name appear
   - Cadets are clearly marked with "[Cadetto]"
   - Results are properly sorted

### Scenario 8: Assignment History Display
**Objective**: Verify that assigned radios display correctly in history

**Steps**:
1. After completing Scenario 1 or 3, refresh the radio view page
2. Verify that:
   - Current assignment section shows the correct assignee
   - Badge number (matricola) is displayed
   - Assignment date is shown
   - "Registra Restituzione" button appears
3. Scroll down to "Storico Assegnazioni"
4. Verify that the new assignment appears in the history

### Scenario 9: Return Radio (Regression)
**Objective**: Verify that radio return functionality still works

**Steps**:
1. On a radio that is currently assigned, click "Registra Restituzione"
2. Enter optional notes in the prompt
3. Click OK
4. Verify that:
   - Radio status returns to "disponibile"
   - Return date is recorded in history
   - Radio can be assigned again

### Scenario 10: Search Performance
**Objective**: Verify that search is responsive

**Steps**:
1. Follow steps 1-5 from Scenario 1
2. Type rapidly in the search field (e.g., "mar", then "mari", then "mario")
3. Verify that:
   - Debouncing works (searches trigger after 300ms of no typing)
   - Previous search results are replaced by new ones
   - No duplicate or stale results appear

## Browser Compatibility Testing
Test the above scenarios in:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (if available)

## Mobile Testing
Test the search functionality on:
- Mobile Chrome
- Mobile Safari
- Mobile Firefox

Verify that:
- Search field is properly sized
- Autocomplete dropdown is readable
- Touch interactions work correctly

## Database Migration Testing (Optional)

### Before Migration
1. Test that the system works without the migration applied
2. Verify that:
   - Only members (not cadets) appear in search results
   - Assignment to members works
   - Error message appears if trying to assign to cadets

### After Migration
1. Apply the migration: `mysql -u username -p database < migrations/add_radio_assignments_junior_support.sql`
2. Test that:
   - Both members and cadets appear in search results
   - Assignment to both works correctly
   - Existing assignments are still displayed correctly
3. Verify database structure:
   ```sql
   DESCRIBE radio_assignments;
   ```
   Should show: `junior_member_id` and `assignee_type` columns

## Troubleshooting

### Issue: Autocomplete doesn't appear
- Check browser console for JavaScript errors
- Verify that radio_member_search_ajax.php is accessible
- Check network tab to see if AJAX request is being made

### Issue: "Database non aggiornato" error
- Migration needs to be applied
- Run the migration SQL file

### Issue: Search returns no results despite having active members
- Verify member status is "attivo"
- Verify volunteer_status is "operativo" for members
- Check database query in radio_member_search_ajax.php logs

### Issue: Assignment fails with error
- Check PHP error logs
- Verify CSRF token is being generated correctly
- Check that user has operations_center edit permission

## Success Criteria
All test scenarios pass without errors, and:
- Search is responsive (< 500ms response time)
- UI is intuitive and clear
- No console errors
- No PHP errors in logs
- Database transactions complete successfully
- Both members and cadets can be assigned
- Backward compatibility maintained

## Reporting Issues
When reporting issues, please include:
- Browser and version
- Steps to reproduce
- Expected vs actual behavior
- Screenshots or screen recordings
- Browser console errors
- PHP error log excerpts
