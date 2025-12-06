# Feature: Notifiche Email e Gestione Riunioni Avanzata

Questo documento descrive le nuove funzionalità implementate per le notifiche email e la gestione avanzata delle riunioni in EasyVol.

## Indice

1. [Destinatari Email Scadenzario](#destinatari-email-scadenzario)
2. [Gestione Partecipanti Riunioni](#gestione-partecipanti-riunioni)
3. [Verifica Annuale Dati Soci](#verifica-annuale-dati-soci)
4. [Installazione](#installazione)
5. [Utilizzo](#utilizzo)

---

## Destinatari Email Scadenzario

### Descrizione

Quando si crea o modifica una scadenza nel sistema scadenzario, è ora possibile specificare destinatari che riceveranno automaticamente un'email di promemoria.

### Tipi di Destinatari

1. **Utenti Sistema**: Utenti con account nel sistema
2. **Soci**: Membri maggiorenni attivi (l'email viene presa dai loro contatti)
3. **Email Esterne**: Indirizzi email esterni non collegati a utenti o soci

### Funzionalità

- Selezione multipla di utenti e soci
- Inserimento di più email esterne separate da virgola
- Invio automatico dei promemoria X giorni prima della scadenza
- Il promemoria viene inviato sia ai destinatari personalizzati che all'utente assegnato

### Database

**Nuova Tabella: `scheduler_item_recipients`**

```sql
CREATE TABLE IF NOT EXISTS `scheduler_item_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scheduler_item_id` int(11) NOT NULL,
  `recipient_type` enum('user', 'member', 'external') NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `external_email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`scheduler_item_id`) REFERENCES `scheduler_items`(`id`) ON DELETE CASCADE
);
```

### API / Controller

**Metodi aggiunti a `SchedulerController`:**

- `getRecipients($schedulerItemId)` - Ottiene tutti i destinatari di una scadenza
- `addRecipients($schedulerItemId, $recipients)` - Aggiunge destinatari a una scadenza
- `removeAllRecipients($schedulerItemId)` - Rimuove tutti i destinatari
- `getRecipientEmails($schedulerItemId)` - Ottiene gli indirizzi email dei destinatari

### UI

**File modificato: `public/scheduler_edit.php`**

Nella pagina di creazione/modifica scadenza è stata aggiunta una sezione "Destinatari Email Promemoria" che permette di:
- Selezionare utenti da una lista a tendina multipla
- Selezionare soci attivi da una lista a tendina multipla
- Inserire email esterne in un campo di testo (separate da virgola)

### Cron Job

**File modificato: `cron/scheduler_alerts.php`**

Il cron job è stato aggiornato per:
1. Inviare promemoria agli utenti assegnati (comportamento esistente)
2. Inviare promemoria ai destinatari personalizzati (nuovo)

---

## Gestione Partecipanti Riunioni

### Descrizione

Sistema completo per gestire i partecipanti alle riunioni/assemblee con funzionalità di convocazione via email e tracciamento presenze.

### Funzionalità

1. **Aggiunta Partecipanti**
   - Aggiunta massiva di tutti i soci attivi (maggiorenni e minorenni)
   - Aggiunta individuale con specifica del ruolo
   - Supporto sia per soci maggiorenni che minorenni

2. **Invio Convocazioni**
   - Invio email automatico a tutti i partecipanti
   - Email HTML formattata con tutti i dettagli della riunione
   - Ordine del giorno incluso nell'email
   - Tracciamento delle email inviate
   - Per i soci minorenni, l'email viene inviata ai genitori/tutori

3. **Tracciamento Presenze**
   - Stati: Invitato, Presente, Assente, Delegato
   - Campo per specificare a chi è stata data la delega
   - Data/ora di conferma presenza

### Database

**Modifiche alla tabella `meetings`:**

```sql
ALTER TABLE `meetings`
  ADD COLUMN `convocation_sent_at` timestamp NULL,
  ADD COLUMN `convocator` varchar(255),
  ADD COLUMN `description` text;
```

**Modifiche alla tabella `meeting_participants`:**

```sql
ALTER TABLE `meeting_participants`
  ADD COLUMN `member_type` enum('adult', 'junior') DEFAULT 'adult',
  ADD COLUMN `junior_member_id` int(11),
  ADD COLUMN `attendance_status` enum('invited', 'present', 'absent', 'delegated') DEFAULT 'invited',
  ADD COLUMN `delegated_to` int(11),
  ADD COLUMN `invitation_sent_at` timestamp NULL,
  ADD COLUMN `response_date` timestamp NULL;
```

### API / Controller

**Metodi aggiunti a `MeetingController`:**

- `addParticipantsFromMembers($meetingId, $includeAdults, $includeJuniors)` - Aggiunge tutti i soci attivi
- `addParticipant($meetingId, $memberId, $memberType, $role)` - Aggiunge un singolo partecipante
- `updateAttendance($participantId, $status, $delegatedTo)` - Aggiorna stato presenza
- `sendInvitations($meetingId, $userId)` - Invia convocazioni via email
- `buildInvitationEmail($meeting, $recipientName)` - Costruisce l'email di convocazione

### UI

**Nuovo file: `public/meeting_participants.php`**

Pagina dedicata alla gestione dei partecipanti con le seguenti sezioni:

1. **Aggiungi Partecipanti**
   - Form per aggiungere tutti i soci (checkbox per maggiorenni/minorenni)
   - Form per aggiungere singolo socio con selezione tipo e ruolo

2. **Convocazione**
   - Riepilogo partecipanti e email da inviare
   - Pulsante per invio convocazioni
   - Indicatore se convocazione già inviata

3. **Lista Partecipanti**
   - Tabella con tutti i partecipanti
   - Stato presenza con badge colorati
   - Indicatore invio email
   - Pulsante per modificare stato presenza

4. **Modal Aggiornamento Presenza**
   - Selezione stato (Invitato/Presente/Assente/Delegato)
   - Campo per delegato (visibile solo se stato = Delegato)

---

## Verifica Annuale Dati Soci

### Descrizione

Cron job che viene eseguito una volta l'anno (7 gennaio) per inviare a ogni socio attivo un'email con tutti i propri dati anagrafici completi, chiedendo conferma o segnalazione di eventuali variazioni.

### Funzionalità

- Invio automatico il 7 gennaio di ogni anno alle 9:00
- Email separate per soci maggiorenni e minorenni
- Controllo per evitare invii duplicati nello stesso anno
- Tracciamento completo degli invii (successi e fallimenti)

### Dati Inclusi nell'Email

**Per Soci Maggiorenni:**
- Dati anagrafici (nome, cognome, codice fiscale, data/luogo di nascita)
- Indirizzi (residenza, domicilio)
- Contatti (telefoni, email, PEC)
- Patenti e brevetti
- Informazioni sanitarie (allergie, intolleranze, patologie, diete)

**Per Soci Minorenni:**
- Dati anagrafici del minore
- Dati genitori/tutori (nome, contatti)

### Database

**Nuova Tabella: `annual_data_verification_emails`**

```sql
CREATE TABLE IF NOT EXISTS `annual_data_verification_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `member_type` enum('adult', 'junior') NOT NULL DEFAULT 'adult',
  `junior_member_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `sent_at` timestamp NOT NULL,
  `year` int(11) NOT NULL,
  `status` enum('sent', 'failed', 'bounced') DEFAULT 'sent',
  `error_message` text,
  PRIMARY KEY (`id`),
  KEY `idx_member_year` (`member_id`, `year`)
);
```

### Cron Job

**Nuovo file: `cron/annual_member_verification.php`**

Script da eseguire tramite crontab:

```bash
0 9 7 1 * php /percorso/easyvol/cron/annual_member_verification.php >> /var/log/easyvol/annual_verification.log 2>&1
```

Il cron job:
1. Verifica se per l'anno corrente sono già state inviate le email (evita duplicati)
2. Recupera tutti i soci maggiorenni attivi con email
3. Recupera tutti i soci minorenni attivi con email dei genitori/tutori
4. Invia email personalizzate con tutti i dati
5. Registra ogni invio nella tabella `annual_data_verification_emails`
6. Gestisce gli errori e li registra nel log

---

## Installazione

### 1. Aggiornamento Database

Eseguire lo script di migrazione:

```bash
mysql -u username -p nome_database < database_migration_notifications.sql
```

Oppure eseguire manualmente le query nel file `database_migration_notifications.sql`.

### 2. Configurazione Cron Jobs

Modificare il crontab per aggiungere il nuovo cron job:

```bash
crontab -e
```

Aggiungere:

```bash
# EasyVol - Verifica annuale dati soci (7 gennaio ore 9:00)
0 9 7 1 * php /var/www/easyvol/cron/annual_member_verification.php >> /var/log/easyvol/annual_verification.log 2>&1
```

Per il cron job scheduler_alerts (già esistente ma aggiornato):

```bash
# EasyVol - Scheduler alerts (giornaliero ore 8:00)
0 8 * * * php /var/www/easyvol/cron/scheduler_alerts.php >> /var/log/easyvol/scheduler_alerts.log 2>&1
```

### 3. Configurazione Email

Assicurarsi che la configurazione email in `config/config.php` sia corretta:

```php
'email' => [
    'enabled' => true,
    'method' => 'smtp',
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => 587,
    'smtp_username' => 'your_email@example.com',
    'smtp_password' => 'your_password',
    'smtp_encryption' => 'tls',
    'from_email' => 'noreply@example.com',
    'from_name' => 'Nome Associazione',
],
'association' => [
    'name' => 'Nome Associazione',
    'email' => 'info@associazione.it',
],
```

### 4. Verifica Permessi

Assicurarsi che la directory log esista e abbia i permessi corretti:

```bash
sudo mkdir -p /var/log/easyvol
sudo chown www-data:www-data /var/log/easyvol
sudo chmod 755 /var/log/easyvol
```

---

## Utilizzo

### Gestione Destinatari Scadenzario

1. Andare in **Scadenzario** → **Nuova Scadenza** (o modifica esistente)
2. Compilare i campi standard (titolo, data, priorità, ecc.)
3. Nella sezione **Destinatari Email Promemoria**:
   - Selezionare utenti dalla lista "Utenti Sistema" (tenere premuto Ctrl/Cmd per selezione multipla)
   - Selezionare soci dalla lista "Soci" (tenere premuto Ctrl/Cmd per selezione multipla)
   - Inserire email esterne nel campo "Email Esterne" (separate da virgola)
4. Salvare la scadenza

I destinatari riceveranno automaticamente un'email X giorni prima della scadenza (dove X è il valore "Promemoria giorni prima").

### Gestione Partecipanti Riunione

1. Creare una riunione in **Riunioni** → **Nuova Riunione**
2. Dopo aver salvato la riunione, cliccare su **Gestisci Partecipanti** (oppure andare direttamente a `meeting_participants.php?id=ID_RIUNIONE`)
3. **Aggiungere Partecipanti**:
   - Usare "Aggiungi tutti i soci attivi" per un'aggiunta massiva
   - Oppure aggiungere singolarmente selezionando tipo e socio
4. **Inviare Convocazioni**:
   - Dopo aver aggiunto i partecipanti, cliccare "Invia Convocazioni"
   - Verrà inviata un'email a tutti i partecipanti con un indirizzo email
5. **Tracciare Presenze** (il giorno della riunione):
   - Usare il pulsante di modifica nella lista partecipanti
   - Selezionare lo stato: Presente, Assente, o Delegato
   - Se Delegato, specificare a chi è stata data la delega
   - Salvare

### Verifica Annuale Dati

Il processo è completamente automatico:
1. Il 7 gennaio alle 9:00, il cron job si avvia automaticamente
2. Vengono inviate email a tutti i soci attivi
3. I soci ricevono l'email con i propri dati
4. Se i dati sono corretti: nessuna azione richiesta
5. Se ci sono variazioni: il socio risponde all'email dell'associazione specificando le modifiche

**Monitoraggio:**
- Log: `/var/log/easyvol/annual_verification.log`
- Database: tabella `annual_data_verification_emails` per verificare invii

**Test Manuale:**
```bash
php /var/www/easyvol/cron/annual_member_verification.php
```

---

## Note Tecniche

### Sicurezza

- Tutti i form utilizzano token CSRF per prevenire attacchi
- Le email vengono sanificate prima dell'invio
- Gli input utente vengono validati e sanificati
- Le query SQL utilizzano prepared statements

### Performance

- Il cron di verifica annuale include un delay di 0.1s tra un invio e l'altro per non sovraccaricare il server email
- Il processamento avviene in batch per ottimizzare le query al database
- La coda email esistente (`email_queue`) può essere utilizzata per invii asincroni

### Compatibilità

- PHP 8.3+
- MySQL 5.6+ o MySQL 8.x o MariaDB 10.3+
- PHPMailer 7.0+
- Bootstrap 5.3+

### Estensioni Future

Possibili miglioramenti:
- Template email personalizzabili dall'interfaccia
- Notifiche Telegram per conferme presenze riunioni
- Report statistici su partecipazione riunioni
- Integrazione calendario (iCal) per le convocazioni
- SMS per soci senza email
- Firma digitale per verbali

---

## Supporto

Per problemi o domande:
1. Controllare i log in `/var/log/easyvol/`
2. Verificare la configurazione email in `config/config.php`
3. Testare manualmente i cron job per identificare errori
4. Consultare la documentazione principale in `README.md`
5. Aprire una issue su GitHub

## Changelog

**Versione 1.0 - 2025-12-06**
- Implementazione destinatari email scadenzario
- Implementazione gestione partecipanti riunioni con convocazioni
- Implementazione verifica annuale dati soci
- Creazione interfacce utente
- Aggiornamento documentazione
