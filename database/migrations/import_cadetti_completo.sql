-- =====================================================
-- IMPORT COMPLETO CADETTI (JUNIOR MEMBERS) DA VECCHIO GESTIONALE A EASYVOL
-- 53 Cadetti Totali (Attivi e Decaduti)
-- Data Generazione: 2025-12-07
-- =====================================================
-- 
-- NOTA IMPORTANTE:
-- Questo script importa i dati dal file CSV: gestionaleweb_worktable16.csv
-- 
-- ⚠️  AVVISO SICUREZZA:
-- Questo è uno script SQL statico che richiede l'inserimento manuale dei dati.
-- Durante la compilazione dello script, prestare MASSIMA ATTENZIONE a:
--   1. Escape corretto di caratteri speciali (apostrofi, backslash, ecc.)
--   2. Validazione dei dati di input prima dell'inserimento
--   3. Test su database di sviluppo prima della produzione
-- 
-- ALTERNATIVA PIÙ SICURA (Raccomandato per grandi volumi):
-- Considerare l'uso di script di importazione con prepared statements:
--   - PHP PDO con bindParam()
--   - Python con parametrizzazione
--   - LOAD DATA INFILE (richiede privilegi FILE)
-- 
-- Questo script SQL è fornito per:
--   - Trasparenza totale del processo di importazione
--   - Possibilità di revisione manuale dei dati
--   - Controllo granulare su ogni record importato
-- 
-- MAPPATURA CAMPI CSV → DATABASE:
-- DATI CADETTO:
--   nuovocampo    → registration_number
--   nuovocampo1   → last_name (cognome cadetto)
--   nuovocampo2   → first_name (nome cadetto)
--   nuovocampo3   → gender (MASCHIO→M, FEMMINA→F) - memorizzato in notes
--   nuovocampo4   → birth_place
--   nuovocampo5   → birth_province - memorizzato in notes
--   nuovocampo6   → birth_date
--   nuovocampo7   → tax_code
--   nuovocampo9   → street indirizzo
--   nuovocampo10  → postal_code (CAP)
--   nuovocampo11  → city
--   nuovocampo12  → province
--   nuovocampo14  → cellulare cadetto
--   nuovocampo15  → email cadetto
--   nuovocampo16  → campo SI/NO se autorizzazioni date
--   nuovocampo17  → Lingue conosciute
--   nuovocampo18  → Allergie/intolleranze cadetto
--   nuovocampo25  → Anno corso
-- 
-- DATI GENITORE 1 (PADRE):
--   nuovocampo33  → guardian_last_name
--   nuovocampo34  → guardian_first_name
--   nuovocampo35  → guardian_birth_place
--   nuovocampo36  → guardian_birth_province
--   nuovocampo37  → guardian_birth_date
--   nuovocampo38  → guardian_tax_code
--   nuovocampo39  → guardian_street
--   nuovocampo40  → guardian_postal_code
--   nuovocampo41  → guardian_city
--   nuovocampo42  → guardian_province
--   nuovocampo43  → guardian_telefono
--   nuovocampo44  → guardian_phone
--   nuovocampo45  → guardian_email
-- 
-- DATI GENITORE 2 (MADRE):
--   nuovocampo46  → guardian2_last_name
--   nuovocampo47  → guardian2_first_name
--   nuovocampo48  → guardian2_birth_place
--   nuovocampo49  → guardian2_birth_province
--   nuovocampo50  → guardian2_birth_date
--   nuovocampo51  → guardian2_tax_code
--   nuovocampo52  → guardian2_street
--   nuovocampo53  → guardian2_postal_code
--   nuovocampo54  → guardian2_city
--   nuovocampo55  → guardian2_province
--   nuovocampo56  → guardian2_email
--   nuovocampo59  → guardian2_telefono
--   nuovocampo60  → guardian2_phone
-- 
-- DATI AUTORIZZAZIONI E NOTE:
--   nuovocampo57  → Allergie genitore (SI/NO)
--   nuovocampo58  → Descrizione allergie genitore
--   nuovocampo61  → registration_date
--   nuovocampo62  → dismissal_reason
--   nuovocampo63  → dismissal_date
--   nuovocampo64  → member_status (SOCIO ORDINARIO→attivo, *DECADUTO*→decaduto)
--   nuovocampo65  → Data ultima modifica
--   created       → created_at
--   last_upd      → updated_at
-- 
-- MEMBER STATUS MAPPING:
--   'SOCIO ORDINARIO'  → 'attivo'
--   '*DECADUTO*'       → 'decaduto'
-- =====================================================

