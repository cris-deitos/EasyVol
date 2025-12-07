# Guida Rapida EasyVol

Questa guida ti aiuterà a installare e configurare EasyVol in 5 minuti.

## 📦 Installazione Veloce

### Requisiti Minimi
- PHP 8.4 o superiore
- MySQL 5.6+ o MySQL 8.x o MariaDB 10.3+
- Web server (Apache o Nginx)
- 50MB spazio disco

### Passo 1: Download

**Opzione A - Da GitHub:**
```bash
git clone https://github.com/cris-deitos/EasyVol.git
cd EasyVol
```

**Opzione B - Download ZIP:**
1. Vai su https://github.com/cris-deitos/EasyVol
2. Clicca su "Code" > "Download ZIP"
3. Estrai il file ZIP

### Passo 2: Installa Dipendenze (OBBLIGATORIO)

**⚠️ IMPORTANTE**: Prima di procedere con l'upload, devi installare le dipendenze PHP:

```bash
# Entra nella directory del progetto
cd EasyVol

# Installa le dipendenze con Composer
composer install --no-dev --optimize-autoloader
```

**Nota**: Se non hai Composer installato:
- **Linux/Mac**: `curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer`
- **Windows**: Scarica l'installer da [getcomposer.org](https://getcomposer.org/download/)

Questo comando installerà:
- PHPMailer (invio email) ✉️
- mPDF (generazione PDF) 📄
- PHPSpreadsheet (Excel) 📊
- QR Code generator (badge) 🔲
- E altre librerie necessarie

**❌ Senza questo passaggio, l'applicazione NON funzionerà!**

### Passo 3: Upload

**Via FTP:**
1. Connettiti al tuo hosting via FTP
2. Carica tutti i file nella directory web (es. `public_html/`) **inclusa la cartella `vendor/`**
3. Assicurati che la cartella `uploads/` sia scrivibile (chmod 755 o 777)

**Oppure copia in locale:**
```bash
# Apache
sudo cp -r EasyVol /var/www/html/easyvol

# Nginx
sudo cp -r EasyVol /usr/share/nginx/html/easyvol

# Permessi
sudo chown -R www-data:www-data /var/www/html/easyvol
sudo chmod -R 755 /var/www/html/easyvol
sudo chmod -R 777 /var/www/html/easyvol/uploads
```

### Passo 4: Installazione Web

1. Apri il browser e vai su: `http://tuosito.com/public/install.php`

2. **Schermata 1 - Configurazione Database**
   - Host: `localhost` (di solito)
   - Porta: `3306` (di solito)
   - Nome Database: `easyvol` (o quello che preferisci)
   - Username: Il tuo username MySQL
   - Password: La tua password MySQL
   - Clicca "Avanti"

3. **Schermata 2 - Dati Associazione e Admin**
   
   **Dati Associazione:**
   - Ragione Sociale: Nome della tua associazione
   - Email: email@associazione.it
   - PEC: pec@associazione.it
   - Codice Fiscale: CF dell'associazione
   - Indirizzo completo (Via, Civico, Comune, Provincia, CAP)
   
   **Dati Amministratore:**
   - Nome Completo: Il tuo nome
   - Username: admin (o quello che preferisci)
   - Email: tua@email.it
   - Password: Scegli una password sicura (min 8 caratteri)
   - Conferma Password
   
   Clicca "Completa Installazione"

4. **Installazione Completata!**
   - Il sistema crea automaticamente il database
   - Importa tutte le tabelle necessarie
   - Crea l'utente amministratore
   - Configura i permessi

### Passo 5: Primo Accesso

1. Vai su: `http://tuosito.com/public/login.php`
2. Inserisci username e password dell'admin
3. Benvenuto in EasyVol!

## 🎯 Primi Passi

### 1. Esplora la Dashboard
- Visualizza statistiche
- Controlla notifiche
- Familiarizza con il menu

### 2. Configura il Sistema
Vai su **Impostazioni** per configurare:
- Logo dell'associazione
- Email SMTP per invio automatico
- Template documenti PDF
- (opzionale) Notifiche Telegram

### 3. Crea Ruoli e Utenti
1. Vai su **Amministrazione** > **Utenti**
2. Crea ruoli personalizzati (segreteria, logistica, volontari)
3. Assegna permessi ai ruoli
4. Crea nuovi utenti

### 4. Inizia a Usare i Moduli

**Per Gestire Soci:**
1. Vai su **Soci**
2. Clicca "Nuovo Socio"
3. Compila i dati
4. Aggiungi indirizzi, contatti, ecc.

**Per Registrazione Pubblica:**
1. Condividi il link: `http://tuosito.com/public/register.php`
2. I nuovi soci compilano il form
3. Sistema genera PDF automatico
4. Approva le domande dalla pagina "Domande Iscrizione"

