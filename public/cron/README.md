# EasyVol - Web-Accessible Cron Jobs

Questa directory contiene gli endpoint web per l'esecuzione dei cron job tramite HTTP/HTTPS.

## Perché usare endpoint web per i cron job?

Su alcuni servizi di hosting condiviso (come **Aruba**), l'esecuzione diretta di script PHP via CLI può essere limitata o non disponibile. Gli endpoint web permettono di eseguire i cron job tramite richieste HTTPS, che sono universalmente supportate.

## Configurazione

### 1. Generare un Token Segreto

Prima di tutto, devi generare un token segreto per autenticare le richieste ai cron job.

```php
// Genera un token casuale sicuro (64 caratteri)
echo bin2hex(random_bytes(32));
```

Oppure usa un generatore online affidabile o questo comando bash:
```bash
openssl rand -hex 32
```

### 2. Configurare il Token in config.php

Aggiungi il token generato al file `config/config.php`:

```php
'cron' => [
    'secret_token' => 'IL_TUO_TOKEN_SEGRETO_QUI', // OBBLIGATORIO
    'allow_cli' => true,  // Permetti esecuzione via CLI
    'allow_web' => true,  // Permetti esecuzione via web
    'allowed_ips' => [],  // Array vuoto = consenti tutti gli IP
                          // Oppure limita: ['127.0.0.1', '192.168.1.100']
],
```

### 3. Proteggere la Directory (Opzionale ma Consigliato)

Se vuoi un ulteriore livello di sicurezza, puoi proteggere questa directory con `.htaccess`:

```apache
# .htaccess in public/cron/
# Blocca accesso diretto senza parametri
RewriteEngine On
RewriteCond %{QUERY_STRING} !token=
RewriteRule .* - [F,L]
```

## Endpoint Disponibili

Tutti gli endpoint seguono lo stesso pattern:

```
https://tuosito.com/public/cron/NOME_CRON.php?token=IL_TUO_TOKEN_SEGRETO
```

### Lista Completa degli Endpoint

| Endpoint | Descrizione | Frequenza Consigliata |
|----------|-------------|----------------------|
| `email_queue.php` | Processa la coda email | Ogni 5 minuti |
| `vehicle_alerts.php` | Alert scadenze mezzi | Giornaliero (08:00) |
| `scheduler_alerts.php` | Alert scadenzario | Giornaliero (08:00) |
| `member_expiry_alerts.php` | Alert scadenze soci | Giornaliero (08:00) |
| `health_surveillance_alerts.php` | Alert visite mediche | Giornaliero (08:00) |
| `annual_member_verification.php` | Verifica annuale soci | 7 gennaio (09:00) |
| `backup.php` | Backup database | Giornaliero (02:00) |
| `sync_all_expiry_dates.php` | Sincronizza scadenze | Settimanale |

## Configurazione su Aruba Hosting

### Metodo 1: Cron Job tramite Pannello di Controllo

1. Accedi al pannello di controllo Aruba
2. Vai alla sezione "Cron Job" o "Operazioni Pianificate"
3. Crea un nuovo cron job per ogni endpoint

**Esempio per Email Queue (ogni 5 minuti):**
```
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN"
Frequenza: */5 * * * *
```

**Esempio per Vehicle Alerts (giornaliero alle 08:00):**
```
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/vehicle_alerts.php?token=IL_TUO_TOKEN"
Frequenza: 0 8 * * *
```

### Metodo 2: Usando curl invece di wget

Se wget non è disponibile, usa curl:

```bash
curl -s "https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN" > /dev/null
```

### Metodo 3: Servizi di Cron Job Esterni

