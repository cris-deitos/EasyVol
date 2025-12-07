# Database Migrations - EasyVol

## Import Soci Completo

### Descrizione
Script SQL per l'importazione completa di 175 soci dal vecchio gestionale a EasyVol.

### File
- `import_soci_completo.sql` - Script principale di importazione

### Struttura dello Script

Lo script è diviso in 4 fasi principali:

#### Fase 1: Aggiornamento Schema Database
Aggiunge i campi mancanti alla tabella `members`:
- `birth_province` (varchar) - Provincia di nascita
- `gender` (enum) - Genere (M/F/Altro)
- `nationality` (varchar) - Nazionalità
- `blood_type` (varchar) - Gruppo sanguigno
- `qualification` (varchar) - Qualifica/Mansione
- `dismissal_date` (date) - Data dimissioni/decadenza
- `dismissal_reason` (text) - Motivo dimissioni/decadenza
- `photo_path` (varchar) - Percorso foto

#### Fase 2: Aggiornamento Tabelle Correlate
Aggiorna le tabelle `member_contacts` e `member_addresses`:
- Aggiunge campo `is_primary` per indicare contatto/indirizzo principale
- Aggiunge campo `notes` a `member_contacts`
- Rinomina `value` in `contact_value` in `member_contacts`
- Aggiorna enum `contact_type` per supportare tipo 'telefono'

#### Fase 3: Importazione Soci
Contiene la struttura per inserire i 175 soci con:
- Dati anagrafici completi
- Contatti (cellulare, telefono, email)
- Indirizzi di residenza
- Note e disponibilità territoriale
- Gestione dimessi e decaduti

#### Fase 4: Verifica e Statistiche
Query di verifica per validare l'importazione:
- Conteggio totale soci
- Suddivisione per status (attivi/dimessi/decaduti)
- Suddivisione per tipo (fondatori/ordinari)
- Conteggio contatti e indirizzi
- Verifica soci senza contatti o indirizzi

### Mappatura Campi CSV → Database

| Campo CSV | Campo Database | Trasformazione |
|-----------|----------------|----------------|
| `matr` | `registration_number` | Diretto |
| `tipo_socio` | `member_type` | SOCIO FONDATORE→fondatore, SOCIO ORDINARIO→ordinario |
| `stato` | `member_status` | OPERATIVO→attivo, *DIMESSO*→dimesso, *DECADUTO*→decaduto |
| `cognome` | `last_name` | Diretto (escape virgolette) |
| `nome` | `first_name` | Diretto (escape virgolette) |
| `data_nascita` | `birth_date` | Formato YYYY-MM-DD |
| `luogo_nascita` | `birth_place` | Diretto |
| `prov_nascita` | `birth_province` | Sigla provincia (2 lettere) |
| `codicefiscale` | `tax_code` | Diretto |
| `problemialimentari` | `gender` | MASCHIO→M, FEMMINA→F |
| `grup_sang` | `blood_type` | Diretto |
| `anno_iscr` | `registration_date` | Formato YYYY-MM-DD |
| `mansione` | `qualification` | Diretto |
| `disp_territ` | `notes` | "Disponibilità: [valore]" |
| `altre_lingue` | `notes` | "Lingue: [valore]" |
| `prob_alim` | `notes` | "Allergie: [valore]" |
| `nuovocampo6` | `notes` | "Patente: [valore]" |
| `cellulare` | `member_contacts.contact_value` | Tipo 'cellulare' |
| `tel_fisso` | `member_contacts.contact_value` | Tipo 'telefono' |
| `e_mail` | `member_contacts.contact_value` | Tipo 'email' |
| `ind_resid` | `member_addresses.street` | Diretto |
| `cap_resid` | `member_addresses.cap` | Diretto |
| `comun_resid` | `member_addresses.city` | Diretto |
| `provincia` | `member_addresses.province` | Sigla provincia |
| `note` | `dismissal_reason` | Solo per dimessi/decaduti |
| `nuovocampo1` | `dismissal_date` | Solo per dimessi/decaduti |
| `created` | `created_at` | Timestamp |
| `last_upd` | `updated_at` | Timestamp |

### Come Utilizzare lo Script

#### Pre-requisiti
1. Database MySQL/MariaDB 5.6+ o 8.x configurato
2. File CSV con i 175 soci del vecchio gestionale
3. Backup del database corrente

#### Procedura di Importazione

**OPZIONE A: Script Template (Attuale)**

Lo script attuale contiene la struttura e 3 esempi di soci. Per importare tutti i 175 soci:

1. Aprire il file CSV con i dati dei soci
2. Per ogni riga del CSV, creare un blocco INSERT seguendo il template fornito
3. Sostituire tutti i placeholder `[campo]` con i valori effettivi dal CSV
4. Gestire correttamente:
   - Escape delle virgolette singole (es: `D'AMICO` → `D''AMICO`)
   - Valori NULL per campi vuoti
   - Formato date: `YYYY-MM-DD`
   - Mapping tipo_socio e stato secondo tabella sopra

