# Guida all'Uso - Importazione Soci da Vecchio Gestionale

## Panoramica
Questa guida spiega come utilizzare gli strumenti forniti per importare i 175 soci dal vecchio gestionale a EasyVol.

## File Forniti

1. **`import_soci_completo.sql`** - Script SQL principale con:
   - Aggiornamenti schema database
   - Template per import dati
   - 3 esempi completi di soci
   - Query di verifica

2. **`generate_import_sql.py`** - Script Python per generare automaticamente INSERT da CSV

3. **`soci_example.csv`** - File CSV di esempio con 5 soci per testare il processo

4. **`README.md`** - Documentazione tecnica completa

## Workflow Completo

### Step 1: Preparazione CSV

Assicurarsi che il file CSV contenga questi campi (header):
```
matr,tipo_socio,stato,cognome,nome,data_nascita,luogo_nascita,prov_nascita,
codicefiscale,problemialimentari,grup_sang,anno_iscr,mansione,disp_territ,
cellulare,tel_fisso,e_mail,ind_resid,cap_resid,comun_resid,provincia,
note,nuovocampo1,altre_lingue,prob_alim,nuovocampo6,created,last_upd
```

**Importante**:
- Salvare il CSV in encoding UTF-8
- Usare virgola (,) come separatore
- I campi vuoti sono permessi (vengono gestiti come NULL)
- Le date possono essere in formato DD/MM/YYYY o YYYY-MM-DD

### Step 2: Backup del Database

**OBBLIGATORIO** - Sempre fare backup prima di qualsiasi import:

```bash
# Backup completo
mysqldump -u root -p easyvol > backup_easyvol_$(date +%Y%m%d_%H%M%S).sql

# Backup solo struttura (per confronto)
mysqldump -u root -p --no-data easyvol > backup_structure.sql
```

### Step 3: Test su Database di Sviluppo

Prima di applicare in produzione, testare su un database di sviluppo:

```bash
# Creare database di test
mysql -u root -p -e "CREATE DATABASE easyvol_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importare schema corrente
mysql -u root -p easyvol_test < database_schema.sql

# Testare l'import
mysql -u root -p easyvol_test < database/migrations/import_soci_completo.sql
```

### Step 4: Generare INSERT Statements

Usare lo script Python per generare gli INSERT dal CSV:

```bash
cd database/migrations

# Generare gli INSERT
python3 generate_import_sql.py /percorso/al/soci.csv > generated_inserts.sql

# Verificare il file generato
wc -l generated_inserts.sql  # Dovrebbe avere molte righe
head -n 50 generated_inserts.sql  # Controllare i primi INSERT
```

### Step 5: Creare Script SQL Completo

Combinare l'header dello script template con gli INSERT generati:

```bash
cd database/migrations

# Estrarre header (primi 120 righe = schema updates + commenti)
head -n 120 import_soci_completo.sql > import_finale.sql

# Aggiungere gli INSERT generati
cat generated_inserts.sql >> import_finale.sql

# Aggiungere footer (ultime 50 righe = statistiche)
tail -n 50 import_soci_completo.sql >> import_finale.sql

# Verificare lo script finale
grep -c "INSERT INTO members" import_finale.sql  # Dovrebbe essere 175
```

### Step 6: Eseguire l'Import

#### 6.1 Prima esecuzione (su database di test)

```bash
mysql -u root -p easyvol_test < import_finale.sql
```

Se ci sono errori:

**Errore: Duplicate column name**
- Alcune colonne esistono già
- Soluzione: Commentare le righe ALTER TABLE problematiche

**Errore: Column 'value' doesn't exist**
- La colonna è già stata rinominata
- Soluzione: Commentare la riga CHANGE COLUMN nel script

**Errore: Data truncated**
- Problema con formato date
- Soluzione: Verificare formato date nel CSV

#### 6.2 Verifica Risultati

```bash
mysql -u root -p easyvol_test
```

```sql
-- Verifica totale soci (deve essere 175)
SELECT COUNT(*) FROM members;

-- Verifica distribuzione
SELECT 
    member_status, 
    COUNT(*) as totale 
FROM members 
GROUP BY member_status;

-- Verifica contatti
SELECT COUNT(*) FROM member_contacts;

-- Verifica indirizzi
SELECT COUNT(*) FROM member_addresses;

-- Soci senza contatti
SELECT 
    m.registration_number, 
    m.last_name, 
    m.first_name
FROM members m
LEFT JOIN member_contacts mc ON m.id = mc.member_id
WHERE mc.id IS NULL;
```

