-- Migration: Add member_application_guardians table
-- Date: 2026-01-07
-- Purpose: Add missing table for storing guardian data in membership applications
-- This table stores guardian information for junior member applications before approval

CREATE TABLE IF NOT EXISTS `member_application_guardians` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `guardian_type` enum('padre', 'madre', 'tutore') NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `birth_place` varchar(255) DEFAULT NULL,
  `tax_code` varchar(50),
  `phone` varchar(50),
  `email` varchar(255),
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  CONSTRAINT `fk_member_application_guardians_application` FOREIGN KEY (`application_id`) REFERENCES `member_applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
