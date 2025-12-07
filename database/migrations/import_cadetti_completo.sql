-- =====================================================
-- IMPORT CADETTI DA VECCHIO GESTIONALE
-- Totale record: 53
-- Data: 2025-12-07
-- 
-- NOTA: Questo file contiene la struttura per l'importazione
-- dei 53 cadetti dal file cadetti.csv
-- 
-- Per generare il file completo:
-- 1. Fornire il file cadetti.csv
-- 2. Utilizzare uno script di conversione
-- 3. Sostituire la sezione ESEMPIO con tutti i 53 record
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
START TRANSACTION;

-- =====================================================
-- SEZIONE DATI - DA POPOLARE CON TUTTI I 53 CADETTI
-- =====================================================

-- ESEMPIO 1: CADETTO ATTIVO CON TUTTI I DATI
INSERT INTO `junior_members` (
    `registration_number`,
    `member_status`,
    `last_name`,
    `first_name`,
    `birth_date`,
    `birth_place`,
    `tax_code`,
    `registration_date`,
    `notes`,
    `created_at`,
    `updated_at`
) VALUES (
    'C001',                            -- nuovocampo (registration_number)
    'attivo',                          -- nuovocampo64: SOCIO ORDINARIO → attivo
    'FERRARI',                         -- nuovocampo1 (last_name)
    'PAOLO',                           -- nuovocampo2 (first_name)
    '2010-05-15',                      -- nuovocampo6 (birth_date)
    'ROMA',                            -- nuovocampo4 (birth_place)
    'FRRPLA10E15H501X',               -- nuovocampo7 (tax_code)
    '2023-09-01',                      -- nuovocampo61 (registration_date)
    -- notes: consolidamento campi extra (non presenti nello schema DB corrente)
    'Provincia nascita: RM
Sesso: M
Gruppo sanguigno: A+
Anno corso: 2023/2024
Lingue cadetto: Inglese
Allergie cadetto: Nessuna
Allergie genitore: Nessuna

MADRE:
Nome: LUCIA MARTINI
Nata a: ROMA (RM) il 1985-03-20
CF: MRTLCU85C60H501Y
Indirizzo: Via dei Fiori, 00100 ROMA (RM)
Tel: 3381234567 / 3391234568
Email: lucia.martini@example.com

PADRE:
Nato a: ROMA (RM) il 1983-07-10
Indirizzo: Via dei Fiori, 00100 ROMA (RM)
Tel fisso: 0612345678',
    NOW(),
    NOW()
);
SET @junior_id = LAST_INSERT_ID();

-- Tutore principale (Padre) per cadetto C001
INSERT INTO `junior_member_guardians` (
    `junior_member_id`,
    `guardian_type`,
    `last_name`,
    `first_name`,
    `tax_code`,
    `phone`,
    `email`
) VALUES (
    @junior_id,
    'padre',                           -- guardian_relationship = genitore
    'FERRARI',                         -- nuovocampo33 (guardian_last_name)
    'MARCO',                           -- nuovocampo34 (guardian_first_name)
    'FRRMRC83L10H501Z',               -- nuovocampo38 (guardian_tax_code)
    '3401234567',                      -- nuovocampo44 (guardian_phone)
    'marco.ferrari@example.com'        -- nuovocampo45 (guardian_email)
);

-- Contatti cadetto C001
INSERT INTO `junior_member_contacts` (`junior_member_id`, `contact_type`, `value`) VALUES
    (@junior_id, 'cellulare', '3331234567'),   -- nuovocampo14 (cellulare cadetto - primary)
    (@junior_id, 'email', 'paolo.ferrari@example.com'); -- nuovocampo15 (email cadetto - primary)

-- Indirizzi cadetto C001
INSERT INTO `junior_member_addresses` (
    `junior_member_id`,
    `address_type`,
    `street`,
    `number`,
    `city`,
    `province`,
    `cap`
) VALUES
    -- Residenza cadetto (primary)
    (@junior_id, 'residenza', 'Via dei Fiori', '12', 'ROMA', 'RM', '00100');

