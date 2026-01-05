# Gestione Strutture - Documentazione

## Panoramica

Il modulo **Gestione Strutture** permette alle associazioni di gestire in modo completo tutte le strutture e sedi utilizzate, con informazioni dettagliate su proprietari, contratti, chiavi e localizzazione GPS.

## Caratteristiche Principali

### 📋 Elenco Strutture
- Visualizzazione tabellare di tutte le strutture
- Informazioni essenziali: nome, tipologia, indirizzo, proprietario, coordinate GPS
- Azioni rapide: visualizza, modifica, elimina

### 🗺️ Mappa Strutture
- Visualizzazione geografica su mappa interattiva OpenStreetMap
- Markers colorati per identificare rapidamente le strutture
- Tooltip informativi con dettagli al passaggio del mouse
- Vista ottimizzata per mostrare tutte le strutture contemporaneamente

### ✏️ Gestione Completa
- **Creazione**: Aggiungi nuove strutture con tutti i dettagli
- **Modifica**: Aggiorna informazioni esistenti
- **Visualizzazione**: Scheda dettagliata di ogni struttura
- **Eliminazione**: Rimozione sicura con conferma

## Campi Disponibili

### Informazioni Generali
- **Nome*** (obbligatorio): Nome identificativo della struttura
- **Tipologia**: Categoria/tipo di struttura (es. Sede, Magazzino, Deposito, Campo base, ecc.)

### Localizzazione
- **Indirizzo Completo**: Con autocomplete intelligente per selezione rapida
- **Coordinate GPS**: Latitudine e longitudine (compilate automaticamente tramite geocoding)

### Informazioni Amministrative
- **Proprietario**: Nome del proprietario/ente proprietario
- **Contatti Proprietario**: Telefoni, email, referenti (campo di testo libero)
- **Contratti e Scadenze**: Informazioni su contratti di locazione, comodato d'uso, scadenze (campo di testo libero)
- **Chiavi e Codici**: Informazioni su chiavi, codici di accesso, badge (campo di testo libero)
- **Note**: Informazioni aggiuntive, particolarità, restrizioni (campo di testo libero)

## Funzionalità Avanzate

### 🔍 Geocoding e Autocomplete Indirizzi
- Integrazione con servizio di geocoding OpenStreetMap
- Ricerca indirizzi in tempo reale mentre si digita
- Selezione rapida con autocompletamento
- **Coordinate GPS automatiche**: Quando si seleziona un indirizzo, latitudine e longitudine vengono compilate automaticamente
- Supporto per indirizzi italiani e internazionali

### 🗺️ Visualizzazione Mappa
- Mappa interattiva con Leaflet.js
- Zoom e navigazione fluidi
- Markers personalizzati per ogni struttura
- Auto-zoom per visualizzare tutte le strutture nell'area ottimale
- Tooltip informativi con:
  - Nome struttura
  - Tipologia
  - Indirizzo completo

### 🔒 Sicurezza e Permessi
- Autenticazione obbligatoria per accesso al modulo
- Sistema di permessi granulare:
  - `structure_management.view`: Visualizzare strutture
  - `structure_management.edit`: Creare e modificare strutture
  - `structure_management.delete`: Eliminare strutture
- Validazione input lato server e client
- Prevenzione XSS e SQL injection
- Log automatico di tutte le operazioni

### 📝 Activity Log
- Registrazione automatica di tutte le operazioni
- Tracciamento di:
  - Creazione nuove strutture
  - Modifiche ai dati
  - Eliminazioni
- Informazioni salvate:
  - Utente che ha effettuato l'operazione
  - Data e ora
  - Dettagli dell'operazione

## Interfaccia Utente

### Layout a Due Tab
1. **Elenco Strutture**: Vista tabellare per gestione rapida
2. **Mappa Strutture**: Vista geografica per visualizzazione spaziale

### Modal Interattivi
- **Modal Aggiungi/Modifica**: Form completo per inserimento dati
- **Modal Visualizza**: Scheda di sola lettura con tutti i dettagli formattati
- **Modal Conferma Eliminazione**: Protezione contro eliminazioni accidentali

## Database

