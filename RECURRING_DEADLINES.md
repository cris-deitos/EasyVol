# Scadenze Ricorrenti - Documentazione Funzionalità

## Descrizione

Il sistema EasyVol ora supporta scadenze ricorrenti che si ripetono automaticamente nel tempo. Questa funzionalità permette di creare scadenze che si rinnovano automaticamente secondo una frequenza configurabile.

## Tipi di Ricorrenza

### 1. Settimanale (Weekly)
La scadenza si ripete ogni settimana nello stesso giorno.

**Esempio:**
- Data iniziale: 11 Gennaio 2026 (Domenica)
- Occorrenze generate: 18 Gen, 25 Gen, 1 Feb, 8 Feb, ecc.

**Casi d'uso:**
- Riunioni settimanali
- Controlli periodici settimanali
- Turni di guardia

### 2. Mensile (Monthly)
La scadenza si ripete ogni mese nello stesso giorno.

**Esempio:**
- Data iniziale: 15 Gennaio 2026
- Occorrenze generate: 15 Feb, 15 Mar, 15 Apr, ecc.

**Casi d'uso:**
- Pagamenti mensili
- Report mensili
- Controlli mensili

**Nota:** Se la data cade su un giorno che non esiste in alcuni mesi (es. 31), PHP gestirà automaticamente il overflow al mese successivo.

### 3. Annuale (Yearly)
La scadenza si ripete ogni anno nello stesso giorno.

**Esempio:**
- Data iniziale: 11 Gennaio 2026
- Occorrenze generate: 11 Gen 2027, 11 Gen 2028, ecc.

**Casi d'uso:**
- Rinnovi annuali
- Scadenze certificate
- Audit annuali

## Come Funziona

### Creazione Scadenza Ricorrente

1. **Vai su Scadenzario** → "Nuova Scadenza"
2. **Compila i dati base:**
   - Titolo
   - Descrizione
   - Data prima scadenza
   - Categoria, priorità, ecc.

3. **Abilita ricorrenza:**
   - Spunta la checkbox "Abilita ricorrenza automatica"
   - Seleziona il tipo di ricorrenza (Settimanale/Mensile/Annuale)
   - (Opzionale) Imposta una data di fine ricorrenza

4. **Configurazione Destinatari:**
   - I destinatari configurati verranno copiati automaticamente su ogni occorrenza generata

### Data Fine Ricorrenza

La data di fine ricorrenza è **opzionale**:

- **Con data di fine:** Le scadenze verranno generate solo fino a quella data
- **Senza data di fine:** Le scadenze continueranno a generarsi indefinitamente fino all'eliminazione manuale

### Generazione Automatica

Le occorrenze future vengono generate automaticamente tramite cron job:

- **Frequenza:** Giornaliera (alle 02:00)
- **Lookahead:** 90 giorni in anticipo
- **Script:** `cron/generate_recurring_deadlines.php`

Questo garantisce che ci siano sempre scadenze future già create per notifiche e promemoria.

## Modifica Scadenze Ricorrenti

### Modifica Scadenza Principale

Quando modifichi una scadenza ricorrente principale:
- Le modifiche NON vengono applicate alle occorrenze già generate
- Le nuove occorrenze erediteranno le nuove impostazioni

### Modifica Singola Occorrenza

Ogni occorrenza generata può essere modificata indipendentemente:
- Le modifiche interessano solo quella specifica occorrenza
- Non influenzano la scadenza principale o altre occorrenze

## Eliminazione

### Eliminazione Scadenza Principale

Quando elimini una scadenza ricorrente principale, hai due opzioni:

1. **Elimina anche tutte le occorrenze future** (default)
   - Elimina la scadenza principale e tutte le sue occorrenze non completate
   - Le occorrenze completate rimangono per storico

2. **Elimina solo la principale**
   - Elimina solo la scadenza principale
   - Le occorrenze già generate rimangono come scadenze indipendenti

### Eliminazione Singola Occorrenza

Puoi eliminare singole occorrenze senza influenzare la scadenza principale o altre occorrenze.

## Identificazione Visiva

Nel Scadenzario, le scadenze ricorrenti sono identificate con badge:

- **Badge "Ricorrente"** (blu): Scadenza principale ricorrente
- **Badge "Occorrenza"** (info): Occorrenza generata da una scadenza ricorrente

## Struttura Database

### Nuovi Campi in `scheduler_items`

```sql
is_recurring TINYINT(1) DEFAULT 0
-- Flag che indica se la scadenza è ricorrente

recurrence_type ENUM('yearly', 'monthly', 'weekly') DEFAULT NULL
-- Tipo di ricorrenza

recurrence_end_date DATE DEFAULT NULL
-- Data fine ricorrenza (NULL = indeterminato)

parent_recurrence_id INT(11) DEFAULT NULL
-- ID della scadenza ricorrente principale
-- NULL per scadenze principali
-- Valorizzato per le occorrenze generate
```

## API e Controller

### Metodi Principali in SchedulerController

