# EasyVol Bug Fixes Summary

## Problemi Risolti

Questo documento riassume i bug corretti in risposta alle seguenti problematiche:

### 1. Errori HTTP 500
**Problema**: Diversi moduli mostravano errori HTTP 500:
- Cadetti (Soci Minorenni)
- Domande Iscrizione
- Creazione Eventi/Interventi
- Creazione Scadenze
- Creazione Veicoli
- Centrale Operativa
- Report

**Causa**: Mancava il metodo `getUserId()` nella classe `App.php`, utilizzato da molti controller.

**Soluzione**: Aggiunto il metodo `getUserId()` in `src/App.php`.

### 2. Dati Associazione Non Visualizzati
**Problema**: In Impostazioni > Dati Associazione, i dati non venivano letti dal database.

**Causa**: L'applicazione non caricava i dati dell'associazione dalla tabella `association` del database.

**Soluzione**:
- Aggiunto metodo `loadAssociationData()` in `src/App.php` che carica i dati dal database
- Aggiornato `public/settings.php` per visualizzare correttamente tutti i campi (nome, indirizzo, città, codice fiscale, email, PEC)

### 3. Selezione Permessi Individuali per Utenti
**Problema**: Non era possibile selezionare i singoli permessi (70+ disponibili) per gli utenti, solo il ruolo.

**Causa**: Mancava la tabella `user_permissions` e l'interfaccia per gestire i permessi specifici degli utenti.

**Soluzione**:
- Creata tabella `user_permissions` nel database schema
- Aggiornato `public/login.php` per caricare sia i permessi del ruolo che quelli specifici dell'utente
- Aggiornato `public/user_edit.php` con interfaccia per selezionare i permessi individuali organizzati per modulo:
  - Soci
  - Soci Minorenni
  - Utenti
  - Riunioni
  - Mezzi
  - Magazzino
  - Corsi
  - Eventi
  - Documenti
  - Scadenze
  - Centrale Operativa
  - Domande Iscrizione
  - Report
  - Impostazioni

## File Modificati

1. **src/App.php**
   - Aggiunto metodo `loadAssociationData()` per caricare i dati dell'associazione dal database
   - Aggiunto metodo `getUserId()` per ottenere l'ID dell'utente corrente

2. **public/login.php**
   - Aggiornata logica per caricare sia i permessi del ruolo che quelli specifici dell'utente
   - I permessi vengono uniti con priorità ai permessi specifici dell'utente

3. **public/settings.php**
   - Aggiornata visualizzazione dati associazione per includere codice fiscale e PEC
   - Rimosso campo telefono (non presente nella tabella database)

4. **public/user_edit.php**
   - Aggiunta sezione per la selezione dei permessi individuali
   - Interfaccia organizzata per modulo con checkbox per ogni azione (visualizza, crea, modifica, elimina, report)
   - Gestione salvataggio permessi nella tabella `user_permissions`

5. **database_schema.sql**
   - Aggiunta tabella `user_permissions` per gestire i permessi specifici degli utenti

## File Creati

1. **database_migrations/001_add_user_permissions.sql**
   - Migration per creare la tabella `user_permissions` in database esistenti

## Istruzioni per il Deploy

### 1. Applicare le Modifiche al Database

Se il database è già esistente, eseguire la migration:

```bash
mysql -u username -p database_name < database_migrations/001_add_user_permissions.sql
```

Oppure eseguire manualmente la query:

```sql
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_permission` (`user_id`, `permission_id`),
  KEY `permission_id` (`permission_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Aggiornare i File del Codice

1. Fare il pull delle modifiche dal repository
2. Verificare che il file `config/config.php` esista (copiare da `config/config.sample.php` se necessario)
3. Verificare la configurazione del database in `config/config.php`

### 3. Test

Dopo il deploy, testare le seguenti funzionalità:

1. ✅ Impostazioni > Dati Associazione - verificare che i dati vengano visualizzati correttamente
2. ✅ Utenti > Nuovo/Modifica - verificare che i permessi individuali siano selezionabili
3. ✅ Cadetti > Nuovo - verificare che la creazione funzioni senza errori 500
4. ✅ Domande Iscrizione - verificare la visualizzazione
5. ✅ Eventi > Nuovo - verificare la creazione
6. ✅ Scadenze > Nuovo - verificare la creazione
7. ✅ Mezzi > Nuovo - verificare la creazione
8. ✅ Centrale Operativa - verificare la visualizzazione
9. ✅ Report - verificare la visualizzazione

## Note Tecniche

### Permessi Utente vs Permessi Ruolo

Il sistema ora supporta due livelli di permessi:

1. **Permessi del Ruolo**: Definiti nella tabella `role_permissions`, assegnati a tutti gli utenti con quel ruolo
2. **Permessi Specifici Utente**: Definiti nella tabella `user_permissions`, assegnati individualmente a un utente

Durante il login, vengono caricati entrambi i tipi di permessi e uniti. Questo permette di:
- Assegnare un ruolo base a un utente (es: "operatore")
- Aggiungere permessi specifici aggiuntivi (es: accesso ai report)

### Dati Associazione

I dati dell'associazione vengono ora caricati automaticamente dal database all'avvio dell'applicazione e sono disponibili tramite:

```php
$config = $app->getConfig();
$associationName = $config['association']['name'];
```

Campi disponibili:
- `name`: Nome dell'associazione
- `address`: Indirizzo completo (via + numero civico)
- `city`: Città con provincia e CAP
- `email`: Email principale
- `pec`: PEC dell'associazione
- `tax_code`: Codice fiscale

## Supporto

Per problemi o domande relative a queste modifiche, creare una issue su GitHub.
