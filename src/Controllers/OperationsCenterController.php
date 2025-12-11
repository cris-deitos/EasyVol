<?php
namespace EasyVol\Controllers;

use EasyVol\Database;

/**
 * Operations Center Controller
 * 
 * Gestisce la centrale operativa, rubrica radio e dashboard operativa
 */
class OperationsCenterController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Dashboard operativa - eventi attivi e risorse disponibili
     */
    public function getDashboard() {
        $dashboard = [];
        
        // Eventi attivi
        $sql = "SELECT e.*, 
                COUNT(DISTINCT im.member_id) as num_members,
                COUNT(DISTINCT iv.vehicle_id) as num_vehicles
                FROM events e
                LEFT JOIN interventions i ON e.id = i.event_id
                LEFT JOIN intervention_members im ON i.id = im.intervention_id
                LEFT JOIN intervention_vehicles iv ON i.id = iv.intervention_id
                WHERE e.status = 'aperto'
                GROUP BY e.id
                ORDER BY e.start_date DESC";
        $dashboard['active_events'] = $this->db->fetchAll($sql);
        
        // Radio disponibili
        $sql = "SELECT * FROM radio_directory 
                WHERE status = 'disponibile' 
                ORDER BY name";
        $dashboard['available_radios'] = $this->db->fetchAll($sql);
        
        // Mezzi disponibili
        $sql = "SELECT * FROM vehicles 
                WHERE status = 'operativo' 
                ORDER BY name";
        $dashboard['available_vehicles'] = $this->db->fetchAll($sql);
        
                // Volontari attivi e operativi
        $dashboard['available_members'] = $this->getAvailableVolunteers();
        
        return $dashboard;
    }
    
    /**
     * Get available volunteers with all necessary fields
     */
    public function getAvailableVolunteers() {
        $sql = "SELECT m.*, 
                mc.value as phone,
                COALESCE(ma.availability_type, 'available') as availability_type,
                ma.notes as availability_notes
                FROM members m
                LEFT JOIN member_contacts mc ON (m.id = mc.member_id AND mc.contact_type = 'cellulare')
                LEFT JOIN member_availability ma ON m.id = ma.member_id
                WHERE m.member_status = 'attivo' 
                AND m.volunteer_status = 'operativo'
                ORDER BY m.last_name, m.first_name
                LIMIT 50";
        
        return $this->db->fetchAll($sql);
    }
    
    // ============================================
    // RADIO DIRECTORY
    // ============================================
    
    /**
     * Lista radio con filtri
     */
    public function indexRadios($filters = [], $page = 1, $perPage = 50) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['device_type'])) {
            $where[] = "device_type = ?";
            $params[] = $filters['device_type'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(name LIKE ? OR identifier LIKE ? OR serial_number LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM radio_directory 
                WHERE $whereClause 
                ORDER BY name 
                LIMIT $perPage OFFSET $offset";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Ottieni singola radio
     */
    public function getRadio($id) {
        $sql = "SELECT * FROM radio_directory WHERE id = ?";
        $radio = $this->db->fetchOne($sql, [$id]);
        
        if (!$radio) {
            return false;
        }
        
        try {
            // Carica assegnazione corrente - use LEFT JOIN to handle assignments without member_id
            // Also select assignee_* columns for backward compatibility
            $sql = "SELECT ra.*, 
                    COALESCE(m.first_name, ra.assignee_first_name) as first_name, 
                    COALESCE(m.last_name, ra.assignee_last_name) as last_name, 
                    m.badge_number 
                    FROM radio_assignments ra
                    LEFT JOIN members m ON ra.member_id = m.id
                    WHERE ra.radio_id = ? 
                    AND ra.return_date IS NULL
                    AND ra.status = 'assegnata'
                    ORDER BY ra.assignment_date DESC
                    LIMIT 1";
            $radio['current_assignment'] = $this->db->fetchOne($sql, [$id]);
            
            // Carica storico assegnazioni
            $sql = "SELECT ra.*, 
                    COALESCE(m.first_name, ra.assignee_first_name) as first_name, 
                    COALESCE(m.last_name, ra.assignee_last_name) as last_name, 
                    m.badge_number 
                    FROM radio_assignments ra
                    LEFT JOIN members m ON ra.member_id = m.id
                    WHERE ra.radio_id = ? 
                    ORDER BY ra.assignment_date DESC
                    LIMIT 10";
            $radio['assignment_history'] = $this->db->fetchAll($sql, [$id]);
        } catch (\Exception $e) {
            // If query fails (e.g., missing columns), try simpler query
            error_log("Error loading radio assignments: " . $e->getMessage());
            $radio['current_assignment'] = null;
            $radio['assignment_history'] = [];
        }
        
        return $radio;
    }
    
    /**
     * Crea nuova radio
     */
    public function createRadio($data, $userId) {
        try {
            $sql = "INSERT INTO radio_directory (
                name, identifier, device_type, brand, model, 
                serial_number, notes, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['name'],
                $data['identifier'] ?? null,
                $data['device_type'] ?? null,
                $data['brand'] ?? null,
                $data['model'] ?? null,
                $data['serial_number'] ?? null,
                $data['notes'] ?? null,
                $data['status'] ?? 'disponibile'
            ];
            
            $this->db->execute($sql, $params);
            $radioId = $this->db->lastInsertId();
            
            // Log activity
            $this->logActivity($userId, 'operations_center', 'create_radio', $radioId, 
                "Creata radio: {$data['name']}");
            
            return $radioId;
        } catch (\Exception $e) {
            error_log("Error creating radio: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna radio
     */
    public function updateRadio($id, $data, $userId) {
        try {
            $sql = "UPDATE radio_directory SET 
                name = ?, identifier = ?, device_type = ?, brand = ?, 
                model = ?, serial_number = ?, notes = ?, status = ?,
                updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['name'],
                $data['identifier'] ?? null,
                $data['device_type'] ?? null,
                $data['brand'] ?? null,
                $data['model'] ?? null,
                $data['serial_number'] ?? null,
                $data['notes'] ?? null,
                $data['status'] ?? 'disponibile',
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            // Log activity
            $this->logActivity($userId, 'operations_center', 'update_radio', $id, 
                "Aggiornata radio: {$data['name']}");
            
            return true;
        } catch (\Exception $e) {
            error_log("Error updating radio: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina radio (soft delete)
     */
    public function deleteRadio($id, $userId) {
        try {
            // Check if radio is currently assigned
            $sql = "SELECT COUNT(*) as count FROM radio_assignments 
                    WHERE radio_id = ? AND return_date IS NULL";
            $result = $this->db->fetchOne($sql, [$id]);
            
            if ($result['count'] > 0) {
                return ['success' => false, 'message' => 'Impossibile eliminare: radio attualmente assegnata'];
            }
            
            // Get radio name for log
            $radio = $this->getRadio($id);
            
            $sql = "DELETE FROM radio_directory WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            // Log activity
            $this->logActivity($userId, 'operations_center', 'delete_radio', $id, 
                "Eliminata radio: {$radio['name']}");
            
            return ['success' => true];
        } catch (\Exception $e) {
            error_log("Error deleting radio: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione'];
        }
    }
    
    // ============================================
    // RADIO ASSIGNMENTS
    // ============================================
    
    /**
     * Assegna radio a volontario
     */
    public function assignRadio($radioId, $memberId, $userId, $notes = null) {
        try {
            $this->db->beginTransaction();
            
            // Check if radio is available
            $sql = "SELECT status FROM radio_directory WHERE id = ?";
            $radio = $this->db->fetchOne($sql, [$radioId]);
            
            if (!$radio || $radio['status'] !== 'disponibile') {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Radio non disponibile'];
            }
            
            // Get member details for assignee fields (backward compatibility)
            $sql = "SELECT first_name, last_name FROM members WHERE id = ?";
            $member = $this->db->fetchOne($sql, [$memberId]);
            
            if (!$member) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Volontario non trovato'];
            }
            
            // Create assignment with both member_id and assignee_* fields for compatibility
            $sql = "INSERT INTO radio_assignments (
                radio_id, member_id, assignee_first_name, assignee_last_name, 
                assigned_by, assignment_date, status, notes
            ) VALUES (?, ?, ?, ?, ?, NOW(), 'assegnata', ?)";
            
            $this->db->execute($sql, [
                $radioId, 
                $memberId, 
                $member['first_name'],
                $member['last_name'],
                $userId,
                $notes
            ]);
            
            // Update radio status
            $sql = "UPDATE radio_directory SET status = 'assegnata', updated_at = NOW() 
                    WHERE id = ?";
            $this->db->execute($sql, [$radioId]);
            
            $this->db->commit();
            
            // Log activity
            $this->logActivity($userId, 'operations_center', 'assign_radio', $radioId, 
                "Radio assegnata a volontario ID: $memberId");
            
            return ['success' => true];
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error assigning radio: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'assegnazione'];
        }
    }
    
    /**
     * Restituisci radio
     */
    public function returnRadio($assignmentId, $userId, $notes = null) {
        try {
            $this->db->beginTransaction();
            
            // Get assignment details
            $sql = "SELECT radio_id FROM radio_assignments WHERE id = ?";
            $assignment = $this->db->fetchOne($sql, [$assignmentId]);
            
            if (!$assignment) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Assegnazione non trovata'];
            }
            
            // Update assignment - set status to restituita, return_by, return_date
            $sql = "UPDATE radio_assignments SET 
                    return_date = NOW(), 
                    return_by = ?,
                    status = 'restituita'
                    WHERE id = ?";
            $this->db->execute($sql, [$userId, $assignmentId]);
            
            // Update radio status
            $sql = "UPDATE radio_directory SET status = 'disponibile', updated_at = NOW() 
                    WHERE id = ?";
            $this->db->execute($sql, [$assignment['radio_id']]);
            
            $this->db->commit();
            
            // Log activity - include notes in description if provided
            $description = "Radio restituita";
            if (!empty($notes)) {
                $description .= " - Note: " . substr($notes, 0, 100);
            }
            $this->logActivity($userId, 'operations_center', 'return_radio', $assignment['radio_id'], 
                $description);
            
            return ['success' => true];
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error returning radio: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante la restituzione'];
        }
    }
    
    /**
     * Scan seriale radio - cerca per numero seriale
     */
    public function scanSerial($serialNumber) {
        $sql = "SELECT * FROM radio_directory WHERE serial_number = ?";
        $radio = $this->db->fetchOne($sql, [$serialNumber]);
        
        if (!$radio) {
            return ['found' => false, 'message' => 'Radio non trovata'];
        }
        
        // Get current assignment - use LEFT JOIN and COALESCE for backward compatibility
        try {
            $sql = "SELECT ra.*, 
                    COALESCE(m.first_name, ra.assignee_first_name) as first_name, 
                    COALESCE(m.last_name, ra.assignee_last_name) as last_name, 
                    m.badge_number 
                    FROM radio_assignments ra
                    LEFT JOIN members m ON ra.member_id = m.id
                    WHERE ra.radio_id = ? 
                    AND ra.return_date IS NULL
                    AND ra.status = 'assegnata'
                    LIMIT 1";
            $assignment = $this->db->fetchOne($sql, [$radio['id']]);
        } catch (\Exception $e) {
            error_log("Error scanning serial: " . $e->getMessage());
            $assignment = null;
        }
        
        return [
            'found' => true,
            'radio' => $radio,
            'assignment' => $assignment
        ];
    }
    
    // ============================================
    // STATISTICS
    // ============================================
    
    /**
     * Statistiche radio
     */
    public function getRadioStats() {
        $stats = [];
        
        // Count by status
        $sql = "SELECT status, COUNT(*) as count 
                FROM radio_directory 
                GROUP BY status";
        $stats['by_status'] = $this->db->fetchAll($sql);
        
        // Count by device type
        $sql = "SELECT device_type, COUNT(*) as count 
                FROM radio_directory 
                WHERE device_type IS NOT NULL
                GROUP BY device_type";
        $stats['by_type'] = $this->db->fetchAll($sql);
        
        // Total radios
        $sql = "SELECT COUNT(*) as total FROM radio_directory";
        $result = $this->db->fetchOne($sql);
        $stats['total'] = $result['total'];
        
        // Currently assigned
        $sql = "SELECT COUNT(*) as count FROM radio_directory WHERE status = 'assegnata'";
        $result = $this->db->fetchOne($sql);
        $stats['assigned'] = $result['count'];
        
        return $stats;
    }
    
    /**
     * Conteggi per dashboard
     */
    public function getCounts() {
        $counts = [];
        
        // Active events
        $sql = "SELECT COUNT(*) as count FROM events WHERE status = 'aperto'";
        $result = $this->db->fetchOne($sql);
        $counts['active_events'] = $result['count'];
        
        // Available radios
        $sql = "SELECT COUNT(*) as count FROM radio_directory WHERE status = 'disponibile'";
        $result = $this->db->fetchOne($sql);
        $counts['available_radios'] = $result['count'];
        
        // Available vehicles
        $sql = "SELECT COUNT(*) as count FROM vehicles WHERE status = 'operativo'";
        $result = $this->db->fetchOne($sql);
        $counts['available_vehicles'] = $result['count'];
        
                // Available members
        $sql = "SELECT COUNT(*) as count 
                FROM members 
                WHERE member_status = 'attivo' 
                AND volunteer_status = 'operativo'";
        $result = $this->db->fetchOne($sql);
        $counts['available_members'] = $result['count'];
        
        return $counts;
    }
    
    // ============================================
    // UTILITY
    // ============================================
    
    /**
     * Log activity
     */
    private function logActivity($userId, $module, $action, $recordId, $details) {
        $sql = "INSERT INTO activity_logs (user_id, module, action, record_id, description, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $this->db->execute($sql, [$userId, $module, $action, $recordId, $details]);
    }
}