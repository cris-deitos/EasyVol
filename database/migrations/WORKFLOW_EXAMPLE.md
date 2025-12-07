# Workflow di Esempio per l'Importazione Dati

Questo documento fornisce un esempio completo del flusso di lavoro per l'importazione dei dati dal vecchio gestionale.

## Scenario

Hai due file CSV dal vecchio sistema:
- `soci.csv` - 175 soci adulti
- `cadetti.csv` - 53 cadetti

Vuoi importarli nel nuovo database EasyVol.

## Passo 1: Preparazione dei File CSV

### Verifica Formato CSV

I file CSV devono essere:
- Codificati in **UTF-8**
- Con separatore **virgola** (,)
- Con intestazione nella prima riga
- Senza virgolette superflue

### Esempio Struttura soci.csv

```csv
matr,tipo_socio,stato,cognome,nome,data_nascita,...
001,SOCIO FONDATORE,OPERATIVO,ROSSI,MARIO,1980-01-15,...
002,SOCIO ORDINARIO,NON OPERATIVO,BIANCHI,GIULIA,1985-06-22,...
```

### Gestione Caratteri Speciali nei CSV

I CSV **NON** devono già avere escape:
- ✅ Corretto: `D'ANGELO` (lo script farà l'escape automaticamente)
- ❌ Sbagliato: `D\'ANGELO` (doppio escape)

## Passo 2: Conversione CSV → SQL

### Per Soci Adulti

```bash
# Posizionarsi nella directory migrations
cd database/migrations

# Convertire il file CSV
python3 csv_to_sql_converter.py ../../soci.csv > import_soci_adulti_completo.sql

# Verificare il file generato
wc -l import_soci_adulti_completo.sql
# Dovrebbe mostrare circa 175 record + header/footer

# Controllare i primi record
head -100 import_soci_adulti_completo.sql
```

### Per Cadetti

```bash
# Convertire il file CSV dei cadetti
python3 csv_to_sql_converter.py ../../cadetti.csv > import_cadetti_completo.sql

# Verificare
wc -l import_cadetti_completo.sql
# Dovrebbe mostrare circa 53 record + header/footer
```

## Passo 3: Backup del Database

**SEMPRE** fare un backup prima di importare:

```bash
# Backup completo
mysqldump -u root -p easyvol > backup_prima_import_$(date +%Y%m%d_%H%M%S).sql

# Backup solo tabelle interessate
mysqldump -u root -p easyvol \
    members member_contacts member_addresses \
    junior_members junior_member_guardians junior_member_contacts junior_member_addresses \
    > backup_membri_$(date +%Y%m%d_%H%M%S).sql
```

## Passo 4: Test su Database di Prova

Prima di importare in produzione, testare su un database di prova:

```bash
# Creare database di test
mysql -u root -p -e "CREATE DATABASE easyvol_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importare schema
mysql -u root -p easyvol_test < ../../database_schema.sql

# Importare soci
mysql -u root -p easyvol_test < import_soci_adulti_completo.sql

# Verificare risultati
mysql -u root -p easyvol_test -e "SELECT COUNT(*) as TotaleSoci FROM members;"
mysql -u root -p easyvol_test -e "SELECT member_type, member_status, COUNT(*) as N FROM members GROUP BY member_type, member_status;"

# Importare cadetti
mysql -u root -p easyvol_test < import_cadetti_completo.sql

# Verificare risultati
mysql -u root -p easyvol_test -e "SELECT COUNT(*) as TotaleCadetti FROM junior_members;"
mysql -u root -p easyvol_test -e "SELECT member_status, COUNT(*) as N FROM junior_members GROUP BY member_status;"

# Verifica totale (dovrebbe essere 228)
mysql -u root -p easyvol_test -e "
    SELECT 
        (SELECT COUNT(*) FROM members) + 
        (SELECT COUNT(*) FROM junior_members) 
    AS TotaleMembri;"
```

## Passo 5: Importazione in Produzione

Se i test sono positivi, procedere con l'importazione in produzione:

```bash
# Importare soci adulti
mysql -u root -p easyvol < import_soci_adulti_completo.sql

# Verificare
mysql -u root -p easyvol -e "SELECT COUNT(*) FROM members;"

# Importare cadetti
mysql -u root -p easyvol < import_cadetti_completo.sql

# Verificare
mysql -u root -p easyvol -e "SELECT COUNT(*) FROM junior_members;"
```

## Passo 6: Verifica Post-Importazione

### Verifica Dati

```sql
-- Totale membri
SELECT 
    (SELECT COUNT(*) FROM members) AS Adulti,
    (SELECT COUNT(*) FROM junior_members) AS Cadetti,
    (SELECT COUNT(*) FROM members) + (SELECT COUNT(*) FROM junior_members) AS Totale;

-- Statistiche soci adulti
SELECT 
    member_type,
    member_status,
    volunteer_status,
    COUNT(*) as Numero
FROM members
GROUP BY member_type, member_status, volunteer_status
ORDER BY member_type, member_status;

-- Statistiche cadetti
SELECT 
    member_status,
    COUNT(*) as Numero
FROM junior_members
GROUP BY member_status;

-- Verifica contatti
SELECT 
    'Adulti' as Tipo,
    COUNT(DISTINCT member_id) as MembriConContatti,
    COUNT(*) as TotaleContatti
FROM member_contacts
UNION ALL
SELECT 
    'Cadetti' as Tipo,
    COUNT(DISTINCT junior_member_id) as MembriConContatti,
    COUNT(*) as TotaleContatti
FROM junior_member_contacts;

-- Verifica indirizzi
SELECT 
    'Adulti' as Tipo,
    address_type,
    COUNT(*) as Numero
FROM member_addresses
GROUP BY address_type
UNION ALL
SELECT 
    'Cadetti' as Tipo,
    address_type,
    COUNT(*) as Numero
FROM junior_member_addresses
GROUP BY address_type;
```

### Verifica Integrità Dati

```sql
-- Verificare che non ci siano membri senza nome
SELECT * FROM members WHERE last_name IS NULL OR first_name IS NULL;
SELECT * FROM junior_members WHERE last_name IS NULL OR first_name IS NULL;

-- Verificare che tutti i tutori abbiano un cadetto
SELECT g.* FROM junior_member_guardians g
LEFT JOIN junior_members j ON g.junior_member_id = j.id
WHERE j.id IS NULL;

-- Verificare numeri di matricola duplicati
SELECT registration_number, COUNT(*) as N 
FROM members 
WHERE registration_number IS NOT NULL
GROUP BY registration_number 
HAVING N > 1;

SELECT registration_number, COUNT(*) as N 
FROM junior_members 
WHERE registration_number IS NOT NULL
GROUP BY registration_number 
HAVING N > 1;
```

## Passo 7: Pulizia

Dopo l'importazione riuscita:

```bash
# Archiviare i file CSV originali
mkdir -p archivio_import_$(date +%Y%m%d)
mv soci.csv cadetti.csv archivio_import_$(date +%Y%m%d)/

# Mantenere i file SQL generati per riferimento
# MA rimuovere eventuali file temporanei
rm -f /tmp/*.csv /tmp/*.sql
```

## Troubleshooting

### Problema: Errori di Encoding

```bash
# Convertire CSV in UTF-8
iconv -f ISO-8859-1 -t UTF-8 soci.csv > soci_utf8.csv
iconv -f ISO-8859-1 -t UTF-8 cadetti.csv > cadetti_utf8.csv

# Oppure con python
python3 -c "
import sys
with open('soci.csv', 'r', encoding='iso-8859-1') as f:
    content = f.read()
with open('soci_utf8.csv', 'w', encoding='utf-8') as f:
    f.write(content)
"
```

### Problema: Foreign Key Errors

```sql
-- Verificare che le tabelle siano vuote prima dell'import
SELECT COUNT(*) FROM members;
SELECT COUNT(*) FROM junior_members;

-- Se necessario, pulire le tabelle
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE member_contacts;
TRUNCATE TABLE member_addresses;
TRUNCATE TABLE members;
TRUNCATE TABLE junior_member_guardians;
TRUNCATE TABLE junior_member_contacts;
TRUNCATE TABLE junior_member_addresses;
TRUNCATE TABLE junior_members;
SET FOREIGN_KEY_CHECKS = 1;
```

### Problema: Campi NULL Inaspettati

Se alcuni record hanno campi NULL che dovrebbero avere valori:

1. Verificare il CSV originale
2. Controllare che tutti i campi siano presenti
3. Verificare che non ci siano virgole extra
4. Eseguire una conversione di test con pochi record

### Problema: Performance Lenta

Per grandi importazioni:

```sql
-- Disabilitare temporaneamente gli indici
ALTER TABLE members DISABLE KEYS;
ALTER TABLE member_contacts DISABLE KEYS;
ALTER TABLE member_addresses DISABLE KEYS;

-- Eseguire import...

-- Riabilitare indici
ALTER TABLE members ENABLE KEYS;
ALTER TABLE member_contacts ENABLE KEYS;
ALTER TABLE member_addresses ENABLE KEYS;
```

## Risultato Atteso

Dopo un'importazione riuscita dovresti avere:

- ✅ **175 soci adulti** nella tabella `members`
- ✅ **53 cadetti** nella tabella `junior_members`
- ✅ **228 membri totali**
- ✅ Contatti e indirizzi associati correttamente
- ✅ Tutori associati ai cadetti
- ✅ Nessun errore di integrità referenziale
- ✅ Dati correttamente formattati (apostrofi, date, NULL)

## Supporto

Per problemi o domande:

1. Verificare i log MySQL per errori specifici
2. Controllare che i CSV siano nel formato corretto
3. Testare sempre prima su database di prova
4. Consultare la documentazione in `README_IMPORT.md`

---

**Ultimo aggiornamento**: 2025-12-07  
**Testato con**: MySQL 8.0, Python 3.8+
