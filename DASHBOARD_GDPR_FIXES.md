# Dashboard e GDPR - Risoluzione Problemi

## Problemi Risolti

### 1. Dashboard Avanzata - Contatori a Zero

**Problema:** I contatori nella dashboard avanzata erano tutti a zero.

**Cause Identificate:**
- Query SQL nel `DashboardController` usavano il valore enum `'completato'` invece di `'concluso'`
- La tabella `events` usa `status` enum con valori: `'in_corso'`, `'concluso'`, `'annullato'`
- La tabella `training_courses` usa `status` enum con valori: `'in_corso'`, `'concluso'`, `'sospeso'`

**Fix Applicati:**
- ✅ Corretto `getEventStatsByType()` in `src/Controllers/DashboardController.php` (linea 194)
- ✅ Corretto `getTrainingCourseStats()` in `src/Controllers/DashboardController.php` (linea 286)

**File Modificati:**
- `src/Controllers/DashboardController.php`

---

### 2. Permessi Dashboard Avanzata Non Rispettati

**Problema:** Anche rimuovendo i permessi per la dashboard avanzata dal ruolo admin, gli utenti admin potevano ancora accedervi.

**Causa Identificata:**
- Nel metodo `App::checkPermission()` c'era un bypass hardcoded per il ruolo 'admin'
- Alle linee 319-322: se `role_name === 'admin'` ritornava sempre `true`
- Questo ignorava completamente i permessi configurati nel database

**Fix Applicato:**
- ✅ Rimosso il bypass hardcoded nel metodo `checkPermission()` 
- ✅ Ora TUTTI gli utenti (inclusi admin) devono avere permessi espliciti nel database
- ✅ I permessi sono verificati da `role_permissions` e `user_permissions` tables

**File Modificati:**
- `src/App.php` (linee 305-336)

**Nota Importante:**
⚠️ Dopo questo fix, gli admin NON hanno più accesso automatico a tutto. Devi assicurarti che il ruolo admin abbia tutti i permessi necessari configurati nella tabella `role_permissions`.

---

### 3a. data_controller_appointments.php - Errore 500

**Problema:** La pagina generava un errore 500 (Internal Server Error).

**Causa Identificata:**
- Mancanza di gestione errori per problemi di connessione database
- Nessun feedback all'utente in caso di errore

**Fix Applicati:**
- ✅ Aggiunto try-catch block per chiamate al controller
- ✅ Aggiunta visualizzazione errore user-friendly in caso di problemi
- ✅ Log degli errori nel file di log PHP per debugging

**File Modificati:**
- `public/data_controller_appointments.php`

---

### 3b. sensitive_data_access_log.php - Errore 500

**Problema:** La pagina generava un errore 500 (Internal Server Error).

**Causa Identificata:**
- Mancanza di gestione errori per problemi di connessione database
- Nessun feedback all'utente in caso di errore

**Fix Applicati:**
- ✅ Aggiunto try-catch block per chiamate al controller
- ✅ Aggiunta visualizzazione errore user-friendly in caso di problemi
- ✅ Aggiunto handling errori per caricamento lista utenti
- ✅ Log degli errori nel file di log PHP per debugging

**File Modificati:**
- `public/sensitive_data_access_log.php`

---

### 3c. data_processing_registry.php - Non Implementato

**Problema:** La pagina mostrava solo "Elenco in costruzione - Implementazione completa richiesta".

**Fix Applicati:**
- ✅ Implementata interfaccia completa per la lista registro trattamenti
- ✅ Aggiunta filtri per base giuridica, stato, e ricerca testuale
- ✅ Aggiunta paginazione
- ✅ Implementato completamente `data_processing_registry_edit.php` con form completo
- ✅ Gestione creazione, modifica ed eliminazione registri
- ✅ Form include tutti i campi GDPR richiesti:
  - Nome e finalità trattamento
  - Base giuridica (consenso, contratto, obbligo legale, etc.)
  - Categorie dati e interessati
  - Destinatari
  - Trasferimenti paesi terzi
  - Periodo di conservazione
  - Misure di sicurezza
  - Titolare, responsabile e DPO
  - Note e stato attivo/non attivo

**File Modificati:**
- `public/data_processing_registry.php` (completamente riscritto)
- `public/data_processing_registry_edit.php` (completamente riscritto)

---

## Verifiche da Effettuare

### 1. Verifica Database

Prima di tutto, verifica che tutte le tabelle e viste necessarie esistano nel database:

