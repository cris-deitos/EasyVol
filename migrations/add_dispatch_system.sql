-- Migration: Add Dispatch System Tables
-- Description: Adds tables for the dispatch system including TalkGroups, transmissions,
--              positions, events, audio recordings, text messages, and emergency codes
-- Date: 2026-01-03

-- TalkGroups Management
CREATE TABLE IF NOT EXISTS `dispatch_talkgroups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `talkgroup_id` varchar(50) NOT NULL COMMENT 'TalkGroup ID from radio network',
  `name` varchar(255) NOT NULL COMMENT 'TalkGroup name/description',
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `talkgroup_id` (`talkgroup_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Real-time Transmissions Tracking
CREATE TABLE IF NOT EXISTS `dispatch_transmissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot` tinyint(1) NOT NULL COMMENT 'Slot number (1 or 2)',
  `radio_id` int(11) DEFAULT NULL COMMENT 'Foreign key to radio_directory',
  `radio_dmr_id` varchar(50) NOT NULL COMMENT 'DMR ID from radio network',
  `talkgroup_id` varchar(50) NOT NULL COMMENT 'TalkGroup ID being transmitted on',
  `transmission_start` datetime NOT NULL,
  `transmission_end` datetime DEFAULT NULL,
  `duration_seconds` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Whether transmission is currently active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `slot` (`slot`),
  KEY `radio_id` (`radio_id`),
  KEY `radio_dmr_id` (`radio_dmr_id`),
  KEY `talkgroup_id` (`talkgroup_id`),
  KEY `is_active` (`is_active`),
  KEY `transmission_start` (`transmission_start`),
  FOREIGN KEY (`radio_id`) REFERENCES `radio_directory`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GPS Positions Tracking
CREATE TABLE IF NOT EXISTS `dispatch_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `radio_id` int(11) DEFAULT NULL COMMENT 'Foreign key to radio_directory',
  `radio_dmr_id` varchar(50) NOT NULL COMMENT 'DMR ID from radio network',
  `latitude` decimal(10, 8) NOT NULL,
  `longitude` decimal(11, 8) NOT NULL,
  `altitude` decimal(8, 2) DEFAULT NULL COMMENT 'Altitude in meters',
  `speed` decimal(6, 2) DEFAULT NULL COMMENT 'Speed in km/h',
  `heading` decimal(5, 2) DEFAULT NULL COMMENT 'Heading in degrees',
  `accuracy` decimal(6, 2) DEFAULT NULL COMMENT 'GPS accuracy in meters',
  `timestamp` datetime NOT NULL COMMENT 'Position timestamp from GPS',
  `received_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When position was received by server',
  PRIMARY KEY (`id`),
  KEY `radio_id` (`radio_id`),
  KEY `radio_dmr_id` (`radio_dmr_id`),
  KEY `timestamp` (`timestamp`),
  KEY `received_at` (`received_at`),
  FOREIGN KEY (`radio_id`) REFERENCES `radio_directory`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Network Events Log
CREATE TABLE IF NOT EXISTS `dispatch_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot` tinyint(1) DEFAULT NULL COMMENT 'Slot number (1 or 2), NULL for system events',
  `event_type` varchar(50) NOT NULL COMMENT 'Event type: transmission, registration, deregistration, position, etc.',
  `radio_id` int(11) DEFAULT NULL COMMENT 'Foreign key to radio_directory',
  `radio_dmr_id` varchar(50) DEFAULT NULL COMMENT 'DMR ID from radio network',
  `talkgroup_id` varchar(50) DEFAULT NULL COMMENT 'TalkGroup ID if applicable',
  `event_data` text COMMENT 'JSON data with additional event information',
  `event_timestamp` datetime NOT NULL COMMENT 'When the event occurred',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `slot` (`slot`),
  KEY `event_type` (`event_type`),
  KEY `radio_id` (`radio_id`),
  KEY `radio_dmr_id` (`radio_dmr_id`),
  KEY `talkgroup_id` (`talkgroup_id`),
  KEY `event_timestamp` (`event_timestamp`),
  FOREIGN KEY (`radio_id`) REFERENCES `radio_directory`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audio Recordings
