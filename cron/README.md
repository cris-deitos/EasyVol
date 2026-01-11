# EasyVol - Cron Jobs

Questo documento descrive i cron jobs necessari per il funzionamento automatico di EasyVol.

## ⚡ Esecuzione via HTTPS per Hosting Aruba

**IMPORTANTE per utenti Aruba e hosting condiviso**: Se hai difficoltà nell'eseguire cron job via PHP CLI, puoi utilizzare gli endpoint web che permettono l'esecuzione tramite HTTPS.

📖 **Consulta la guida completa**: [public/cron/README.md](../public/cron/README.md)

Esempio di esecuzione via HTTPS su Aruba:
```bash
# Nel pannello cron di Aruba, usa wget o curl:
wget -q -O /dev/null "https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN"
```

**Vantaggi dell'esecuzione HTTPS:**
- ✅ Funziona su tutti gli hosting condivisi (incluso Aruba)
- ✅ Nessuna configurazione PHP CLI richiesta
- ✅ Facile da testare dal browser
- ✅ Compatibile con servizi esterni di cron job

## Cron Jobs Disponibili

### 1. Email Queue Processor
**File**: `email_queue.php`
**Frequenza**: Ogni 5 minuti
**Descrizione**: Processa la coda delle email da inviare

**Esecuzione CLI (metodo tradizionale):**
```bash
*/5 * * * * php /percorso/easyvol/cron/email_queue.php >> /var/log/easyvol/email_queue.log 2>&1
```

**Esecuzione HTTPS (consigliato per Aruba):**
```bash
*/5 * * * * wget -q -O /dev/null "https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN"
```

### 2. Vehicle Alerts
**File**: `vehicle_alerts.php`
**Frequenza**: Giornaliero alle 8:00
**Descrizione**: Controlla scadenze mezzi (assicurazioni, revisioni, manutenzioni) e invia alert

**Esecuzione CLI (metodo tradizionale):**
```bash
0 8 * * * php /percorso/easyvol/cron/vehicle_alerts.php >> /var/log/easyvol/vehicle_alerts.log 2>&1
```

**Esecuzione HTTPS (consigliato per Aruba):**
```bash
0 8 * * * wget -q -O /dev/null "https://tuosito.com/public/cron/vehicle_alerts.php?token=IL_TUO_TOKEN"
```

### 3. Scheduler Alerts
**File**: `scheduler_alerts.php`
**Frequenza**: Giornaliero alle 8:00
**Descrizione**: Invia reminder per scadenze in arrivo e aggiorna scadenze scadute

**Esecuzione CLI (metodo tradizionale):**
```bash
0 8 * * * php /percorso/easyvol/cron/scheduler_alerts.php >> /var/log/easyvol/scheduler_alerts.log 2>&1
```

**Esecuzione HTTPS (consigliato per Aruba):**
```bash
0 8 * * * wget -q -O /dev/null "https://tuosito.com/public/cron/scheduler_alerts.php?token=IL_TUO_TOKEN"
```

### 4. Member Expiry Alerts
**File**: `member_expiry_alerts.php`
**Frequenza**: Giornaliero alle 8:00
**Descrizione**: Controlla scadenze patenti, qualifiche e corsi dei soci e invia alert

**Esecuzione CLI (metodo tradizionale):**
```bash
0 8 * * * php /percorso/easyvol/cron/member_expiry_alerts.php >> /var/log/easyvol/member_expiry_alerts.log 2>&1
```

**Esecuzione HTTPS (consigliato per Aruba):**
```bash
0 8 * * * wget -q -O /dev/null "https://tuosito.com/public/cron/member_expiry_alerts.php?token=IL_TUO_TOKEN"
```

### 5. Health Surveillance Alerts
**File**: `health_surveillance_alerts.php`
**Frequenza**: Giornaliero alle 8:00
**Descrizione**: Controlla scadenze visite mediche di sorveglianza sanitaria e invia alert

**Esecuzione CLI (metodo tradizionale):**
```bash
0 8 * * * php /percorso/easyvol/cron/health_surveillance_alerts.php >> /var/log/easyvol/health_surveillance_alerts.log 2>&1
```

