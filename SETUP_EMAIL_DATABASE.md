# Setup Email e Database per EasyVol

Questo documento spiega come configurare correttamente il database e l'invio email per risolvere l'errore "Errore durante il salvataggio dell'utente".

## 🔧 Problemi Risolti

Questa configurazione risolve i seguenti problemi:

1. ✅ **Errore creazione utente**: "Errore durante il salvataggio dell'utente"
2. ✅ **Campo must_change_password mancante**: La tabella users ora ha il campo necessario
3. ✅ **Tabella email_logs mancante**: Creata per il logging delle email
4. ✅ **PHPMailer non configurato**: Gestione graceful se non installato
5. ✅ **Invio email che blocca creazione utente**: Ora non-blocking
6. ✅ **Activity_logs con colonne obbligatorie**: Gestione corretta

## 📋 Prerequisiti

- PHP 8.3 o superiore
- MySQL 5.6+ o MariaDB 10.3+
- Accesso al database con permessi ALTER TABLE
- (Opzionale) Account SMTP per invio email

## 🗄️ Step 1: Aggiornamento Database

### Metodo 1: Usando phpMyAdmin (Raccomandato per principianti)

1. Accedi a phpMyAdmin
2. Seleziona il database EasyVol
3. Clicca sulla tab "SQL"
4. Apri il file `migrations/fix_user_creation_issues.sql`
5. Copia tutto il contenuto del file
6. Incolla nell'area SQL di phpMyAdmin
7. Clicca "Esegui" o "Go"

### Metodo 2: Usando MySQL da terminale

```bash
cd /home/runner/work/EasyVol/EasyVol
mysql -u your_username -p your_database < migrations/fix_user_creation_issues.sql
```

Sostituisci `your_username` con il tuo username MySQL e `your_database` con il nome del database.

### Metodo 3: Usando lo script PHP di migrazione

```bash
cd /home/runner/work/EasyVol/EasyVol
php migrations/run_migration.php migrations/fix_user_creation_issues.sql
```

### Verifica che la migrazione sia avvenuta correttamente

Esegui questa query in phpMyAdmin o da terminale:

```sql
-- Verifica campo must_change_password
SHOW COLUMNS FROM users LIKE 'must_change_password';

-- Verifica tabella email_logs
SHOW TABLES LIKE 'email_logs';

-- Verifica tabella password_reset_tokens
SHOW TABLES LIKE 'password_reset_tokens';

-- Verifica email templates
SELECT COUNT(*) FROM email_templates WHERE template_name IN ('user_welcome', 'password_reset');
```

Se tutti i comandi restituiscono risultati, la migrazione è avvenuta con successo.

## 📧 Step 2: Installazione PHPMailer

PHPMailer è già nel file `composer.json`, ma devi installarlo:

```bash
cd /home/runner/work/EasyVol/EasyVol
composer install
```

Questo comando installerà:
- PHPMailer 7.x
- Tutte le altre dipendenze necessarie

### Verifica installazione PHPMailer

```bash
composer show phpmailer/phpmailer
```

Dovresti vedere l'output con la versione installata (7.x).

### Se composer non è installato

Su hosting condiviso, potrebbe essere necessario:

1. Scaricare composer.phar:
   ```bash
   php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
   php composer-setup.php
   php -r "unlink('composer-setup.php');"
   ```

2. Usare composer.phar invece di composer:
   ```bash
   php composer.phar install
   ```

## ⚙️ Step 3: Configurazione Email

### 3.1 Crea il file config.php

Se non esiste, copia il file di esempio:

```bash
cp config/config.sample.php config/config.php
```

### 3.2 Configura le impostazioni email

Apri `config/config.php` e modifica la sezione email:

#### Opzione A: Email DISABILITATA (più semplice)

Se non vuoi inviare email, imposta:

```php
'email' => [
    'enabled' => false,  // Disabilita invio email
    'method' => 'smtp',
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'tls',
    'from_email' => 'noreply@example.com',
    'from_name' => 'EasyVol',
],
```

Con questa configurazione:
- ✅ La creazione utenti funzionerà
- ✅ Non verrà inviata nessuna email
- ✅ Verrà loggato nel log PHP che l'email è disabilitata

#### Opzione B: Email ABILITATA con SMTP

Se hai un server SMTP (Gmail, Outlook, server personale):

