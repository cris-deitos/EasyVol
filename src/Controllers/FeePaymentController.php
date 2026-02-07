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
                AND m.member_status = 'attivo'
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
                AND jm.member_status = 'attivo'
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
                 COALESCE(m.first_name, jm.first_name) as first_name,
                 COALESCE(m.last_name, jm.last_name) as last_name,
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
     * Verifica se è possibile inviare promemoria per un determinato anno
     * (non inviato negli ultimi 20 giorni)
     * 
     * @param int $year Anno di riferimento
     * @return array ['can_send' => bool, 'last_sent' => string|null, 'days_since' => int|null]
     */
    public function canSendReminders($year = null) {
        if ($year === null) {
            $year = date('Y');
        }
        
        $sql = "SELECT sent_at 
                FROM fee_payment_reminders 
                WHERE year = ? 
                ORDER BY sent_at DESC 
                LIMIT 1";
        
        $stmt = $this->db->query($sql, [$year]);
        $lastReminder = $stmt->fetch();
        
        if (!$lastReminder) {
            return [
                'can_send' => true,
                'last_sent' => null,
                'days_since' => null
            ];
        }
        
        $lastSentDate = new \DateTime($lastReminder['sent_at']);
        $now = new \DateTime();
        $diff = $now->diff($lastSentDate);
        
        // Calculate days, handling false return case
        $daysSince = $diff !== false ? (int)$diff->days : 0;
        
        return [
            'can_send' => $daysSince >= 20,
            'last_sent' => $lastReminder['sent_at'],
            'days_since' => $daysSince
        ];
    }
    
    /**
     * Crea batch di promemoria per soci con quote non versate
     * Accoda direttamente le email in email_queue usando l'infrastruttura esistente
     * 
     * @param int $year Anno di riferimento
     * @param int $userId ID utente che richiede l'invio
     * @return int|false ID del batch creato o false
     */
    public function createReminderBatch($year, $userId) {
        try {
            // Check if can send
            $checkResult = $this->canSendReminders($year);
            if (!$checkResult['can_send']) {
                throw new \Exception('Promemoria già inviato negli ultimi 20 giorni');
            }
            
            // Get unpaid members (all, no pagination)
            $unpaidResult = $this->getUnpaidMembersForReminder($year);
            $unpaidMembers = $unpaidResult['members'];
            
            if (empty($unpaidMembers)) {
                throw new \Exception('Nessun socio trovato con quota non versata');
            }
            
            $this->db->beginTransaction();
            
            // Create EmailSender instance
            $emailSender = new EmailSender($this->config, $this->db);
            
            // Queue emails directly using existing email_queue infrastructure
            $totalQueued = 0;
            $subject = "Promemoria: Quota Associativa " . $year;
            
            foreach ($unpaidMembers as $member) {
                if (!empty($member['email'])) {
                    $body = $this->buildReminderEmailBody($member, $year);
                    
                    // Queue email with priority 2 (important but not urgent)
                    $queueId = $emailSender->queue(
                        $member['email'],
                        $subject,
                        $body,
                        [], // no attachments
                        2   // priority
                    );
                    
                    if ($queueId) {
                        $totalQueued++;
                    } else {
                        error_log("Failed to queue fee reminder email for member {$member['registration_number']} ({$member['email']})");
                    }
                }
            }
            
            if ($totalQueued === 0) {
                throw new \Exception('Nessuna email è stata accodata con successo');
            }
            
            // Insert single record in fee_payment_reminders for cooldown tracking
            $sql = "INSERT INTO fee_payment_reminders (year, sent_by, sent_at, total_queued) 
                    VALUES (?, ?, NOW(), ?)";
            $this->db->execute($sql, [$year, $userId, $totalQueued]);
            $reminderId = $this->db->lastInsertId();
            
            $this->db->commit();
            
            return $reminderId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error creating reminder batch: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni soci senza quota da includere nei promemoria (con email)
     * 
     * @param int $year Anno di riferimento
     * @return array
     */
    private function getUnpaidMembersForReminder($year) {
        // Get adult members without payment for the specified year (with email)
        $sqlAdult = "SELECT m.id, m.registration_number, m.first_name, m.last_name, 
                    mc.value as email, 'adult' as member_type
                    FROM members m
                    LEFT JOIN member_contacts mc ON m.id = mc.member_id AND mc.contact_type = 'email'
                    WHERE m.member_status = 'attivo'
                    AND mc.value IS NOT NULL AND mc.value != ''
                    AND NOT EXISTS (
                        SELECT 1 FROM member_fees mf 
                        WHERE mf.member_id = m.id 
                        AND mf.year = ?
                    )";
        
        // Get junior members without payment for the specified year (with email)
        $sqlJunior = "SELECT jm.id, jm.registration_number, jm.first_name, jm.last_name, 
                     jmc.value as email, 'junior' as member_type
                     FROM junior_members jm
                     LEFT JOIN junior_member_contacts jmc ON jm.id = jmc.junior_member_id AND jmc.contact_type = 'email'
                     WHERE jm.member_status = 'attivo'
                     AND jmc.value IS NOT NULL AND jmc.value != ''
                     AND NOT EXISTS (
                         SELECT 1 FROM junior_member_fees jmf 
                         WHERE jmf.junior_member_id = jm.id 
                         AND jmf.year = ?
                     )";
        
        $sql = "SELECT * FROM (
                    ($sqlAdult) UNION ALL ($sqlJunior)
                ) as combined
                ORDER BY CAST(registration_number AS UNSIGNED)";
        
        $stmt = $this->db->query($sql, [$year, $year]);
        $members = $stmt->fetchAll();
        
        return [
            'members' => $members,
            'total' => count($members)
        ];
    }
    
    
    /**
     * Costruisce corpo email promemoria
     * 
     * @param array $member Dati socio
     * @param int $year Anno
     * @return string
     */
    private function buildReminderEmailBody($member, $year) {
        // Escape all user data to prevent HTML injection
        $firstName = htmlspecialchars($member['first_name'], ENT_QUOTES, 'UTF-8');
        $lastName = htmlspecialchars($member['last_name'], ENT_QUOTES, 'UTF-8');
        $registrationNumber = htmlspecialchars($member['registration_number'], ENT_QUOTES, 'UTF-8');
        $yearEscaped = htmlspecialchars($year, ENT_QUOTES, 'UTF-8');
        
    $body = "
        <h2>Promemoria Pagamento Quota Associativa</h2>
        <p>Gentile {$firstName} {$lastName},</p>
        <p>Ti ricordiamo che non risulta ancora versata la quota associativa per l'anno <strong>{$yearEscaped}</strong>.</p>
        <p><strong>Dati:</strong></p>
        <ul>
            <li>Matricola: {$registrationNumber}</li>
            <li>Anno: {$yearEscaped}</li>
        </ul>
        <p>Ti invitiamo a provvedere al pagamento della quota associativa il prima possibile.</p>
        <p>Dopo aver effettuato il pagamento, puoi caricare la ricevuta attraverso il portale dedicato, 
        raggiungibile all'interno dei <strong>Servizi Digitali Interni</strong> 
        <a href=\"https://sdi.protezionecivilebassogarda.it/\" target=\"_blank\" style=\"color: #007bff; text-decoration: underline;\">qui</a>, 
        e direttamente a questo link: </p>
        <p style=\"text-align: center; margin:  20px 0;\">
            <a href=\"https://sdi.protezionecivilebassogarda.it/EasyVol/public/pay_fee.php\" 
               target=\"_blank\" 
               style=\"display: inline-block; padding: 12px 24px; background-color: #007bff; color:  white; text-decoration: none; border-radius: 5px; font-weight: bold;\">
                Carica Ricevuta Pagamento
            </a>
        </p>
        <p>Per qualsiasi chiarimento, non esitare a contattarci.</p>
        <p>Cordiali saluti,<br>Il Consiglio Direttivo</p>
    ";
        
        return $body;
    }
    
    /**
     * Ottieni soci attivi senza pagamento quota per l'anno specificato
     * 
     * @param int $year Anno di riferimento
     * @param int $page Pagina corrente
     * @param int $perPage Risultati per pagina
     * @param string $search Termine di ricerca per matricola, nome o cognome
     * @return array
     */
    public function getUnpaidMembers($year = null, $page = 1, $perPage = 20, $search = '') {
        if ($year === null) {
            $year = date('Y');
        }
        
        // Ensure page and perPage are integers to prevent SQL injection
        $page = max(1, intval($page));
        $perPage = max(1, min(100, intval($perPage))); // Max 100 per page
        
        // Build search parameters
        $searchCondition = '';
        $searchParams = [];
        
        if (!empty($search)) {
            $searchPattern = "%{$search}%";
            $searchParams = [$searchPattern, $searchPattern, $searchPattern];
            $searchCondition = " AND (m.registration_number LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ?)";
        }
        
        // Build search condition for junior members (replace 'm.' with 'jm.')
        $searchConditionJunior = '';
        if (!empty($search)) {
            $searchConditionJunior = " AND (jm.registration_number LIKE ? OR jm.first_name LIKE ? OR jm.last_name LIKE ?)";
        }
        
        // Get adult members without payment for the specified year
        $sqlAdult = "SELECT m.id, m.registration_number, m.first_name, m.last_name, 
                    m.member_status as status, 'adult' as member_type
                    FROM members m
                    WHERE m.member_status = 'attivo'
                    AND NOT EXISTS (
                        SELECT 1 FROM member_fees mf 
                        WHERE mf.member_id = m.id 
                        AND mf.year = ?
                    )" . $searchCondition;
        
        // Get junior members without payment for the specified year
        $sqlJunior = "SELECT jm.id, jm.registration_number, jm.first_name, jm.last_name, 
                     jm.member_status as status, 'junior' as member_type
                     FROM junior_members jm
                     WHERE jm.member_status = 'attivo'
                     AND NOT EXISTS (
                         SELECT 1 FROM junior_member_fees jmf 
                         WHERE jmf.junior_member_id = jm.id 
                         AND jmf.year = ?
                     )" . $searchConditionJunior;
        
        // Prepare parameters for queries
        $paramsAdult = array_merge([$year], $searchParams);
        $paramsJunior = array_merge([$year], $searchParams);
        
        // Get total count first (without pagination)
        $countSql = "SELECT COUNT(*) as total FROM (
                        ($sqlAdult) UNION ALL ($sqlJunior)
                     ) as combined";
        
        // Merge parameters for count query
        $mergedCountParams = array_merge($paramsAdult, $paramsJunior);
        $countStmt = $this->db->query($countSql, $mergedCountParams);
        $total = $countStmt->fetch()['total'];
        
        // Get paginated results - safe integer interpolation after validation
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM (
                    ($sqlAdult) UNION ALL ($sqlJunior)
                ) as combined
                ORDER BY CAST(registration_number AS UNSIGNED)
                LIMIT $perPage OFFSET $offset";
        
        // Merge parameters for main query (same as count query)
        $mergedParams = array_merge($paramsAdult, $paramsJunior);
        $stmt = $this->db->query($sql, $mergedParams);
        $members = $stmt->fetchAll();
        
        return [
            'members' => $members,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Ottieni tutti i soci senza quota versata per l'anno specificato (senza paginazione)
     * Per export PDF
     * 
     * @param int $year Anno di riferimento
     * @return array Lista di tutti i soci senza quota versata, ordinati per matricola
     */
    public function getAllUnpaidMembersForExport($year = null) {
        if ($year === null) {
            $year = date('Y');
        }
        
        // Get adult members without payment for the specified year
        $sqlAdult = "SELECT m.id, m.registration_number, m.first_name, m.last_name, 
                    'adult' as member_type
                    FROM members m
                    WHERE m.member_status = 'attivo'
                    AND NOT EXISTS (
                        SELECT 1 FROM member_fees mf 
                        WHERE mf.member_id = m.id 
                        AND mf.year = ?
                    )";
        
        // Get junior members without payment for the specified year
        $sqlJunior = "SELECT jm.id, jm.registration_number, jm.first_name, jm.last_name, 
                     'junior' as member_type
                     FROM junior_members jm
                     WHERE jm.member_status = 'attivo'
                     AND NOT EXISTS (
                         SELECT 1 FROM junior_member_fees jmf 
                         WHERE jmf.junior_member_id = jm.id 
                         AND jmf.year = ?
                     )";
        
        // Combine both queries and order by registration_number
        $sql = "SELECT * FROM (
                    ($sqlAdult) UNION ALL ($sqlJunior)
                ) as combined
                ORDER BY CAST(registration_number AS UNSIGNED) ASC";
        
        $stmt = $this->db->query($sql, [$year, $year]);
        return $stmt->fetchAll();
    }
}
