# Importazione Cadetti (Junior Members) da Vecchio Gestionale

## Descrizione

Questo script SQL (`import_cadetti_completo.sql`) è progettato per importare 53 cadetti (soci minorenni) dal vecchio gestionale al sistema EasyVol.

## File Necessari

1. **Script SQL**: `import_cadetti_completo.sql` (in questa directory)
2. **File CSV**: `gestionaleweb_worktable16.csv` (da fornire)

## Struttura del Database

Lo script importa i dati nelle seguenti tabelle:

- **`junior_members`**: Dati anagrafici principali del cadetto
- **`junior_member_guardians`**: Dati dei genitori/tutori (padre, madre)
- **`junior_member_addresses`**: Indirizzi di residenza/domicilio
- **`junior_member_contacts`**: Contatti (cellulare, email, telefono)
- **`junior_member_health`**: Informazioni su allergie, intolleranze, patologie

## Mappatura Campi CSV

### Dati Cadetto
| Campo CSV | Descrizione | Campo Database |
|-----------|-------------|----------------|
| nuovocampo | Numero registrazione | registration_number |
| nuovocampo1 | Cognome | last_name |
| nuovocampo2 | Nome | first_name |
| nuovocampo3 | Sesso (MASCHIO/FEMMINA) | notes (Gender: M/F) |
| nuovocampo4 | Luogo di nascita | birth_place |
| nuovocampo5 | Provincia nascita | notes (Birth Province) |
| nuovocampo6 | Data di nascita | birth_date |
| nuovocampo7 | Codice Fiscale | tax_code |
| nuovocampo9 | Indirizzo | junior_member_addresses.street |
| nuovocampo10 | CAP | junior_member_addresses.cap |
| nuovocampo11 | Città | junior_member_addresses.city |
| nuovocampo12 | Provincia | junior_member_addresses.province |
| nuovocampo14 | Cellulare cadetto | junior_member_contacts.value |
| nuovocampo15 | Email cadetto | junior_member_contacts.value |
| nuovocampo17 | Lingue conosciute | notes |
| nuovocampo18 | Allergie cadetto | junior_member_health.description |
| nuovocampo25 | Anno corso | notes |

### Dati Genitore 1 (Padre)
| Campo CSV | Descrizione | Campo Database |
|-----------|-------------|----------------|
| nuovocampo33 | Cognome padre | junior_member_guardians.last_name |
| nuovocampo34 | Nome padre | junior_member_guardians.first_name |
| nuovocampo38 | CF padre | junior_member_guardians.tax_code |
| nuovocampo43 | Telefono padre | junior_member_guardians.phone |
| nuovocampo44 | Cellulare padre | junior_member_guardians.phone |
| nuovocampo45 | Email padre | junior_member_guardians.email |

### Dati Genitore 2 (Madre)
| Campo CSV | Descrizione | Campo Database |
|-----------|-------------|----------------|
| nuovocampo46 | Cognome madre | junior_member_guardians.last_name |
| nuovocampo47 | Nome madre | junior_member_guardians.first_name |
| nuovocampo51 | CF madre | junior_member_guardians.tax_code |
| nuovocampo56 | Email madre | junior_member_guardians.email |
| nuovocampo59 | Telefono madre | junior_member_guardians.phone |
| nuovocampo60 | Cellulare madre | junior_member_guardians.phone |

### Dati Status e Date
| Campo CSV | Descrizione | Campo Database |
|-----------|-------------|----------------|
| nuovocampo61 | Data iscrizione | registration_date |
| nuovocampo62 | Motivo dismissione | notes (se decaduto) |
| nuovocampo63 | Data dismissione | notes (se decaduto) |
| nuovocampo64 | Status socio | member_status |
| created | Data creazione record | created_at |
| last_upd | Data ultima modifica | updated_at |

## Mappatura Status

Il campo `nuovocampo64` del CSV viene mappato al campo `member_status`:

- **"SOCIO ORDINARIO"** → `attivo`
- **"*DECADUTO*"** → `decaduto`
- Altri valori possibili: `dimesso`, `in_aspettativa`, `sospeso`, `in_congedo`

## Stato Attuale dello Script

Lo script contiene:

