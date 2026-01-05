# EasyVol - Sistema Gestionale per Associazioni di Volontariato

Sistema gestionale completo per associazioni di volontariato di protezione civile - PHP 8.4 + MySQL

## 📋 Descrizione

EasyVol è un sistema di gestione completo progettato specificamente per le associazioni di volontariato di protezione civile. Fornisce tutti gli strumenti necessari per gestire soci, mezzi, magazzino, eventi, formazione, documenti e molto altro.

## ✨ Caratteristiche Principali

### Gestione Completa
- **Gestione Soci**: Anagrafica completa dei soci maggiorenni con tutti i dati richiesti
- **Gestione Cadetti**: Gestione soci minorenni con dati tutori
- **Registrazione Pubblica**: Modulo di registrazione online con generazione PDF automatica
- **Gestione Utenti**: Sistema di permessi granulari per modulo e azione
- **Log Dettagliati**: Tracciamento completo di tutte le attività

### Moduli Operativi
- **Riunioni e Assemblee**: Gestione completa con verbali e votazioni
- **Gestione Mezzi**: Veicoli, natanti, rimorchi con manutenzioni e scadenze
- **Magazzino**: Inventario, DPI, movimenti con QR code e barcode
- **Strutture**: Gestione sedi e strutture dell'associazione con mappe GPS
- **Formazione**: Corsi, attestati, presenze, scadenze
- **Eventi/Interventi**: Gestione emergenze, esercitazioni, attività
- **Scadenzario**: Promemoria automatici con notifiche email
- **Centrale Operativa**: Gestione radio e risorse in tempo reale
- **Documenti**: Archivio centralizzato con ricerca avanzata

### Funzionalità Avanzate
- **Dashboard Interattiva**: Statistiche, notifiche, scadenze
- **Generazione PDF**: Report, tesserini, verbali, documenti
- **Sistema Email**: Template personalizzabili, code automatica
- **Notifiche Telegram**: Integrazione bot Telegram (opzionale)
- **Backup Automatici**: Sistema di backup configurabile
- **Report e Statistiche**: Analisi dettagliate e esportazioni

## 🚀 Installazione Rapida

### Requisiti
- PHP 8.4 o superiore
- MySQL 5.6+ o MySQL 8.x o MariaDB 10.3+
- Web server (Apache, Nginx)
- Estensioni PHP: PDO, mbstring, json, gd, zip

### Installazione

1. **Download e Estrazione**
   ```bash
   # Scarica il repository
   git clone https://github.com/cris-deitos/EasyVol.git
   
   # Oppure scarica il file ZIP e estrailo
   ```