```sql
-- Tabelle GDPR
SHOW TABLES LIKE 'data_controller_appointments';
SHOW TABLES LIKE 'sensitive_data_access_log';
SHOW TABLES LIKE 'data_processing_registry';
SHOW TABLES LIKE 'privacy_consents';
SHOW TABLES LIKE 'personal_data_export_requests';

-- Tabelle Dashboard
SHOW TABLES LIKE 'interventions';
SHOW TABLES LIKE 'intervention_members';
SHOW TABLES LIKE 'events';
SHOW TABLES LIKE 'members';
SHOW TABLES LIKE 'training_courses';
SHOW TABLES LIKE 'dashboard_kpi_config';
SHOW TABLES LIKE 'dashboard_chart_config';
SHOW TABLES LIKE 'dashboard_stats_cache';

-- Viste Dashboard
SHOW CREATE VIEW v_yoy_event_stats;
SHOW CREATE VIEW v_yoy_member_stats;
SHOW CREATE VIEW v_intervention_geographic_stats;

-- Permessi
SELECT * FROM permissions WHERE module = 'dashboard' AND action = 'view_advanced';
SELECT * FROM permissions WHERE module = 'gdpr_compliance';

-- Permessi assegnati al ruolo admin (assumendo role_id = 1)
SELECT p.module, p.action, p.description 
FROM role_permissions rp
JOIN permissions p ON rp.permission_id = p.id
WHERE rp.role_id = 1
ORDER BY p.module, p.action;
```

### 2. Applicare Migrazioni Mancanti

Se le tabelle o viste non esistono, esegui le migrazioni:

```bash
# Dalla directory del progetto
cd /path/to/EasyVol

# Se usi uno script di migrazione
php migrations/run_migrations.php

# Oppure applica manualmente il database_schema.sql
mysql -u username -p database_name < database_schema.sql
```

**Importante:** Le migrazioni rilevanti sono:
- `migrations/013_add_advanced_dashboard_features.sql` - Dashboard avanzata
- `migrations/014_add_gdpr_compliance.sql` - Funzionalità GDPR
- `migrations/015_fix_dashboard_views.sql` - Fix viste dashboard

### 3. Verifica Permessi Ruolo Admin

Dopo aver rimosso il bypass admin, assicurati che il ruolo admin abbia tutti i permessi:

```sql
-- Aggiungi permesso dashboard avanzata agli admin
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, p.id FROM permissions p 
WHERE p.module = 'dashboard' AND p.action IN ('view', 'view_advanced', 'customize');

-- Aggiungi permessi GDPR agli admin
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, p.id FROM permissions p 
WHERE p.module = 'gdpr_compliance';

-- Verifica
SELECT p.module, p.action, p.description 
FROM role_permissions rp
JOIN permissions p ON rp.permission_id = p.id
WHERE rp.role_id = 1
ORDER BY p.module, p.action;
```

### 4. Test Manuale Dashboard Avanzata

1. Accedi come admin
2. Vai a `Dashboard` → `Statistiche Avanzate` (o direttamente a `/public/dashboard_advanced.php`)
3. Verifica che:
   - I KPI mostrano valori corretti (non tutti zero)
   - I grafici si caricano correttamente
   - Non ci sono errori JavaScript nella console
   - La mappa geografica si carica se ci sono dati

**Se i contatori sono ancora zero:**
- Verifica che ci siano dati nelle tabelle `events`, `interventions`, `members`, etc.
- Controlla i log PHP per eventuali errori SQL
- Verifica le viste del database con query dirette

### 5. Test Permessi Dashboard Avanzata

1. Accedi come admin
2. Vai a `Gestione` → `Ruoli e Permessi`
3. Modifica il ruolo admin e **rimuovi** il permesso `dashboard > view_advanced`
4. Fai logout e login di nuovo
5. Verifica che il link "Statistiche Avanzate" nella sidebar NON sia più visibile
6. Prova ad accedere direttamente a `/public/dashboard_advanced.php`
7. Dovrebbe mostrare solo i KPI ma NON i grafici avanzati

### 6. Test Pagine GDPR

#### Test data_controller_appointments.php
1. Vai a `/public/data_controller_appointments.php`
2. Verifica che la pagina si carichi senza errore 500
3. Se ci sono errori di database, dovrebbe mostrare un messaggio user-friendly
4. Prova a creare una nuova nomina responsabile
5. Verifica filtri e paginazione

#### Test sensitive_data_access_log.php
1. Vai a `/public/sensitive_data_access_log.php`
2. Verifica che la pagina si carichi senza errore 500
3. Se ci sono errori di database, dovrebbe mostrare un messaggio user-friendly
4. Verifica che i filtri funzionino
5. Controlla che i log siano visualizzati correttamente