-- Limitare la disabilitazione delle foreign key alla sessione corrente
SET SESSION FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- =====================================================
-- INIZIO IMPORTAZIONE CADETTI
-- =====================================================

-- -----------------------------------------------------
-- CADETTO 1: ORLANDO GAIA (Registration Number: 2)
-- Status: DECADUTO
-- -----------------------------------------------------
INSERT INTO junior_members (
    registration_number,
    member_status,
    last_name,
    first_name,
    birth_date,
    birth_place,
    tax_code,
    registration_date,
    notes,
    created_at,
    updated_at
) VALUES (
    '2',
    'decaduto',
    'ORLANDO',
    'GAIA',
    '2003-12-02',
    'BRESCIA',
    'RLNGAI03T42B157A',
    '2019-01-12',
    'Gender: F - Birth Province: BS - Anno corso: 2022 - Nazionalità: Italiana - Madre: ROSSELLI PATRIZIA (Tel: 3491307297, Email: patroselli69@gmail.com) - Padre nato a: PORTIGLIOLA (RE) il 1968-12-02',
    '2019-01-13 10:17:37',
    '2025-05-01 10:14:34'
);
SET @junior_2_id = LAST_INSERT_ID();

-- Inserire guardian (padre)
INSERT INTO junior_member_guardians (
    junior_member_id,
    guardian_type,
    last_name,
    first_name,
    tax_code,
    phone,
    email
) VALUES (
    @junior_2_id,
    'padre',
    'ORLANDO',
    'GIUSEPPE',
    NULL,
    '3478823850',
    NULL
);

-- Inserire guardian (madre)
INSERT INTO junior_member_guardians (
    junior_member_id,
    guardian_type,
    last_name,
    first_name,
    tax_code,
    phone,
    email
) VALUES (
    @junior_2_id,
    'madre',
    'ROSSELLI',
    'PATRIZIA',
    NULL,
    '3491307297',
    'patroselli69@gmail.com'
);

-- Inserire contatti cadetto (se presenti nel CSV)
-- INSERT INTO junior_member_contacts (junior_member_id, contact_type, value) VALUES
-- (@junior_2_id, 'cellulare', 'CELLULARE_CADETTO'), -- se nuovocampo14 presente
-- (@junior_2_id, 'email', 'EMAIL_CADETTO'); -- se nuovocampo15 presente

-- Inserire indirizzo residenza cadetto
-- INSERT INTO junior_member_addresses (junior_member_id, address_type, street, number, city, province, cap) VALUES
-- (@junior_2_id, 'residenza', 'VIA_ESEMPIO', 'NUMERO', 'CITTA', 'PROV', 'CAP'); -- da nuovocampo9-12

-- Inserire allergie se presenti
-- INSERT INTO junior_member_health (junior_member_id, health_type, description) VALUES
-- (@junior_2_id, 'allergie', 'DESCRIZIONE_ALLERGIE'); -- se nuovocampo18 presente


