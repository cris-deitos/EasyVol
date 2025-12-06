<?php
namespace EasyVol\Controllers;

use EasyVol\Database;

/**
 * Meeting Controller
 * 
 * Gestisce riunioni, assemblee e verbali
 */
class MeetingController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Lista riunioni con filtri
     */
    public function index($filters = [], $page = 1, $perPage = 20) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['type'])) {
            $where[] = "meeting_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(title LIKE ? OR location LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM meetings 
                WHERE $whereClause 
                ORDER BY meeting_date DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Ottieni singola riunione
     */
    public function get($id) {
        $sql = "SELECT * FROM meetings WHERE id = ?";
        $meeting = $this->db->fetchOne($sql, [$id]);
        
        if (!$meeting) {
            return false;
        }
        
        // Carica partecipanti
        $meeting['participants'] = $this->getParticipants($id);
        
        // Carica ordine del giorno
        $meeting['agenda'] = $this->getAgenda($id);
        
        return $meeting;
    }
    
    /**
     * Crea nuova riunione
     */
    public function create($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO meetings (
                meeting_type, title, meeting_date, location, convocator, description, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['meeting_type'],
                $data['title'],
                $data['meeting_date'],
                $data['location'] ?? null,
                $data['convocator'] ?? null,
                $data['description'] ?? null
            ];
            
            $this->db->execute($sql, $params);
            $meetingId = $this->db->lastInsertId();
            
            $this->logActivity($userId, 'meeting', 'create', $meetingId, 'Creata nuova riunione: ' . $data['title']);
            
            $this->db->commit();
            return $meetingId;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Errore creazione riunione: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna riunione
     */
    public function update($id, $data, $userId) {
        try {
            $sql = "UPDATE meetings SET
                meeting_type = ?, title = ?, meeting_date = ?, location = ?,
                convocator = ?, description = ?, updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['meeting_type'],
                $data['title'],
                $data['meeting_date'],
                $data['location'] ?? null,
                $data['convocator'] ?? null,
                $data['description'] ?? null,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            $this->logActivity($userId, 'meeting', 'update', $id, 'Aggiornata riunione');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore aggiornamento riunione: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni partecipanti riunione
     */
    private function getParticipants($meetingId) {
        $sql = "SELECT mp.*, m.first_name, m.last_name, m.registration_number
                FROM meeting_participants mp
                JOIN members m ON mp.member_id = m.id
                WHERE mp.meeting_id = ?
                ORDER BY m.last_name, m.first_name";
        return $this->db->fetchAll($sql, [$meetingId]);
    }
    
    /**
     * Ottieni ordine del giorno
     */
    private function getAgenda($meetingId) {
        $sql = "SELECT * FROM meeting_agenda WHERE meeting_id = ? ORDER BY id";
        return $this->db->fetchAll($sql, [$meetingId]);
    }
    
    /**
     * Registra attività nel log
     */
    private function logActivity($userId, $module, $action, $recordId, $details) {
        try {
            $sql = "INSERT INTO activity_logs 
                    (user_id, module, action, record_id, details, ip_address, user_agent, created_at) 
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
     * Elimina riunione
     */
    public function delete($id, $userId) {
        try {
            // Get meeting details for log
            $meeting = $this->get($id);
            if (!$meeting) {
                return ['success' => false, 'message' => 'Riunione non trovata'];
            }
            
            $this->db->beginTransaction();
            
            // Delete related records
            $sql = "DELETE FROM meeting_participants WHERE meeting_id = ?";
            $this->db->execute($sql, [$id]);
            
            $sql = "DELETE FROM meeting_agenda WHERE meeting_id = ?";
            $this->db->execute($sql, [$id]);
            
            $sql = "DELETE FROM meeting_attachments WHERE meeting_id = ?";
            $this->db->execute($sql, [$id]);
            
            // Delete meeting
            $sql = "DELETE FROM meetings WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            $this->db->commit();
            
            // Log activity
            $this->logActivity($userId, 'meetings', 'delete', $id, "Eliminata riunione: {$meeting['title']}");
            
            return ['success' => true];
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore eliminazione riunione: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione'];
        }
    }
}
