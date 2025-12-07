-- =====================================================
-- IMPORT COMPLETO SOCI DA VECCHIO GESTIONALE A EASYVOL
-- 175 Soci Totali (Attivi, Dimessi, Decaduti)
-- Data Generazione: 2025-12-07
-- =====================================================
-- 
-- NOTA IMPORTANTE:
-- Questo script è un template strutturato per l'importazione dei 175 soci.
-- I dati devono essere inseriti dal file CSV del vecchio gestionale.
-- 
-- ISTRUZIONI:
-- 1. Prima di eseguire questo script, assicurarsi che il database sia aggiornato
-- 2. Il CSV deve contenere i 175 soci con i campi specificati nel problema
-- 3. Sostituire i valori [PLACEHOLDER] con i dati effettivi dal CSV
-- 4. Eseguire lo script su un database di test prima della produzione
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =====================================================
-- FASE 1: AGGIORNAMENTO SCHEMA DATABASE
-- Aggiunta campi mancanti alla tabella members
-- =====================================================
-- NOTA: Questi ALTER TABLE potrebbero generare errori se le colonne esistono già.
-- In tal caso, è possibile eseguire lo script ignorando gli errori di colonne duplicate
-- oppure commentare le righe relative alle colonne già esistenti.
-- =====================================================

-- Aggiunta campo birth_province
ALTER TABLE `members` 
ADD COLUMN `birth_province` varchar(5) AFTER `birth_place`;

-- Aggiunta campo gender
ALTER TABLE `members` 
ADD COLUMN `gender` enum('M', 'F', 'Altro') AFTER `tax_code`;

-- Aggiunta campo nationality
ALTER TABLE `members` 
ADD COLUMN `nationality` varchar(100) DEFAULT 'Italiana' AFTER `gender`;

-- Aggiunta campo blood_type
ALTER TABLE `members` 
ADD COLUMN `blood_type` varchar(10) AFTER `nationality`;

-- Aggiunta campo qualification (mansione)
ALTER TABLE `members` 
ADD COLUMN `qualification` varchar(255) AFTER `registration_date`;

-- Aggiunta campi per dimissioni/decadenza
ALTER TABLE `members` 
ADD COLUMN `dismissal_date` date AFTER `notes`;

ALTER TABLE `members` 
ADD COLUMN `dismissal_reason` text AFTER `dismissal_date`;

-- Aggiunta campo photo_path
ALTER TABLE `members` 
ADD COLUMN `photo_path` varchar(255) AFTER `photo`;

-- =====================================================
-- FASE 2: AGGIORNAMENTO TABELLE CORRELATE
-- Aggiunta campi mancanti a member_contacts e member_addresses
-- =====================================================
-- NOTA: Se le colonne esistono già o sono già rinominate, 
-- commentare le righe corrispondenti per evitare errori.
-- =====================================================

-- Aggiunta campo is_primary a member_contacts
ALTER TABLE `member_contacts` 
ADD COLUMN `is_primary` tinyint(1) DEFAULT 0 AFTER `value`;

-- Aggiunta campo notes a member_contacts
ALTER TABLE `member_contacts` 
ADD COLUMN `notes` text AFTER `is_primary`;

-- Rinomina colonna value a contact_value in member_contacts
-- IMPORTANTE: Commentare questa riga se la colonna è già stata rinominata
ALTER TABLE `member_contacts` 
CHANGE COLUMN `value` `contact_value` varchar(255) NOT NULL;

-- Modifica enum per supportare i tipi di contatto dal vecchio gestionale
ALTER TABLE `member_contacts` 
MODIFY COLUMN `contact_type` enum('telefono_fisso', 'telefono', 'cellulare', 'email', 'pec') NOT NULL;

-- Aggiunta campo is_primary a member_addresses
ALTER TABLE `member_addresses` 
ADD COLUMN `is_primary` tinyint(1) DEFAULT 0 AFTER `cap`;

-- Rinomina colonna number a civic_number in member_addresses (opzionale per allineamento)
-- NOTA: Questa è opzionale, il campo può rimanere 'number'
-- Lo script usa 'number' come nome colonna nei successivi INSERT
-- ALTER TABLE `member_addresses` 
-- CHANGE COLUMN `number` `civic_number` varchar(20);

