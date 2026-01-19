# Sistema di Rilevamento Anomalie - EasyVol

## Descrizione

Il sistema di rilevamento anomalie permette di identificare rapidamente problemi e dati mancanti nei profili dei soci e dei cadetti dell'associazione.

## Funzionalità

### Per Soci Maggiorenni (Members)

Il sistema controlla le seguenti anomalie:

1. **Soci senza Numero di Cellulare** - Identifica i soci che non hanno un numero di cellulare registrato
2. **Soci senza Email** - Identifica i soci che non hanno un indirizzo email registrato
3. **Codici Fiscali Non Validi** - Verifica la validità dei codici fiscali contro i dati anagrafici (data di nascita, sesso)
4. **Sorveglianza Sanitaria Assente** - Identifica i soci attivi senza sorveglianza sanitaria
5. **Sorveglianza Sanitaria Scaduta** - Identifica i soci con sorveglianza sanitaria scaduta
6. **Patenti Scadute** - Identifica le patenti dei soci che sono scadute
7. **Corsi Scaduti** - Identifica i corsi con scadenza che sono scaduti

### Per Soci Minorenni (Junior Members/Cadetti)

Il sistema controlla le seguenti anomalie:

1. **Cadetti senza Numero di Cellulare** - Identifica i cadetti che non hanno un numero di cellulare registrato
2. **Cadetti senza Email** - Identifica i cadetti che non hanno un indirizzo email registrato
3. **Codici Fiscali Non Validi** - Verifica la validità dei codici fiscali contro i dati anagrafici
4. **Cadetti senza Dati Genitori/Tutori** - Identifica i cadetti che non hanno dati dei genitori o tutori
5. **Sorveglianza Sanitaria Assente** - Identifica i cadetti attivi senza sorveglianza sanitaria
6. **Sorveglianza Sanitaria Scaduta** - Identifica i cadetti con sorveglianza sanitaria scaduta

## Come Accedere

1. Accedere a EasyVol con le credenziali
2. Navigare alla pagina "Gestione Soci" o "Gestione Soci Minorenni"
3. Cliccare sul pulsante **"Anomalie"** (icona triangolo giallo di avviso) nella barra degli strumenti
4. La pagina mostrerà tutte le anomalie rilevate raggruppate per tipo

## Permessi Richiesti

Per accedere alla funzionalità di rilevamento anomalie, l'utente deve avere i seguenti permessi:

- **members:view_anomalies** - Per visualizzare le anomalie dei soci maggiorenni
- **junior_members:view_anomalies** - Per visualizzare le anomalie dei soci minorenni

Questi permessi possono essere assegnati a qualsiasi ruolo o utente attraverso l'interfaccia di gestione dei permessi. Gli amministratori possono gestire chi ha accesso a questa funzionalità assegnando i permessi appropriati ai ruoli o agli utenti specifici.

## Validazione Codice Fiscale

Il sistema utilizza un validatore avanzato del codice fiscale italiano che:

1. Verifica il formato (16 caratteri alfanumerici nella struttura corretta)
2. Verifica il checksum (carattere di controllo finale)
3. Confronta il codice fiscale con i dati anagrafici:
   - Anno di nascita (ultime 2 cifre)
   - Mese di nascita (lettera codificata)
   - Giorno di nascita (con +40 per le donne)
   - Sesso (M/F)

## Come Risolvere le Anomalie

Per ogni anomalia rilevata, è possibile:

1. Cliccare sul pulsante **"Modifica"** per aggiornare i dati del socio/cadetto
2. Aggiungere le informazioni mancanti
3. Correggere eventuali errori nei dati anagrafici
4. Rinnovare certificazioni scadute

## Installazione e Configurazione

### Requisiti

- EasyVol già installato e funzionante
- MySQL 5.6+ o MySQL 8.x
- PHP 8.4+

### Passi per l'Installazione

1. Assicurarsi che tutti i file siano stati aggiornati nel repository
2. Eseguire la migrazione del database:
   ```bash
   mysql -u username -p database_name < migrations/012_add_anomalies_permissions.sql
   ```
3. Verificare che i permessi siano stati creati correttamente
4. Assegnare i permessi agli utenti che devono accedere alle anomalie
5. Testare la funzionalità

## Risoluzione Problemi

### Problema: "Accesso negato"

**Soluzione**: Verificare che l'utente abbia i permessi corretti:
```sql
-- Verificare i permessi dell'utente
SELECT p.* FROM permissions p
INNER JOIN role_permissions rp ON p.id = rp.permission_id
INNER JOIN roles r ON r.id = rp.role_id
INNER JOIN users u ON u.role_id = r.id
WHERE u.username = 'nome_utente';
```

### Problema: Nessuna anomalia visualizzata ma ci sono problemi noti

**Soluzione**: 
- Verificare che i soci siano nello stato "attivo"
- Il sistema controlla solo i soci attivi, non quelli dimessi/decaduti
- Verificare che i dati siano correttamente inseriti nel database

### Problema: Errori nella validazione del codice fiscale

**Soluzione**:
- Verificare che il codice fiscale sia nel formato corretto (16 caratteri)
- Verificare che i dati anagrafici (data di nascita, sesso) siano corretti
- Il sistema è molto rigoroso nella validazione - anche piccoli errori nei dati vengono rilevati

## File Modificati/Creati

### Nuovi File
- `migrations/012_add_anomalies_permissions.sql` - Migrazione database per permessi
- `src/Utils/FiscalCodeValidator.php` - Validatore codice fiscale italiano
- `public/member_anomalies.php` - Pagina anomalie soci
- `public/junior_member_anomalies.php` - Pagina anomalie cadetti

### File Modificati
- `database_schema.sql` - Aggiunto inserimento permessi
- `src/Controllers/MemberController.php` - Aggiunto metodo `getAnomalies()`
- `src/Controllers/JuniorMemberController.php` - Aggiunto metodo `getAnomalies()`
- `public/members.php` - Aggiunto pulsante "Anomalie"
- `public/junior_members.php` - Aggiunto pulsante "Anomalie"

## Supporto Tecnico

Per problemi o domande relative al sistema di rilevamento anomalie, contattare il supporto tecnico EasyVol.

## Changelog

### Versione 1.0 (2026-01-19)
- Implementazione iniziale del sistema di rilevamento anomalie
- Validatore codice fiscale italiano
- Controlli per soci maggiorenni e minorenni
- Interfaccia utente completa
- Sistema di permessi granulare
