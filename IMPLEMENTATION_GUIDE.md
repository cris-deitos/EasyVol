# Guida all'Implementazione EasyVol

Questo documento fornisce una guida dettagliata per completare l'implementazione di tutti i moduli richiesti.

## Stato Attuale

### ✅ Completato

1. **Infrastruttura Core**
   - Autoloader PSR-4
   - Classe App principale con gestione sessioni
   - Database wrapper con PDO
   - Sistema di configurazione
   
2. **Database**
   - Schema completo con tutte le tabelle necessarie
   - Relazioni tra tabelle configurate
   - Indici per performance
   
3. **Autenticazione e Autorizzazione**
   - Sistema login completo
   - Gestione permessi granulari
   - Logging attività
   
4. **UI/UX Base**
   - Layout responsive con Bootstrap 5
   - Dashboard interattiva
   - Navbar e sidebar dinamici
   - CSS e JavaScript personalizzati
   
5. **Installazione**
   - Wizard di installazione completo
   - Creazione automatica database
   - Setup associazione e admin

### 🚧 Da Implementare

I seguenti moduli richiedono l'implementazione delle pagine PHP, controller e viste.

## Moduli da Completare

### 1. Gestione Soci (members.php)

**File da creare:**
- `public/members.php` - Lista soci con ricerca e filtri
- `public/member_view.php` - Vista dettaglio socio multi-tab
- `public/member_edit.php` - Modifica socio
- `src/Controllers/MemberController.php`
- `src/Models/Member.php`
- `src/Views/members/` - Template per le viste

**Funzionalità:**
- Lista paginata con ricerca
- Filtri per stato, tipo, matricola
- Scheda multi-tab con:
  * Dati anagrafici
  * Indirizzi (CRUD)
  * Contatti (CRUD)
  * Titoli di studio (CRUD)
  * Datore di lavoro (CRUD)
  * Patenti (CRUD)
  * Corsi (CRUD)
  * Mansioni (CRUD)
  * Disponibilità (CRUD)
  * Quote sociali (CRUD)
  * Salute (CRUD)
  * Provvedimenti (CRUD)
  * Note e allegati
- Generazione PDF (tesserini, schede, libro soci)
- Gestione foto profilo
- Storico modifiche

**Esempio Controller:**
```php
<?php
namespace EasyVol\Controllers;

class MemberController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function index($filters = []) {
        $sql = "SELECT * FROM members WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND member_status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (last_name LIKE ? OR first_name LIKE ? OR registration_number LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY last_name, first_name";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function get($id) {
        return $this->db->fetchOne("SELECT * FROM members WHERE id = ?", [$id]);
    }
    
    // Altri metodi: create, update, delete, getAddresses, etc.
}
```

### 2. Gestione Cadetti (junior_members.php)

Simile a gestione soci ma con:
- Dati tutori integrati
- Campi ridotti (no patenti, no titoli studio professionali)
- Template PDF specifici

### 3. Registrazione Pubblica (register.php)

**File da creare:**
- `public/register.php` - Form registrazione maggiorenni
- `public/register_junior.php` - Form registrazione minorenni
- `src/Utils/PdfGenerator.php` - Generazione PDF domande
- `src/Utils/EmailSender.php` - Invio email

**Funzionalità:**
- Form multi-step con validazione
- CAPTCHA (Google reCAPTCHA v2/v3)
- Checkbox clausole obbligatorie
- Generazione codice univoco
- Creazione PDF con tutti i dati
- Invio email doppio (richiedente + associazione)
- Salvataggio in `member_applications`

**Template PDF da includere:**
- Intestazione associazione
- Dati anagrafici completi
- Clausole accettate
- Firma digitale/spazio per firma
- Codice univoco domanda

### 4. Gestione Domande Iscrizione (applications.php)

**Funzionalità:**
- Lista domande pending/approved/rejected
- Vista dettaglio domanda con PDF
- Approva/Rifiuta
- Se approvata: crea record in members/junior_members
- Assegnazione matricola automatica (numero crescente)
- Notifica email al richiedente

### 5. Gestione Quote (fee_payments.php)

**Pagina Pubblica (public/pay_fee.php):**
- Form con matricola + cognome
- Validazione match database
- Upload ricevuta
- CAPTCHA
- Invio in `fee_payment_requests`

**Pagina Interna:**
- Lista richieste pending
- Approva/Rifiuta
- Se approvata: crea record in `member_fees`

### 6. Riunioni e Assemblee (meetings.php)

**Funzionalità:**
- CRUD riunioni
- Gestione partecipanti
- Ordine del giorno
- Votazioni
- Editor HTML per verbali
- Generazione PDF verbali
- Allegati documenti

**Editor HTML consigliato:**
- TinyMCE o CKEditor (incluso via CDN)

### 7. Gestione Mezzi (vehicles.php)

**Funzionalità:**
- CRUD mezzi/natanti/rimorchi
- Manutenzioni (CRUD)
- Scadenze con alert
- Documenti (upload/gestione)
- Report utilizzo
- Cron per email scadenze

