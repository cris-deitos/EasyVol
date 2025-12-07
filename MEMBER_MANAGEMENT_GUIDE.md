# Guida alla Gestione Soci - Member Management Guide

## Panoramica delle Modifiche / Overview of Changes

Questo aggiornamento risolve i problemi di inserimento dei soci nel database e aggiunge una completa interfaccia utente per gestire tutte le informazioni dei soci.

This update fixes member insertion issues and adds a comprehensive UI for managing all member information.

## Problemi Risolti / Issues Fixed

### 1. Errori di Inserimento Database / Database Insertion Errors
**Problema**: Il form richiedeva campi (nazionalità, sesso, provincia di nascita) che non esistevano nel database.

**Soluzione**: Aggiunti i campi mancanti tramite migrazione SQL.

### 2. Interfaccia Incompleta / Incomplete Interface  
**Problema**: La scheda socio non mostrava tutte le sezioni (contatti, indirizzi, qualifiche, allergie, datore di lavoro, corsi, patenti).

**Soluzione**: Implementate tutte le schede con funzionalità complete di aggiunta/modifica/eliminazione.

## Migrazione Database Richiesta / Required Database Migration

**IMPORTANTE**: Prima di utilizzare il sistema, devi applicare la migrazione del database.

**IMPORTANT**: Before using the system, you must apply the database migration.

### Passo 1: Backup del Database / Step 1: Backup Database
```bash
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

### Passo 2: Applica la Migrazione / Step 2: Apply Migration
```bash
mysql -u username -p database_name < migrations/add_member_fields.sql
```

Oppure tramite phpMyAdmin / Or via phpMyAdmin:
1. Apri phpMyAdmin
2. Seleziona il database
3. Vai alla tab "SQL"
4. Copia e incolla il contenuto di `migrations/add_member_fields.sql`
5. Clicca "Esegui" / Click "Go"

## Nuove Funzionalità / New Features

### Per Soci Maggiorenni / For Adult Members

#### Schede Disponibili / Available Tabs:
1. **Dati Anagrafici** - Informazioni personali base
2. **Contatti** - Telefono, cellulare, email, PEC
3. **Indirizzi** - Residenza e domicilio
4. **Datore di Lavoro** - Informazioni lavorative
5. **Qualifiche** - Ruoli e qualifiche
6. **Corsi** - Formazione e certificati
7. **Patenti** - Patenti e abilitazioni (A, B, C, D, E, nautica, muletto, ecc.)
8. **Allergie/Salute** - Allergie, intolleranze, patologie, diete

#### Come Aggiungere Dati / How to Add Data:
1. Vai alla scheda del socio / Go to member page
2. Clicca sulla tab desiderata / Click desired tab
3. Clicca "Aggiungi..." / Click "Add..."
4. Compila il form / Fill the form
5. Salva / Save

### Per Soci Minorenni / For Junior Members

#### Schede Disponibili / Available Tabs:
1. **Dati Anagrafici** - Informazioni personali base
2. **Genitori/Tutori** - Dati tutori legali
3. **Contatti** - Telefono, cellulare, email
4. **Indirizzi** - Residenza e domicilio
5. **Allergie/Salute** - Allergie, intolleranze, patologie, diete

#### Nota Importante / Important Note:
I tutori possono essere aggiunti dalla scheda "Genitori/Tutori". È possibile aggiungere più tutori (padre, madre, tutore).

Guardians can be added from the "Parents/Guardians" tab. Multiple guardians can be added (father, mother, guardian).

## Files Modificati / Modified Files

### Database:
- `database_schema.sql` - Schema aggiornato con nuovi campi
- `migrations/add_member_fields.sql` - Script di migrazione
- `migrations/README.md` - Guida alle migrazioni

### Models:
- `src/Models/JuniorMember.php` - Nuovo modello per soci minorenni

### Controllers:
- `src/Controllers/MemberController.php` - Gestione campi aggiuntivi
- `src/Controllers/JuniorMemberController.php` - Corretto per usare tabella guardians separata

### Views - Adult Members:
- `public/member_view.php` - Vista completa con tutte le schede
- `public/member_data.php` - Handler per operazioni CRUD
- `public/member_address_edit.php` - Form indirizzi
- `public/member_employment_edit.php` - Form datore di lavoro
- `public/member_role_edit.php` - Form qualifiche
- `public/member_course_edit.php` - Form corsi
- `public/member_license_edit.php` - Form patenti
- `public/member_health_edit.php` - Form salute

### Views - Junior Members:
- `public/junior_member_view.php` - Vista completa con tutte le schede
- `public/junior_member_data.php` - Handler per operazioni CRUD
- `public/junior_member_address_edit.php` - Form indirizzi
- `public/junior_member_guardian_edit.php` - Form tutori
- `public/junior_member_health_edit.php` - Form salute

## Test Consigliati / Recommended Tests

Dopo aver applicato la migrazione, testa le seguenti funzionalità:

After applying the migration, test the following features:

1. ✅ Creazione nuovo socio maggiorenne con tutti i campi
2. ✅ Aggiunta contatti, indirizzi, qualifiche, corsi, patenti, allergie
3. ✅ Modifica dati esistenti
4. ✅ Eliminazione dati
5. ✅ Creazione nuovo socio minorenne con tutore
6. ✅ Aggiunta tutori, contatti, indirizzi, allergie per minorenni

## Supporto / Support

In caso di problemi:
1. Verifica che la migrazione sia stata applicata correttamente
2. Controlla i log di errore PHP
3. Verifica i permessi utente nel sistema

If you encounter issues:
1. Verify the migration was applied correctly
2. Check PHP error logs
3. Verify user permissions in the system

## Note Tecniche / Technical Notes

- I campi `gender`, `nationality`, e `birth_province` sono ora correttamente salvati nel database
- La tabella `junior_member_guardians` è separata dalla tabella `junior_members`
- Tutti i form utilizzano protezione CSRF
- Le operazioni CRUD sono loggiate nella tabella `activity_logs`

## Compatibilità / Compatibility

- PHP 8.4+
- MySQL 5.6+ / MySQL 8.x
- Bootstrap 5.3+