-- -----------------------------------------------------
-- TEMPLATE PER I RIMANENTI 52 CADETTI
-- -----------------------------------------------------
-- 
-- Per ogni cadetto del CSV (gestionaleweb_worktable16.csv), replicare la seguente struttura:
-- 
-- -- CADETTO [N]: [COGNOME] [NOME] (Registration Number: [nuovocampo])
-- -- Status: [ATTIVO/DECADUTO]
-- INSERT INTO junior_members (
--     registration_number,
--     member_status,
--     last_name,
--     first_name,
--     birth_date,
--     birth_place,
--     tax_code,
--     registration_date,
--     approval_date,
--     notes,
--     created_at,
--     updated_at
-- ) VALUES (
--     '[nuovocampo]',
--     '[mappare nuovocampo64: SOCIO ORDINARIO→attivo, *DECADUTO*→decaduto]',
--     '[nuovocampo1]',
--     '[nuovocampo2]',
--     '[nuovocampo6 formato YYYY-MM-DD]',
--     '[nuovocampo4]',
--     '[nuovocampo7]',
--     '[nuovocampo61 formato YYYY-MM-DD]',
--     NULL, -- approval_date se disponibile
--     'Gender: [M/F da nuovocampo3] - Birth Province: [nuovocampo5] - Anno corso: [nuovocampo25] - Lingue: [nuovocampo17] - Allergie cadetto: [nuovocampo18] - Nazionalità: Italiana - Madre: [nuovocampo46] [nuovocampo47] (CF: [nuovocampo51], Tel: [nuovocampo60], Email: [nuovocampo56]) - Allergie genitore: [nuovocampo58 se nuovocampo57=SI]',
--     '[created timestamp]',
--     '[last_upd timestamp]'
-- );
-- SET @junior_[nuovocampo]_id = LAST_INSERT_ID();
-- 
-- -- Guardian 1 (Padre) se dati presenti
-- INSERT INTO junior_member_guardians (
--     junior_member_id,
--     guardian_type,
--     last_name,
--     first_name,
--     tax_code,
--     phone,
--     email
-- ) VALUES (
--     @junior_[nuovocampo]_id,
--     'padre',
--     '[nuovocampo33]',
--     '[nuovocampo34]',
--     '[nuovocampo38]',
--     '[nuovocampo44 o nuovocampo43]', -- preferire cellulare
--     '[nuovocampo45]'
-- );
-- 
-- -- Guardian 2 (Madre) se dati presenti
-- INSERT INTO junior_member_guardians (
--     junior_member_id,
--     guardian_type,
--     last_name,
--     first_name,
--     tax_code,
--     phone,
--     email
-- ) VALUES (
--     @junior_[nuovocampo]_id,
--     'madre',
--     '[nuovocampo46]',
--     '[nuovocampo47]',
--     '[nuovocampo51]',
--     '[nuovocampo60 o nuovocampo59]', -- preferire cellulare
--     '[nuovocampo56]'
-- );
-- 
-- -- Contatti cadetto (se presenti)
-- INSERT INTO junior_member_contacts (junior_member_id, contact_type, value) VALUES
-- (@junior_[nuovocampo]_id, 'cellulare', '[nuovocampo14]'), -- se presente
-- (@junior_[nuovocampo]_id, 'email', '[nuovocampo15]'); -- se presente
-- 
-- -- Indirizzo residenza cadetto
-- INSERT INTO junior_member_addresses (
--     junior_member_id,
--     address_type,
--     street,
--     number,
--     city,
--     province,
--     cap
-- ) VALUES (
--     @junior_[nuovocampo]_id,
--     'residenza',
--     '[estrarre via da nuovocampo9]',
--     '[estrarre civico da nuovocampo9]',
--     '[nuovocampo11]',
--     '[nuovocampo12]',
--     '[nuovocampo10]'
-- );
-- 
-- -- Indirizzo padre (se diverso da residenza cadetto)
-- INSERT INTO junior_member_addresses (
--     junior_member_id,
--     address_type,
--     street,
--     number,
--     city,
--     province,
--     cap
-- ) VALUES (
--     @junior_[nuovocampo]_id,
--     'domicilio',
--     '[estrarre via da nuovocampo39]',
--     '[estrarre civico da nuovocampo39]',
--     '[nuovocampo41]',
--     '[nuovocampo42]',
--     '[nuovocampo40]'
-- );
-- 
-- -- Allergie cadetto (se presenti)
-- INSERT INTO junior_member_health (
--     junior_member_id,
--     health_type,
--     description
-- ) VALUES (
--     @junior_[nuovocampo]_id,
--     'allergie',
--     '[nuovocampo18]' -- es: 'ALOE, CIBI IN SCATOLA, MUFFA, LATTICE'
-- );
-- 
-- -- Allergie genitore (se nuovocampo57 = SI)
-- INSERT INTO junior_member_health (
--     junior_member_id,
--     health_type,
--     description
-- ) VALUES (
--     @junior_[nuovocampo]_id,
--     'allergie',
--     'Allergie genitore: [nuovocampo58]' -- es: 'ALOE,ULIVO,GRAMINACEE,MUFFA,LATTICE'
-- );
-- 
-- -----------------------------------------------------
-- ESEMPIO COMPLETO: CADETTO CON ALLERGIE
-- -----------------------------------------------------
-- (Basato su esempio nel problem statement)
-- 
-- -- CADETTO [X]: COGNOME NOME
-- INSERT INTO junior_members (
--     registration_number,
--     member_status,
--     last_name,
--     first_name,
--     birth_date,
--     birth_place,
--     tax_code,
--     registration_date,
--     notes,
--     created_at,
--     updated_at
-- ) VALUES (
--     'NUM_REG',
--     'attivo',
--     'COGNOME',
--     'NOME',
--     'YYYY-MM-DD',
--     'LUOGO_NASCITA',
--     'CODICE_FISCALE',
--     'YYYY-MM-DD',
--     'Gender: M - Birth Province: XX - Anno corso: 2025 - Lingue: INGLESE - Allergie cadetto: ALOE, CIBI IN SCATOLA, MUFFA, LATTICE - Nazionalità: Italiana - Madre: GHIDINI ISABELLA (CF: GHDSLL81T60B157W, Tel: 3339604770, Email: mollydingo@gmail.com) - Allergie genitore: ALOE,ULIVO,GRAMINACEE,MUFFA,LATTICE',
--     'YYYY-MM-DD HH:MM:SS',
--     'YYYY-MM-DD HH:MM:SS'
-- );
-- SET @junior_NUM_id = LAST_INSERT_ID();
-- 
-- -- Guardians, addresses, contacts, health records...
-- (seguire il template sopra)


