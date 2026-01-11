# Guida Rapida - Configurazione Cron Jobs su Aruba

Questa guida ti aiuterà a configurare i cron jobs di EasyVol su hosting Aruba in pochi semplici passaggi.

## 📋 Prerequisiti

- EasyVol installato e funzionante
- Accesso al pannello di controllo Aruba
- HTTPS configurato sul tuo dominio

## ⚡ Configurazione Rapida (5 minuti)

### Passo 1: Genera il Token Segreto

Dalla directory root del progetto, esegui:

```bash
php cron/generate_token.php
```

Vedrai un output simile a questo:

```
===========================================
EasyVol - Cron Job Token Generator
===========================================

Your secure token:

  609c011485cfbd8af099a21c506a40690f90babbee79e35d68f35d372f34f306

===========================================
Configuration:
===========================================

Add this to your config/config.php file:

'cron' => [
    'secret_token' => '609c011485cfbd8af099a21c506a40690f90babbee79e35d68f35d372f34f306',
    'allow_cli' => true,
    'allow_web' => true,
    'allowed_ips' => [],
],
```

**Copia il token generato** (sarà diverso ogni volta).

### Passo 2: Configura config.php

Apri il file `config/config.php` e aggiungi (o aggiorna) la sezione `cron`:

```php
'cron' => [
    'secret_token' => 'IL_TUO_TOKEN_GENERATO_QUI',
    'allow_cli' => true,
    'allow_web' => true,
    'allowed_ips' => [], // Lascia vuoto per accettare da tutti gli IP
],
```

**Salva il file**.

### Passo 3: Verifica la Configurazione

1. Apri il browser e vai a:
   ```
   https://tuosito.com/public/cron/
   ```

2. Dovresti vedere una pagina con lo stato della configurazione. Verifica che:
   - ✅ Secret Token: Configurato
   - ✅ Esecuzione Web (HTTPS): Abilitata

3. Copia gli URL dei cron job mostrati nella pagina (saranno simili a questo):
   ```
   https://tuosito.com/public/cron/email_queue.php?token=***TOKEN_NASCOSTO***
   ```

### Passo 4: Test Manuale

Prima di configurare i cron job, testa che funzionino:

```bash
# Sostituisci IL_TUO_TOKEN con il token generato
curl "https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN"
```

Dovresti ricevere una risposta JSON simile a:
```json
{
    "success": true,
    "cron_job": "email_queue",
    "message": "Cron job executed successfully",
    "output": "Processed 0 emails\n",
    "timestamp": "2024-01-15 10:30:00"
}
```

Se ricevi `"success": false`, controlla il messaggio di errore nella risposta.

### Passo 5: Configura i Cron Job su Aruba

1. **Accedi al Pannello di Controllo Aruba**
2. Vai alla sezione **"Gestione Avanzata" → "Cron Jobs"** o **"Operazioni Pianificate"**
3. **Crea i seguenti cron job:**

#### 📧 Email Queue (PRIORITÀ ALTA - ogni 5 minuti)
```
Tipo: Comando personalizzato
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN"
Frequenza: */5 * * * *
Descrizione: Processa coda email
```

#### 🚗 Vehicle Alerts (giornaliero alle 8:00)
```
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/vehicle_alerts.php?token=IL_TUO_TOKEN"
Frequenza: 0 8 * * *
Descrizione: Alert scadenze mezzi
```

#### 📅 Scheduler Alerts (giornaliero alle 8:00)
```
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/scheduler_alerts.php?token=IL_TUO_TOKEN"
Frequenza: 0 8 * * *
Descrizione: Reminder scadenze
```

#### 👥 Member Expiry Alerts (giornaliero alle 8:00)
```
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/member_expiry_alerts.php?token=IL_TUO_TOKEN"
Frequenza: 0 8 * * *
Descrizione: Alert scadenze soci
```

#### ⚕️ Health Surveillance Alerts (giornaliero alle 8:00)
```
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/health_surveillance_alerts.php?token=IL_TUO_TOKEN"
Frequenza: 0 8 * * *
Descrizione: Alert visite mediche
```

#### 💾 Database Backup (giornaliero alle 2:00)
```
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/backup.php?token=IL_TUO_TOKEN"
Frequenza: 0 2 * * *
Descrizione: Backup database
```

#### 📊 Annual Verification (7 gennaio alle 9:00)
```
Comando: wget -q -O /dev/null "https://tuosito.com/public/cron/annual_member_verification.php?token=IL_TUO_TOKEN"
Frequenza: 0 9 7 1 *
Descrizione: Verifica annuale soci
```

### Passo 6: Verifica il Funzionamento

Dopo aver configurato i cron job:

1. Attendi che il primo cron job si esegua (o eseguilo manualmente dal pannello Aruba)
2. Controlla i log nel pannello Aruba per verificare che non ci siano errori
3. Verifica nella tabella `activity_logs` del database che i cron siano stati eseguiti:
   ```sql
   SELECT * FROM activity_logs WHERE module = 'cron' ORDER BY created_at DESC LIMIT 10;
   ```

