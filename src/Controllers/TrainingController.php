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
                LIMIT $perPage OFFSET $offset";
        
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
        
        // Carica sessioni
        $course['sessions'] = $this->getSessions($id);
        
        // Calcola statistiche
        $course['stats'] = $this->getCourseStats($id);
        
        // Calcola ore totali
        $course['total_hours'] = $this->getTotalCourseHours($id);
        
        return $course;
    }
    
    /**
     * Crea nuovo corso
     */
    public function create($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO training_courses (
                course_name, course_type, sspc_course_code, sspc_edition_code, description, location, 
                start_date, end_date, instructor, max_participants, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['course_name'],
                $data['course_type'] ?? null,
                $data['sspc_course_code'] ?? null,
                $data['sspc_edition_code'] ?? null,
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
            $this->db->rollBack();
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
                course_name = ?, course_type = ?, sspc_course_code = ?, sspc_edition_code = ?, description = ?, location = ?,
                start_date = ?, end_date = ?, instructor = ?, max_participants = ?,
                status = ?, updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['course_name'],
                $data['course_type'] ?? null,
                $data['sspc_course_code'] ?? null,
                $data['sspc_edition_code'] ?? null,
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
                attendance_status = ?, final_grade = ?, 
                exam_passed = ?, exam_score = ?,
                certificate_issued = ?, certificate_file = ?
                WHERE id = ?";
            
            $examPassed = null;
            if (isset($data['exam_passed']) && $data['exam_passed'] !== '') {
                $examPassed = (int)$data['exam_passed'];
            }
            
            $examScore = null;
            if (isset($data['exam_score']) && $data['exam_score'] !== '') {
                $examScore = max(1, min(10, (int)$data['exam_score']));
            }
            
            $params = [
                $data['attendance_status'] ?? 'iscritto',
                $data['final_grade'] ?? null,
                $examPassed,
                $examScore,
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
     * Ottieni singolo partecipante
     */
    public function getParticipant($participantId) {
        $sql = "SELECT tp.*, m.first_name, m.last_name, m.registration_number
                FROM training_participants tp
                JOIN members m ON tp.member_id = m.id
                WHERE tp.id = ?";
        return $this->db->fetchOne($sql, [$participantId]);
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
    
    // ===== SESSION MANAGEMENT =====
    
    /**
     * Ottieni sessioni di un corso
     */
    public function getSessions($courseId) {
        $sql = "SELECT * FROM training_sessions 
                WHERE course_id = ? 
                ORDER BY session_date ASC, start_time ASC";
        return $this->db->fetchAll($sql, [$courseId]);
    }
    
    /**
     * Ottieni singola sessione
     */
    public function getSession($sessionId) {
        $sql = "SELECT * FROM training_sessions WHERE id = ?";
        return $this->db->fetchOne($sql, [$sessionId]);
    }
    
    /**
     * Crea nuova sessione
     */
    public function createSession($courseId, $data, $userId) {
        try {
            $sql = "INSERT INTO training_sessions (course_id, session_date, start_time, end_time, description)
                    VALUES (?, ?, ?, ?, ?)";
            
            $params = [
                $courseId,
                $data['session_date'],
                $data['start_time'],
                $data['end_time'],
                $data['description'] ?? null
            ];
            
            $this->db->execute($sql, $params);
            $sessionId = $this->db->lastInsertId();
            
            $this->logActivity($userId, 'training', 'create_session', $courseId, 
                "Creata sessione ID: $sessionId per il " . $data['session_date']);
            
            return $sessionId;
            
        } catch (\Exception $e) {
            error_log("Errore creazione sessione: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna sessione
     */
    public function updateSession($sessionId, $data, $userId) {
        try {
            $sql = "UPDATE training_sessions SET 
                    session_date = ?, start_time = ?, end_time = ?, description = ?
                    WHERE id = ?";
            
            $params = [
                $data['session_date'],
                $data['start_time'],
                $data['end_time'],
                $data['description'] ?? null,
                $sessionId
            ];
            
            $this->db->execute($sql, $params);
            
            $this->logActivity($userId, 'training', 'update_session', $sessionId, 'Aggiornata sessione');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore aggiornamento sessione: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina sessione
     */
    public function deleteSession($sessionId, $userId) {
        try {
            $sql = "DELETE FROM training_sessions WHERE id = ?";
            $this->db->execute($sql, [$sessionId]);
            
            $this->logActivity($userId, 'training', 'delete_session', $sessionId, 'Eliminata sessione');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore eliminazione sessione: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calcola durata sessione in ore
     */
    public function getSessionDuration($sessionId) {
        $session = $this->getSession($sessionId);
        if (!$session) {
            return 0;
        }
        
        $start = strtotime($session['start_time']);
        $end = strtotime($session['end_time']);
        
        return round(($end - $start) / 3600, 2);
    }
    
    /**
     * Calcola ore totali del corso
     */
    public function getTotalCourseHours($courseId) {
        $sql = "SELECT SUM(
                    TIMESTAMPDIFF(MINUTE, start_time, end_time) / 60
                ) as total_hours
                FROM training_sessions
                WHERE course_id = ?";
        
        $result = $this->db->fetchOne($sql, [$courseId]);
        return $result ? round((float)$result['total_hours'], 2) : 0;
    }
    
    // ===== ENHANCED ATTENDANCE MANAGEMENT =====
    
    /**
     * Registra presenza per una sessione
     */
    public function recordSessionAttendance($sessionId, $memberId, $present, $userId, $notes = null) {
        try {
            $session = $this->getSession($sessionId);
            if (!$session) {
                return ['error' => 'Sessione non trovata'];
            }
            
            $courseId = $session['course_id'];
            $date = $session['session_date'];
            $hoursAttended = $present ? $this->getSessionDuration($sessionId) : 0;
            
            // Verifica se già registrata
            $sql = "SELECT id FROM training_attendance 
                    WHERE session_id = ? AND member_id = ?";
            $existing = $this->db->fetchOne($sql, [$sessionId, $memberId]);
            
            if ($existing) {
                // Aggiorna
                $sql = "UPDATE training_attendance SET present = ?, hours_attended = ?, notes = ? 
                        WHERE id = ?";
                $this->db->execute($sql, [$present, $hoursAttended, $notes, $existing['id']]);
            } else {
                // Inserisci
                $sql = "INSERT INTO training_attendance 
                        (course_id, session_id, member_id, date, present, hours_attended, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $this->db->execute($sql, [$courseId, $sessionId, $memberId, $date, $present, $hoursAttended, $notes]);
            }
            
            // Aggiorna totali nel partecipante
            $this->updateParticipantHours($courseId, $memberId);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore registrazione presenza sessione: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni presenze per una sessione
     */
    public function getSessionAttendance($sessionId) {
        $sql = "SELECT ta.*, m.first_name, m.last_name, m.registration_number
                FROM training_attendance ta
                JOIN members m ON ta.member_id = m.id
                WHERE ta.session_id = ?
                ORDER BY m.last_name, m.first_name";
        
        return $this->db->fetchAll($sql, [$sessionId]);
    }
    
    /**
     * Ottieni partecipanti con stato presenza per una sessione
     */
    public function getParticipantsWithSessionAttendance($courseId, $sessionId) {
        $sql = "SELECT tp.*, m.first_name, m.last_name, m.registration_number,
                       COALESCE(ta.present, 0) as session_present,
                       ta.notes as session_notes
                FROM training_participants tp
                JOIN members m ON tp.member_id = m.id
                LEFT JOIN training_attendance ta ON ta.member_id = tp.member_id 
                    AND ta.session_id = ?
                WHERE tp.course_id = ?
                ORDER BY m.last_name, m.first_name";
        
        return $this->db->fetchAll($sql, [$sessionId, $courseId]);
    }
    
    /**
     * Aggiorna ore totali partecipante
     */
    private function updateParticipantHours($courseId, $memberId) {
        try {
            // Calcola ore totali del corso
            $totalCourseHours = $this->getTotalCourseHours($courseId);
            
            // Calcola ore presenti
            $sql = "SELECT COALESCE(SUM(hours_attended), 0) as hours_attended
                    FROM training_attendance
                    WHERE course_id = ? AND member_id = ? AND present = 1";
            $result = $this->db->fetchOne($sql, [$courseId, $memberId]);
            $hoursAttended = $result ? (float)$result['hours_attended'] : 0;
            
            // Calcola ore assenti
            $hoursAbsent = max(0, $totalCourseHours - $hoursAttended);
            
            // Aggiorna partecipante
            $sql = "UPDATE training_participants 
                    SET total_hours_attended = ?, total_hours_absent = ?
                    WHERE course_id = ? AND member_id = ?";
            $this->db->execute($sql, [$hoursAttended, $hoursAbsent, $courseId, $memberId]);
            
        } catch (\Exception $e) {
            error_log("Errore aggiornamento ore partecipante: " . $e->getMessage());
        }
    }
    
    /**
     * Ricalcola ore per tutti i partecipanti di un corso
     */
    public function recalculateAllParticipantHours($courseId) {
        $participants = $this->getParticipantsPublic($courseId);
        foreach ($participants as $participant) {
            $this->updateParticipantHours($courseId, $participant['member_id']);
        }
    }
    
    /**
     * Ottieni partecipanti di un corso (public version)
     */
    public function getParticipantsPublic($courseId) {
        return $this->getParticipants($courseId);
    }
    
    /**
     * Ottieni statistiche partecipante
     */
    public function getParticipantStats($participantId) {
        $sql = "SELECT tp.*, 
                       m.first_name, m.last_name, m.registration_number,
                       (SELECT COUNT(*) FROM training_sessions WHERE course_id = tp.course_id) as total_sessions,
                       (SELECT COUNT(*) FROM training_attendance 
                        WHERE course_id = tp.course_id AND member_id = tp.member_id AND present = 1) as attended_sessions,
                       (SELECT COALESCE(SUM(hours_attended), 0) FROM training_attendance 
                        WHERE course_id = tp.course_id AND member_id = tp.member_id AND present = 1) as actual_hours_attended
                FROM training_participants tp
                JOIN members m ON tp.member_id = m.id
                WHERE tp.id = ?";
        
        return $this->db->fetchOne($sql, [$participantId]);
    }
    
    /**
     * Ottieni lista membri per aggiunta partecipante
     */
    public function getAvailableMembers($courseId, $search = '') {
        $sql = "SELECT m.id, m.first_name, m.last_name, m.registration_number
                FROM members m
                WHERE m.member_status = 'attivo'
                AND m.id NOT IN (SELECT member_id FROM training_participants WHERE course_id = ?)";
        
        $params = [$courseId];
        
        if (!empty($search)) {
            $sql .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.registration_number LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY m.last_name, m.first_name LIMIT 50";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Ottieni partecipanti con email per export SSPC
     */
    public function getParticipantsWithEmail($courseId) {
        $sql = "SELECT 
                    m.first_name AS Nome,
                    m.last_name AS Cognome,
                    m.tax_code AS Codice_Fiscale,
                    mc.value AS Email
                FROM training_participants tp
                JOIN members m ON tp.member_id = m.id
                LEFT JOIN member_contacts mc ON mc.member_id = m.id AND mc.contact_type = 'email'
                WHERE tp.course_id = ?
                GROUP BY tp.id, m.first_name, m.last_name, m.tax_code
                ORDER BY m.last_name, m.first_name";
        
        return $this->db->fetchAll($sql, [$courseId]);
    }
}
