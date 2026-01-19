-- Migration 015: Fix Dashboard Views
-- Fixes issues with database views for advanced dashboard:
-- 1. Fix v_yoy_event_stats - correct status enum value from 'completato' to 'concluso'
-- 2. Fix v_intervention_geographic_stats - add province column (NULL since tables don't have it)
-- 3. Views are correctly defined as VIEWs (not tables)

-- Fix year-over-year event statistics view
-- Issue: was using 'completato' but events table uses 'concluso'
DROP VIEW IF EXISTS `v_yoy_event_stats`;

CREATE OR REPLACE VIEW `v_yoy_event_stats` AS
SELECT 
    YEAR(start_date) as year,
    MONTH(start_date) as month,
    event_type,
    COUNT(*) as event_count,
    COUNT(DISTINCT id) as unique_events,
    SUM(CASE WHEN status = 'concluso' THEN 1 ELSE 0 END) as completed_events,
    SUM(CASE WHEN status = 'in_corso' THEN 1 ELSE 0 END) as in_progress_events
FROM events
GROUP BY YEAR(start_date), MONTH(start_date), event_type;

-- Fix intervention geographic statistics view
-- Issue: DashboardController expects 'province' column but it doesn't exist
-- Solution: Add province as NULL since neither events nor interventions tables have a province field
DROP VIEW IF EXISTS `v_intervention_geographic_stats`;

CREATE OR REPLACE VIEW `v_intervention_geographic_stats` AS
SELECT 
    i.id as intervention_id,
    i.title,
    e.municipality,
    NULL as province,
    e.start_date,
    e.event_type,
    i.latitude,
    i.longitude,
    COUNT(DISTINCT im.member_id) as volunteer_count,
    SUM(im.hours_worked) as total_hours
FROM interventions i
LEFT JOIN events e ON i.event_id = e.id
LEFT JOIN intervention_members im ON i.id = im.intervention_id
WHERE i.latitude IS NOT NULL AND i.longitude IS NOT NULL
GROUP BY i.id, i.title, e.municipality, e.start_date, e.event_type, i.latitude, i.longitude;

-- v_yoy_member_stats view appears to be correct and doesn't need changes
-- Keeping it as is for reference
