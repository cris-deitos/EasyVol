# Guida Importazione Dati da Vecchio Gestionale

## Panoramica

Questa directory contiene gli script SQL per importare i dati dal vecchio gestionale al nuovo sistema EasyVol.

## File Disponibili

### 1. `import_soci_adulti_completo.sql`
Script per importare **175 soci adulti** dal file `soci.csv`

### 2. `import_cadetti_completo.sql`
Script per importare **53 cadetti** dal file `cadetti.csv`

## Stato Attuale

⚠️ **IMPORTANTE**: Gli script attualmente contengono solo **5 record di esempio** per ciascuna categoria per dimostrare la struttura corretta dell'importazione.

### Cosa Contengono Ora:
- ✅ Struttura SQL completa e funzionante
- ✅ 5 esempi di soci adulti con diversi scenari (fondatore, ordinario, dimesso, decaduto, con dati completi)
- ✅ 5 esempi di cadetti con diversi scenari (attivo, decaduto, con indirizzi genitori diversi)
- ✅ Gestione corretta di caratteri speciali (es. apostrofi in D'Angelo)
- ✅ Consolidamento di campi extra nel campo `notes`
- ✅ Transazioni SQL con COMMIT/ROLLBACK
- ✅ Statistiche finali di verifica

### Cosa Serve per Completare:
- 📋 File `soci.csv` con i 175 record completi
- 📋 File `cadetti.csv` con i 53 record completi
- 🔧 Script o processo per convertire i rimanenti 170 soci e 48 cadetti in formato SQL

## Come Completare gli Script

### Opzione 1: Fornire i File CSV

1. Caricare i file `soci.csv` e `cadetti.csv` nella repository
2. Verrà creato uno script di conversione automatica
3. Gli script SQL verranno rigenerati con tutti i record

### Opzione 2: Conversione Manuale

1. Aprire i file SQL di importazione
2. Copiare uno dei blocchi di esempio
3. Modificare i valori secondo i dati del CSV
4. Ripetere per ogni record
5. Verificare escape di caratteri speciali

### Opzione 3: Script di Conversione Personalizzato

Creare uno script (Python, PHP, ecc.) che:
1. Legga il file CSV
2. Per ogni riga, generi il blocco SQL corrispondente
3. Gestisca escape e valori NULL
4. Consolidi i campi extra nel campo notes

## Struttura di un Record di Importazione

### Soci Adulti

```sql
-- TIPO: COGNOME NOME - Matr. XXX - STATO
INSERT INTO `members` (...) VALUES (...);
SET @member_id = LAST_INSERT_ID();

-- Contatti (se presenti)
INSERT INTO `member_contacts` (...) VALUES (...);

-- Indirizzi (se presenti)
INSERT INTO `member_addresses` (...) VALUES (...);
```

### Cadetti

```sql
-- CADETTO: COGNOME NOME - Matr. CXXX - STATO
INSERT INTO `junior_members` (...) VALUES (...);
SET @junior_id = LAST_INSERT_ID();

-- Tutore principale
INSERT INTO `junior_member_guardians` (...) VALUES (...);

-- Contatti (se presenti)
INSERT INTO `junior_member_contacts` (...) VALUES (...);

-- Indirizzi (se presenti)
INSERT INTO `junior_member_addresses` (...) VALUES (...);
```

## Mappatura Campi

### Soci Adulti (soci.csv → members)

| Campo CSV | Campo DB | Note |
|-----------|----------|------|
| matr | registration_number | Numero matricola |
| tipo_socio | member_type | FONDATORE→fondatore, ORDINARIO→ordinario |
| stato | member_status + volunteer_status | OPERATIVO→attivo+operativo, ecc. |
| cognome | last_name | |
| nome | first_name | |
| data_nascita | birth_date | Formato: YYYY-MM-DD |
| luogo_nascita | birth_place | |
| prov_nascita | notes | Consolidato in notes |
| codicefiscale | tax_code | |
| sesso | notes | M o F, consolidato in notes |
| grup_sang | notes | Gruppo sanguigno in notes |
| anno_iscrizione | registration_date | |
| mansione | notes | Qualifica in notes |
| created | created_at | Timestamp originale |
| last_upd | updated_at | Timestamp originale |

**Campi Consolidati in Notes:**
- Provincia nascita
- Sesso
- Gruppo sanguigno
- Qualifica
- Disponibilità territoriale
- Lingue
- Allergie
- Patente
- Titolo di studio
- Lavoro (tipo + azienda)
- Data dimissione (per dimessi/decaduti)
- Motivo dimissione (per dimessi/decaduti)

### Cadetti (cadetti.csv → junior_members)

| Campo CSV | Campo DB | Note |
|-----------|----------|------|
| nuovocampo | registration_number | Es: C001 |
| nuovocampo64 | member_status | SOCIO ORDINARIO→attivo, *DECADUTO*→decaduto |
| nuovocampo1 | last_name | |
| nuovocampo2 | first_name | |
| nuovocampo6 | birth_date | |
| nuovocampo4 | birth_place | |
| nuovocampo7 | tax_code | |
| nuovocampo61 | registration_date | |
| nuovocampo33 | guardian_last_name | Padre |
| nuovocampo34 | guardian_first_name | Padre |
| nuovocampo38 | guardian_tax_code | Padre |
| nuovocampo44 | guardian_phone | Padre |
| nuovocampo45 | guardian_email | Padre |

**Dati Madre (tutti in notes):**
- nuovocampo46, 47: Nome madre
- nuovocampo48-51: Dati nascita e CF madre
- nuovocampo52-55: Indirizzo madre
- nuovocampo56, 59, 60: Contatti madre

## Limitazioni Schema Attuale

Lo schema del database attuale ha alcune limitazioni rispetto ai requisiti:

### Campi Mancanti in `members`:
- `birth_province` - viene consolidato in `notes`
- `gender` - viene consolidato in `notes`
- `blood_type` - viene consolidato in `notes`
- `qualification` - viene consolidato in `notes`
- `dismissal_date` - viene consolidato in `notes`
- `dismissal_reason` - viene consolidato in `notes`
- `nationality` - non presente, si assume "Italiana"

### Campi Mancanti in `member_contacts` e `junior_member_contacts`:
- `is_primary` - non presente nel schema
- `notes` - non presente nel schema

### Campi Mancanti in `member_addresses` e `junior_member_addresses`:
- `is_primary` - non presente nel schema
- `notes` - non presente nel schema

### Soluzione Adottata:
Tutti i campi extra vengono consolidati nel campo `notes` in formato strutturato e leggibile.

## Come Eseguire l'Importazione

### 1. Backup del Database
```bash
mysqldump -u username -p database_name > backup_before_import.sql
```

### 2. Verifica Prerequisiti
- Database EasyVol esistente
- Tabelle create con `database_schema.sql`
- Nessun dato conflittuale nelle tabelle

### 3. Esecuzione Script Soci Adulti
```bash
mysql -u username -p database_name < database/migrations/import_soci_adulti_completo.sql
```

### 4. Verifica Importazione Soci
```sql
SELECT * FROM members ORDER BY id DESC LIMIT 10;
SELECT COUNT(*) FROM members;
```

### 5. Esecuzione Script Cadetti
```bash
mysql -u username -p database_name < database/migrations/import_cadetti_completo.sql
```

### 6. Verifica Importazione Cadetti
```sql
SELECT * FROM junior_members ORDER BY id DESC LIMIT 10;
SELECT COUNT(*) FROM junior_members;
```

### 7. Verifica Finale
```sql
-- Totale membri
SELECT 
    (SELECT COUNT(*) FROM members) + 
    (SELECT COUNT(*) FROM junior_members) AS TotaleMembers;

-- Dovrebbe essere: 175 + 53 = 228
```

## Gestione Errori

### Foreign Key Errors
Se si verificano errori di foreign key:
```sql
SET FOREIGN_KEY_CHECKS = 0;
-- esegui import
SET FOREIGN_KEY_CHECKS = 1;
```
(Gli script già includono questa gestione)

### Caratteri Speciali
Assicurarsi che:
- Il database usi charset `utf8mb4`
- Gli apostrofi siano escaped: `D'Angelo` → `D\'Angelo`
- Le connessioni usino `SET NAMES utf8mb4`

### Rollback in Caso di Errore
Gli script usano transazioni:
```sql
START TRANSACTION;
-- import
COMMIT; -- o ROLLBACK; in caso di errore
```

## Prossimi Passi

1. ✅ Script SQL con struttura completa creati
2. ⏳ Attendere file CSV con dati completi
3. ⏳ Generare/completare gli script con tutti i 228 record
4. ⏳ Testare importazione su database di test
5. ⏳ Eseguire importazione finale

## Supporto

Per problemi o domande:
1. Verificare che i file CSV siano nel formato corretto
2. Controllare i log MySQL per errori specifici
3. Verificare che le tabelle siano vuote prima dell'import
4. Controllare i permessi del database

---

**Ultimo aggiornamento**: 2025-12-07
**Versione**: 1.0 (Struttura con esempi)
