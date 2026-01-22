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
                    CASE 
                        WHEN COUNT(DISTINCT i.id) = 0 AND e.end_date IS NOT NULL THEN 
                            COALESCE(SUM(ep.hours), 0) + (COUNT(DISTINCT ep.member_id) * TIMESTAMPDIFF(HOUR, e.start_date, e.end_date))
                        ELSE 
                            COALESCE(SUM(ep.hours), 0) + COALESCE(SUM(im.hours_worked), 0)
                    END as ore_totali,
                    CASE 
                        WHEN COUNT(DISTINCT i.id) = 0 AND e.end_date IS NOT NULL THEN 
                            (COALESCE(SUM(ep.hours), 0) + (COUNT(DISTINCT ep.member_id) * TIMESTAMPDIFF(HOUR, e.start_date, e.end_date))) / NULLIF(COUNT(DISTINCT COALESCE(ep.member_id, im.member_id)), 0)
                        ELSE 
                            (COALESCE(SUM(ep.hours), 0) + COALESCE(SUM(im.hours_worked), 0)) / NULLIF(COUNT(DISTINCT COALESCE(ep.member_id, im.member_id)), 0)
                    END as ore_medie
                FROM events e
                LEFT JOIN event_participants ep ON e.id = ep.event_id
                LEFT JOIN interventions i ON e.id = i.event_id
                LEFT JOIN intervention_members im ON i.id = im.intervention_id
                WHERE YEAR(e.start_date) = ?
                GROUP BY e.id, e.title, e.event_type, e.municipality, e.start_date, e.end_date
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
                    CASE 
                        WHEN COUNT(DISTINCT i.id) = 0 AND e.end_date IS NOT NULL THEN 
                            COALESCE(SUM(ep.hours), 0) + (COUNT(DISTINCT ep.member_id) * TIMESTAMPDIFF(HOUR, e.start_date, e.end_date))
                        ELSE 
                            COALESCE(SUM(ep.hours), 0) + COALESCE(SUM(im.hours_worked), 0)
                    END as ore_totali,
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
                                   AND YEAR(e3.start_date) = ?), 0) +
                         COALESCE((SELECT SUM(TIMESTAMPDIFF(HOUR, e5.start_date, e5.end_date))
                                   FROM event_participants ep5
                                   INNER JOIN events e5 ON ep5.event_id = e5.id
                                   WHERE ep5.member_id = m.id
                                   AND YEAR(e5.start_date) = ?
                                   AND e5.end_date IS NOT NULL
                                   AND NOT EXISTS (
                                       SELECT 1 FROM interventions i5 
                                       WHERE i5.event_id = e5.id
                                   )), 0)) as ore_totali,
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
        
        return $this->db->fetchAll($sql, [$year, $year, $year, $year, $year, $year]);
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
    
    /**
     * Raccoglie tutti i dati per il Report Annuale Associativo
     * 
     * @param int $year Anno da analizzare
     * @return array Dati completi del report
     */
    public function getAnnualAssociationReportData($year) {
        // Validazione anno
        $year = intval($year);
        $minYear = 2000;
        $maxYear = date('Y') + 1;
        if ($year < $minYear || $year > $maxYear) {
            throw new \InvalidArgumentException("Anno non valido. Deve essere tra {$minYear} e {$maxYear}");
        }
        
        $data = [];
        
        // Date per l'anno
        $yearStart = "{$year}-01-01";
        $yearEnd = "{$year}-12-31";
        
        // ===== SITUAZIONE AL 01.01.XXXX =====
        $data['year_start'] = [
            'date' => $yearStart,
            'members_active' => $this->getMembersCountByStatusAndDate('attivo', $yearStart),
            'members_suspended' => $this->getMembersCountByStatusAndDate(['sospeso', 'in_aspettativa', 'in_congedo'], $yearStart),
            'junior_members_active' => $this->getJuniorMembersCountByStatusAndDate('attivo', $yearStart),
            'junior_members_suspended' => $this->getJuniorMembersCountByStatusAndDate(['sospeso', 'in_aspettativa', 'in_congedo'], $yearStart),
            'vehicles_total' => $this->getVehiclesCountByDate($yearStart),
            'vehicles_veicoli' => $this->getVehiclesCountByTypeAndDate('veicolo', $yearStart),
            'vehicles_rimorchi' => $this->getVehiclesCountByTypeAndDate('rimorchio', $yearStart),
            'vehicles_natanti' => $this->getVehiclesCountByTypeAndDate('natante', $yearStart),
        ];
        
        // ===== SITUAZIONE AL 31.12.XXXX =====
        $data['year_end'] = [
            'date' => $yearEnd,
            'members_active' => $this->getMembersCountByStatusAndDate('attivo', $yearEnd),
            'members_suspended' => $this->getMembersCountByStatusAndDate(['sospeso', 'in_aspettativa', 'in_congedo'], $yearEnd),
            'junior_members_active' => $this->getJuniorMembersCountByStatusAndDate('attivo', $yearEnd),
            'junior_members_suspended' => $this->getJuniorMembersCountByStatusAndDate(['sospeso', 'in_aspettativa', 'in_congedo'], $yearEnd),
            'vehicles_total' => $this->getVehiclesCountByDate($yearEnd),
            'vehicles_veicoli' => $this->getVehiclesCountByTypeAndDate('veicolo', $yearEnd),
            'vehicles_rimorchi' => $this->getVehiclesCountByTypeAndDate('rimorchio', $yearEnd),
            'vehicles_natanti' => $this->getVehiclesCountByTypeAndDate('natante', $yearEnd),
        ];
        
        // ===== ATTIVITÀ ANNUALE =====
        $data['annual_activity'] = [
            'new_members' => $this->getNewMembersCountByYear($year),
            'new_junior_members' => $this->getNewJuniorMembersCountByYear($year),
            'events_total' => $this->getEventsCountByYear($year),
            'events_emergenza' => $this->getEventsCountByTypeAndYear('emergenza', $year),
            'events_esercitazione' => $this->getEventsCountByTypeAndYear('esercitazione', $year),
            'events_attivita' => $this->getEventsCountByTypeAndYear('attivita', $year),
            'events_servizio' => $this->getEventsCountByTypeAndYear('servizio', $year),
            'meetings_total' => $this->getMeetingsCountByYear($year),
            'meetings_consiglio' => $this->getMeetingsCountByTypeAndYear('consiglio_direttivo', $year),
            'meetings_assemblea_ordinaria' => $this->getMeetingsCountByTypeAndYear('assemblea_ordinaria', $year),
            'meetings_assemblea_straordinaria' => $this->getMeetingsCountByTypeAndYear('assemblea_straordinaria', $year),
            'training_courses' => $this->getTrainingCoursesCountByYear($year),
            'vehicle_km_total' => $this->getVehicleKilometersByYear($year),
            'vehicle_hours_total' => $this->getVehicleHoursByYear($year),
        ];
        
        // ===== SEZIONE CONVENZIONE =====
        $data['convention_section'] = $this->getEventsByMunicipalityAndYear($year);
        
        return $data;
    }
    
    /**
     * Conta i soci per stato a una certa data
     */
    private function getMembersCountByStatusAndDate($status, $date) {
        if (is_array($status)) {
            $placeholders = implode(',', array_fill(0, count($status), '?'));
            $sql = "SELECT COUNT(*) as count
                    FROM members
                    WHERE member_status IN ($placeholders)
                    AND registration_date <= ?";
            $params = array_merge($status, [$date]);
        } else {
            $sql = "SELECT COUNT(*) as count
                    FROM members
                    WHERE member_status = ?
                    AND registration_date <= ?";
            $params = [$status, $date];
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result['count'] ?? 0;
    }
    
    /**
     * Conta i cadetti per stato a una certa data
     */
    private function getJuniorMembersCountByStatusAndDate($status, $date) {
        if (is_array($status)) {
            $placeholders = implode(',', array_fill(0, count($status), '?'));
            $sql = "SELECT COUNT(*) as count
                    FROM junior_members
                    WHERE member_status IN ($placeholders)
                    AND registration_date <= ?";
            $params = array_merge($status, [$date]);
        } else {
            $sql = "SELECT COUNT(*) as count
                    FROM junior_members
                    WHERE member_status = ?
                    AND registration_date <= ?";
            $params = [$status, $date];
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result['count'] ?? 0;
    }
    
    /**
     * Conta i mezzi totali a una certa data
     */
    private function getVehiclesCountByDate($date) {
        $sql = "SELECT COUNT(*) as count
                FROM vehicles
                WHERE created_at <= ?";
        
        $result = $this->db->fetchOne($sql, [$date]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Conta i mezzi per tipo a una certa data
     */
    private function getVehiclesCountByTypeAndDate($type, $date) {
        $sql = "SELECT COUNT(*) as count
                FROM vehicles
                WHERE vehicle_type = ?
                AND created_at <= ?";
        
        $result = $this->db->fetchOne($sql, [$type, $date]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Conta nuovi soci iscritti nell'anno
     */
    private function getNewMembersCountByYear($year) {
        $sql = "SELECT COUNT(*) as count
                FROM members
                WHERE YEAR(registration_date) = ?";
        
        $result = $this->db->fetchOne($sql, [$year]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Conta nuovi cadetti iscritti nell'anno
     */
    private function getNewJuniorMembersCountByYear($year) {
        $sql = "SELECT COUNT(*) as count
                FROM junior_members
                WHERE YEAR(registration_date) = ?";
        
        $result = $this->db->fetchOne($sql, [$year]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Conta eventi totali nell'anno
     */
    private function getEventsCountByYear($year) {
        $sql = "SELECT COUNT(*) as count
                FROM events
                WHERE YEAR(start_date) = ?";
        
        $result = $this->db->fetchOne($sql, [$year]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Conta eventi per tipo nell'anno
     */
    private function getEventsCountByTypeAndYear($type, $year) {
        $sql = "SELECT COUNT(*) as count
                FROM events
                WHERE event_type = ?
                AND YEAR(start_date) = ?";
        
        $result = $this->db->fetchOne($sql, [$type, $year]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Conta riunioni totali nell'anno
     */
    private function getMeetingsCountByYear($year) {
        $sql = "SELECT COUNT(*) as count
                FROM meetings
                WHERE YEAR(meeting_date) = ?";
        
        $result = $this->db->fetchOne($sql, [$year]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Conta riunioni per tipo nell'anno
     */
    private function getMeetingsCountByTypeAndYear($type, $year) {
        $sql = "SELECT COUNT(*) as count
                FROM meetings
                WHERE meeting_type = ?
                AND YEAR(meeting_date) = ?";
        
        $result = $this->db->fetchOne($sql, [$type, $year]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Conta corsi di formazione organizzati nell'anno
     */
    private function getTrainingCoursesCountByYear($year) {
        $sql = "SELECT COUNT(*) as count
                FROM training_courses
                WHERE YEAR(start_date) = ?";
        
        $result = $this->db->fetchOne($sql, [$year]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Calcola chilometri totali dei mezzi nell'anno
     */
    private function getVehicleKilometersByYear($year) {
        $sql = "SELECT SUM(CASE 
                    WHEN return_km IS NOT NULL AND departure_km IS NOT NULL 
                         AND return_km >= departure_km
                    THEN (return_km - departure_km)
                    ELSE 0 
                END) as total_km
                FROM vehicle_movements
                WHERE YEAR(departure_datetime) = ?";
        
        $result = $this->db->fetchOne($sql, [$year]);
        return (int)($result['total_km'] ?? 0);
    }
    
    /**
     * Calcola ore totali di viaggio dei mezzi nell'anno
     */
    private function getVehicleHoursByYear($year) {
        $sql = "SELECT SUM(
                    TIMESTAMPDIFF(HOUR, departure_datetime, return_datetime)
                ) as total_hours
                FROM vehicle_movements
                WHERE YEAR(departure_datetime) = ?
                AND return_datetime IS NOT NULL";
        
        $result = $this->db->fetchOne($sql, [$year]);
        return (int)($result['total_hours'] ?? 0);
    }
    
    /**
     * Ottiene eventi raggruppati per comune con dettagli per sezione convenzione
     */
    private function getEventsByMunicipalityAndYear($year) {
        $sql = "SELECT 
                    e.municipality as comune,
                    e.title as titolo,
                    e.start_date as data_inizio,
                    e.end_date as data_fine,
                    COUNT(DISTINCT i.id) as numero_interventi
                FROM events e
                LEFT JOIN interventions i ON e.id = i.event_id
                WHERE YEAR(e.start_date) = ?
                AND e.municipality IS NOT NULL
                AND e.municipality != ''
                GROUP BY e.id, e.municipality, e.title, e.start_date, e.end_date
                ORDER BY e.municipality, e.start_date";
        
        return $this->db->fetchAll($sql, [$year]);
    }
    
    /**
     * Genera PDF del Report Annuale Associativo
     * 
     * @param int $year Anno del report
     * @param array $associationData Dati dell'associazione
     * @return void (genera download)
     */
    public function generateAnnualAssociationReportPDF($year, $associationData) {
        // Validazione parametri
        $year = intval($year);
        $minYear = 2000;
        $maxYear = date('Y') + 1;
        if ($year < $minYear || $year > $maxYear) {
            throw new \InvalidArgumentException("Anno non valido. Deve essere tra {$minYear} e {$maxYear}");
        }
        
        if (!is_array($associationData)) {
            throw new \InvalidArgumentException("I dati dell'associazione devono essere un array");
        }
        
        // Raccogli i dati
        $data = $this->getAnnualAssociationReportData($year);
        
        // Inizializza mPDF
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 10,
            'margin_footer' => 10,
        ]);
        
        // Genera HTML
        $html = $this->generateAnnualReportHTML($year, $data, $associationData);
        
        // Scrivi HTML nel PDF
        $mpdf->WriteHTML($html);
        
        // Output
        $filename = "report_annuale_associativo_{$year}.pdf";
        $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
        exit;
    }
    
    /**
     * Genera HTML per il Report Annuale Associativo
     */
    private function generateAnnualReportHTML($year, $data, $associationData) {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: "DejaVu Sans", Arial, sans-serif;
                    font-size: 10pt;
                    line-height: 1.5;
                }
                h1 {
                    color: #003366;
                    text-align: center;
                    font-size: 18pt;
                    margin-bottom: 5px;
                }
                h2 {
                    color: #003366;
                    font-size: 14pt;
                    margin-top: 20px;
                    margin-bottom: 10px;
                    border-bottom: 2px solid #003366;
                    padding-bottom: 5px;
                }
                h3 {
                    color: #0066cc;
                    font-size: 12pt;
                    margin-top: 15px;
                    margin-bottom: 8px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 3px solid #003366;
                    padding-bottom: 15px;
                }
                .association-name {
                    font-size: 16pt;
                    font-weight: bold;
                    color: #003366;
                    margin-bottom: 5px;
                }
                .association-details {
                    font-size: 9pt;
                    color: #666;
                }
                .stat-label {
                    font-weight: bold;
                    display: inline-block;
                    width: 250px;
                }
                .stat-value {
                    display: inline-block;
                    text-align: right;
                    width: 100px;
                    font-weight: bold;
                    color: #003366;
                }
                .indent {
                    margin-left: 30px;
                }
                .section {
                    margin-bottom: 20px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                    font-size: 9pt;
                }
                table th {
                    background-color: #003366;
                    color: white;
                    padding: 8px;
                    text-align: left;
                    font-weight: bold;
                }
                table td {
                    padding: 6px 8px;
                    border-bottom: 1px solid #ddd;
                }
                table tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                .footer {
                    margin-top: 30px;
                    text-align: center;
                    font-size: 8pt;
                    color: #666;
                }
            </style>
        </head>
        <body>';
        
        // Header con informazioni associazione
        $html .= '<div class="header">';
        $html .= '<div class="association-name">' . htmlspecialchars($associationData['name'] ?? 'Associazione di Volontariato') . '</div>';
        
        $addressParts = [];
        if (!empty($associationData['address_street'])) {
            $addressStr = htmlspecialchars($associationData['address_street']);
            if (!empty($associationData['address_number'])) {
                $addressStr .= ' ' . htmlspecialchars($associationData['address_number']);
            }
            $addressParts[] = $addressStr;
        }
        if (!empty($associationData['address_city'])) {
            $cityStr = '';
            if (!empty($associationData['address_cap'])) {
                $cityStr .= htmlspecialchars($associationData['address_cap']) . ' ';
            }
            $cityStr .= htmlspecialchars($associationData['address_city']);
            if (!empty($associationData['address_province'])) {
                $cityStr .= ' (' . htmlspecialchars($associationData['address_province']) . ')';
            }
            $addressParts[] = $cityStr;
        }
        if (!empty($addressParts)) {
            $html .= '<div class="association-details">' . implode(' - ', $addressParts) . '</div>';
        }
        
        if (!empty($associationData['email'])) {
            $html .= '<div class="association-details">Email: ' . htmlspecialchars($associationData['email']) . '</div>';
        }
        if (!empty($associationData['phone'])) {
            $html .= '<div class="association-details">Tel: ' . htmlspecialchars($associationData['phone']) . '</div>';
        }
        
        $html .= '</div>';
        
        // Titolo Report
        $html .= '<h1>Report Annuale Associativo ' . $year . '</h1>';
        
        // SITUAZIONE AL 01.01.XXXX
        $html .= '<h2>Situazione al 01.01.' . $year . '</h2>';
        $html .= '<div class="section">';
        $html .= '<div><span class="stat-label">Nr. Soci Attivi: </span><span class="stat-value">' . number_format($data['year_start']['members_active']) . '</span></div>';
        $html .= '<div><span class="stat-label">Nr. Soci Sospesi (Sospesi, in aspettativa, in congedo): </span><span class="stat-value">' . number_format($data['year_start']['members_suspended']) . '</span></div>';
        $html .= '<div><span class="stat-label">Nr. Cadetti Attivi: </span><span class="stat-value">' . number_format($data['year_start']['junior_members_active']) . '</span></div>';
        $html .= '<div><span class="stat-label">Nr. Cadetti Sospesi (Sospesi, in aspettativa, in congedo): </span><span class="stat-value">' . number_format($data['year_start']['junior_members_suspended']) . '</span></div>';
        $html .= '<div><span class="stat-label">Nr. Mezzi: </span><span class="stat-value">' . number_format($data['year_start']['vehicles_total']) . '</span></div>';
        $html .= '<div class="indent"><span class="stat-label">Di Cui Veicoli: </span><span class="stat-value">' . number_format($data['year_start']['vehicles_veicoli']) . '</span></div>';
        $html .= '<div class="indent"><span class="stat-label">Di Cui Rimorchi: </span><span class="stat-value">' . number_format($data['year_start']['vehicles_rimorchi']) . '</span></div>';
        $html .= '<div class="indent"><span class="stat-label">Di Cui Natanti: </span><span class="stat-value">' . number_format($data['year_start']['vehicles_natanti']) . '</span></div>';
        $html .= '</div>';
        
        // SITUAZIONE AL 31.12.XXXX
        $html .= '<h2>Situazione al 31.12.' . $year . '</h2>';
        $html .= '<div class="section">';
        $html .= '<div><span class="stat-label">Nr. Soci Attivi: </span><span class="stat-value">' . number_format($data['year_end']['members_active']) . '</span></div>';
        $html .= '<div><span class="stat-label">Nr. Soci Sospesi (Sospesi, in aspettativa, in congedo): </span><span class="stat-value">' . number_format($data['year_end']['members_suspended']) . '</span></div>';
        $html .= '<div><span class="stat-label">Nr. Cadetti Attivi: </span><span class="stat-value">' . number_format($data['year_end']['junior_members_active']) . '</span></div>';
        $html .= '<div><span class="stat-label">Nr. Cadetti Sospesi (Sospesi, in aspettativa, in congedo): </span><span class="stat-value">' . number_format($data['year_end']['junior_members_suspended']) . '</span></div>';
        $html .= '<div><span class="stat-label">Nr. Mezzi: </span><span class="stat-value">' . number_format($data['year_end']['vehicles_total']) . '</span></div>';
        $html .= '<div class="indent"><span class="stat-label">Di Cui Veicoli: </span><span class="stat-value">' . number_format($data['year_end']['vehicles_veicoli']) . '</span></div>';
        $html .= '<div class="indent"><span class="stat-label">Di Cui Rimorchi: </span><span class="stat-value">' . number_format($data['year_end']['vehicles_rimorchi']) . '</span></div>';
        $html .= '<div class="indent"><span class="stat-label">Di Cui Natanti: </span><span class="stat-value">' . number_format($data['year_end']['vehicles_natanti']) . '</span></div>';
        $html .= '</div>';
        
        // ATTIVITÀ ANNUALE
        $html .= '<h2>Attività Annuale</h2>';
        $html .= '<div class="section">';
        $html .= '<div><span class="stat-label">Nr. Nuovi Soci Iscritti: </span><span class="stat-value">' . number_format($data['annual_activity']['new_members']) . '</span></div>';
        $html .= '<div><span class="stat-label">Nr. Nuovi Cadetti Iscritti: </span><span class="stat-value">' . number_format($data['annual_activity']['new_junior_members']) . '</span></div>';
        $html .= '<br>';
        $html .= '<div><span class="stat-label">Nr. Eventi: </span><span class="stat-value">' . number_format($data['annual_activity']['events_total']) . '</span></div>';
        $html .= '<div class="indent"><span class="stat-label">Di cui Emergenze: </span><span class="stat-value">' . number_format($data['annual_activity']['events_emergenza']) . '</span></div>';
        $html .= '<div class="indent"><span class="stat-label">Di cui Esercitazioni: </span><span class="stat-value">' . number_format($data['annual_activity']['events_esercitazione']) . '</span></div>';
        $html .= '<div class="indent"><span class="stat-label">Di cui Attività: </span><span class="stat-value">' . number_format($data['annual_activity']['events_attivita']) . '</span></div>';
        $html .= '<div class="indent"><span class="stat-label">Di cui Servizi: </span><span class="stat-value">' . number_format($data['annual_activity']['events_servizio']) . '</span></div>';
        $html .= '<br>';
        $html .= '<div><span class="stat-label">Nr. Riunioni ed Assemblee: </span><span class="stat-value">' . number_format($data['annual_activity']['meetings_total']) . '</span></div>';
        $html .= '<div class="indent"><span class="stat-label">Di cui Consigli Direttivi: </span><span class="stat-value">' . number_format($data['annual_activity']['meetings_consiglio']) . '</span></div>';
        $html .= '<div class="indent"><span class="stat-label">Di cui Assemblee Ordinarie dei Soci: </span><span class="stat-value">' . number_format($data['annual_activity']['meetings_assemblea_ordinaria']) . '</span></div>';
        $html .= '<div class="indent"><span class="stat-label">Di cui Assemblee Straordinarie dei Soci: </span><span class="stat-value">' . number_format($data['annual_activity']['meetings_assemblea_straordinaria']) . '</span></div>';
        $html .= '<br>';
        $html .= '<div><span class="stat-label">Nr. Corsi di Formazione organizzati: </span><span class="stat-value">' . number_format($data['annual_activity']['training_courses']) . '</span></div>';
        $html .= '<br>';
        $html .= '<div><span class="stat-label">Km di viaggio con i mezzi dell\'Associazione: </span><span class="stat-value">' . number_format($data['annual_activity']['vehicle_km_total']) . '</span></div>';
        $html .= '<div><span class="stat-label">Ore di viaggio con i mezzi dell\'Associazione: </span><span class="stat-value">' . number_format($data['annual_activity']['vehicle_hours_total']) . '</span></div>';
        $html .= '</div>';
        
        // SEZIONE CONVENZIONE
        $html .= '<h2>Sezione Convenzione</h2>';
        $html .= '<div class="section">';
        $html .= '<p>Eventi raggruppati per Comune con data inizio e fine, titolo e numero di interventi:</p>';
        
        if (!empty($data['convention_section'])) {
            // Raggruppa per comune
            $eventsByMunicipality = [];
            foreach ($data['convention_section'] as $event) {
                $comune = $event['comune'];
                if (!isset($eventsByMunicipality[$comune])) {
                    $eventsByMunicipality[$comune] = [];
                }
                $eventsByMunicipality[$comune][] = $event;
            }
            
            // Genera tabelle per ogni comune
            foreach ($eventsByMunicipality as $comune => $events) {
                $html .= '<h3>Comune: ' . htmlspecialchars($comune) . '</h3>';
                $html .= '<table>';
                $html .= '<thead><tr>';
                $html .= '<th>Titolo Evento</th>';
                $html .= '<th>Data Inizio</th>';
                $html .= '<th>Data Fine</th>';
                $html .= '<th style="text-align: center;">Nr. Interventi</th>';
                $html .= '</tr></thead>';
                $html .= '<tbody>';
                
                foreach ($events as $event) {
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($event['titolo']) . '</td>';
                    $html .= '<td>' . date('d/m/Y H:i', strtotime($event['data_inizio'])) . '</td>';
                    $html .= '<td>' . ($event['data_fine'] ? date('d/m/Y H:i', strtotime($event['data_fine'])) : '-') . '</td>';
                    $html .= '<td style="text-align: center;">' . number_format($event['numero_interventi']) . '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody></table>';
            }
        } else {
            $html .= '<p><em>Nessun evento con comune specificato per l\'anno ' . $year . '</em></p>';
        }
        
        $html .= '</div>';
        
        // Footer
        $html .= '<div class="footer">';
        $html .= 'Report generato il ' . date('d/m/Y H:i') . ' da EasyVol';
        $html .= '</div>';
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    /**
     * Export members data to Excel/CSV
     */
    public function exportMembers($format = 'excel') {
        $sql = "SELECT 
                    m.id,
                    m.registration_number as numero_matricola,
                    m.first_name as nome,
                    m.last_name as cognome,
                    m.tax_code as codice_fiscale,
                    m.birth_date as data_nascita,
                    m.birth_place as luogo_nascita,
                    m.gender as sesso,
                    m.member_status as stato,
                    m.registration_date as data_iscrizione,
                    (SELECT value FROM member_contacts WHERE member_id = m.id AND contact_type = 'email' LIMIT 1) as email,
                    (SELECT value FROM member_contacts WHERE member_id = m.id AND contact_type = 'telefono_fisso' LIMIT 1) as telefono,
                    (SELECT value FROM member_contacts WHERE member_id = m.id AND contact_type = 'cellulare' LIMIT 1) as cellulare,
                    (SELECT street FROM member_addresses WHERE member_id = m.id AND address_type = 'residenza' LIMIT 1) as via,
                    (SELECT number FROM member_addresses WHERE member_id = m.id AND address_type = 'residenza' LIMIT 1) as civico,
                    (SELECT city FROM member_addresses WHERE member_id = m.id AND address_type = 'residenza' LIMIT 1) as citta,
                    (SELECT province FROM member_addresses WHERE member_id = m.id AND address_type = 'residenza' LIMIT 1) as provincia,
                    (SELECT cap FROM member_addresses WHERE member_id = m.id AND address_type = 'residenza' LIMIT 1) as cap
                FROM members m
                ORDER BY COALESCE(CAST(NULLIF(m.registration_number, '') AS UNSIGNED), 0) ASC, m.registration_number ASC";
        
        $data = $this->db->fetchAll($sql);
        
        if ($format === 'csv') {
            return $this->exportToCSV($data);
        } else {
            $this->exportToExcel($data, 'Soci', 'elenco_soci_' . date('Y-m-d') . '.xlsx');
        }
    }
    
    /**
     * Export junior members data to Excel/CSV
     */
    public function exportJuniorMembers($format = 'excel') {
        $sql = "SELECT 
                    jm.id,
                    jm.registration_number as numero_matricola,
                    jm.first_name as nome,
                    jm.last_name as cognome,
                    jm.tax_code as codice_fiscale,
                    jm.birth_date as data_nascita,
                    jm.birth_place as luogo_nascita,
                    jm.gender as sesso,
                    jm.member_status as stato,
                    jm.registration_date as data_iscrizione,
                    (SELECT street FROM junior_member_addresses WHERE junior_member_id = jm.id AND address_type = 'residenza' LIMIT 1) as via,
                    (SELECT number FROM junior_member_addresses WHERE junior_member_id = jm.id AND address_type = 'residenza' LIMIT 1) as civico,
                    (SELECT city FROM junior_member_addresses WHERE junior_member_id = jm.id AND address_type = 'residenza' LIMIT 1) as citta,
                    (SELECT province FROM junior_member_addresses WHERE junior_member_id = jm.id AND address_type = 'residenza' LIMIT 1) as provincia,
                    (SELECT cap FROM junior_member_addresses WHERE junior_member_id = jm.id AND address_type = 'residenza' LIMIT 1) as cap
                FROM junior_members jm
                ORDER BY CASE WHEN jm.registration_number LIKE 'C-%' 
                         THEN CAST(SUBSTRING(jm.registration_number, 3) AS UNSIGNED) 
                         ELSE 0 END ASC, jm.registration_number ASC";
        
        $data = $this->db->fetchAll($sql);
        
        if ($format === 'csv') {
            return $this->exportToCSV($data);
        } else {
            $this->exportToExcel($data, 'Cadetti', 'elenco_cadetti_' . date('Y-m-d') . '.xlsx');
        }
    }
    
    /**
     * Export meetings data to Excel/CSV
     */
    public function exportMeetings($format = 'excel') {
        $sql = "SELECT 
                    m.id,
                    m.title as titolo,
                    m.meeting_type as tipo,
                    m.meeting_date as data,
                    m.start_time as ora_inizio,
                    m.end_time as ora_fine,
                    m.location as luogo,
                    m.status as stato,
                    COUNT(DISTINCT mp.member_id) as partecipanti,
                    SUM(CASE WHEN mp.present = 1 THEN 1 ELSE 0 END) as presenti
                FROM meetings m
                LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
                GROUP BY m.id
                ORDER BY m.meeting_date DESC, m.start_time DESC";
        
        $data = $this->db->fetchAll($sql);
        
        if ($format === 'csv') {
            return $this->exportToCSV($data);
        } else {
            $this->exportToExcel($data, 'Riunioni', 'elenco_riunioni_' . date('Y-m-d') . '.xlsx');
        }
    }
    
    /**
     * Export vehicles data to Excel/CSV
     */
    public function exportVehicles($format = 'excel') {
        $sql = "SELECT 
                    v.id,
                    v.name as nome,
                    v.license_plate as targa,
                    v.vehicle_type as tipo,
                    v.brand as marca,
                    v.model as modello,
                    v.year as anno,
                    v.status as stato,
                    v.insurance_expiry as scadenza_assicurazione,
                    v.inspection_expiry as scadenza_revisione
                FROM vehicles v
                ORDER BY v.name";
        
        $data = $this->db->fetchAll($sql);
        
        if ($format === 'csv') {
            return $this->exportToCSV($data);
        } else {
            $this->exportToExcel($data, 'Mezzi', 'elenco_mezzi_' . date('Y-m-d') . '.xlsx');
        }
    }
    
    /**
     * Export warehouse items to Excel/CSV
     */
    public function exportWarehouse($format = 'excel') {
        $sql = "SELECT 
                    wi.id,
                    wi.code as codice,
                    wi.name as nome,
                    wi.category as categoria,
                    wi.description as descrizione,
                    wi.quantity as quantita,
                    wi.minimum_quantity as quantita_minima,
                    wi.unit as unita_misura,
                    wi.location as posizione,
                    wi.status as stato,
                    wi.notes as note
                FROM warehouse_items wi
                ORDER BY wi.category, wi.name";
        
        $data = $this->db->fetchAll($sql);
        
        if ($format === 'csv') {
            return $this->exportToCSV($data);
        } else {
            $this->exportToExcel($data, 'Magazzino', 'magazzino_' . date('Y-m-d') . '.xlsx');
        }
    }
    
    /**
     * Export structures data to Excel/CSV
     */
    public function exportStructures($format = 'excel') {
        $sql = "SELECT 
                    s.id,
                    s.name as nome,
                    s.type as tipo,
                    s.full_address as indirizzo,
                    s.latitude as latitudine,
                    s.longitude as longitudine,
                    s.owner as proprietario,
                    s.owner_contacts as contatti_proprietario,
                    s.contracts_deadlines as contratti_scadenze,
                    s.keys_codes as chiavi_codici,
                    s.notes as note
                FROM structures s
                ORDER BY s.name";
        
        $data = $this->db->fetchAll($sql);
        
        if ($format === 'csv') {
            return $this->exportToCSV($data);
        } else {
            $this->exportToExcel($data, 'Strutture', 'elenco_strutture_' . date('Y-m-d') . '.xlsx');
        }
    }
    
    /**
     * Export training courses to Excel/CSV
     */
    public function exportTraining($format = 'excel') {
        $sql = "SELECT 
                    tc.id,
                    tc.course_name as titolo,
                    tc.course_type as tipo_corso,
                    tc.start_date as data_inizio,
                    tc.end_date as data_fine,
                    tc.location as luogo,
                    tc.instructor as istruttore,
                    tc.status as stato,
                    tc.max_participants as max_partecipanti,
                    COUNT(DISTINCT tp.member_id) as partecipanti
                FROM training_courses tc
                LEFT JOIN training_participants tp ON tc.id = tp.course_id
                GROUP BY tc.id
                ORDER BY tc.start_date DESC";
        
        $data = $this->db->fetchAll($sql);
        
        if ($format === 'csv') {
            return $this->exportToCSV($data);
        } else {
            $this->exportToExcel($data, 'Formazione', 'corsi_formazione_' . date('Y-m-d') . '.xlsx');
        }
    }
    
    /**
     * Export events/interventions to Excel/CSV
     */
    public function exportEvents($format = 'excel') {
        $sql = "SELECT 
                    e.id,
                    e.title as titolo,
                    e.event_type as tipo,
                    e.start_date as data_inizio,
                    e.end_date as data_fine,
                    e.municipality as comune,
                    e.status as stato,
                    COUNT(DISTINCT i.id) as numero_interventi,
                    COUNT(DISTINCT ep.member_id) as partecipanti,
                    COALESCE(SUM(ep.hours), 0) as ore_totali
                FROM events e
                LEFT JOIN interventions i ON e.id = i.event_id
                LEFT JOIN event_participants ep ON e.id = ep.event_id
                GROUP BY e.id
                ORDER BY e.start_date DESC";
        
        $data = $this->db->fetchAll($sql);
        
        if ($format === 'csv') {
            return $this->exportToCSV($data);
        } else {
            $this->exportToExcel($data, 'Eventi', 'elenco_eventi_' . date('Y-m-d') . '.xlsx');
        }
    }
    
    /**
     * Export scheduler items to Excel/CSV
     */
    public function exportScheduler($format = 'excel') {
        $sql = "SELECT 
                    si.id,
                    si.title as titolo,
                    si.category as categoria,
                    si.due_date as data_scadenza,
                    si.priority as priorita,
                    si.status as stato,
                    si.assigned_to as assegnato_a,
                    si.description as descrizione
                FROM scheduler_items si
                ORDER BY si.due_date DESC";
        
        $data = $this->db->fetchAll($sql);
        
        if ($format === 'csv') {
            return $this->exportToCSV($data);
        } else {
            $this->exportToExcel($data, 'Scadenzario', 'scadenzario_' . date('Y-m-d') . '.xlsx');
        }
    }
}