-- =====================================================
-- FASE 3: IMPORTAZIONE SOCI
-- Template per l'importazione dei 175 soci dal CSV
-- =====================================================

-- ====================================================================
-- IMPORTANTE: Di seguito è riportato un esempio di struttura INSERT
-- Questo deve essere ripetuto per TUTTI i 175 soci presenti nel CSV
-- ====================================================================

-- ----------------------------------------------------------------
-- ESEMPIO SOCIO 1: [matr] - [cognome] [nome] ([tipo_socio])
-- ----------------------------------------------------------------
-- Mappatura campi dal CSV:
-- matr → registration_number
-- tipo_socio → member_type (SOCIO FONDATORE→fondatore, SOCIO ORDINARIO→ordinario)
-- stato → member_status (OPERATIVO→attivo, *DIMESSO*→dimesso, *DECADUTO*→decaduto)
-- cognome → last_name
-- nome → first_name
-- data_nascita → birth_date (formato: YYYY-MM-DD)
-- luogo_nascita → birth_place
-- prov_nascita → birth_province
-- codicefiscale → tax_code
-- problemialimentari → gender (MASCHIO→M, FEMMINA→F)
-- grup_sang → blood_type
-- anno_iscr → registration_date (formato: YYYY-MM-DD)
-- mansione → qualification
-- disp_territ → aggiungere in notes come "Disponibilità: [valore]"
-- altre_lingue → aggiungere in notes come "Lingue: [valore]"
-- prob_alim → aggiungere in notes come "Allergie: [valore]"
-- nuovocampo6 → aggiungere in notes come "Patente: [valore]"
-- note → dismissal_reason (per dimessi/decaduti)
-- nuovocampo1 → dismissal_date (per dimessi/decaduti, formato: YYYY-MM-DD)
-- created → created_at
-- last_upd → updated_at
-- ----------------------------------------------------------------

/*
-- TEMPLATE PER OGNI SOCIO (ripetere per tutti i 175 soci):

-- SOCIO [matr]: [cognome] [nome] ([tipo_socio] - [stato])
INSERT INTO members (
    registration_number, 
    member_type, 
    member_status,
    volunteer_status,
    last_name, 
    first_name, 
    birth_date, 
    birth_place, 
    birth_province, 
    tax_code, 
    gender,
    nationality, 
    blood_type, 
    registration_date,
    qualification, 
    notes, 
    dismissal_date,
    dismissal_reason,
    photo_path, 
    created_at, 
    updated_at
) VALUES (
    '[matr]',                                    -- numero matricola dal CSV
    '[member_type]',                             -- 'fondatore' o 'ordinario'
    '[member_status]',                           -- 'attivo', 'dimesso', o 'decaduto'
    '[volunteer_status]',                        -- 'operativo' se attivo, altrimenti 'non_operativo'
    '[cognome]',                                 -- cognome (escape virgolette: O''Brien)
    '[nome]',                                    -- nome (escape virgolette se necessario)
    '[data_nascita]',                            -- formato YYYY-MM-DD o NULL
    '[luogo_nascita]',                           -- luogo di nascita
    '[prov_nascita]',                            -- provincia nascita (sigla 2 lettere) o NULL
    '[codicefiscale]',                           -- codice fiscale o NULL
    '[gender]',                                  -- 'M' o 'F'
    '[nationality]',                             -- 'Italiana' o altro paese se estero
    '[grup_sang]',                               -- gruppo sanguigno o NULL
    '[anno_iscr]',                               -- data iscrizione formato YYYY-MM-DD o NULL
    '[mansione]',                                -- qualifica/mansione o NULL
    CONCAT_WS(' - ',                             -- concatena note multiple
        IF('[disp_territ]' != '', CONCAT('Disponibilità: ', '[disp_territ]'), NULL),
        IF('[altre_lingue]' != '', CONCAT('Lingue: ', '[altre_lingue]'), NULL),
        IF('[prob_alim]' != '', CONCAT('Allergie: ', '[prob_alim]'), NULL),
        IF('[nuovocampo6]' != '', CONCAT('Patente: ', '[nuovocampo6]'), NULL)
    ),
    '[dismissal_date]',                          -- data dimissioni/decadenza o NULL
    '[dismissal_reason]',                        -- motivo dimissioni/decadenza o NULL
    NULL,                                        -- photo_path
    '[created]',                                 -- created_at dal CSV o CURRENT_TIMESTAMP
    '[last_upd]'                                 -- updated_at dal CSV o CURRENT_TIMESTAMP
);
SET @member_id = LAST_INSERT_ID();

-- Inserimento CELLULARE (se presente nel CSV)
INSERT INTO member_contacts (member_id, contact_type, contact_value, is_primary, notes) 
SELECT @member_id, 'cellulare', '[cellulare]', 1, NULL
WHERE '[cellulare]' IS NOT NULL AND '[cellulare]' != '';

-- Inserimento TELEFONO FISSO (se presente nel CSV)
INSERT INTO member_contacts (member_id, contact_type, contact_value, is_primary, notes) 
SELECT @member_id, 'telefono', '[tel_fisso]', 0, NULL
WHERE '[tel_fisso]' IS NOT NULL AND '[tel_fisso]' != '';

-- Inserimento EMAIL (se presente nel CSV)
INSERT INTO member_contacts (member_id, contact_type, contact_value, is_primary, notes) 
SELECT @member_id, 'email', '[e_mail]', 1, NULL
WHERE '[e_mail]' IS NOT NULL AND '[e_mail]' != '';

-- Inserimento INDIRIZZO RESIDENZA (se presente nel CSV)
INSERT INTO member_addresses (member_id, address_type, street, number, city, province, cap, is_primary) 
SELECT @member_id, 'residenza', '[ind_resid]', '[civico]', '[comun_resid]', '[provincia]', '[cap_resid]', 1
WHERE '[ind_resid]' IS NOT NULL AND '[ind_resid]' != '';

-- ----------------------------------------------------------------
*/

