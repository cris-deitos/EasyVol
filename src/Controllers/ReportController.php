<?php
namespace EasyVol\Controllers;

use EasyVol\Database;

/**
 * Report Controller
 * 
 * Gestisce report e statistiche del sistema
 */
class ReportController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Report soci per stato
     */
    public function membersByStatus() {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count
                FROM members
                WHERE deleted_at IS NULL
                GROUP BY status
                ORDER BY status";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Report soci per qualifica
     */
    public function membersByQualification() {
        $sql = "SELECT 
                    qualification,
                    COUNT(*) as count
                FROM members
                WHERE deleted_at IS NULL
                GROUP BY qualification
                ORDER BY count DESC";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Statistiche eventi per tipo
     */
    public function eventsByType($startDate = null, $endDate = null) {
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
                    COUNT(DISTINCT id) as total_events
                FROM events
                WHERE $whereClause
                GROUP BY event_type";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Statistiche partecipazioni eventi
     */
    public function eventParticipationStats($startDate = null, $endDate = null) {
        $where = ["1=1"];
        $params = [];
        
        if ($startDate) {
            $where[] = "e.start_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $where[] = "e.start_date <= ?";
            $params[] = $endDate;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT 
                    m.id,
                    m.first_name,
                    m.last_name,
                    m.registration_number,
                    COUNT(ep.id) as event_count
                FROM members m
                LEFT JOIN event_participants ep ON m.id = ep.member_id
                LEFT JOIN events e ON ep.event_id = e.id
                WHERE $whereClause
                GROUP BY m.id
                ORDER BY event_count DESC
                LIMIT 20";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Report mezzi per tipo
     */
    public function vehiclesByType() {
        $sql = "SELECT 
                    vehicle_type,
                    COUNT(*) as count
                FROM vehicles
                GROUP BY vehicle_type";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Report scadenze mezzi
     */
    public function vehicleExpirations($daysAhead = 30) {
        $sql = "SELECT 
                    v.id,
                    v.name,
                    v.license_plate,
                    v.insurance_expiry,
                    v.inspection_expiry,
                    DATEDIFF(v.insurance_expiry, CURDATE()) as insurance_days,
                    DATEDIFF(v.inspection_expiry, CURDATE()) as inspection_days
                FROM vehicles v
                WHERE (v.insurance_expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                    OR v.inspection_expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY))
                ORDER BY 
                    LEAST(COALESCE(v.insurance_expiry, '9999-12-31'), 
                          COALESCE(v.inspection_expiry, '9999-12-31'))";
        
        return $this->db->fetchAll($sql, [$daysAhead, $daysAhead]);
    }
    
    /**
     * Report giacenze magazzino
     */
    public function warehouseStock() {
        $sql = "SELECT 
                    category,
                    COUNT(*) as items,
                    SUM(quantity) as total_quantity,
                    SUM(CASE WHEN quantity <= min_quantity THEN 1 ELSE 0 END) as low_stock_items
                FROM warehouse_items
                GROUP BY category
                ORDER BY category";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Report articoli sotto scorta
     */
    public function lowStockItems() {
        $sql = "SELECT 
                    id,
                    code,
                    name,
                    category,
                    quantity,
                    min_quantity,
                    unit
                FROM warehouse_items
                WHERE quantity <= min_quantity
                ORDER BY category, name";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Report corsi di formazione
     */
    public function trainingStats($startDate = null, $endDate = null) {
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
                    course_type,
                    COUNT(*) as course_count,
                    COUNT(DISTINCT id) as total_courses
                FROM training_courses
                WHERE $whereClause
                GROUP BY course_type";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Report documenti per categoria
     */
    public function documentsByCategory() {
        $sql = "SELECT 
                    category,
                    COUNT(*) as count,
                    SUM(file_size) as total_size
                FROM documents
                GROUP BY category
                ORDER BY count DESC";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Dashboard generale - KPI principali
     */
    public function getDashboardStats() {
        $stats = [];
        
        // Soci
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'attivo' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'sospeso' THEN 1 ELSE 0 END) as suspended
                FROM members
                WHERE deleted_at IS NULL";
        $stats['members'] = $this->db->fetchOne($sql);
        
        // Eventi
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'aperto' THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN status = 'in_corso' THEN 1 ELSE 0 END) as in_progress
                FROM events";
        $stats['events'] = $this->db->fetchOne($sql);
        
        // Mezzi
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'operativo' THEN 1 ELSE 0 END) as operational
                FROM vehicles";
        $stats['vehicles'] = $this->db->fetchOne($sql);
        
        // Corsi
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'in_corso' THEN 1 ELSE 0 END) as active
                FROM training_courses";
        $stats['training'] = $this->db->fetchOne($sql);
        
        return $stats;
    }
    
    /**
     * Attività recenti
     */
    public function getRecentActivity($limit = 50) {
        $sql = "SELECT 
                    al.*,
                    u.username
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Genera CSV da risultati query
     */
    public function exportToCSV($data, $filename = 'export.csv') {
        if (empty($data)) {
            return false;
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Header
        fputcsv($output, array_keys($data[0]));
        
        // Righe
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Registra attività nel log
     */
    private function logActivity($userId, $module, $action, $recordId, $details) {
        try {
            $sql = "INSERT INTO activity_logs 
                    (user_id, module, action, record_id, description, ip_address, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $userId,
                $module,
                $action,
                $recordId,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ];
            
            $this->db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Errore log attività: " . $e->getMessage());
        }
    }
}