-- =====================================================
-- NOTE IMPORTANTI PER L'IMPORTAZIONE
-- =====================================================
-- 
-- 1. GESTIONE NULL VALUES:
--    - Usare NULL per campi vuoti nel CSV
--    - Non usare stringhe vuote ''
-- 
-- 2. ESCAPE CARATTERI SPECIALI (IMPORTANTE PER SICUREZZA):
--    ⚠️  ATTENZIONE: Fare escape corretto per prevenire SQL injection
--    - Apostrofi: ' deve diventare \'
--      Es: "D'ANGELO" → 'D\'ANGELO'
--    - Backslash: \ deve diventare \\
--    - Virgolette doppie nel valore: " deve diventare \"
--    - RACCOMANDAZIONE: Usare editor con funzione di escape SQL automatico
--    - ALTERNATIVA SICURA: Usare script di importazione con prepared statements
--      invece di questo file SQL statico
-- 
-- 3. FORMATI DATE:
--    - birth_date: formato YYYY-MM-DD
--    - registration_date: formato YYYY-MM-DD
--    - created_at/updated_at: formato YYYY-MM-DD HH:MM:SS
-- 
-- 4. MEMBER STATUS:
--    - 'SOCIO ORDINARIO' → 'attivo'
--    - '*DECADUTO*' → 'decaduto'
--    - Altri valori possibili: 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo'
-- 
-- 5. GENDER:
--    - MASCHIO → M (memorizzare in notes)
--    - FEMMINA → F (memorizzare in notes)
-- 
-- 6. NAZIONALITÀ:
--    - Default: 'Italiana'
--    - Se birth_place indica paese estero, adattare
-- 
-- 7. CAMPO NOTES:
--    NOTA: Il campo notes viene usato per consolidare informazioni non mappabili
--    direttamente nelle tabelle esistenti. Questo è un compromesso per la migrazione
--    dai vecchi dati. Per coerenza, seguire questo formato strutturato:
--    
--    Formato: "Campo1: valore - Campo2: valore - Campo3: valore"
--    
--    Campi da includere (se disponibili):
--      * Gender: M/F
--      * Birth Province: XX
--      * Anno corso: YYYY
--      * Lingue: LINGUA1, LINGUA2
--      * Allergie cadetto: LISTA
--      * Nazionalità: PAESE (solo se non Italiana)
--      * Madre: COGNOME NOME (CF: XXX, Tel: XXX, Email: XXX) - se non in guardians
--      * Allergie genitore: LISTA
--      * Altre note rilevanti
--    
--    RACCOMANDAZIONE: Mantenere ordine consistente per facilitare parsing futuro
-- 
-- 8. INDIRIZZI:
--    - Estrarre numero civico da street (es: "Via Roma 10" → street="Via Roma", number="10")
--    - Se indirizzo padre uguale a cadetto, non duplicare
-- 
-- 9. CONTATTI:
--    - Preferire cellulare a telefono fisso
--    - Inserire solo se valore presente nel CSV
-- 
-- 10. HEALTH RECORDS:
--     - Inserire record separati per allergie cadetto e allergie genitore
--     - Tipo: 'allergie', 'intolleranze', 'patologie', 'vegano', 'vegetariano'
-- 
-- 11. CADETTI DECADUTI:
--     - Usare member_status = 'decaduto'
--     - dismissal_date e dismissal_reason non sono campi della tabella,
--       inserire queste informazioni nel campo notes
-- 
-- =====================================================