1. ✅ **Header e configurazione SQL** completa
2. ✅ **Mappatura completa** di tutti i campi CSV → Database
3. ✅ **Esempio completo** di importazione (ORLANDO GAIA)
4. ✅ **Template dettagliato** per importare i rimanenti 52 cadetti
5. ✅ **Query di verifica** e statistiche finali
6. ✅ **Documentazione inline** completa

## Come Completare l'Importazione

### Passo 1: Ottenere il File CSV
Assicurarsi di avere il file `gestionaleweb_worktable16.csv` con i dati di tutti i 53 cadetti.

### Passo 2: Completare lo Script SQL

Aprire `import_cadetti_completo.sql` e aggiungere gli altri 52 cadetti seguendo il template fornito:

```sql
-- CADETTO [N]: [COGNOME] [NOME]
INSERT INTO junior_members (...) VALUES (...);
SET @junior_[NUM]_id = LAST_INSERT_ID();

-- Guardians
INSERT INTO junior_member_guardians (...) VALUES (...); -- padre
INSERT INTO junior_member_guardians (...) VALUES (...); -- madre

-- Addresses
INSERT INTO junior_member_addresses (...) VALUES (...);

-- Contacts
INSERT INTO junior_member_contacts (...) VALUES (...);

-- Health
INSERT INTO junior_member_health (...) VALUES (...);
```

### Passo 3: Gestire Caratteri Speciali

Assicurarsi di fare l'escape di:
- Apostrofi: `D'ANGELO` → `D\'ANGELO`
- Virgolette: sostituire `"` con `\"`

### Passo 4: Formati Date

- **birth_date**: `YYYY-MM-DD` (es: `2003-12-02`)
- **registration_date**: `YYYY-MM-DD` (es: `2019-01-12`)
- **created_at/updated_at**: `YYYY-MM-DD HH:MM:SS` (es: `2019-01-13 10:17:37`)

### Passo 5: Gestione NULL

Usare `NULL` per campi vuoti, NON stringhe vuote `''`.

### Passo 6: Test su Database di Sviluppo

Prima di eseguire in produzione, testare lo script su un database di sviluppo:

```bash
mysql -u username -p database_test < import_cadetti_completo.sql
```

### Passo 7: Verificare le Statistiche

Dopo l'esecuzione, controllare l'output delle query di verifica:
- Totale cadetti importati
- Numero attivi vs decaduti
- Guardians importati (padri/madri)
- Contatti importati
- Indirizzi importati
- Health records importati
- Eventuali alert (cadetti senza guardians/indirizzi)

### Passo 8: Esecuzione in Produzione

Se i test sono OK, eseguire in produzione:

```bash
mysql -u username -p easyvol_production < import_cadetti_completo.sql
```

## Esempio di Output Atteso

```
+-----------------------------------+---------------+---------+-----------+
| Stato                             | Totale_Cadetti| Attivi  | Decaduti  |
+-----------------------------------+---------------+---------+-----------+
| IMPORTAZIONE CADETTI COMPLETATA   | 53            | XX      | YY        |
+-----------------------------------+---------------+---------+-----------+

+--------------------+---------+-------+-------+--------+
| Tipo               | Totale  | Padri | Madri | Tutori |
+--------------------+---------+-------+-------+--------+
| GUARDIANS IMPORTATI| 106     | 53    | 53    | 0      |
+--------------------+---------+-------+-------+--------+

... (altre statistiche)
```

## Note Importanti

1. **Backup**: Fare sempre un backup del database prima dell'importazione
2. **Foreign Keys**: Lo script disabilita temporaneamente i vincoli di chiave esterna
3. **Transaction**: L'intera importazione è in una transazione, quindi si può fare rollback in caso di errori
4. **Duplicati**: Se il `registration_number` esiste già, l'inserimento fallirà (campo UNIQUE)
5. **Campo Notes**: Viene usato per consolidare informazioni non mappabili direttamente

## Struttura File nella Repository

```
database/
├── migrations/
│   ├── import_cadetti_completo.sql      # Script di importazione principale
│   └── README_IMPORT_CADETTI.md         # Questa documentazione
```

## Supporto

Per problemi o domande:
1. Verificare i log di MySQL per errori specifici
2. Controllare che i dati del CSV siano nel formato corretto
3. Verificare che le tabelle esistano nel database (`junior_members`, `junior_member_guardians`, ecc.)

## Cronologia

- **2025-12-07**: Creazione script iniziale con esempio ORLANDO GAIA e template per 52 cadetti rimanenti
