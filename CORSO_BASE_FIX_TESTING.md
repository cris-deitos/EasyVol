# Corso Base Synchronization Fix - Testing Guide

## Summary
Fixed the corso base synchronization logic to ensure that when a member completes the basic civil protection course (corso base), both the flags in the `members` table AND the course entry in the `member_courses` table are properly updated.

## What Was Fixed

### Primary Issue
The `TrainingController::addCourseToMemberRecord()` method was using raw SQL to insert/update courses, which bypassed the `Member::updateCorsoBaseFields()` logic. This meant that when a training participant passed the corso base exam, the course was added to their records BUT the `corso_base_completato` and `corso_base_anno` flags were NOT updated.

### Solution
- Modified `TrainingController::addCourseToMemberRecord()` to use the Member model methods
- This ensures that `updateCorsoBaseFields()` is always called when adding/updating courses
- Improved pattern matching to handle variations in corso base course names

## Testing Scenarios

### Scenario 1: Training Participant Completes Corso Base

**Steps:**
1. Navigate to "Formazione" → "Corsi"
2. Create a new training course with one of these names:
   - "A1 CORSO BASE PER VOLONTARI OPERATIVI DI PROTEZIONE CIVILE" (exact match)
   - "CORSO BASE PROTEZIONE CIVILE" (will match)
   - "A1 - CORSO BASE PC" (will match)
3. Set start date and end date (e.g., 2024-01-15 to 2024-01-31)
4. Add an active member as a participant
5. Go to the course view and mark the participant as:
   - Status: "presente"
   - Exam Passed: checked (1)
   - OR Certificate Issued: checked (1)
6. Save the participant

**Expected Results:**
- ✅ A new entry appears in the member's "Corsi" tab with:
  - Course name: matching the training course name
  - Course type: matching the training course type
  - Completion date: the training course end_date (or current date if no end_date)
  - training_course_id: linked to the training course
- ✅ In the member's overview, the corso base status shows:
  - "Corso Base: Completato"
  - "Anno: [year from completion date]"
- ✅ Database verification:
  ```sql
  SELECT corso_base_completato, corso_base_anno FROM members WHERE id = [member_id];
  -- Should show: corso_base_completato = 1, corso_base_anno = [year]
  
  SELECT * FROM member_courses WHERE member_id = [member_id] AND training_course_id = [course_id];
  -- Should return the corso base course entry
  ```

### Scenario 2: New Member Application with Corso Base

**Steps:**
1. Go to public registration form (as non-logged-in user)
2. Fill out application form
3. Check "Ho già completato il Corso Base di Protezione Civile"
4. Enter year (e.g., 2023)
5. Submit application
6. As admin, go to "Domande di Iscrizione"
7. Approve the application

**Expected Results:**
- ✅ New member created with corso base status:
  - "Corso Base: Completato"
  - "Anno: 2023" (the entered year)
- ✅ A corso base entry appears in member's "Corsi" tab with:
  - Course name: "A1 CORSO BASE PER VOLONTARI OPERATIVI DI PROTEZIONE CIVILE"
  - Course type: "A1"
  - Completion date: "2023-01-01" (January 1st of the entered year)
- ✅ Database verification:
  ```sql
  SELECT corso_base_completato, corso_base_anno FROM members WHERE id = [new_member_id];
  -- Should show: corso_base_completato = 1, corso_base_anno = 2023
  
  SELECT * FROM member_courses WHERE member_id = [new_member_id] AND course_type = 'A1';
  -- Should return the corso base course entry
  ```

### Scenario 3: Manual Member Edit with Corso Base

**Steps:**
1. Navigate to "Soci" → select a member
2. Click "Modifica"
3. Check "Corso Base Completato"
4. Enter year (e.g., 2022)
5. Save

**Expected Results:**
- ✅ Member's corso base status updated:
  - "Corso Base: Completato"
  - "Anno: 2022"
- ✅ A corso base entry appears/updates in member's "Corsi" tab
- ✅ If entry already exists, it updates the year
- ✅ If entry doesn't exist, it creates a new one

## Corso Base Name Matching

The system now recognizes corso base courses with these patterns:
- Exact match: "A1 CORSO BASE PER VOLONTARI OPERATIVI DI PROTEZIONE CIVILE"
- Contains "A1" AND "CORSO BASE" (case-insensitive)
- Contains "CORSO BASE" AND "PROTEZIONE CIVILE" (case-insensitive)

Examples that will be recognized:
- ✅ "A1 CORSO BASE PER VOLONTARI OPERATIVI DI PROTEZIONE CIVILE"
- ✅ "CORSO BASE PROTEZIONE CIVILE"
- ✅ "A1 - CORSO BASE PC"
- ✅ "Corso Base di Protezione Civile A1"
- ✅ "corso base per volontari protezione civile"

Examples that will NOT be recognized:
- ❌ "CORSO AVANZATO" (missing "BASE")
- ❌ "FORMAZIONE BASE" (missing "CORSO")
- ❌ "A1" only (missing "CORSO BASE")

## Database Schema

### Members Table
```sql
corso_base_completato tinyint(1) DEFAULT 0  -- Flag: 0 or 1
corso_base_anno int(11) DEFAULT NULL        -- Year: e.g., 2023
```

### Member Courses Table
```sql
member_id int(11)                          -- Link to member
course_name varchar(255)                   -- Full course name
course_type varchar(100)                   -- Course type (e.g., "A1")
completion_date date                       -- Completion date
training_course_id int(11) DEFAULT NULL    -- Link to training course if from organized training
```

## Troubleshooting

### Issue: Course not appearing in "Corsi" tab
**Check:**
1. Verify the training course name contains "CORSO BASE" or "A1"
2. Check if participant has exam_passed = 1 OR certificate_issued = 1
3. Look at PHP error logs for any exceptions

### Issue: Flags not updated in member record
**Check:**
1. Verify the completion date is valid (YYYY-MM-DD format)
2. Check if the year is within valid range (1950 to current year + 1)
3. Look at database logs for UPDATE errors

### Issue: Duplicate corso base entries
**Possible cause:** The sync logic checks for existing entries by training_course_id and course_type
**Solution:** The code should update existing entries, but if duplicates appear, check the database constraints

## Code Files Changed

1. `src/Controllers/TrainingController.php`
   - Method: `addCourseToMemberRecord()`
   - Change: Now uses Member model instead of raw SQL

2. `src/Models/Member.php`
   - Method: `updateCorsoBaseFields()`
   - Change: Improved pattern matching for corso base courses

## Migration Status

- No new migrations required
- Existing migration `005_add_corso_base_fields.sql` is sufficient
- Database schema is up to date

## Notes for Developers

1. Always use the Member model methods (`addCourse`, `updateCourse`) when working with member courses
2. The `updateCorsoBaseFields()` is automatically called by these methods
3. Don't use raw SQL to insert/update `member_courses` table
4. The corso base logic is centralized in the Member model for consistency

## Security Summary

- No security vulnerabilities introduced
- All database operations use parameterized queries
- No sensitive data exposure
- Input validation is maintained
