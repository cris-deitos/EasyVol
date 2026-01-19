<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use Exception;

/**
 * Dashboard Controller
 * 
 * Gestisce dashboard avanzata con statistiche, KPI personalizzabili e grafici interattivi
 */
class DashboardController {
    private $db;
    private $config;
    private $userId;
    
    public function __construct(Database $db, $config, $userId = null) {
        $this->db = $db;
        $this->config = $config;
        $this->userId = $userId;
    }
    
    /**
     * Get KPI data for dashboard
     */
    public function getKPIData() {
        $kpis = [];
        
        // Active members KPI
        $kpis['active_members'] = $this->db->fetchOne(
            "SELECT COUNT(*) as value FROM members WHERE member_status = 'attivo'"
        ) ?: ['value' => 0];
        
        // Junior members KPI
        $kpis['junior_members'] = $this->db->fetchOne(
            "SELECT COUNT(*) as value FROM junior_members WHERE member_status = 'attivo'"
        ) ?: ['value' => 0];
        
        // Active events KPI
        $kpis['active_events'] = $this->db->fetchOne(
            "SELECT COUNT(*) as value FROM events WHERE status = 'in_corso' AND start_date >= CURDATE()"
        ) ?: ['value' => 0];
        
        // Operational vehicles KPI
        $kpis['operational_vehicles'] = $this->db->fetchOne(
            "SELECT COUNT(*) as value FROM vehicles WHERE status = 'operativo'"
        ) ?: ['value' => 0];
        
        // Pending applications KPI
        $kpis['pending_applications'] = $this->db->fetchOne(
            "SELECT COUNT(*) as value FROM member_applications WHERE status = 'pending'"
        ) ?: ['value' => 0];
        
        // Active training courses KPI
        $kpis['active_training'] = $this->db->fetchOne(
            "SELECT COUNT(*) as value FROM training_courses WHERE status = 'in_corso'"
        ) ?: ['value' => 0];
        
        // Upcoming deadlines KPI
        $kpis['upcoming_deadlines'] = $this->db->fetchOne(
            "SELECT COUNT(*) as value FROM scheduler_items 
            WHERE status != 'completato' AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
        ) ?: ['value' => 0];
        
        // Low stock items KPI
        $kpis['low_stock_items'] = $this->db->fetchOne(
            "SELECT COUNT(*) as value FROM warehouse_items WHERE quantity <= minimum_quantity"
        ) ?: ['value' => 0];
        
        // Year to date interventions
        $kpis['ytd_interventions'] = $this->db->fetchOne(
            "SELECT COUNT(*) as value FROM interventions 
            WHERE YEAR(start_time) = YEAR(CURDATE())"
        ) ?: ['value' => 0];
        
        // Year to date volunteer hours
        $kpis['ytd_volunteer_hours'] = $this->db->fetchOne(
            "SELECT COALESCE(SUM(hours_worked), 0) as value 
            FROM intervention_members im
            INNER JOIN interventions i ON im.intervention_id = i.id
            WHERE YEAR(i.start_time) = YEAR(CURDATE())"
        ) ?: ['value' => 0];
        
        return $kpis;
    }
    
    /**
     * Get year-over-year comparison data for events
     */
    public function getYoYEventStats($currentYear = null, $previousYear = null) {
        if (!$currentYear) {
            $currentYear = date('Y');
        }
        if (!$previousYear) {
            $previousYear = $currentYear - 1;
        }
        
        $sql = "SELECT 
                    month,
                    event_type,
                    SUM(CASE WHEN year = ? THEN event_count ELSE 0 END) as current_year_count,
                    SUM(CASE WHEN year = ? THEN event_count ELSE 0 END) as previous_year_count
                FROM v_yoy_event_stats
                WHERE year IN (?, ?)
                GROUP BY month, event_type
                ORDER BY month, event_type";
        
        return $this->db->fetchAll($sql, [$currentYear, $previousYear, $currentYear, $previousYear]);
    }
    
    /**
     * Get year-over-year comparison data for members
     */
    public function getYoYMemberStats($currentYear = null, $previousYear = null) {
        if (!$currentYear) {
            $currentYear = date('Y');
        }
        if (!$previousYear) {
            $previousYear = $currentYear - 1;
        }
        
        $sql = "SELECT 
                    month,
                    member_status,
                    SUM(CASE WHEN year = ? THEN member_count ELSE 0 END) as current_year_count,
                    SUM(CASE WHEN year = ? THEN member_count ELSE 0 END) as previous_year_count
                FROM v_yoy_member_stats
                WHERE year IN (?, ?)
                GROUP BY month, member_status
                ORDER BY month, member_status";
        
        return $this->db->fetchAll($sql, [$currentYear, $previousYear, $currentYear, $previousYear]);
    }
    
