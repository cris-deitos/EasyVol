# MySQL 5.6 e MySQL 8.x - Compatibilità

Questo documento descrive le modifiche apportate al database schema di EasyVol per garantire la compatibilità con entrambe le versioni MySQL 5.6 e MySQL 8.x.

## Versioni Supportate

- ✅ MySQL 5.6.5+
- ✅ MySQL 5.7.x
- ✅ MySQL 8.0.x
- ✅ MySQL 8.1+
- ✅ MariaDB 10.3+

## Modifiche Apportate

### 1. Timestamp Columns

**Problema**: MySQL 5.6 non supporta più di una colonna `timestamp` con `NOT NULL DEFAULT CURRENT_TIMESTAMP` per tabella.

**Soluzione**: Modificate tutte le colonne `updated_at` da:
```sql
`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

A:
```sql
`updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

Questa modifica:
- ✅ Funziona perfettamente con MySQL 5.6+
- ✅ Funziona perfettamente con MySQL 8.x
- ✅ Mantiene il comportamento automatico di aggiornamento
- ✅ Permette valori NULL (che verranno comunque popolati automaticamente)

### 2. Character Set e Collation

Lo schema utilizza:
```sql
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

Questo è compatibile con:
- ✅ MySQL 5.6.5+ (utf8mb4 introdotto in 5.5.3, supporto completo in 5.6.5+)
- ✅ MySQL 8.x
- ✅ Supporta emoji e caratteri speciali Unicode

### 3. Reserved Keywords

Tutte le colonne e tabelle utilizzano backticks (`) per evitare conflitti con parole riservate:
```sql
CREATE TABLE `table_name` (
  `column_name` varchar(255)
)
```

### 4. SQL Mode

Lo schema imposta:
```sql
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
```

Questo garantisce comportamento consistente tra le versioni.

## Tabelle Modificate

Le seguenti tabelle hanno avuto modifiche alle colonne `updated_at`:

1. `config`
2. `association`
3. `users`
4. `roles`
5. `members`
6. `junior_members`
7. `meetings`
8. `meeting_minutes`
9. `vehicles`
10. `warehouse_items`
11. `scheduler_items`
12. `training_courses`
13. `events`
14. `radio_directory`
15. `email_templates`
16. `print_templates`

**Totale**: 16 tabelle modificate

## Test di Compatibilità

### Test Automatico

È disponibile uno script di test in `/tmp/test_mysql_compatibility.php` che verifica:
- ✅ Assenza di multiple colonne timestamp NOT NULL
- ✅ Uso corretto di character set e collation
- ✅ Assenza di tipi di dati incompatibili (es. JSON in MySQL 5.6)
- ✅ Corretta definizione delle colonne updated_at

### Test Manuale

Per testare lo schema su MySQL 5.6:
```bash
mysql -u root -p -e "SELECT VERSION();"
mysql -u root -p < database_schema.sql
```

Per testare lo schema su MySQL 8.x:
```bash
mysql -u root -p -e "SELECT VERSION();"
mysql -u root -p < database_schema.sql
```

## Note Importanti

### Comportamento delle Colonne `updated_at`

Le colonne `updated_at` con `NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`:
- Vengono automaticamente impostate a `CURRENT_TIMESTAMP` alla creazione del record
- Vengono automaticamente aggiornate a `CURRENT_TIMESTAMP` ad ogni modifica del record
- Possono tecnicamente contenere NULL, ma in pratica non lo faranno mai grazie al DEFAULT

### Differenze tra MySQL 5.6 e 8.x

#### Funzionalità NON utilizzate (per mantenere compatibilità MySQL 5.6):
- ❌ JSON column type (disponibile solo da MySQL 5.7+)
- ❌ Window functions (disponibili solo da MySQL 8.0+)
- ❌ Common Table Expressions (CTE) (disponibili solo da MySQL 8.0+)
- ❌ Invisible columns (disponibili solo da MySQL 8.0.23+)

#### Funzionalità UTILIZZATE (compatibili con entrambe le versioni):
- ✅ InnoDB engine
- ✅ Foreign keys con CASCADE
- ✅ ENUM types
- ✅ AUTO_INCREMENT
- ✅ Indexes e unique constraints
- ✅ Triggers (se necessari in futuro)
- ✅ Stored procedures (se necessarie in futuro)

## Migrazione da Versioni Precedenti

Se hai già un database EasyVol con lo schema vecchio, puoi aggiornarlo con:

```sql
-- Backup del database
mysqldump -u root -p easyvol > backup_easyvol_$(date +%Y%m%d).sql

-- Modifica delle colonne updated_at
ALTER TABLE `config` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `association` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `users` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `roles` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `members` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `junior_members` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `meetings` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `meeting_minutes` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `vehicles` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `warehouse_items` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `scheduler_items` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `training_courses` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `events` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `radio_directory` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `email_templates` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `print_templates` MODIFY `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

## Verifica Post-Installazione

Dopo l'installazione, verifica che tutto funzioni correttamente:

```sql
-- Verifica la versione di MySQL
SELECT VERSION();

-- Verifica le tabelle create
SHOW TABLES;

-- Verifica la struttura di una tabella
DESCRIBE users;

-- Test inserimento e update automatico
INSERT INTO config (config_key, config_value) VALUES ('test', 'value');
SELECT created_at, updated_at FROM config WHERE config_key = 'test';

-- Aspetta qualche secondo e poi aggiorna
UPDATE config SET config_value = 'new_value' WHERE config_key = 'test';
SELECT created_at, updated_at FROM config WHERE config_key = 'test';

-- Pulisci
DELETE FROM config WHERE config_key = 'test';
```

I campi `created_at` e `updated_at` dovrebbero essere popolati automaticamente e `updated_at` dovrebbe essere aggiornato dopo l'UPDATE.

## Supporto

Per problemi o domande relative alla compatibilità MySQL:
- Apri una issue su: https://github.com/cris-deitos/EasyVol/issues
- Includi la versione di MySQL: `SELECT VERSION();`
- Includi l'errore completo se presente

## Riferimenti

- [MySQL 5.6 Reference Manual](https://dev.mysql.com/doc/refman/5.6/en/)
- [MySQL 8.0 Reference Manual](https://dev.mysql.com/doc/refman/8.0/en/)
- [MySQL Timestamp Initialization](https://dev.mysql.com/doc/refman/5.6/en/timestamp-initialization.html)
- [MariaDB Compatibility](https://mariadb.com/kb/en/mariadb-vs-mysql-compatibility/)
