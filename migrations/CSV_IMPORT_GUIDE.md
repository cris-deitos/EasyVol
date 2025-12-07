# Guida Import CSV - EasyVol

## Panoramica

Il sistema di import CSV permette di importare dati da file CSV con struttura MONOTABELLA e convertirli automaticamente in struttura MULTI-TABELLA del database EasyVol.

## Caratteristiche Principali

### ✅ Funzionalità Implementate

- **Encoding Detection Automatico**: Rileva automaticamente UTF-8 e ISO-8859-1
- **4 Tipi di Import Supportati**:
  - Soci Adulti
  - Cadetti (Minorenni)
  - Mezzi e Veicoli
  - Attrezzature e Magazzino
- **Mappatura Intelligente**: Suggerimento automatico mapping colonne CSV → campi database
- **Anteprima Dati**: Visualizza prime 10 righe prima dell'import
- **Split Automatico**: Distribuisce dati nelle tabelle correlate
- **Gestione Contatti Multipli**: Email, Telefono, Cellulare, PEC
- **Gestione Indirizzi Multipli**: Residenza, Domicilio
- **Rilevamento Duplicati**: Via matricola, targa o codice
- **Transazioni Sicure**: Rollback automatico in caso di errore
- **Log Dettagliato**: Ogni import viene registrato con dettagli completi

## Installazione

### 1. Esegui la Migration

Prima di utilizzare il sistema di import, esegui la migration per creare la tabella dei log:

```bash
mysql -u root -p easyvol < migrations/create_import_logs_table.sql
```

Oppure tramite PHP:

```bash
php migrations/run_migration.php create_import_logs_table.sql
```

### 2. Verifica Permessi

Assicurati che la directory `uploads/imports` abbia i permessi corretti:

```bash
chmod 755 uploads/imports
```

## Strutture Dati

### Soci Adulti

**Tabella Principale**: `members`

**Tabelle Correlate**:
- `member_contacts` (email, telefono_fisso, cellulare, pec)
- `member_addresses` (residenza, domicilio)
- `member_employment` (dati lavorativi)

**Colonne CSV Suggerite**:
```
Matricola, Nome, Cognome, Data_Nascita, Luogo_Nascita, Provincia_Nascita,
Codice_Fiscale, Sesso, Nazionalita, Data_Iscrizione, Data_Approvazione,
Tipo_Socio, Stato_Socio, Stato_Volontario, Email, Telefono, Cellulare, PEC,
Via_Residenza, Numero_Residenza, Citta_Residenza, Provincia_Residenza, CAP_Residenza,
Via_Domicilio, Numero_Domicilio, Citta_Domicilio, Provincia_Domicilio, CAP_Domicilio,
Datore_Lavoro, Indirizzo_Lavoro, Citta_Lavoro, Telefono_Lavoro, Note
```

### Cadetti (Minorenni)

**Tabella Principale**: `junior_members`

**Tabelle Correlate**:
- `junior_member_contacts` (email, telefono_fisso, cellulare)
- `junior_member_addresses` (residenza, domicilio)
- `junior_member_guardians` (padre, madre, tutore)

**Colonne CSV Suggerite**:
```
Matricola, Nome, Cognome, Data_Nascita, Luogo_Nascita, Provincia_Nascita,
Codice_Fiscale, Sesso, Nazionalita, Data_Iscrizione, Data_Approvazione,
Stato_Socio, Email, Telefono, Cellulare,
Via_Residenza, Numero_Residenza, Citta_Residenza, Provincia_Residenza, CAP_Residenza,
Nome_Padre, Cognome_Padre, CF_Padre, Telefono_Padre, Email_Padre,
Nome_Madre, Cognome_Madre, CF_Madre, Telefono_Madre, Email_Madre,
Nome_Tutore, Cognome_Tutore, CF_Tutore, Telefono_Tutore, Email_Tutore, Note
```

### Mezzi e Veicoli

**Tabella Principale**: `vehicles`

**Tabelle Correlate**:
- `vehicle_maintenance` (record di manutenzione)

**Colonne CSV Suggerite**:
```
Tipo, Nome, Targa, Marca, Modello, Anno, Numero_Serie, Stato,
Scadenza_Assicurazione, Scadenza_Revisione, Note
```

**Valori Validi per Tipo**: `veicolo`, `natante`, `rimorchio`
**Valori Validi per Stato**: `operativo`, `in_manutenzione`, `fuori_servizio`, `dismesso`

### Attrezzature e Magazzino

**Tabella Principale**: `warehouse_items`

**Tabelle Correlate**:
- `warehouse_movements` (movimenti di magazzino)

**Colonne CSV Suggerite**:
```
Codice, Nome, Categoria, Descrizione, Quantita, Quantita_Minima,
Unita, Posizione, Stato
```

**Valori Validi per Stato**: `disponibile`, `in_manutenzione`, `fuori_servizio`

## Formati Dati Supportati

### Date

Il sistema riconosce automaticamente i seguenti formati:
- `YYYY-MM-DD` (es: 2024-01-15)
- `DD/MM/YYYY` (es: 15/01/2024)
- `DD-MM-YYYY` (es: 15-01-2024)
- `YYYY/MM/DD` (es: 2024/01/15)

### Sesso/Genere

Valori accettati (case-insensitive):
- `M`, `MASCHIO`, `MALE`, `UOMO` → viene convertito in `M`
- `F`, `FEMMINA`, `FEMALE`, `DONNA` → viene convertito in `F`

### Tipo Socio

