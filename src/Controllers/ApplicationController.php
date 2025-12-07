<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Utils\PdfGenerator;
use EasyVol\Utils\EmailSender;

/**
 * Application Controller
 * 
 * Gestisce le domande di iscrizione pubbliche
 */
class ApplicationController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Crea nuova domanda di iscrizione
     * 
     * @param array $data Dati domanda
     * @param bool $isJunior Se è un minorenne
     * @return array ['success' => bool, 'id' => int, 'code' => string, 'error' => string]
     */
    public function create($data, $isJunior = false) {
        try {
            $this->db->beginTransaction();
            
            // Genera codice univoco
            $code = $this->generateUniqueCode();
            
            // Inserisci domanda
            $sql = "INSERT INTO member_applications (
                application_code, application_type, status,
                last_name, first_name, birth_date, birth_place, birth_province,
                tax_code, gender, nationality,
                email, phone,
                privacy_accepted, terms_accepted, photo_release_accepted,
                created_at
            ) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $code,
                $isJunior ? 'junior' : 'adult',
                $data['last_name'],
                $data['first_name'],
                $data['birth_date'],
                $data['birth_place'] ?? null,
                $data['birth_province'] ?? null,
                $data['tax_code'] ?? null,
                $data['gender'] ?? null,
                $data['nationality'] ?? 'Italiana',
                $data['email'],
                $data['phone'] ?? null,
                $data['privacy_accepted'] ? 1 : 0,
                $data['terms_accepted'] ? 1 : 0,
                $data['photo_release_accepted'] ? 1 : 0
            ];
            
            $this->db->execute($sql, $params);
            $applicationId = $this->db->lastInsertId();
            
            // Se minorenne, inserisci dati tutore
            if ($isJunior && !empty($data['guardian_data'])) {
                $this->saveGuardianData($applicationId, $data['guardian_data']);
            }
            
            // Genera PDF
            $pdfPath = $this->generateApplicationPdf($applicationId, $data, $isJunior);
            
            // Aggiorna con path PDF
            $this->db->execute(
                "UPDATE member_applications SET pdf_path = ? WHERE id = ?",
                [$pdfPath, $applicationId]
            );
            
            // Invia email
            $this->sendApplicationEmails($applicationId, $data, $pdfPath);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'id' => $applicationId,
                'code' => $code,
                'error' => null
            ];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Errore creazione domanda: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Ottieni lista domande
     * 
     * @param array $filters Filtri
     * @param int $page Pagina
     * @param int $perPage Elementi per pagina
     * @return array
     */
    public function getAll($filters = [], $page = 1, $perPage = 20) {
        $sql = "SELECT * FROM member_applications WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['type'])) {
            $sql .= " AND application_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (last_name LIKE ? OR first_name LIKE ? OR application_code LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY submitted_at DESC";
        
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Ottieni singola domanda
     * 
     * @param int $id ID domanda
     * @return array|false
     */
    public function get($id) {
        return $this->db->fetchOne("SELECT * FROM member_applications WHERE id = ?", [$id]);
    }
    
    /**
     * Approva domanda e crea socio
     * 
     * @param int $id ID domanda
     * @param int $userId ID utente che approva
     * @return bool
     */
    public function approve($id, $userId) {
        try {
            $this->db->beginTransaction();
            
            $application = $this->get($id);
            if (!$application || $application['status'] !== 'pending') {
                throw new \Exception('Domanda non valida');
            }
            
            // Crea socio o cadetto
            if ($application['application_type'] === 'junior') {
                $memberId = $this->createJuniorMember($application, $userId);
            } else {
                $memberId = $this->createMember($application, $userId);
            }
            
            // Aggiorna domanda
            $sql = "UPDATE member_applications SET 
                    status = 'approved',
                    approved_by = ?,
                    approved_at = NOW(),
                    member_id = ?
                    WHERE id = ?";
            
            $this->db->execute($sql, [$userId, $memberId, $id]);
            
            // Invia email approvazione
            $this->sendApprovalEmail($application);
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Errore approvazione domanda: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rifiuta domanda
     * 
     * @param int $id ID domanda
     * @param int $userId ID utente
     * @param string $reason Motivazione
     * @return bool
     */
    public function reject($id, $userId, $reason = '') {
        try {
            $application = $this->get($id);
            if (!$application) {
                return false;
            }
            
            $sql = "UPDATE member_applications SET 
                    status = 'rejected',
                    rejected_by = ?,
                    rejected_at = NOW(),
                    rejection_reason = ?
                    WHERE id = ?";
            
            $this->db->execute($sql, [$userId, $reason, $id]);
            
            // Invia email rifiuto
            $this->sendRejectionEmail($application, $reason);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore rifiuto domanda: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Genera codice univoco per domanda
     * 
     * @return string
     */
    private function generateUniqueCode() {
        do {
            $code = 'APP-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $existing = $this->db->fetchOne(
                "SELECT id FROM member_applications WHERE application_code = ?",
                [$code]
            );
        } while ($existing);
        
        return $code;
    }
    
    /**
     * Salva dati tutore
     * 
     * @param int $applicationId ID domanda
     * @param array $guardianData Dati tutore
     */
    private function saveGuardianData($applicationId, $guardianData) {
        $sql = "INSERT INTO member_application_guardians (
            application_id, guardian_type,
            last_name, first_name, birth_date, birth_place,
            tax_code, phone, email
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $applicationId,
            $guardianData['type'] ?? 'parent',
            $guardianData['last_name'],
            $guardianData['first_name'],
            $guardianData['birth_date'] ?? null,
            $guardianData['birth_place'] ?? null,
            $guardianData['tax_code'] ?? null,
            $guardianData['phone'] ?? null,
            $guardianData['email'] ?? null
        ];
        
        $this->db->execute($sql, $params);
    }
    
    /**
     * Genera PDF domanda
     * 
     * @param int $id ID domanda
     * @param array $data Dati domanda
     * @param bool $isJunior Se minorenne
     * @return string Path PDF
     */
    private function generateApplicationPdf($id, $data, $isJunior) {
        $pdfGen = new PdfGenerator($this->config);
        
        // TODO: Create proper PDF template
        $html = $pdfGen->getHeaderHtml();
        $html .= '<h2 style="text-align: center;">Domanda di Iscrizione</h2>';
        $html .= '<p><strong>Codice:</strong> ' . htmlspecialchars($data['application_code'] ?? '') . '</p>';
        $html .= '<p><strong>Cognome:</strong> ' . htmlspecialchars($data['last_name']) . '</p>';
        $html .= '<p><strong>Nome:</strong> ' . htmlspecialchars($data['first_name']) . '</p>';
        $html .= $pdfGen->getFooterHtml();
        
        $filename = 'domanda_' . $id . '.pdf';
        $path = __DIR__ . '/../../uploads/applications/' . $filename;
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        $pdfGen->generate($html, $filename, 'F');
        
        return $path;
    }
    
    /**
     * Invia email domanda
     * 
     * @param int $id ID domanda
     * @param array $data Dati
     * @param string $pdfPath Path PDF
     */
    private function sendApplicationEmails($id, $data, $pdfPath) {
        if (!($this->config['email']['enabled'] ?? false)) {
            return;
        }
        
        $emailSender = new EmailSender($this->config, $this->db);
        
        // Email al richiedente
        $subject = 'Domanda di iscrizione ricevuta';
        $body = '<p>Gentile ' . htmlspecialchars($data['first_name']) . ',</p>';
        $body .= '<p>La tua domanda di iscrizione è stata ricevuta correttamente.</p>';
        $body .= '<p>Ti contatteremo presto per gli aggiornamenti.</p>';
        
        $emailSender->queue($data['email'], $subject, $body, [$pdfPath]);
        
        // Email all'associazione
        if (!empty($this->config['association']['email'])) {
            $subject = 'Nuova domanda di iscrizione';
            $body = '<p>È stata ricevuta una nuova domanda di iscrizione.</p>';
            $body .= '<p><strong>Nome:</strong> ' . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']) . '</p>';
            
            $emailSender->queue($this->config['association']['email'], $subject, $body, [$pdfPath]);
        }
    }
    
    /**
     * Invia email approvazione
     * 
     * @param array $application Dati domanda
     */
    private function sendApprovalEmail($application) {
        if (!($this->config['email']['enabled'] ?? false)) {
            return;
        }
        
        $emailSender = new EmailSender($this->config, $this->db);
        
        $subject = 'Domanda di iscrizione approvata';
        $body = '<p>Gentile ' . htmlspecialchars($application['first_name']) . ',</p>';
        $body .= '<p>La tua domanda di iscrizione è stata approvata!</p>';
        $body .= '<p>Benvenuto nella nostra associazione.</p>';
        
        $emailSender->queue($application['email'], $subject, $body);
    }
    
    /**
     * Invia email rifiuto
     * 
     * @param array $application Dati domanda
     * @param string $reason Motivazione
     */
    private function sendRejectionEmail($application, $reason) {
        if (!($this->config['email']['enabled'] ?? false)) {
            return;
        }
        
        $emailSender = new EmailSender($this->config, $this->db);
        
        $subject = 'Domanda di iscrizione';
        $body = '<p>Gentile ' . htmlspecialchars($application['first_name']) . ',</p>';
        $body .= '<p>Ci dispiace informarti che la tua domanda di iscrizione non può essere accettata.</p>';
        if ($reason) {
            $body .= '<p><strong>Motivazione:</strong> ' . htmlspecialchars($reason) . '</p>';
        }
        
        $emailSender->queue($application['email'], $subject, $body);
    }
    
    /**
     * Crea socio da domanda approvata
     * 
     * @param array $application Dati domanda
     * @param int $userId ID utente
     * @return int ID socio creato
     */
    private function createMember($application, $userId) {
        $memberController = new MemberController($this->db, $this->config);
        
        $data = [
            'last_name' => $application['last_name'],
            'first_name' => $application['first_name'],
            'birth_date' => $application['birth_date'],
            'birth_place' => $application['birth_place'],
            'birth_province' => $application['birth_province'],
            'tax_code' => $application['tax_code'],
            'gender' => $application['gender'],
            'nationality' => $application['nationality'],
            'member_type' => 'ordinario',
            'member_status' => 'attivo',
            'volunteer_status' => 'aspirante',
            'registration_date' => date('Y-m-d')
        ];
        
        return $memberController->create($data, $userId);
    }
    
    /**
     * Crea cadetto da domanda approvata
     * 
     * @param array $application Dati domanda
     * @param int $userId ID utente
     * @return int ID cadetto creato
     */
    private function createJuniorMember($application, $userId) {
        // Insert into junior_members table
        $sql = "INSERT INTO junior_members (
            registration_number, member_status,
            last_name, first_name, birth_date, birth_place, birth_province,
            tax_code, gender, nationality,
            registration_date,
            created_at, created_by
        ) VALUES (?, 'attivo', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        
        // Generate registration number
        $regNumber = $this->generateRegistrationNumber('junior');
        
        $params = [
            $regNumber,
            $application['last_name'],
            $application['first_name'],
            $application['birth_date'],
            $application['birth_place'],
            $application['birth_province'],
            $application['tax_code'],
            $application['gender'],
            $application['nationality'],
            date('Y-m-d'),
            $userId
        ];
        
        $this->db->execute($sql, $params);
        $juniorMemberId = $this->db->lastInsertId();
        
        // Add guardian data if exists
        $guardian = $this->db->fetchOne(
            "SELECT * FROM member_application_guardians WHERE application_id = ?",
            [$application['id']]
        );
        
        if ($guardian) {
            $sql = "INSERT INTO junior_member_guardians (
                junior_member_id, guardian_type,
                last_name, first_name, birth_date, birth_place,
                tax_code, phone, email
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $juniorMemberId,
                $guardian['guardian_type'] ?? 'parent',
                $guardian['last_name'],
                $guardian['first_name'],
                $guardian['birth_date'],
                $guardian['birth_place'],
                $guardian['tax_code'],
                $guardian['phone'],
                $guardian['email']
            ];
            
            $this->db->execute($sql, $params);
        }
        
        return $juniorMemberId;
    }
    
    /**
     * Genera numero registrazione per soci o cadetti
     * 
     * @param string $type 'member' o 'junior'
     * @return string
     */
    private function generateRegistrationNumber($type = 'member') {
        $table = $type === 'junior' ? 'junior_members' : 'members';
        
        $sql = "SELECT registration_number FROM $table 
                WHERE registration_number REGEXP '^[0-9]+$' 
                ORDER BY CAST(registration_number AS UNSIGNED) DESC 
                LIMIT 1";
        
        $last = $this->db->fetchOne($sql);
        
        if ($last && is_numeric($last['registration_number'])) {
            $nextNumber = intval($last['registration_number']) + 1;
        } else {
            $nextNumber = 1;
        }
        
        return str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Elimina domanda di iscrizione
     */
    public function delete($id, $userId) {
        try {
            // Get application details for log
            $application = $this->get($id);
            if (!$application) {
                return ['success' => false, 'message' => 'Domanda non trovata'];
            }
            
            // Prevent deletion of approved applications
            if ($application['status'] === 'approvata') {
                return ['success' => false, 'message' => 'Impossibile eliminare: domanda già approvata'];
            }
            
            // Delete application
            $sql = "DELETE FROM member_applications WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            // Log activity
            $this->logActivity(
                $userId, 
                'applications', 
                'delete', 
                $id, 
                "Eliminata domanda: {$application['first_name']} {$application['last_name']}"
            );
            
            return ['success' => true];
        } catch (\Exception $e) {
            error_log("Errore eliminazione domanda: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione'];
        }
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
