# Sistema di Promemoria Pagamento Quote Associative

Questo documento descrive il sistema di invio promemoria per il pagamento delle quote associative implementato in EasyVol.

## Panoramica

Il sistema consente di inviare promemoria via email a tutti i soci (volontari e cadetti) che non hanno ancora versato la quota associativa per un determinato anno. Il sistema implementa:

- **Cooldown di 20 giorni**: Previene l'invio di promemoria troppo frequenti
- **Invio asincrono**: Le email vengono accodate e inviate gradualmente tramite cron
- **Tracking completo**: Ogni invio viene tracciato nel database
- **Interfaccia utente intuitiva**: Pulsante nella sezione "Quote Non Versate"

## Architettura

### Database

Il sistema utilizza due nuove tabelle:

#### `fee_payment_reminders`
Traccia i batch di promemoria inviati:
- `id`: ID univoco del batch
- `year`: Anno di riferimento della quota
- `sent_at`: Data e ora di creazione del batch
- `sent_by`: ID dell'utente che ha avviato l'invio
- `total_sent`: Numero totale di email nel batch
- `status`: Stato del batch (pending, processing, completed, failed)
- `notes`: Note aggiuntive

#### `fee_payment_reminder_members`
Traccia ogni singola email del batch:
- `id`: ID univoco dell'email
- `reminder_id`: Riferimento al batch
- `member_type`: Tipo di socio (adult/junior)
- `member_id`: ID del socio
- `registration_number`: Matricola del socio
- `email`: Indirizzo email del destinatario
- `status`: Stato dell'invio (pending, sent, failed)
- `sent_at`: Data e ora di invio effettivo
- `error_message`: Messaggio di errore in caso di fallimento

### Componenti

