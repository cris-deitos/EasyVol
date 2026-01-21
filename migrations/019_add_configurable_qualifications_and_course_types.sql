-- Migration 019: Add Configurable Qualifications and Course Types
-- Adds tables to allow configuration of member qualifications and training course types from Settings

-- Create table for member qualification types
CREATE TABLE IF NOT EXISTS `member_qualification_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Nome della qualifica',
  `description` text DEFAULT NULL COMMENT 'Descrizione opzionale della qualifica',
  `sort_order` int(11) DEFAULT 0 COMMENT 'Ordine di visualizzazione',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Se la qualifica è attiva e utilizzabile',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for training course types
CREATE TABLE IF NOT EXISTS `training_course_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL COMMENT 'Codice del corso (es. A1, A2-01)',
  `name` varchar(255) NOT NULL COMMENT 'Nome completo del corso',
  `category` varchar(100) DEFAULT NULL COMMENT 'Categoria del corso (es. Corsi Base, Corsi A2)',
  `description` text DEFAULT NULL COMMENT 'Descrizione opzionale del corso',
  `sort_order` int(11) DEFAULT 0 COMMENT 'Ordine di visualizzazione',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Se il tipo di corso è attivo e utilizzabile',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_category` (`category`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default member qualification types (from original member_role_edit.php dropdown)
INSERT INTO `member_qualification_types` (`name`, `description`, `sort_order`, `is_active`) VALUES
('OPERATORE GENERICO', 'Operatore generico', 10, 1),
('PRESIDENTE', 'Presidente', 20, 1),
('VICE PRESIDENTE', 'Vice presidente', 30, 1),
('CAPOSQUADRA', 'Capo squadra', 40, 1),
('RESPONSABILE RAPPORTI ISTITUZIONALI E STAMPA', 'Responsabile rapporti istituzionali e stampa', 50, 1),
('RESPONSABILE NUCLEO TLC RADIO', 'Responsabile nucleo TLC radio', 60, 1),
('RESPONSABILE NUCLEO GIS/GPS', 'Responsabile nucleo GIS/GPS', 70, 1),
('RESPONSABILE NUCLEO SEGRETERIA OPERATIVA', 'Responsabile nucleo segreteria operativa', 80, 1),
('RESPONSABILE NUCLEO DRONE', 'Responsabile nucleo drone', 90, 1),
('RESPONSABILE NUCLEO RICERCA E SOCCORSO', 'Responsabile nucleo ricerca e soccorso', 100, 1),
('RESPONSABILE NUCLEO NAUTICO', 'Responsabile nucleo nautico', 110, 1),
('RESPONSABILE NUCLEO SOMMOZZATORI', 'Responsabile nucleo sommozzatori', 120, 1),
('RESPONSABILE NUCLEO IDROGEOLOGICO', 'Responsabile nucleo idrogeologico', 130, 1),
('RESPONSABILE NUCLEO LOGISTICO', 'Responsabile nucleo logistico', 140, 1),
('RESPONSABILE NUCLEO CINOFILI', 'Responsabile nucleo cinofili', 150, 1),
('RESPONSABILE NUCLEO A CAVALLO', 'Responsabile nucleo a cavallo', 160, 1),
('RESPONSABILE NUCLEO CUCINA E MENSA', 'Responsabile nucleo cucina e mensa', 170, 1),
('OPERATORE SEGRETERIA', 'Operatore segreteria', 180, 1),
('OPERATORE TLC RADIO', 'Operatore TLC radio', 190, 1),
('OPERATORE GIS/GPS', 'Operatore GIS/GPS', 200, 1),
('OPERATORE DRONE', 'Operatore drone', 210, 1),
('OPERATORE CUCINA', 'Operatore cucina', 220, 1),
('OPERATORE LOGISTICO', 'Operatore logistico', 230, 1),
('OPERATORE IDROGEOLOGICO', 'Operatore idrogeologico', 240, 1),
('OPERATORE MENSA', 'Operatore mensa', 250, 1),
('OPERATORE SOMMOZZATORE', 'Operatore sommozzatore', 260, 1),
('OPERATORE NAUTICO', 'Operatore nautico', 270, 1),
('OPERATORE CINOFILO', 'Operatore cinofilo', 280, 1),
('OPERATORE A CAVALLO', 'Operatore a cavallo', 290, 1),
('OPERATORE FOTO REPORTER', 'Operatore foto reporter', 300, 1),
('AUTISTA A', 'Autista categoria A', 310, 1),
('AUTISTA B', 'Autista categoria B', 320, 1),
('AUTISTA C', 'Autista categoria C', 330, 1),
('AUTISTA D', 'Autista categoria D', 340, 1),
('AUTISTA E', 'Autista categoria E', 350, 1),
('PILOTA NATANTE', 'Pilota natante', 360, 1),
('NON OPERATIVO', 'Non operativo', 370, 1);