```php
'email' => [
    'enabled' => true,  // Abilita invio email
    'method' => 'smtp',
    'smtp_host' => 'smtp.gmail.com',  // Il tuo server SMTP
    'smtp_port' => 587,                // 587 per TLS, 465 per SSL
    'smtp_username' => 'tuo@email.com',
    'smtp_password' => 'tua_password_app',  // Vedi nota sotto
    'smtp_encryption' => 'tls',        // 'tls' o 'ssl'
    'from_email' => 'noreply@tuodominio.com',
    'from_name' => 'EasyVol',
],
```

**Nota per Gmail**: Non usare la password normale, ma crea una "App Password":
1. Vai su https://myaccount.google.com/security
2. Abilita "Verifica in due passaggi"
3. Vai su "Password per le app"
4. Genera una nuova password per "Mail"
5. Usa quella password nel config

#### Opzione C: Email con sendmail (server Linux)

```php
'email' => [
    'enabled' => true,
    'method' => 'sendmail',
    'from_email' => 'noreply@tuodominio.com',
    'from_name' => 'EasyVol',
],
```

### 3.3 Configura URL applicazione

Importante per i link nelle email:

```php
'app' => [
    'name' => 'EasyVol',
    'version' => '1.0.0',
    'url' => 'https://tuodominio.com',  // ← Cambia questo!
    'timezone' => 'Europe/Rome',
    'locale' => 'it_IT',
],
```

## 🧪 Step 4: Test della Configurazione

### Test 1: Verifica configurazione database

Crea un file temporaneo `test_db.php` nella root:

```php
<?php
require_once 'src/Autoloader.php';
EasyVol\Autoloader::register();

try {
    $app = EasyVol\App::getInstance();
    $db = $app->getDb();
    
    // Test query
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM users");
    echo "✅ Database connesso! Utenti nel sistema: " . $result['count'] . "\n";
    
    // Verifica campo must_change_password
    $columns = $db->fetchAll("SHOW COLUMNS FROM users LIKE 'must_change_password'");
    if (count($columns) > 0) {
        echo "✅ Campo must_change_password presente\n";
    } else {
        echo "❌ Campo must_change_password MANCANTE - Esegui la migrazione!\n";
    }
    
    // Verifica tabella email_logs
    $tables = $db->fetchAll("SHOW TABLES LIKE 'email_logs'");
    if (count($tables) > 0) {
        echo "✅ Tabella email_logs presente\n";
    } else {
        echo "❌ Tabella email_logs MANCANTE - Esegui la migrazione!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Errore: " . $e->getMessage() . "\n";
}
```

Esegui:
```bash
php test_db.php
```

### Test 2: Test creazione utente (senza email)

Crea `test_user_creation.php`:

```php
<?php
require_once 'src/Autoloader.php';
EasyVol\Autoloader::register();

try {
    $app = EasyVol\App::getInstance();
    $db = $app->getDb();
    $config = $app->getConfig();
    
    $userController = new \EasyVol\Controllers\UserController($db, $config);
    
    // Dati test - CAMBIA questi valori per ogni test!
    $userData = [
        'username' => 'test_' . time(),
        'email' => 'test_' . time() . '@example.com',
        'full_name' => 'Test User',
        'role_id' => 1,  // Cambia con un role_id valido del tuo DB
        'is_active' => 1
    ];
    
    echo "Creazione utente test...\n";
    $result = $userController->create($userData, 1);
    
    if (is_numeric($result)) {
        echo "✅ Utente creato con successo! ID: $result\n";
        echo "Username: " . $userData['username'] . "\n";
        echo "Password di default: Pw@12345678\n";
    } else {
        echo "❌ Errore creazione utente: " . print_r($result, true) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Eccezione: " . $e->getMessage() . "\n";
}
```

Esegui:
```bash
php test_user_creation.php
```

### Test 3: Test invio email (solo se email abilitata)

Crea `test_email.php`:

```php
<?php
require_once 'src/Autoloader.php';
EasyVol\Autoloader::register();

try {
    $app = EasyVol\App::getInstance();
    $db = $app->getDb();
    $config = $app->getConfig();
    
    if (!($config['email']['enabled'] ?? false)) {
        echo "ℹ️  Email disabilitata nella configurazione\n";
        exit(0);
    }
    
    if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        echo "❌ PHPMailer non installato. Eseguire: composer install\n";
        exit(1);
    }
    
    $emailSender = new \EasyVol\Utils\EmailSender($config, $db);
    
    echo "Invio email di test...\n";
    $result = $emailSender->send(
        'tua@email.com',  // ← Cambia con la tua email!
        'Test EasyVol',
        '<h1>Test Email</h1><p>Se ricevi questa email, la configurazione funziona!</p>'
    );
    
    if ($result) {
        echo "✅ Email inviata con successo!\n";
    } else {
        echo "❌ Errore invio email. Controlla i log PHP.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Eccezione: " . $e->getMessage() . "\n";
}
```

