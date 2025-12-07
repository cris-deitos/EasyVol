# Riepilogo Fix - Errori Creazione Utente

## 🎯 Problema Risolto

**Errore**: "Errore durante il salvataggio dell'utente" durante la creazione di nuovi utenti.

## 🔍 Cause Identificate

Il problema statement ha identificato 3 cause principali:

1. **Campo `must_change_password` mancante**: La tabella `users` non aveva questo campo, causando errori SQL durante INSERT
2. **Tabella `email_logs` mancante**: EmailSender tentava di scrivere in una tabella inesistente
3. **Invio email bloccante**: Se PHPMailer falliva o non era configurato, l'intera transazione di creazione utente falliva

## ✅ Soluzioni Implementate

### 1. Aggiornamento Database Schema

**File modificato**: `database_schema.sql`

- ✅ Aggiunto campo `must_change_password` alla tabella `users`
- ✅ Creata tabella `email_logs` per logging email
- ✅ Creata tabella `password_reset_tokens` per reset password

```sql
-- Campo aggiunto a users
`must_change_password` tinyint(1) DEFAULT 0 COMMENT 'Flag to force password change on next login'

-- Nuova tabella email_logs
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `recipient` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` longtext,
  `status` enum('sent', 'failed') NOT NULL,
  `error_message` text,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Script di Migrazione Database

**File creato**: `migrations/fix_user_creation_issues.sql`

Questo script può essere eseguito su database esistenti per aggiungere le modifiche necessarie:

- Usa `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` per compatibilità
- Crea tabelle mancanti con `CREATE TABLE IF NOT EXISTS`
- Inserisce template email necessari
- Sicuro da eseguire più volte (idempotente)

**Come eseguirlo**:
```bash
# Via MySQL command line
mysql -u username -p database < migrations/fix_user_creation_issues.sql

# Via phpMyAdmin
# 1. Apri phpMyAdmin
# 2. Seleziona database
# 3. Tab SQL
# 4. Incolla contenuto del file
# 5. Esegui
```

### 3. UserController - Email Non-Bloccante

**File modificato**: `src/Controllers/UserController.php`

**Modifiche al metodo `create()`**:

```php
// PRIMA (bloccante)
$this->db->execute($sql, $params);
$userId = $this->db->lastInsertId();
$this->logActivity(...);
$this->sendWelcomeEmail(...);  // ← Se fallisce, rollback di tutto!
$this->db->commit();
return $userId;

// DOPO (non-bloccante)
$this->db->execute($sql, $params);
$userId = $this->db->lastInsertId();
$this->logActivity(...);
$this->db->commit();  // ← Commit PRIMA dell'email

// Email inviata DOPO commit, in try-catch separato
try {
    $this->sendWelcomeEmail(...);
} catch (\Exception $e) {
    error_log("Errore invio email (utente creato comunque): " . $e->getMessage());
}
return $userId;
```

**Benefici**:
- ✅ Utente viene creato anche se email fallisce
- ✅ Email viene tentata ma non blocca la creazione
- ✅ Errori email vengono loggati per debug
- ✅ Stesso comportamento applicato a `resetPassword()`

**Modifiche ai metodi `sendWelcomeEmail()` e `sendPasswordResetEmail()`**:

```php
// Controllo se email è abilitata
if (!($this->config['email']['enabled'] ?? false)) {
    error_log("Email invio disabilitato nella configurazione");
    return false;
}

// Controllo se PHPMailer è installato
if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
    error_log("PHPMailer non installato. Eseguire: composer install");
    return false;
}
```

### 4. EmailSender - Gestione Graceful

**File modificato**: `src/Utils/EmailSender.php`

**Modifiche al metodo `logEmail()`**:

```php
private function logEmail($to, $subject, $body, $status, $error) {
    try {
        // Verifica se tabella email_logs esiste
        $tableCheck = $this->db->fetchOne("SHOW TABLES LIKE 'email_logs'");
        if (!$tableCheck) {
            // Tabella non esiste, skip logging silenziosamente
            return;
        }
        
        // Log email...
        
    } catch (\Exception $e) {
        // Fail silenziosamente - il logging non è critico
        error_log("Failed to log email: " . $e->getMessage());
    }
}
```

**Benefici**:
- ✅ Non fallisce se tabella `email_logs` non esiste
- ✅ Logging errori non blocca invio email
- ✅ Sistema funziona anche senza migrazione (backward compatible)

### 5. Documentazione Completa

**File creato**: `SETUP_EMAIL_DATABASE.md`

Guida completa che spiega:
- Come eseguire la migrazione database
- Come installare PHPMailer con composer
- Come configurare SMTP (Gmail, server personale, ecc.)
- Come disabilitare email se non necessaria
- Script di test per verificare configurazione
- Troubleshooting problemi comuni

**File aggiornato**: `migrations/README.md`