**Esecuzione HTTPS (consigliato per Aruba):**
```bash
0 8 * * * wget -q -O /dev/null "https://tuosito.com/public/cron/health_surveillance_alerts.php?token=IL_TUO_TOKEN"
```

### 6. Annual Member Verification
**File**: `annual_member_verification.php`
**Frequenza**: Annuale, 7 gennaio alle 9:00
**Descrizione**: Invia email di verifica dati anagrafici a tutti i soci attivi

**Esecuzione CLI (metodo tradizionale):**
```bash
0 9 7 1 * php /percorso/easyvol/cron/annual_member_verification.php >> /var/log/easyvol/annual_verification.log 2>&1
```

**Esecuzione HTTPS (consigliato per Aruba):**
```bash
0 9 7 1 * wget -q -O /dev/null "https://tuosito.com/public/cron/annual_member_verification.php?token=IL_TUO_TOKEN"
```

### 7. Database Backup
**File**: `backup.php`
**Frequenza**: Giornaliero alle 2:00
**Descrizione**: Crea backup automatico del database (mantiene ultimi 30 giorni)

**Esecuzione CLI (metodo tradizionale):**
```bash
0 2 * * * php /percorso/easyvol/cron/backup.php >> /var/log/easyvol/backup.log 2>&1
```

**Esecuzione HTTPS (consigliato per Aruba):**
```bash
0 2 * * * wget -q -O /dev/null "https://tuosito.com/public/cron/backup.php?token=IL_TUO_TOKEN"
```

## Installazione Cron Jobs

### Metodo 1: Crontab Utente

1. Apri crontab dell'utente:
```bash
crontab -e
```

2. Aggiungi i cron jobs:
```bash
# EasyVol - Email Queue
*/5 * * * * php /var/www/easyvol/cron/email_queue.php >> /var/log/easyvol/email_queue.log 2>&1

# EasyVol - Vehicle Alerts
0 8 * * * php /var/www/easyvol/cron/vehicle_alerts.php >> /var/log/easyvol/vehicle_alerts.log 2>&1

# EasyVol - Scheduler Alerts
0 8 * * * php /var/www/easyvol/cron/scheduler_alerts.php >> /var/log/easyvol/scheduler_alerts.log 2>&1

# EasyVol - Member Expiry Alerts
0 8 * * * php /var/www/easyvol/cron/member_expiry_alerts.php >> /var/log/easyvol/member_expiry_alerts.log 2>&1

# EasyVol - Health Surveillance Alerts
0 8 * * * php /var/www/easyvol/cron/health_surveillance_alerts.php >> /var/log/easyvol/health_surveillance_alerts.log 2>&1

# EasyVol - Annual Member Verification (January 7th)
0 9 7 1 * php /var/www/easyvol/cron/annual_member_verification.php >> /var/log/easyvol/annual_verification.log 2>&1

# EasyVol - Database Backup
0 2 * * * php /var/www/easyvol/cron/backup.php >> /var/log/easyvol/backup.log 2>&1
```

3. Salva e esci

### Metodo 2: File in /etc/cron.d/

1. Crea file `/etc/cron.d/easyvol`:
```bash
sudo nano /etc/cron.d/easyvol
```

2. Inserisci contenuto:
```bash
# EasyVol Cron Jobs
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

# Email Queue - Ogni 5 minuti
*/5 * * * * www-data php /var/www/easyvol/cron/email_queue.php >> /var/log/easyvol/email_queue.log 2>&1

# Vehicle Alerts - Giornaliero alle 8:00
0 8 * * * www-data php /var/www/easyvol/cron/vehicle_alerts.php >> /var/log/easyvol/vehicle_alerts.log 2>&1

# Scheduler Alerts - Giornaliero alle 8:00
0 8 * * * www-data php /var/www/easyvol/cron/scheduler_alerts.php >> /var/log/easyvol/scheduler_alerts.log 2>&1

# Member Expiry Alerts - Giornaliero alle 8:00
0 8 * * * www-data php /var/www/easyvol/cron/member_expiry_alerts.php >> /var/log/easyvol/member_expiry_alerts.log 2>&1

# Health Surveillance Alerts - Giornaliero alle 8:00
0 8 * * * www-data php /var/www/easyvol/cron/health_surveillance_alerts.php >> /var/log/easyvol/health_surveillance_alerts.log 2>&1

# Annual Member Verification - 7 gennaio alle 9:00
0 9 7 1 * www-data php /var/www/easyvol/cron/annual_member_verification.php >> /var/log/easyvol/annual_verification.log 2>&1

# Database Backup - Giornaliero alle 2:00
0 2 * * * www-data php /var/www/easyvol/cron/backup.php >> /var/log/easyvol/backup.log 2>&1
```