2. **Installa le Dipendenze (OBBLIGATORIO)**
   ```bash
   # Entra nella directory del progetto
   cd EasyVol
   
   # Installa le dipendenze con Composer
   composer install --no-dev --optimize-autoloader
   ```
   
   **Nota importante**: Questo passaggio è **obbligatorio** per il corretto funzionamento dell'applicazione. 
   Le librerie come PHPMailer, mPDF, PHPSpreadsheet e altre sono gestite tramite Composer e non sono incluse nel repository.
   
   Se non hai Composer installato, scaricalo da [getcomposer.org](https://getcomposer.org/download/)

3. **Upload via FTP**
   - Carica tutti i file nella directory del tuo hosting (inclusa la cartella `vendor/` generata da Composer)
   - Assicurati che la cartella `uploads/` sia scrivibile (chmod 755 o 777)
   - Assicurati che la cartella `config/` sia scrivibile per la configurazione iniziale

4. **Installazione Web**
   - Vai su `http://tuosito.com/public/install.php`
   - Segui la procedura guidata:
     * Passo 1: Configura database MySQL
     * Passo 2: Inserisci dati associazione e amministratore
     * Passo 3: Installazione completata!

5. **Primo Accesso**
   - Vai su `http://tuosito.com/public/login.php`
   - Accedi con le credenziali amministratore create durante l'installazione

## 📁 Struttura Progetto

```
EasyVol/
├── config/                      # Configurazione
│   ├── config.sample.php       # Configurazione di esempio
│   └── config.php              # Configurazione (generato automaticamente)
├── public/                      # File pubblici accessibili via web
│   ├── install.php             # Installazione guidata
│   ├── login.php               # Pagina login
│   ├── logout.php              # Logout
│   ├── dashboard.php           # Dashboard principale
│   ├── members.php             # Gestione soci
│   ├── junior_members.php      # Gestione cadetti
│   ├── register.php            # Registrazione pubblica
│   └── ...                     # Altri moduli
├── src/                         # Codice sorgente
│   ├── Autoloader.php          # PSR-4 autoloader
│   ├── App.php                 # Classe applicazione principale
│   ├── Database.php            # Gestione database
│   ├── Controllers/            # Controller MVC
│   ├── Models/                 # Model MVC
│   ├── Views/                  # View MVC
│   │   └── includes/           # Template riutilizzabili
│   ├── Middleware/             # Middleware (auth, permissions)
│   └── Utils/                  # Utilità (PDF, Email, etc.)
├── assets/                      # Risorse statiche
│   ├── css/                    # Fogli di stile
│   ├── js/                     # JavaScript
│   └── images/                 # Immagini
├── uploads/                     # File caricati
│   ├── members/                # Foto e documenti soci
│   ├── documents/              # Documenti generali
│   ├── vehicles/               # Documenti mezzi
│   └── warehouse/              # Documenti magazzino
├── vendor/                      # Librerie esterne (incluse)
├── database_schema.sql         # Schema database completo
└── README.md                   # Questo file
```

## 🔧 Configurazione

### Database
Il file `config/config.php` viene generato automaticamente durante l'installazione. Se necessario, puoi modificarlo manualmente:

```php
'database' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'easyvol',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
],
```

### Email
Configura l'invio email in `config/config.php`:

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
    'from_name' => 'EasyVol',
],
```

### Telegram (Opzionale)
Per abilitare le notifiche Telegram:

```php
'telegram' => [
    'enabled' => true,
    'bot_token' => 'YOUR_BOT_TOKEN',
    'chat_id' => 'YOUR_CHAT_ID',
],
```

## 📚 Moduli Principali

### A) Gestione Utenti
- Ruoli personalizzabili (admin, segreteria, logistica, volontari, direttivo)
- Permessi granulari per modulo e azione (view, create, edit, delete, report)
- Ogni utente può essere collegato a un socio

### B) Log e Attività
- Tracciamento ultra-dettagliato di tutte le operazioni
- Registrazione di IP, user agent, data/ora
- Ricerca avanzata per data, utente, modulo, azione

### C) Gestione Soci (Maggiorenni)
Scheda multi-tab completa:
- **Dati generali**: Matricola, tipo socio, stato, dati anagrafici
- **Indirizzi**: Residenza, domicilio
- **Contatti**: Telefoni, email, PEC
- **Titoli di studio**
- **Datore di lavoro**
- **Patenti**: Auto, nautica, patentini
- **Corsi**: Base protezione civile, DGR 1190/2019
- **Mansioni**: Ruoli operativi
- **Disponibilità**: Territoriale (comunale, provinciale, regionale, nazionale, internazionale)
- **Quote sociali**: Anni pagati
- **Salute**: Diete, allergie, intolleranze, patologie
- **Provvedimenti**: Sanzioni e stato socio
- **Note e allegati**

### D) Gestione Cadetti (Minorenni)
Come i soci maggiorenni ma con:
- Dati anagrafici genitori/tutori
- Senza patenti, titoli di studio, corsi professionali, mansioni operative

### E) Registrazione Pubblica
- Modulo online con CAPTCHA
- Validazione completa dei dati
- Generazione automatica PDF con codice univoco
- Invio email a richiedente e associazione
- Clausole da accettare (diverse per maggiorenni e minorenni)
- Sistema di approvazione interno

### F-H) Gestione Domande e Quote
- Pagina interna per approvazione domande iscrizione
- Sistema pubblico per upload ricevute pagamento quote
- Verifica e approvazione pagamenti

### I) Riunioni e Assemblee
- Assemblee ordinarie/straordinarie
- Consigli direttivi
- Ordine del giorno con votazioni
- Generazione verbali in PDF
- Gestione partecipanti e presenze
- Allegati documenti

### L) Gestione Mezzi
- Veicoli, natanti, rimorchi
- Scadenze (revisioni, assicurazioni)
- Manutenzioni ordinarie/straordinarie
- Guasti, riparazioni, incidenti
- Alert automatici via email
- Documenti digitalizzati

### M) Magazzino
- Inventario completo
- DPI personali assegnati
- Scorte minime con alert
- QR code e barcode
- Registro movimenti
- Richieste di acquisto

### N) Scadenzario
- Convenzioni, atti, scadenze annuali
- Alert via email programmabili
- Priorità e categorie
- Generazione documenti

### O) Formazione
- Corsi interni ed esterni
- Scadenze attestati (BLSD, AIB, radio, D.Lgs 81/08)
- Registro presenze
- Caricamento certificati PDF

### P) Eventi/Interventi
- Eventi: Emergenza, esercitazione, attività
- Interventi multipli per evento
- Assegnazione volontari e mezzi
- Report dettagliati
- Generazione PDF
- Email automatiche

### Q) Tracciamento Volontari
- Foglio attività personale
- Ore di servizio
- Partecipazione eventi
- Report annuali per tipo attività
- Certificazioni operatività

### R) Gestione Documentale
- Archivio organizzato per categorie
- Upload multipli
- Ricerca avanzata
- Normative, manuali, procedure

### AC) Centrale Operativa
Accesso separato per operatori di centrale:
- Gestione eventi/interventi
- Rubrica radio con assegnazioni
- Rubrica volontari (solo attivi)
- Rubrica mezzi
- Rubrica magazzino

## 🔐 Sicurezza

- Password hasate con bcrypt
- Protezione CSRF
- Sanitizzazione input
- Prepared statements (protezione SQL injection)
- XSS protection
- Session sicure con HTTPOnly cookie
- Rate limiting login
- HTTPS consigliato
- Backup automatici

## 📧 Sistema Email e Notifiche

### Email
- Sistema di code per invii multipli
- Template HTML personalizzabili
- Allegati automatici (PDF, documenti)
- Invio via SMTP, sendmail o mail()
- Log degli invii

### Notifiche
- Dashboard con notifiche in tempo reale
- Badge contatori
- Email per scadenze
- Telegram (opzionale)

## 📊 Report e Statistiche

- Dashboard con KPI principali
- Report soci per stato e categoria
- Report interventi e ore volontari
- Report mezzi e utilizzo
- Report magazzino e scorte
- Esportazione Excel/PDF
- Grafici statistici

## 🎨 Personalizzazione

### Template PDF
Configura intestazioni, font, stili per:
- Verbali
- Tesserini
- Schede soci
- Libro soci annuale
- Report interventi

### Email Template
Personalizza template per:
- Benvenuto nuovi soci
- Promemoria scadenze
- Convocazioni
- Alert urgenti

## 🛠️ Sviluppo e Estensione

### Architettura
- MVC pattern
- PSR-4 autoloading
- Separazione concerns
- Database abstraction layer

### Aggiungere Nuovi Moduli
1. Creare controller in `src/Controllers/`
2. Creare model in `src/Models/`
3. Creare view in `src/Views/`
4. Aggiungere route in `public/`
5. Aggiungere permessi nel database
6. Aggiungere voci menu in sidebar

### Librerie Incluse
- Bootstrap 5.3 (UI framework)
- Bootstrap Icons
- jQuery (opzionale)
- Chart.js (grafici)
- DataTables (tabelle avanzate)
- mPDF (generazione PDF)
- PHPMailer (invio email)

## 🔄 Backup e Ripristino

### Backup Database
```bash
mysqldump -u username -p easyvol > backup.sql
```

### Backup File
```bash
tar -czf easyvol_backup.tar.gz /path/to/easyvol/
```

### Ripristino
```bash
mysql -u username -p easyvol < backup.sql
tar -xzf easyvol_backup.tar.gz
```

## 📝 Requisiti Legali

Il sistema gestisce dati sensibili. Assicurati di:
- Conformità GDPR (Regolamento UE 2016/679)
- Informativa privacy completa
- Consenso trattamento dati
- Misure di sicurezza adeguate
- Registro trattamenti
- DPO se necessario

## 🆘 Supporto e Contributi

### Documentazione
- [Wiki](https://github.com/cris-deitos/EasyVol/wiki)
- [FAQ](https://github.com/cris-deitos/EasyVol/wiki/FAQ)
- [Guide](https://github.com/cris-deitos/EasyVol/wiki/Guides)

### Segnalazione Bug
Usa il [sistema di issue](https://github.com/cris-deitos/EasyVol/issues) su GitHub

### Contributi
Le pull request sono benvenute! Vedi [CONTRIBUTING.md](CONTRIBUTING.md)

## 📄 Licenza

Questo progetto è rilasciato sotto licenza MIT. Vedi il file [LICENSE](LICENSE) per i dettagli.

## 👥 Autori

Sistema sviluppato per le esigenze delle associazioni di volontariato di protezione civile italiane.

## 🙏 Ringraziamenti

- Associazioni di volontariato che hanno contribuito con feedback
- Community open source
- Tutti i volontari che dedicano il loro tempo agli altri

---

**EasyVol** - Gestionale per il cuore del volontariato ❤️
