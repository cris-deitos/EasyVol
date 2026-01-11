-- Migration 010: Add rate limiting table
-- This table is used to track login attempts and prevent brute force attacks

CREATE TABLE IF NOT EXISTS `rate_limit_attempts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL COMMENT 'IP address or username being rate limited',
  `action` varchar(50) NOT NULL COMMENT 'Action being attempted (login, reset_password, etc.)',
  `success` tinyint(1) DEFAULT 0 COMMENT 'Whether the attempt was successful',
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_identifier_action` (`identifier`, `action`),
  KEY `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
