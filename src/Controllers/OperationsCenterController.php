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
                WHERE e.status = 'in_corso'
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
        // Get volunteers currently on-call (reperibili)
        $sql = "SELECT m.*, 
                mc.value as phone,
                ocs.id as schedule_id,
                ocs.start_datetime,
                ocs.end_datetime,
                ocs.notes as on_call_notes,
                rd.name as radio_name,
                rd.identifier as radio_identifier
                FROM on_call_schedule ocs
                JOIN members m ON ocs.member_id = m.id
                LEFT JOIN member_contacts mc ON (m.id = mc.member_id AND mc.contact_type = 'cellulare')
                LEFT JOIN radio_assignments ra ON (m.id = ra.member_id AND ra.status = 'assegnata' AND ra.return_date IS NULL)
                LEFT JOIN radio_directory rd ON ra.radio_id = rd.id
                WHERE m.member_status = 'attivo'
                AND ocs.start_datetime <= NOW()
                AND ocs.end_datetime >= NOW()
                ORDER BY m.last_name, m.first_name";
        
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
            $where[] = "(name LIKE ? OR identifier LIKE ? OR dmr_id LIKE ? OR serial_number LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
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
            // Check if junior_member_id column exists
            $checkColumnSql = "SHOW COLUMNS FROM radio_assignments LIKE 'junior_member_id'";
            $columnExists = $this->db->fetchOne($checkColumnSql);
            
            if ($columnExists) {
                // New schema with junior_members support
                // Carica assegnazione corrente - handle both members and junior_members
                $sql = "SELECT ra.*, 
                        COALESCE(m.first_name, jm.first_name, ra.assignee_first_name) as first_name, 
                        COALESCE(m.last_name, jm.last_name, ra.assignee_last_name) as last_name, 
                        COALESCE(m.badge_number, jm.registration_number) as badge_number,
                        COALESCE(mc.value, ra.assignee_phone) as phone_number,
                        ra.assignee_organization as organization,
                        CASE 
                            WHEN ra.member_id IS NULL AND ra.junior_member_id IS NULL THEN 1 
                            ELSE 0 
                        END as is_external,
                        ra.notes as assignment_notes,
                        ra.assignee_type
                        FROM radio_assignments ra
                        LEFT JOIN members m ON ra.member_id = m.id
                        LEFT JOIN junior_members jm ON ra.junior_member_id = jm.id
                        LEFT JOIN member_contacts mc ON (m.id = mc.member_id AND mc.contact_type = 'cellulare')
                        WHERE ra.radio_id = ? 
                        AND ra.return_date IS NULL
                        AND ra.status = 'assegnata'
                        ORDER BY ra.assignment_date DESC
                        LIMIT 1";
                $radio['current_assignment'] = $this->db->fetchOne($sql, [$id]);
                
                // Carica storico assegnazioni
                $sql = "SELECT ra.*, 
                        COALESCE(m.first_name, jm.first_name, ra.assignee_first_name) as first_name, 
                        COALESCE(m.last_name, jm.last_name, ra.assignee_last_name) as last_name, 
                        COALESCE(m.badge_number, jm.registration_number) as badge_number,
                        COALESCE(mc.value, ra.assignee_phone) as phone_number,
                        ra.assignee_organization as organization,
                        CASE 
                            WHEN ra.member_id IS NULL AND ra.junior_member_id IS NULL THEN 1 
                            ELSE 0 
                        END as is_external,
                        ra.notes as assignment_notes,
                        ra.assignee_type
                        FROM radio_assignments ra
                        LEFT JOIN members m ON ra.member_id = m.id
                        LEFT JOIN junior_members jm ON ra.junior_member_id = jm.id
                        LEFT JOIN member_contacts mc ON (m.id = mc.member_id AND mc.contact_type = 'cellulare')
                        WHERE ra.radio_id = ? 
                        ORDER BY ra.assignment_date DESC
                        LIMIT 10";
                $radio['assignment_history'] = $this->db->fetchAll($sql, [$id]);
            } else {
                // Old schema - only members table
                $sql = "SELECT ra.*, 
                        COALESCE(m.first_name, ra.assignee_first_name) as first_name, 
                        COALESCE(m.last_name, ra.assignee_last_name) as last_name, 
                        m.badge_number,
                        COALESCE(mc.value, ra.assignee_phone) as phone_number,
                        ra.assignee_organization as organization,
                        ra.member_id IS NULL as is_external,
                        ra.notes as assignment_notes
                        FROM radio_assignments ra
                        LEFT JOIN members m ON ra.member_id = m.id
                        LEFT JOIN member_contacts mc ON (m.id = mc.member_id AND mc.contact_type = 'cellulare')
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
                        m.badge_number,
                        COALESCE(mc.value, ra.assignee_phone) as phone_number,
                        ra.assignee_organization as organization,
                        ra.member_id IS NULL as is_external,
                        ra.notes as assignment_notes
                        FROM radio_assignments ra
                        LEFT JOIN members m ON ra.member_id = m.id
                        LEFT JOIN member_contacts mc ON (m.id = mc.member_id AND mc.contact_type = 'cellulare')
                        WHERE ra.radio_id = ? 
                        ORDER BY ra.assignment_date DESC
                        LIMIT 10";
                $radio['assignment_history'] = $this->db->fetchAll($sql, [$id]);
            }
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
                name, identifier, dmr_id, device_type, brand, model, 
                serial_number, notes, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['name'],
                $data['identifier'] ?? null,
                $data['dmr_id'] ?? null,
                $data['device_type'] ?? null,
                $data['brand'] ?? null,
                $data['model'] ?? null,
                $data['serial_number'] ?? null,
                $data['notes'] ?? null,
                $data['status'] ?? 'disponibile'
            ];
            
            $this->db->execute($sql, $params);
            $radioId = $this->db->lastInsertId();
            
            // Log activity with full details
            $description = "Creata nuova radio: {$data['name']}. Dettagli: " . 
                          json_encode($data, JSON_UNESCAPED_UNICODE);
            $this->logActivity($userId, 'radio', 'create', $radioId, $description);
            
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
            // Get old data before update
            $oldData = $this->getRadio($id);
            
            if (!$oldData) {
                return false; // Radio not found
            }
            
            $sql = "UPDATE radio_directory SET 
                name = ?, identifier = ?, dmr_id = ?, device_type = ?, brand = ?, 
                model = ?, serial_number = ?, notes = ?, status = ?,
                updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['name'],
                $data['identifier'] ?? null,
                $data['dmr_id'] ?? null,
                $data['device_type'] ?? null,
                $data['brand'] ?? null,
                $data['model'] ?? null,
                $data['serial_number'] ?? null,
                $data['notes'] ?? null,
                $data['status'] ?? 'disponibile',
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            // Log activity with before/after data
            $changes = [];
            foreach ($data as $key => $value) {
                if (isset($oldData[$key]) && $oldData[$key] != $value) {
                    $changes[$key] = ['da' => $oldData[$key], 'a' => $value];
                }
            }
            $description = "Aggiornata radio: {$data['name']}. Modifiche: " . 
                          json_encode($changes, JSON_UNESCAPED_UNICODE);
            $this->logActivity($userId, 'radio', 'update', $id, $description);
            
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
            
            // Get radio data for log (BEFORE deletion)
            $radio = $this->getRadio($id);
            
            if (!$radio) {
                return ['success' => false, 'message' => 'Radio non trovata'];
            }
            
            // Create detailed description of deleted data
            $deletedData = [
                'id' => $radio['id'],
                'name' => $radio['name'],
                'identifier' => $radio['identifier'] ?? '',
                'device_type' => $radio['device_type'] ?? '',
                'brand' => $radio['brand'] ?? '',
                'model' => $radio['model'] ?? '',
                'serial_number' => $radio['serial_number'] ?? '',
                'dmr_id' => $radio['dmr_id'] ?? '',
                'notes' => $radio['notes'] ?? '',
                'status' => $radio['status']
            ];
            
            $sql = "DELETE FROM radio_directory WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            // Log activity with full deleted data
            $description = "Eliminata radio: {$radio['name']}. Dati completi eliminati: " . 
                          json_encode($deletedData, JSON_UNESCAPED_UNICODE);
            $this->logActivity($userId, 'radio', 'delete', $id, $description);
            
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
    public function assignRadio($radioId, $memberId, $userId, $notes = null, $memberType = 'member') {
        try {
            $this->db->beginTransaction();
            
            // Check if radio is available
            $sql = "SELECT status FROM radio_directory WHERE id = ?";
            $radio = $this->db->fetchOne($sql, [$radioId]);
            
            if (!$radio || $radio['status'] !== 'disponibile') {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Radio non disponibile'];
            }
            
            // Get member details based on member type
            if ($memberType === 'cadet') {
                $sql = "SELECT first_name, last_name FROM junior_members WHERE id = ? AND member_status = 'attivo'";
                $member = $this->db->fetchOne($sql, [$memberId]);
                $assigneeType = 'cadet';
                $assigneeLabel = 'cadetto';
            } else {
                $sql = "SELECT first_name, last_name FROM members WHERE id = ? AND member_status = 'attivo'";
                $member = $this->db->fetchOne($sql, [$memberId]);
                $assigneeType = 'member';
                $assigneeLabel = 'volontario';
            }
            
            if (!$member) {
                $this->db->rollBack();
                return ['success' => false, 'message' => ucfirst($assigneeLabel) . ' non trovato o non attivo'];
            }
            
            // Check if assignee_type column exists in the database
            $checkColumnSql = "SHOW COLUMNS FROM radio_assignments LIKE 'assignee_type'";
            $columnExists = $this->db->fetchOne($checkColumnSql);
            
            if ($columnExists) {
                // New schema with assignee_type support
                if ($memberType === 'cadet') {
                    $sql = "INSERT INTO radio_assignments (
                        radio_id, junior_member_id, assignee_type, assignee_first_name, assignee_last_name, 
                        assigned_by, assignment_date, status, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'assegnata', ?)";
                    
                    $this->db->execute($sql, [
                        $radioId, 
                        $memberId,
                        $assigneeType,
                        $member['first_name'],
                        $member['last_name'],
                        $userId,
                        $notes
                    ]);
                } else {
                    $sql = "INSERT INTO radio_assignments (
                        radio_id, member_id, assignee_type, assignee_first_name, assignee_last_name, 
                        assigned_by, assignment_date, status, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'assegnata', ?)";
                    
                    $this->db->execute($sql, [
                        $radioId, 
                        $memberId,
                        $assigneeType,
                        $member['first_name'],
                        $member['last_name'],
                        $userId,
                        $notes
                    ]);
                }
            } else {
                // Old schema - only supports members table
                if ($memberType === 'cadet') {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Database non aggiornato per supportare cadetti. Eseguire migration.'];
                }
                
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
            }
            
            // Update radio status
            $sql = "UPDATE radio_directory SET status = 'assegnata', updated_at = NOW() 
                    WHERE id = ?";
            $this->db->execute($sql, [$radioId]);
            
            $this->db->commit();
            
            // Log activity with detailed information
            $radioInfo = $this->getRadio($radioId);
            $description = "Radio '{$radioInfo['name']}' (ID: $radioId) assegnata a $assigneeLabel: " . 
                          "{$member['first_name']} {$member['last_name']} (ID: $memberId, Tipo: $assigneeType)";
            if ($notes) {
                $description .= ". Note: $notes";
            }
            $this->logActivity($userId, 'radio', 'assign', $radioId, $description);
            
            return ['success' => true];
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error assigning radio: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'assegnazione: ' . $e->getMessage()];
        }
    }
    
    /**
     * Assegna radio a personale esterno
     * 
     * @param int $radioId ID radio
     * @param array $externalData Dati personale esterno (last_name, first_name, organization, phone)
     * @param int $userId ID utente che assegna
     * @param string|null $notes Note assegnazione
     * @return array
     */
    public function assignRadioToExternal($radioId, $externalData, $userId, $notes = null) {
        try {
            $this->db->beginTransaction();
            
            // Check if radio is available
            $sql = "SELECT status FROM radio_directory WHERE id = ?";
            $radio = $this->db->fetchOne($sql, [$radioId]);
            
            if (!$radio || $radio['status'] !== 'disponibile') {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Radio non disponibile'];
            }
            
            // Create assignment with external personnel data
            // member_id is NULL for external personnel
            $sql = "INSERT INTO radio_assignments (
                radio_id, member_id, assignee_first_name, assignee_last_name, 
                assignee_phone, assignee_organization, 
                assigned_by, assignment_date, status, notes
            ) VALUES (?, NULL, ?, ?, ?, ?, ?, NOW(), 'assegnata', ?)";
            
            $this->db->execute($sql, [
                $radioId,
                $externalData['first_name'],
                $externalData['last_name'],
                $externalData['phone'],
                $externalData['organization'],
                $userId,
                $notes
            ]);
            
            // Update radio status
            $sql = "UPDATE radio_directory SET status = 'assegnata', updated_at = NOW() 
                    WHERE id = ?";
            $this->db->execute($sql, [$radioId]);
            
            $this->db->commit();
            
            // Log activity with detailed information
            $radioInfo = $this->getRadio($radioId);
            $description = "Radio '{$radioInfo['name']}' (ID: $radioId) assegnata a personale esterno: " . 
                          "{$externalData['first_name']} {$externalData['last_name']} " .
                          "(Organizzazione: {$externalData['organization']}, Telefono: {$externalData['phone']})";
            if ($notes) {
                $description .= ". Note: $notes";
            }
            $this->logActivity($userId, 'radio', 'assign_external', $radioId, $description);
            
            return ['success' => true];
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error assigning radio to external: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'assegnazione: ' . $e->getMessage()];
        }
    }
    
    /**
     * Restituisci radio
     */
    public function returnRadio($assignmentId, $userId, $notes = null) {
        try {
            $this->db->beginTransaction();
            
            // Get full assignment details for logging
            $sql = "SELECT ra.*, rd.name as radio_name, rd.identifier,
                    COALESCE(m.first_name, ra.assignee_first_name) as assignee_first_name,
                    COALESCE(m.last_name, ra.assignee_last_name) as assignee_last_name,
                    ra.assignee_organization
                    FROM radio_assignments ra
                    LEFT JOIN radio_directory rd ON ra.radio_id = rd.id
                    LEFT JOIN members m ON ra.member_id = m.id
                    WHERE ra.id = ?";
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
            
            // Log activity with full details
            $assigneeName = "{$assignment['assignee_first_name']} {$assignment['assignee_last_name']}";
            if ($assignment['assignee_organization']) {
                $assigneeName .= " ({$assignment['assignee_organization']})";
            }
            $description = "Radio '{$assignment['radio_name']}' (ID: {$assignment['radio_id']}) " . 
                          "restituita da: $assigneeName. " .
                          "Assegnazione ID: $assignmentId";
            if ($notes) {
                $description .= ". Note rientro: $notes";
            }
            $this->logActivity($userId, 'radio', 'return', $assignment['radio_id'], $description);
            
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
        $sql = "SELECT COUNT(*) as count FROM events WHERE status = 'in_corso'";
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