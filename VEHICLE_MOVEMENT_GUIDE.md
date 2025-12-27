# Sistema di Movimentazione Mezzi

## Panoramica

Il sistema di Movimentazione Mezzi permette di tracciare completamente l'utilizzo dei veicoli dell'associazione, includendo:
- Registrazione uscite e rientri
- Gestione autisti con validazione patenti
- Check list personalizzate per veicolo
- Notifiche email per anomalie e sanzioni
- Storico completo dei movimenti

## Installazione

### 1. Eseguire la migrazione del database

```bash
cd /path/to/EasyVol
mysql -u username -p database_name < migrations/add_vehicle_movement_management.sql
```

### 2. Configurare le email

Accedere a **Impostazioni > Email** e configurare:
- Impostazioni SMTP
- Email per alert movimentazione veicoli (campo "Email per Alert Movimentazione Veicoli")

### 3. Aggiungere le qualifiche autisti

Nella sezione **Soci > Qualifiche**, aggiungere le seguenti qualifiche:
- AUTISTA A
- AUTISTA B
- AUTISTA C
- AUTISTA D
- AUTISTA E
- PILOTA NATANTE

### 4. Configurare i veicoli

Per ogni veicolo:
1. Accedere a **Mezzi > Modifica Mezzo**
2. Compilare il campo "Patente Richiesta" (es: B, C, B,E, Nautica)
3. (Opzionale) Creare check list personalizzate

## Utilizzo

### Pagina Pubblica (per soci)

URL: `https://tuosito.it/EasyVol/public/vehicle_movement_login.php`

1. **Accesso**:
   - Numero Matricola
   - Cognome
   - Solo soci con qualifica AUTISTA o PILOTA possono accedere

2. **Funzionalità disponibili**:
   - Visualizzare elenco mezzi
   - Cambiare stato mezzo (Operativo, In Manutenzione, Fuori Servizio)
   - Registrare uscita veicolo
   - Registrare rientro veicolo
   - Aggiungere manutenzioni
   - Caricare documenti

3. **Uscita Veicolo**:
   - Data e ora uscita (obbligatorio)
   - Autisti (obbligatorio, con validazione patenti)
   - Km partenza (facoltativo)
   - Stato carburante (facoltativo)
   - Tipo di servizio (facoltativo)
   - Destinazione (facoltativo)
   - Autorizzato da (facoltativo)
   - Check list uscita
   - Note anomalie
   - Flag "Invia Alert di Anomalia"

4. **Rientro Veicolo**:
   - Data e ora rientro (obbligatorio)
   - Autisti rientro (facoltativo)
   - Km rientro (facoltativo)
   - Stato carburante (facoltativo)
   - Check list rientro
   - Note anomalie
   - Flag "Ipotesi Sanzioni Codice della Strada"
   - Flag "Invia Alert di Anomalia"

### Gestione Interna

URL: `https://tuosito.it/EasyVol/public/vehicle_movements.php`

1. **Visualizzazione Storico**:
   - Elenco completo movimenti
   - Filtri per veicolo, stato, data
   - Visualizzazione dettagli completi

2. **Funzionalità aggiuntive**:
   - Creare uscita da gestionale
   - Registrare rientro da gestionale
   - Completare viaggio senza rientro
   - Esportare storico

## Check List

### Creare Check List Personalizzate

Le check list possono essere create direttamente nel database nella tabella `vehicle_checklists`:

```sql
INSERT INTO vehicle_checklists (vehicle_id, item_name, item_type, check_timing, is_required, display_order)
VALUES 
  (1, 'Livello olio motore', 'boolean', 'departure', 1, 1),
  (1, 'Pressione gomme', 'boolean', 'departure', 1, 2),
  (1, 'Funzionamento luci', 'boolean', 'both', 1, 3),
  (1, 'Livello carburante (litri)', 'numeric', 'departure', 0, 4),
  (1, 'Danni visibili', 'text', 'return', 0, 5);
```

**Tipi di item**:
- `boolean`: Checkbox sì/no
- `numeric`: Campo numerico
- `text`: Campo testo

**Timing**:
- `departure`: Solo in uscita
- `return`: Solo al rientro
- `both`: Sia in uscita che al rientro

## Notifiche Email

### Alert Anomalie

Quando viene flaggato "Invia Alert di Anomalia", viene inviata un'email agli indirizzi configurati in impostazioni con:
- Dettagli veicolo
- Data/ora uscita o rientro
- Check list compilata
- Note anomalie
- Operatore che ha effettuato la segnalazione

### Alert Sanzioni Codice della Strada

Quando viene flaggato "Ipotesi Sanzioni Codice della Strada", viene inviata un'email all'email dell'associazione con:
- Dettagli veicolo
- Data/ora uscita e rientro
- Autisti uscita e rientro
- Durata viaggio
- Km percorsi

## Validazione Patenti

Il sistema verifica automaticamente che gli autisti abbiano le patenti richieste dal veicolo:

- Se il veicolo richiede patente "B", almeno un autista deve avere la qualifica "AUTISTA B"
- Se il veicolo richiede patente "B,E", devono esserci autisti con "AUTISTA B" E "AUTISTA E" (anche lo stesso autista può avere entrambe)
- Per natanti, è necessaria la qualifica "PILOTA NATANTE"

## Stati Veicolo

- **Operativo**: Disponibile per missioni normali e manutenzione
- **In Manutenzione**: Disponibile solo per attività di manutenzione, test, trasferimenti
- **Fuori Servizio**: NON disponibile per missioni

## Tracciamento

Tutte le operazioni vengono tracciate con:
- Operatore (matricola, nome, cognome)
- Data e ora
- Dettagli dell'operazione

## Responsive Design

La pagina pubblica è completamente ottimizzata per smartphone, permettendo la gestione direttamente sul campo.

## Supporto

Per problemi o domande:
1. Verificare la configurazione email
2. Verificare le qualifiche soci
3. Controllare i log di sistema
4. Contattare l'amministratore di sistema
