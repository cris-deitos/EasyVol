-- =====================================================
-- IMPORT SOCI ADULTI DA VECCHIO GESTIONALE
-- Totale record: 175
-- Data: 2025-12-07
-- 
-- NOTA: Questo file contiene la struttura per l'importazione
-- dei 175 soci adulti dal file soci.csv
-- 
-- Per generare il file completo:
-- 1. Fornire il file soci.csv
-- 2. Utilizzare uno script di conversione
-- 3. Sostituire la sezione ESEMPIO con tutti i 175 record
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
START TRANSACTION;

-- =====================================================
-- SEZIONE DATI - DA POPOLARE CON TUTTI I 175 SOCI
-- =====================================================

-- ESEMPIO 1: SOCIO FONDATORE OPERATIVO
-- Dati di esempio basati sulla struttura del CSV
INSERT INTO `members` (
    `registration_number`,
    `member_type`,
    `member_status`,
    `volunteer_status`,
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
    '001',                              -- matr
    'fondatore',                        -- tipo_socio: SOCIO FONDATORE → fondatore
    'attivo',                          -- stato: OPERATIVO → attivo
    'operativo',                       -- stato: OPERATIVO → operativo
    'ROSSI',                           -- cognome
    'MARIO',                           -- nome
    '1980-01-15',                      -- data_nascita
    'ROMA',                            -- luogo_nascita
    'RSSMRA80A15H501X',               -- codicefiscale
    '2010-03-20',                      -- anno_iscrizione
    -- notes: consolidamento campi extra (non presenti nello schema DB corrente)
    'Provincia nascita: RM
Sesso: M
Gruppo sanguigno: A+
Qualifica: Volontario
Disponibilità: Territoriale
Lingue: Inglese, Francese
Allergie: Nessuna
Patente: B
Titolo studio: Laurea
Lavoro: Impiegato presso ABC S.r.l.',
    '2010-03-20 10:00:00',            -- created
    '2024-12-01 15:30:00'             -- last_upd
);
SET @member_id = LAST_INSERT_ID();

-- Contatti socio 001
INSERT INTO `member_contacts` (`member_id`, `contact_type`, `value`) VALUES
    (@member_id, 'cellulare', '3331234567'),        -- cellulare (primary)
    (@member_id, 'telefono_fisso', '0612345678'),   -- telefono_fisso
    (@member_id, 'email', 'mario.rossi@example.com'); -- e_mail (primary)

-- Indirizzi socio 001
INSERT INTO `member_addresses` (
    `member_id`,
    `address_type`,
    `street`,
    `number`,
    `city`,
    `province`,
    `cap`
) VALUES
    -- Residenza
    (@member_id, 'residenza', 'Via Roma', '10', 'ROMA', 'RM', '00100'),
    -- Indirizzo lavoro (usando domicilio come workaround)
    (@member_id, 'domicilio', 'Via del Lavoro', '5', 'ROMA', 'RM', '00199');

-- =====================================================

-- ESEMPIO 2: SOCIO ORDINARIO NON OPERATIVO
INSERT INTO `members` (
    `registration_number`,
    `member_type`,
    `member_status`,
    `volunteer_status`,
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
    '002',
    'ordinario',                       -- tipo_socio: SOCIO ORDINARIO → ordinario
    'attivo',                          -- stato: NON OPERATIVO → attivo
    'non_operativo',                   -- stato: NON OPERATIVO → non_operativo
    'BIANCHI',
    'GIULIA',
    '1985-06-22',
    'MILANO',
    'BNCGLI85H62F205Y',
    '2015-05-10',
    'Provincia nascita: MI
Sesso: F
Gruppo sanguigno: B+
Qualifica: Volontario
Disponibilità: Locale
Patente: B
Titolo studio: Diploma',
    '2015-05-10 09:00:00',
    '2024-11-15 12:00:00'
);
SET @member_id = LAST_INSERT_ID();

-- Contatti socio 002
INSERT INTO `member_contacts` (`member_id`, `contact_type`, `value`) VALUES
    (@member_id, 'cellulare', '3397654321'),
    (@member_id, 'email', 'giulia.bianchi@example.com');

-- Indirizzo socio 002
INSERT INTO `member_addresses` (
    `member_id`,
    `address_type`,
    `street`,
    `number`,
    `city`,
    `province`,
    `cap`
) VALUES
    (@member_id, 'residenza', 'Corso Italia', '25', 'MILANO', 'MI', '20100');