-- =====================================================
-- SEZIONE DATI: INSERIMENTO DEI 175 SOCI
-- =====================================================
-- 
-- NOTA: I dati effettivi dei 175 soci devono essere inseriti qui.
-- Utilizzare il template sopra per ogni socio del CSV.
-- 
-- Esempio di come dovrebbero apparire i dati reali:
-- =====================================================

-- Esempio Socio 1: Attivo - Socio Fondatore
INSERT INTO members (
    registration_number, member_type, member_status, volunteer_status,
    last_name, first_name, birth_date, birth_place, birth_province, 
    tax_code, gender, nationality, blood_type, 
    registration_date, qualification, notes, 
    dismissal_date, dismissal_reason, photo_path, 
    created_at, updated_at
) VALUES (
    '001', 'fondatore', 'attivo', 'operativo',
    'ROSSI', 'MARIO', '1975-03-15', 'ROMA', 'RM', 
    'RSSMRA75C15H501Z', 'M', 'Italiana', 'A+', 
    '2010-01-15', 'SOCCORRITORE', 'Disponibilità: PROVINCIALE - Lingue: INGLESE - Patente: B', 
    NULL, NULL, NULL, 
    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
);
SET @member_001_id = LAST_INSERT_ID();

INSERT INTO member_contacts (member_id, contact_type, contact_value, is_primary, notes) VALUES
(@member_001_id, 'cellulare', '3331234567', 1, NULL),
(@member_001_id, 'email', 'mario.rossi@example.com', 1, NULL);

INSERT INTO member_addresses (member_id, address_type, street, number, city, province, cap, is_primary) VALUES
(@member_001_id, 'residenza', 'VIA ROMA', '10', 'ROMA', 'RM', '00100', 1);

-- Esempio Socio 2: Dimesso - Socio Ordinario
INSERT INTO members (
    registration_number, member_type, member_status, volunteer_status,
    last_name, first_name, birth_date, birth_place, birth_province, 
    tax_code, gender, nationality, blood_type, 
    registration_date, qualification, notes, 
    dismissal_date, dismissal_reason, photo_path, 
    created_at, updated_at
) VALUES (
    '002', 'ordinario', 'dimesso', 'non_operativo',
    'BIANCHI', 'LAURA', '1980-06-20', 'MILANO', 'MI', 
    'BNCLRA80H60F205X', 'F', 'Italiana', 'B+', 
    '2012-03-10', 'AUTISTA', 'Disponibilità: COMUNALE - Patente: B, C', 
    '2023-12-31', 'Trasferimento altra città', NULL, 
    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
);
SET @member_002_id = LAST_INSERT_ID();

