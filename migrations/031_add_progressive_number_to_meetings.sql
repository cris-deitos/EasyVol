-- Migration: Add progressive_number column to meetings table
-- Date: 2026-02-22
-- Description: Adds a progressive_number column to the meetings table to track
--              the sequential number of each Meeting/Assembly per meeting type.

ALTER TABLE `meetings`
  ADD COLUMN `progressive_number` int(11) DEFAULT NULL COMMENT 'Numero progressivo della riunione/assemblea per tipo' AFTER `meeting_type`,
  ADD UNIQUE KEY `uq_meeting_type_progressive` (`meeting_type`, `progressive_number`);
