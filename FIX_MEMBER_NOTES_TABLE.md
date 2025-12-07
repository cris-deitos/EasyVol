# Fix per Errore "Table member_notes doesn't exist"

## Problema
Quando si tenta di inserire un nuovo socio, appare questo errore:
```
Fatal error: Uncaught Exception: Query failed: SQLSTATE[42S02]: 
Base table or view not found: 1146 Table 'Sql1905151_1.member_notes' doesn't exist
```

## Causa
La tabella `member_notes` era referenziata nel codice ma non esisteva nello schema del database.

## Soluzione

### Opzione 1: Utilizzare lo script di migrazione (Raccomandato)

Se hai accesso alla riga di comando sul server:

```bash
php migrations/run_migration.php migrations/add_member_notes_table.sql
```

### Opzione 2: Utilizzare phpMyAdmin

1. Accedi a phpMyAdmin
2. Seleziona il tuo database (es. `Sql1905151_1`)
3. Clicca sulla scheda "SQL"
4. Copia e incolla il seguente codice SQL:

```sql
CREATE TABLE IF NOT EXISTS `member_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

5. Clicca su "Esegui"

### Opzione 3: Utilizzare MySQL dalla riga di comando

```bash
mysql -u your_username -p your_database_name < migrations/add_member_notes_table.sql
```

## Verifica

Dopo aver applicato la migrazione, verifica che la tabella sia stata creata:

1. Accedi al database
2. Esegui la query: `SHOW TABLES LIKE 'member_notes';`
3. Dovresti vedere la tabella `member_notes` nell'elenco

## Test

Prova a creare un nuovo socio:
1. Vai alla sezione "Soci"
2. Clicca su "Nuovo Socio"
3. Compila il modulo
4. Salva il socio

Se la migrazione è stata applicata correttamente, non dovresti più vedere l'errore.

## Note

- La tabella `member_notes` permette di aggiungere note ai profili dei soci
- Le note sono accessibili dalla scheda "Note" nella visualizzazione del profilo del socio
- Ogni nota include l'ID del socio, il testo della nota, chi l'ha creata e quando

## Supporto

Se riscontri ancora problemi dopo aver applicato questa migrazione, verifica:
1. Che la tabella sia stata creata correttamente
2. Che l'utente del database abbia i permessi necessari
3. Che non ci siano altri errori nel log di PHP/MySQL
