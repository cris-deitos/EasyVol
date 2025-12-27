<?php
namespace EasyVol\Controllers;

use EasyVol\Database;

/**
 * Event Controller
 * 
 * Gestisce eventi, esercitazioni e interventi
 */
class EventController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Lista eventi con filtri
     */
    public function index($filters = [], $page = 1, $perPage = 20) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['type'])) {
            $where[] = "event_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(title LIKE ? OR description LIKE ? OR location LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM events 
                WHERE $whereClause 
                ORDER BY start_date DESC 
                LIMIT $perPage OFFSET $offset";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Ottieni singolo evento
     */
    public function get($id) {
        $sql = "SELECT * FROM events WHERE id = ?";
        $event = $this->db->fetchOne($sql, [$id]);
        
        if (!$event) {
            return false;
        }
        
        // Carica interventi
        $event['interventions'] = $this->getInterventions($id);
        
        // Carica partecipanti
        $event['participants'] = $this->getParticipants($id);
        
        // Carica mezzi
        $event['vehicles'] = $this->getVehicles($id);
        
        return $event;
    }
    
    /**
     * Crea nuovo evento
     */
    public function create($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO events (
                event_type, title, description, start_date, end_date, location, status, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['event_type'],
                $data['title'],
                $data['description'] ?? null,
                $data['start_date'],
                $data['end_date'] ?? null,
                $data['location'] ?? null,
                $data['status'] ?? 'aperto',
                $userId
            ];
            
            $this->db->execute($sql, $params);
            $eventId = $this->db->lastInsertId();
            
            $this->logActivity($userId, 'event', 'create', $eventId, 'Creato nuovo evento: ' . $data['title']);
            
            $this->db->commit();
            return $eventId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore creazione evento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna evento
     */
    public function update($id, $data, $userId) {
        try {
            $sql = "UPDATE events SET
                event_type = ?, title = ?, description = ?, start_date = ?,
                end_date = ?, location = ?, status = ?, updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['event_type'],
                $data['title'],
                $data['description'] ?? null,
                $data['start_date'],
                $data['end_date'] ?? null,
                $data['location'] ?? null,
                $data['status'] ?? 'aperto',
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            $this->logActivity($userId, 'event', 'update', $id, 'Aggiornato evento');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore aggiornamento evento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni interventi di un evento
     */
    private function getInterventions($eventId) {
        $sql = "SELECT * FROM interventions WHERE event_id = ? ORDER BY start_time DESC";
        return $this->db->fetchAll($sql, [$eventId]);
    }
    
    /**
     * Ottieni partecipanti di un evento
     */
    private function getParticipants($eventId) {
        $sql = "SELECT ep.*, m.first_name, m.last_name, m.registration_number
                FROM event_participants ep
                JOIN members m ON ep.member_id = m.id
                WHERE ep.event_id = ?
                ORDER BY m.last_name, m.first_name";
        return $this->db->fetchAll($sql, [$eventId]);
    }
    
    /**
     * Ottieni mezzi di un evento
     */
    private function getVehicles($eventId) {
        $sql = "SELECT ev.*, v.name, v.license_plate, v.serial_number, v.brand, v.model, v.vehicle_type
                FROM event_vehicles ev
                JOIN vehicles v ON ev.vehicle_id = v.id
                WHERE ev.event_id = ?";
        return $this->db->fetchAll($sql, [$eventId]);
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
     * Elimina evento (soft delete)
     */
    public function delete($id, $userId) {
        try {
            // Get event details for log
            $event = $this->get($id);
            if (!$event) {
                return ['success' => false, 'message' => 'Evento non trovato'];
            }
            
            // Mark event as cancelled instead of soft delete
            $sql = "UPDATE events SET status = 'annullato' WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            // Log activity
            $this->logActivity($userId, 'events', 'delete', $id, "Eliminato evento: {$event['title']}");
            
            return ['success' => true];
        } catch (\Exception $e) {
            error_log("Errore eliminazione evento: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione'];
        }
    }
    
    /**
     * Aggiungi intervento a un evento
     */
    public function addIntervention($eventId, $data, $userId) {
        try {
            $sql = "INSERT INTO interventions (event_id, title, description, start_time, end_time, location, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $eventId,
                $data['title'],
                $data['description'] ?? null,
                $data['start_time'],
                $data['end_time'] ?? null,
                $data['location'] ?? null,
                $data['status'] ?? 'in_corso'
            ];
            
            $this->db->execute($sql, $params);
            $interventionId = $this->db->lastInsertId();
            
            $this->logActivity($userId, 'interventions', 'create', $interventionId, 'Aggiunto intervento a evento');
            
            return $interventionId;
        } catch (\Exception $e) {
            error_log("Errore aggiunta intervento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cerca membri disponibili per un evento
     */
    public function getAvailableMembers($eventId, $search = '') {
        try {
            $sql = "SELECT m.id, m.first_name, m.last_name, m.registration_number
                    FROM members m
                    WHERE m.status = 'attivo'
                    AND m.id NOT IN (SELECT member_id FROM event_participants WHERE event_id = ?)";
            
            $params = [$eventId];
            
            if (!empty($search)) {
                $sql .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.registration_number LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY m.last_name, m.first_name LIMIT 20";
            
            return $this->db->fetchAll($sql, $params);
        } catch (\Exception $e) {
            error_log("Errore ricerca membri: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Aggiungi partecipante a un evento
     */
    public function addParticipant($eventId, $memberId, $userId) {
        try {
            // Check if already exists
            $sql = "SELECT id FROM event_participants WHERE event_id = ? AND member_id = ?";
            $existing = $this->db->fetchOne($sql, [$eventId, $memberId]);
            
            if ($existing) {
                return ['error' => 'Il partecipante è già presente nell\'evento'];
            }
            
            $sql = "INSERT INTO event_participants (event_id, member_id, role, hours, notes, created_at)
                    VALUES (?, ?, NULL, 0, NULL, NOW())";
            
            $this->db->execute($sql, [$eventId, $memberId]);
            
            $this->logActivity($userId, 'event_participants', 'create', $eventId, 'Aggiunto partecipante a evento');
            
            return true;
        } catch (\Exception $e) {
            error_log("Errore aggiunta partecipante: " . $e->getMessage());
            return ['error' => 'Errore durante l\'aggiunta del partecipante'];
        }
    }
    
    /**
     * Cerca veicoli disponibili per un evento
     */
    public function getAvailableVehicles($eventId, $search = '') {
        try {
            $sql = "SELECT v.id, v.name, v.license_plate, v.serial_number, v.brand, v.model, v.vehicle_type
                    FROM vehicles v
                    WHERE v.status = 'operativo'
                    AND v.id NOT IN (SELECT vehicle_id FROM event_vehicles WHERE event_id = ?)";
            
            $params = [$eventId];
            
            if (!empty($search)) {
                $sql .= " AND (v.name LIKE ? OR v.license_plate LIKE ? OR v.serial_number LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY v.name LIMIT 20";
            
            return $this->db->fetchAll($sql, $params);
        } catch (\Exception $e) {
            error_log("Errore ricerca veicoli: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Aggiungi veicolo a un evento
     */
    public function addVehicle($eventId, $vehicleId, $userId) {
        try {
            // Check if already exists
            $sql = "SELECT id FROM event_vehicles WHERE event_id = ? AND vehicle_id = ?";
            $existing = $this->db->fetchOne($sql, [$eventId, $vehicleId]);
            
            if ($existing) {
                return ['error' => 'Il veicolo è già presente nell\'evento'];
            }
            
            $sql = "INSERT INTO event_vehicles (event_id, vehicle_id, driver_name, hours, km_traveled, notes, created_at)
                    VALUES (?, ?, NULL, 0, 0, NULL, NOW())";
            
            $this->db->execute($sql, [$eventId, $vehicleId]);
            
            $this->logActivity($userId, 'event_vehicles', 'create', $eventId, 'Aggiunto veicolo a evento');
            
            return true;
        } catch (\Exception $e) {
            error_log("Errore aggiunta veicolo: " . $e->getMessage());
            return ['error' => 'Errore durante l\'aggiunta del veicolo'];
        }
    }
}