#### Test data_processing_registry.php
1. Vai a `/public/data_processing_registry.php`
2. Verifica che la pagina mostri l'elenco registri (anche se vuoto)
3. Clicca su "Nuovo Trattamento"
4. Compila il form con tutti i campi obbligatori:
   - Nome trattamento
   - Finalità
   - Base giuridica
   - Categorie dati
   - Categorie interessati
5. Prova il checkbox "Trasferimento verso Paesi Terzi" - dovrebbe mostrare/nascondere campo dettagli
6. Salva e verifica che il registro appaia nell'elenco
7. Prova a modificare il registro
8. Prova a filtrare per base giuridica e stato
9. Verifica la paginazione se ci sono molti record

---

## Debugging

### Errori Database

Se le pagine GDPR mostrano ancora errori 500, controlla i log PHP:

```bash
# Log Apache
tail -f /var/log/apache2/error.log

# Log PHP-FPM (se usi nginx)
tail -f /var/log/php-fpm/error.log

# Log applicazione (se configurato)
tail -f /path/to/easyvol/logs/error.log
```

Cerca messaggi come:
- "Table 'easyvol.data_controller_appointments' doesn't exist"
- "SQLSTATE[42S02]: Base table or view not found"
- "Unknown column 'status' in 'where clause'"

### Dashboard Contatori Zero

Se i contatori rimangono a zero anche dopo i fix:

```sql
-- Verifica dati nella tabella events
SELECT COUNT(*), status FROM events GROUP BY status;

-- Verifica dati interventions
SELECT COUNT(*) FROM interventions;

-- Verifica membri attivi
SELECT COUNT(*) FROM members WHERE member_status = 'attivo';

-- Verifica training courses
SELECT COUNT(*), status FROM training_courses GROUP BY status;

-- Testa le viste direttamente
SELECT * FROM v_yoy_event_stats LIMIT 10;
SELECT * FROM v_yoy_member_stats LIMIT 10;
SELECT * FROM v_intervention_geographic_stats LIMIT 10;
```

### Permessi Non Funzionanti

Se dopo aver rimosso i permessi l'admin vede ancora la dashboard avanzata:

1. Fai logout completo
2. Pulisci la sessione: `rm -rf /tmp/sess_*` (o session storage configurato)
3. Pulisci cache browser (Ctrl+Shift+R)
4. Login di nuovo
5. Verifica nella tabella `role_permissions`:
   ```sql
   SELECT * FROM role_permissions rp
   JOIN permissions p ON rp.permission_id = p.id
   WHERE rp.role_id = 1 AND p.module = 'dashboard' AND p.action = 'view_advanced';
   ```
6. Se la riga esiste, il permesso è ancora assegnato

---

## Note Finali

### Aggiornamento database_schema.sql

Il file `database_schema.sql` è già aggiornato con tutte le modifiche necessarie. Se devi ricreare il database da zero:

```bash
mysql -u username -p database_name < database_schema.sql
```

### Backup

Prima di applicare modifiche al database in produzione:

```bash
# Backup completo
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup solo struttura
mysqldump -u username -p --no-data database_name > schema_backup_$(date +%Y%m%d_%H%M%S).sql
```

### Performance Dashboard

La dashboard avanzata usa una cache di 30 minuti per le statistiche. Se i dati non si aggiornano immediatamente:

```sql
-- Pulisci cache dashboard
DELETE FROM dashboard_stats_cache WHERE expires_at < NOW();

-- Oppure pulisci tutta la cache
TRUNCATE TABLE dashboard_stats_cache;
```

---

## Riassunto Modifiche Codice

### File Modificati

1. **src/App.php**
   - Rimosso bypass admin in `checkPermission()`
   - Linee 305-336

2. **src/Controllers/DashboardController.php**
   - Corretto `status = 'completato'` → `status = 'concluso'` in due metodi
   - Linea 194: `getEventStatsByType()`
   - Linea 286: `getTrainingCourseStats()`

3. **public/data_controller_appointments.php**
   - Aggiunto try-catch per error handling
   - Aggiunta visualizzazione errori user-friendly

4. **public/sensitive_data_access_log.php**
   - Aggiunto try-catch per error handling
   - Aggiunta visualizzazione errori user-friendly

5. **public/data_processing_registry.php**
   - Completamente riscritto (da stub a implementazione completa)
   - Aggiunta lista con filtri e paginazione

6. **public/data_processing_registry_edit.php**
   - Completamente riscritto (da stub a form completo)
   - Form GDPR completo con validazione

### Nessuna Modifica al Database Schema

Tutte le tabelle, viste e permessi necessari erano già presenti nel `database_schema.sql`. Le modifiche riguardano solo la logica applicativa PHP.
