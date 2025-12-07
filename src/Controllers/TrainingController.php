<?php
namespace EasyVol\Controllers;

use EasyVol\Database;

/**
 * Training Controller
 * 
 * Gestisce corsi di formazione, partecipanti e presenze
 */
class TrainingController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Lista corsi con filtri
     */
    public function index($filters = [], $page = 1, $perPage = 20) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['type'])) {
            $where[] = "course_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(course_name LIKE ? OR description LIKE ? OR instructor LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT tc.*, 
                    (SELECT COUNT(*) FROM training_participants WHERE course_id = tc.id) as participant_count
                FROM training_courses tc
                WHERE $whereClause 
                ORDER BY tc.start_date DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Conta corsi con filtri
     */
    public function count($filters = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['type'])) {
            $where[] = "course_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(course_name LIKE ? OR description LIKE ? OR instructor LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) as total FROM training_courses WHERE $whereClause";
        $result = $this->db->fetchOne($sql, $params);
        
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Ottieni statistiche corsi
     */
    public function getStats() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pianificato' THEN 1 ELSE 0 END) as pianificati,
                    SUM(CASE WHEN status = 'in_corso' THEN 1 ELSE 0 END) as in_corso,
                    SUM(CASE WHEN status = 'completato' THEN 1 ELSE 0 END) as completati
                FROM training_courses";
        
        return $this->db->fetchOne($sql);
    }
    
    /**
     * Ottieni singolo corso
     */
    public function get($id) {
        $sql = "SELECT * FROM training_courses WHERE id = ?";
        $course = $this->db->fetchOne($sql, [$id]);
        
        if (!$course) {
            return false;
        }
        
        // Carica partecipanti
        $course['participants'] = $this->getParticipants($id);
        
        // Calcola statistiche
        $course['stats'] = $this->getCourseStats($id);
        
        return $course;
    }
    
    /**
     * Crea nuovo corso
     */
    public function create($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO training_courses (
                course_name, course_type, description, location, 
                start_date, end_date, instructor, max_participants, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['course_name'],
                $data['course_type'] ?? null,
                $data['description'] ?? null,
                $data['location'] ?? null,
                $data['start_date'],
                $data['end_date'] ?? null,
                $data['instructor'] ?? null,
                $data['max_participants'] ?? null,
                $data['status'] ?? 'pianificato'
            ];
            
            $this->db->execute($sql, $params);
            $courseId = $this->db->lastInsertId();
            
            $this->logActivity($userId, 'training', 'create', $courseId, 'Creato nuovo corso: ' . $data['course_name']);
            
            $this->db->commit();
            return $courseId;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Errore creazione corso: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna corso
     */
    public function update($id, $data, $userId) {
        try {
            $sql = "UPDATE training_courses SET
                course_name = ?, course_type = ?, description = ?, location = ?,
                start_date = ?, end_date = ?, instructor = ?, max_participants = ?,
                status = ?, updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['course_name'],
                $data['course_type'] ?? null,
                $data['description'] ?? null,
                $data['location'] ?? null,
                $data['start_date'],
                $data['end_date'] ?? null,
                $data['instructor'] ?? null,
                $data['max_participants'] ?? null,
                $data['status'] ?? 'pianificato',
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            $this->logActivity($userId, 'training', 'update', $id, 'Aggiornato corso');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore aggiornamento corso: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina corso (soft delete)
     */
    public function delete($id, $userId) {
        try {
            // Verifica se ci sono partecipanti
            $sql = "SELECT COUNT(*) as count FROM training_participants WHERE course_id = ?";
            $result = $this->db->fetchOne($sql, [$id]);
            
            if ($result && $result['count'] > 0) {
                return ['error' => 'Impossibile eliminare: il corso ha partecipanti registrati'];
            }
            
            // Elimina il corso
            $sql = "DELETE FROM training_courses WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            $this->logActivity($userId, 'training', 'delete', $id, 'Eliminato corso');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore eliminazione corso: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni partecipanti di un corso
     */
    private function getParticipants($courseId) {
        $sql = "SELECT tp.*, m.first_name, m.last_name, m.registration_number
                FROM training_participants tp
                JOIN members m ON tp.member_id = m.id
                WHERE tp.course_id = ?
                ORDER BY m.last_name, m.first_name";
        return $this->db->fetchAll($sql, [$courseId]);
    }
    
    /**
     * Aggiungi partecipante a corso
     */
    public function addParticipant($courseId, $memberId, $userId) {
        try {
            // Verifica se già iscritto
            $sql = "SELECT id FROM training_participants WHERE course_id = ? AND member_id = ?";
            $existing = $this->db->fetchOne($sql, [$courseId, $memberId]);
            
            if ($existing) {
                return ['error' => 'Il socio è già iscritto al corso'];
            }
            
            // Verifica posti disponibili
            $sql = "SELECT max_participants, 
                    (SELECT COUNT(*) FROM training_participants WHERE course_id = ?) as current_count
                    FROM training_courses WHERE id = ?";
            $course = $this->db->fetchOne($sql, [$courseId, $courseId]);
            
            if ($course['max_participants'] && $course['current_count'] >= $course['max_participants']) {
                return ['error' => 'Corso completo: numero massimo di partecipanti raggiunto'];
            }
            
            // Aggiungi partecipante
            $sql = "INSERT INTO training_participants (course_id, member_id, registration_date, attendance_status)
                    VALUES (?, ?, NOW(), 'iscritto')";
            $this->db->execute($sql, [$courseId, $memberId]);
            
            $this->logActivity($userId, 'training', 'add_participant', $courseId, "Aggiunto partecipante ID: $memberId");
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore aggiunta partecipante: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna stato partecipante
     */
    public function updateParticipant($participantId, $data, $userId) {
        try {
            $sql = "UPDATE training_participants SET
                attendance_status = ?, final_grade = ?, certificate_issued = ?, certificate_file = ?
                WHERE id = ?";
            
            $params = [
                $data['attendance_status'] ?? 'iscritto',
                $data['final_grade'] ?? null,
                isset($data['certificate_issued']) ? (int)$data['certificate_issued'] : 0,
                $data['certificate_file'] ?? null,
                $participantId
            ];
            
            $this->db->execute($sql, $params);
            
            $this->logActivity($userId, 'training', 'update_participant', $participantId, 'Aggiornato partecipante');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore aggiornamento partecipante: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rimuovi partecipante
     */
    public function removeParticipant($participantId, $userId) {
        try {
            $sql = "DELETE FROM training_participants WHERE id = ?";
            $this->db->execute($sql, [$participantId]);
            
            $this->logActivity($userId, 'training', 'remove_participant', $participantId, 'Rimosso partecipante');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore rimozione partecipante: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni statistiche corso
     */
    private function getCourseStats($courseId) {
        $sql = "SELECT 
                COUNT(*) as total_partecipanti,
                SUM(CASE WHEN attendance_status = 'presente' THEN 1 ELSE 0 END) as presenti,
                SUM(CASE WHEN attendance_status = 'assente' THEN 1 ELSE 0 END) as assenti,
                SUM(CASE WHEN certificate_issued = 1 THEN 1 ELSE 0 END) as certificati_rilasciati
                FROM training_participants
                WHERE course_id = ?";
        
        return $this->db->fetchOne($sql, [$courseId]);
    }
    
    /**
     * Registra presenza
     */
    public function recordAttendance($courseId, $memberId, $date, $present, $userId, $notes = null) {
        try {
            // Verifica se già registrata
            $sql = "SELECT id FROM training_attendance 
                    WHERE course_id = ? AND member_id = ? AND date = ?";
            $existing = $this->db->fetchOne($sql, [$courseId, $memberId, $date]);
            
            if ($existing) {
                // Aggiorna
                $sql = "UPDATE training_attendance SET present = ?, notes = ? 
                        WHERE id = ?";
                $this->db->execute($sql, [$present, $notes, $existing['id']]);
            } else {
                // Inserisci
                $sql = "INSERT INTO training_attendance (course_id, member_id, date, present, notes)
                        VALUES (?, ?, ?, ?, ?)";
                $this->db->execute($sql, [$courseId, $memberId, $date, $present, $notes]);
            }
            
            $this->logActivity($userId, 'training', 'record_attendance', $courseId, 
                "Registrata presenza per socio ID: $memberId");
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore registrazione presenza: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni presenze per un corso
     */
    public function getAttendance($courseId, $date = null) {
        $sql = "SELECT ta.*, m.first_name, m.last_name, m.registration_number
                FROM training_attendance ta
                JOIN members m ON ta.member_id = m.id
                WHERE ta.course_id = ?";
        
        $params = [$courseId];
        
        if ($date) {
            $sql .= " AND ta.date = ?";
            $params[] = $date;
        }
        
        $sql .= " ORDER BY ta.date DESC, m.last_name, m.first_name";
        
        return $this->db->fetchAll($sql, $params);
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
