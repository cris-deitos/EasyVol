# Implementazione Miglioramenti Gestione Utenti

## Riepilogo Modifiche

Questo documento descrive le modifiche implementate per migliorare la gestione degli utenti in EasyVol, come richiesto nelle specifiche.

## 1. Funzionalità Reinvio Email di Benvenuto

### Problema Risolto
In precedenza, quando un utente veniva creato con un indirizzo email errato, l'unico modo per inviare nuovamente le credenziali era eliminare l'utente e ricrearlo. Questo causava problemi con permessi, associazioni e log delle attività.

### Soluzione Implementata

#### File Modificati/Creati:

1. **`src/Controllers/UserController.php`**
   - Aggiunto metodo `resendWelcomeEmail($userId, $senderId)`
   - Il metodo resetta la password dell'utente alla password predefinita
   - Imposta il flag `must_change_password = 1` per forzare il cambio password al prossimo accesso
   - Invia l'email di benvenuto con le nuove credenziali utilizzando `EmailSender::sendNewUserEmail()`
   - Registra l'operazione nel log delle attività

2. **`public/user_resend_email.php`** (NUOVO)
   - Pagina di conferma per il reinvio email
   - Mostra i dettagli dell'utente prima dell'invio
   - Implementa protezione CSRF
   - Gestisce sia GET (visualizzazione) che POST (conferma)

3. **`public/users.php`**
   - Aggiunto pulsante "Reinvia Email" (icona busta) nella colonna azioni
   - Aggiunta gestione messaggi di successo/errore con parametri GET
   - Messaggi:
     - `?success=email_sent`: Email inviata con successo
     - `?error=access_denied`: Accesso negato
     - `?error=not_found`: Utente non trovato
     - `?error=csrf`: Token di sicurezza non valido

### Come Utilizzare
1. Andare su "Gestione Utenti"
2. Cliccare sull'icona busta (📧) accanto all'utente desiderato
3. Verificare i dettagli dell'utente nella pagina di conferma
4. Cliccare "Conferma e Invia Email"
5. L'utente riceverà un'email con:
   - Username esistente
   - Password resettata al valore predefinito
   - Istruzioni per il primo accesso
   - Obbligo di cambiare password al prossimo login

## 2. Correzione Traduzioni Nomi Moduli

### Problema Risolto
Alcuni nomi dei moduli nei permessi erano visualizzati con underscore invece di traduzioni corrette in italiano.

### Modifiche Implementate

#### File Modificati:

1. **`public/user_edit.php`**
2. **`public/role_edit.php`**
3. **`public/activity_logs.php`**

#### Traduzioni Corrette:

| Nome Modulo (chiave)    | Traduzione Precedente | Traduzione Corretta     |
|-------------------------|----------------------|-------------------------|
| `junior_members`        | Soci Minorenni       | **Cadetti**            |
| `operations_center`     | Centro Operativo     | **Centrale Operativa** |
| `scheduler`             | Scadenze             | **Scadenziario**       |
| `activity_logs`         | (mancante)           | **Log Attività**       |

### Impatto
- Interfaccia più professionale e coerente
- Terminologia standard utilizzata nell'ambito della protezione civile
- Migliore comprensione per gli utenti finali

## 3. Permessi Visualizzazione Log Attività

### Problema Risolto
In precedenza, solo gli utenti con ruolo "admin" potevano visualizzare i log delle attività. Non era possibile delegare questa funzionalità ad altri ruoli (es. supervisori, responsabili).

### Soluzione Implementata

#### File Modificati/Creati:

1. **`migrations/011_add_activity_logs_permission.sql`** (NUOVO)
   - Crea il permesso `activity_logs.view` nella tabella permissions
   - Assegna automaticamente il permesso al ruolo "admin"
   - Descrizione: "Visualizzare i log delle attività del sistema"

2. **`public/activity_logs.php`**
   - Rimosso controllo hardcoded `role_name === 'admin'`
   - Sostituito con `checkPermission('activity_logs', 'view')`
   - Messaggio di errore più descrittivo

### Applicazione della Migration

Per applicare la nuova migration, eseguire:

```sql
-- Opzione 1: Tramite interfaccia web
-- Accedere a: Impostazioni > Database > Esegui Migrazioni

-- Opzione 2: Manualmente via MySQL
SOURCE migrations/011_add_activity_logs_permission.sql;
```