Esegui:
```bash
php test_email.php
```

## 🐛 Risoluzione Problemi Comuni

### Problema: "Errore durante il salvataggio dell'utente"

**Causa**: Campo `must_change_password` mancante o problemi con email/activity_logs

**Soluzione**:
1. Esegui la migrazione: `migrations/fix_user_creation_issues.sql`
2. Verifica con: `SHOW COLUMNS FROM users LIKE 'must_change_password';`
3. Riavvia PHP-FPM se necessario: `sudo service php-fpm restart`

### Problema: "Table 'email_logs' doesn't exist"

**Causa**: Tabella mancante

**Soluzione**:
1. Esegui la migrazione: `migrations/fix_user_creation_issues.sql`
2. Verifica con: `SHOW TABLES LIKE 'email_logs';`

### Problema: "PHPMailer not found"

**Causa**: Dipendenze non installate

**Soluzione**:
```bash
composer install
# oppure
php composer.phar install
```

### Problema: "SMTP connect() failed"

**Causa**: Credenziali SMTP errate o firewall

**Soluzione**:
1. Verifica username e password SMTP
2. Per Gmail, usa "App Password" non la password normale
3. Verifica porta (587 per TLS, 465 per SSL)
4. Controlla che il firewall permetta connessioni SMTP
5. Prova con email disabilitata (`'enabled' => false`)

### Problema: Email non viene inviata ma utente creato

**Comportamento**: Questo è NORMALE e CORRETTO!

**Spiegazione**: 
- Il sistema ora crea l'utente PRIMA di inviare l'email
- Se l'email fallisce, l'utente viene comunque creato
- L'errore email viene loggato ma non blocca la creazione
- Questo previene la perdita di dati

**Verifica log**:
```bash
tail -f /var/log/php/error.log
# oppure
tail -f /var/log/apache2/error.log
```

### Problema: "Column count doesn't match"

**Causa**: Database non aggiornato

**Soluzione**:
1. Esegui TUTTE le migrazioni in ordine
2. Verifica struttura tabelle con `DESCRIBE users;`

## 📁 File Importanti

- `config/config.php` - Configurazione principale (NON committare!)
- `config/config.sample.php` - Template configurazione
- `migrations/fix_user_creation_issues.sql` - Migrazione database
- `src/Controllers/UserController.php` - Logica creazione utenti
- `src/Utils/EmailSender.php` - Gestione invio email
- `database_schema.sql` - Schema completo database

## ✅ Checklist Finale

Prima di considerare il setup completo, verifica:

- [ ] Migrazione database eseguita con successo
- [ ] Campo `must_change_password` presente in tabella `users`
- [ ] Tabella `email_logs` creata
- [ ] Tabella `password_reset_tokens` creata
- [ ] Email templates inseriti
- [ ] Composer install eseguito (se vuoi usare email)
- [ ] File `config/config.php` creato e configurato
- [ ] Test creazione utente funziona
- [ ] (Opzionale) Test invio email funziona
- [ ] Log PHP non mostrano errori critici

## 🎯 Risultato Atteso

Dopo questa configurazione:

✅ **Creazione utenti funziona sempre**, anche se:
- Email è disabilitata
- PHPMailer non è installato
- Server SMTP non è configurato
- Invio email fallisce

✅ **Email viene inviata quando**:
- Email è abilitata in config
- PHPMailer è installato
- SMTP è configurato correttamente
- Template email esistono nel database

✅ **Utente può cambiare password** al primo login se:
- Campo `must_change_password` = 1
- Password è quella di default

✅ **Activity logs funziona** con tutte le colonne richieste

## 📞 Supporto

Se hai ancora problemi dopo aver seguito questa guida:

1. Controlla i log PHP: `/var/log/php/error.log`
2. Controlla i log MySQL: `/var/log/mysql/error.log`
3. Abilita debug SMTP decommentando in `EmailSender.php`:
   ```php
   $mailer->SMTPDebug = 2;
   ```
4. Esegui i test script sopra per diagnostica

---

*Ultimo aggiornamento: 7 Dicembre 2024*
*Versione: 1.0*
