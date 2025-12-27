# Istruzioni per la Migrazione del Sistema di Movimentazione Mezzi

Questo documento contiene le istruzioni per applicare gli aggiornamenti al sistema di movimentazione mezzi, includendo il supporto per i rimorchi.

## Panoramica degli Aggiornamenti

Gli aggiornamenti includono:

1. **Creazione della tabella `vehicle_movements`** (se non esiste)
2. **Aggiunta supporto rimorchi** - I veicoli possono ora avere rimorchi agganciati
3. **Validazione patenti combinata** - Verifica che gli autisti abbiano le patenti sia del veicolo che del rimorchio
4. **Checklist combinate** - Le checklist di veicolo e rimorchio vengono sommate
5. **Rinomina menu** - "Movimenti Veicoli" → "Movimentazione Mezzi"

## Requisiti

- MySQL 5.6+ o MySQL 8.x
- Accesso al database con privilegi di ALTER TABLE
- Backup del database prima di procedere

## Passo 1: Backup del Database

**IMPORTANTE**: Eseguire sempre un backup completo del database prima di applicare le migrazioni.

```bash
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql
```

## Passo 2: Applicare le Migrazioni

### 2.1 Migrazione Base (se la tabella vehicle_movements non esiste)

Se ricevi l'errore "Table 'vehicle_movements' doesn't exist", esegui prima questa migrazione:

```bash
mysql -u username -p database_name < migrations/add_vehicle_movement_management.sql
```

Questa migrazione crea:
- Tabella `vehicle_movements`
- Tabella `vehicle_movement_drivers`
- Tabella `vehicle_movement_checklists`
- Tabella `vehicle_checklists`
- Campo `license_type` nella tabella `vehicles`
- Configurazioni email per gli alert

### 2.2 Migrazione Supporto Rimorchi

Dopo aver creato le tabelle base, applica la migrazione per il supporto rimorchi:

```bash
mysql -u username -p database_name < migrations/add_trailer_support_to_vehicle_movements.sql
```

Questa migrazione aggiunge:
- Campo `trailer_id` nella tabella `vehicle_movements`
- Foreign key per collegare i rimorchi

## Passo 3: Verificare le Migrazioni

Verifica che le tabelle siano state create correttamente:

```sql
-- Verifica struttura tabella vehicle_movements
DESCRIBE vehicle_movements;

-- Verifica che il campo trailer_id sia presente
SHOW COLUMNS FROM vehicle_movements LIKE 'trailer_id';

-- Conta i movimenti esistenti
SELECT COUNT(*) FROM vehicle_movements;
```

## Passo 4: Configurare le Qualifiche Autisti

Le seguenti qualifiche devono essere configurate nel sistema (Soci > Qualifiche):

- **AUTISTA A** - Per veicoli con patente A
- **AUTISTA B** - Per veicoli con patente B
- **AUTISTA C** - Per veicoli con patente C
- **AUTISTA D** - Per veicoli con patente D
- **AUTISTA E** - Per rimorchi (patente E)
- **PILOTA NATANTE** - Per natanti

### Esempio SQL per creare le qualifiche (opzionale)

Se vuoi crearle direttamente nel database:

```sql
-- Inserisci le qualifiche se non esistono già
INSERT INTO member_roles (member_id, role_name, start_date)
VALUES 
  (1, 'AUTISTA B', '2024-01-01'),  -- Sostituisci 1 con l'ID del socio
  (1, 'AUTISTA E', '2024-01-01');
```

## Passo 5: Configurare i Veicoli

Per ogni veicolo, specifica le patenti richieste:

1. Vai a **Mezzi > Modifica Mezzo**
2. Compila il campo "Patente Richiesta":
   - Veicolo con patente B: `B`
   - Veicolo con patente B + rimorchio: `B,E`
   - Veicolo con patente C: `C`
   - Natante: `Nautica`

3. Per i rimorchi:
   - Vai a **Mezzi**
   - Crea o modifica il rimorchio
   - Imposta "Tipo Veicolo" = `rimorchio`
   - Specifica la patente richiesta (es: `E`)

## Passo 6: Configurare le Email di Alert

