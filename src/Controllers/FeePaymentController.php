<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Utils\FileUploader;
use EasyVol\Utils\EmailSender;

/**
 * Fee Payment Controller
 * 
 * Gestisce le richieste di pagamento quote associative
 */
class FeePaymentController {
    private $db;
    private $config;
    
    // Member type constants
    const MEMBER_TYPE_ADULT = 'adult';
    const MEMBER_TYPE_JUNIOR = 'junior';
    
    /**
     * Constructor
     * 
     * @param Database $db Database instance
     * @param array $config Configuration
     */
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Determina se la matricola appartiene a un socio minorenne (cadetto)
     * 
     * @param string $registrationNumber Matricola
     * @return bool
     */
    public static function isJuniorMember($registrationNumber) {
        return strtoupper(substr($registrationNumber, 0, 1)) === 'C';
    }
    
    /**
     * Verifica match matricola e cognome socio
     * 
     * @param string $registrationNumber Matricola
     * @param string $lastName Cognome
     * @return array|false Dati socio se trovato, false altrimenti
     */
    public function verifyMember($registrationNumber, $lastName) {
        // Check if registration number starts with 'C' for junior members (cadetti minorenni)
        if (self::isJuniorMember($registrationNumber)) {
            return $this->verifyJuniorMember($registrationNumber, $lastName);
        }
        
        // Regular member verification
        $sql = "SELECT m.id, m.registration_number, m.last_name, m.first_name, 
                mc.value as email, '" . self::MEMBER_TYPE_ADULT . "' as member_type
                FROM members m
                LEFT JOIN member_contacts mc ON m.id = mc.member_id 
                    AND mc.contact_type = 'email'
                WHERE m.registration_number = ? 
                AND LOWER(m.last_name) = LOWER(?)
                LIMIT 1";
        
        $stmt = $this->db->query($sql, [$registrationNumber, $lastName]);
        return $stmt->fetch();
    }
    
    /**
     * Verifica match matricola e cognome socio minorenne
     * 
     * @param string $registrationNumber Matricola
     * @param string $lastName Cognome
     * @return array|false Dati socio minorenne se trovato, false altrimenti
     */
    private function verifyJuniorMember($registrationNumber, $lastName) {
        $sql = "SELECT jm.id, jm.registration_number, jm.last_name, jm.first_name, 
                jmc.value as email, '" . self::MEMBER_TYPE_JUNIOR . "' as member_type
                FROM junior_members jm
                LEFT JOIN junior_member_contacts jmc ON jm.id = jmc.junior_member_id 
                    AND jmc.contact_type = 'email'
                WHERE jm.registration_number = ? 
                AND LOWER(jm.last_name) = LOWER(?)
                LIMIT 1";
        
        $stmt = $this->db->query($sql, [$registrationNumber, $lastName]);
        return $stmt->fetch();
    }
    