#### `generateNextRecurrence($parentId, $baseDate = null)`
Genera la prossima occorrenza per una scadenza ricorrente.

**Parametri:**
- `$parentId`: ID della scadenza ricorrente principale
- `$baseDate`: Data base da cui calcolare (opzionale, default: due_date del parent)

**Ritorna:** ID della nuova occorrenza o `false` in caso di errore

#### `generateAllRecurrences($daysAhead = 90)`
Genera tutte le occorrenze future per tutte le scadenze ricorrenti attive.

**Parametri:**
- `$daysAhead`: Numero di giorni in avanti per cui generare occorrenze

**Ritorna:** Numero di occorrenze generate

#### `calculateNextOccurrence($currentDate, $recurrenceType)`
Calcola la prossima data di occorrenza basata sul tipo di ricorrenza.

**Parametri:**
- `$currentDate`: Data corrente
- `$recurrenceType`: 'weekly', 'monthly', 'yearly'

**Ritorna:** Prossima data in formato Y-m-d

## Configurazione Cron Job

### Installazione CLI

Aggiungi al crontab:

```bash
# Genera occorrenze scadenze ricorrenti - Giornaliero alle 02:00
0 2 * * * php /percorso/easyvol/cron/generate_recurring_deadlines.php >> /var/log/easyvol/recurring_deadlines.log 2>&1
```

### Installazione HTTPS (Hosting Aruba)

Nel pannello cron di Aruba:

```bash
0 2 * * * wget -q -O /dev/null "https://tuosito.com/public/cron/generate_recurring_deadlines.php?token=IL_TUO_TOKEN"
```

## Esempi Pratici

### Esempio 1: Riunione Settimanale del Consiglio Direttivo

```
Titolo: Riunione Consiglio Direttivo
Data: 11 Gennaio 2026 (Domenica)
Ricorrenza: Settimanale
Data Fine: 31 Dicembre 2026

Risultato: 52 occorrenze generate (una per ogni domenica dell'anno)
```

### Esempio 2: Rinnovo Assicurazione Annuale

```
Titolo: Rinnovo Polizza Assicurativa Mezzi
Data: 1 Marzo 2026
Ricorrenza: Annuale
Data Fine: (nessuna)

Risultato: Occorrenze generate ogni 1 Marzo indefinitamente
```

### Esempio 3: Report Mensile

```
Titolo: Report Attività Mensile
Data: 30 Gennaio 2026
Ricorrenza: Mensile
Data Fine: (nessuna)

Risultato: Occorrenze generate il 30 di ogni mese
(Attenzione: febbraio avrà overflow al 2-3 marzo)
```

## Limitazioni e Note

1. **Overflow Mesi:** Per date come il 31, PHP gestisce automaticamente i mesi con meno giorni
2. **Generazione in Anticipo:** Le occorrenze vengono generate con 90 giorni di anticipo
3. **Safety Limit:** Il sistema ha un limite di 100 iterazioni per prevenire loop infiniti
4. **Occorrenze Completate:** Le occorrenze completate non vengono rigenerate
5. **Cron Dependency:** La generazione automatica dipende dal corretto funzionamento del cron job

## Troubleshooting

### Le occorrenze non vengono generate

**Verifiche:**
1. Controlla che il cron job sia configurato correttamente
2. Verifica i log: `/var/log/easyvol/recurring_deadlines.log`
3. Testa manualmente: `php cron/generate_recurring_deadlines.php`

### Troppe occorrenze generate

**Soluzione:**
- Modifica il parametro `$daysAhead` nel cron job (default: 90 giorni)

### Occorrenze duplicate

**Causa:** Il cron job è stato eseguito più volte
**Soluzione:** Il sistema previene automaticamente i duplicati verificando se l'occorrenza esiste già

## Best Practices

1. **Usa Date di Fine:** Per scadenze con durata limitata, imposta sempre una data di fine
2. **Descrizioni Chiare:** Indica nella descrizione che si tratta di una scadenza ricorrente
3. **Categoria Coerente:** Usa categorie specifiche per facilitare il filtraggio
4. **Destinatari Appropriati:** Configura destinatari solo per chi deve ricevere tutte le occorrenze
5. **Monitoraggio:** Controlla periodicamente il log del cron job per verificare il corretto funzionamento

## Migrazione Database

La funzionalità richiede l'esecuzione della migration `008_add_recurring_deadlines.sql`:

```bash
# Esegui la migration manualmente
mysql -u username -p database_name < migrations/008_add_recurring_deadlines.sql
```

Oppure tramite il sistema di installazione web di EasyVol se disponibile.

## Supporto

Per problemi o domande sulla funzionalità di scadenze ricorrenti, consulta:
- Il codice sorgente: `src/Controllers/SchedulerController.php`
- I test di logica: `/tmp/test_recurring_logic.php`
- La documentazione generale di EasyVol

---

**Versione:** 1.0  
**Data:** 11 Gennaio 2026  
**Autore:** Sistema EasyVol