1. Vai a **Impostazioni**
2. Nella sezione "Notifiche Movimentazione Veicoli"
3. Inserisci gli indirizzi email per ricevere gli alert (separati da virgola)

## Funzionalità del Sistema Aggiornato

### Selezione Rimorchio

Quando si registra un'uscita veicolo:
1. È possibile selezionare un rimorchio opzionale dalla lista
2. Solo i rimorchi disponibili (non in missione, non fuori servizio) sono mostrati
3. Il sistema verifica che gli autisti abbiano le patenti necessarie per veicolo E rimorchio

### Validazione Patenti Combinata

Esempio:
- Veicolo richiede patente `B`
- Rimorchio richiede patente `E`
- Gli autisti devono avere qualifiche **AUTISTA B** E **AUTISTA E**

Gli autisti possono essere diversi, purché collettivamente coprano tutte le patenti richieste.

### Checklist Combinate

Durante l'uscita o il rientro:
- Le checklist del veicolo vengono mostrate normalmente
- Se è presente un rimorchio, le sue checklist vengono aggiunte (prefissate con "[RIMORCHIO]")
- Tutte le checklist devono essere completate

### Visualizzazione

Il rimorchio viene visualizzato:
- Nella lista movimenti (storico)
- Nei dettagli del movimento
- Durante il rientro veicolo
- Nella missione attiva

## Risoluzione Problemi

### Errore: Table 'vehicle_movements' doesn't exist

Esegui la migrazione base (Passo 2.1).

### Errore: Column 'trailer_id' doesn't exist

Esegui la migrazione rimorchi (Passo 2.2).

### Errore: Foreign key constraint fails

Verifica che:
1. Le tabelle `vehicles` e `members` esistano
2. I valori di `trailer_id` siano validi (esistano nella tabella `vehicles`)

### Gli autisti non vengono validati correttamente

Verifica che:
1. I soci abbiano le qualifiche corrette (AUTISTA A, B, C, D, E)
2. Le qualifiche non siano scadute (end_date IS NULL o >= oggi)
3. Il campo `license_type` dei veicoli sia compilato correttamente

### Il rimorchio non appare nella lista

Verifica che:
1. Il veicolo sia di tipo `rimorchio` (vehicle_type = 'rimorchio')
2. Lo stato non sia `fuori_servizio` o `dismesso`
3. Il rimorchio non sia già in missione

## Test del Sistema

### Test 1: Uscita con Rimorchio

1. Vai a **Movimentazione Mezzi** (pagina pubblica)
2. Seleziona un veicolo
3. Clicca "Registra Uscita"
4. Seleziona autisti con patenti B ed E
5. Seleziona un rimorchio
6. Verifica che il sistema accetti la registrazione

### Test 2: Validazione Patenti

1. Prova a selezionare autisti senza patente E
2. Seleziona un rimorchio che richiede patente E
3. Verifica che il sistema blocchi l'uscita con messaggio di errore

### Test 3: Checklist Combinate

1. Crea checklist per un veicolo
2. Crea checklist per un rimorchio
3. Registra uscita con rimorchio
4. Verifica che entrambe le checklist appaiano

## Supporto

Per problemi o domande:
1. Controlla i log del server web
2. Verifica la configurazione del database
3. Consulta la documentazione completa in `VEHICLE_MOVEMENT_GUIDE.md`

## Changelog

### Versione 1.1 (2025-12-27)

- ✅ Aggiunto supporto rimorchi alla tabella `vehicle_movements`
- ✅ Validazione patenti combinata per veicolo + rimorchio
- ✅ Checklist combinate per veicolo + rimorchio
- ✅ Rinominato "Movimenti Veicoli" in "Movimentazione Mezzi"
- ✅ Aggiunto metodo `getAvailableTrailers()` al controller
- ✅ Aggiornate query per includere informazioni rimorchio
- ✅ Interfaccia utente aggiornata per mostrare rimorchi

### Versione 1.0 (2025-12-26)

- ✅ Sistema base di movimentazione veicoli
- ✅ Gestione partenze e rientri
- ✅ Validazione patenti
- ✅ Checklist personalizzate
- ✅ Notifiche email