    /**
     * Crea richiesta pagamento quota
     * 
     * @param array $data Dati richiesta
     * @return int|false ID richiesta o false
     */
    public function createPaymentRequest($data) {
        try {
            $sql = "INSERT INTO fee_payment_requests (
                registration_number, last_name, payment_year, 
                payment_date, amount, receipt_file, status, submitted_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
            
            $params = [
                $data['registration_number'],
                $data['last_name'],
                $data['payment_year'],
                $data['payment_date'],
                $data['amount'] ?? null,
                $data['receipt_file']
            ];
            
            $this->db->execute($sql, $params);
            $requestId = $this->db->lastInsertId();
            
            // Note: Email sending is handled by sendSubmissionEmails() method
            // called from the controller to avoid duplicate emails
            
            return $requestId;
        } catch (\Exception $e) {
            error_log("Error creating payment request: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni richieste pagamento con filtri
     * 
     * @param array $filters Filtri
     * @param int $page Pagina
     * @param int $perPage Risultati per pagina
     * @return array
     */
    public function getPaymentRequests($filters = [], $page = 1, $perPage = 20) {
        // Note: Registration numbers starting with 'C' are junior members, others are adult members
        // This ensures no overlap between the two tables for the same registration number
        $sql = "SELECT fpr.*, 
                COALESCE(m.id, jm.id) as member_id, 
                COALESCE(m.first_name, jm.first_name) as first_name,
                COALESCE(m.last_name, jm.last_name) as last_name,
                u.full_name as processed_by_name
                FROM fee_payment_requests fpr
                LEFT JOIN members m ON fpr.registration_number = m.registration_number
                LEFT JOIN junior_members jm ON fpr.registration_number = jm.registration_number
                LEFT JOIN users u ON fpr.processed_by = u.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND fpr.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['year'])) {
            $sql .= " AND fpr.payment_year = ?";
            $params[] = $filters['year'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (fpr.registration_number LIKE ? OR fpr.last_name LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY fpr.submitted_at DESC";
        
        // Pagination
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT $perPage OFFSET $offset";
        
        $stmt = $this->db->query($sql, $params);
        $requests = $stmt->fetchAll();
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM fee_payment_requests fpr WHERE 1=1";
        $countParams = [];
        
        if (!empty($filters['status'])) {
            $countSql .= " AND fpr.status = ?";
            $countParams[] = $filters['status'];
        }
        
        if (!empty($filters['year'])) {
            $countSql .= " AND fpr.payment_year = ?";
            $countParams[] = $filters['year'];
        }
        
        if (!empty($filters['search'])) {
            $countSql .= " AND (fpr.registration_number LIKE ? OR fpr.last_name LIKE ?)";
            $search = "%{$filters['search']}%";
            $countParams[] = $search;
            $countParams[] = $search;
        }
        
        $countStmt = $this->db->query($countSql, $countParams);
        $total = $countStmt->fetch()['total'];
        
        return [
            'requests' => $requests,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Approva richiesta pagamento
     * 
     * @param int $requestId ID richiesta
     * @param int $userId ID utente che approva
     * @return bool
     */
    public function approvePaymentRequest($requestId, $userId) {
        try {
            $this->db->beginTransaction();
            
            // Get request details and check both adult and junior members
            $stmt = $this->db->query(
                "SELECT fpr.*, 
                 m.id as member_id,
                 jm.id as junior_member_id,
                 CASE 
                     WHEN m.id IS NOT NULL THEN '" . self::MEMBER_TYPE_ADULT . "'
                     WHEN jm.id IS NOT NULL THEN '" . self::MEMBER_TYPE_JUNIOR . "'
                     ELSE NULL
                 END as member_type
                FROM fee_payment_requests fpr
                LEFT JOIN members m ON fpr.registration_number = m.registration_number
                LEFT JOIN junior_members jm ON fpr.registration_number = jm.registration_number
                WHERE fpr.id = ?",
                [$requestId]
            );
            $request = $stmt->fetch();
            
            if (!$request || $request['status'] !== 'pending') {
                throw new \Exception('Richiesta non valida o già processata');
            }
            
            if (!$request['member_id'] && !$request['junior_member_id']) {
                throw new \Exception('Socio non trovato');
            }
            
            // Insert into appropriate fees table based on member type
            if ($request['member_type'] === self::MEMBER_TYPE_JUNIOR) {
                // Insert into junior_member_fees
                $sql = "INSERT INTO junior_member_fees (
                    junior_member_id, year, payment_date, amount, receipt_file, 
                    verified, verified_by, verified_at
                ) VALUES (?, ?, ?, ?, ?, 1, ?, NOW())";
                
                $this->db->execute($sql, [
                    $request['junior_member_id'],
                    $request['payment_year'],
                    $request['payment_date'],
                    $request['amount'] ?? null,
                    $request['receipt_file'],
                    $userId
                ]);
            } else {
                // Insert into member_fees
                $sql = "INSERT INTO member_fees (
                    member_id, year, payment_date, amount, receipt_file, 
                    verified, verified_by, verified_at
                ) VALUES (?, ?, ?, ?, ?, 1, ?, NOW())";
                
                $this->db->execute($sql, [
                    $request['member_id'],
                    $request['payment_year'],
                    $request['payment_date'],
                    $request['amount'] ?? null,
                    $request['receipt_file'],
                    $userId
                ]);
            }
            
            // Update request status
            $this->db->execute(
                "UPDATE fee_payment_requests 
                SET status = 'approved', processed_at = NOW(), processed_by = ?
                WHERE id = ?",
                [$userId, $requestId]
            );
            
            $this->db->commit();
            
            // Send approval email using new method
            try {
                // Try to get member data from both adult and junior members tables
                $feeRequest = null;
                
                // First try adult members
                $feeRequest = $this->db->fetchOne(
                    "SELECT fpr.*, m.first_name, m.last_name, mc.value as email 
                     FROM fee_payment_requests fpr
                     JOIN members m ON fpr.registration_number = m.registration_number
                     LEFT JOIN member_contacts mc ON m.id = mc.member_id AND mc.contact_type = 'email'
                     WHERE fpr.id = ?",
                    [$requestId]
                );
                
                // If not found in adult members, try junior members
                if (!$feeRequest) {
                    $feeRequest = $this->db->fetchOne(
                        "SELECT fpr.*, jm.first_name, jm.last_name, jmc.value as email 
                         FROM fee_payment_requests fpr
                         JOIN junior_members jm ON fpr.registration_number = jm.registration_number
                         LEFT JOIN junior_member_contacts jmc ON jm.id = jmc.junior_member_id AND jmc.contact_type = 'email'
                         WHERE fpr.id = ?",
                        [$requestId]
                    );
                }
                
                if ($feeRequest && !empty($feeRequest['email'])) {
                    require_once __DIR__ . '/../Utils/EmailSender.php';
                    $emailSender = new \EasyVol\Utils\EmailSender($this->config, $this->db);
                    $memberData = [
                        'first_name' => $feeRequest['first_name'],
                        'last_name' => $feeRequest['last_name'],
                        'email' => $feeRequest['email']
                    ];
                    $emailSender->sendFeePaymentApprovedEmail($memberData, $feeRequest);
                }
            } catch (\Exception $e) {
                error_log("Fee approval email failed: " . $e->getMessage());
            }
            
            // Send Telegram notification
            try {
                require_once __DIR__ . '/../Services/TelegramService.php';
                $telegramService = new \EasyVol\Services\TelegramService($this->db, $this->config);
                
                if ($telegramService->isEnabled()) {
                    $message = "💰 <b>Nuovo pagamento quota associativa approvato</b>\n\n";
                    $message .= "👤 <b>Socio:</b> " . htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) . "\n";
                    $message .= "🔢 <b>Matricola:</b> " . htmlspecialchars($request['registration_number']) . "\n";
                    $message .= "📅 <b>Anno:</b> " . $request['payment_year'] . "\n";
                    $message .= "💵 <b>Data pagamento:</b> " . date('d/m/Y', strtotime($request['payment_date'])) . "\n";
                    if ($request['amount']) {
                        $message .= "💸 <b>Importo:</b> €" . number_format($request['amount'], 2, ',', '.') . "\n";
                    }
                    
                    $telegramService->sendNotification('fee_payment', $message);
                }
            } catch (\Exception $e) {
                error_log("Errore invio notifica Telegram per pagamento: " . $e->getMessage());
            }
            
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error approving payment request: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rifiuta richiesta pagamento
     * 
     * @param int $requestId ID richiesta
     * @param int $userId ID utente che rifiuta
     * @return bool
     */
    public function rejectPaymentRequest($requestId, $userId) {
        try {
            // Get request details
            $stmt = $this->db->query(
                "SELECT fpr.* FROM fee_payment_requests fpr WHERE fpr.id = ?",
                [$requestId]
            );
            $request = $stmt->fetch();
            
            if (!$request || $request['status'] !== 'pending') {
                return false;
            }
            
            // Update request status
            $this->db->execute(
                "UPDATE fee_payment_requests 
                SET status = 'rejected', processed_at = NOW(), processed_by = ?
                WHERE id = ?",
                [$userId, $requestId]
            );
            
            // Send rejection email
            $this->sendRejectionEmail($request);
            
            return true;
        } catch (\Exception $e) {
            error_log("Error rejecting payment request: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Invia email conferma caricamento richiesta
     * 
     * @param array $member Dati socio
     * @param array $request Dati richiesta
     * @return bool
     */
    public function sendSubmissionEmails($member, $request) {
        try {
            $emailSender = new EmailSender($this->config, $this->db);
            
            // Get association email
            $assocStmt = $this->db->query("SELECT email FROM association LIMIT 1");
            $assoc = $assocStmt->fetch();
            $assocEmail = $assoc['email'] ?? null;
            
            // Email to member
            if (!empty($member['email'])) {
                $memberSubject = "Ricevuta di pagamento quota ricevuta";
                $amountText = !empty($request['amount']) ? "<li>Importo: €" . htmlspecialchars(number_format($request['amount'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') . "</li>" : "";
                $memberBody = "
                    <h2>Conferma Ricezione Ricevuta</h2>
                    <p>Gentile {$member['first_name']} {$member['last_name']},</p>
                    <p>Abbiamo ricevuto la tua ricevuta di pagamento per la quota associativa dell'anno {$request['payment_year']}.</p>
                    <p><strong>Dettagli:</strong></p>
                    <ul>
                        <li>Matricola: {$member['registration_number']}</li>
                        <li>Anno: {$request['payment_year']}</li>
                        <li>Data pagamento: {$request['payment_date']}</li>
                        {$amountText}
                    </ul>
                    <p>La tua richiesta è in attesa di verifica. Riceverai una conferma via email non appena sarà approvata.</p>
                    <p>Grazie per la collaborazione.</p>
                ";
                
                $emailSender->send($member['email'], $memberSubject, $memberBody);
            }
            
            // Email to association
            if (!empty($assocEmail)) {
                $amountTextAssoc = !empty($request['amount']) ? "<li>Importo: €" . htmlspecialchars(number_format($request['amount'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') . "</li>" : "";
                $assocSubject = "Nuova ricevuta pagamento quota da verificare";
                $assocBody = "
                    <h2>Nuova Richiesta Pagamento Quota</h2>
                    <p>È stata ricevuta una nuova ricevuta di pagamento quota da verificare.</p>
                    <p><strong>Dettagli:</strong></p>
                    <ul>
                        <li>Socio: {$member['first_name']} {$member['last_name']}</li>
                        <li>Matricola: {$member['registration_number']}</li>
                        <li>Anno: {$request['payment_year']}</li>
                        <li>Data pagamento: {$request['payment_date']}</li>
                        {$amountTextAssoc}
                        <li>Data invio: " . date('d/m/Y H:i') . "</li>
                    </ul>
                    <p>Accedi al gestionale per verificare e approvare la richiesta.</p>
                ";
                
                $emailSender->send($assocEmail, $assocSubject, $assocBody);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Error sending submission emails: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Invia email approvazione
     * 
     * @param array $request Dati richiesta
     * @return bool
     */
    private function sendApprovalEmail($request) {
        try {
            $emailSender = new EmailSender($this->config, $this->db);
            
            // Get member email - try adult members first
            $stmt = $this->db->query(
                "SELECT m.first_name, m.last_name, mc.value as email
                FROM members m
                LEFT JOIN member_contacts mc ON m.id = mc.member_id AND mc.contact_type = 'email'
                WHERE m.registration_number = ?",
                [$request['registration_number']]
            );
            $member = $stmt->fetch();
            
            // If not found, try junior members
            if (!$member) {
                $stmt = $this->db->query(
                    "SELECT jm.first_name, jm.last_name, jmc.value as email
                    FROM junior_members jm
                    LEFT JOIN junior_member_contacts jmc ON jm.id = jmc.junior_member_id AND jmc.contact_type = 'email'
                    WHERE jm.registration_number = ?",
                    [$request['registration_number']]
                );
                $member = $stmt->fetch();
            }
            
            if (!empty($member['email'])) {
                $subject = "Pagamento quota approvato";
                $body = "
                    <h2>Pagamento Quota Approvato</h2>
                    <p>Gentile {$member['first_name']} {$member['last_name']},</p>
                    <p>Il tuo pagamento della quota associativa per l'anno {$request['payment_year']} è stato verificato e approvato.</p>
                    <p>Grazie per il tuo contributo!</p>
                ";
                
                $emailSender->send($member['email'], $subject, $body);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Error sending approval email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Invia email rifiuto
     * 
     * @param array $request Dati richiesta
     * @return bool
     */
    private function sendRejectionEmail($request) {
        try {
            $emailSender = new EmailSender($this->config, $this->db);
            
            // Get member email - try adult members first
            $stmt = $this->db->query(
                "SELECT m.first_name, m.last_name, mc.value as email
                FROM members m
                LEFT JOIN member_contacts mc ON m.id = mc.member_id AND mc.contact_type = 'email'
                WHERE m.registration_number = ?",
                [$request['registration_number']]
            );
            $member = $stmt->fetch();
            
            // If not found, try junior members
            if (!$member) {
                $stmt = $this->db->query(
                    "SELECT jm.first_name, jm.last_name, jmc.value as email
                    FROM junior_members jm
                    LEFT JOIN junior_member_contacts jmc ON jm.id = jmc.junior_member_id AND jmc.contact_type = 'email'
                    WHERE jm.registration_number = ?",
                    [$request['registration_number']]
                );
                $member = $stmt->fetch();
            }
            
            if (!empty($member['email'])) {
                $subject = "Ricevuta pagamento quota non approvata";
                $body = "
                    <h2>Ricevuta Pagamento Non Approvata</h2>
                    <p>Gentile {$member['first_name']} {$member['last_name']},</p>
                    <p>La ricevuta di pagamento della quota associativa per l'anno {$request['payment_year']} non è stata approvata.</p>
                    <p>Ti preghiamo di contattare l'associazione per maggiori informazioni.</p>
                ";
                
                $emailSender->send($member['email'], $subject, $body);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Error sending rejection email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni statistiche richieste pagamento
     * 
     * @return array
     */
    public function getStatistics() {
        $sql = "SELECT 
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count
                FROM fee_payment_requests";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }
    
    /**
     * Ottieni soci attivi senza pagamento quota per l'anno specificato
     * 
     * @param int $year Anno di riferimento
     * @param int $page Pagina corrente
     * @param int $perPage Risultati per pagina
     * @return array
     */
    public function getUnpaidMembers($year = null, $page = 1, $perPage = 20) {
        if ($year === null) {
            $year = date('Y');
        }
        
        // Get adult members without payment for the specified year
        $sqlAdult = "SELECT m.id, m.registration_number, m.first_name, m.last_name, 
                    m.status, 'adult' as member_type
                    FROM members m
                    WHERE m.status = 'attivo'
                    AND NOT EXISTS (
                        SELECT 1 FROM member_fees mf 
                        WHERE mf.member_id = m.id 
                        AND mf.year = ?
                    )";
        
        // Get junior members without payment for the specified year
        $sqlJunior = "SELECT jm.id, jm.registration_number, jm.first_name, jm.last_name, 
                     jm.status, 'junior' as member_type
                     FROM junior_members jm
                     WHERE jm.status = 'attivo'
                     AND NOT EXISTS (
                         SELECT 1 FROM junior_member_fees jmf 
                         WHERE jmf.junior_member_id = jm.id 
                         AND jmf.year = ?
                     )";
        
        // Union both queries
        $sql = "($sqlAdult) UNION ALL ($sqlJunior) ORDER BY registration_number";
        
        // Get total count first
        $countSql = "SELECT COUNT(*) as total FROM (($sqlAdult) UNION ALL ($sqlJunior)) as combined";
        $countStmt = $this->db->query($countSql, [$year, $year]);
        $total = $countStmt->fetch()['total'];
        
        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT $perPage OFFSET $offset";
        
        $stmt = $this->db->query($sql, [$year, $year]);
        $members = $stmt->fetchAll();
        
        return [
            'members' => $members,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
}