-- =====================================================

-- ESEMPIO 2: CADETTO ATTIVO CON INDIRIZZI DIVERSI PER GENITORI
INSERT INTO `junior_members` (
    `registration_number`,
    `member_status`,
    `last_name`,
    `first_name`,
    `birth_date`,
    `birth_place`,
    `tax_code`,
    `registration_date`,
    `notes`,
    `created_at`,
    `updated_at`
) VALUES (
    'C002',
    'attivo',
    'COLOMBO',
    'SARA',
    '2011-08-22',
    'MILANO',
    'CLMSRA11M62F205W',
    '2023-09-01',
    'Provincia nascita: MI
Sesso: F
Gruppo sanguigno: B+
Anno corso: 2023/2024
Lingue cadetto: Inglese, Francese
Allergie cadetto: Lattosio
Allergie genitore: Nessuna

MADRE:
Nome: ELENA RUSSO
Nata a: MILANO (MI) il 1986-11-15
CF: RSSLNE86S55F205K
Indirizzo: Via Montenapoleone, 20100 MILANO (MI)
Tel: 3392223344 / 3393334455
Email: elena.russo@example.com

PADRE:
Nato a: NAPOLI (NA) il 1984-02-28
Indirizzo: Via Garibaldi, 80100 NAPOLI (NA)
Tel fisso: 0815556677',
    NOW(),
    NOW()
);
SET @junior_id = LAST_INSERT_ID();

-- Tutore principale (Padre) per cadetto C002
INSERT INTO `junior_member_guardians` (
    `junior_member_id`,
    `guardian_type`,
    `last_name`,
    `first_name`,
    `tax_code`,
    `phone`,
    `email`
) VALUES (
    @junior_id,
    'padre',
    'COLOMBO',
    'ANTONIO',
    'CLMNTN84B28F839P',
    '3402223344',
    'antonio.colombo@example.com'
);

-- Contatti cadetto C002
INSERT INTO `junior_member_contacts` (`junior_member_id`, `contact_type`, `value`) VALUES
    (@junior_id, 'cellulare', '3345556677'),
    (@junior_id, 'email', 'sara.colombo@example.com');

-- Indirizzi cadetto C002 (residenza + indirizzi genitori)
INSERT INTO `junior_member_addresses` (
    `junior_member_id`,
    `address_type`,
    `street`,
    `number`,
    `city`,
    `province`,
    `cap`
) VALUES
    -- Residenza cadetto (con padre a Napoli)
    (@junior_id, 'residenza', 'Via Garibaldi', '45', 'NAPOLI', 'NA', '80100'),
    -- Residenza madre (diversa - a Milano)
    (@junior_id, 'domicilio', 'Via Montenapoleone', '8', 'MILANO', 'MI', '20100');

-- =====================================================

-- ESEMPIO 3: CADETTO DECADUTO
INSERT INTO `junior_members` (
    `registration_number`,
    `member_status`,
    `last_name`,
    `first_name`,
    `birth_date`,
    `birth_place`,
    `tax_code`,
    `registration_date`,
    `notes`,
    `created_at`,
    `updated_at`
) VALUES (
    'C003',
    'decaduto',                        -- nuovocampo64: *DECADUTO* → decaduto
    'BRUNO',
    'ALESSIO',
    '2009-12-10',
    'TORINO',
    'BRNLSS09T10L219M',
    '2022-09-01',
    'Provincia nascita: TO
Sesso: M
Gruppo sanguigno: 0+
Anno corso: 2022/2023
Data decadenza: 2024-06-30
Motivo decadenza: Maggiore età raggiunta

MADRE:
Nome: CARLA MANCINI
Nata a: TORINO (TO) il 1987-04-12
CF: MNCCRLA87D52L219T
Indirizzo: Corso Francia, 10100 TORINO (TO)
Tel: 3356667788 / 3357778899
Email: carla.mancini@example.com

PADRE:
Nato a: TORINO (TO) il 1985-09-05
Indirizzo: Corso Francia, 10100 TORINO (TO)
Tel fisso: 0118889900',
    NOW(),
    NOW()
);
SET @junior_id = LAST_INSERT_ID();