#### 6.3 Esecuzione in Produzione

Solo dopo aver verificato su database di test:

```bash
# Backup finale
mysqldump -u root -p easyvol > backup_prima_import_$(date +%Y%m%d_%H%M%S).sql

# Eseguire import
mysql -u root -p easyvol < import_finale.sql

# Verificare risultati (query sopra)
```

### Step 7: Post-Import

#### 7.1 Ottimizzazione Database

```sql
-- Analizzare tabelle
ANALYZE TABLE members;
ANALYZE TABLE member_contacts;
ANALYZE TABLE member_addresses;

-- Ottimizzare tabelle
OPTIMIZE TABLE members;
OPTIMIZE TABLE member_contacts;
OPTIMIZE TABLE member_addresses;
```

#### 7.2 Verifica Integrità

```sql
-- Verificare referential integrity
SELECT 
    'Contatti orfani' as Tipo,
    COUNT(*) as Totale
FROM member_contacts mc
LEFT JOIN members m ON mc.member_id = m.id
WHERE m.id IS NULL;

-- Verificare duplicati matricola
SELECT 
    registration_number,
    COUNT(*) as occorrenze
FROM members
GROUP BY registration_number
HAVING COUNT(*) > 1;

-- Verificare codici fiscali duplicati
SELECT 
    tax_code,
    COUNT(*) as occorrenze
FROM members
WHERE tax_code IS NOT NULL
GROUP BY tax_code
HAVING COUNT(*) > 1;
```

## Risoluzione Problemi Comuni

### Problema: Script Python non funziona

**Errore**: `python3: command not found`
```bash
# Provare con python invece di python3
python generate_import_sql.py soci.csv
```

**Errore**: Encoding UTF-8
```bash
# Convertire CSV in UTF-8
iconv -f ISO-8859-1 -t UTF-8 soci_old.csv > soci.csv
```

### Problema: Date non riconosciute

Modificare lo script Python aggiungendo il formato nel vostro CSV:

```python
formats = [
    '%Y-%m-%d',
    '%d/%m/%Y',
    '%d-%m-%Y',
    '%Y/%m/%d',
    '%d.%m.%Y',  # Aggiungere qui il vostro formato
]
```

### Problema: Caratteri speciali corrotti

```bash
# Verificare encoding del CSV
file -i soci.csv

# Se necessario, convertire
iconv -f WINDOWS-1252 -t UTF-8 soci.csv > soci_utf8.csv
```

### Problema: Import troppo lento

```sql
-- Disabilitare temporaneamente gli indici
ALTER TABLE members DISABLE KEYS;
ALTER TABLE member_contacts DISABLE KEYS;
ALTER TABLE member_addresses DISABLE KEYS;

-- Eseguire import

-- Riabilitare indici
ALTER TABLE members ENABLE KEYS;
ALTER TABLE member_contacts ENABLE KEYS;
ALTER TABLE member_addresses ENABLE KEYS;
```

## Best Practices

1. **Sempre testare prima in sviluppo**
2. **Fare backup prima di ogni operazione**
3. **Verificare encoding UTF-8 del CSV**
4. **Controllare le statistiche dopo l'import**
5. **Documentare eventuali problemi riscontrati**
6. **Mantenere i backup per almeno 30 giorni**
7. **Verificare l'integrità referenziale dopo l'import**

## Supporto

Per problemi o domande:
1. Controllare questa guida e il README.md
2. Verificare i log di errore MySQL
3. Testare con il file soci_example.csv fornito
4. Aprire un issue su GitHub con:
   - Errore completo
   - Versione MySQL/MariaDB
   - Prime righe del CSV (anonimizzate)
   - Output dello script Python

## Checklist Pre-Import

- [ ] Backup database effettuato
- [ ] CSV preparato in UTF-8
- [ ] Script Python testato con soci_example.csv
- [ ] Test su database di sviluppo completato
- [ ] Verifiche integrità superate
- [ ] Script SQL finale generato
- [ ] Approvazione per import in produzione

## Checklist Post-Import

- [ ] 175 soci importati (SELECT COUNT(*) FROM members)
- [ ] Contatti importati verificati
- [ ] Indirizzi importati verificati
- [ ] Nessun contatto/indirizzo orfano
- [ ] Nessun duplicato matricola
- [ ] Ottimizzazione database eseguita
- [ ] Statistiche verificate
- [ ] Backup post-import effettuato

---

**Versione**: 1.0  
**Data**: 2025-12-07  
**Compatibilità**: MySQL 5.6+, MySQL 8.x, MariaDB 10.x