3. Imposta permessi corretti:
```bash
sudo chmod 644 /etc/cron.d/easyvol
```

## Directory Log

Crea directory per i log:

```bash
sudo mkdir -p /var/log/easyvol
sudo chown www-data:www-data /var/log/easyvol
sudo chmod 755 /var/log/easyvol
```

## Directory Backup

I backup vengono salvati in `/percorso/easyvol/backups/`. Assicurati che la directory abbia i permessi corretti:

```bash
mkdir -p /var/www/easyvol/backups
chmod 750 /var/www/easyvol/backups
chown www-data:www-data /var/www/easyvol/backups
```

## Requisiti

### Per esecuzione CLI (metodo tradizionale)
- PHP CLI installato
- Accesso al database MySQL/MariaDB
- mysqldump disponibile per i backup
- gzip disponibile per la compressione backup
- Permessi di scrittura sulle directory necessarie

### Per esecuzione HTTPS (hosting condiviso come Aruba)
- HTTPS configurato sul dominio
- Token segreto configurato in `config/config.php`
- wget o curl disponibile nel pannello cron
- Nessun requisito PHP CLI necessario

## Verifica Funzionamento

Per verificare che i cron jobs siano attivi:

```bash
# Lista cron jobs attivi
crontab -l

# Oppure verifica file in /etc/cron.d/
cat /etc/cron.d/easyvol

# Verifica log cron di sistema
sudo grep CRON /var/log/syslog | grep easyvol
```

## Test Manuale

### Test esecuzione CLI
Puoi eseguire manualmente ogni script per testarlo:

```bash
php /var/www/easyvol/cron/email_queue.php
php /var/www/easyvol/cron/vehicle_alerts.php
php /var/www/easyvol/cron/backup.php
```

### Test esecuzione HTTPS
Puoi testare gli endpoint web con curl o dal browser:

```bash
# Test con curl
curl "https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN"

# Test con wget
wget -O - "https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN"

# Oppure apri l'URL nel browser per vedere la risposta JSON
```

## 🌐 Configurazione Specifica per Aruba Hosting

### Passo 1: Configurare il Token Segreto

1. Genera un token sicuro usando lo script fornito:
   ```bash
   # Dalla directory root del progetto
   php cron/generate_token.php
   ```
   
   Lo script genererà un token e mostrerà la configurazione completa da copiare.
   
   Oppure genera manualmente con:
   ```bash
   # Su Linux/Mac
   openssl rand -hex 32
   
   # Oppure in PHP
   php -r "echo bin2hex(random_bytes(32));"
   ```

2. Aggiungi il token in `config/config.php`:
   ```php
   'cron' => [
       'secret_token' => 'tuo_token_generato_qui',
       'allow_cli' => true,
       'allow_web' => true,
       'allowed_ips' => [], // Vuoto = accetta da tutti
   ],
   ```

### Passo 2: Configurare i Cron Job nel Pannello Aruba

1. Accedi al **Pannello di Controllo Aruba**
2. Vai alla sezione **"Gestione Avanzata" → "Cron Jobs"** (o "Operazioni Pianificate")
3. Per ogni cron job, crea una nuova pianificazione:

#### Email Queue (ogni 5 minuti)
```
Tipo: Comando personalizzato
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN"
Frequenza: */5 * * * *
```

#### Vehicle Alerts (giornaliero alle 8:00)
```
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/vehicle_alerts.php?token=IL_TUO_TOKEN"
Frequenza: 0 8 * * *
```

#### Scheduler Alerts (giornaliero alle 8:00)
```
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/scheduler_alerts.php?token=IL_TUO_TOKEN"
Frequenza: 0 8 * * *
```