**OPZIONE B: Script Automatico (Consigliato)**

È disponibile uno script Python che genera automaticamente tutti gli INSERT statements dal CSV.

**File**: `generate_import_sql.py`

**Uso**:
```bash
# Generare gli INSERT statements dal CSV
python3 generate_import_sql.py soci.csv > generated_inserts.sql

# Combinare con l'header dello script template
cat import_soci_completo.sql > import_final.sql
# Inserire manualmente gli INSERT generati nella sezione appropriata

# Oppure generare direttamente uno script completo
head -n 120 import_soci_completo.sql > import_final.sql
python3 generate_import_sql.py soci.csv >> import_final.sql
tail -n 50 import_soci_completo.sql >> import_final.sql
```

**Caratteristiche dello script**:
- Legge automaticamente il CSV con header
- Mappa correttamente tutti i campi secondo le specifiche
- Gestisce escape di virgolette singole
- Gestisce valori NULL
- Converte formati data automaticamente
- Riconosce nazionalità estere dal luogo di nascita
- Genera INSERT per members, member_contacts e member_addresses
- Estrae automaticamente numeri civici dagli indirizzi

**File di esempio**: `soci_example.csv` contiene 5 soci di esempio per testare lo script

#### Esecuzione dello Script

```bash
# 1. Backup del database
mysqldump -u root -p easyvol > backup_easyvol_$(date +%Y%m%d).sql

# 2. Esecuzione script di importazione
mysql -u root -p easyvol < import_soci_completo.sql

# 3. Verifica risultati
mysql -u root -p easyvol -e "SELECT COUNT(*) FROM members;"
```

### Gestione Errori

#### Errore: Duplicate column name
Se si ottiene un errore "Duplicate column name", significa che alcune colonne esistono già.
Soluzione: Commentare le righe ALTER TABLE per le colonne che esistono già.

#### Errore: Unknown column in field list
Se si ottiene questo errore durante gli INSERT, verificare:
1. Che tutte le ALTER TABLE siano state eseguite correttamente
2. Che i nomi delle colonne nei INSERT corrispondano allo schema
3. Che la colonna `value` sia stata rinominata in `contact_value`

#### Errore: Foreign key constraint fails
Verificare che:
1. La tabella `members` esista
2. Gli ID dei membri siano corretti
3. Non ci siano riferimenti circolari

### Validazione Post-Importazione

Dopo l'esecuzione, verificare:

```sql
-- Totale soci importati (deve essere 175)
SELECT COUNT(*) FROM members;

-- Suddivisione per status
SELECT member_status, COUNT(*) 
FROM members 
GROUP BY member_status;

-- Suddivisione per tipo
SELECT member_type, COUNT(*) 
FROM members 
GROUP BY member_type;

-- Soci senza contatti
SELECT m.registration_number, m.last_name, m.first_name
FROM members m
LEFT JOIN member_contacts mc ON m.id = mc.member_id
WHERE mc.id IS NULL;

-- Soci senza indirizzo
SELECT m.registration_number, m.last_name, m.first_name
FROM members m
LEFT JOIN member_addresses ma ON m.id = ma.member_id
WHERE ma.id IS NULL;
```

### Note Importanti

1. **Backup**: Eseguire SEMPRE un backup prima di importare
2. **Test**: Testare su database di sviluppo prima di produzione
3. **Encoding**: Assicurarsi che CSV e database usino UTF-8
4. **Date**: Formato date deve essere `YYYY-MM-DD`
5. **NULL**: Gestire correttamente i valori NULL per campi opzionali
6. **Escape**: Fare escape delle virgolette singole nei valori di testo
7. **Nazionalità**: Se luogo_nascita contiene paesi esteri (CUBA, ROMANIA, ecc.), impostare nationality di conseguenza

### Troubleshooting

**Problema**: Lo script impiega troppo tempo
**Soluzione**: 
- Disabilitare temporaneamente gli indici prima dell'import
- Aumentare `bulk_insert_buffer_size`
- Usare transazioni batch più piccole

**Problema**: Caratteri speciali non visualizzati correttamente
**Soluzione**:
- Verificare che il database usi charset utf8mb4
- Verificare che il CSV sia in UTF-8
- Usare: `SET NAMES utf8mb4;` prima degli INSERT

**Problema**: Performance lente dopo import
**Soluzione**:
```sql
ANALYZE TABLE members;
ANALYZE TABLE member_contacts;
ANALYZE TABLE member_addresses;
OPTIMIZE TABLE members;
```

### Supporto

Per problemi o domande sull'importazione, consultare la documentazione principale del progetto o aprire un issue su GitHub.

### Versione
- Script Version: 1.0
- Data Creazione: 2025-12-07
- Compatibilità: MySQL 5.6+, MySQL 8.x, MariaDB 10.x
