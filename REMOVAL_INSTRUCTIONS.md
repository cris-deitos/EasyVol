# Istruzioni per la Rimozione della Dashboard Avanzata

## File da Eliminare dal Server

I seguenti file devono essere eliminati definitivamente dal server di produzione:

### 1. File PHP
- `public/dashboard_advanced.php` - Dashboard avanzata con statistiche
- `public/data_export.php` - Endpoint universale per export dati
- `src/Controllers/DashboardController.php` - Controller per dashboard avanzata

### 2. File di Documentazione
- `ADVANCED_DASHBOARD_IMPLEMENTATION.md` - Documentazione implementazione dashboard avanzata

## Migration Database da Applicare

Eseguire la seguente migration sul database di produzione per rimuovere le tabelle e i permessi della dashboard avanzata:

```bash
mysql -u username -p database_name < migrations/014_remove_advanced_dashboard.sql
```

Oppure tramite phpMyAdmin o altro tool di gestione database, eseguire il contenuto del file:
- `migrations/014_remove_advanced_dashboard.sql`

### Cosa fa la Migration 014

La migration rimuove:

1. **Viste Database:**
   - `v_yoy_event_stats`
   - `v_yoy_member_stats`
   - `v_intervention_geographic_stats`

2. **Tabelle Database:**
   - `dashboard_stats_cache`
   - `dashboard_chart_config`
   - `dashboard_kpi_config`

3. **Permessi:**
   - `dashboard:view`
   - `dashboard:view_advanced`
   - `dashboard:customize`
   - Tutti i permessi `export` per i moduli:
     - `members:export`
     - `junior_members:export`
     - `meetings:export`
     - `vehicles:export`
     - `warehouse:export`
     - `structure_management:export`
     - `training:export`
     - `events:export`
     - `scheduler:export`

## Modifiche al Codice Applicate

Le seguenti modifiche sono già presenti nel codice e verranno applicate con il deployment:

### 1. Sidebar
- Rimosso link "Statistiche Avanzate" da `src/Views/includes/sidebar.php`

### 2. Pulsanti Export Rimossi
I pulsanti "Esporta" (Excel/CSV) sono stati rimossi da tutte le pagine di gestione:
- `public/members.php` (Soci)
- `public/junior_members.php` (Cadetti)
- `public/meetings.php` (Riunioni)
- `public/vehicles.php` (Mezzi)
- `public/warehouse.php` (Magazzino)
- `public/structure_management.php` (Strutture)
- `public/training.php` (Formazione)
- `public/events.php` (Eventi)
- `public/scheduler.php` (Scadenzario)

### 3. Controller
- Rimossi metodi di export universale da `src/Controllers/ReportController.php`
- Eliminato `src/Controllers/DashboardController.php` (non più necessario)

### 4. Database Schema
- Aggiornato `database_schema.sql` rimuovendo la sezione "ADVANCED DASHBOARD FEATURES"

## Funzionalità Mantenute

Le seguenti funzionalità continuano a funzionare normalmente:

1. **Dashboard Base** (`public/dashboard.php`)
   - Statistiche base (soci, cadetti, eventi, ecc.)
   - Log attività recenti
   - Scadenze in arrivo
   - Notifiche

2. **Export Esistenti**
   I seguenti export specifici continuano a funzionare:
   - `public/training_export_sspc.php` - Export corsi per SSPC
   - `public/province_export_excel.php` - Export province
   - `public/event_export_excel.php` - Export eventi specifico

3. **Report e Statistiche** (`public/reports.php`)
   - Report soci per stato e qualifica
   - Report eventi per tipo
   - Report mezzi
   - Report magazzino
   - Report documenti
   - Tutte le funzionalità di stampa

## Verifica Post-Deployment

Dopo aver applicato le modifiche, verificare:

1. **Login Funzionante**
   - Accedere al sistema con credenziali admin
   - Verificare che la sessione si crei correttamente

2. **Dashboard Base**
   - Accedere a `public/dashboard.php`
   - Verificare che le statistiche si carichino
   - Controllare che non ci siano errori PHP nel log

3. **Sidebar**
   - Verificare che NON compaia più il link "Statistiche Avanzate"
   - Verificare che tutti gli altri link funzionino

4. **Pagine di Gestione**
   - Aprire `public/members.php` e verificare che NON ci siano pulsanti "Esporta"
   - I pulsanti "Stampa" devono continuare a funzionare
   - Testare anche le altre pagine (mezzi, magazzino, ecc.)

5. **Database**
   - Verificare che le tabelle `dashboard_*` non esistano più
   - Verificare che le viste `v_yoy_*` e `v_intervention_geographic_stats` non esistano più
   - Verificare che i permessi export non esistano più

## Risoluzione Problemi

### Errore: "Table 'dashboard_kpi_config' doesn't exist"
**Soluzione:** La migration 014 è stata applicata correttamente. Ignorare questo errore se compare nei log vecchi.

### Errore: "Permission 'dashboard:view_advanced' not found"
**Soluzione:** Normale, il permesso è stato rimosso intenzionalmente.

### Errore 404 su dashboard_advanced.php
**Soluzione:** Normale, la pagina è stata rimossa intenzionalmente.

### Errore 404 su data_export.php
**Soluzione:** Normale, il file è stato rimosso intenzionalmente. Gli export universali non sono più disponibili.

## Note Importanti

1. **Backup Database:** Prima di applicare la migration 014, fare un backup completo del database.

2. **Test su Staging:** Se disponibile un ambiente di staging, testare tutte le modifiche prima di applicarle in produzione.

3. **Permessi Utenti:** Gli utenti che avevano permessi legati all'export o alla dashboard avanzata non avranno più accesso a quelle funzionalità.

4. **Alternative agli Export:** Se in futuro servisse esportare dati:
   - Usare le funzionalità di stampa esistenti
   - Usare phpMyAdmin per export manuali dal database
   - Sviluppare export specifici per le esigenze reali

## Riepilogo File da Eliminare

```bash
# File da eliminare con comando shell (da eseguire nella root del progetto)
rm -f public/dashboard_advanced.php
rm -f public/data_export.php
rm -f src/Controllers/DashboardController.php
rm -f ADVANCED_DASHBOARD_IMPLEMENTATION.md
```

## Data Rimozione
19 Gennaio 2026

## Motivo Rimozione
Funzionalità non necessaria per le esigenze dell'organizzazione. La dashboard avanzata e gli export universali aggiungevano complessità senza fornire valore reale all'utente finale.