-- Tutore principale (Padre) per cadetto C003
INSERT INTO `junior_member_guardians` (
    `junior_member_id`,
    `guardian_type`,
    `last_name`,
    `first_name`,
    `tax_code`,
    `phone`,
    `email`
) VALUES (
    @junior_id,
    'padre',
    'BRUNO',
    'SIMONE',
    'BRNSMN85P05L219Y',
    '3358889900',
    'simone.bruno@example.com'
);

-- Contatti cadetto C003
INSERT INTO `junior_member_contacts` (`junior_member_id`, `contact_type`, `value`) VALUES
    (@junior_id, 'cellulare', '3369991122'),
    (@junior_id, 'email', 'alessio.bruno@example.com');

-- Indirizzo cadetto C003
INSERT INTO `junior_member_addresses` (
    `junior_member_id`,
    `address_type`,
    `street`,
    `number`,
    `city`,
    `province`,
    `cap`
) VALUES
    (@junior_id, 'residenza', 'Corso Francia', '120', 'TORINO', 'TO', '10100');

-- =====================================================

-- ESEMPIO 4: CADETTO CON NOME COMPOSTO E CARATTERI SPECIALI
INSERT INTO `junior_members` (
    `registration_number`,
    `member_status`,
    `last_name`,
    `first_name`,
    `birth_date`,
    `birth_place`,
    `tax_code`,
    `registration_date`,
    `notes`,
    `created_at`,
    `updated_at`
) VALUES (
    'C004',
    'attivo',
    'D\'ANGELO',                       -- escape di apostrofo
    'MARIA CHIARA',
    '2012-03-25',
    'FIRENZE',
    'DNGMCH12C65D612L',
    '2024-09-01',
    'Provincia nascita: FI
Sesso: F
Gruppo sanguigno: AB+
Anno corso: 2024/2025
Lingue cadetto: Inglese
Allergie cadetto: Polline
Allergie genitore: Nessuna

MADRE:
Nome: ANNA BIANCHI
Nata a: FIRENZE (FI) il 1988-06-30
CF: BNCNNA88H70D612S
Indirizzo: Via Tornabuoni, 50100 FIRENZE (FI)
Tel: 3371112233 / 3372223344
Email: anna.bianchi@example.com

PADRE:
Nato a: FIRENZE (FI) il 1986-01-20
Indirizzo: Via Tornabuoni, 50100 FIRENZE (FI)
Tel fisso: 0553334455',
    NOW(),
    NOW()
);
SET @junior_id = LAST_INSERT_ID();

-- Tutore principale (Padre) per cadetto C004
INSERT INTO `junior_member_guardians` (
    `junior_member_id`,
    `guardian_type`,
    `last_name`,
    `first_name`,
    `tax_code`,
    `phone`,
    `email`
) VALUES (
    @junior_id,
    'padre',
    'D\'ANGELO',                       -- escape di apostrofo
    'FRANCESCO',
    'DNGFNC86A20D612N',
    '3373334455',
    'francesco.dangelo@example.com'
);

-- Contatti cadetto C004
INSERT INTO `junior_member_contacts` (`junior_member_id`, `contact_type`, `value`) VALUES
    (@junior_id, 'cellulare', '3384445566'),
    (@junior_id, 'email', 'maria.dangelo@example.com');

-- Indirizzo cadetto C004
INSERT INTO `junior_member_addresses` (
    `junior_member_id`,
    `address_type`,
    `street`,
    `number`,
    `city`,
    `province`,
    `cap`
) VALUES
    (@junior_id, 'residenza', 'Via Tornabuoni', '15', 'FIRENZE', 'FI', '50100');

-- =====================================================

