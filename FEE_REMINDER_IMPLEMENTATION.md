# Sistema di Promemoria Pagamento Quote Associative

Questo documento descrive il sistema semplificato di invio promemoria per il pagamento delle quote associative implementato in EasyVol.

## Panoramica

Il sistema consente di inviare promemoria via email a tutti i soci (volontari e cadetti) che non hanno ancora versato la quota associativa per un determinato anno. Il sistema implementa:

- **Cooldown di 20 giorni**: Previene l'invio di promemoria troppo frequenti
- **Invio immediato**: Le email vengono accodate direttamente nella `email_queue` esistente
- **Tracking semplificato**: Un solo record per batch nella tabella `fee_payment_reminders`
- **Interfaccia utente intuitiva**: Pulsante nella sezione "Quote Non Versate"

## Architettura Semplificata

### Database

Il sistema utilizza **UNA SOLA tabella** dedicata ai promemoria:

#### `fee_payment_reminders`
Traccia i batch di promemoria inviati (solo per il cooldown):
- `id`: ID univoco del batch
- `year`: Anno di riferimento della quota
- `sent_by`: ID dell'utente che ha avviato l'invio
- `sent_at`: Data e ora di creazione del batch
- `total_queued`: Numero totale di email accodate in `email_queue`

Le email vengono accodate nella tabella **esistente** `email_queue` che viene già processata dal cron `email_queue.php`.

### Componenti

