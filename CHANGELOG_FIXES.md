# Changelog - Correzioni e Miglioramenti

Data: 7 Dicembre 2024

## Problemi Risolti

### A) Dimensione Pulsanti Sidebar Inconsistente ✅
**Problema**: I pulsanti sulla barra di sinistra erano più grandi nella dashboard e si rimpicciolivano quando si aprivano le varie schede (Soci, Cadetti, etc.).

**Soluzione**: 
- Aggiunto CSS con dimensione fissa per i link della sidebar (`font-size: 0.9rem`)
- Aggiunto dimensione fissa per le icone (`font-size: 1rem`)
- File modificato: `src/Views/includes/sidebar.php`

### B) Riduzione Dimensione Generale del 10% ✅
**Problema**: Richiesta di ridurre il contenuto generale del 10% (contatori, schermate, form, etc.).

**Soluzione**:
- Ridotto font-size del body da 14px a 12.6px (riduzione del 10%)
- Ridotto font-size dei form controls e label del 10%
- Ridotto padding dei form controls del 10%
- Ridotto font-size del contenuto delle card del 10%
- File modificato: `assets/css/main.css`

### C) Funzionalità Riunioni Migliorata ✅
**Problema**: Le riunioni necessitavano di:
- Aggiunta partecipanti tra soci maggiorenni e minorenni attivi
- Possibilità di inserire più ordini del giorno
- Descrizione e discussione per ogni ordine del giorno
- Esito votazione (votanti, favorevoli, contrari, astenuti, esito)
- Data e ora fine oltre a data e ora inizio

**Soluzione**:
- **Aggiunta Partecipanti**: 
  - Selettore dinamico per soci adulti e cadetti attivi
  - Possibilità di aggiungere/rimuovere partecipanti
  - JavaScript per gestire l'interfaccia dinamica

- **Ordini del Giorno Multipli**:
  - Interfaccia per aggiungere/rimuovere ordini del giorno dinamicamente
  - Campi per oggetto, descrizione, discussione
  - Sistema di numerazione automatica

- **Gestione Votazioni**:
  - Checkbox per indicare se c'è stata votazione
  - Campi per: votanti totali, favorevoli, contrari, astenuti
  - Dropdown per esito (Approvato, Respinto, Non Votato)
  - Campi votazione mostrati/nascosti dinamicamente

- **Data/Ora Fine**:
  - Aggiunto campo per ora inizio
  - Aggiunto campo per ora fine
  - Separazione tra data e orari

- **Database**:
  - Aggiornato schema `meeting_agenda` con campi votazione
  - Creato script di migrazione `database_migration_meetings.sql`
  - Aggiunto file `MIGRATION_INSTRUCTIONS.md` con istruzioni

- File modificati: 
  - `public/meeting_edit.php` (completamente riscritto)
  - `database_schema.sql`
  - Creati: `database_migration_meetings.sql`, `MIGRATION_INSTRUCTIONS.md`

### D) Notifiche Hardcoded Rimosse ✅
**Problema**: Nella barra in alto c'erano 3 notifiche hardcoded che non esistevano e non si potevano cancellare.

**Soluzione**:
- Rimosso badge numerico fisso (3)
- Rimossi link notifiche hardcoded
- Lasciato dropdown con messaggio "Nessuna notifica"
- Le notifiche saranno implementate dinamicamente in futuro
- File modificato: `src/Views/includes/navbar.php`

### E) Badge Domande Iscrizione Corretto ✅
**Problema**: Badge con numero 3 hardcoded nelle domande iscrizione.

**Soluzione**:
- Implementato conteggio dinamico delle domande pending
- Badge mostrato solo se ci sono domande pending
- Corretto nome tabella da `applications` a `member_applications`
- File modificato: `src/Views/includes/sidebar.php`

### F) Pagina Profile.php Mancante ✅
**Problema**: Non esisteva profile.php, generava errore "file non trovato".

**Soluzione**:
- Creata pagina `public/profile.php` completa
- Funzionalità implementate:
  - Visualizzazione dati profilo utente corrente
  - Modifica nome completo ed email
  - Cambio password con verifica password attuale
  - Protezione CSRF
  - Validazione campi
  - Informazioni account (stato, data creazione, ultimo accesso)
- File creato: `public/profile.php`

### G) Campi Soci Maggiorenni Corretti ✅
**Problema**: 
- Campo "Qualifica" non necessario
- In "Tipo Socio" servivano solo Ordinario o Fondatore
- In "Stato" serviva "Decaduto" invece di "Deceduto"

