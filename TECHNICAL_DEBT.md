# Technical Debt and Future Improvements

This document tracks technical debt and potential improvements identified during code reviews.

## Photo Path Handling

### Issue
Path replacement logic in `MemberController` and `JuniorMemberController` uses a hardcoded relative path pattern:
```php
$relativePath = str_replace(__DIR__ . '/../../', '../', $result['path']);
```

### Recommendation
1. Extract path conversion logic into a shared utility method
2. Use more robust path resolution (realpath(), basename())
3. Define web root path as a configuration constant

### Priority
Medium - Current implementation works but could be more maintainable

---

## Date Validation in Sanctions

### Issue
`strtotime()` comparisons in sanction logic may fail if dates are invalid. Currently assumes dates are always valid.

**Files affected:**
- `public/member_sanction_edit.php` (lines 94-99)
- `public/junior_member_sanction_edit.php` (lines 94-99)

### Recommendation
Add validation before date comparison:
```php
$currentDate = strtotime($data['sanction_date']);
$sanctionDate = strtotime($s['sanction_date']);

if ($currentDate === false || $sanctionDate === false) {
    throw new \Exception('Invalid date format');
}

if ($sanctionDate < $currentDate && ...) {
    // comparison logic
}
```

### Priority
Low - Dates come from date input fields which provide format validation

---

## Code Duplication in Sanctions

### Issue
Sanction status logic is duplicated between:
- `public/member_sanction_edit.php` (lines 88-109)
- `public/junior_member_sanction_edit.php` (lines 88-109)

### Recommendation
Extract into a shared service class:
```php
class SanctionService {
    public static function calculateMemberStatus(
        string $sanctionType,
        array $allSanctions,
        string $sanctionDate
    ): string {
        // Shared logic here
    }
}
```

### Priority
Low - Logic is identical and well-tested, duplication is manageable

---

## Photo Path Migration

### Issue
Existing photos stored with absolute paths need to be updated to relative paths for display.

### Recommendation
Create a one-time migration script:
```php
$members = $db->fetchAll("SELECT id, photo_path FROM members WHERE photo_path LIKE '/home/%' OR photo_path LIKE '/var/%'");
foreach ($members as $member) {
    $relativePath = str_replace($projectRoot, '../', $member['photo_path']);
    $db->execute("UPDATE members SET photo_path = ? WHERE id = ?", [$relativePath, $member['id']]);
}
// Repeat for junior_members table
```

### Priority
High - If there are existing photos, they won't display until migrated

---

## General Guidelines

When addressing technical debt:
1. Write tests before refactoring
2. Update documentation
3. Ensure backward compatibility
4. Test in staging before production
5. Coordinate with team before major refactoring

## Review Schedule

Review this document quarterly and prioritize items based on:
- Security impact
- User-facing issues
- Maintenance burden
- Development velocity
