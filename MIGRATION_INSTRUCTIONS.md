# Istruzioni per la Migrazione del Database

Questa guida spiega come applicare le modifiche al database per la versione aggiornata di EasyVol.

## Modifiche alla Tabella meetings

Le seguenti modifiche sono state apportate per migliorare la gestione delle riunioni:

### Nuovi campi per la votazione negli ordini del giorno

È stata aggiornata la tabella `meeting_agenda` per includere informazioni dettagliate sulle votazioni:

- `has_voting`: Indica se è stata effettuata una votazione
- `voting_total`: Numero totale di votanti
- `voting_in_favor`: Numero di voti favorevoli
- `voting_against`: Numero di voti contrari
- `voting_abstentions`: Numero di astensioni
- `voting_result`: Esito della votazione (approvato, respinto, non_votato)

## Come Applicare la Migrazione

### Opzione 1: Tramite phpMyAdmin o MySQL Workbench

1. Accedi al tuo database MySQL
2. Apri il file `database_migration_meetings.sql`
3. Esegui lo script SQL

### Opzione 2: Da riga di comando

```bash
mysql -u your_username -p your_database_name < database_migration_meetings.sql
```

### Opzione 3: Tramite script PHP

Crea un file temporaneo `migrate.php` nella cartella `public/` con il seguente contenuto:

```php
<?php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

$app = App::getInstance();
$db = $app->getDb();

try {
    // Leggi il file di migrazione
    $sql = file_get_contents(__DIR__ . '/../database_migration_meetings.sql');
    
    // Esegui le query
    $statements = explode(';', $sql);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $db->query($statement);
        }
    }
    
    echo "Migrazione completata con successo!";
} catch (Exception $e) {
    echo "Errore durante la migrazione: " . $e->getMessage();
}
?>
```

Poi visita: `http://tuosito.com/public/migrate.php`

**IMPORTANTE**: Elimina il file `migrate.php` dopo aver completato la migrazione per motivi di sicurezza.

## Verifica della Migrazione

Dopo aver eseguito la migrazione, verifica che:

1. La tabella `meeting_agenda` abbia le nuove colonne
2. Non ci siano errori nel log di MySQL
3. L'applicazione funzioni correttamente

## Rollback

Se necessario, puoi annullare le modifiche con questo SQL:

```sql
ALTER TABLE `meeting_agenda` 
DROP COLUMN IF EXISTS `has_voting`,
DROP COLUMN IF EXISTS `voting_total`,
DROP COLUMN IF EXISTS `voting_in_favor`,
DROP COLUMN IF EXISTS `voting_against`,
DROP COLUMN IF EXISTS `voting_abstentions`,
MODIFY COLUMN `voting_result` varchar(255);
```

## Supporto

In caso di problemi durante la migrazione, controlla:
- I log di MySQL per eventuali errori
- Che l'utente del database abbia i permessi ALTER TABLE
- Che il backup del database sia stato effettuato prima della migrazione