**Soluzione**:
- **Qualifica**: Rinominato in "Stato Volontario" con valori corretti:
  - In Formazione
  - Operativo
  - Non Operativo
  
- **Tipo Socio**: Ridotto a due opzioni:
  - Ordinario
  - Fondatore
  
- **Stato**: Sostituito "Deceduto" con "Decaduto":
  - Attivo
  - Sospeso
  - Dimesso
  - Decaduto (invece di Deceduto)

- File modificato: `public/member_edit.php`

### H) Upload Documenti Corretto ✅
**Problema**: Non funzionava l'upload dei documenti.

**Soluzione**:
- Corretto uso della classe FileUploader
- Creata directory `uploads/documents/` con permessi corretti
- Aggiunto supporto per MIME types corretti
- Implementata validazione dimensione file (max 50MB)
- Creato file `.htaccess` in uploads per sicurezza
- File modificati: 
  - `public/document_edit.php`
  - Creati: `uploads/documents/`, `uploads/.htaccess`

## File Creati

1. `public/profile.php` - Pagina profilo utente
2. `database_migration_meetings.sql` - Script migrazione database
3. `MIGRATION_INSTRUCTIONS.md` - Istruzioni per migrazione
4. `uploads/.htaccess` - Protezione directory uploads
5. `uploads/documents/` - Directory per documenti
6. `CHANGELOG_FIXES.md` - Questo file

## File Modificati

1. `assets/css/main.css` - Riduzione dimensioni e fix consistenza
2. `src/Views/includes/sidebar.php` - Fix dimensione pulsanti e badge dinamico
3. `src/Views/includes/navbar.php` - Rimozione notifiche hardcoded
4. `public/member_edit.php` - Correzione campi soci
5. `public/meeting_edit.php` - Riscrittura completa con nuove funzionalità
6. `public/document_edit.php` - Fix upload documenti
7. `database_schema.sql` - Aggiornamento schema meeting_agenda

## Istruzioni Post-Deploy

1. **Migrare il Database**:
   ```bash
   mysql -u username -p database_name < database_migration_meetings.sql
   ```
   Oppure seguire le istruzioni in `MIGRATION_INSTRUCTIONS.md`

2. **Verificare Permessi Directory**:
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/documents/
   ```

3. **Verificare Configurazione PHP**:
   - Assicurarsi che `upload_max_filesize` sia almeno 50M
   - Assicurarsi che `post_max_size` sia almeno 50M

4. **Testare Funzionalità**:
   - Creare una nuova riunione con partecipanti e ordini del giorno
   - Caricare un documento
   - Accedere alla pagina profilo
   - Creare un nuovo socio e verificare i campi

## Note Tecniche

- Tutte le modifiche sono backward compatible
- Il database può essere facilmente rollback se necessario
- Nessuna dipendenza esterna aggiunta
- Codice conforme agli standard PSR esistenti nel progetto
- Implementata protezione CSRF su tutti i form nuovi/modificati
- Validazione lato client e server implementata

## Problemi Noti e Limitazioni

1. **User Creation (Issue E)**: Il codice per la creazione utenti era già corretto. Se ci sono problemi, verificare:
   - Permessi database dell'utente
   - Che la tabella `users` esista
   - Che i log di PHP per errori specifici
   
2. **Notifiche**: Il sistema di notifiche è stato preparato ma richiede implementazione backend completa per funzionalità avanzate

3. **Migrazione Database**: Deve essere eseguita manualmente - non c'è auto-migrazione

## Sicurezza

- Aggiunto `.htaccess` in uploads per prevenire esecuzione PHP
- Validazione MIME type per upload documenti
- Protezione CSRF su tutti i form
- Sanitizzazione input implementata
- Prepared statements utilizzati per tutte le query

## Performance

- Query ottimizzate con indici esistenti
- JavaScript minimale per funzionalità dinamiche
- Caricamento lazy dei partecipanti solo quando necessario
- Nessun impatto negativo su prestazioni esistenti

## Compatibilità

- PHP 8.0+
- MySQL 5.6+ / MariaDB 10.3+
- Browser moderni (Chrome, Firefox, Safari, Edge)
- Responsive design mantenuto

## Supporto

Per problemi o domande:
1. Controllare i log di PHP: `/var/log/php/error.log`
2. Controllare i log di MySQL
3. Verificare permessi file e directory
4. Consultare `MIGRATION_INSTRUCTIONS.md` per problemi database
