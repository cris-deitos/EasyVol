-- Migration: Create import_logs table
-- Description: Stores detailed logs of CSV imports
-- Date: 2025-12-07

CREATE TABLE IF NOT EXISTS `import_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `import_type` enum('soci', 'cadetti', 'mezzi', 'attrezzature') NOT NULL COMMENT 'Tipo di import',
  `file_name` varchar(255) NOT NULL COMMENT 'Nome file CSV caricato',
  `file_encoding` varchar(50) DEFAULT 'UTF-8' COMMENT 'Encoding rilevato del file',
  `total_rows` int(11) DEFAULT 0 COMMENT 'Totale righe nel CSV',
  `imported_rows` int(11) DEFAULT 0 COMMENT 'Righe importate con successo',
  `skipped_rows` int(11) DEFAULT 0 COMMENT 'Righe saltate (duplicati o errori)',
  `error_rows` int(11) DEFAULT 0 COMMENT 'Righe con errori',
  `status` enum('in_progress', 'completed', 'failed', 'partial') DEFAULT 'in_progress' COMMENT 'Stato import',
  `error_message` text COMMENT 'Messaggio di errore generale',
  `import_details` longtext COMMENT 'Dettagli import in formato JSON',
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL,
  `created_by` int(11) COMMENT 'User ID che ha eseguito import',
  PRIMARY KEY (`id`),
  KEY `import_type` (`import_type`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  KEY `started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
