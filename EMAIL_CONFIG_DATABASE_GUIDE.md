# Configurazione Email nel Database

## Panoramica

Questa implementazione sposta la configurazione email dal file `config.php` al database, rendendo possibile modificare le impostazioni email direttamente dall'interfaccia web nella pagina **Impostazioni > Email**.

## Modifiche Implementate

### 1. Migration Database (`migrations/add_email_config_to_database.sql`)

Aggiunge le seguenti chiavi di configurazione alla tabella `config`:

- `email_from_address` - Indirizzo email mittente
- `email_from_name` - Nome mittente
- `email_reply_to` - Indirizzo per risposte
- `email_return_path` - Return-Path per bounce
- `email_charset` - Charset (UTF-8, ISO-8859-1, ecc.)
- `email_encoding` - Encoding (8bit, 7bit, base64, quoted-printable)
- `email_sendmail_params` - Parametri aggiuntivi per sendmail
- `email_additional_headers` - Header personalizzati

### 2. Modifiche a `src/App.php`

#### Nuovo metodo: `loadEmailConfigFromDatabase()`

Questo metodo:
- Carica le configurazioni email dal database
- Sovrascrive i valori del file `config.php` con quelli del database
- Converte `additional_headers` da stringa a array
- Gestisce errori in modo silenzioso per fallback al file config

### 3. Modifiche a `public/settings.php`

#### Tab Email Aggiornato

Ora include tutti i campi di configurazione:

**Campi Base:**
- Indirizzo Email Mittente (obbligatorio)
- Nome Mittente (obbligatorio)
- Indirizzo per Risposte
- Return-Path

**Configurazione Sendmail:**
- Charset (select dropdown)
- Encoding (select dropdown)
- Parametri Sendmail (campo testo)
- Header Aggiuntivi (textarea multilinea)

#### Logica di Salvataggio

- Non modifica più il file `config.php`
- Salva tutti i valori nella tabella `config` del database
- Usa `INSERT ... ON DUPLICATE KEY UPDATE` per upsert
- Valida tutti gli input prima del salvataggio

## Istruzioni di Installazione

### 1. Eseguire la Migration

```sql
-- Eseguire il file: migrations/add_email_config_to_database.sql
-- Oppure dalla pagina Impostazioni > Backup > Applica Correzioni Database
```

### 2. Configurare le Impostazioni Email

1. Accedere a **Impostazioni** nel menu
2. Selezionare il tab **Email**
3. Compilare i campi richiesti:
   - Indirizzo Email Mittente
   - Nome Mittente
4. (Opzionale) Configurare i campi avanzati:
   - Reply-To
   - Return-Path
   - Charset (default: UTF-8)
   - Encoding (default: 8bit)
   - Parametri Sendmail
   - Header Aggiuntivi
5. Cliccare **Salva Modifiche**

## Campi Configurazione Email

### Indirizzo Email Mittente
Email che appare come mittente nei messaggi inviati. **Obbligatorio**.

Esempio: `noreply@example.com`

### Nome Mittente
Nome che appare come mittente. **Obbligatorio**.

Esempio: `EasyVol - Protezione Civile`

### Indirizzo per Risposte (Reply-To)
Indirizzo a cui verranno inviate le risposte degli utenti. Se vuoto, usa l'indirizzo mittente.

Esempio: `info@example.com`

### Return-Path
Indirizzo per gestire i bounce (email non consegnate). Se vuoto, usa l'indirizzo mittente.

Esempio: `bounce@example.com`

### Charset
Codifica dei caratteri per le email.

Opzioni:
- `UTF-8` (consigliato)
- `ISO-8859-1`
- `ISO-8859-15`

### Encoding
Metodo di codifica del contenuto email.

Opzioni:
- `8bit` (consigliato per UTF-8)
- `7bit` (per ASCII puro)
- `base64` (per contenuti binari)
- `quoted-printable` (per testo con caratteri speciali)

### Parametri Sendmail
Parametri aggiuntivi da passare alla funzione `mail()` di PHP.

Esempio: `-f bounce@example.com` (imposta envelope sender)

⚠️ **Attenzione**: Utilizzare con cautela, parametri errati possono impedire l'invio di email.

### Header Aggiuntivi
Header personalizzati da aggiungere alle email, uno per riga.

Esempio:
```
X-Priority: 1
X-Mailer-Custom: EasyVol
Organization: Protezione Civile
```

⚠️ **Nota Sicurezza**: Non includere header pericolosi come `BCC:`, `CC:`, `To:`, `From:` per evitare injection attacks. Il sistema filtra automaticamente questi header.

## Priorità di Caricamento

1. Il sistema carica prima il file `config.php`
2. Poi carica le configurazioni dal database
3. I valori del database sovrascrivono quelli del file config
4. Se il database non è disponibile, usa i valori del file config

Questo garantisce:
- **Compatibilità**: Il sistema funziona anche senza configurazione nel database
- **Flessibilità**: Si possono gestire le impostazioni via web
- **Fallback**: Se c'è un problema con il database, usa il file config

## File Modificati

- `migrations/add_email_config_to_database.sql` - Nuova migration
- `src/App.php` - Caricamento configurazione email da database
- `public/settings.php` - UI e salvataggio configurazione email
- `EMAIL_CONFIG_DATABASE_GUIDE.md` - Questa documentazione

## Compatibilità

- ✅ Compatibile con versioni precedenti
- ✅ Non richiede modifiche al file `config.php`
- ✅ Il file `config.php` rimane valido come fallback
- ✅ Funziona con MySQL 5.6+ e MySQL 8.x

## Note per Sviluppatori

### EmailSender.php

Non sono necessarie modifiche a `EmailSender.php` perché:
- Riceve già la configurazione via costruttore
- La classe `App` si occupa di caricare la configurazione dal database
- Il merge automatico garantisce che `EmailSender` riceva sempre la configurazione corretta

### Testing

Per testare la configurazione email:
1. Accedere a `public/test_sendmail.php`
2. Verificare che le impostazioni siano caricate correttamente
3. Inviare un'email di test

## Sicurezza

### Validazioni Implementate

- ✅ Validazione formato email per from_address, reply_to, return_path
- ✅ Whitelist per encoding (7bit, 8bit, base64, quoted-printable)
- ✅ Filtro automatico header pericolosi (BCC, CC, To, From)
- ✅ Protezione CSRF su tutti i form
- ✅ Controllo permessi (settings.edit)

### Best Practices

1. **Non inserire mai password** nei campi di configurazione
2. **Verificare il formato** degli header aggiuntivi
3. **Testare sempre** dopo aver modificato la configurazione
4. **Utilizzare indirizzi email validi** e di proprietà

## Risoluzione Problemi

### Le email non vengono inviate

1. Verificare che `email.enabled` sia `true` in `config.php`
2. Verificare che sendmail sia configurato sul server
3. Controllare i log di PHP per errori
4. Verificare che gli indirizzi email siano validi

### La configurazione non si salva

1. Verificare i permessi di scrittura sul database
2. Controllare che la migration sia stata eseguita
3. Verificare che l'utente abbia il permesso `settings.edit`

### Gli header personalizzati non funzionano

1. Verificare il formato: `Nome-Header: Valore`
2. Controllare che non ci siano header pericolosi
3. Verificare che sendmail supporti gli header utilizzati

## Supporto

Per problemi o domande:
1. Controllare i log di sistema (`error_log`)
2. Verificare la configurazione del database
3. Testare con `public/test_sendmail.php`
