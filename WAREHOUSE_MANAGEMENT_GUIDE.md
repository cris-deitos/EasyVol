# Warehouse Management Guide - Guida Gestione Magazzino

## Panoramica / Overview

Il sistema di gestione magazzino consente di:
- Gestire articoli di magazzino (attrezzature, DPI, materiali)
- Tracciare movimenti di carico/scarico
- Assegnare DPI ai volontari
- Generare QR code e barcode per gli articoli

## Funzionalità Implementate

### 1. Gestione Articoli
- **Creazione articoli**: Codice, nome, categoria, quantità, ubicazione
- **Modifica articoli**: Aggiornamento dati esistenti
- **Visualizzazione dettagli**: Vista completa con tabs per informazioni, movimenti e DPI

### 2. Generazione Codici
- **QR Code**: Generazione automatica QR code per identificazione rapida
- **Barcode**: Generazione barcode Code 128 per stampa etichette
- **Stampa**: Funzione di stampa diretta per QR code e barcode

### 3. Movimenti Magazzino
- **Tipi di movimento**:
  - Carico (entrata)
  - Scarico (uscita)
  - Assegnazione (a volontario)
  - Restituzione (da volontario)
  - Trasferimento (tra ubicazioni)
- **Tracciabilità**: Ogni movimento registra quantità, utente, data/ora, note
- **Storico completo**: Pagina dedicata per visualizzare tutti i movimenti

### 4. Assegnazione DPI
- **Assegnazione a volontari**: Assegnazione DPI ai soci/volontari
- **Tracking scadenze**: Registrazione data scadenza DPI
- **Gestione quantità**: Quantità specifica per ogni assegnazione
- **Stato**: Monitoraggio stato (assegnato/restituito)

## Struttura File

### Controller
- `src/Controllers/WarehouseController.php`: Logica principale gestione magazzino

### Views/Pages
- `public/warehouse.php`: Lista articoli magazzino
- `public/warehouse_edit.php`: Creazione/modifica articolo
- `public/warehouse_view.php`: Dettaglio articolo con tabs
- `public/warehouse_movements.php`: Storico tutti i movimenti
- `public/warehouse_api.php`: API AJAX per operazioni

### Utilities
- `src/Utils/QrCodeGenerator.php`: Generazione QR code
- `src/Utils/BarcodeGenerator.php`: Generazione barcode

### Database
- `warehouse_items`: Articoli magazzino
- `warehouse_movements`: Movimenti
- `dpi_assignments`: Assegnazioni DPI
- `warehouse_maintenance`: Manutenzioni (opzionale)

## Utilizzo / Usage

### Creare un Articolo
1. Andare su "Gestione Magazzino"
2. Click su "Nuovo Articolo"
3. Compilare:
   - Nome articolo (obbligatorio)
   - Codice univoco (opzionale)
   - Categoria (DPI, Attrezzatura, etc.)
   - Quantità disponibile
   - Scorta minima
   - Ubicazione
4. Selezionare opzioni:
   - ☑ Genera QR Code automaticamente
   - ☑ Genera Barcode automaticamente
5. Salvare

### Registrare un Movimento
1. Aprire dettaglio articolo
2. Tab "Movimenti"
3. Click "Nuovo Movimento"
4. Selezionare:
   - Tipo movimento
   - Quantità
   - Volontario (se applicabile)
   - Destinazione
   - Note
5. Salvare

### Assegnare DPI
1. Aprire dettaglio articolo DPI
2. Tab "DPI Assegnati"
3. Click "Assegna DPI"
4. Selezionare:
   - Volontario
   - Quantità
   - Data assegnazione
   - Data scadenza (opzionale)
   - Note
5. Salvare

### Stampare QR Code/Barcode
1. Aprire dettaglio articolo
2. Click su "Stampa QR Code" o "Stampa Barcode"
3. Il codice viene generato e si apre finestra di stampa

## Database Migration

Per aggiornare il database con le nuove colonne:

```sql
-- Eseguire migration
source migrations/add_dpi_assignments_quantity_and_expiry.sql
```

Oppure manualmente:

```sql
ALTER TABLE `dpi_assignments` 
ADD COLUMN `quantity` int(11) NOT NULL DEFAULT 1 AFTER `member_id`,
ADD COLUMN `expiry_date` date NULL AFTER `return_date`,
ADD COLUMN `assigned_date` date NULL AFTER `assignment_date`;

UPDATE `dpi_assignments` SET `assigned_date` = `assignment_date` WHERE `assigned_date` IS NULL;
```

## Permessi / Permissions

- `warehouse.view`: Visualizzare articoli e movimenti
- `warehouse.create`: Creare nuovi articoli
- `warehouse.edit`: Modificare articoli, registrare movimenti, assegnare DPI
- `warehouse.delete`: Eliminare articoli (se privi di movimenti)

## API Endpoints

### POST warehouse_api.php

**Azioni disponibili:**

1. `add_movement`: Registra nuovo movimento
   - Parameters: `item_id`, `movement_type`, `quantity`, `member_id`, `destination`, `notes`

2. `assign_dpi`: Assegna DPI a volontario
   - Parameters: `item_id`, `member_id`, `quantity`, `assignment_date`, `expiry_date`, `notes`

3. `generate_qr`: Genera QR code
   - Parameters: `id` (item_id)

4. `generate_barcode`: Genera barcode
   - Parameters: `id` (item_id)

5. `get_members`: Lista volontari attivi
   - Parameters: `search` (opzionale)

## Note Tecniche

### QR Code
- Libreria: `endroid/qr-code` v6.0
- Formato: PNG
- Dimensione: 200x200px
- Percorso: `uploads/qrcodes/item_{id}.png`

### Barcode
- Tipo: Barcode semplificato (visual identification)
- Generazione: PHP GD Library
- Formato: PNG
- Dimensione: 300x80px
- Percorso: `uploads/barcodes/item_{id}.png`
- **Nota**: L'implementazione attuale è semplificata per etichette visive. Per l'uso con scanner barcode professionali, si consiglia di integrare una libreria completa Code 128 come 'picqer/php-barcode-generator'

### Movimenti
- I movimenti di tipo "carico" e "restituzione" aumentano la quantità
- I movimenti di tipo "scarico", "assegnazione" e "trasferimento" diminuiscono la quantità
- Ogni movimento è tracciato con utente, data/ora e note

### DPI Assignments
- Le assegnazioni DPI creano automaticamente un movimento di tipo "assegnazione"
- La quantità assegnata viene scalata dalla giacenza disponibile
- Le scadenze DPI possono essere monitorate per alert automatici

## Troubleshooting

### QR Code non si genera
- Verificare che la directory `uploads/qrcodes` esista e sia scrivibile
- Verificare che la libreria `endroid/qr-code` sia installata
- Controllare i log per errori specifici

### Barcode non si genera
- Verificare che l'estensione PHP GD sia abilitata
- Verificare che la directory `uploads/barcodes` esista e sia scrivibile
- Controllare i log per errori specifici

### Assegnazione DPI fallisce
- Verificare che il volontario esista e sia attivo
- Verificare che ci sia quantità sufficiente disponibile
- Verificare i permessi utente (`warehouse.edit`)

## Future Enhancements

Possibili miglioramenti futuri:
- Lettore QR code per mobile
- Inventario con scanner barcode
- Alert automatici per scorte minime
- Report statistici movimenti
- Export dati Excel/PDF
- Integrazione con fornitori
- Gestione manutenzioni attrezzature

## Support

Per problemi o domande:
- Consultare i log di errore PHP
- Verificare permessi database e filesystem
- Controllare configurazione `config/config.php`