**Per Eventi/Interventi:**
1. Vai su **Eventi/Interventi**
2. Crea nuovo evento
3. Aggiungi interventi
4. Assegna volontari e mezzi

## 🔧 Configurazione Email

Per abilitare l'invio automatico email:

1. Modifica `/config/config.php`:

```php
'email' => [
    'enabled' => true,
    'method' => 'smtp',
    'smtp_host' => 'smtp.gmail.com',  // Es. Gmail
    'smtp_port' => 587,
    'smtp_username' => 'tua@email.com',
    'smtp_password' => 'tua-password-app',  // Usa password app per Gmail
    'smtp_encryption' => 'tls',
    'from_email' => 'noreply@associazione.it',
    'from_name' => 'Associazione Volontari',
],
```

### Gmail
- Abilita "Accesso app meno sicure" o crea una "Password per app"
- Host: `smtp.gmail.com`, Porta: `587`, TLS

### Aruba
- Host: `smtps.aruba.it`, Porta: `465`, SSL

### Libero
- Host: `smtp.libero.it`, Porta: `587`, TLS

## 🔐 Sicurezza in Produzione

Prima di andare in produzione:

1. **Abilita HTTPS**
   ```bash
   # Installa certificato SSL (es. Let's Encrypt)
   sudo certbot --apache  # o --nginx
   ```

2. **Configura .htaccess**
   - Decommentare redirect HTTPS in `/public/.htaccess`

3. **Proteggi config.php**
   ```bash
   chmod 640 /path/to/config/config.php
   ```

4. **Disabilita errori PHP**
   - In `config.php` assicurati: `display_errors = Off`
   - In `php.ini` assicurati: `display_errors = Off`

5. **Backup Regolari**
   - Configura backup automatico database
   - Backup file via cron o panel hosting

## 📱 Test Funzionalità

Dopo l'installazione, testa:

- [ ] Login/Logout
- [ ] Creazione nuovo socio
- [ ] Upload file (foto, documenti)
- [ ] Registrazione pubblica
- [ ] Invio email (se configurato)
- [ ] Dashboard statistiche
- [ ] Permessi utenti

## ❓ Risoluzione Problemi

### Errore: "Could not connect to database"
- Verifica credenziali MySQL
- Controlla che MySQL sia avviato
- Verifica host (potrebbe essere `127.0.0.1` invece di `localhost`)

### Errore: "Permission denied" su uploads
```bash
chmod -R 777 /path/to/easyvol/uploads
```

### Pagina bianca dopo login
- Controlla log errori PHP: `tail -f /var/log/apache2/error.log`
- Verifica permessi cartelle
- Controlla sessioni PHP configurate

### Errore: "PHPMailer not found" o email non funzionano
- **Causa principale**: Dipendenze Composer non installate
- **Soluzione**: Esegui `composer install --no-dev --optimize-autoloader` nella directory del progetto
- Verifica che esista la cartella `vendor/phpmailer/`
- Verifica configurazione SMTP in `config/config.php`
- Testa con script PHP semplice
- Controlla firewall non blocchi porta 587/465

### Upload file non funziona
- Controlla `php.ini`: `upload_max_filesize` e `post_max_size`
- Verifica permessi cartella uploads
- Controlla `.htaccess` / nginx.conf per limiti

## 📞 Supporto

### Documentazione Completa
- README.md - Panoramica completa
- IMPLEMENTATION_GUIDE.md - Guida sviluppo moduli
- CONTRIBUTING.md - Come contribuire

### Aiuto
- GitHub Issues: https://github.com/cris-deitos/EasyVol/issues
- Wiki: https://github.com/cris-deitos/EasyVol/wiki

## 🚀 Prossimi Passi

Dopo aver familiarizzato con il sistema:

1. Implementa i moduli richiesti (vedi IMPLEMENTATION_GUIDE.md)
2. Personalizza template PDF
3. Configura cron job per email automatiche
4. Importa dati esistenti (se hai un sistema precedente)
5. Forma gli utenti all'utilizzo

## 📈 Tips per l'Uso

1. **Fai Backup Regolari**
   - Database: `mysqldump -u user -p easyvol > backup.sql`
   - File: `tar -czf easyvol_backup.tar.gz /path/to/easyvol/`

2. **Monitora i Log**
   - Activity logs: Controlla chi fa cosa
   - Error logs: Monitora eventuali problemi

3. **Aggiorna Regolarmente**
   - Controlla aggiornamenti su GitHub
   - Testa prima in ambiente di staging

4. **Personalizza**
   - Logo associazione in impostazioni
   - Template email e PDF
   - Colori e stili in CSS

---

**Installazione completata in 5 minuti! 🎉**

Per domande o problemi: [Apri una issue](https://github.com/cris-deitos/EasyVol/issues)
