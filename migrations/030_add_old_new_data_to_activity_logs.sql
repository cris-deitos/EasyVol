-- Migration: Add old_data and new_data columns to activity_logs for detailed change tracking
-- Date: 2026-02-22
-- Description: Adds old_data (before change) and new_data (after change) JSON columns
--              to activity_logs table so that every create/update/delete operation
--              can record the full record data before and after the change.

ALTER TABLE `activity_logs`
  ADD COLUMN `old_data` longtext COMMENT 'JSON: record data before the change (for update/delete)',
  ADD COLUMN `new_data` longtext COMMENT 'JSON: record data after the change (for create/update)';