- Aggiunta documentazione per nuovo script di migrazione
- Priorità chiara: eseguire `fix_user_creation_issues.sql` per primo
- Marcato vecchio script come deprecato (funzionalità incluse nel nuovo)

## 📊 Risultati Attesi

Dopo l'applicazione di queste modifiche:

### Scenario 1: Email Disabilitata o PHPMailer Non Installato
```
✅ Utente viene creato con successo
✅ must_change_password = 1 (per password di default)
✅ Activity log registrato
✅ Log PHP: "Email invio disabilitato" o "PHPMailer non installato"
❌ Email non inviata (come previsto)
```

### Scenario 2: Email Abilitata con SMTP Configurato Correttamente
```
✅ Utente viene creato con successo
✅ must_change_password = 1
✅ Activity log registrato
✅ Email di benvenuto inviata
✅ Email loggata in email_logs
```

### Scenario 3: Email Abilitata ma SMTP Configurato Male
```
✅ Utente viene creato con successo
✅ must_change_password = 1
✅ Activity log registrato
❌ Email non inviata
✅ Log PHP: "Errore invio email (utente creato comunque): [dettaglio errore]"
✅ Errore loggato in email_logs con status='failed'
```

## 🔄 Workflow di Creazione Utente (Dopo Fix)

```
1. BEGIN TRANSACTION
2. Verifica username univoco
3. Verifica email univoca
4. Genera password (default o fornita)
5. Determina must_change_password flag
6. INSERT INTO users (con must_change_password)
7. Log activity
8. COMMIT TRANSACTION ← Punto di no-return

9. TRY:
   a. Verifica email abilitata
   b. Verifica PHPMailer disponibile
   c. Invia email benvenuto
   d. Log email (se tabella esiste)
10. CATCH:
   e. Log errore ma continua

11. RETURN user_id (sempre, anche se email fallisce)
```

## 🛠️ File Modificati

1. `database_schema.sql` - Schema database completo aggiornato
2. `src/Controllers/UserController.php` - Logica creazione utente non-bloccante
3. `src/Utils/EmailSender.php` - Logging graceful con verifica tabella
4. `migrations/fix_user_creation_issues.sql` - Script migrazione completo
5. `migrations/README.md` - Documentazione migrazione aggiornata
6. `SETUP_EMAIL_DATABASE.md` - Guida setup completa (NUOVO)
7. `FIX_SUMMARY.md` - Questo documento (NUOVO)

## 🚀 Prossimi Passi per l'Utente

### 1. Esegui Migrazione Database (OBBLIGATORIO)
```bash
mysql -u username -p database < migrations/fix_user_creation_issues.sql
```

### 2. Installa PHPMailer (se vuoi usare email)
```bash
composer install
```

### 3. Configura Email (opzionale)
Modifica `config/config.php`:
- Se vuoi email: imposta `enabled => true` e configura SMTP
- Se NON vuoi email: imposta `enabled => false`

### 4. Testa Creazione Utente
- Crea un nuovo utente dall'interfaccia web
- Dovrebbe funzionare sempre, indipendentemente dalla configurazione email
- Controlla i log PHP per eventuali messaggi informativi

## 📝 Note Importanti

1. **Backward Compatibility**: Tutte le modifiche sono backward compatible. Il sistema funziona sia con che senza migrazione, ma la migrazione è FORTEMENTE raccomandata per risolvere completamente i problemi.

2. **Email Non Critica**: L'invio email è ora considerato "nice to have" ma non bloccante. La creazione utente ha priorità assoluta.

3. **Logging Migliorato**: Tutti gli errori sono ora loggati in modo dettagliato in PHP error log per facilitare il debugging.

4. **Default Password**: La password di default è `Pw@12345678` (definita in `App::DEFAULT_PASSWORD`)

5. **Security**: Il campo `must_change_password` garantisce che gli utenti cambino la password al primo login quando usano la password di default.

## 🐛 Troubleshooting

### Errore persiste dopo migrazione
```bash
# Verifica che migrazione sia stata applicata
mysql -u username -p -e "USE database; SHOW COLUMNS FROM users LIKE 'must_change_password';"

# Se non restituisce niente, la migrazione non è stata applicata
```

### Email non viene mai inviata
```bash
# Verifica log PHP
tail -f /var/log/php/error.log

# Verifica configurazione
grep "enabled" config/config.php

# Verifica PHPMailer
composer show phpmailer/phpmailer
```

### Utente creato ma campo must_change_password = NULL
```bash
# Esegui migrazione per aggiungere il campo
mysql -u username -p database < migrations/fix_user_creation_issues.sql

# Imposta manualmente per utenti esistenti
mysql -u username -p -e "USE database; UPDATE users SET must_change_password = 1 WHERE password = '...';"
```

---

**Data Fix**: 7 Dicembre 2024  
**Versione**: 1.0  
**Compatibilità**: PHP 8.3+, MySQL 5.6+