CREATE TABLE IF NOT EXISTS `dispatch_audio_recordings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot` tinyint(1) NOT NULL COMMENT 'Slot number (1 or 2)',
  `radio_id` int(11) DEFAULT NULL COMMENT 'Foreign key to radio_directory',
  `radio_dmr_id` varchar(50) NOT NULL COMMENT 'DMR ID from radio network',
  `talkgroup_id` varchar(50) NOT NULL COMMENT 'TalkGroup ID',
  `file_path` varchar(500) NOT NULL COMMENT 'Path to audio file',
  `duration_seconds` int(11) DEFAULT NULL,
  `file_size_bytes` int(11) DEFAULT NULL,
  `recorded_at` datetime NOT NULL COMMENT 'When the audio was recorded',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `slot` (`slot`),
  KEY `radio_id` (`radio_id`),
  KEY `radio_dmr_id` (`radio_dmr_id`),
  KEY `talkgroup_id` (`talkgroup_id`),
  KEY `recorded_at` (`recorded_at`),
  FOREIGN KEY (`radio_id`) REFERENCES `radio_directory`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Text Messages
CREATE TABLE IF NOT EXISTS `dispatch_text_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot` tinyint(1) NOT NULL COMMENT 'Slot number (1 or 2)',
  `from_radio_id` int(11) DEFAULT NULL COMMENT 'Sender radio (foreign key)',
  `from_radio_dmr_id` varchar(50) NOT NULL COMMENT 'Sender DMR ID',
  `to_radio_dmr_id` varchar(50) DEFAULT NULL COMMENT 'Recipient radio DMR ID (NULL for broadcast)',
  `to_talkgroup_id` varchar(50) DEFAULT NULL COMMENT 'Recipient TalkGroup ID (NULL for direct)',
  `message_text` text NOT NULL,
  `message_timestamp` datetime NOT NULL COMMENT 'When the message was sent',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `slot` (`slot`),
  KEY `from_radio_id` (`from_radio_id`),
  KEY `from_radio_dmr_id` (`from_radio_dmr_id`),
  KEY `to_radio_dmr_id` (`to_radio_dmr_id`),
  KEY `to_talkgroup_id` (`to_talkgroup_id`),
  KEY `message_timestamp` (`message_timestamp`),
  FOREIGN KEY (`from_radio_id`) REFERENCES `radio_directory`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Emergency Codes
CREATE TABLE IF NOT EXISTS `dispatch_emergency_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `radio_id` int(11) DEFAULT NULL COMMENT 'Foreign key to radio_directory',
  `radio_dmr_id` varchar(50) NOT NULL COMMENT 'DMR ID from radio network',
  `latitude` decimal(10, 8) DEFAULT NULL,
  `longitude` decimal(11, 8) DEFAULT NULL,
  `emergency_timestamp` datetime NOT NULL COMMENT 'When the emergency code was received',
  `acknowledged_by` int(11) DEFAULT NULL COMMENT 'User who acknowledged the emergency',
  `acknowledged_at` datetime DEFAULT NULL,
  `notes` text COMMENT 'Notes about the emergency',
  `status` enum('active', 'acknowledged', 'resolved') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `radio_id` (`radio_id`),
  KEY `radio_dmr_id` (`radio_dmr_id`),
  KEY `emergency_timestamp` (`emergency_timestamp`),
  KEY `status` (`status`),
  KEY `acknowledged_by` (`acknowledged_by`),
  FOREIGN KEY (`radio_id`) REFERENCES `radio_directory`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`acknowledged_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raspberry Pi Configuration
CREATE TABLE IF NOT EXISTS `dispatch_raspberry_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default Raspberry Pi configuration
INSERT INTO `dispatch_raspberry_config` (`config_key`, `config_value`, `description`) VALUES
('api_enabled', '1', 'Enable/disable API endpoints for Raspberry Pi'),
('api_key', '', 'API key for authentication (leave empty to generate)'),
('audio_storage_path', 'uploads/dispatch/audio/', 'Path to store audio recordings'),
('max_audio_file_size', '10485760', 'Maximum audio file size in bytes (10MB default)'),
('position_update_interval', '60', 'Expected position update interval in seconds'),
('position_inactive_threshold', '1800', 'Seconds after which a radio is considered inactive (30 minutes)');

-- Add DMR ID field to radio_directory if not exists
ALTER TABLE `radio_directory` 
ADD COLUMN IF NOT EXISTS `dmr_id` varchar(50) DEFAULT NULL COMMENT 'DMR Radio ID' AFTER `identifier`,
ADD UNIQUE KEY IF NOT EXISTS `dmr_id` (`dmr_id`);
