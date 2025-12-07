# Sistema di Logging Completo delle Attività

## Panoramica

È stato implementato un sistema di logging completo e dettagliato che traccia **QUALSIASI** attività effettuata dagli utenti nel gestionale EasyVol. Il sistema registra ogni pagina visitata, ogni ricerca effettuata, ogni filtro applicato, e ogni interazione con il sistema.

## Caratteristiche Principali

### 1. Logging Automatico
- **Accesso alle pagine**: Ogni volta che un utente visualizza una pagina, viene registrato automaticamente
- **Parametri di ricerca**: Tutte le ricerche e i filtri applicati vengono tracciati
- **Record ID**: Quando si visualizza un record specifico (socio, veicolo, documento, ecc.), viene registrato l'ID
- **Query parameters**: Tutti i parametri della URL vengono registrati nella descrizione

### 2. Informazioni Registrate
Per ogni attività vengono registrate le seguenti informazioni:
- **User ID**: Identificativo dell'utente che ha effettuato l'azione
- **Action**: Tipo di azione (page_view, create, edit, delete, search, filter, export, ecc.)
- **Module**: Modulo del sistema (members, vehicles, documents, events, ecc.)
- **Record ID**: ID del record coinvolto (se applicabile)
- **Description**: Descrizione dettagliata con parametri di ricerca, filtri, ecc.
- **IP Address**: Indirizzo IP dell'utente
- **User Agent**: Browser e sistema operativo utilizzato
- **Timestamp**: Data e ora precisa dell'azione

### 3. Pagina di Amministrazione

#### Accesso
Gli amministratori possono accedere al registro completo delle attività tramite:
- **URL**: `/public/activity_logs.php`
- **Menu**: Amministrazione → Registro Attività (visibile solo agli admin)

#### Funzionalità della Pagina
- **Visualizzazione completa**: Tutti i log di tutti gli utenti
- **Statistiche**: 
  - Totale attività registrate
  - Attività di oggi
  - Attività della settimana
  - Utenti attivi unici
- **Filtri Avanzati**:
  - Per utente
  - Per tipo di azione
  - Per modulo
  - Per intervallo di date (da/a)
- **Paginazione**: 50 risultati per pagina
- **Dettagli completi**: Visualizzazione di tutte le informazioni registrate
- **Badge colorati**: Codifica visiva per tipo di azione
- **Export e stampa**: Possibilità di stampare i risultati

#### Tipi di Azioni Registrate
- `page_view` - Visualizzazione di una pagina
- `view` - Visualizzazione di un record specifico
- `create` - Creazione di un nuovo record
- `edit` / `update` - Modifica di un record esistente
- `delete` - Eliminazione di un record
- `search` - Ricerca effettuata
- `filter` - Applicazione di filtri
- `export` - Esportazione dati
- `print` - Stampa documenti
- `login` - Accesso al sistema
- `logout` - Uscita dal sistema
- E molte altre...

## Implementazione Tecnica

### Componenti Principali

#### 1. ActivityLogger Middleware (`src/Middleware/ActivityLogger.php`)
Fornisce metodi per registrare diversi tipi di attività:
- `logPageView()` - Log visualizzazione pagina
- `logSearch()` - Log ricerca
- `logFilter()` - Log applicazione filtri
- `logRecordView()` - Log visualizzazione record
- `logExport()` - Log esportazione
- `logPrint()` - Log stampa

#### 2. AutoLogger Utility (`src/Utils/AutoLogger.php`)
Sistema di logging automatico che:
- Si integra automaticamente in tutte le pagine
- Estrae informazioni dalla URL e parametri
- Determina automaticamente il modulo dalla pagina
- Registra i parametri di ricerca e filtri
- Estrae automaticamente gli ID dei record

#### 3. Integrazione nelle Pagine
Tutte le pagine pubbliche (oltre 70 file) sono state aggiornate con:
```php
use EasyVol\Utils\AutoLogger;

// Dopo il controllo di autenticazione/permessi
AutoLogger::logPageAccess();
```

Per ricerche specifiche:
```php
if (!empty($filters['search'])) {
    AutoLogger::logSearch('module_name', $filters['search'], $filters);
}
```

### Database