-- Insert default training course types from TrainingCourseTypes utility class
INSERT INTO `training_course_types` (`code`, `name`, `category`, `sort_order`, `is_active`) VALUES
-- Corsi Base
('A0', 'A0 Corso informativo rivolto alla cittadinanza', 'Corsi Base', 10, 1),
('A1', 'A1 Corso base per volontari operativi di Protezione Civile', 'Corsi Base', 20, 1),

-- Corsi A2 - Specializzazione
('A2-01', 'A2-01 ATTIVITÀ LOGISTICO GESTIONALI', 'Corsi A2 - Specializzazione', 100, 1),
('A2-02', 'A2-02 OPERATORE SEGRETERIA', 'Corsi A2 - Specializzazione', 110, 1),
('A2-03', 'A2-03 CUCINA IN EMERGENZA', 'Corsi A2 - Specializzazione', 120, 1),
('A2-04', 'A2-04 RADIOCOMUNICAZIONI E PROCESSO COMUNICATIVO IN PROTEZIONE CIVILE', 'Corsi A2 - Specializzazione', 130, 1),
('A2-05', 'A2-05 IDROGEOLOGICO: ALLUVIONE', 'Corsi A2 - Specializzazione', 140, 1),
('A2-06', 'A2-06 IDROGEOLOGICO: FRANE', 'Corsi A2 - Specializzazione', 150, 1),
('A2-07', 'A2-07 IDROGEOLOGICO: SISTEMI DI ALTO POMPAGGIO', 'Corsi A2 - Specializzazione', 160, 1),
('A2-08', 'A2-08 USO MOTOSEGA E DECESPUGLIATORE', 'Corsi A2 - Specializzazione', 170, 1),
('A2-09', 'A2-09 SICUREZZA IN PROTEZIONE CIVILE: D. Lgs. 81/08', 'Corsi A2 - Specializzazione', 180, 1),
('A2-10', 'A2-10 TOPOGRAFIA E GPS', 'Corsi A2 - Specializzazione', 190, 1),
('A2-11', 'A2-11 RICERCA DISPERSI', 'Corsi A2 - Specializzazione', 200, 1),
('A2-12', 'A2-12 OPERATORE NATANTE IN EMERGENZA DI PROTEZIONE CIVILE', 'Corsi A2 - Specializzazione', 210, 1),
('A2-13', 'A2-13 INTERVENTI ZOOTECNICI IN EMERGENZA DI PROTEZIONE CIVILE', 'Corsi A2 - Specializzazione', 220, 1),
('A2-14', 'A2-14 PIANO DI PROTEZIONE CIVILE: DIVULGAZIONE E INFORMAZIONE', 'Corsi A2 - Specializzazione', 230, 1),
('A2-15', 'A2-15 QUADERNI DI PRESIDIO', 'Corsi A2 - Specializzazione', 240, 1),
('A2-16', 'A2-16 EVENTI A RILEVANTE IMPATTO LOCALE', 'Corsi A2 - Specializzazione', 250, 1),
('A2-17', 'A2-17 SCUOLA I° CICLO DELL\'ISTRUZIONE', 'Corsi A2 - Specializzazione', 260, 1),
('A2-18', 'A2-18 SCUOLA SECONDARIA SUPERIORE', 'Corsi A2 - Specializzazione', 270, 1),