-- ESEMPIO 5: CADETTO CON TUTTI I CONTATTI GENITORI
INSERT INTO `junior_members` (
    `registration_number`,
    `member_status`,
    `last_name`,
    `first_name`,
    `birth_date`,
    `birth_place`,
    `tax_code`,
    `registration_date`,
    `notes`,
    `created_at`,
    `updated_at`
) VALUES (
    'C005',
    'attivo',
    'COSTA',
    'ANDREA',
    '2011-11-08',
    'BOLOGNA',
    'CSTNDR11S08A944R',
    '2023-09-01',
    'Provincia nascita: BO
Sesso: M
Gruppo sanguigno: A+
Anno corso: 2023/2024
Lingue cadetto: Inglese, Spagnolo
Allergie cadetto: Nessuna
Allergie genitore: Glutine

MADRE:
Nome: GIULIA RICCI
Nata a: BOLOGNA (BO) il 1989-09-12
CF: RCCGLI89P52A944V
Indirizzo: Via Rizzoli, 40100 BOLOGNA (BO)
Tel: 3395556677 / 3396667788
Email: giulia.ricci@example.com

PADRE:
Nato a: MODENA (MO) il 1987-05-18
Indirizzo: Via Rizzoli, 40100 BOLOGNA (BO)
Tel fisso: 0517778899',
    NOW(),
    NOW()
);
SET @junior_id = LAST_INSERT_ID();

-- Tutore principale (Padre) per cadetto C005
INSERT INTO `junior_member_guardians` (
    `junior_member_id`,
    `guardian_type`,
    `last_name`,
    `first_name`,
    `tax_code`,
    `phone`,
    `email`
) VALUES (
    @junior_id,
    'padre',
    'COSTA',
    'ROBERTO',
    'CSTRRT87E18F257K',
    '3397778899',
    'roberto.costa@example.com'
);

-- Contatti cadetto C005
-- Note: Current schema limitations - parent contacts are stored in notes field
-- junior_member_contacts table is for junior's contacts only
INSERT INTO `junior_member_contacts` (`junior_member_id`, `contact_type`, `value`) VALUES
    (@junior_id, 'cellulare', '3388889900'),   -- cellulare cadetto
    (@junior_id, 'email', 'andrea.costa@example.com'); -- email cadetto

-- Indirizzo cadetto C005
INSERT INTO `junior_member_addresses` (
    `junior_member_id`,
    `address_type`,
    `street`,
    `number`,
    `city`,
    `province`,
    `cap`
) VALUES
    (@junior_id, 'residenza', 'Via Rizzoli', '33', 'BOLOGNA', 'BO', '40100');

-- =====================================================
-- QUI AGGIUNGERE GLI ALTRI 48 CADETTI
-- Seguire lo stesso pattern degli esempi sopra
-- =====================================================

-- [INSERIRE RECORD C006-C053]

-- =====================================================
-- FINE INSERIMENTI
-- =====================================================

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- STATISTICHE FINALI
-- =====================================================

SELECT 'IMPORT CADETTI COMPLETATO' AS Stato,
    COUNT(*) AS Totale,
    SUM(CASE WHEN member_status='attivo' THEN 1 ELSE 0 END) AS Attivi,
    SUM(CASE WHEN member_status='decaduto' THEN 1 ELSE 0 END) AS Decaduti
FROM `junior_members`;

-- Verifica tutori
SELECT 'TUTORI IMPORTATI' AS Tipo, COUNT(*) AS Totale
FROM `junior_member_guardians`;

-- Verifica contatti cadetti
SELECT 'CONTATTI IMPORTATI' AS Tipo, COUNT(*) AS Totale
FROM `junior_member_contacts`;

-- Verifica indirizzi cadetti
SELECT 'INDIRIZZI IMPORTATI' AS Tipo, COUNT(*) AS Totale
FROM `junior_member_addresses`;