1. **FeePaymentController** (`src/Controllers/FeePaymentController.php`)
   - `canSendReminders()`: Verifica se è possibile inviare promemoria (20 giorni dall'ultimo invio)
   - `createReminderBatch()`: Accoda le email direttamente in `email_queue` usando `EmailSender->queue()`
   - `getUnpaidMembersForReminder()`: Ottiene lista soci senza quota (con email)
   - `buildReminderEmailBody()`: Costruisce il corpo dell'email personalizzata

2. **API Endpoint** (`public/api/send_fee_reminders.php`)
   - Gestisce la richiesta AJAX dall'interfaccia utente
   - Valida i permessi e il token CSRF
   - Crea il batch di promemoria

3. **Interfaccia Utente** (`public/fee_payments.php`)
   - Tab "Quote Non Versate"
   - Pulsante "Invia Promemoria" con stato (abilitato/disabilitato)
   - Messaggio informativo sul cooldown
   - JavaScript per l'invio AJAX

4. **Cron Job Esistente** (`cron/email_queue.php`)
   - Già presente nel sistema
   - Processa TUTTE le email in coda (inclusi i promemoria)
   - Eseguito ogni 5-10 minuti su hosting condiviso

## Flusso di Lavoro Semplificato

### 1. Creazione Batch

Quando un amministratore clicca sul pulsante "Invia Promemoria":

1. Il sistema verifica che siano passati almeno 20 giorni dall'ultimo invio
2. Ottiene la lista di tutti i soci attivi senza quota per l'anno selezionato
3. Filtra solo i soci che hanno un indirizzo email valido
4. **Accoda direttamente le email in `email_queue`** usando `EmailSender->queue()`
5. Inserisce UN solo record in `fee_payment_reminders` per tracciare la data (cooldown)
6. Restituisce conferma all'utente

### 2. Invio Email (automatico)

Il cron **esistente** `email_queue.php` si occupa dell'invio:

1. Eseguito ogni 5-10 minuti (configurabile)
2. Processa le email in coda (inclusi i promemoria)
3. Invia le email tramite SMTP
4. Registra i log in `email_logs`

## Vantaggi della Soluzione Semplificata

✅ **Nessun cron aggiuntivo** - Usa l'infrastruttura esistente `email_queue.php`
✅ **Nessuna tabella ridondante** - Elimina `fee_payment_reminder_members`
✅ **Codice più semplice** - Meno metodi, meno complessità
✅ **Stesso comportamento** - L'utente non nota differenze
✅ **Manutenzione ridotta** - Un solo sistema di coda email da gestire

## Installazione

### 1. Migrazione Database

Eseguire la migrazione `migrations/018_add_fee_reminder_system.sql`:

```bash
mysql -u username -p database_name < migrations/018_add_fee_reminder_system.sql
```

La migration rimuoverà automaticamente la tabella `fee_payment_reminder_members` se esiste (cleanup).

### 2. Verificare Cron Esistente

Assicurarsi che il cron `email_queue.php` sia configurato:

```bash
# CLI
*/10 * * * * php /percorso/easyvol/cron/email_queue.php >> /var/log/easyvol/email_queue.log 2>&1

# HTTPS (per Aruba)
*/10 * * * * wget -q -O /dev/null "https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN"
```

**NOTA**: Non serve configurare `fee_payment_reminders.php` - è stato eliminato!

### 3. Verificare Permessi

L'utente deve avere il permesso `members.edit` per accedere alla funzionalità.

## Utilizzo

### Interfaccia Web

1. Accedere a **Gestione Pagamento Quote**
2. Selezionare il tab **Quote Non Versate**
3. Selezionare l'**anno** desiderato
4. Cliccare su **Invia Promemoria**
5. Confermare l'operazione nel popup
6. Le email saranno accodate e inviate automaticamente dal cron esistente

### Verifica Stato

Dopo l'invio, è possibile verificare lo stato:

- Nel database, tabella `fee_payment_reminders` per la data dell'ultimo invio
- Nel database, tabella `email_queue` per le email in coda
- Nella tabella `email_logs` per le email inviate
- Nei log del cron: `/var/log/easyvol/email_queue.log`

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

### Priorità Email

Le email di promemoria sono accodate con priorità 2 (importante ma non urgente):

```php
// In createReminderBatch()
$emailSender->queue(
    $member['email'],
    $subject,
    $body,
    [], // no attachments
    2   // priority: 1=alta, 5=bassa
);
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
FROM email_queue 
WHERE status = 'pending';
```

**Verificare email inviate (promemoria):**
```sql
SELECT * FROM email_logs 
WHERE subject LIKE 'Promemoria: Quota Associativa%'
ORDER BY sent_at DESC;
```

**Verificare ultimo invio per anno:**
```sql
SELECT year, MAX(sent_at) as last_sent, 
       DATEDIFF(NOW(), MAX(sent_at)) as days_since,
       SUM(total_queued) as total_emails
FROM fee_payment_reminders
GROUP BY year
ORDER BY year DESC;
```

## Troubleshooting

### Il pulsante è disabilitato

**Causa**: È già stato inviato un promemoria negli ultimi 20 giorni.

**Soluzione**: Attendere il tempo rimanente indicato nel messaggio di warning.

### Email non vengono inviate

**Causa**: Il cron `email_queue.php` non è in esecuzione.

**Soluzione**: 
1. Verificare configurazione cron: `crontab -l`
2. Controllare i log: `tail -f /var/log/easyvol/email_queue.log`
3. Eseguire manualmente per test: `php cron/email_queue.php`

### Email in coda ma non inviate

**Causa**: Possibili problemi SMTP o configurazione email.

**Soluzione**:
1. Verificare configurazione SMTP in `config/config.php`
2. Controllare la tabella `email_queue` per errori
3. Verificare i log in `email_logs` per messaggi di errore
4. Testare invio email manualmente tramite `public/test_email.php` (se disponibile)

## Differenze dal Sistema Precedente

### Prima (PR #274 - Complesso)
- ❌ Due tabelle: `fee_payment_reminders` + `fee_payment_reminder_members`
- ❌ Due cron: `email_queue.php` + `fee_payment_reminders.php`
- ❌ Doppia coda: prima `fee_payment_reminder_members`, poi `email_queue`
- ❌ Metodi aggiuntivi: `processReminderQueue()`, `updateReminderBatchStatus()`, ecc.

### Dopo (Semplificato)
- ✅ Una tabella: `fee_payment_reminders` (solo tracking cooldown)
- ✅ Un cron: `email_queue.php` (già esistente)
- ✅ Coda diretta: email accodate subito in `email_queue`
- ✅ Codice minimo: solo i metodi essenziali

## Sicurezza

- L'endpoint API richiede autenticazione e permesso `members.edit`
- Token CSRF obbligatorio per tutte le operazioni POST
- Il cron può essere protetto con token segreto (metodo HTTPS)
- Nessun dato sensibile nei log
- Input sanitizzato (htmlspecialchars) nel corpo email

## Performance

- Le email sono accodate istantaneamente
- L'invio è distribuito nel tempo tramite il cron esistente
- Non impatta sulle prestazioni del sistema principale
- Il processo è asincrono e non blocca l'interfaccia utente
- Nessun overhead di gestione tabelle duplicate

## Supporto

Per problemi o domande:
- Consultare i log di sistema (`email_queue.log`)
- Verificare la configurazione email in `config/config.php`
- Controllare i permessi utente
- Aprire una issue su GitHub con dettagli