### Tabella `structures`
```sql
CREATE TABLE `structures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `full_address` varchar(500) DEFAULT NULL,
  `latitude` decimal(10, 8) DEFAULT NULL,
  `longitude` decimal(11, 8) DEFAULT NULL,
  `owner` varchar(255) DEFAULT NULL,
  `owner_contacts` text,
  `contracts_deadlines` text,
  `keys_codes` text,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `type` (`type`),
  KEY `idx_coordinates` (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## API Endpoints

### `GET /api/structures.php?action=list`
Restituisce l'elenco completo delle strutture

### `GET /api/structures.php?action=get&id={id}`
Restituisce i dettagli di una specifica struttura

### `POST /api/structures.php` (action=create)
Crea una nuova struttura

### `POST /api/structures.php` (action=update)
Aggiorna una struttura esistente

### `POST /api/structures.php` (action=delete)
Elimina una struttura

### `GET /api/structures.php?action=get_with_coordinates`
Restituisce solo le strutture con coordinate GPS (per la mappa)

## Installazione

### Nuove Installazioni
Il modulo è già incluso nel file `database_schema.sql`. Durante l'installazione iniziale:
1. La tabella `structures` viene creata automaticamente
2. I permessi `structure_management` vengono configurati
3. Il menu "Strutture" appare nella sidebar dopo "Magazzino"

### Installazioni Esistenti (Aggiornamento)
Per aggiungere il modulo a installazioni esistenti:

1. Eseguire la migration:
```bash
mysql -u username -p database_name < migrations/20260105_add_structure_management.sql
```

2. Assegnare i permessi agli utenti/ruoli appropriati tramite l'interfaccia di gestione permessi

3. Il menu "Strutture" apparirà automaticamente per gli utenti con permessi appropriati

## Utilizzo

### Aggiungere una Struttura

1. Accedere a **Strutture** dal menu laterale
2. Cliccare su **"Aggiungi Struttura"**
3. Compilare i campi obbligatori (almeno il Nome)
4. Utilizzare il campo Indirizzo per cercare e selezionare l'indirizzo:
   - Digitare l'indirizzo
   - Selezionare dalla lista dei suggerimenti
   - Le coordinate GPS verranno compilate automaticamente
5. Aggiungere eventuali informazioni aggiuntive (proprietario, contatti, contratti, ecc.)
6. Cliccare su **"Salva"**

### Modificare una Struttura

1. Dall'elenco strutture, cliccare sull'icona **matita** (Modifica)
2. Aggiornare i campi desiderati
3. Cliccare su **"Salva"**

### Visualizzare una Struttura

1. Dall'elenco strutture, cliccare sull'icona **occhio** (Visualizza)
2. Verrà mostrata una scheda con tutti i dettagli formattati

### Eliminare una Struttura

1. Dall'elenco strutture, cliccare sull'icona **cestino** (Elimina)
2. Confermare l'operazione nel modal di conferma
3. **ATTENZIONE**: L'eliminazione è permanente e non può essere annullata

### Visualizzare la Mappa

1. Cliccare sulla tab **"Mappa Strutture"**
2. La mappa mostrerà tutte le strutture con coordinate GPS
3. Cliccare sui marker per vedere i dettagli
4. Utilizzare i controlli della mappa per zoom e navigazione

## Best Practices

### Convenzioni Nomenclatura
- Usare nomi descrittivi e univoci per le strutture
- Specificare la tipologia per facilitare la ricerca e categorizzazione
- Esempio: "Sede Principale - Via Roma 1", "Magazzino Nord", "Campo Base Monte Bianco"

### Gestione Indirizzi
- Utilizzare sempre l'autocomplete per garantire coordinate GPS corrette
- Verificare che le coordinate sulla mappa corrispondano alla posizione reale
- In caso di indirizzi ambigui, aggiungere dettagli nelle Note

### Informazioni Sensibili
- I campi "Chiavi e Codici" sono visibili solo agli utenti autorizzati
- Prestare attenzione nella gestione dei permessi
- Considerare l'uso di codici o riferimenti invece di informazioni dirette

### Manutenzione Dati
- Aggiornare regolarmente le informazioni sui contratti e scadenze
- Rivedere periodicamente i contatti dei proprietari
- Archiviare strutture non più utilizzate invece di eliminarle (aggiungendo "Non in uso" nelle Note)

## Supporto Tecnico

Per problemi o domande sul modulo Gestione Strutture:
1. Verificare i log di attività per eventuali errori
2. Controllare i permessi utente/ruolo
3. Verificare la connessione al servizio di geocoding
4. Consultare la documentazione tecnica del progetto

## Note di Sviluppo

### File Modificati/Creati
- `database_schema.sql`: Aggiunta tabella `structures` e permessi
- `migrations/20260105_add_structure_management.sql`: Migration per installazioni esistenti
- `src/Controllers/StructureController.php`: Controller backend
- `public/api/structures.php`: Endpoint API REST
- `public/structure_management.php`: Interfaccia utente principale
- `src/Views/includes/sidebar.php`: Aggiunto menu item
- `README.md`: Aggiornata documentazione principale

### Dipendenze
- Leaflet.js 1.9.4 (mappa)
- OpenStreetMap (tiles mappa)
- Bootstrap 5.3.0 (UI)
- Bootstrap Icons 1.11.0 (icone)
- Nominatim/OpenStreetMap Geocoding API (geocoding e ricerca indirizzi)

### Compatibilità
- PHP 8.3+
- MySQL 5.6+ / MySQL 8.x / MariaDB 10.3+
- Browser moderni (Chrome, Firefox, Safari, Edge)