## 🔧 Risoluzione Problemi Comuni

### ❌ "Invalid authentication token"
**Causa**: Il token nell'URL non corrisponde a quello in config.php  
**Soluzione**: 
- Verifica che hai copiato il token completo
- Controlla che non ci siano spazi extra
- Il token è case-sensitive

### ❌ "Cron secret token not configured"
**Causa**: La sezione `cron` non è presente in config.php  
**Soluzione**: Aggiungi la configurazione come mostrato nel Passo 2

### ❌ wget: command not found
**Causa**: wget non è disponibile sul server Aruba  
**Soluzione**: Usa curl invece:
```bash
curl -s "https://tuosito.com/public/cron/email_queue.php?token=IL_TUO_TOKEN" > /dev/null
```

### ❌ Il cron non si esegue mai
**Soluzioni**:
1. Verifica che HTTPS sia configurato correttamente
2. Prova ad aprire l'URL nel browser per vedere se funziona
3. Controlla i log del pannello Aruba
4. Verifica che la sintassi della frequenza cron sia corretta

### ❌ Timeout durante l'esecuzione
**Causa**: Il cron job impiega troppo tempo  
**Soluzioni**:
- Per i backup, eseguili in orari di basso traffico
- Contatta il supporto Aruba per aumentare i timeout
- Considera di usare un servizio esterno per cron più lunghi

## 📊 Frequenza dei Cron Jobs Consigliata

| Cron Job | Frequenza | Importanza |
|----------|-----------|------------|
| Email Queue | Ogni 5 minuti | ⭐⭐⭐⭐⭐ Alta |
| Vehicle Alerts | Giornaliero 08:00 | ⭐⭐⭐⭐ Media-Alta |
| Scheduler Alerts | Giornaliero 08:00 | ⭐⭐⭐⭐ Media-Alta |
| Member Expiry Alerts | Giornaliero 08:00 | ⭐⭐⭐⭐ Media-Alta |
| Health Surveillance | Giornaliero 08:00 | ⭐⭐⭐⭐ Media-Alta |
| Database Backup | Giornaliero 02:00 | ⭐⭐⭐⭐⭐ Alta |
| Annual Verification | 7 gennaio 09:00 | ⭐⭐⭐ Media |
| Sync Expiry Dates | Settimanale | ⭐⭐ Bassa |

**Nota**: Se hai limiti sul numero di cron job su Aruba, inizia con Email Queue e Database Backup, poi aggiungi gli altri.

## 🌐 Alternative a Aruba Cron Jobs

Se i cron job integrati di Aruba non funzionano o hai raggiunto il limite, puoi usare servizi esterni gratuiti:

### 1. EasyCron (https://www.easycron.com/)
- ✅ 100 cron job gratuiti
- ✅ Frequenza minima: 1 minuto
- ✅ Interfaccia user-friendly
- ✅ Notifiche email su errori

### 2. Cron-Job.org (https://cron-job.org/)
- ✅ Cron illimitati gratuiti
- ✅ Frequenza minima: 1 minuto
- ✅ Monitoring e statistiche
- ✅ Nessuna registrazione carta di credito

### 3. SetCronJob (https://www.setcronjob.com/)
- ✅ 5 cron job gratuiti
- ✅ Frequenza minima: 5 minuti
- ✅ Semplice da usare

**Come usarli:**
1. Registrati sul servizio
2. Aggiungi l'URL del cron job (es: `https://tuosito.com/public/cron/email_queue.php?token=TOKEN`)
3. Configura la frequenza desiderata
4. Salva e attiva

## 📚 Documentazione Completa

Per informazioni più dettagliate, consulta:

- **[public/cron/README.md](public/cron/README.md)** - Guida completa agli endpoint web
- **[cron/README.md](cron/README.md)** - Documentazione generale cron jobs
- **Status Page**: https://tuosito.com/public/cron/ - Verifica configurazione

## 🆘 Serve Aiuto?

1. Controlla la documentazione completa nei link sopra
2. Verifica i log di sistema e del database
3. Apri una issue su GitHub con i dettagli del problema
4. Includi sempre:
   - Messaggio di errore completo
   - Configurazione cron (senza il token!)
   - Log rilevanti

## ✅ Checklist Finale

Prima di considerare la configurazione completa, verifica:

- [ ] Token generato e configurato in config.php
- [ ] Status page mostra tutto verde
- [ ] Test manuale con curl funziona
- [ ] Tutti i cron job configurati nel pannello Aruba
- [ ] Primo cron job eseguito con successo
- [ ] Log nel database mostrano le esecuzioni
- [ ] Email di test ricevute (se email queue funziona)
- [ ] Backup creato nella directory backups/

**Congratulazioni! I tuoi cron job sono configurati correttamente! 🎉**
