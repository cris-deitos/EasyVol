# Quick Reference - Import Soci

## Comandi Rapidi

### Generare SQL da CSV
```bash
cd database/migrations
python3 generate_import_sql.py soci.csv > generated_inserts.sql
```

### Creare Script Completo
```bash
head -n 120 import_soci_completo.sql > import_finale.sql
cat generated_inserts.sql >> import_finale.sql
tail -n 50 import_soci_completo.sql >> import_finale.sql
```

### Backup Database
```bash
mysqldump -u root -p easyvol > backup_$(date +%Y%m%d).sql
```

### Eseguire Import
```bash
mysql -u root -p easyvol < import_finale.sql
```

### Verifiche Rapide
```sql
-- Totale soci
SELECT COUNT(*) FROM members;

-- Per status
SELECT member_status, COUNT(*) FROM members GROUP BY member_status;

-- Senza contatti
SELECT COUNT(*) FROM members m 
LEFT JOIN member_contacts mc ON m.id = mc.member_id 
WHERE mc.id IS NULL;
```

## Mappatura Rapida Campi

| CSV | Database | Note |
|-----|----------|------|
| matr | registration_number | Matricola univoca |
| tipo_socio | member_type | FONDATORE→fondatore, ORDINARIO→ordinario |
| stato | member_status | OPERATIVO→attivo, DIMESSO→dimesso, DECADUTO→decaduto |
| cognome | last_name | MAIUSCOLO |
| nome | first_name | MAIUSCOLO |
| codicefiscale | tax_code | - |
| problemialimentari | gender | MASCHIO→M, FEMMINA→F |
| cellulare | member_contacts | tipo='cellulare' |
| e_mail | member_contacts | tipo='email' |
| ind_resid | member_addresses | tipo='residenza' |

## Gestione Errori Comuni

### Colonna duplicata
```sql
-- Commentare nel script SQL:
-- ALTER TABLE members ADD COLUMN birth_province...
```

### Colonna 'value' non esiste
```sql
-- Commentare nel script SQL:
-- ALTER TABLE member_contacts CHANGE COLUMN value...
```

### Date non valide
Modificare formato in `generate_import_sql.py` riga ~40

### Encoding problemi
```bash
iconv -f ISO-8859-1 -t UTF-8 input.csv > output.csv
```

## Validazione Post-Import

```sql
-- Deve essere 175
SELECT COUNT(*) FROM members;

-- Verifica distribuzione
SELECT 
    member_status,
    member_type,
    COUNT(*) 
FROM members 
GROUP BY member_status, member_type;

-- Integrità referenziale
SELECT COUNT(*) FROM member_contacts mc
LEFT JOIN members m ON mc.member_id = m.id
WHERE m.id IS NULL;  -- Deve essere 0
```

## Rollback

```bash
# Se qualcosa va male:
mysql -u root -p easyvol < backup_$(date +%Y%m%d).sql
```

## Performance

```sql
-- Disabilita chiavi (prima import)
SET FOREIGN_KEY_CHECKS = 0;
SET AUTOCOMMIT = 0;

-- [esegui import]

-- Riabilita
COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
ANALYZE TABLE members, member_contacts, member_addresses;
```

## File di Test
```bash
# Testare con esempio fornito
python3 generate_import_sql.py soci_example.csv | mysql -u root -p easyvol_test
```
