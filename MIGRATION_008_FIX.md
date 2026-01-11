# Fix per Errore Migration 008 - Scadenze Ricorrenti

## Problema Riscontrato
Quando si importava il file `migrations/008_add_recurring_deadlines.sql`, MySQL restituiva questo errore:

```
#1064 - You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near 'anno), monthly (stesso giorno ogni mese), weekly (stesso giorno ogni settimana)'' at line 1
```

Inoltre, quando si tentava di inserire una nuova scadenza con ricorrenza nel scadenziario, si riceveva il messaggio:
```
Errore durante il salvataggio. Verifica tutto.
```

## Causa del Problema
L'errore era causato da un apostrofo (`'`) nella stringa di COMMENT del campo `recurrence_type` nella migrazione SQL. Nello specifico, il testo conteneva `l'anno` che, quando inserito in una stringa SQL dinamica (usata con PREPARE), causava un errore di sintassi.

## Soluzione Implementata
La fix rimuove l'apostrofo problematico cambiando `l'anno` in `anno` nel COMMENT del campo `recurrence_type`.

### File Modificati
1. **migrations/008_add_recurring_deadlines.sql** - Corretto la sintassi SQL
2. **database_schema.sql** - Aggiornato per coerenza

## Istruzioni per Applicare la Fix

### Opzione 1: Database Nuovo (Nessuna Migrazione Eseguita)
Se non hai ancora eseguito alcuna migrazione:

1. Scarica i file aggiornati dal repository
2. Importa il file `database_schema.sql` completo nel tuo database MySQL
3. Procedi normalmente con la configurazione

### Opzione 2: Migration 008 Non Ancora Eseguita
Se hai già un database ma non hai ancora eseguito la migration 008:

1. Scarica il file aggiornato `migrations/008_add_recurring_deadlines.sql`
2. Importa il file nel tuo database MySQL:
   ```bash
   mysql -u username -p database_name < migrations/008_add_recurring_deadlines.sql
   ```
3. Verifica che la migrazione sia completata con successo

### Opzione 3: Correzione Manuale (Se Preferisci)
Se preferisci correggere manualmente, esegui questi comandi SQL:

```sql
-- Verifica se le colonne esistono già
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'scheduler_items' 
AND COLUMN_NAME IN ('is_recurring', 'recurrence_type', 'recurrence_end_date', 'parent_recurrence_id');

-- Se le colonne NON esistono, eseguile migration corretta:
-- Scarica e importa migrations/008_add_recurring_deadlines.sql aggiornato
```

## Verifica della Correzione

### 1. Verifica Struttura Database
Controlla che le colonne siano state aggiunte correttamente:

```sql
DESCRIBE scheduler_items;
```

Dovresti vedere questi nuovi campi:
- `is_recurring` - TINYINT(1)
- `recurrence_type` - ENUM('yearly', 'monthly', 'weekly')
- `recurrence_end_date` - DATE
- `parent_recurrence_id` - INT(11)

### 2. Test Funzionalità
1. Accedi all'applicazione EasyVol
2. Vai a **Scadenziario**
3. Clicca su **Nuova Scadenza**
4. Compila i campi obbligatori (Titolo, Data di Scadenza)
5. Abilita la casella **"Abilita ricorrenza automatica"**
6. Seleziona un tipo di ricorrenza:
   - **yearly** - 1 volta anno (stesso giorno ogni anno)
   - **monthly** - Stesso giorno ogni mese
   - **weekly** - Stesso giorno ogni settimana
7. Opzionalmente, imposta una data di fine ricorrenza
8. Salva la scadenza

Se il salvataggio ha successo, la fix è stata applicata correttamente!

## Dettagli Tecnici

### Campo recurrence_type
Il campo `recurrence_type` è un ENUM che supporta tre tipi di ricorrenza:

- `yearly`: La scadenza si ripete ogni anno nello stesso giorno
- `monthly`: La scadenza si ripete ogni mese nello stesso giorno
- `weekly`: La scadenza si ripete ogni settimana nello stesso giorno

### Esempio di Utilizzo
```php
// Creare una scadenza ricorrente annuale
$data = [
    'title' => 'Rinnovo assicurazione',
    'due_date' => '2026-12-31',
    'is_recurring' => 1,
    'recurrence_type' => 'yearly',
    'recurrence_end_date' => null // Ricorrenza a tempo indeterminato
];
```

## Supporto
Se riscontri ancora problemi dopo aver applicato questa fix, verifica:

1. Che la versione di MySQL sia compatibile (5.6+ o 8.x)
2. Che l'utente del database abbia i permessi per ALTER TABLE
3. I log degli errori di PHP (`error_log`) per dettagli sull'errore

## Note sulla Traduzione
Il COMMENT è stato modificato da:
```
'Tipo ricorrenza: yearly (1 volta l'anno), monthly (stesso giorno ogni mese), weekly (stesso giorno ogni settimana)'
```

A:
```
'Tipo ricorrenza: yearly (1 volta anno), monthly (stesso giorno ogni mese), weekly (stesso giorno ogni settimana)'
```

La modifica è minima e non impatta la funzionalità del sistema.