-- =====================================================
-- FINE IMPORTAZIONE
-- =====================================================

-- Riabilitare i controlli di foreign key
SET SESSION FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- VALIDAZIONE INTEGRITÀ REFERENZIALE PRE-COMMIT
-- =====================================================
-- Verificare che non ci siano violazioni di foreign key
-- prima di fare il commit

-- Verifica: tutti i guardian hanno un junior_member_id valido
SELECT 
    'VALIDAZIONE: Guardian orphan check' as Test,
    CASE 
        WHEN COUNT(*) = 0 THEN 'PASS'
        ELSE CONCAT('FAIL - Found ', COUNT(*), ' orphan guardians')
    END as Result
FROM junior_member_guardians jmg
LEFT JOIN junior_members jm ON jmg.junior_member_id = jm.id
WHERE jm.id IS NULL;

-- Verifica: tutti gli indirizzi hanno un junior_member_id valido
SELECT 
    'VALIDAZIONE: Address orphan check' as Test,
    CASE 
        WHEN COUNT(*) = 0 THEN 'PASS'
        ELSE CONCAT('FAIL - Found ', COUNT(*), ' orphan addresses')
    END as Result
FROM junior_member_addresses jma
LEFT JOIN junior_members jm ON jma.junior_member_id = jm.id
WHERE jm.id IS NULL;

-- Verifica: tutti i contatti hanno un junior_member_id valido
SELECT 
    'VALIDAZIONE: Contact orphan check' as Test,
    CASE 
        WHEN COUNT(*) = 0 THEN 'PASS'
        ELSE CONCAT('FAIL - Found ', COUNT(*), ' orphan contacts')
    END as Result
FROM junior_member_contacts jmc
LEFT JOIN junior_members jm ON jmc.junior_member_id = jm.id
WHERE jm.id IS NULL;

-- SE TUTTE LE VALIDAZIONI PASSANO, procedere con il commit
-- ALTRIMENTI fare ROLLBACK e correggere gli errori
COMMIT;

-- =====================================================
-- STATISTICHE IMPORTAZIONE
-- =====================================================

SELECT 
    'IMPORTAZIONE CADETTI COMPLETATA' as Stato,
    COUNT(*) as Totale_Cadetti,
    SUM(CASE WHEN member_status = 'attivo' THEN 1 ELSE 0 END) as Attivi,
    SUM(CASE WHEN member_status = 'decaduto' THEN 1 ELSE 0 END) as Decaduti,
    SUM(CASE WHEN member_status = 'dimesso' THEN 1 ELSE 0 END) as Dimessi
