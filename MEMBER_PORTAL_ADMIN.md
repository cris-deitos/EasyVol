# Portale Soci - Documentazione Tecnica per Amministratori

## Panoramica

Il Portale Soci è una funzionalità pubblica che permette ai soci maggiorenni attivi di accedere e aggiornare autonomamente i propri dati. Questo documento descrive l'architettura, la configurazione e la gestione del portale.

## Architettura

### Componenti

1. **Controller**: `src/Controllers/MemberPortalController.php`
   - Gestisce la logica di verifica e aggiornamento
   - Genera e valida i codici di verifica
   - Invia email di notifica
   - Registra tutte le attività

2. **Pagine Pubbliche**:
   - `public/member_portal_verify.php` - Step 1: Verifica identità
   - `public/member_portal_code.php` - Step 2: Verifica codice email
   - `public/member_portal_update.php` - Step 3: Visualizza e aggiorna dati

3. **Database**:
   - Tabella `member_verification_codes` - Memorizza i codici temporanei
   - Template email in `email_templates` - Template per le notifiche

### Flusso Operativo

```
1. Socio inserisce matricola + cognome
   ↓
2. Sistema verifica: socio attivo + maggiorenne
   ↓
3. Sistema genera codice univoco (8 caratteri alfanumerici)
   ↓
4. Sistema invia email con codice
   ↓
5. Socio inserisce codice
   ↓
6. Sistema valida codice (non scaduto, non usato)
   ↓
7. Socio accede alla pagina di aggiornamento
   ↓
8. Socio modifica i propri dati
   ↓
9. Sistema salva le modifiche in una transazione
   ↓
10. Sistema invia email di conferma a socio + associazione
```

## Installazione

### 1. Eseguire la Migrazione del Database

```bash
cd /path/to/easyvol
mysql -u username -p database_name < migrations/add_member_verification_codes.sql
```

La migrazione crea:
- Tabella `member_verification_codes` con indici ottimizzati
- Template email per codice di verifica
- Template email per conferma aggiornamento

### 2. Verificare la Configurazione Email

Il portale richiede che il sistema di invio email sia configurato correttamente:

```php
// In config/config.php
'email' => [
    'enabled' => true,
    'method' => 'smtp', // o 'sendmail'
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => 587,
    'smtp_username' => 'your_email@example.com',
    'smtp_password' => 'your_password',
    'smtp_encryption' => 'tls', // o 'ssl' o ''
    'from_address' => 'noreply@example.com',
    'from_name' => 'Associazione Nome',
],
```

### 3. Testare l'Invio Email

Usa la pagina di test: `public/test_sendmail.php` per verificare che le email vengano inviate correttamente.

### 4. Aggiungere il Link al Portale

Il link è già stato aggiunto alla pagina di login. Puoi aggiungerlo anche in altre posizioni:

```html
<a href="member_portal_verify.php">
    <i class="bi bi-person-badge"></i> Portale Soci
</a>
```

## Configurazione

### Parametri del Codice di Verifica

Nel file `src/Controllers/MemberPortalController.php`:

```php
const CODE_LENGTH = 8;              // Lunghezza del codice (8 caratteri)
const CODE_EXPIRY_MINUTES = 15;     // Scadenza del codice (15 minuti)
```

Per modificare questi parametri:
1. Modifica le costanti nella classe
2. Aggiorna i template email se necessario

### Template Email

I template email possono essere personalizzati dalla tabella `email_templates`:

1. **member_verification_code**: Email con il codice di verifica
   - Placeholders: `{{association_name}}`, `{{member_name}}`, `{{verification_code}}`

2. **member_data_updated**: Conferma aggiornamento dati
   - Placeholders: `{{association_name}}`, `{{member_name}}`, `{{changes_summary}}`

## Sicurezza

### Misure Implementate

1. **Verifica a Due Fattori**:
   - Step 1: Verifica identità (matricola + cognome)
   - Step 2: Verifica possesso email (codice temporaneo)

2. **Protezione CSRF**:
   - Tutti i form utilizzano token CSRF
   - Token validati ad ogni submission

3. **Codici Temporanei**:
   - Scadenza automatica dopo 15 minuti
   - Invalidazione dopo l'uso
   - Un solo codice valido per socio alla volta

4. **Validazione Input**:
   - Sanitizzazione di tutti gli input
   - Prepared statements per query SQL
   - Validazione email con FILTER_VALIDATE_EMAIL

5. **Logging Completo**:
   - Tutti gli accessi sono tracciati
   - Tentativi falliti registrati
   - Modifiche dati registrate con dettagli

6. **Controllo Età**:
   - Solo soci maggiorenni (18+) possono accedere
   - Calcolo età dal campo `birth_date`

7. **Controllo Stato**:
   - Solo soci con stato "attivo" possono accedere
   - Verifica in tempo reale ad ogni step

### Best Practices

1. **HTTPS Obbligatorio**: Assicurati che il sito usi HTTPS in produzione
2. **Backup Regolari**: Backup del database prima delle modifiche
3. **Monitoraggio**: Controlla regolarmente i log per attività sospette
4. **Email di Test**: Testa periodicamente l'invio email