    /**
     * Get geographic intervention data for maps
     */
    public function getGeographicInterventionData($startDate = null, $endDate = null) {
        $where = ["1=1"];
        $params = [];
        
        if ($startDate) {
            $where[] = "start_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $where[] = "start_date <= ?";
            $params[] = $endDate;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT 
                    intervention_id,
                    title,
                    municipality,
                    province,
                    start_date,
                    event_type,
                    latitude,
                    longitude,
                    volunteer_count,
                    total_hours
                FROM v_intervention_geographic_stats
                WHERE $whereClause
                ORDER BY start_date DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get event statistics by type for charts
     */
    public function getEventStatsByType($startDate = null, $endDate = null) {
        $where = ["1=1"];
        $params = [];
        
        if ($startDate) {
            $where[] = "start_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $where[] = "start_date <= ?";
            $params[] = $endDate;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT 
                    event_type,
                    COUNT(*) as count,
                    SUM(CASE WHEN status = 'concluso' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'in_corso' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'annullato' THEN 1 ELSE 0 END) as cancelled
                FROM events
                WHERE $whereClause
                GROUP BY event_type
                ORDER BY count DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get monthly event trend for charts
     */
    public function getMonthlyEventTrend($months = 12) {
        $sql = "SELECT 
                    DATE_FORMAT(start_date, '%Y-%m') as month,
                    event_type,
                    COUNT(*) as count
                FROM events
                WHERE start_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(start_date, '%Y-%m'), event_type
                ORDER BY month, event_type";
        
        return $this->db->fetchAll($sql, [$months]);
    }
    
    /**
     * Get volunteer activity statistics for charts
     */
    public function getVolunteerActivityStats($limit = 20) {
        $sql = "SELECT 
                    m.id,
                    CONCAT(m.first_name, ' ', m.last_name) as name,
                    m.registration_number,
                    COUNT(DISTINCT ep.event_id) as event_count,
                    COALESCE(SUM(ep.hours), 0) + COALESCE(SUM(im.hours_worked), 0) as total_hours
                FROM members m
                LEFT JOIN event_participants ep ON m.id = ep.member_id
                LEFT JOIN intervention_members im ON m.id = im.member_id
                WHERE m.member_status = 'attivo'
                AND (ep.event_id IS NOT NULL OR im.intervention_id IS NOT NULL)
                GROUP BY m.id, m.first_name, m.last_name, m.registration_number
                ORDER BY total_hours DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Get warehouse stock level statistics
     */
    public function getWarehouseStockStats() {
        $sql = "SELECT 
                    category,
                    COUNT(*) as item_count,
                    SUM(quantity) as total_quantity,
                    SUM(CASE WHEN quantity <= minimum_quantity THEN 1 ELSE 0 END) as low_stock_count
                FROM warehouse_items
                GROUP BY category
                ORDER BY category";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get vehicle usage statistics
     */
    public function getVehicleUsageStats($months = 12) {
        $sql = "SELECT 
                    v.id,
                    v.name,
                    v.license_plate,
                    COUNT(DISTINCT vm.id) as movement_count,
                    SUM(vm.end_km - vm.start_km) as total_km
                FROM vehicles v
                LEFT JOIN vehicle_movements vm ON v.id = vm.vehicle_id
                WHERE vm.departure_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                AND vm.end_km IS NOT NULL
                GROUP BY v.id, v.name, v.license_plate
                ORDER BY total_km DESC";
        
        return $this->db->fetchAll($sql, [$months]);
    }
    
    /**
     * Get training course statistics
     */
    public function getTrainingCourseStats() {
        $sql = "SELECT 
                    course_type,
                    COUNT(*) as course_count,
                    SUM(CASE WHEN status = 'concluso' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'in_corso' THEN 1 ELSE 0 END) as in_progress,
                    AVG(hours) as avg_hours
                FROM training_courses
                WHERE start_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY course_type
                ORDER BY course_count DESC";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get meeting attendance statistics
     */
    public function getMeetingAttendanceStats($limit = 10) {
        $sql = "SELECT 
                    m.id,
                    m.title,
                    m.meeting_type,
                    m.date,
                    COUNT(DISTINCT mp.member_id) as attendees
                FROM meetings m
                LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id AND mp.attended = 1
                WHERE m.date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY m.id, m.title, m.meeting_type, m.date
                ORDER BY m.date DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Get user's custom KPI configuration
     */
    public function getUserKPIConfig() {
        if (!$this->userId) {
            return [];
        }
        
        $sql = "SELECT * FROM dashboard_kpi_config 
                WHERE user_id = ? 
                ORDER BY display_order";
        
        return $this->db->fetchAll($sql, [$this->userId]);
    }
    
    /**
     * Get user's custom chart configuration
     */
    public function getUserChartConfig() {
        if (!$this->userId) {
            return [];
        }
        
        $sql = "SELECT * FROM dashboard_chart_config 
                WHERE user_id = ? AND is_visible = 1 
                ORDER BY position";
        
        return $this->db->fetchAll($sql, [$this->userId]);
    }
    
    /**
     * Save user KPI configuration
     */
    public function saveUserKPIConfig($kpiKey, $displayOrder, $isVisible, $customLabel = null, $customColor = null) {
        if (!$this->userId) {
            throw new Exception("User ID required");
        }
        
        $sql = "INSERT INTO dashboard_kpi_config 
                (user_id, kpi_key, display_order, is_visible, custom_label, custom_color)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                display_order = VALUES(display_order),
                is_visible = VALUES(is_visible),
                custom_label = VALUES(custom_label),
                custom_color = VALUES(custom_color)";
        
        return $this->db->execute($sql, [
            $this->userId, 
            $kpiKey, 
            $displayOrder, 
            $isVisible, 
            $customLabel, 
            $customColor
        ]);
    }
    
    /**
     * Save user chart configuration
     */
    public function saveUserChartConfig($chartKey, $chartType, $position, $isVisible, $customTitle = null, $dateRange = 'last_12_months') {
        if (!$this->userId) {
            throw new Exception("User ID required");
        }
        
        $sql = "INSERT INTO dashboard_chart_config 
                (user_id, chart_key, chart_type, position, is_visible, custom_title, date_range)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                chart_type = VALUES(chart_type),
                position = VALUES(position),
                is_visible = VALUES(is_visible),
                custom_title = VALUES(custom_title),
                date_range = VALUES(date_range)";
        
        return $this->db->execute($sql, [
            $this->userId,
            $chartKey,
            $chartType,
            $position,
            $isVisible,
            $customTitle,
            $dateRange
        ]);
    }
    
    /**
     * Get cached statistics
     */
    private function getCachedStats($cacheKey) {
        $sql = "SELECT cache_data FROM dashboard_stats_cache 
                WHERE cache_key = ? AND expires_at > NOW()";
        
        $result = $this->db->fetchOne($sql, [$cacheKey]);
        
        if ($result) {
            return json_decode($result['cache_data'], true);
        }
        
        return null;
    }
    
    /**
     * Set cached statistics
     */
    private function setCachedStats($cacheKey, $data, $expiryMinutes = 60) {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryMinutes} minutes"));
        
        $sql = "INSERT INTO dashboard_stats_cache (cache_key, cache_data, expires_at)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                cache_data = VALUES(cache_data),
                expires_at = VALUES(expires_at)";
        
        return $this->db->execute($sql, [$cacheKey, json_encode($data), $expiresAt]);
    }
    
    /**
     * Get comprehensive dashboard data with caching
     */
    public function getDashboardData($useCache = true) {
        $cacheKey = "dashboard_data_user_{$this->userId}";
        
        if ($useCache) {
            $cached = $this->getCachedStats($cacheKey);
            if ($cached) {
                return $cached;
            }
        }
        
        $data = [
            'kpis' => $this->getKPIData(),
            'event_stats' => $this->getEventStatsByType(date('Y-m-d', strtotime('-12 months'))),
            'monthly_trend' => $this->getMonthlyEventTrend(12),
            'volunteer_activity' => $this->getVolunteerActivityStats(20),
            'warehouse_stats' => $this->getWarehouseStockStats(),
            'vehicle_usage' => $this->getVehicleUsageStats(12),
            'training_stats' => $this->getTrainingCourseStats(),
            'meeting_attendance' => $this->getMeetingAttendanceStats(10),
        ];
        
        if ($useCache) {
            $this->setCachedStats($cacheKey, $data, 30); // Cache for 30 minutes
        }
        
        return $data;
    }
}
