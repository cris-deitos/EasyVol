-- Migration: Add convention amounts table for yearly amounts
CREATE TABLE IF NOT EXISTS `convention_amounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `convention_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `convention_id` (`convention_id`),
  UNIQUE KEY `convention_year` (`convention_id`, `year`),
  FOREIGN KEY (`convention_id`) REFERENCES `conventions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