-- =====================================================

-- ESEMPIO 3: SOCIO DIMESSO
INSERT INTO `members` (
    `registration_number`,
    `member_type`,
    `member_status`,
    `volunteer_status`,
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
    '003',
    'ordinario',                       -- tipo originale mantenuto
    'dimesso',                        -- *DIMESSO* → dimesso
    'non_operativo',
    'VERDI',
    'FRANCO',
    '1975-03-10',
    'NAPOLI',
    'VRDFNC75C10F839W',
    '2012-01-15',
    'Provincia nascita: NA
Sesso: M
Gruppo sanguigno: 0+
Qualifica: Volontario
Data dimissione: 2023-06-30
Motivo dimissione: Trasferimento',  -- nuovocampo + note
    '2012-01-15 10:00:00',
    '2023-06-30 17:00:00'
);
SET @member_id = LAST_INSERT_ID();

-- Contatti socio 003
INSERT INTO `member_contacts` (`member_id`, `contact_type`, `value`) VALUES
    (@member_id, 'cellulare', '3381112233'),
    (@member_id, 'email', 'franco.verdi@example.com');

-- Indirizzo socio 003
INSERT INTO `member_addresses` (
    `member_id`,
    `address_type`,
    `street`,
    `number`,
    `city`,
    `province`,
    `cap`
) VALUES
    (@member_id, 'residenza', 'Via Caracciolo', '88', 'NAPOLI', 'NA', '80100');

-- =====================================================

-- ESEMPIO 4: SOCIO DECADUTO
INSERT INTO `members` (
    `registration_number`,
    `member_type`,
    `member_status`,
    `volunteer_status`,
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
    '004',
    'fondatore',                       -- tipo originale mantenuto
    'decaduto',                       -- *DECADUTO* → decaduto
    'non_operativo',
    'NERI',
    'ANNA',
    '1978-11-05',
    'TORINO',
    'NRANNA78S45L219Z',
    '2011-09-01',
    'Provincia nascita: TO
Sesso: F
Gruppo sanguigno: AB+
Qualifica: Volontario
Data decadenza: 2022-12-31
Motivo decadenza: Mancato pagamento quote',  -- nuovocampo + note
    '2011-09-01 08:30:00',
    '2022-12-31 23:59:59'
);
SET @member_id = LAST_INSERT_ID();

-- Contatti socio 004
INSERT INTO `member_contacts` (`member_id`, `contact_type`, `value`) VALUES
    (@member_id, 'cellulare', '3355667788'),
    (@member_id, 'email', 'anna.neri@example.com');

-- Indirizzo socio 004
INSERT INTO `member_addresses` (
    `member_id`,
    `address_type`,
    `street`,
    `number`,
    `city`,
    `province`,
    `cap`
) VALUES
    (@member_id, 'residenza', 'Piazza Castello', '1', 'TORINO', 'TO', '10100');

-- =====================================================
-- ESEMPIO 5: SOCIO CON DOPPIO INDIRIZZO E EMAIL LAVORO
INSERT INTO `members` (
    `registration_number`,
    `member_type`,
    `member_status`,
    `volunteer_status`,
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
    '005',
    'ordinario',
    'attivo',
    'operativo',
    'D\'AQUINO',                      -- escape di apostrofo
    'LUCA',
    '1982-07-18',
    'FIRENZE',
    'DQNLCU82L18D612V',
    '2013-04-12',
    'Provincia nascita: FI
Sesso: M
Gruppo sanguigno: A+
Qualifica: Autista
Disponibilità: Nazionale
Lingue: Inglese, Spagnolo, Tedesco
Patente: B, C, D
Titolo studio: Diploma
Lavoro: Autista presso XYZ Trasporti S.p.A.',
    '2013-04-12 11:00:00',
    '2024-12-05 09:30:00'
);
SET @member_id = LAST_INSERT_ID();

