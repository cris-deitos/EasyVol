# EasyVol - Cron Jobs

Questo documento descrive i cron jobs necessari per il funzionamento automatico di EasyVol.

## Cron Jobs Disponibili

### 1. Email Queue Processor
**File**: `email_queue.php`
**Frequenza**: Ogni 5 minuti
**Descrizione**: Processa la coda delle email da inviare

```bash
*/5 * * * * php /percorso/easyvol/cron/email_queue.php >> /var/log/easyvol/email_queue.log 2>&1
```

### 2. Vehicle Alerts
**File**: `vehicle_alerts.php`
**Frequenza**: Giornaliero alle 8:00
**Descrizione**: Controlla scadenze mezzi (assicurazioni, revisioni, manutenzioni) e invia alert

```bash
0 8 * * * php /percorso/easyvol/cron/vehicle_alerts.php >> /var/log/easyvol/vehicle_alerts.log 2>&1
```

### 3. Scheduler Alerts
**File**: `scheduler_alerts.php`
**Frequenza**: Giornaliero alle 8:00
**Descrizione**: Invia reminder per scadenze in arrivo e aggiorna scadenze scadute

```bash
0 8 * * * php /percorso/easyvol/cron/scheduler_alerts.php >> /var/log/easyvol/scheduler_alerts.log 2>&1
```

### 4. Member Expiry Alerts
**File**: `member_expiry_alerts.php`
**Frequenza**: Giornaliero alle 8:00
**Descrizione**: Controlla scadenze patenti, qualifiche e corsi dei soci e invia alert

```bash
0 8 * * * php /percorso/easyvol/cron/member_expiry_alerts.php >> /var/log/easyvol/member_expiry_alerts.log 2>&1
```

### 5. Health Surveillance Alerts
**File**: `health_surveillance_alerts.php`
**Frequenza**: Giornaliero alle 8:00
**Descrizione**: Controlla scadenze visite mediche di sorveglianza sanitaria e invia alert

```bash
0 8 * * * php /percorso/easyvol/cron/health_surveillance_alerts.php >> /var/log/easyvol/health_surveillance_alerts.log 2>&1
```

### 6. Annual Member Verification
**File**: `annual_member_verification.php`
**Frequenza**: Annuale, 7 gennaio alle 9:00
**Descrizione**: Invia email di verifica dati anagrafici a tutti i soci attivi

```bash
0 9 7 1 * php /percorso/easyvol/cron/annual_member_verification.php >> /var/log/easyvol/annual_verification.log 2>&1
```

### 7. Database Backup
**File**: `backup.php`
**Frequenza**: Giornaliero alle 2:00
**Descrizione**: Crea backup automatico del database (mantiene ultimi 30 giorni)

```bash
0 2 * * * php /percorso/easyvol/cron/backup.php >> /var/log/easyvol/backup.log 2>&1
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

- PHP CLI installato
- Accesso al database MySQL/MariaDB
- mysqldump disponibile per i backup
- gzip disponibile per la compressione backup
- Permessi di scrittura sulle directory necessarie

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

Puoi eseguire manualmente ogni script per testarlo:

```bash
php /var/www/easyvol/cron/email_queue.php
php /var/www/easyvol/cron/vehicle_alerts.php
php /var/www/easyvol/cron/backup.php
```

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