**Cron Job (cron_vehicle_alerts.php):**
```php
// Da eseguire giornalmente
// Controlla scadenze entro 30 giorni
// Invia email promemoria
```

### 8. Magazzino (warehouse.php)

**Funzionalità:**
- CRUD articoli inventario
- Movimenti (carico/scarico)
- DPI assegnati a volontari
- Scorte minime con alert
- Generazione QR code/barcode
- Report giacenze
- Manutenzioni attrezzature

**Librerie consigliate:**
- QR Code: `phpqrcode` o `endroid/qr-code`
- Barcode: `picqer/php-barcode-generator`

### 9. Scadenzario (scheduler.php)

**Funzionalità:**
- CRUD scadenze
- Priorità e categorie
- Alert email automatici
- Vista calendario
- Cron per notifiche

### 10. Formazione (training.php)

**Funzionalità:**
- CRUD corsi
- Iscrizioni volontari
- Registro presenze
- Attestati (upload PDF)
- Scadenze attestati con alert
- Report presenze

### 11. Eventi/Interventi (events.php)

**Funzionalità:**
- CRUD eventi (emergenza/esercitazione/attività)
- Interventi multipli per evento
- Assegnazione volontari
- Assegnazione mezzi
- Tracciamento ore
- Report dettagliati
- Email automatiche all'apertura

### 12. Centrale Operativa (operations_center.php)

**Login separato (operations_login.php):**
- Verifica ruolo operatore centrale

**Dashboard operativa:**
- Vista eventi in corso
- Rubrica radio con assegnazioni
- Scan seriale per rientro radio veloce
- Rubrica volontari (solo attivi)
- Rubrica mezzi disponibili
- Rubrica magazzino

### 13. Gestione Documenti (documents.php)

**Funzionalità:**
- Upload multiplo
- Categorie (normative, manuali, procedure, progetti)
- Ricerca full-text
- Tag
- Versioning
- Permessi visualizzazione

### 14. Gestione Utenti (users.php)

**Funzionalità:**
- CRUD utenti
- CRUD ruoli
- Gestione permessi per ruolo
- Assegnazione ruoli multipli
- Reset password
- Attivazione/disattivazione

### 15. Report (reports.php)

**Report disponibili:**
- Libro soci annuale
- Soci per categoria
- Interventi per tipo e periodo
- Ore volontari
- Utilizzo mezzi
- Giacenze magazzino
- Statistiche formazione
- Export Excel/PDF

### 16. Impostazioni (settings.php)

**Sezioni:**
- Dati associazione
- Logo
- Configurazione email
- Configurazione Telegram
- Template email
- Template PDF
- Backup database
- Import/Export dati

## Utility Classes da Implementare

### PdfGenerator.php

```php
<?php
namespace EasyVol\Utils;

use Mpdf\Mpdf;

class PdfGenerator {
    private $config;
    private $mpdf;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function generate($html, $filename, $output = 'D') {
        $this->mpdf = new Mpdf([
            'tempDir' => sys_get_temp_dir(),
            'default_font' => $this->config['pdf']['default_font'],
            'default_font_size' => $this->config['pdf']['default_font_size'],
            'margin_top' => $this->config['pdf']['margin_top'],
            'margin_bottom' => $this->config['pdf']['margin_bottom'],
            'margin_left' => $this->config['pdf']['margin_left'],
            'margin_right' => $this->config['pdf']['margin_right'],
        ]);
        
        $this->mpdf->WriteHTML($html);
        return $this->mpdf->Output($filename, $output);
    }
    
    public function generateFromTemplate($template, $data, $filename) {
        // Carica template HTML
        // Sostituisci placeholder con dati
        // Genera PDF
    }
}
```

### EmailSender.php

```php
<?php
namespace EasyVol\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender {
    private $config;
    private $mailer;
    
    public function __construct($config) {
        $this->config = $config;
        $this->initMailer();
    }
    
    private function initMailer() {
        $this->mailer = new PHPMailer(true);
        
        if ($this->config['email']['method'] === 'smtp') {
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['email']['smtp_host'];
            $this->mailer->Port = $this->config['email']['smtp_port'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['email']['smtp_username'];
            $this->mailer->Password = $this->config['email']['smtp_password'];
            $this->mailer->SMTPSecure = $this->config['email']['smtp_encryption'];
        }
        
        $this->mailer->setFrom(
            $this->config['email']['from_email'],
            $this->config['email']['from_name']
        );
    }
    
    public function send($to, $subject, $body, $attachments = []) {
        try {
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;
            
            foreach ($attachments as $attachment) {
                $this->mailer->addAttachment($attachment);
            }
            
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email send failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendFromTemplate($to, $templateName, $data) {
        // Carica template da database
        // Sostituisci placeholder
        // Invia email
    }
    
    public function queue($to, $subject, $body, $attachments = []) {
        // Inserisci in email_queue per invio asincrono
    }
}
```