-- Corsi A3 - Coordinamento
('A3-01', 'A3-01 CAPO SQUADRA', 'Corsi A3 - Coordinamento', 300, 1),
('A3-02', 'A3-02 COORDINATORE TERRITORIALE DEL VOLONTARIATO', 'Corsi A3 - Coordinamento', 310, 1),
('A3-03', 'A3-03 VICE COORDINATORE DI SEGRETERIA E SUPPORTO ALLA SALA OPERATIVA', 'Corsi A3 - Coordinamento', 320, 1),
('A3-04', 'A3-04 PRESIDENTE ASSOCIAZIONE e/o COORD. GR. COMUNALE/INTERCOM.', 'Corsi A3 - Coordinamento', 330, 1),
('A3-05', 'A3-05 COMPONENTI CCV (eletti)', 'Corsi A3 - Coordinamento', 340, 1),
('A3-06', 'A3-06 SUPPORTO ALLA PIANIFICAZIONE', 'Corsi A3 - Coordinamento', 350, 1),

-- Corsi A4 - Alta Specializzazione
('A4-01', 'A4-01 SOMMOZZATORI di Protezione civile: Operatore tecnico assistenza sommozzatori PC 1° livello', 'Corsi A4 - Alta Specializzazione', 400, 1),
('A4-02', 'A4-02 SOMMOZZATORI di protezione civile Alta specializzazione', 'Corsi A4 - Alta Specializzazione', 410, 1),
('A4-03', 'A4-03 ATTIVITÀ OPERATORI CINOFILI', 'Corsi A4 - Alta Specializzazione', 420, 1),
('A4-04', 'A4-04 ATTIVITÀ OPERATORI EQUESTRI', 'Corsi A4 - Alta Specializzazione', 430, 1),
('A4-05', 'A4-05 CATTURA IMENOTTERI E BONIFICA', 'Corsi A4 - Alta Specializzazione', 440, 1),
('A4-06', 'A4-06 T.S.A. - Tecniche Speleo Alpinistiche', 'Corsi A4 - Alta Specializzazione', 450, 1),
('A4-07', 'A4-07 S.R.T. - Swiftwater Rescue Technician', 'Corsi A4 - Alta Specializzazione', 460, 1),
('A4-08', 'A4-08 PATENTE PER OPERATORE RADIO AMATORIALE', 'Corsi A4 - Alta Specializzazione', 470, 1),
('A4-09', 'A4-09 OPERATORE GRU SU AUTO-CARRO', 'Corsi A4 - Alta Specializzazione', 480, 1),
('A4-10', 'A4-10 OPERATORE MULETTO', 'Corsi A4 - Alta Specializzazione', 490, 1),
('A4-11', 'A4-11 OPERATORE PER PIATTAFORME DI LAVORO ELEVABILI (PLE)', 'Corsi A4 - Alta Specializzazione', 500, 1),
('A4-12', 'A4-12 OPERATORE ESCAVATORE', 'Corsi A4 - Alta Specializzazione', 510, 1),
('A4-13', 'A4-13 OPERATORE TRATTORE', 'Corsi A4 - Alta Specializzazione', 520, 1),
('A4-14', 'A4-14 OPERATORE DRONI', 'Corsi A4 - Alta Specializzazione', 530, 1),
('A4-15', 'A4-15 HACCP', 'Corsi A4 - Alta Specializzazione', 540, 1),

-- Corsi A5 - AIB
('A5-01', 'A5-01 A.I.B. di 1° LIVELLO', 'Corsi A5 - AIB', 600, 1),
('A5-02', 'A5-02 A.I.B. AGGIORNAMENTI', 'Corsi A5 - AIB', 610, 1),
('A5-03', 'A5-03 CAPOSQUADRA A.I.B.', 'Corsi A5 - AIB', 620, 1),
('A5-04', 'A5-04 D.O.S. (in gestione direttamente a RL)', 'Corsi A5 - AIB', 630, 1),

-- Altro
('Altro', 'Altro da specificare', 'Altro', 999, 1);