1. **FeePaymentController** (`src/Controllers/FeePaymentController.php`)
   - `canSendReminders()`: Verifica se è possibile inviare promemoria (20 giorni dall'ultimo invio)
   - `createReminderBatch()`: Crea un nuovo batch di promemoria
   - `processReminderQueue()`: Processa la coda e invia le email (chiamato da cron)
   - `getUnpaidMembersForReminder()`: Ottiene lista soci senza quota (con email)

2. **API Endpoint** (`public/api/send_fee_reminders.php`)
   - Gestisce la richiesta AJAX dall'interfaccia utente
   - Valida i permessi e il token CSRF
   - Crea il batch di promemoria

3. **Interfaccia Utente** (`public/fee_payments.php`)
   - Tab "Quote Non Versate"
   - Pulsante "Invia Promemoria" con stato (abilitato/disabilitato)
   - Messaggio informativo sul cooldown
   - JavaScript per l'invio AJAX

4. **Cron Job** (`cron/fee_payment_reminders.php`)
   - Eseguito ogni 5 minuti
   - Processa fino a 50 email per esecuzione
   - Aggiorna lo stato delle email e dei batch

## Flusso di Lavoro

### 1. Creazione Batch

Quando un amministratore clicca sul pulsante "Invia Promemoria":

1. Il sistema verifica che siano passati almeno 20 giorni dall'ultimo invio
2. Ottiene la lista di tutti i soci attivi senza quota per l'anno selezionato
3. Filtra solo i soci che hanno un indirizzo email valido
4. Crea un record in `fee_payment_reminders` con status 'pending'
5. Crea un record in `fee_payment_reminder_members` per ogni socio
6. Restituisce conferma all'utente

### 2. Invio Email (via Cron)

Il cron `fee_payment_reminders.php` esegue questi passi:

1. Ottiene fino a 50 email con status 'pending'
2. Per ogni email:
   - Recupera i dati del socio
   - Costruisce il corpo dell'email
   - Accoda l'email nella tabella `email_queue`
   - Aggiorna lo status a 'sent' o 'failed'
3. Aggiorna lo status del batch quando tutte le email sono processate

### 3. Invio Effettivo

Il cron `email_queue.php` (già esistente) processa la coda standard e invia fisicamente le email tramite SMTP.

## Installazione

### 1. Migrazione Database

Eseguire la migrazione `migrations/018_add_fee_reminder_system.sql`:

```bash
mysql -u username -p database_name < migrations/018_add_fee_reminder_system.sql
```

Oppure copiare le CREATE TABLE nel tool di gestione database (phpMyAdmin, ecc.).

### 2. Configurare il Cron

Aggiungere al crontab:

```bash
# Fee Payment Reminders - Ogni 5 minuti
*/5 * * * * php /percorso/easyvol/cron/fee_payment_reminders.php >> /var/log/easyvol/fee_reminders.log 2>&1
```

**Per Aruba Hosting (HTTPS):**

```bash
*/5 * * * * wget -q -O /dev/null "https://tuosito.com/public/cron/fee_payment_reminders.php?token=IL_TUO_TOKEN"
```

### 3. Verificare Permessi

L'utente deve avere il permesso `members.edit` per accedere alla funzionalità.

## Utilizzo

### Interfaccia Web

1. Accedere a **Gestione Pagamento Quote**
2. Selezionare il tab **Quote Non Versate**
3. Selezionare l'**anno** desiderato
4. Cliccare su **Invia Promemoria**
5. Confermare l'operazione nel popup
6. Le email saranno accodate e inviate gradualmente

### Verifica Stato

Dopo l'invio, è possibile verificare lo stato:

- Nel database, tabella `fee_payment_reminders` per lo stato del batch
- Nel database, tabella `fee_payment_reminder_members` per singole email
- Nei log del cron: `/var/log/easyvol/fee_reminders.log`
- Nella tabella `activity_logs` per le esecuzioni del cron

## Contenuto Email

L'email inviata contiene:

- Saluto personalizzato con nome e cognome del socio
- Informazione sulla quota non versata per l'anno specificato
- Matricola del socio
- Invito a provvedere al pagamento
- Riferimento al portale per caricare la ricevuta

## Configurazione

### Cooldown

Il cooldown di 20 giorni è hardcoded nel metodo `canSendReminders()`. Per modificarlo, editare:

```php
// In src/Controllers/FeePaymentController.php
return [
    'can_send' => $daysSince >= 20,  // Modificare questo valore
    // ...
];
```

### Batch Size

Il numero di email processate per esecuzione cron è configurabile:

```php
// In cron/fee_payment_reminders.php
$sent = $controller->processReminderQueue(50);  // Modificare questo valore
```

### Contenuto Email

Per personalizzare il contenuto dell'email, modificare il metodo `buildReminderEmailBody()` in `FeePaymentController.php`.

## Monitoring

### Query Utili

**Verificare batch creati:**
```sql
SELECT * FROM fee_payment_reminders ORDER BY sent_at DESC;
```

**Verificare email in coda:**
```sql
SELECT COUNT(*) as pending 
FROM fee_payment_reminder_members 
WHERE status = 'pending';
```

**Verificare email fallite:**
```sql
SELECT frm.*, m.first_name, m.last_name
FROM fee_payment_reminder_members frm
LEFT JOIN members m ON frm.member_id = m.id AND frm.member_type = 'adult'
LEFT JOIN junior_members jm ON frm.member_id = jm.id AND frm.member_type = 'junior'
WHERE frm.status = 'failed';
```

**Verificare ultimo invio per anno:**
```sql
SELECT year, MAX(sent_at) as last_sent, 
       DATEDIFF(NOW(), MAX(sent_at)) as days_since
FROM fee_payment_reminders
WHERE status = 'completed'
GROUP BY year;
```

## Troubleshooting

### Il pulsante è disabilitato

**Causa**: È già stato inviato un promemoria negli ultimi 20 giorni.

**Soluzione**: Attendere il tempo rimanente indicato nel messaggio di warning.

### Email non vengono inviate

**Causa**: Il cron `fee_payment_reminders.php` non è in esecuzione.

**Soluzione**: 
1. Verificare configurazione cron: `crontab -l`
2. Controllare i log: `tail -f /var/log/easyvol/fee_reminders.log`
3. Eseguire manualmente per test: `php cron/fee_payment_reminders.php`

### Alcune email falliscono

**Causa**: Indirizzi email non validi o problemi SMTP.

**Soluzione**:
1. Verificare gli indirizzi email nella tabella `fee_payment_reminder_members` con status 'failed'
2. Controllare l'`error_message` per dettagli
3. Correggere gli indirizzi email non validi
4. Verificare configurazione SMTP in `config/config.php`

### Batch rimane in stato 'processing'

**Causa**: Alcune email sono bloccate in stato 'pending'.

**Soluzione**:
1. Controllare `fee_payment_reminder_members` per email pending
2. Verificare se il cron è in esecuzione
3. Se necessario, resettare manualmente lo stato:
```sql
UPDATE fee_payment_reminder_members 
SET status = 'failed', error_message = 'Manual reset' 
WHERE status = 'pending' 
AND reminder_id = [ID_BATCH];
```

## Sicurezza

- L'endpoint API richiede autenticazione e permesso `members.edit`
- Token CSRF obbligatorio per tutte le operazioni POST
- Il cron può essere protetto con token segreto (metodo HTTPS)
- Nessun dato sensibile nei log

## Performance

- Le email sono inviate in batch di 50 per esecuzione
- L'invio è distribuito nel tempo (ogni 5 minuti)
- Non impatta sulle prestazioni del sistema principale
- Il processo è asincrono e non blocca l'interfaccia utente

## Estensioni Future

Possibili miglioramenti:

- [ ] Template email personalizzabile dall'interfaccia
- [ ] Statistiche di invio (aperture, click)
- [ ] Filtri avanzati (per nucleo, gruppo, ecc.)
- [ ] Invio selettivo a specifici soci
- [ ] Reminder automatici programmabili
- [ ] Notifiche Telegram per gli amministratori
- [ ] Report di riepilogo dopo l'invio

## Supporto

Per problemi o domande:
- Consultare i log di sistema
- Verificare la configurazione email
- Controllare i permessi utente
- Aprire una issue su GitHub con dettagli