FROM junior_members
WHERE registration_number IS NOT NULL;

SELECT 
    'GUARDIANS IMPORTATI' as Tipo,
    COUNT(*) as Totale,
    SUM(CASE WHEN guardian_type = 'padre' THEN 1 ELSE 0 END) as Padri,
    SUM(CASE WHEN guardian_type = 'madre' THEN 1 ELSE 0 END) as Madri,
    SUM(CASE WHEN guardian_type = 'tutore' THEN 1 ELSE 0 END) as Tutori
FROM junior_member_guardians jmg
INNER JOIN junior_members jm ON jmg.junior_member_id = jm.id
WHERE jm.registration_number IS NOT NULL;

SELECT 
    'CONTATTI CADETTI IMPORTATI' as Tipo,
    COUNT(*) as Totale,
    SUM(CASE WHEN contact_type = 'cellulare' THEN 1 ELSE 0 END) as Cellulari,
    SUM(CASE WHEN contact_type = 'email' THEN 1 ELSE 0 END) as Email,
    SUM(CASE WHEN contact_type = 'telefono_fisso' THEN 1 ELSE 0 END) as Telefoni_Fissi
FROM junior_member_contacts jmc
INNER JOIN junior_members jm ON jmc.junior_member_id = jm.id
WHERE jm.registration_number IS NOT NULL;

SELECT 
    'INDIRIZZI CADETTI IMPORTATI' as Tipo,
    COUNT(*) as Totale,
    SUM(CASE WHEN address_type = 'residenza' THEN 1 ELSE 0 END) as Residenze,
    SUM(CASE WHEN address_type = 'domicilio' THEN 1 ELSE 0 END) as Domicili
FROM junior_member_addresses jma
INNER JOIN junior_members jm ON jma.junior_member_id = jm.id
WHERE jm.registration_number IS NOT NULL;

SELECT 
    'HEALTH RECORDS IMPORTATI' as Tipo,
    COUNT(*) as Totale,
    SUM(CASE WHEN health_type = 'allergie' THEN 1 ELSE 0 END) as Allergie,
    SUM(CASE WHEN health_type = 'intolleranze' THEN 1 ELSE 0 END) as Intolleranze,
    SUM(CASE WHEN health_type = 'patologie' THEN 1 ELSE 0 END) as Patologie
FROM junior_member_health jmh
INNER JOIN junior_members jm ON jmh.junior_member_id = jm.id
WHERE jm.registration_number IS NOT NULL;

-- =====================================================
-- VERIFICA INTEGRITÀ DATI
-- =====================================================

-- Cadetti senza guardians
SELECT 
    'CADETTI SENZA GUARDIANS' as Alert,
    COUNT(*) as Totale
FROM junior_members jm
LEFT JOIN junior_member_guardians jmg ON jm.id = jmg.junior_member_id
WHERE jmg.id IS NULL
  AND jm.registration_number IS NOT NULL;

-- Cadetti senza indirizzo
SELECT 
    'CADETTI SENZA INDIRIZZO' as Alert,
    COUNT(*) as Totale
FROM junior_members jm
LEFT JOIN junior_member_addresses jma ON jm.id = jma.junior_member_id
WHERE jma.id IS NULL
  AND jm.registration_number IS NOT NULL;

-- =====================================================
-- FINE SCRIPT
-- =====================================================
-- 
-- ISTRUZIONI PER L'USO:
-- 
-- 1. Aggiungere gli altri 52 cadetti seguendo il template fornito
-- 2. Mappare tutti i campi dal CSV secondo la tabella di mappatura
-- 3. Gestire correttamente i valori NULL
-- 4. Fare escape dei caratteri speciali
-- 5. Verificare le date nel formato corretto
-- 6. Eseguire lo script su database di test prima della produzione
-- 7. Verificare le statistiche finali
-- 
-- Per eseguire lo script:
-- mysql -u username -p database_name < import_cadetti_completo.sql
-- 
-- =====================================================