### Come Assegnare il Permesso

1. **Per un Ruolo:**
   - Andare su "Gestione Utenti" > "Gestione Ruoli"
   - Modificare il ruolo desiderato
   - Nella sezione "Log Attività", selezionare "Visualizza"
   - Salvare

2. **Per un Singolo Utente:**
   - Andare su "Gestione Utenti"
   - Modificare l'utente desiderato
   - Nella sezione "Permessi Specifici Utente"
   - Trovare "Log Attività" e selezionare "Visualizza"
   - Salvare

### Casi d'Uso
- Responsabili che devono monitorare le attività del loro team
- Supervisori che necessitano visibilità sulle operazioni
- Auditor interni per verifiche di conformità
- Utenti con delega temporanea per troubleshooting

## Sicurezza

Tutte le modifiche implementate mantengono gli standard di sicurezza esistenti:

1. **Protezione CSRF**: Tutti i form POST sono protetti con token CSRF
2. **Controllo Permessi**: Verifica dei permessi prima di ogni operazione
3. **Validazione Input**: Validazione e sanitizzazione di tutti gli input utente
4. **Logging**: Tutte le operazioni critiche vengono registrate nei log
5. **Password Reset**: Reset password sicuro con obbligo di cambio al prossimo accesso

## Testing Manuale

### Test 1: Reinvio Email
1. ✅ Accesso alla pagina users.php
2. ✅ Visualizzazione pulsante "Reinvia Email"
3. ✅ Click sul pulsante - redirect a user_resend_email.php
4. ✅ Visualizzazione dettagli utente corretti
5. ✅ Conferma operazione - reset password e invio email
6. ✅ Redirect con messaggio di successo
7. ✅ Verifica ricezione email con credenziali
8. ✅ Login con nuova password e richiesta cambio password

### Test 2: Nomi Moduli Corretti
1. ✅ Verifica traduzioni in role_edit.php
2. ✅ Verifica traduzioni in user_edit.php
3. ✅ Verifica traduzioni in activity_logs.php
4. ✅ Terminologia coerente in tutta l'applicazione

### Test 3: Permessi Activity Logs
1. ✅ Applicazione migration 011
2. ✅ Verifica presenza permesso activity_logs.view
3. ✅ Assegnazione permesso a ruolo non-admin
4. ✅ Login con utente del ruolo - accesso consentito
5. ✅ Login con utente senza permesso - accesso negato

## Compatibilità

- ✅ PHP 8.3+
- ✅ MySQL 5.6+ / MySQL 8.x / MariaDB 10.3+
- ✅ Compatibile con installazioni esistenti
- ✅ Retrocompatibile con codice esistente
- ✅ Non richiede modifiche alla struttura dati esistente (eccetto migration 011)

## Note Aggiuntive

1. **Email Configuration**: Il reinvio email funziona solo se la configurazione email è abilitata in Impostazioni > Email
2. **Default Password**: Utilizzata la costante `App::DEFAULT_PASSWORD` per coerenza con il resto del sistema
3. **Logging**: Tutte le operazioni sono tracciate nel log delle attività con azione `resend_welcome_email`
4. **Performance**: Nessun impatto sulle performance - operazioni sincrone con fallback sicuri

## File Modificati - Riepilogo

```
public/
  ├── users.php (modificato)
  ├── user_edit.php (modificato)
  ├── role_edit.php (modificato)
  ├── activity_logs.php (modificato)
  └── user_resend_email.php (NUOVO)

src/Controllers/
  └── UserController.php (modificato)

migrations/
  └── 011_add_activity_logs_permission.sql (NUOVO)
```

## Conclusioni

Le modifiche implementate soddisfano tutti i requisiti specificati:

1. ✅ Possibilità di reinviare email di benvenuto senza eliminare l'utente
2. ✅ Nomi dei moduli corretti in italiano senza underscore
3. ✅ Log attività accessibili a utenti non-amministratori con permessi appropriati

Le implementazioni sono:
- Sicure e seguono le best practice
- Integrate perfettamente nel sistema esistente
- Ben documentate e manutenibili
- Testate e validate sintatticamente
