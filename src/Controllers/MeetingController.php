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
    
    // Convocator field separator
    const CONVOCATOR_SEPARATOR = '|';
    
    // Role constants
    const ROLE_PRESIDENTE = 'Presidente';
    const ROLE_SEGRETARIO = 'Segretario';
    
    // Meeting type display names
    const MEETING_TYPE_NAMES = [
        'assemblea_ordinaria' => 'Assemblea dei Soci Ordinaria',
        'assemblea_straordinaria' => 'Assemblea dei Soci Straordinaria',
        'consiglio_direttivo' => 'Consiglio Direttivo',
        'riunione_capisquadra' => 'Riunione dei Capisquadra',
        'riunione_nucleo' => 'Riunione di Nucleo',
        'altra_riunione' => 'Altra Riunione' // Backward compatibility
    ];
    
    // Date search regex pattern
    const DATE_SEARCH_PATTERN = '/^(\d{1,2})[\/\.\-](\d{1,2})[\/\.\-](\d{4})$/';
    
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
            $searchTerm = trim($filters['search']);
            
            // Try to parse as date in various formats (DD/MM/YYYY, DD.MM.YYYY, DD-MM-YYYY)
            $dateSearched = false;
            if (preg_match(self::DATE_SEARCH_PATTERN, $searchTerm, $matches)) {
                $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $year = $matches[3];
                $dateFormatted = "$year-$month-$day"; // Convert to MySQL format YYYY-MM-DD
                
                // Validate the date (cast to int for checkdate)
                if (checkdate((int)$month, (int)$day, (int)$year)) {
                    $where[] = "meeting_date = ?";
                    $params[] = $dateFormatted;
                    $dateSearched = true;
                }
            }
            
            // If not a valid date, search in location
            if (!$dateSearched) {
                $where[] = "location LIKE ?";
                $params[] = '%' . $searchTerm . '%';
            }
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
        
        // Carica allegati
        $meeting['attachments'] = $this->getAttachments($id);
        
        // Compute convened_by, president, and secretary from participants and convocator field
        $meeting['convened_by'] = '-';
        $meeting['president'] = '-';
        $meeting['secretary'] = '-';
        
        // Parse convocator field (format: member_id|role)
        $convocatorData = $this->parseConvocator($meeting['convocator']);
        if ($convocatorData['member_id']) {
            // Find the member name
            $memberSql = "SELECT first_name, last_name FROM members WHERE id = ?";
            $convocatorMember = $this->db->fetchOne($memberSql, [$convocatorData['member_id']]);
            if ($convocatorMember) {
                $meeting['convened_by'] = trim($convocatorMember['first_name'] . ' ' . $convocatorMember['last_name']) . ' (' . $convocatorData['role'] . ')';
            }
        }
        
        // Find Presidente and Segretario from participants
        foreach ($meeting['participants'] as $participant) {
            $role = $participant['role'] ?? '';
            // Use case-insensitive comparison for consistency
            if (strcasecmp($role, self::ROLE_PRESIDENTE) === 0) {
                $meeting['president'] = $this->extractMemberName($participant);
            }
            if (strcasecmp($role, self::ROLE_SEGRETARIO) === 0) {
                $meeting['secretary'] = $this->extractMemberName($participant);
            }
        }
        
        return $meeting;
    }
    
    /**
     * Helper method to extract member name from participant data
     */
    private function extractMemberName($participant) {
        // Get member name based on member type
        if ($participant['member_type'] === 'junior') {
            $firstName = $participant['junior_first_name'] ?? '';
            $lastName = $participant['junior_last_name'] ?? '';
        } else {
            $firstName = $participant['first_name'] ?? '';
            $lastName = $participant['last_name'] ?? '';
        }
        
        if ($firstName || $lastName) {
            return trim($firstName . ' ' . $lastName);
        }
        
        return '-';
    }
    
    /**
     * Ottieni il prossimo numero progressivo per il tipo di riunione
     */
    public function getNextProgressiveNumber($meetingType) {
        $sql = "SELECT COALESCE(MAX(progressive_number), 0) + 1 as next_number FROM meetings WHERE meeting_type = ?";
        $result = $this->db->fetchOne($sql, [$meetingType]);
        return $result['next_number'] ?? 1;
    }
    
    /**
     * Parse convocator field into member ID and role
     * @param string $convocator The convocator field value
     * @return array Array with keys 'member_id' and 'role', or empty array if invalid
     */
    public function parseConvocator($convocator) {
        if (!empty($convocator) && strpos($convocator, self::CONVOCATOR_SEPARATOR) !== false) {
            [$memberId, $role] = explode(self::CONVOCATOR_SEPARATOR, $convocator, 2);
            return ['member_id' => $memberId, 'role' => $role];
        }
        return ['member_id' => null, 'role' => ''];
    }
    
    /**
     * Format convocator field for display: "Nome Cognome (matricola) | ruolo"
     * @param string $convocator The convocator field value
     * @return string Formatted convocator display string
     */
    public function formatConvocatorDisplay($convocator) {
        $data = $this->parseConvocator($convocator);
        if (!$data['member_id']) {
            return '-';
        }
        
        $memberSql = "SELECT first_name, last_name, registration_number FROM members WHERE id = ?";
        $member = $this->db->fetchOne($memberSql, [$data['member_id']]);
        if ($member) {
            return trim($member['first_name'] . ' ' . $member['last_name']) . ' (' . $member['registration_number'] . ') | ' . $data['role'];
        }
        
        return $data['member_id'] . ' | ' . $data['role'];
    }
    
    /**
     * Crea nuova riunione
     */
    public function create($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO meetings (
                meeting_type, progressive_number, meeting_date, location, convocator, description, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['meeting_type'],
                !empty($data['progressive_number']) ? intval($data['progressive_number']) : null,
                $data['meeting_date'],
                $data['location'] ?? null,
                $data['convocator'] ?? null,
                $data['description'] ?? null
            ];
            
            $this->db->execute($sql, $params);
            $meetingId = $this->db->lastInsertId();
            
            // Generate meeting type name for log
            $meetingName = self::MEETING_TYPE_NAMES[$data['meeting_type']] ?? $data['meeting_type'];
            
            $newMeetingData = $this->db->fetchOne("SELECT * FROM meetings WHERE id = ?", [$meetingId]);
            $this->logActivity($userId, 'meeting', 'create', $meetingId, 'Creata nuova riunione: ' . $meetingName, null, $newMeetingData);
            
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
            // Cattura dati precedenti per il log
            $oldMeetingData = $this->db->fetchOne("SELECT * FROM meetings WHERE id = ?", [$id]);
            
            $sql = "UPDATE meetings SET
                meeting_type = ?, progressive_number = ?, meeting_date = ?, location = ?,
                convocator = ?, description = ?, updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['meeting_type'],
                !empty($data['progressive_number']) ? intval($data['progressive_number']) : null,
                $data['meeting_date'],
                $data['location'] ?? null,
                $data['convocator'] ?? null,
                $data['description'] ?? null,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            $newMeetingData = $this->db->fetchOne("SELECT * FROM meetings WHERE id = ?", [$id]);
            $this->logActivity($userId, 'meeting', 'update', $id, 'Aggiornata riunione', $oldMeetingData, $newMeetingData);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore aggiornamento riunione: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni allegati riunione
     */
    public function getAttachments($meetingId) {
        $sql = "SELECT * FROM meeting_attachments WHERE meeting_id = ? ORDER BY attachment_type DESC, progressive_number ASC, uploaded_at ASC";
        return $this->db->fetchAll($sql, [$meetingId]);
    }

    /**
     * Aggiungi allegato alla riunione
     * @param int $meetingId
     * @param array $data Keys: attachment_type, file_name, file_path, file_type, title, description, progressive_number
     * @param int $userId
     * @return int|false ID del nuovo allegato, o false in caso di errore
     */
    public function addAttachment($meetingId, $data, $userId) {
        try {
            $sql = "INSERT INTO meeting_attachments 
                    (meeting_id, attachment_type, file_name, file_path, file_type, title, description, progressive_number, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [
                $meetingId,
                $data['attachment_type'],
                $data['file_name'],
                $data['file_path'],
                $data['file_type'] ?? null,
                $data['title'] ?? null,
                $data['description'] ?? null,
                isset($data['progressive_number']) ? intval($data['progressive_number']) : null,
                $userId
            ];
            $this->db->execute($sql, $params);
            $attachmentId = $this->db->lastInsertId();
            $this->logActivity($userId, 'meeting', 'add_attachment', $meetingId,
                'Aggiunto allegato: ' . $data['file_name']);
            return $attachmentId;
        } catch (\Exception $e) {
            error_log("Errore aggiunta allegato riunione: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina allegato riunione
     * @param int $attachmentId
     * @param int $userId
     * @return array ['success' => bool, 'file_path' => string|null, 'message' => string|null]
     */
    public function deleteAttachment($attachmentId, $userId) {
        try {
            $sql = "SELECT * FROM meeting_attachments WHERE id = ?";
            $attachment = $this->db->fetchOne($sql, [$attachmentId]);
            if (!$attachment) {
                return ['success' => false, 'message' => 'Allegato non trovato'];
            }
            $this->db->execute("DELETE FROM meeting_attachments WHERE id = ?", [$attachmentId]);
            $this->logActivity($userId, 'meeting', 'delete_attachment', $attachment['meeting_id'],
                'Eliminato allegato: ' . $attachment['file_name']);
            return ['success' => true, 'file_path' => $attachment['file_path']];
        } catch (\Exception $e) {
            error_log("Errore eliminazione allegato riunione: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione'];
        }
    }

    /**
     * Ottieni il prossimo numero progressivo per gli allegati di una riunione
     */
    public function getNextAttachmentNumber($meetingId) {
        $sql = "SELECT COALESCE(MAX(progressive_number), 0) + 1 as next_number FROM meeting_attachments WHERE meeting_id = ? AND attachment_type = 'allegato'";
        $result = $this->db->fetchOne($sql, [$meetingId]);
        return $result['next_number'] ?? 1;
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
    private function logActivity($userId, $module, $action, $recordId, $details, $oldData = null, $newData = null) {
        try {
            $sql = "INSERT INTO activity_logs 
                    (user_id, module, action, record_id, description, ip_address, user_agent, old_data, new_data, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $userId,
                $module,
                $action,
                $recordId,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                is_array($oldData) ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : $oldData,
                is_array($newData) ? json_encode($newData, JSON_UNESCAPED_UNICODE) : $newData,
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
            $meeting = $this->db->fetchOne("SELECT * FROM meetings WHERE id = ?", [$id]);
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
            
            // Log activity with old data
            $meetingName = self::MEETING_TYPE_NAMES[$meeting['meeting_type']] ?? $meeting['meeting_type'];
            $this->logActivity($userId, 'meetings', 'delete', $id, "Eliminata riunione: {$meetingName}", $meeting, null);
            
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
     * Aggiorna ruolo partecipante
     */
    public function updateParticipantRole($participantId, $role) {
        try {
            $sql = "UPDATE meeting_participants SET role = ? WHERE id = ?";
            $this->db->execute($sql, [$role, $participantId]);
            return true;
        } catch (\Exception $e) {
            error_log("Errore aggiornamento ruolo: " . $e->getMessage());
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
     * Elimina partecipante dalla riunione
     */
    public function deleteParticipant($participantId, $userId) {
        try {
            // Get participant details for logging
            $sql = "SELECT mp.*, m.first_name, m.last_name, jm.first_name as junior_first_name, jm.last_name as junior_last_name
                    FROM meeting_participants mp
                    LEFT JOIN members m ON mp.member_id = m.id AND mp.member_type = 'adult'
                    LEFT JOIN junior_members jm ON mp.junior_member_id = jm.id AND mp.member_type = 'junior'
                    WHERE mp.id = ?";
            $participant = $this->db->fetchOne($sql, [$participantId]);
            
            if (!$participant) {
                return false;
            }
            
            // Delete participant
            $sql = "DELETE FROM meeting_participants WHERE id = ?";
            $this->db->execute($sql, [$participantId]);
            
            // Log activity
            $name = $participant['member_type'] === 'adult' 
                ? ($participant['first_name'] . ' ' . $participant['last_name'])
                : ($participant['junior_first_name'] . ' ' . $participant['junior_last_name']);
            $this->logActivity($userId, 'meeting', 'delete_participant', $participant['meeting_id'], 
                "Rimosso partecipante: {$name}");
            
            return true;
        } catch (\Exception $e) {
            error_log("Errore eliminazione partecipante: " . $e->getMessage());
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
                
                // Build email subject with meeting type and date
                $meetingTypeName = self::MEETING_TYPE_NAMES[$meeting['meeting_type']] ?? ucfirst(str_replace('_', ' ', $meeting['meeting_type']));
                $subject = "Convocazione: " . $meetingTypeName . " - " . date('d/m/Y', strtotime($meeting['meeting_date']));
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
        // Format meeting type for display
        $meetingTypeFormatted = self::MEETING_TYPE_NAMES[$meeting['meeting_type']] ?? ucwords(str_replace('_', ' ', $meeting['meeting_type']));
        
        $body = "<html><body>";
        $body .= "<h2>Convocazione Riunione</h2>";
        $body .= "<p>Gentile " . htmlspecialchars($recipientName) . ",</p>";
        $body .= "<p>Sei convocato/a alla seguente riunione:</p>";
        $body .= "<div style='border: 1px solid #ccc; padding: 15px; margin: 15px 0;'>";
        $body .= "<p><strong>Tipo:</strong> " . htmlspecialchars($meetingTypeFormatted) . "</p>";
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