Valori accettati:
- `ordinario` (default)
- `fondatore`

### Stato Socio

Valori accettati:
- `attivo` (default)
- `decaduto`
- `dimesso`
- `in_aspettativa`
- `sospeso`
- `in_congedo`

### Stato Volontario

Valori accettati:
- `operativo`
- `non_operativo`
- `in_formazione` (default)

## Utilizzo

### 1. Accedi al Sistema

Vai su: **Impostazioni → Import CSV**

Oppure accedi direttamente: `/public/import_data.php`

### 2. Carica File CSV

1. Seleziona il **Tipo Import** (Soci, Cadetti, Mezzi, Attrezzature)
2. Scegli il **File CSV**
3. Seleziona il **Delimitatore** (virgola, punto e virgola, tab)
4. Clicca su **Carica e Analizza**

### 3. Verifica Anteprima e Mappatura

- Controlla l'**anteprima** delle prime 10 righe
- Verifica la **mappatura automatica** delle colonne
- Modifica la mappatura se necessario
- Lascia vuoto un campo per ignorare quella colonna

### 4. Conferma Import

- Clicca su **Conferma e Importa**
- Il sistema:
  - Crea una transazione
  - Importa i dati nelle tabelle appropriate
  - Rileva e salta i duplicati
  - Registra tutto nel log
  - In caso di errore, esegue rollback automatico

### 5. Visualizza Risultati

- Vedi il riepilogo con:
  - Record importati con successo
  - Record saltati (duplicati)
  - Record con errori
  - Log dettagliato riga per riga

## Gestione Duplicati

Il sistema rileva automaticamente i duplicati basandosi su:

- **Soci**: `registration_number` (matricola)
- **Cadetti**: `registration_number` (matricola)
- **Mezzi**: `license_plate` (targa)
- **Attrezzature**: `code` (codice)

I record duplicati vengono **saltati** e registrati nel log come "skipped".

## Log degli Import

Ogni import viene registrato nella tabella `import_logs` con:

- Tipo di import
- Nome file
- Encoding rilevato
- Totale righe processate
- Righe importate/saltate/errori
- Stato (in_progress, completed, failed, partial)
- Dettagli in formato JSON
- Data e ora inizio/fine
- Utente che ha eseguito l'import

Per visualizzare i log:
- Vai su **Impostazioni → Import CSV**
- Nella sezione "Ultimi Import" trovi gli ultimi 5 import eseguiti

## Esempi CSV

### Esempio Soci

```csv
Matricola,Nome,Cognome,Data_Nascita,Email,Cellulare,Via_Residenza,Citta_Residenza
001,Mario,Rossi,15/01/1980,mario.rossi@example.com,3331234567,Via Roma 10,Roma
002,Laura,Bianchi,20/03/1985,laura.bianchi@example.com,3339876543,Via Milano 5,Milano
```

### Esempio Cadetti

```csv
Matricola,Nome,Cognome,Data_Nascita,Email,Nome_Padre,Cognome_Padre,Telefono_Padre
C001,Luca,Neri,12/05/2010,luca.neri@example.com,Paolo,Neri,3332222222
C002,Sofia,Gialli,08/08/2012,sofia.gialli@example.com,Marco,Gialli,3335555555
```

### Esempio Mezzi

```csv
Tipo,Nome,Targa,Marca,Modello,Anno,Stato
veicolo,Ambulanza 1,AB123CD,Fiat,Ducato,2018,operativo
natante,Gommone 1,IJ789KL,Zodiac,Pro 500,2019,operativo
```

### Esempio Attrezzature

```csv
Codice,Nome,Categoria,Quantita,Unita,Posizione,Stato
ATT001,Casco Protezione,DPI,50,pz,Magazzino A,disponibile
ATT002,Giubbotto Alta Visibilita,DPI,80,pz,Magazzino A,disponibile
```

## Troubleshooting

### Il file CSV non viene caricato

- Verifica che il file sia effettivamente un CSV
- Controlla che il delimitatore sia corretto
- Assicurati che il file non superi i limiti di upload PHP

### Errore "encoding not supported"

- Il file potrebbe avere un encoding non supportato
- Converti il file in UTF-8 o ISO-8859-1
- Su Windows, salva come "CSV UTF-8"

### Alcune righe vengono saltate

- Controlla se ci sono duplicati (stessa matricola/targa/codice)
- Verifica che tutti i campi obbligatori siano presenti
- Guarda il log dettagliato per il motivo specifico

### Errore di transazione

- Verifica che il database supporti le transazioni InnoDB
- Controlla i permessi dell'utente database
- Assicurati che le foreign key constraints siano rispettate

## Sicurezza

- I file CSV vengono salvati temporaneamente in `uploads/imports`
- La directory è protetta da `.htaccess` (accesso negato via web)
- I file vengono eliminati dopo l'import
- Tutte le operazioni sono registrate nel log
- Le transazioni garantiscono l'integrità dei dati

## Performance

Per import di grandi dimensioni (>1000 righe):

1. Dividi il file in più parti
2. Esegui import separati
3. Monitora i log per eventuali errori
4. Considera di aumentare i limiti PHP:
   - `max_execution_time`
   - `memory_limit`
   - `upload_max_filesize`
   - `post_max_size`

## Supporto

Per problemi o domande:
1. Controlla i log degli import
2. Verifica la struttura del CSV
3. Consulta questa guida
4. Contatta il supporto tecnico

---

**Versione**: 1.0  
**Ultima modifica**: Dicembre 2024
