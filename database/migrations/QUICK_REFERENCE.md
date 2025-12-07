# Quick Reference: Import Cadetti Script

## Files Created

1. **`import_cadetti_completo.sql`** - Main import script
2. **`README_IMPORT_CADETTI.md`** - Complete documentation
3. **`QUICK_REFERENCE.md`** - This file

## Current Status

✅ **COMPLETED:**
- Database schema analysis
- Complete field mapping CSV → Database
- SQL script structure with transaction support
- Example import for ORLANDO GAIA (Registration #2)
- Template for remaining 52 cadetti
- Statistics and verification queries
- Comprehensive documentation

⏳ **TO BE COMPLETED:**
- Add remaining 52 cadetti from CSV file `gestionaleweb_worktable16.csv`

## Quick Start

### 1. Get the CSV File
Obtain `gestionaleweb_worktable16.csv` with all 53 junior members data.

### 2. Add Remaining Cadetti
Open `import_cadetti_completo.sql` and follow the template starting at line ~160.

### 3. Test
```bash
# Test on development database
mysql -u username -p database_test < import_cadetti_completo.sql
```

### 4. Deploy to Production
```bash
# Backup first!
mysqldump -u username -p easyvol_production > backup_$(date +%Y%m%d).sql

# Import
mysql -u username -p easyvol_production < import_cadetti_completo.sql
```

## Database Schema

### Tables Involved

1. **`junior_members`** - Main table
   - Fields: registration_number, member_status, last_name, first_name, birth_date, birth_place, tax_code, registration_date, approval_date, photo, notes, created_at, updated_at

2. **`junior_member_guardians`** - Parents/tutors
   - Fields: junior_member_id (FK), guardian_type (padre/madre/tutore), last_name, first_name, tax_code, phone, email

3. **`junior_member_addresses`** - Addresses
   - Fields: junior_member_id (FK), address_type (residenza/domicilio), street, number, city, province, cap

4. **`junior_member_contacts`** - Contacts
   - Fields: junior_member_id (FK), contact_type (telefono_fisso/cellulare/email), value

5. **`junior_member_health`** - Health info
   - Fields: junior_member_id (FK), health_type (vegano/vegetariano/allergie/intolleranze/patologie), description

## Template Structure for Each Cadetto

```sql
-- CADETTO [N]: [LAST_NAME] [FIRST_NAME]
INSERT INTO junior_members (...) VALUES (...);
SET @junior_[REG_NUM]_id = LAST_INSERT_ID();

-- Guardian (padre)
INSERT INTO junior_member_guardians (...) VALUES (...);

-- Guardian (madre)
INSERT INTO junior_member_guardians (...) VALUES (...);

-- Contacts (if available)
INSERT INTO junior_member_contacts (...) VALUES (...);

-- Address
INSERT INTO junior_member_addresses (...) VALUES (...);

-- Health/Allergie (if available)
INSERT INTO junior_member_health (...) VALUES (...);
```

## Field Mapping Cheat Sheet

### Junior Member Core Data
| CSV Field | Database | Notes |
|-----------|----------|-------|
| nuovocampo | registration_number | Unique ID |
| nuovocampo1 | last_name | |
| nuovocampo2 | first_name | |
| nuovocampo6 | birth_date | Format: YYYY-MM-DD |
| nuovocampo4 | birth_place | |
| nuovocampo7 | tax_code | Codice Fiscale |
| nuovocampo61 | registration_date | Format: YYYY-MM-DD |
| nuovocampo64 | member_status | SOCIO ORDINARIO→attivo, *DECADUTO*→decaduto |

### Info in Notes Field
- nuovocampo3 → Gender (M/F)
- nuovocampo5 → Birth Province
- nuovocampo25 → Anno corso
- nuovocampo17 → Lingue
- nuovocampo18 → Allergie cadetto
- nuovocampo58 → Allergie genitore
- Mother's complete data

### Guardian 1 (Padre)
| CSV Field | Database |
|-----------|----------|
| nuovocampo33 | last_name |
| nuovocampo34 | first_name |
| nuovocampo38 | tax_code |
| nuovocampo44 | phone (preferire) |
| nuovocampo43 | phone (alternativo) |
| nuovocampo45 | email |

### Guardian 2 (Madre)
| CSV Field | Database |
|-----------|----------|
| nuovocampo46 | last_name |
| nuovocampo47 | first_name |
| nuovocampo51 | tax_code |
| nuovocampo60 | phone (preferire) |
| nuovocampo59 | phone (alternativo) |
| nuovocampo56 | email |

### Address
| CSV Field | Database |
|-----------|----------|
| nuovocampo9 | street (extract via/number) |
| nuovocampo10 | cap |
| nuovocampo11 | city |
| nuovocampo12 | province |

### Contacts
| CSV Field | Type | Database |
|-----------|------|----------|
| nuovocampo14 | cellulare | junior_member_contacts |
| nuovocampo15 | email | junior_member_contacts |

### Health
| CSV Field | Type | Database |
|-----------|------|----------|
| nuovocampo18 | allergie | junior_member_health |
| nuovocampo58 | allergie (genitore) | junior_member_health |

## Common Issues & Solutions

### Issue: Special Characters
**Solution:** Escape apostrophes
```sql
'D'ANGELO' → 'D\'ANGELO'
```

### Issue: NULL Values
**Solution:** Use NULL, not empty strings
```sql
-- Good
'ROSSI', 'MARIO', NULL, '3331234567'

-- Bad
'ROSSI', 'MARIO', '', '3331234567'
```

### Issue: Date Format
**Solution:** Always use YYYY-MM-DD
```sql
-- Good
'2003-12-02'

-- Bad
'02/12/2003'
'02-12-2003'
```

### Issue: Member Status
**Solution:** Map correctly
- "SOCIO ORDINARIO" → `'attivo'`
- "*DECADUTO*" → `'decaduto'`

## Verification Queries

After import, check:

```sql
-- Total count
SELECT COUNT(*) FROM junior_members WHERE registration_number IS NOT NULL;
-- Expected: 53

-- Status distribution
SELECT member_status, COUNT(*) FROM junior_members GROUP BY member_status;

-- Check guardians
SELECT COUNT(*) FROM junior_member_guardians;
-- Expected: ~106 (2 per cadetto if both parents present)

-- Check addresses
SELECT COUNT(*) FROM junior_member_addresses;

-- Check contacts
SELECT COUNT(*) FROM junior_member_contacts;

-- Check health records
SELECT COUNT(*) FROM junior_member_health;

-- Find issues
SELECT * FROM junior_members jm
LEFT JOIN junior_member_guardians jmg ON jm.id = jmg.junior_member_id
WHERE jmg.id IS NULL;
```

## Script Safety Features

✅ Transaction support (can ROLLBACK on error)
✅ Foreign key checks disabled during import
✅ NULL value handling
✅ Unique constraint on registration_number (prevents duplicates)
✅ Verification queries at end

## Support

For issues:
1. Check MySQL error log
2. Verify CSV data format
3. Test on development database first
4. Review README_IMPORT_CADETTI.md for detailed docs

## Example: Complete Import Entry

```sql
-- CADETTO 1: ORLANDO GAIA (Registration Number: 2)
INSERT INTO junior_members (
    registration_number, member_status, last_name, first_name,
    birth_date, birth_place, tax_code, registration_date,
    notes, created_at, updated_at
) VALUES (
    '2', 'decaduto', 'ORLANDO', 'GAIA',
    '2003-12-02', 'BRESCIA', 'RLNGAI03T42B157A', '2019-01-12',
    'Gender: F - Birth Province: BS - Anno corso: 2022 - Nazionalità: Italiana - Madre: ROSSELLI PATRIZIA (Tel: 3491307297, Email: patroselli69@gmail.com)',
    '2019-01-13 10:17:37', '2025-05-01 10:14:34'
);
SET @junior_2_id = LAST_INSERT_ID();

INSERT INTO junior_member_guardians (
    junior_member_id, guardian_type, last_name, first_name,
    tax_code, phone, email
) VALUES 
(@junior_2_id, 'padre', 'ORLANDO', 'GIUSEPPE', NULL, '3478823850', NULL),
(@junior_2_id, 'madre', 'ROSSELLI', 'PATRIZIA', NULL, '3491307297', 'patroselli69@gmail.com');
```

---

**Last Updated:** 2025-12-07
**Status:** Ready for data population from CSV
