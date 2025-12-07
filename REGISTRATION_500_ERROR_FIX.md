# Risoluzione Errore 500 nei Form di Registrazione

## Problema
I form di registrazione (`register_adult.php` e `register_junior.php`) restituiscono un errore 500 quando vengono inviati.

## Causa
L'errore 500 si verifica perché l'applicazione **non è ancora stata installata**. I form di registrazione richiedono:
- Database configurato
- Schema del database creato
- Tabelle necessarie presenti
- Dipendenze installate

## Soluzione

### Passo 1: Installare le Dipendenze
Prima di tutto, assicurati che tutte le dipendenze siano installate:

```bash
cd /percorso/EasyVol
composer install
```

Questo installerà:
- mPDF per la generazione dei PDF
- PHPMailer per l'invio delle email
- Altre librerie necessarie

### Passo 2: Configurare il Database
Crea il file di configurazione copiando il template:

```bash
cp config/config.sample.php config/config.php
```

Modifica `config/config.php` con i tuoi dati:

```php
'database' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'easyvol',        // Nome del tuo database
    'username' => 'tuo_utente',  // Il tuo utente MySQL
    'password' => 'tua_password', // La tua password MySQL
    'charset' => 'utf8mb4',
],
```

### Passo 3: Creare il Database
Se il database non esiste ancora, crealo:

```bash
mysql -u root -p
CREATE DATABASE easyvol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;
```

### Passo 4: Eseguire l'Installazione
Visita la pagina di installazione nel tuo browser:

```
http://tuo-dominio/install.php
```

L'installazione wizard ti guiderà attraverso:
1. **Configurazione Database**: Verifica della connessione
2. **Creazione Tabelle**: Importazione dello schema del database
3. **Creazione Admin**: Impostazione del primo utente amministratore
4. **Completamento**: Conferma dell'installazione riuscita

### Passo 5: Verificare l'Installazione
Dopo l'installazione, verifica che:
- Il login funzioni con l'utente admin creato
- La dashboard sia accessibile
- Le tabelle siano state create correttamente

### Passo 6: Testare i Form di Registrazione
Ora puoi testare i form di registrazione:

#### Registrazione Adulti
```
http://tuo-dominio/register_adult.php
```
Form completo con:
- Dati anagrafici
- Indirizzi (residenza e domicilio)
- Recapiti
- Patenti e abilitazioni
- Corsi e specializzazioni
- Informazioni sanitarie
- Datore di lavoro
- Dichiarazioni obbligatorie

#### Registrazione Minorenni (Cadetti)
```
http://tuo-dominio/register_junior.php
```
Form con:
- Dati anagrafici del minore
- Indirizzi
- Recapiti
- Informazioni sanitarie
- Dati genitori/tutori
- Dichiarazioni obbligatorie

## Funzionalità dei Form di Registrazione

Una volta completata l'installazione, i form:

✅ **Validano** tutti i campi obbligatori
✅ **Generano** automaticamente un codice univoco per la domanda
✅ **Creano** un PDF della domanda compilata
✅ **Inviano** email di conferma (se configurato)
✅ **Salvano** la domanda nel database per l'approvazione
✅ **Gestiscono** file allegati e documenti

## Email e PDF

### Configurazione Email (Opzionale)
Se vuoi che vengano inviate email di conferma, configura SMTP in `config/config.php`:

```php
'email' => [
    'enabled' => true,
    'method' => 'smtp',
    'smtp_host' => 'smtp.tuoserver.com',
    'smtp_port' => 587,
    'smtp_username' => 'tuo@email.com',
    'smtp_password' => 'tua_password',
    'smtp_encryption' => 'tls',
    'from_email' => 'noreply@tuodominio.com',
    'from_name' => 'EasyVol',
],
```

### Generazione PDF
I PDF vengono generati automaticamente e salvati in:
```
uploads/applications/
```

Assicurati che questa directory abbia i permessi di scrittura:
```bash
chmod 755 uploads/applications
```

## Risoluzione Problemi Comuni

### Errore: "Configuration file not found"
**Soluzione**: Crea il file `config/config.php` come descritto nel Passo 2

### Errore: "Connection failed"
**Soluzione**: Verifica le credenziali del database in `config/config.php`

### Errore: "Table 'member_applications' doesn't exist"
**Soluzione**: Completa l'installazione visitando `install.php`

### Errore: "Failed to create directory"
**Soluzione**: Imposta i permessi corretti:
```bash
chmod 755 uploads
chmod 755 uploads/applications
```

### PDF non si genera
**Soluzione**: Verifica che mPDF sia installato:
```bash
composer require mpdf/mpdf
```

## Test Finale

Dopo aver completato tutti i passi, testa il form di registrazione:

1. Compila tutti i campi richiesti
2. Accetta tutte le dichiarazioni obbligatorie
3. Invia il form
4. Verifica che venga mostrata la pagina di conferma con il codice domanda
5. Controlla che il PDF sia stato generato in `uploads/applications/`
6. (Se email configurata) Verifica l'email di conferma

## Supporto

Se continui ad avere problemi:
1. Controlla i log di errore PHP
2. Verifica i log di Apache/Nginx
3. Controlla i permessi delle directory
4. Verifica che tutte le dipendenze siano installate

## Note di Sicurezza

⚠️ **Importante**:
- Non committare mai `config/config.php` nel repository
- Usa password sicure per il database
- Mantieni aggiornate le dipendenze
- Configura correttamente i permessi delle directory
- Usa HTTPS in produzione