-- =====================================================
-- NOTE SULL'UTILIZZO
-- =====================================================
-- 
-- COME COMPLETARE QUESTO FILE:
-- 1. Fornire il file cadetti.csv con i 53 record
-- 2. Per ogni record del CSV, creare un blocco come negli esempi
-- 3. Mappare i campi come descritto nella sezione header
-- 4. Gestire l'escape di caratteri speciali (es. D'ANGELO → D\'ANGELO)
-- 5. Inserire NULL per campi vuoti
-- 6. Consolidare campi extra nel campo notes
-- 
-- MAPPATURA CAMPI CADETTO:
-- nuovocampo → registration_number
-- nuovocampo64 → member_status (SOCIO ORDINARIO→attivo, *DECADUTO*→decaduto)
-- nuovocampo1 → last_name
-- nuovocampo2 → first_name
-- nuovocampo6 → birth_date
-- nuovocampo4 → birth_place
-- nuovocampo7 → tax_code
-- nuovocampo61 → registration_date
-- nuovocampo5 → birth_province (va in notes)
-- nuovocampo3 → gender (va in notes: MASCHIO→M, FEMMINA→F)
-- nuovocampo8 → blood_type (va in notes)
-- 
-- MAPPATURA TUTORE PRINCIPALE (Padre):
-- nuovocampo33 → guardian_last_name
-- nuovocampo34 → guardian_first_name
-- nuovocampo38 → guardian_tax_code
-- nuovocampo44 → guardian_phone
-- nuovocampo45 → guardian_email
-- guardian_type = 'padre'
-- 
-- CAMPI DA CONSOLIDARE IN NOTES:
-- - nuovocampo25 (Anno corso)
-- - nuovocampo17 (Lingue cadetto)
-- - nuovocampo18 (Allergie cadetto)
-- - nuovocampo58 (Allergie genitore)
-- - nuovocampo63 (Data decadenza) - solo per decaduti
-- - nuovocampo62 (Motivo decadenza) - solo per decaduti
-- 
-- DATI MADRE (tutti in notes):
-- - nuovocampo46, nuovocampo47 (Nome madre)
-- - nuovocampo48, nuovocampo49 (Luogo nascita madre)
-- - nuovocampo50 (Data nascita madre)
-- - nuovocampo51 (CF madre)
-- - nuovocampo52 (Indirizzo madre)
-- - nuovocampo53, nuovocampo54, nuovocampo55 (CAP, Città, Provincia madre)
-- - nuovocampo59, nuovocampo60 (Tel madre)
-- - nuovocampo56 (Email madre)
-- 
-- DATI PADRE AGGIUNTIVI (in notes):
-- - nuovocampo35, nuovocampo36 (Luogo nascita padre)
-- - nuovocampo37 (Data nascita padre)
-- - nuovocampo39 (Indirizzo padre)
-- - nuovocampo40, nuovocampo41, nuovocampo42 (CAP, Città, Provincia padre)
-- - nuovocampo43 (Tel fisso padre)
-- 
-- CONTATTI CADETTO (inserire solo se presenti):
-- - nuovocampo14 → junior_member_contacts (cellulare)
-- - nuovocampo15 → junior_member_contacts (email)
-- 
-- NOTA: I contatti dei genitori (nuovocampo44, 43, 45, 60, 56) sono descritti
-- nella sezione notes perché la tabella junior_member_contacts è solo
-- per il cadetto, non per i genitori.
-- 
-- INDIRIZZI CADETTO:
-- - Residenza: nuovocampo9, nuovocampo10, nuovocampo11, nuovocampo12
-- - Padre (se diverso): nuovocampo39, nuovocampo40, nuovocampo41, nuovocampo42
-- - Madre (se diverso): nuovocampo52, nuovocampo53, nuovocampo54, nuovocampo55
-- 
-- LIMITAZIONI SCHEMA ATTUALE:
-- - junior_member_contacts non ha campi is_primary e notes
-- - junior_member_addresses non ha campi is_primary e notes
-- - Non c'è modo di distinguere contatti/indirizzi genitori se non in notes
-- - I dati dei genitori diversi dal tutore principale vanno in notes
-- 
-- =====================================================