## Librerie Esterne da Includere

### Via Composer (opzionale) o Download Manuale

1. **mPDF** - Generazione PDF
   ```bash
   composer require mpdf/mpdf
   ```
   Oppure download da: https://github.com/mpdf/mpdf/releases

2. **PHPMailer** - Invio email
   ```bash
   composer require phpmailer/phpmailer
   ```
   Oppure download da: https://github.com/PHPMailer/PHPMailer/releases

3. **PHPSpreadsheet** - Export Excel
   ```bash
   composer require phpoffice/phpspreadsheet
   ```

4. **QR Code Generator**
   ```bash
   composer require endroid/qr-code
   ```

5. **Google reCAPTCHA**
   ```bash
   composer require google/recaptcha
   ```

### Via CDN (già incluso)

- Bootstrap 5.3
- Bootstrap Icons
- jQuery (se necessario)
- Chart.js
- DataTables
- TinyMCE/CKEditor

## Cron Jobs da Configurare

Crea file in `/cron/` per operazioni automatiche:

1. **cron_email_queue.php** - Processa code email
2. **cron_vehicle_alerts.php** - Alert scadenze mezzi
3. **cron_training_alerts.php** - Alert scadenze attestati
4. **cron_scheduler_alerts.php** - Alert scadenzario
5. **cron_backup.php** - Backup automatico database
6. **cron_warehouse_alerts.php** - Alert scorte minime

Configurazione crontab:
```bash
# Ogni 5 minuti - Code email
*/5 * * * * php /path/to/easyvol/cron/cron_email_queue.php

# Ogni giorno alle 8:00 - Alert scadenze
0 8 * * * php /path/to/easyvol/cron/cron_vehicle_alerts.php
0 8 * * * php /path/to/easyvol/cron/cron_training_alerts.php
0 8 * * * php /path/to/easyvol/cron/cron_scheduler_alerts.php

# Ogni giorno alle 2:00 - Backup
0 2 * * * php /path/to/easyvol/cron/cron_backup.php

# Ogni lunedì alle 9:00 - Alert scorte
0 9 * * 1 php /path/to/easyvol/cron/cron_warehouse_alerts.php
```

## Sicurezza

### Checklist Sicurezza

- [ ] Validazione input server-side
- [ ] Sanitizzazione output (htmlspecialchars)
- [ ] Prepared statements (✅ già implementato)
- [ ] CSRF tokens per form
- [ ] Rate limiting login
- [ ] Password policy forte
- [ ] HTTPS obbligatorio in produzione
- [ ] File upload: validazione tipo e dimensione
- [ ] Permessi file corretti (uploads 755, config 644)
- [ ] Error reporting disabilitato in produzione
- [ ] Log errori in file separato
- [ ] Backup regolari
- [ ] Aggiornamenti sicurezza PHP/MySQL

### Esempio CSRF Protection

```php
// Generazione token
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validazione token
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// In form
echo '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';

// In processing
if (!validateCsrfToken($_POST['csrf_token'])) {
    die('Invalid CSRF token');
}
```

## Testing

### Test Manuali Essenziali

1. **Installazione**
   - [ ] Wizard completamento
   - [ ] Database creato
   - [ ] Admin login funzionante

2. **Autenticazione**
   - [ ] Login corretto
   - [ ] Login errato bloccato
   - [ ] Logout
   - [ ] Permessi verificati

3. **CRUD Base**
   - [ ] Crea record
   - [ ] Leggi record
   - [ ] Modifica record
   - [ ] Elimina record

4. **Upload File**
   - [ ] Upload funzionante
   - [ ] Validazione tipo
   - [ ] Validazione dimensione
   - [ ] Visualizzazione

5. **PDF Generation**
   - [ ] PDF generato correttamente
   - [ ] Layout corretto
   - [ ] Dati corretti

6. **Email**
   - [ ] Invio funzionante
   - [ ] Template corretto
   - [ ] Allegati

## Performance

### Ottimizzazioni

1. **Database**
   - Indici su colonne ricerca frequente
   - Query ottimizzate
   - Paginazione risultati

2. **Cache**
   - Cache statistiche dashboard
   - Cache template compilati
   - Opcode cache (OPcache)

3. **Assets**
   - Minify CSS/JS
   - Compressione gzip
   - CDN per librerie

## Deployment Checklist

- [ ] PHP 8.4+ installato
- [ ] MySQL configurato
- [ ] Permessi cartelle corretti
- [ ] Config email funzionante
- [ ] HTTPS configurato
- [ ] Cron jobs attivi
- [ ] Backup automatico attivo
- [ ] Error logging attivo
- [ ] Performance ottimizzata
- [ ] Test completi eseguiti
- [ ] Documentazione aggiornata
- [ ] Formazione utenti

## Supporto

Per domande o problemi:
- GitHub Issues: https://github.com/cris-deitos/EasyVol/issues
- Email: support@easyvol.example.com
- Documentazione: Wiki del progetto

---

**Ultima revisione**: Dicembre 2024
