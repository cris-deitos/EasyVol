<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
                    e.id as id_evento,
                    e.title as titolo_evento,
                    e.event_type as tipo_evento,
                    e.municipality as comune,
                    e.start_date as data_evento,
                    COUNT(DISTINCT i.id) as numero_interventi,
                    COUNT(DISTINCT COALESCE(ep.member_id, im.member_id)) as numero_volontari,
                    (COALESCE(SUM(ep.hours), 0) + COALESCE(SUM(im.hours_worked), 0)) as ore_totali,
                    (COALESCE(SUM(ep.hours), 0) + COALESCE(SUM(im.hours_worked), 0)) / NULLIF(COUNT(DISTINCT COALESCE(ep.member_id, im.member_id)), 0) as ore_medie
                FROM events e
                LEFT JOIN event_participants ep ON e.id = ep.event_id
                LEFT JOIN interventions i ON e.id = i.event_id
                LEFT JOIN intervention_members im ON i.id = im.intervention_id
                WHERE YEAR(e.start_date) = ?
                GROUP BY e.id, e.title, e.event_type, e.municipality, e.start_date
                ORDER BY e.event_type, ore_totali DESC";
        
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
                    e.id as id_evento,
                    e.event_type as tipo_evento,
                    e.title as titolo,
                    e.status as stato,
                    e.municipality as comune,
                    e.legal_benefits_recognized as benefici_legali_riconosciuti,
                    e.start_date as data_ora_apertura_evento,
                    e.end_date as data_ora_chiusura_evento,
                    COUNT(DISTINCT i.id) as numero_interventi,
                    COUNT(DISTINCT COALESCE(ep.member_id, im.member_id)) as volontari_coinvolti,
                    (COALESCE(SUM(ep.hours), 0) + COALESCE(SUM(im.hours_worked), 0)) as ore_totali,
                    MIN(i.start_time) as data_ora_primo_intervento,
                    COALESCE(MAX(i.end_time), MAX(i.start_time)) as data_ora_ultimo_intervento
                FROM events e
                LEFT JOIN event_participants ep ON e.id = ep.event_id
                LEFT JOIN interventions i ON e.id = i.event_id
                LEFT JOIN intervention_members im ON i.id = im.intervention_id
                WHERE YEAR(e.start_date) = ?
                GROUP BY e.id, e.event_type, e.title, e.status, e.municipality, e.legal_benefits_recognized, e.start_date, e.end_date
                ORDER BY e.start_date DESC, e.event_type";
        
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
                    member_data.id,
                    member_data.numero_matricola,
                    member_data.nome,
                    member_data.cognome,
                    member_data.stato_socio,
                    member_data.numero_eventi,
                    member_data.numero_interventi,
                    member_data.ore_totali,
                    member_data.ore_totali / NULLIF(member_data.numero_eventi, 0) as ore_medie_per_evento,
                    member_data.tipi_eventi,
                    member_data.primo_evento,
                    member_data.ultimo_evento
                FROM (
                    SELECT 
                        m.id,
                        m.registration_number as numero_matricola,
                        m.first_name as nome,
                        m.last_name as cognome,
                        m.member_status as stato_socio,
                        COUNT(DISTINCT e.id) as numero_eventi,
                        (SELECT COUNT(DISTINCT i2.id) 
                         FROM interventions i2 
                         INNER JOIN intervention_members im2 ON i2.id = im2.intervention_id
                         WHERE im2.member_id = m.id 
                           AND YEAR(i2.start_time) = ?) as numero_interventi,
                        (COALESCE(SUM(ep.hours), 0) + 
                         COALESCE((SELECT SUM(im3.hours_worked) 
                                   FROM intervention_members im3
                                   INNER JOIN interventions i3 ON im3.intervention_id = i3.id
                                   INNER JOIN events e3 ON i3.event_id = e3.id
                                   WHERE im3.member_id = m.id 
                                   AND YEAR(e3.start_date) = ?), 0)) as ore_totali,
                        GROUP_CONCAT(DISTINCT e.event_type ORDER BY e.event_type SEPARATOR ', ') as tipi_eventi,
                        MIN(e.start_date) as primo_evento,
                        MAX(e.start_date) as ultimo_evento
                    FROM members m
                    LEFT JOIN event_participants ep ON m.id = ep.member_id
                    LEFT JOIN events e ON ep.event_id = e.id AND YEAR(e.start_date) = ?
                    WHERE EXISTS (
                        SELECT 1 FROM event_participants ep2 
                        INNER JOIN events e2 ON ep2.event_id = e2.id 
                        WHERE ep2.member_id = m.id AND YEAR(e2.start_date) = ?
                    ) OR EXISTS (
                        SELECT 1 FROM intervention_members im4
                        INNER JOIN interventions i4 ON im4.intervention_id = i4.id
                        INNER JOIN events e4 ON i4.event_id = e4.id
                        WHERE im4.member_id = m.id AND YEAR(e4.start_date) = ?
                    )
                    GROUP BY m.id
                ) AS member_data
                ORDER BY member_data.ore_totali DESC, member_data.cognome, member_data.nome";
        
        return $this->db->fetchAll($sql, [$year, $year, $year, $year, $year]);
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
                    v.name as nome,
                    v.license_plate as targa,
                    v.vehicle_type as tipo_mezzo,
                    v.brand as marca,
                    v.model as modello,
                    COUNT(DISTINCT vm.id) as numero_movimenti,
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
            $sheet->setCellValue([$colIndex, 1], ucfirst(str_replace('_', ' ', $header)));
            $sheet->getStyle([$colIndex, 1])->getFont()->setBold(true);
            $colIndex++;
        }
        
        // Dati
        $row = 2;
        foreach ($data as $record) {
            $colIndex = 1;
            foreach ($record as $value) {
                $sheet->setCellValue([$colIndex, $row], $value);
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
