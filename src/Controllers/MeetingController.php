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
    
    // Constants for member types
    const MEMBER_TYPE_ADULT = 'adult';
    const MEMBER_TYPE_JUNIOR = 'junior';
    
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
                LIMIT $perPage OFFSET $offset";
        
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
            $this->db->rollBack();
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
        $sql = "SELECT mp.*, 
                m.first_name, m.last_name, m.registration_number,
                jm.first_name as junior_first_name, jm.last_name as junior_last_name, 
                jm.registration_number as junior_registration_number
                FROM meeting_participants mp
                LEFT JOIN members m ON mp.member_id = m.id AND mp.member_type = 'adult'
                LEFT JOIN junior_members jm ON mp.junior_member_id = jm.id AND mp.member_type = 'junior'
                WHERE mp.meeting_id = ?
                ORDER BY m.last_name, m.first_name, jm.last_name, jm.first_name";
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
    
    /**
     * Aggiungi partecipanti da soci maggiorenni e minorenni
     */
    public function addParticipantsFromMembers($meetingId, $includeAdults = true, $includeJuniors = true) {
        try {
            $this->db->beginTransaction();
            
            // Add adult members
            if ($includeAdults) {
                $sql = "SELECT id FROM members WHERE member_status = 'attivo'";
                $adultMembers = $this->db->fetchAll($sql);
                
                foreach ($adultMembers as $member) {
                    $sql = "INSERT IGNORE INTO meeting_participants 
                            (meeting_id, member_id, member_type, attendance_status) 
                            VALUES (?, ?, ?, 'invited')";
                    $this->db->execute($sql, [$meetingId, $member['id'], self::MEMBER_TYPE_ADULT]);
                }
            }
            
            // Add junior members
            if ($includeJuniors) {
                $sql = "SELECT id FROM junior_members WHERE member_status = 'attivo'";
                $juniorMembers = $this->db->fetchAll($sql);
                
                foreach ($juniorMembers as $member) {
                    $sql = "INSERT IGNORE INTO meeting_participants 
                            (meeting_id, junior_member_id, member_type, attendance_status) 
                            VALUES (?, ?, ?, 'invited')";
                    $this->db->execute($sql, [$meetingId, $member['id'], self::MEMBER_TYPE_JUNIOR]);
                }
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore aggiunta partecipanti: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiungi singolo partecipante
     */
    public function addParticipant($meetingId, $memberId, $memberType = self::MEMBER_TYPE_ADULT, $role = null) {
        try {
            $sql = "INSERT INTO meeting_participants 
                    (meeting_id, member_id, junior_member_id, member_type, role, attendance_status) 
                    VALUES (?, ?, ?, ?, ?, 'invited')";
            
            $params = [
                $meetingId,
                $memberType === self::MEMBER_TYPE_ADULT ? $memberId : null,
                $memberType === self::MEMBER_TYPE_JUNIOR ? $memberId : null,
                $memberType,
                $role
            ];
            
            $this->db->execute($sql, $params);
            return true;
        } catch (\Exception $e) {
            error_log("Errore aggiunta partecipante: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna stato presenza partecipante
     */
    public function updateAttendance($participantId, $status, $delegatedTo = null) {
        try {
            $isPresent = ($status === 'present') ? 1 : 0;
            
            $sql = "UPDATE meeting_participants 
                    SET attendance_status = ?, 
                        delegated_to = ?,
                        response_date = NOW(),
                        present = ?
                    WHERE id = ?";
            
            $this->db->execute($sql, [$status, $delegatedTo, $isPresent, $participantId]);
            return true;
        } catch (\Exception $e) {
            error_log("Errore aggiornamento presenza: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Invia convocazione via email a tutti i partecipanti
     */
    public function sendInvitations($meetingId, $userId) {
        try {
            require_once __DIR__ . '/../Utils/EmailSender.php';
            
            $meeting = $this->get($meetingId);
            if (!$meeting) {
                return ['success' => false, 'message' => 'Riunione non trovata'];
            }
            
            $emailSender = new \EasyVol\Utils\EmailSender($this->config, $this->db);
            $sentCount = 0;
            $failedCount = 0;
            
            // Get all participants with email addresses
            $sql = "SELECT mp.id, mp.member_type,
                    m.first_name, m.last_name,
                    mc.value as email,
                    jm.first_name as junior_first_name, jm.last_name as junior_last_name,
                    jg.email as junior_email
                    FROM meeting_participants mp
                    LEFT JOIN members m ON mp.member_id = m.id AND mp.member_type = 'adult'
                    LEFT JOIN member_contacts mc ON (m.id = mc.member_id AND mc.contact_type = 'email')
                    LEFT JOIN junior_members jm ON mp.junior_member_id = jm.id AND mp.member_type = 'junior'
                    LEFT JOIN junior_member_guardians jg ON (jm.id = jg.junior_member_id AND jg.guardian_type IN ('padre', 'madre'))
                    WHERE mp.meeting_id = ?
                    AND mp.invitation_sent_at IS NULL";
            
            $participants = $this->db->fetchAll($sql, [$meetingId]);
            
            foreach ($participants as $participant) {
                $email = $participant['email'] ?? $participant['junior_email'];
                if (!$email) {
                    continue;
                }
                
                $name = $participant['member_type'] === 'adult' 
                    ? $participant['first_name'] . ' ' . $participant['last_name']
                    : $participant['junior_first_name'] . ' ' . $participant['junior_last_name'];
                
                // Build email body
                $subject = "Convocazione: " . $meeting['title'];
                $body = $this->buildInvitationEmail($meeting, $name);
                
                if ($emailSender->send($email, $subject, $body)) {
                    // Mark as sent
                    $this->db->execute(
                        "UPDATE meeting_participants SET invitation_sent_at = NOW() WHERE id = ?",
                        [$participant['id']]
                    );
                    $sentCount++;
                } else {
                    $failedCount++;
                }
            }
            
            // Update meeting
            $this->db->execute(
                "UPDATE meetings SET convocation_sent_at = NOW() WHERE id = ?",
                [$meetingId]
            );
            
            $this->logActivity($userId, 'meetings', 'send_invitations', $meetingId, 
                "Inviate $sentCount convocazioni, $failedCount fallite");
            
            return [
                'success' => true, 
                'sent' => $sentCount, 
                'failed' => $failedCount
            ];
            
        } catch (\Exception $e) {
            error_log("Errore invio convocazioni: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'invio'];
        }
    }
    
    /**
     * Costruisci email di convocazione
     */
    private function buildInvitationEmail($meeting, $recipientName) {
        // Format meeting type for display (convert underscores to spaces and capitalize)
        $meetingTypeFormatted = ucwords(str_replace('_', ' ', $meeting['meeting_type']));
        
        $body = "<html><body>";
        $body .= "<h2>Convocazione Riunione</h2>";
        $body .= "<p>Gentile " . htmlspecialchars($recipientName) . ",</p>";
        $body .= "<p>Sei convocato/a alla seguente riunione:</p>";
        $body .= "<div style='border: 1px solid #ccc; padding: 15px; margin: 15px 0;'>";
        $body .= "<p><strong>Tipo:</strong> " . htmlspecialchars($meetingTypeFormatted) . "</p>";
        $body .= "<p><strong>Titolo:</strong> " . htmlspecialchars($meeting['title']) . "</p>";
        $body .= "<p><strong>Data:</strong> " . date('d/m/Y', strtotime($meeting['meeting_date']));
        if ($meeting['start_time']) {
            $body .= " ore " . date('H:i', strtotime($meeting['start_time']));
        }
        $body .= "</p>";
        
        if (!empty($meeting['location']) || !empty($meeting['location_address'])) {
            $location = $meeting['location'] ?? $meeting['location_address'];
            $body .= "<p><strong>Luogo:</strong> " . htmlspecialchars($location) . "</p>";
        }
        
        if ($meeting['location_type'] === 'online' && !empty($meeting['online_details'])) {
            $body .= "<p><strong>Dettagli collegamento online:</strong><br>" . 
                     nl2br(htmlspecialchars($meeting['online_details'])) . "</p>";
        }
        
        if (!empty($meeting['description'])) {
            $body .= "<p><strong>Descrizione:</strong><br>" . 
                     nl2br(htmlspecialchars($meeting['description'])) . "</p>";
        }
        
        if (!empty($meeting['agenda'])) {
            $body .= "<p><strong>Ordine del giorno:</strong></p><ol>";
            foreach ($meeting['agenda'] as $item) {
                $body .= "<li>" . htmlspecialchars($item['subject']) . "</li>";
            }
            $body .= "</ol>";
        }
        
        $body .= "</div>";
        $body .= "<p>La tua presenza è importante.</p>";
        $body .= "<p>Cordiali saluti</p>";
        $body .= "</body></html>";
        
        return $body;
    }
}