#### Member Expiry Alerts (giornaliero alle 8:00)
```
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/member_expiry_alerts.php?token=IL_TUO_TOKEN"
Frequenza: 0 8 * * *
```

#### Health Surveillance Alerts (giornaliero alle 8:00)
```
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/health_surveillance_alerts.php?token=IL_TUO_TOKEN"
Frequenza: 0 8 * * *
```

#### Backup Database (giornaliero alle 2:00)
```
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/backup.php?token=IL_TUO_TOKEN"
Frequenza: 0 2 * * *
```

#### Verifica Annuale Soci (7 gennaio alle 9:00)
```
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/annual_member_verification.php?token=IL_TUO_TOKEN"
Frequenza: 0 9 7 1 *
```

### Passo 3: Test e Verifica

1. **Test manuale via browser**: Apri l'URL nel browser per verificare che funzioni
   ```
   https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN
   ```
   
   Dovresti vedere una risposta JSON simile a:
   ```json
   {
       "success": true,
       "cron_job": "email_queue",
       "message": "Cron job executed successfully",
       "output": "Processed 0 emails\n",
       "timestamp": "2024-01-15 10:30:00"
   }
   ```

2. **Verifica nei log di Aruba**: Dopo l'esecuzione pianificata, controlla i log nel pannello Aruba

3. **Verifica nei log dell'applicazione**: Controlla la tabella `activity_logs` nel database

### Problemi Comuni su Aruba

#### 1. "wget: command not found"
**Soluzione**: Usa `curl` invece di `wget`:
```bash
curl -s "https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN" > /dev/null
```

#### 2. Cron job non si esegue
**Soluzioni**:
- Verifica che HTTPS sia configurato correttamente
- Controlla che il token sia corretto
- Prova ad eseguire il comando manualmente dal terminale SSH di Aruba
- Verifica i log del pannello Aruba per eventuali errori

#### 3. "Invalid authentication token"
**Soluzione**: 
- Assicurati che il token in `config.php` sia identico a quello nell'URL
- Il token è case-sensitive e non deve avere spazi

#### 4. Timeout su operazioni lunghe
**Soluzione**: 
- Aumenta il timeout nel pannello Aruba se disponibile
- Per il backup, considera di eseguirlo in orari di basso traffico

### Alternative per Aruba

Se i cron job interni di Aruba non funzionano, puoi usare servizi esterni gratuiti:

1. **EasyCron** (https://www.easycron.com/) - 100 cron job gratis
2. **Cron-Job.org** (https://cron-job.org/) - Illimitati, gratis
3. **SetCronJob** (https://www.setcronjob.com/) - 5 cron job gratis

Questi servizi invieranno richieste HTTPS ai tuoi endpoint secondo la pianificazione configurata.

## Troubleshooting

### Email non vengono inviate

1. Verifica che `email_queue.php` sia in esecuzione:
```bash
grep email_queue /var/log/easyvol/email_queue.log
```

2. Controlla configurazione email in `config/config.php`

3. Verifica tabella `email_queue` nel database

### Backup non vengono creati

1. Verifica permessi directory backup
2. Controlla che mysqldump sia disponibile: `which mysqldump`
3. Verifica credenziali database
4. Controlla log: `tail -f /var/log/easyvol/backup.log`

### Cron non si eseguono

1. Verifica sintassi crontab: `crontab -l`
2. Controlla log di sistema: `sudo tail -f /var/log/syslog`
3. Verifica che PHP CLI sia installato: `php -v`
4. Controlla percorsi assoluti nei cron jobs

## Cron Jobs Aggiuntivi (da implementare)

Altri cron jobs previsti ma non ancora implementati:

- **Training Alerts**: Alert scadenze attestati formativi
- **Warehouse Alerts**: Alert scorte minime magazzino
- **Member Stats**: Statistiche periodiche soci

## Note di Sicurezza

- Non inserire password direttamente nei cron jobs
- Utilizza permessi restrittivi sui file di log (640)
- Limita accesso alla directory backup
- Considera crittografia per i backup sensibili
- Monitora regolarmente i log per anomalie

## Supporto

Per problemi o domande sui cron jobs, consulta la documentazione principale o apri una issue su GitHub.