Puoi usare servizi esterni gratuiti come:
- **EasyCron** (https://www.easycron.com/)
- **Cron-Job.org** (https://cron-job.org/)
- **SetCronJob** (https://www.setcronjob.com/)

Questi servizi invieranno richieste HTTP ai tuoi endpoint secondo la pianificazione configurata.

## Configurazione Completa per Aruba

### Configurazione Email Queue (ogni 5 minuti)
```
Tipo: Comando Web
URL: https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN
Frequenza: */5 * * * *
```

### Configurazione Alert Giornalieri (alle 08:00)
```
# Vehicle Alerts
URL: https://tuosito.com/public/cron/vehicle_alerts.php?token=IL_TUO_TOKEN
Frequenza: 0 8 * * *

# Scheduler Alerts
URL: https://tuosito.com/public/cron/scheduler_alerts.php?token=IL_TUO_TOKEN
Frequenza: 0 8 * * *

# Member Expiry Alerts
URL: https://tuosito.com/public/cron/member_expiry_alerts.php?token=IL_TUO_TOKEN
Frequenza: 0 8 * * *

# Health Surveillance Alerts
URL: https://tuosito.com/public/cron/health_surveillance_alerts.php?token=IL_TUO_TOKEN
Frequenza: 0 8 * * *
```

### Configurazione Backup (alle 02:00)
```
URL: https://tuosito.com/public/cron/backup.php?token=IL_TUO_TOKEN
Frequenza: 0 2 * * *
```

### Configurazione Verifica Annuale (7 gennaio alle 09:00)
```
URL: https://tuosito.com/public/cron/annual_member_verification.php?token=IL_TUO_TOKEN
Frequenza: 0 9 7 1 *
```

## Test Manuale

Puoi testare un endpoint manualmente visitando l'URL nel browser o usando curl:

```bash
# Test via curl
curl "https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN"

# Test via wget
wget -O - "https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN"
```

### Risposta di Successo
```json
{
    "success": true,
    "cron_job": "email_queue",
    "message": "Cron job executed successfully",
    "output": "Processed 5 emails\n",
    "timestamp": "2024-01-15 10:30:00"
}
```

### Risposta di Errore
```json
{
    "success": false,
    "cron_job": "email_queue",
    "error": "Invalid authentication token",
    "timestamp": "2024-01-15 10:30:00"
}
```

## Sicurezza

### Best Practices

1. **Token Segreto Forte**: Usa un token di almeno 32 caratteri casuali
2. **HTTPS**: Usa sempre HTTPS per proteggere il token in transito
3. **IP Whitelist**: Se possibile, limita gli IP autorizzati in `config.php`
4. **Rotazione Token**: Cambia il token periodicamente
5. **Monitoraggio**: Controlla i log per accessi non autorizzati

### Log degli Accessi

Gli accessi falliti vengono registrati automaticamente nel log degli errori PHP.
Controlla `/var/log/php-errors.log` o il log configurato nel tuo hosting.

### Protezione da Brute Force

Il sistema usa `hash_equals()` per prevenire timing attacks. Per ulteriore protezione:
- Monitora i tentativi falliti
- Implementa rate limiting a livello di firewall/CDN
- Usa una whitelist IP rigorosa

## Troubleshooting

### Problema: "Invalid authentication token"
**Soluzione**: Verifica che il token in config.php corrisponda esattamente al token nell'URL

### Problema: "Cron secret token not configured"
**Soluzione**: Aggiungi il parametro `cron.secret_token` nel file `config/config.php`

### Problema: "Web-based cron execution is disabled"
**Soluzione**: Imposta `cron.allow_web` a `true` in `config/config.php`

### Problema: "Access denied: IP not whitelisted"
**Soluzione**: Aggiungi l'IP del server Aruba alla whitelist in `config.php` oppure svuota l'array `allowed_ips`

### Problema: Cron job non si esegue
**Soluzioni**:
1. Verifica che l'URL sia accessibile dal browser
2. Controlla i log del pannello Aruba per errori di esecuzione
3. Testa manualmente l'endpoint con curl
4. Verifica che HTTPS sia configurato correttamente

## Differenze tra CLI e Web

### Esecuzione CLI (tradizionale)
```bash
php /path/to/easyvol/cron/email_queue.php
```

**Vantaggi:**
- Più veloce
- Accesso diretto al filesystem
- Nessun overhead HTTP

**Svantaggi:**
- Potrebbe non essere disponibile su hosting condiviso
- Richiede configurazione PHP CLI separata

### Esecuzione Web (HTTP/HTTPS)
```bash
curl "https://tuosito.com/public/cron/email_queue.php?token=TOKEN"
```

**Vantaggi:**
- Funziona su tutti gli hosting
- Può essere chiamato da servizi esterni
- Facile da testare e debuggare

**Svantaggi:**
- Overhead HTTP
- Richiede autenticazione
- Timeout potenziali su operazioni lunghe

## Compatibilità

Questi endpoint sono compatibili con:
- ✅ Aruba Hosting
- ✅ SiteGround
- ✅ HostGator
- ✅ BlueHost
- ✅ Qualsiasi hosting con supporto HTTP/HTTPS
- ✅ Servizi esterni di cron job
- ✅ Server VPS/Dedicati

## Supporto

Per problemi o domande:
1. Controlla questa documentazione
2. Verifica i log di sistema
3. Apri una issue su GitHub
4. Consulta la documentazione principale in `/cron/README.md`