## Monitoraggio e Log

### Activity Log

Tutte le operazioni del portale sono registrate nella tabella `activity_logs`:

```sql
-- Visualizza accessi al portale
SELECT * FROM activity_logs 
WHERE module = 'member_portal' 
ORDER BY created_at DESC 
LIMIT 100;

-- Visualizza tentativi di verifica falliti
SELECT * FROM activity_logs 
WHERE module = 'member_portal' 
  AND action = 'verify_failed' 
ORDER BY created_at DESC;

-- Visualizza aggiornamenti dati
SELECT * FROM activity_logs 
WHERE module = 'member_portal' 
  AND action = 'data_updated' 
ORDER BY created_at DESC;
```

### Codici di Verifica

Monitorare i codici generati:

```sql
-- Visualizza codici recenti
SELECT mvc.*, m.registration_number, m.last_name, m.first_name
FROM member_verification_codes mvc
JOIN members m ON mvc.member_id = m.id
ORDER BY mvc.created_at DESC
LIMIT 50;

-- Codici scaduti non usati
SELECT mvc.*, m.registration_number
FROM member_verification_codes mvc
JOIN members m ON mvc.member_id = m.id
WHERE mvc.expires_at < NOW() AND mvc.used = 0;

-- Cleanup codici vecchi (oltre 7 giorni)
DELETE FROM member_verification_codes 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

## Manutenzione

### Pulizia Database

Programma una pulizia periodica dei codici vecchi:

```sql
-- Aggiungi a cron/cleanup.php o simile
DELETE FROM member_verification_codes 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

### Monitoraggio Email

Verifica che le email vengano inviate correttamente:

```sql
-- Se usi la tabella email_queue
SELECT * FROM email_queue 
WHERE status = 'failed' 
ORDER BY created_at DESC 
LIMIT 50;
```

## Risoluzione Problemi

### Problema: Email non arrivano

**Soluzioni**:
1. Verifica configurazione SMTP in `config/config.php`
2. Controlla i log email in `email_logs` (se abilitati)
3. Verifica che il server SMTP accetti le connessioni
4. Controlla spam/posta indesiderata
5. Testa con `public/test_sendmail.php`

### Problema: Codice sempre "non valido"

**Soluzioni**:
1. Verifica che la tabella `member_verification_codes` esista
2. Controlla l'orologio del server (timezone corretta)
3. Verifica i log con `action = 'code_verify_failed'`

### Problema: Socio non può accedere

**Cause possibili**:
1. Stato socio non "attivo" → verifica in tabella `members`
2. Socio minorenne → verifica campo `birth_date`
3. Email non presente → aggiungi in `member_contacts`
4. Matricola o cognome errati → verifica dati

### Problema: Aggiornamenti non salvati

**Soluzioni**:
1. Verifica i permessi della tabella database
2. Controlla i log per errori SQL
3. Verifica che la transazione non venga abortita
4. Controlla i vincoli foreign key

## Personalizzazione

### Modificare i Campi Modificabili

Per aggiungere o rimuovere sezioni modificabili, modifica:

1. `src/Controllers/MemberPortalController.php`:
   - Aggiungi metodo `updateNomeSezione()`
   - Aggiungi chiamata in `updateMemberData()`

2. `public/member_portal_update.php`:
   - Aggiungi tab nella nav-tabs
   - Aggiungi contenuto nel tab-pane
   - Aggiungi gestione nel form submission

### Personalizzare l'Aspetto

I file utilizzano Bootstrap 5 con stili inline. Per personalizzare:

1. Modifica i colori nel gradiente:
   ```css
   background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
   ```

2. Modifica il logo (se presente):
   ```html
   <img src="../assets/images/logo.png" alt="Logo">
   ```

## Statistiche

Query utili per statistiche di utilizzo:

```sql
-- Accessi al portale per mese
SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as mese,
    COUNT(*) as accessi
FROM activity_logs 
WHERE module = 'member_portal' 
  AND action = 'verify_success'
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY mese DESC;

-- Soci più attivi
SELECT 
    m.registration_number,
    m.first_name,
    m.last_name,
    COUNT(*) as aggiornamenti
FROM activity_logs al
JOIN members m ON al.entity_id = m.id
WHERE al.module = 'member_portal' 
  AND al.action = 'data_updated'
GROUP BY m.id
ORDER BY aggiornamenti DESC
LIMIT 20;

-- Sezioni più modificate
SELECT 
    JSON_EXTRACT(details, '$.sections') as sezioni,
    COUNT(*) as volte
FROM activity_logs 
WHERE module = 'member_portal' 
  AND action = 'data_updated'
GROUP BY sezioni
ORDER BY volte DESC;
```

## Supporto

Per problemi tecnici o domande:

1. Consulta la documentazione utente: `MEMBER_PORTAL_GUIDE.md`
2. Controlla i log del sistema
3. Verifica la configurazione email
4. Contatta il supporto tecnico

---

**EasyVol** - Sistema Gestionale per Associazioni di Volontariato
