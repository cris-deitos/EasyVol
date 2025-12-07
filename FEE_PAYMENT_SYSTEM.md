# Sistema Gestione Quote Associative

## Panoramica

Il sistema di gestione quote associative consente ai soci di caricare autonomamente le ricevute di pagamento delle quote annuali tramite una pagina pubblica, e agli amministratori di gestire e approvare queste richieste tramite un'interfaccia interna.

## Funzionalità Implementate

### G) Pagina Pubblica - Caricamento Ricevute (`/public/pay_fee.php`)

Pagina accessibile pubblicamente senza autenticazione per il caricamento delle ricevute di pagamento.

**Caratteristiche:**
- Processo a 2 step per maggiore sicurezza
- Step 1: Verifica identità socio (Matricola + Cognome)
- Step 2: Caricamento ricevuta e dettagli pagamento

**Campi richiesti:**
- Matricola socio (obbligatorio)
- Cognome socio (obbligatorio)
- Data pagamento (obbligatorio)
- Anno di riferimento quota (obbligatorio)
- File ricevuta (PDF, JPG, PNG - max 5MB)
- Verifica CAPTCHA (se abilitato)

**Sicurezza:**
- Protezione CSRF
- Verifica reCAPTCHA (opzionale ma raccomandato)
- Validazione match matricola/cognome nel database
- Upload file con controlli tipo MIME e dimensione
- File salvati in directory protetta

**Notifiche Email:**
- Email di conferma ricezione al socio
- Email di notifica nuova richiesta all'associazione

### H) Pagina Interna - Gestione Richieste (`/public/fee_payments.php`)

Pagina interna riservata agli utenti autenticati con permessi di modifica soci.

**Caratteristiche:**
- Dashboard statistiche (In Sospeso, Approvate, Rifiutate)
- Lista paginata richieste con filtri
- Visualizzazione ricevute caricate
- Approvazione/rifiuto richieste
- Link diretto alla scheda socio

**Filtri disponibili:**
- Stato richiesta (Pending, Approved, Rejected)
- Anno riferimento
- Ricerca per matricola o cognome

**Azioni:**
- **Approva**: Crea record in `member_fees`, segna come verificato, invia email conferma al socio
- **Rifiuta**: Segna richiesta come rifiutata, invia email notifica al socio

## Tabelle Database

### `fee_payment_requests`
Memorizza tutte le richieste di pagamento caricate dai soci.

```sql
CREATE TABLE IF NOT EXISTS `fee_payment_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_number` varchar(50),
  `last_name` varchar(100),
  `payment_year` int(11),
  `payment_date` date,
  `receipt_file` varchar(255),
  `status` enum('pending', 'approved', 'rejected') DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL,
  `processed_by` int(11),
  PRIMARY KEY (`id`)
)
```

### `member_fees`
Memorizza i pagamenti quote verificati e approvati.

```sql
CREATE TABLE IF NOT EXISTS `member_fees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `payment_date` date,
  `amount` decimal(10,2),
  `receipt_file` varchar(255),
  `verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11),
  `verified_at` timestamp NULL,
  PRIMARY KEY (`id`)
)
```

## File Implementati

### Controller
- `/src/Controllers/FeePaymentController.php` - Logica business completa

**Metodi principali:**
- `verifyMember()` - Verifica match matricola/cognome
- `createPaymentRequest()` - Crea nuova richiesta
- `getPaymentRequests()` - Lista richieste con filtri
- `approvePaymentRequest()` - Approva richiesta
- `rejectPaymentRequest()` - Rifiuta richiesta
- `getStatistics()` - Statistiche aggregate
- `sendSubmissionEmails()` - Email conferma caricamento
- `sendApprovalEmail()` - Email approvazione
- `sendRejectionEmail()` - Email rifiuto

### Pagine Pubbliche
- `/public/pay_fee.php` - Interfaccia pubblica caricamento ricevute
- `/public/fee_payments.php` - Interfaccia interna gestione richieste

### Configurazione
- `/config/config.sample.php` - Aggiunta configurazione reCAPTCHA

### Upload
- `/uploads/fee_receipts/` - Directory ricevute caricate
- `/uploads/fee_receipts/.htaccess` - Protezione accesso diretto

## Configurazione

### reCAPTCHA (Opzionale ma Raccomandato)

Per abilitare la protezione reCAPTCHA:

1. Registra il tuo sito su [Google reCAPTCHA](https://www.google.com/recaptcha/admin)
2. Aggiungi le chiavi in `config/config.php`:

```php
'recaptcha' => [
    'enabled' => true,
    'site_key' => 'TUA_SITE_KEY',
    'secret_key' => 'TUA_SECRET_KEY',
],
```

### Email

Assicurati che la configurazione email sia correttamente impostata in `config/config.php`:

```php
'email' => [
    'enabled' => true,
    'method' => 'smtp',
    'smtp_host' => 'smtp.tuoserver.com',
    'smtp_port' => 587,
    'smtp_username' => 'user@example.com',
    'smtp_password' => 'password',
    'smtp_encryption' => 'tls',
    'from_email' => 'noreply@example.com',
    'from_name' => 'Nome Associazione',
],
```

### Permessi

La gestione richieste richiede il permesso `members:edit`. Gli utenti devono avere questo permesso per accedere a `/public/fee_payments.php`.

## Workflow Completo

1. **Socio carica ricevuta**
   - Accede a `pay_fee.php`
   - Inserisce matricola e cognome
   - Sistema verifica identità
   - Socio carica ricevuta con dettagli pagamento
   - Sistema salva in `fee_payment_requests` con status 'pending'
   - Invio email conferma a socio e associazione

2. **Amministratore verifica**
   - Accede a `fee_payments.php`
   - Visualizza richieste in sospeso
   - Scarica e verifica ricevuta
   - Approva o rifiuta richiesta

3. **Approvazione**
   - Sistema crea record in `member_fees`
   - Marca pagamento come verificato
   - Aggiorna status richiesta a 'approved'
   - Invia email conferma al socio

4. **Rifiuto**
   - Aggiorna status richiesta a 'rejected'
   - Invia email notifica al socio

## Sicurezza

- ✅ Protezione CSRF su tutti i form
- ✅ Validazione input server-side
- ✅ Controllo MIME type file upload
- ✅ Limite dimensione file (5MB)
- ✅ reCAPTCHA opzionale
- ✅ Directory upload protetta con .htaccess
- ✅ Autenticazione richiesta per gestione
- ✅ Verifica permessi utente
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS prevention (htmlspecialchars)

## Navigazione

Il link "Quote Associative" è stato aggiunto alla sidebar nella sezione principale, subito dopo "Domande Iscrizione", ed è visibile agli utenti con permesso `members:edit`.

## Note di Sviluppo

- Utilizza classi esistenti: `FileUploader`, `EmailSender`, `CsrfProtection`
- Segue il pattern MVC del progetto
- Compatibile con MySQL 5.6+ e MySQL 8.x
- PHP 8.3+ richiesto
- Bootstrap 5 per l'interfaccia
- Design responsive mobile-friendly

## Future Migliorazioni

- [ ] Aggiungere campo importo quota in fase di caricamento
- [ ] Dashboard widget per richieste in sospeso
- [ ] Esportazione lista richieste in Excel
- [ ] Notifiche Telegram (se abilitato)
- [ ] Storico pagamenti nella scheda socio
- [ ] Report annuale quote incassate
- [ ] Promemoria automatico quote non pagate
