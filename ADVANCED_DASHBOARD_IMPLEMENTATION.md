# Advanced Dashboard Statistics - Implementation Summary

## Panoramica
Implementazione completa di funzionalità avanzate di dashboard con statistiche, KPI personalizzabili, grafici interattivi e esportazione dati in Excel/CSV per tutte le tabelle del sistema.

## Data di implementazione
19 Gennaio 2026

## Componenti Implementati

### 1. Database (Migration 013)
**File**: `migrations/013_add_advanced_dashboard_features.sql`

#### Nuove Tabelle
- **dashboard_kpi_config**: Configurazione KPI personalizzabili per utente
  - Permette agli utenti di personalizzare visualizzazione, ordine, etichette e colori dei KPI
  
- **dashboard_chart_config**: Configurazione grafici personalizzabili per utente
  - Gestisce tipo grafico, posizione, titoli personalizzati e range temporale
  
- **dashboard_stats_cache**: Cache per statistiche dashboard
  - Migliora performance con caching delle query pesanti
  - Scadenza automatica dei dati cached

#### Nuove Viste
- **v_yoy_event_stats**: Statistiche eventi anno su anno
  - Raggruppa eventi per anno, mese e tipo
  - Include conteggi totali, completati e in corso
  
- **v_yoy_member_stats**: Statistiche soci anno su anno
  - Raggruppa soci per anno, mese e stato
  
- **v_intervention_geographic_stats**: Statistiche geografiche interventi
  - Include coordinate GPS, comune, provincia
  - Aggrega numero volontari e ore lavorate per intervento

#### Nuovi Permessi
Aggiunti permessi per:
- **Dashboard**: view, view_advanced, customize
- **Export**: Tutti i moduli principali (members, junior_members, meetings, vehicles, warehouse, structures, training, events, scheduler)

### 2. Backend Controllers

#### DashboardController
**File**: `src/Controllers/DashboardController.php`

**Metodi Principali**:
- `getKPIData()`: Recupera tutti i KPI principali
  - Soci attivi/cadetti
  - Eventi/interventi
  - Mezzi operativi
  - Corsi attivi
  - Articoli sotto scorta
  - Ore volontariato YTD

- `getYoYEventStats()`: Confronto anno su anno eventi
- `getYoYMemberStats()`: Confronto anno su anno soci
- `getGeographicInterventionData()`: Dati geografici per mappe
- `getEventStatsByType()`: Statistiche eventi per tipo
- `getMonthlyEventTrend()`: Trend mensile eventi
- `getVolunteerActivityStats()`: Top volontari per ore
- `getWarehouseStockStats()`: Stato giacenze magazzino
- `getVehicleUsageStats()`: Utilizzo mezzi
- `getTrainingCourseStats()`: Statistiche corsi
- `getMeetingAttendanceStats()`: Presenze riunioni
- `getDashboardData()`: Dati completi dashboard con caching

#### ReportController (Esteso)
**File**: `src/Controllers/ReportController.php`

**Nuovi Metodi di Export**:
- `exportMembers()`: Export soci in Excel/CSV
- `exportJuniorMembers()`: Export cadetti in Excel/CSV
- `exportMeetings()`: Export riunioni in Excel/CSV
- `exportVehicles()`: Export mezzi in Excel/CSV
- `exportWarehouse()`: Export magazzino in Excel/CSV
- `exportStructures()`: Export strutture in Excel/CSV
- `exportTraining()`: Export corsi in Excel/CSV
- `exportEvents()`: Export eventi/interventi in Excel/CSV
- `exportScheduler()`: Export scadenzario in Excel/CSV

### 3. API Endpoint

#### Data Export API
**File**: `public/data_export.php`

**Funzionalità**:
- Endpoint unificato per export di tutti i tipi di entità
- Supporto formati Excel (.xlsx) e CSV
- Controllo permessi integrato
- Logging automatico attività export
- Gestione errori completa

**Utilizzo**:
```
GET /data_export.php?entity=members&format=excel
GET /data_export.php?entity=vehicles&format=csv
```

### 4. Frontend

#### Dashboard Avanzata
**File**: `public/dashboard_advanced.php`

**Caratteristiche**:
- **8 KPI Cards**: Soci attivi, cadetti, mezzi operativi, eventi attivi, corsi attivi, articoli sotto scorta, interventi YTD, ore volontariato YTD
- **Grafici Interattivi** con Chart.js:
  - Eventi per tipo (bar chart)
  - Trend eventi mensili (line chart)
  - Top 20 volontari per ore (horizontal bar)
  - Stato magazzino (doughnut chart)
- **Mappa Geografica Interventi** con Leaflet
  - Visualizza tutti gli interventi con coordinate GPS
  - Popup informativi per ogni marker
- **Export Grafici**: Possibilità di esportare ogni grafico come immagine PNG
- **Responsive Design**: Funziona su desktop, tablet e mobile

#### Pulsanti Export su Tutte le Pagine
Aggiunti pulsanti "Esporta" con dropdown Excel/CSV su:
- `public/members.php`
- `public/junior_members.php`
- `public/meetings.php`
- `public/vehicles.php`
- `public/warehouse.php`
- `public/structure_management.php`
- `public/training.php`
- `public/events.php`
- `public/scheduler.php`

**Design Pattern**:
```php
<?php if ($app->checkPermission('module_name', 'export')): ?>
<div class="btn-group me-2">
    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
        <i class="bi bi-download"></i> Esporta
    </button>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="data_export.php?entity=module_name&format=excel">
            <i class="bi bi-file-earmark-excel"></i> Excel (.xlsx)
        </a></li>
        <li><a class="dropdown-item" href="data_export.php?entity=module_name&format=csv">
            <i class="bi bi-file-earmark-text"></i> CSV
        </a></li>
    </ul>
</div>
<?php endif; ?>
```