-- Contatti socio 005 (include telefono fisso e email lavoro)
INSERT INTO `member_contacts` (`member_id`, `contact_type`, `value`) VALUES
    (@member_id, 'cellulare', '3402223344'),
    (@member_id, 'telefono_fisso', '0552223344'),
    (@member_id, 'email', 'luca.daquino@example.com'),
    (@member_id, 'email', 'l.daquino@xyztraporti.it'); -- email lavoro (non c'è campo notes per distinguere)

-- Indirizzi socio 005 (residenza + lavoro)
INSERT INTO `member_addresses` (
    `member_id`,
    `address_type`,
    `street`,
    `number`,
    `city`,
    `province`,
    `cap`
) VALUES
    (@member_id, 'residenza', 'Via dei Mille', '42', 'FIRENZE', 'FI', '50100'),
    (@member_id, 'domicilio', 'Via Industriale', '15', 'PRATO', 'PO', '59100'); -- lavoro

-- =====================================================
-- QUI AGGIUNGERE GLI ALTRI 170 SOCI
-- Seguire lo stesso pattern degli esempi sopra
-- =====================================================

-- [INSERIRE RECORD 006-175]

-- =====================================================
-- FINE INSERIMENTI
-- =====================================================

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- STATISTICHE FINALI
-- =====================================================

SELECT 'IMPORT SOCI ADULTI COMPLETATO' AS Stato,
    COUNT(*) AS Totale,
    SUM(CASE WHEN member_status='attivo' THEN 1 ELSE 0 END) AS Attivi,
    SUM(CASE WHEN member_status='dimesso' THEN 1 ELSE 0 END) AS Dimessi,
    SUM(CASE WHEN member_status='decaduto' THEN 1 ELSE 0 END) AS Decaduti,
    SUM(CASE WHEN volunteer_status='operativo' THEN 1 ELSE 0 END) AS Operativi,
    SUM(CASE WHEN volunteer_status='non_operativo' THEN 1 ELSE 0 END) AS NonOperativi,
    SUM(CASE WHEN member_type='fondatore' THEN 1 ELSE 0 END) AS Fondatori,
    SUM(CASE WHEN member_type='ordinario' THEN 1 ELSE 0 END) AS Ordinari
FROM `members`;

-- Verifica contatti
SELECT 'CONTATTI IMPORTATI' AS Tipo, COUNT(*) AS Totale
FROM `member_contacts`;

-- Verifica indirizzi
SELECT 'INDIRIZZI IMPORTATI' AS Tipo, COUNT(*) AS Totale
FROM `member_addresses`;

-- =====================================================
-- NOTE SULL'UTILIZZO
-- =====================================================
-- 
-- COME COMPLETARE QUESTO FILE:
-- 1. Fornire il file soci.csv con i 175 record
-- 2. Per ogni record del CSV, creare un blocco come negli esempi
-- 3. Mappare i campi come descritto nella sezione header
-- 4. Gestire l'escape di caratteri speciali (es. D'AQUINO → D\'AQUINO)
-- 5. Inserire NULL per campi vuoti
-- 6. Consolidare campi extra nel campo notes
-- 
-- MAPPATURA CAMPI:
-- matr → registration_number
-- tipo_socio → member_type (SOCIO FONDATORE→fondatore, SOCIO ORDINARIO→ordinario)
-- stato → member_status + volunteer_status
-- cognome → last_name
-- nome → first_name
-- data_nascita → birth_date
-- luogo_nascita → birth_place
-- codicefiscale → tax_code
-- anno_iscrizione → registration_date
-- created → created_at
-- last_upd → updated_at
-- 
-- CAMPI DA CONSOLIDARE IN NOTES:
-- - prov_nascita (Provincia nascita)
-- - sesso (Sesso: MASCHIO→M, FEMMINA→F)
-- - grup_sang (Gruppo sanguigno)
-- - mansione (Qualifica)
-- - disponibilita_territoriale (Disponibilità)
-- - altre_lingue (Lingue)
-- - problemi_alimentari (Allergie)
-- - nuovocampo4 (Patente)
-- - titolo_di_studio (Titolo studio)
-- - tipologia_lavoro, ente_azienda (Lavoro)
-- - nuovocampo (Data dimissione) - per dimessi/decaduti
-- - note (Motivo dimissione) - per dimessi/decaduti
-- 
-- CONTATTI (inserire solo se presenti):
-- - cellulare → member_contacts (cellulare)
-- - telefono_fisso → member_contacts (telefono_fisso)
-- - e_mail → member_contacts (email)
-- - e_mail_lavoro → member_contacts (email)
-- 
-- INDIRIZZI (inserire solo se presenti):
-- - Residenza: ind_resid, cap_resid, comun_resid, provincia_residenza
-- - Lavoro: indirizzo_lavoro, cap_lavoro, comune_lav, prov_lavoro (usare domicilio)
-- 
-- =====================================================
