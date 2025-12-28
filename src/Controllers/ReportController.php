<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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
                    member_status as status,
                    COUNT(*) as count
                FROM members
                GROUP BY member_status
                ORDER BY member_status";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Report soci per qualifica
     */
    public function membersByQualification() {
        $sql = "SELECT 
                    COALESCE(mr.role_name, 'Non assegnato') as qualification,
                    COUNT(DISTINCT m.id) as count
                FROM members m
                LEFT JOIN member_roles mr ON m.id = mr.member_id 
                    AND (mr.end_date IS NULL OR mr.end_date >= CURDATE())
                GROUP BY mr.role_name
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
                    SUM(CASE WHEN quantity <= minimum_quantity THEN 1 ELSE 0 END) as low_stock_items
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
                    minimum_quantity,
                    unit
                FROM warehouse_items
                WHERE quantity <= minimum_quantity
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
                    SUM(CASE WHEN member_status = 'attivo' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN member_status = 'sospeso' THEN 1 ELSE 0 END) as suspended
                FROM members";
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
    
    /**
     * Report ore volontariato per tipo di evento (annuale)
     * 
     * @param int $year Anno da analizzare
     * @return array Dati del report
     */
    public function volunteerHoursByEventType($year) {
        $sql = "SELECT 
                    e.event_type,
                    COUNT(DISTINCT e.id) as num_eventi,
                    COUNT(DISTINCT ep.member_id) as num_volontari,
                    SUM(ep.hours) as ore_totali,
                    AVG(ep.hours) as ore_medie
                FROM events e
                LEFT JOIN event_participants ep ON e.id = ep.event_id
                WHERE YEAR(e.start_date) = ?
                GROUP BY e.event_type
                ORDER BY ore_totali DESC";
        
        return $this->db->fetchAll($sql, [$year]);
    }
    
    /**
     * Report numero e tipologie di eventi (annuale)
     * 
     * @param int $year Anno da analizzare
     * @return array Dati del report
     */
    public function eventsByTypeAndCount($year) {
        $sql = "SELECT 
                    e.event_type,
                    e.status,
                    COUNT(*) as numero_eventi,
                    COUNT(DISTINCT ep.member_id) as volontari_coinvolti,
                    SUM(ep.hours) as ore_totali,
                    MIN(e.start_date) as primo_evento,
                    MAX(e.start_date) as ultimo_evento
                FROM events e
                LEFT JOIN event_participants ep ON e.id = ep.event_id
                WHERE YEAR(e.start_date) = ?
                GROUP BY e.event_type, e.status
                ORDER BY e.event_type, numero_eventi DESC";
        
        return $this->db->fetchAll($sql, [$year]);
    }
    
    /**
     * Report ore di presenza e attività per singolo volontario (annuale)
     * 
     * @param int $year Anno da analizzare
     * @return array Dati del report
     */
    public function volunteerActivityReport($year) {
        $sql = "SELECT 
                    m.id,
                    m.registration_number,
                    m.first_name,
                    m.last_name,
                    m.member_status,
                    COUNT(DISTINCT ep.event_id) as num_eventi,
                    SUM(ep.hours) as ore_totali,
                    AVG(ep.hours) as ore_medie_per_evento,
                    GROUP_CONCAT(DISTINCT e.event_type ORDER BY e.event_type SEPARATOR ', ') as tipi_eventi,
                    MIN(e.start_date) as primo_evento,
                    MAX(e.start_date) as ultimo_evento
                FROM members m
                LEFT JOIN event_participants ep ON m.id = ep.member_id
                LEFT JOIN events e ON ep.event_id = e.id AND YEAR(e.start_date) = ?
                WHERE ep.id IS NOT NULL
                GROUP BY m.id
                ORDER BY ore_totali DESC, m.last_name, m.first_name";
        
        return $this->db->fetchAll($sql, [$year]);
    }
    
    /**
     * Report chilometri mezzi su base annua
     * 
     * @param int $year Anno da analizzare
     * @return array Dati del report
     */
    public function vehicleKilometersReport($year) {
        $sql = "SELECT 
                    v.id,
                    v.name,
                    v.license_plate,
                    v.vehicle_type,
                    v.brand,
                    v.model,
                    COUNT(DISTINCT vm.id) as num_movimenti,
                    SUM(CASE 
                        WHEN vm.return_km IS NOT NULL AND vm.departure_km IS NOT NULL 
                        THEN (vm.return_km - vm.departure_km)
                        ELSE 0 
                    END) as km_totali,
                    AVG(CASE 
                        WHEN vm.return_km IS NOT NULL AND vm.departure_km IS NOT NULL 
                        THEN (vm.return_km - vm.departure_km)
                        ELSE NULL 
                    END) as km_medi_per_movimento,
                    MIN(vm.departure_datetime) as primo_movimento,
                    MAX(vm.return_datetime) as ultimo_movimento
                FROM vehicles v
                LEFT JOIN vehicle_movements vm ON v.id = vm.vehicle_id 
                    AND YEAR(vm.departure_datetime) = ?
                    AND vm.return_km IS NOT NULL 
                    AND vm.departure_km IS NOT NULL
                WHERE vm.id IS NOT NULL
                GROUP BY v.id
                ORDER BY km_totali DESC";
        
        return $this->db->fetchAll($sql, [$year]);
    }
    
    /**
     * Genera file Excel da dati report
     * 
     * @param array $data Dati del report
     * @param string $sheetName Nome del foglio
     * @param string $filename Nome del file
     * @return void (genera download)
     */
    public function exportToExcel($data, $sheetName = 'Report', $filename = 'report.xlsx') {
        if (empty($data)) {
            throw new \Exception('Nessun dato da esportare');
        }
        
        // Usa PhpSpreadsheet per generare Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);
        
        // Header
        $headers = array_keys($data[0]);
        $colIndex = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($colIndex, 1, ucfirst(str_replace('_', ' ', $header)));
            $sheet->getStyleByColumnAndRow($colIndex, 1)->getFont()->setBold(true);
            $colIndex++;
        }
        
        // Dati
        $row = 2;
        foreach ($data as $record) {
            $colIndex = 1;
            foreach ($record as $value) {
                $sheet->setCellValueByColumnAndRow($colIndex, $row, $value);
                $colIndex++;
            }
            $row++;
        }
        
        // Auto-size columns
        $numColumns = count($headers);
        for ($i = 1; $i <= $numColumns; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        
        // Output
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