INSERT INTO member_contacts (member_id, contact_type, contact_value, is_primary, notes) VALUES
(@member_002_id, 'cellulare', '3339876543', 1, NULL),
(@member_002_id, 'telefono', '0212345678', 0, NULL),
(@member_002_id, 'email', 'laura.bianchi@example.com', 1, NULL);

INSERT INTO member_addresses (member_id, address_type, street, number, city, province, cap, is_primary) VALUES
(@member_002_id, 'residenza', 'VIA MILANO', '25', 'MILANO', 'MI', '20100', 1);

-- Esempio Socio 3: Decaduto - Socio Ordinario
INSERT INTO members (
    registration_number, member_type, member_status, volunteer_status,
    last_name, first_name, birth_date, birth_place, birth_province, 
    tax_code, gender, nationality, blood_type, 
    registration_date, qualification, notes, 
    dismissal_date, dismissal_reason, photo_path, 
    created_at, updated_at
) VALUES (
    '003', 'ordinario', 'decaduto', 'non_operativo',
    'VERDI', 'GIUSEPPE', '1970-09-05', 'NAPOLI', 'NA', 
    'VRDGPP70P05F839Y', 'M', 'Italiana', '0+', 
    '2015-05-20', 'CENTRALINISTA', NULL, 
    '2024-06-30', 'Mancato pagamento quote', NULL, 
    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
);
SET @member_003_id = LAST_INSERT_ID();

INSERT INTO member_contacts (member_id, contact_type, contact_value, is_primary, notes) VALUES
(@member_003_id, 'cellulare', '3345678901', 1, NULL);

-- =====================================================
-- [CONTINUARE CON GLI ALTRI 172 SOCI DAL CSV]
-- Ripetere la struttura sopra per ogni socio rimanente
-- Totale richiesto: 175 soci
-- =====================================================

-- =====================================================
-- FASE 4: VERIFICA E STATISTICHE
-- =====================================================

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- Statistiche Importazione
SELECT 
    'IMPORTAZIONE COMPLETATA' as Stato,
    COUNT(*) as Totale_Soci,
    SUM(CASE WHEN member_status = 'attivo' THEN 1 ELSE 0 END) as Attivi,
    SUM(CASE WHEN member_status = 'dimesso' THEN 1 ELSE 0 END) as Dimessi,
    SUM(CASE WHEN member_status = 'decaduto' THEN 1 ELSE 0 END) as Decaduti,
    SUM(CASE WHEN member_type = 'fondatore' THEN 1 ELSE 0 END) as Fondatori,
    SUM(CASE WHEN member_type = 'ordinario' THEN 1 ELSE 0 END) as Ordinari
FROM members;

SELECT 'CONTATTI IMPORTATI' as Tipo, COUNT(*) as Totale FROM member_contacts;
SELECT 'INDIRIZZI IMPORTATI' as Tipo, COUNT(*) as Totale FROM member_addresses;

-- Verifica soci senza contatti
SELECT 
    'SOCI SENZA CONTATTI' as Tipo,
    COUNT(DISTINCT m.id) as Totale
FROM members m
LEFT JOIN member_contacts mc ON m.id = mc.member_id
WHERE mc.id IS NULL;

-- Verifica soci senza indirizzi
SELECT 
    'SOCI SENZA INDIRIZZO' as Tipo,
    COUNT(DISTINCT m.id) as Totale
FROM members m
LEFT JOIN member_addresses ma ON m.id = ma.member_id
WHERE ma.id IS NULL;

-- =====================================================
-- FINE SCRIPT IMPORTAZIONE
-- =====================================================
-- 
-- NOTE FINALI:
-- 1. Questo script deve essere personalizzato con i dati reali dal CSV
-- 2. Testare sempre su database di sviluppo prima di produzione
-- 3. Fare backup del database prima di eseguire questo script
-- 4. Verificare che tutti i 175 soci siano stati importati correttamente
-- 5. Controllare le statistiche finali per validare l'importazione
-- =====================================================