#### Sidebar Enhancement
**File**: `src/Views/includes/sidebar.php`

Aggiunto link "Statistiche Avanzate" nella sidebar per utenti con permesso `dashboard:view_advanced`

### 5. Librerie JavaScript Integrate

#### Chart.js 4.4.0
- Libreria principale per grafici interattivi
- Supporta bar, line, pie, doughnut charts
- Export canvas come immagine

#### Leaflet 1.9.4
- Libreria per mappe geografiche
- Integrazione con OpenStreetMap
- Markers personalizzati con popup

## Schema Database Aggiornato

**File**: `database_schema.sql`

Aggiunta sezione completa "ADVANCED DASHBOARD FEATURES" con:
- Tutte le tabelle di configurazione
- Tutte le viste per statistiche
- Tutti i permessi export
- Grants automatici per ruolo admin

## Sicurezza

### Controlli Permessi
Tutti gli endpoint verificano:
1. Autenticazione utente
2. Permesso specifico per modulo e azione (view, export, customize)
3. Validazione parametri input

### Logging Attività
Tutte le azioni di export vengono registrate nel sistema con:
- User ID
- Modulo esportato
- Formato (Excel/CSV)
- Timestamp

### SQL Injection Prevention
- Tutti i parametri utente vengono sanificati
- Utilizzo di prepared statements
- Validazione tipi e range valori

## Performance

### Caching
- Cache statistiche dashboard per 30 minuti
- Riduce carico database per query complesse
- Invalidazione automatica dopo scadenza

### Query Optimization
- Utilizzo di viste materializzate per statistiche YoY
- Indici su campi di ricerca frequenti
- Aggregazioni pre-calcolate dove possibile

### Lazy Loading
- Grafici caricati solo quando necessario
- Mappa geografica caricata solo se ci sono dati
- Dati pesanti recuperati on-demand

## Compatibilità

### Browser
- Chrome/Edge: ✅ Full support
- Firefox: ✅ Full support
- Safari: ✅ Full support
- Mobile browsers: ✅ Responsive design

### PHP
- Versione minima: PHP 8.3.0
- Librerie richieste: PhpSpreadsheet (già presente)

### MySQL
- Versione minima: MySQL 5.6
- Compatibilità: MySQL 8.x ✅

## Come Utilizzare

### Per Amministratori

1. **Applicare la Migration**:
   ```sql
   SOURCE migrations/013_add_advanced_dashboard_features.sql;
   ```

2. **Verificare Permessi**:
   - Assicurarsi che i ruoli abbiano i permessi corretti
   - Admin ha automaticamente tutti i permessi

3. **Accedere alla Dashboard Avanzata**:
   - Login come admin o utente con permesso `dashboard:view_advanced`
   - Click su "Statistiche Avanzate" nella sidebar
   - Oppure navigare direttamente a `/dashboard_advanced.php`

### Per Utenti

1. **Visualizzare KPI**:
   - Dashboard mostra automaticamente tutti i KPI principali
   - KPI si aggiornano automaticamente ogni 30 minuti

2. **Esportare Dati**:
   - Aprire qualsiasi pagina di gestione (Soci, Mezzi, etc.)
   - Click su pulsante "Esporta"
   - Scegliere formato (Excel o CSV)
   - File viene scaricato automaticamente

3. **Visualizzare Grafici**:
   - Navigare a Dashboard Avanzata
   - Grafici sono interattivi (hover per dettagli)
   - Click su pulsante export per salvare grafico come immagine

4. **Visualizzare Mappa**:
   - Mappa mostra tutti gli interventi con coordinate GPS
   - Click su marker per vedere dettagli intervento

## Troubleshooting

### Errore "Permessi insufficienti"
**Soluzione**: Verificare che l'utente abbia il permesso export per il modulo specifico

### Grafici non si caricano
**Soluzione**: 
- Verificare connessione internet (Chart.js è CDN)
- Controllare console JavaScript per errori
- Verificare che ci siano dati da visualizzare

### Export fallisce
**Soluzione**:
- Verificare che PhpSpreadsheet sia installato (`composer install`)
- Controllare permessi scrittura su directory temporanea PHP
- Verificare log errori PHP

### Mappa non si visualizza
**Soluzione**:
- Verificare connessione internet (Leaflet è CDN)
- Assicurarsi che ci siano interventi con coordinate GPS nel database
- Controllare console JavaScript per errori

## Manutenzione

### Cache Cleanup
La cache viene automaticamente pulita dopo scadenza. Per pulizia manuale:
```sql
DELETE FROM dashboard_stats_cache WHERE expires_at < NOW();
```

### Performance Monitoring
Query pesanti da monitorare:
- `v_yoy_event_stats`
- `v_yoy_member_stats`
- `v_intervention_geographic_stats`

Considerare indici aggiuntivi se le performance degradano con l'aumentare dei dati.

## Estensioni Future

### Possibili Miglioramenti
1. **Grafici Personalizzabili**: Permettere agli utenti di scegliere quali grafici visualizzare
2. **Dashboard Widget Drag-and-Drop**: Layout dashboard personalizzabile
3. **Schedulazione Export**: Export automatici programmati via cron
4. **Report PDF**: Generazione report PDF completi
5. **Dashboard Real-time**: Aggiornamento statistiche in tempo reale con WebSocket
6. **Filtri Avanzati**: Filtri temporali e categorici sui grafici
7. **Export Bulk**: Export multipli in un unico file ZIP

## Autori
- Implementazione: GitHub Copilot
- Data: 19 Gennaio 2026

## License
Come da progetto principale EasyVol