#### Tabella `activity_logs`
Struttura già esistente nel database:
```sql
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11),
  `action` varchar(100) NOT NULL,
  `module` varchar(100),
  `record_id` int(11),
  `description` text,
  `ip_address` varchar(45),
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`),
  KEY `module_record` (`module`, `record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Gli indici sono ottimizzati per le query di ricerca e filtraggio.

## Utilizzo per gli Amministratori

### Come Accedere al Registro
1. Effettuare il login come amministratore
2. Nel menu laterale, sezione "Amministrazione", cliccare su "Registro Attività"
3. Verrà visualizzata la pagina con tutti i log

### Come Filtrare i Log
1. Utilizzare i filtri nella sezione in alto:
   - **Utente**: Selezionare un utente specifico dal menu a tendina
   - **Azione**: Filtrare per tipo di azione (view, create, edit, ecc.)
   - **Modulo**: Filtrare per modulo del sistema
   - **Da/A**: Selezionare un intervallo di date
2. Cliccare sul pulsante di ricerca (icona lente)
3. Per rimuovere i filtri, cliccare su "Rimuovi Filtri"

### Come Interpretare i Dati
- **Badge colorati** per le azioni:
  - Verde (success): Creazione di nuovi record
  - Blu (primary): Modifica di record esistenti
  - Rosso (danger): Eliminazione
  - Azzurro (info): Visualizzazione
  - Grigio (secondary): Altre azioni

### Esempi di Utilizzo

#### Monitoraggio Accessi
Filtrare per azione "page_view" e selezionare un utente per vedere tutte le pagine visitate.

#### Tracciamento Modifiche
Filtrare per azione "edit" o "update" per vedere tutte le modifiche effettuate, con dettagli su cosa è stato modificato.

#### Audit di Sicurezza
Verificare gli accessi sospetti filtrando per IP address o orari insoliti.

#### Analisi Utilizzo
Vedere quali moduli sono più utilizzati e da quali utenti.

## Sicurezza e Privacy

### Controllo Accessi
- **Solo amministratori** possono accedere alla pagina del registro attività
- Tentativo di accesso non autorizzato restituisce errore 403 Forbidden

### Dati Sensibili
Il sistema registra:
- ✅ Azioni effettuate
- ✅ Pagine visitate
- ✅ Parametri di ricerca
- ✅ IP e User Agent
- ❌ NON registra password
- ❌ NON registra dati sensibili come codici fiscali nei log

### Conformità GDPR
I log delle attività contengono dati personali (user_id, IP) e devono essere trattati secondo le normative GDPR:
- Gli utenti devono essere informati del tracciamento
- I log dovrebbero essere mantenuti per un periodo limitato
- Gli amministratori devono avere accesso legittimo ai log

## Manutenzione

### Pulizia Log Vecchi
Per evitare che il database cresca troppo, è consigliabile implementare una pulizia periodica:
```sql
-- Elimina log più vecchi di 365 giorni
DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY);
```

### Performance
- La tabella ha indici ottimizzati per le query di ricerca
- La paginazione limita il carico a 50 record per pagina
- I filtri riducono il numero di record analizzati

## Sviluppo Futuro

Possibili miglioramenti:
- [ ] Export dei log in CSV/Excel
- [ ] Grafici e statistiche avanzate
- [ ] Alert per attività sospette
- [ ] Retention policy automatica dei log
- [ ] API per integrazione con sistemi esterni
- [ ] Dashboard tempo reale delle attività

## Supporto

Per problemi o domande relative al sistema di logging:
1. Verificare che la tabella `activity_logs` esista nel database
2. Verificare i permessi di scrittura sul database
3. Controllare i log di errore di PHP per eventuali problemi

## Conclusioni

Il sistema di logging implementato fornisce una visibilità completa su tutte le attività effettuate nel gestionale, permettendo agli amministratori di:
- Monitorare l'utilizzo del sistema
- Tracciare le modifiche ai dati
- Analizzare il comportamento degli utenti
- Identificare problemi o anomalie
- Garantire conformità e audit

Ogni azione, ricerca, visualizzazione e modifica viene registrata automaticamente, fornendo un audit trail completo e dettagliato di tutto ciò che accade nel sistema.
