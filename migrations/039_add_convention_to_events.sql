-- Migration 039: Add Convention field to Events
-- Links events to conventions via convention_id

ALTER TABLE `events` ADD COLUMN `convention_id` int(11) DEFAULT NULL AFTER `legal_benefits_recognized`;
ALTER TABLE `events` ADD KEY `idx_events_convention_id` (`convention_id`);
ALTER TABLE `events` ADD CONSTRAINT `fk_events_convention_id` FOREIGN KEY (`convention_id`) REFERENCES `conventions`(`id`) ON DELETE SET NULL;
