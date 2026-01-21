<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Utils\EmailSender;
use EasyVol\Utils\AutoLogger;

/**
 * Member Portal Controller
 * 
 * Handles public member self-service portal functionality including:
 * - Member verification (registration number + last name)
 * - Email verification code sending and validation
 * - Member data viewing and updating
 */
class MemberPortalController {
    private $db;
    private $config;
    private $emailSender;
    
    const CODE_LENGTH = 8;
    const CODE_EXPIRY_MINUTES = 15;
    
    /**
     * Constructor
     * 
     * @param Database $db Database instance
     * @param array $config Configuration
     */
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
        $this->emailSender = new EmailSender($config, $db);
    }
    
    /**
     * Check if an exception is due to a missing database table
     * Helper method to reduce code duplication
     * 
     * @param \Exception $e The exception to check
     * @return bool True if the exception is due to a missing table
     */
    private function isMissingTableException(\Exception $e) {
        $message = $e->getMessage();
        // Check for SQLSTATE 42S02 (Base table or view not found) or text patterns
        return strpos($message, "42S02") !== false ||
               strpos($message, "Base table or view not found") !== false || 
               strpos($message, "doesn't exist") !== false ||
               strpos($message, "Table") !== false && strpos($message, "doesn't exist") !== false;
    }
    
    /**
     * Convert empty string to null for database fields
     * 
     * @param mixed $value The value to check
     * @return mixed|null Returns null if value is empty string, otherwise returns the value
     */
    private function emptyToNull($value) {
        return ($value !== null && trim($value) === '') ? null : $value;
    }
    
    /**
     * Verify member by registration number and last name
     * Returns member data if active adult member found
     * 
     * @param string $registrationNumber Member registration number
     * @param string $lastName Member last name
     * @return array|false Member data or false if not found/invalid
     */
    public function verifyMember($registrationNumber, $lastName) {
        AutoLogger::logActivity('member_portal', 'verify_attempt', null, [
            'registration_number' => $registrationNumber
        ]);
        
        // Query for active adult member with matching credentials
        $sql = "SELECT m.id, m.registration_number, m.last_name, m.first_name, 
                       m.birth_date, m.member_status, m.member_type
                FROM members m
                WHERE m.registration_number = ? 
                  AND LOWER(m.last_name) = LOWER(?)
                  AND m.member_status = 'attivo'
                LIMIT 1";
        
        $member = $this->db->fetchOne($sql, [$registrationNumber, trim($lastName)]);
        
        if (!$member) {
            AutoLogger::logActivity('member_portal', 'verify_failed', null, [
                'registration_number' => $registrationNumber,
                'reason' => 'not_found_or_inactive'
            ]);
            return false;
        }
        
        // Check if member is adult (18+)
        if (!empty($member['birth_date'])) {
            $birthDate = new \DateTime($member['birth_date']);
            $today = new \DateTime();
            $age = $birthDate->diff($today)->y;
            
            if ($age < 18) {
                AutoLogger::logActivity('member_portal', 'verify_failed', $member['id'], [
                    'registration_number' => $registrationNumber,
                    'reason' => 'underage',
                    'age' => $age
                ]);
                return false;
            }
        }
        
        AutoLogger::logActivity('member_portal', 'verify_success', $member['id'], [
            'registration_number' => $registrationNumber
        ]);
        
        return $member;
    }
    
    /**
     * Get member's primary email address
     * 
     * @param int $memberId Member ID
     * @return string|false Email address or false if not found
     */
    public function getMemberEmail($memberId) {
        $sql = "SELECT value FROM member_contacts 
                WHERE member_id = ? AND contact_type = 'email' 
                ORDER BY id ASC LIMIT 1";
        
        $result = $this->db->fetchOne($sql, [$memberId]);
        return $result ? $result['value'] : false;
    }
    
    /**
     * Check if member has an existing valid (unused and not expired) verification code
     * 
     * @param int $memberId Member ID
     * @return array|false Returns code data if valid code exists, false otherwise
     */
    public function hasValidVerificationCode($memberId) {
        $sql = "SELECT id, code, email, expires_at, created_at 
                FROM member_verification_codes 
                WHERE member_id = ? 
                  AND used = 0 
                  AND expires_at > NOW()
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $result = $this->db->fetchOne($sql, [$memberId]);
        
        if ($result) {
            AutoLogger::logActivity('member_portal', 'valid_code_found', $memberId, [
                'expires_at' => $result['expires_at']
            ]);
        }
        
        return $result;
    }
    
    /**
     * Generate and send verification code to member's email
     * 
     * @param int $memberId Member ID
     * @param string $email Email address
     * @return bool True on success
     */
    public function sendVerificationCode($memberId, $email) {
        // Generate random verification code
        $code = $this->generateVerificationCode();
        
        // Calculate expiry time
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::CODE_EXPIRY_MINUTES . ' minutes'));
        
        // Invalidate any existing unused codes for this member
        $this->db->execute(
            "UPDATE member_verification_codes SET used = 1 WHERE member_id = ? AND used = 0",
            [$memberId]
        );
        
        // Insert new verification code
        $sql = "INSERT INTO member_verification_codes (member_id, code, email, expires_at, used) 
                VALUES (?, ?, ?, ?, 0)";
        
        $this->db->execute($sql, [$memberId, $code, $email, $expiresAt]);
        
        // Get member name for email
        $member = $this->db->fetchOne("SELECT first_name, last_name FROM members WHERE id = ?", [$memberId]);
        $memberName = $member ? ($member['first_name'] . ' ' . $member['last_name']) : '';
        
        // Send email with verification code
        $associationName = $this->config['association']['name'] ?? 'Associazione';
        
        $subject = "Codice di Verifica - $associationName";
        $body = $this->getVerificationEmailBody($memberName, $code, $associationName);
        
        $sent = $this->emailSender->send($email, $subject, $body);
        
        if ($sent) {
            AutoLogger::logActivity('member_portal', 'code_sent', $memberId, [
                'email' => $email,
                'expires_at' => $expiresAt
            ]);
        } else {
            AutoLogger::logActivity('member_portal', 'code_send_failed', $memberId, [
                'email' => $email,
                'error' => 'email_failed'
            ]);
        }
        
        return $sent;
    }
    
    /**
     * Verify the code entered by the member
     * 
     * @param int $memberId Member ID
     * @param string $code Verification code
     * @return bool True if valid
     */
    public function verifyCode($memberId, $code) {
        $sql = "SELECT id, expires_at, used FROM member_verification_codes 
                WHERE member_id = ? AND code = ? 
                ORDER BY created_at DESC LIMIT 1";
        
        $result = $this->db->fetchOne($sql, [$memberId, $code]);
        
        if (!$result) {
            AutoLogger::logActivity('member_portal', 'code_verify_failed', $memberId, [
                'reason' => 'code_not_found'
            ]);
            return false;
        }
        
        // Check if already used
        if ($result['used'] == 1) {
            AutoLogger::logActivity('member_portal', 'code_verify_failed', $memberId, [
                'reason' => 'code_already_used'
            ]);
            return false;
        }
        
        // Check if expired
        $expiresAt = new \DateTime($result['expires_at']);
        $now = new \DateTime();
        
        if ($now > $expiresAt) {
            AutoLogger::logActivity('member_portal', 'code_verify_failed', $memberId, [
                'reason' => 'code_expired',
                'expires_at' => $result['expires_at']
            ]);
            return false;
        }
        
        // Mark code as used
        $this->db->execute(
            "UPDATE member_verification_codes SET used = 1 WHERE id = ?",
            [$result['id']]
        );
        
        AutoLogger::logActivity('member_portal', 'code_verify_success', $memberId);
        
        return true;
    }
    
    /**
     * Get complete member data for portal display
     * 
     * @param int $memberId Member ID
     * @return array Member data with all related tables
     */
    public function getMemberData($memberId) {
        // Get main member data
        $sql = "SELECT * FROM members WHERE id = ?";
        $member = $this->db->fetchOne($sql, [$memberId]);
        
        if (!$member) {
            return false;
        }
        
        // Get all related data
        $member['contacts'] = $this->db->fetchAll("SELECT * FROM member_contacts WHERE member_id = ?", [$memberId]);
        $member['addresses'] = $this->db->fetchAll("SELECT * FROM member_addresses WHERE member_id = ?", [$memberId]);
        
        // Handle member_courses gracefully if table doesn't exist
        try {
            $member['courses'] = $this->db->fetchAll("SELECT * FROM member_courses WHERE member_id = ?", [$memberId]);
        } catch (\Exception $e) {
            if ($this->isMissingTableException($e)) {
                $member['courses'] = [];
            } else {
                throw $e;
            }
        }
        
        $member['licenses'] = $this->db->fetchAll("SELECT * FROM member_licenses WHERE member_id = ?", [$memberId]);
        $member['health'] = $this->db->fetchAll("SELECT * FROM member_health WHERE member_id = ?", [$memberId]);
        $member['availability'] = $this->db->fetchAll("SELECT * FROM member_availability WHERE member_id = ?", [$memberId]);
        
        return $member;
    }
    
    /**
     * Update member data from portal submission
     * 
     * @param int $memberId Member ID
     * @param array $data Updated data
     * @return bool True on success
     */
    public function updateMemberData($memberId, $data) {
        try {
            $this->db->beginTransaction();
            
            $changes = [];
            
            // Update main member fields (worker_type, education_level)
            if (isset($data['member_fields']) && !empty($data['member_fields'])) {
                $this->updateMemberFields($memberId, $data['member_fields'], $changes);
            }
            
            // Update contacts
            if (isset($data['contacts'])) {
                $this->updateContacts($memberId, $data['contacts'], $changes);
            }
            
            // Update addresses
            if (isset($data['addresses'])) {
                $this->updateAddresses($memberId, $data['addresses'], $changes);
            }
            
            // Update courses
            if (isset($data['courses'])) {
                $this->updateCourses($memberId, $data['courses'], $changes);
            }
            
            // Update licenses
            if (isset($data['licenses'])) {
                $this->updateLicenses($memberId, $data['licenses'], $changes);
            }
            
            // Update health info
            if (isset($data['health'])) {
                $this->updateHealth($memberId, $data['health'], $changes);
            }
            
            // Update availability
            if (isset($data['availability'])) {
                $this->updateAvailability($memberId, $data['availability'], $changes);
            }
            
            $this->db->commit();
            
            // Log the update
            AutoLogger::logActivity('member_portal', 'data_updated', $memberId, [
                'changes_count' => count($changes),
                'sections' => array_keys($data)
            ]);
            
            // Send confirmation email
            $this->sendUpdateConfirmationEmail($memberId, $changes);
            
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            AutoLogger::logActivity('member_portal', 'update_failed', $memberId, [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Update main member fields (worker_type, education_level)
     */
    private function updateMemberFields($memberId, $fields, &$changes) {
        $allowedFields = ['worker_type', 'education_level'];
        $updateParts = [];
        $params = [];
        
        foreach ($fields as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateParts[] = "$field = ?";
                $params[] = !empty($value) ? $value : null;
                
                // Add to changes log
                $fieldLabels = [
                    'worker_type' => 'Tipo Lavoratore',
                    'education_level' => 'Titolo di Studio'
                ];
                $valueLabels = [
                    'studente' => 'Studente',
                    'dipendente_privato' => 'Dipendente Privato',
                    'dipendente_pubblico' => 'Dipendente Pubblico',
                    'lavoratore_autonomo' => 'Lavoratore Autonomo',
                    'disoccupato' => 'Disoccupato',
                    'pensionato' => 'Pensionato',
                    'licenza_media' => 'Licenza Media',
                    'diploma_maturita' => 'Diploma di Maturità',
                    'laurea_triennale' => 'Laurea Triennale',
                    'laurea_magistrale' => 'Laurea Magistrale',
                    'dottorato' => 'Dottorato'
                ];
                
                $fieldLabel = $fieldLabels[$field] ?? $field;
                $valueLabel = $valueLabels[$value] ?? ($value ?: 'Non specificato');
                $changes[] = "$fieldLabel aggiornato: $valueLabel";
            }
        }
        
        if (!empty($updateParts)) {
            $params[] = $memberId;
            $sql = "UPDATE members SET " . implode(', ', $updateParts) . " WHERE id = ?";
            $this->db->execute($sql, $params);
        }
    }
    
    /**
     * Update member contacts
     */
    private function updateContacts($memberId, $contacts, &$changes) {
        // Delete existing contacts
        $this->db->execute("DELETE FROM member_contacts WHERE member_id = ?", [$memberId]);
        
        // Insert new contacts
        foreach ($contacts as $contact) {
            if (!empty($contact['value'])) {
                $sql = "INSERT INTO member_contacts (member_id, contact_type, value) VALUES (?, ?, ?)";
                $this->db->execute($sql, [$memberId, $contact['type'], trim($contact['value'])]);
                $changes[] = "Contatto aggiunto: " . $contact['type'] . " - " . $contact['value'];
            }
        }
    }
    
    /**
     * Update member addresses
     */
    private function updateAddresses($memberId, $addresses, &$changes) {
        // Delete existing addresses
        $this->db->execute("DELETE FROM member_addresses WHERE member_id = ?", [$memberId]);
        
        // Insert new addresses
        foreach ($addresses as $address) {
            if (!empty($address['street']) || !empty($address['city'])) {
                $sql = "INSERT INTO member_addresses (member_id, address_type, street, number, city, province, cap) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $this->db->execute($sql, [
                    $memberId,
                    $address['type'],
                    $address['street'] ?? '',
                    $address['number'] ?? '',
                    $address['city'] ?? '',
                    $address['province'] ?? '',
                    $address['cap'] ?? ''
                ]);
                $changes[] = "Indirizzo aggiornato: " . $address['type'];
            }
        }
    }
    
    /**
     * Update member courses
     */
    private function updateCourses($memberId, $courses, &$changes) {
        try {
            // Delete existing courses
            $this->db->execute("DELETE FROM member_courses WHERE member_id = ?", [$memberId]);
            
            // Insert new courses
            foreach ($courses as $course) {
                if (!empty($course['course_name'])) {
                    $sql = "INSERT INTO member_courses (member_id, course_name, completion_date, expiry_date, notes) 
                            VALUES (?, ?, ?, ?, ?)";
                    $this->db->execute($sql, [
                        $memberId,
                        $course['course_name'],
                        $this->emptyToNull($course['completion_date'] ?? ''),
                        $this->emptyToNull($course['expiry_date'] ?? ''),
                        $course['notes'] ?? ''
                    ]);
                    $changes[] = "Corso aggiunto: " . $course['course_name'];
                }
            }
        } catch (\Exception $e) {
            // Handle missing table gracefully - skip course updates
            if ($this->isMissingTableException($e)) {
                // Silently skip - table doesn't exist yet
                return;
            }
            // Re-throw other exceptions
            throw $e;
        }
    }
    
    /**
     * Update member licenses
     */
    private function updateLicenses($memberId, $licenses, &$changes) {
        // Delete existing licenses
        $this->db->execute("DELETE FROM member_licenses WHERE member_id = ?", [$memberId]);
        
        // Insert new licenses
        foreach ($licenses as $license) {
            if (!empty($license['license_type'])) {
                $sql = "INSERT INTO member_licenses (member_id, license_type, license_number, issue_date, expiry_date, notes) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $this->db->execute($sql, [
                    $memberId,
                    $license['license_type'],
                    $license['license_number'] ?? '',
                    $this->emptyToNull($license['issue_date'] ?? ''),
                    $this->emptyToNull($license['expiry_date'] ?? ''),
                    $license['notes'] ?? ''
                ]);
                $changes[] = "Patente aggiunta: " . $license['license_type'];
            }
        }
    }
    
    /**
     * Update member health info
     */
    private function updateHealth($memberId, $healthData, &$changes) {
        // Delete existing health data
        $this->db->execute("DELETE FROM member_health WHERE member_id = ?", [$memberId]);
        
        // Insert new health data
        foreach ($healthData as $health) {
            if (!empty($health['type']) && !empty($health['description'])) {
                $sql = "INSERT INTO member_health (member_id, health_type, description) 
                        VALUES (?, ?, ?)";
                $this->db->execute($sql, [
                    $memberId,
                    $health['type'],
                    $health['description']
                ]);
                $changes[] = "Info alimentare aggiornata: " . $health['type'];
            }
        }
    }
    
    /**
     * Update member availability
     */
    private function updateAvailability($memberId, $availability, &$changes) {
        // Delete existing availability
        $this->db->execute("DELETE FROM member_availability WHERE member_id = ?", [$memberId]);
        
        // Insert new availability
        foreach ($availability as $avail) {
            if (!empty($avail['availability_type'])) {
                $sql = "INSERT INTO member_availability (member_id, availability_type, notes) 
                        VALUES (?, ?, ?)";
                $this->db->execute($sql, [
                    $memberId,
                    $avail['availability_type'],
                    $avail['notes'] ?? ''
                ]);
                $changes[] = "Disponibilità aggiunta: " . $avail['availability_type'];
            }
        }
    }
    
    /**
     * Send confirmation email after data update
     */
    private function sendUpdateConfirmationEmail($memberId, $changes) {
        $member = $this->db->fetchOne("SELECT first_name, last_name FROM members WHERE id = ?", [$memberId]);
        $email = $this->getMemberEmail($memberId);
        
        if (!$email || !$member) {
            return false;
        }
        
        $memberName = $member['first_name'] . ' ' . $member['last_name'];
        $associationName = $this->config['association']['name'] ?? 'Associazione';
        $associationEmail = $this->config['association']['email'] ?? '';
        
        // Build changes summary
        $changesSummary = '<ul>';
        foreach ($changes as $change) {
            $changesSummary .= '<li>' . htmlspecialchars($change) . '</li>';
        }
        $changesSummary .= '</ul>';
        
        $subject = "Conferma Aggiornamento Dati - $associationName";
        $body = $this->getUpdateConfirmationEmailBody($memberName, $changesSummary, $associationName);
        
        // Send to member
        $this->emailSender->send($email, $subject, $body);
        
        // Send copy to association if email configured
        if (!empty($associationEmail)) {
            $ccSubject = "Aggiornamento Dati Socio - $memberName";
            $this->emailSender->send($associationEmail, $ccSubject, $body);
        }
        
        return true;
    }
    
    /**
     * Generate random verification code
     */
    private function generateVerificationCode() {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $code;
    }
    
    /**
     * Get verification email HTML body
     */
    private function getVerificationEmailBody($memberName, $code, $associationName) {
        return '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #667eea;">Codice di Verifica</h2>
                <p>Gentile <strong>' . htmlspecialchars($memberName) . '</strong>,</p>
                <p>Hai richiesto l\'accesso al portale di aggiornamento dati soci.</p>
                <div style="background-color: #f4f4f4; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center;">
                    <p style="margin: 5px 0; font-size: 14px;">Il tuo codice di verifica è:</p>
                    <p style="margin: 10px 0; font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 5px;">' . htmlspecialchars($code) . '</p>
                    <p style="margin: 5px 0; font-size: 12px; color: #666;">Il codice scadrà tra ' . self::CODE_EXPIRY_MINUTES . ' minuti</p>
                </div>
                <p>Inserisci questo codice nella pagina di verifica per procedere.</p>
                <p><strong>Se non hai richiesto questo codice, ignora questa email.</strong></p>
                <p>Cordiali saluti,<br>' . htmlspecialchars($associationName) . '</p>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Get update confirmation email HTML body
     */
    private function getUpdateConfirmationEmailBody($memberName, $changesSummary, $associationName) {
        return '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #667eea;">Conferma Aggiornamento Dati</h2>
                <p>Gentile <strong>' . htmlspecialchars($memberName) . '</strong>,</p>
                <p>I tuoi dati sono stati aggiornati con successo nel nostro sistema.</p>
                <div style="background-color: #f4f4f4; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0;">Riepilogo delle modifiche:</h3>
                    ' . $changesSummary . '
                </div>
                <p>Se hai riscontrato errori o necessiti di ulteriori modifiche, contatta la Segreteria dell\'Associazione.</p>
                <p>Cordiali saluti,<br>' . htmlspecialchars($associationName) . '</p>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Get on-call schedules for a member
     * 
     * @param int $memberId Member ID
     * @param string $status Filter by status: 'all', 'past', 'active', 'future'
     * @return array Array of on-call schedules
     */
    public function getOnCallSchedules($memberId, $status = 'all') {
        $sql = "SELECT ocs.*, 
                m.first_name, m.last_name, m.badge_number 
                FROM on_call_schedule ocs
                JOIN members m ON ocs.member_id = m.id
                WHERE ocs.member_id = ?";
        
        $params = [$memberId];
        
        if ($status === 'past') {
            $sql .= " AND ocs.end_datetime < NOW()";
        } elseif ($status === 'active') {
            $sql .= " AND ocs.start_datetime <= NOW() AND ocs.end_datetime >= NOW()";
        } elseif ($status === 'future') {
            $sql .= " AND ocs.start_datetime > NOW()";
        }
        
        $sql .= " ORDER BY ocs.start_datetime DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Add on-call schedule for a member
     * 
     * @param int $memberId Member ID
     * @param string $startDatetime Start datetime
     * @param string $endDatetime End datetime
     * @param string $notes Optional notes
     * @return bool Success status
     */
    public function addOnCallSchedule($memberId, $startDatetime, $endDatetime, $notes = '') {
        try {
            // Validate dates
            $start = new \DateTime($startDatetime);
            $end = new \DateTime($endDatetime);
            
            if ($end <= $start) {
                return false;
            }
            
            // Convert to MySQL format
            $startMysql = $start->format('Y-m-d H:i:s');
            $endMysql = $end->format('Y-m-d H:i:s');
            
            // Check for overlapping schedules
            // Two date ranges overlap if: start1 < end2 AND start2 < end1
            $sql = "SELECT COUNT(*) as count FROM on_call_schedule 
                    WHERE member_id = ? 
                    AND start_datetime < ? 
                    AND end_datetime > ?";
            $result = $this->db->fetchOne($sql, [
                $memberId,
                $endMysql,
                $startMysql
            ]);
            
            if ($result['count'] > 0) {
                return false;
            }
            
            // Insert on-call schedule (created_by is the member_id for self-service)
            $sql = "INSERT INTO on_call_schedule (member_id, start_datetime, end_datetime, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?)";
            $this->db->execute($sql, [$memberId, $startMysql, $endMysql, $notes, $memberId]);
            
            AutoLogger::logActivity('member_portal', 'add_on_call', $memberId, [
                'start' => $startMysql,
                'end' => $endMysql
            ]);
            
            return true;
        } catch (\Exception $e) {
            error_log("Error adding on-call schedule: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update on-call schedule
     * 
     * @param int $scheduleId Schedule ID
     * @param int $memberId Member ID (for verification)
     * @param string $startDatetime Start datetime
     * @param string $endDatetime End datetime
     * @param string $notes Optional notes
     * @return bool Success status
     */
    public function updateOnCallSchedule($scheduleId, $memberId, $startDatetime, $endDatetime, $notes = '') {
        try {
            // Verify the schedule belongs to the member
            $sql = "SELECT member_id FROM on_call_schedule WHERE id = ?";
            $schedule = $this->db->fetchOne($sql, [$scheduleId]);
            
            if (!$schedule || $schedule['member_id'] != $memberId) {
                return false;
            }
            
            // Validate dates
            $start = new \DateTime($startDatetime);
            $end = new \DateTime($endDatetime);
            
            if ($end <= $start) {
                return false;
            }
            
            // Convert to MySQL format
            $startMysql = $start->format('Y-m-d H:i:s');
            $endMysql = $end->format('Y-m-d H:i:s');
            
            // Check for overlapping schedules (excluding current)
            // Two date ranges overlap if: start1 < end2 AND start2 < end1
            $sql = "SELECT COUNT(*) as count FROM on_call_schedule 
                    WHERE member_id = ? 
                    AND id != ?
                    AND start_datetime < ? 
                    AND end_datetime > ?";
            $result = $this->db->fetchOne($sql, [
                $memberId,
                $scheduleId,
                $endMysql,
                $startMysql
            ]);
            
            if ($result['count'] > 0) {
                return false;
            }
            
            // Update schedule
            $sql = "UPDATE on_call_schedule 
                    SET start_datetime = ?, end_datetime = ?, notes = ?, updated_at = NOW()
                    WHERE id = ? AND member_id = ?";
            $this->db->execute($sql, [$startMysql, $endMysql, $notes, $scheduleId, $memberId]);
            
            AutoLogger::logActivity('member_portal', 'update_on_call', $memberId, [
                'schedule_id' => $scheduleId
            ]);
            
            return true;
        } catch (\Exception $e) {
            error_log("Error updating on-call schedule: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete on-call schedule
     * 
     * @param int $scheduleId Schedule ID
     * @param int $memberId Member ID (for verification)
     * @return bool Success status
     */
    public function deleteOnCallSchedule($scheduleId, $memberId) {
        try {
            // Verify the schedule belongs to the member
            $sql = "SELECT member_id FROM on_call_schedule WHERE id = ?";
            $schedule = $this->db->fetchOne($sql, [$scheduleId]);
            
            if (!$schedule || $schedule['member_id'] != $memberId) {
                return false;
            }
            
            // Delete schedule
            $sql = "DELETE FROM on_call_schedule WHERE id = ? AND member_id = ?";
            $this->db->execute($sql, [$scheduleId, $memberId]);
            
            AutoLogger::logActivity('member_portal', 'delete_on_call', $memberId, [
                'schedule_id' => $scheduleId
            ]);
            
            return true;
        } catch (\Exception $e) {
            error_log("Error deleting on-call schedule: " . $e->getMessage());
            return false;
        }
    }
}
