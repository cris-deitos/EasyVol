-- Migration: Add event_participants and event_vehicles tables
-- Created: 2025-12-13
-- Description: Creates the missing tables for tracking event participants and vehicles
--              These tables are needed to associate members and vehicles with events

-- Create event_participants table
CREATE TABLE IF NOT EXISTS `event_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `role` varchar(100) DEFAULT NULL COMMENT 'Role of the member in the event',
  `hours` decimal(5,2) DEFAULT 0.00 COMMENT 'Hours of service',
  `notes` text COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks member participation in events';

-- Create event_vehicles table
CREATE TABLE IF NOT EXISTS `event_vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `driver_name` varchar(255) DEFAULT NULL COMMENT 'Name of the driver',
  `hours` decimal(5,2) DEFAULT 0.00 COMMENT 'Hours of vehicle usage',
  `km_traveled` int(11) DEFAULT 0 COMMENT 'Kilometers traveled',
  `km_start` int(11) DEFAULT NULL COMMENT 'Starting odometer reading',
  `km_end` int(11) DEFAULT NULL COMMENT 'Ending odometer reading',
  `notes` text COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `vehicle_id` (`vehicle_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks vehicle usage in events';
