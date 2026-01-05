-- =============================================
-- Migration: Add Quote Non Versate Tab Feature
-- Date: 2026-01-05
-- Description: This migration documents the addition of the "Quote Non Versate" 
--              feature to the fee payments management page.
--              No database schema changes are required as the feature uses
--              existing tables and relationships.
-- =============================================

-- NOTE: This is a documentation-only migration.
-- The feature uses existing tables:
--   - members (with member_status field)
--   - junior_members (with member_status field)
--   - member_fees (with year field)
--   - junior_member_fees (with year field)

-- The new feature adds:
-- 1. A new tab "Quote Non Versate" in the fee_payments.php page
-- 2. Displays active members (member_status = 'attivo') without fee payment for selected year
-- 3. Supports both adult members and junior members
-- 4. Page title changed from "Gestione Richieste Pagamento Quote" to "Gestione Pagamento Quote"

-- Implementation details:
-- - New method in FeePaymentController: getUnpaidMembers($year, $page, $perPage)
-- - Queries members and junior_members with NOT EXISTS subquery on respective fees tables
-- - Results are paginated and displayed in a user-friendly table format

-- No SQL statements to execute - all required schema already exists.

SELECT 'Migration 20260105_add_unpaid_fees_tab.sql completed - Feature uses existing schema' as message;
