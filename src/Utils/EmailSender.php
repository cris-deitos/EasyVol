<?php
namespace EasyVol\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Email Sender Utility
 * 
 * Gestisce l'invio di email per EasyVol usando PHPMailer con supporto SMTP
 * Supporta sia invio tramite SMTP che tramite sendmail (mail() PHP)
 */
class EmailSender {
    /**
     * Fallback base URL when no configuration is provided
     * This maintains backwards compatibility with the original hardcoded value.
     * Users should configure email.base_url in settings for their installation.
     */
    const FALLBACK_BASE_URL = 'https://sdi.protezionecivilebassogarda.it/EasyVol';
    
    private $config;
    private $db;
    private $emailLogsTableExists = null;
    
    /**
     * Constructor
     * 
     * @param array $config Configurazione applicazione
     * @param \EasyVol\Database $db Database instance
     */
    public function __construct($config, $db = null) {
        $this->config = $config;
        $this->db = $db;
    }
    
    /**
     * Resolve base URL for email links from configuration
     * 
     * @return string Base URL (without trailing slash)
     */
    private function resolveEmailBaseUrl() {
        // Try email base_url first (preferred)
        $baseUrl = $this->config['email']['base_url'] ?? '';
        
        if (empty($baseUrl)) {
            // Fallback to app base_url for backwards compatibility
            $baseUrl = $this->config['app']['base_url'] ?? '';
        }
        
        if (empty($baseUrl)) {
            // Try to construct from common settings
            $baseUrl = $this->config['app']['url'] ?? '';
        }
        
        // If still empty, use fallback constant
        if (empty($baseUrl)) {
            $baseUrl = self::FALLBACK_BASE_URL;
            error_log("Warning: email.base_url not configured in settings. Using fallback URL: " . self::FALLBACK_BASE_URL);
        }
        
        return rtrim($baseUrl, '/');
    }
    
    /**
     * Create and configure PHPMailer instance
     * 
     * @return PHPMailer
     */
    private function createMailer() {
        // Check if PHPMailer is available
        if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            throw new \Exception('PHPMailer non è installato. Eseguire: composer install');
        }
        
        $mailer = new PHPMailer(true); // Enable exceptions
        
        // Get email method from config (smtp or sendmail)
        $method = $this->config['email']['method'] ?? 'smtp';
        
        if ($method === 'smtp') {
            // Configure SMTP
            $mailer->isSMTP();
            $mailer->Host = $this->config['email']['smtp_host'] ?? '';
            $mailer->Port = intval($this->config['email']['smtp_port'] ?? 587);
            
            // SMTP Authentication
            $smtpAuth = $this->config['email']['smtp_auth'] ?? true;
            if ($smtpAuth) {
                $mailer->SMTPAuth = true;
                $mailer->Username = $this->config['email']['smtp_username'] ?? '';
                $mailer->Password = $this->config['email']['smtp_password'] ?? '';
            } else {
                $mailer->SMTPAuth = false;
            }
            
            // SMTP Encryption
            $encryption = $this->config['email']['smtp_encryption'] ?? 'tls';
            if ($encryption === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mailer->SMTPSecure = '';
                $mailer->SMTPAutoTLS = false;
            }
            
            // Debug mode - WARNING: This logs sensitive information including auth details
            $debug = $this->config['email']['smtp_debug'] ?? false;
            if ($debug) {
                $mailer->SMTPDebug = SMTP::DEBUG_SERVER;
                // Filter sensitive data from debug output
                $mailer->Debugoutput = function($str, $level) {
                    // Mask password and auth data in debug output
                    $filtered = preg_replace('/AUTH LOGIN.*$/mi', 'AUTH LOGIN [CREDENTIALS HIDDEN]', $str);
                    $filtered = preg_replace('/\d{3}.*base64.*/i', '[BASE64 AUTH DATA HIDDEN]', $filtered);
                    error_log("SMTP DEBUG [$level]: $filtered");
                };
            }
        } else {
            // Use PHP mail() function via sendmail
            $mailer->isMail();
        }
        
        // Character encoding
        $mailer->CharSet = $this->config['email']['charset'] ?? PHPMailer::CHARSET_UTF8;
        $mailer->Encoding = PHPMailer::ENCODING_BASE64;
        
        // Set From address
        $fromEmail = $this->config['email']['from_address'] ?? $this->config['email']['from_email'] ?? 'noreply@localhost';
        $fromName = $this->config['email']['from_name'] ?? 'EasyVol';
        $mailer->setFrom($fromEmail, $fromName);
        
        // Set Reply-To if configured
        $replyTo = $this->config['email']['reply_to'] ?? '';
        if (!empty($replyTo)) {
            $mailer->addReplyTo($replyTo);
        }
        
        // Set Return-Path (bounce address)
        $returnPath = $this->config['email']['return_path'] ?? '';
        if (!empty($returnPath)) {
            $mailer->Sender = $returnPath;
        }
        
        // HTML email
        $mailer->isHTML(true);
        
        // Add custom X-Mailer header
        $mailer->XMailer = 'EasyVol/' . ($this->config['app']['version'] ?? '1.0');
        
        return $mailer;
    }
    
    /**
     * Invia email
     * 
     * @param string|array $to Destinatario/i
     * @param string $subject Oggetto
     * @param string $body Corpo HTML
     * @param array $attachments Allegati (array di ['path' => '/path/to/file', 'name' => 'filename.pdf'])
     * @param string|array $cc CC recipients
     * @param string|array $bcc BCC recipients
     * @param string $replyTo Reply-To email (overrides config)
     * @return bool
     */
    public function send($to, $subject, $body, $attachments = [], $cc = [], $bcc = [], $replyTo = null) {
        try {
            // Check if email is enabled
            if (!($this->config['email']['enabled'] ?? false)) {
                error_log('Email sending is disabled in configuration');
                return false;
            }
            
            // Get primary recipient email
            $toEmail = $this->extractPrimaryEmailAddress($to);
            
            // Validate email
            if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception("Invalid email address: $toEmail");
            }
            
            // Create and configure mailer
            $mailer = $this->createMailer();
            
            // Add recipient(s)
            if (is_array($to)) {
                foreach ($to as $email => $name) {
                    if (is_numeric($email)) {
                        // Indexed array: value is the email
                        $mailer->addAddress($name);
                    } else {
                        // Associative array: key is email, value is name
                        $mailer->addAddress($email, $name);
                    }
                }
            } else {
                $mailer->addAddress($toEmail);
            }
            
            // Add CC
            if (!empty($cc)) {
                $ccList = is_array($cc) ? $cc : [$cc];
                foreach ($ccList as $ccEmail) {
                    if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                        $mailer->addCC($ccEmail);
                    }
                }
            }
            
            // Add BCC
            if (!empty($bcc)) {
                $bccList = is_array($bcc) ? $bcc : [$bcc];
                foreach ($bccList as $bccEmail) {
                    if (filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
                        $mailer->addBCC($bccEmail);
                    }
                }
            }
            
            // Override Reply-To if provided
            if (!empty($replyTo) && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mailer->clearReplyTos();
                $mailer->addReplyTo($replyTo);
            }
            
            // Set subject and body
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->AltBody = strip_tags($body); // Plain text alternative
            
            // Add attachments
            if (!empty($attachments) && is_array($attachments)) {
                foreach ($attachments as $attachment) {
                    if (isset($attachment['path']) && file_exists($attachment['path'])) {
                        // Validate file size (max 10MB)
                        $fileSize = filesize($attachment['path']);
                        $maxSize = 10 * 1024 * 1024; // 10MB
                        if ($fileSize > $maxSize) {
                            error_log("Attachment too large: {$attachment['path']} ($fileSize bytes)");
                            continue;
                        }
                        
                        $fileName = $attachment['name'] ?? basename($attachment['path']);
                        $mailer->addAttachment($attachment['path'], $fileName);
                    }
                }
            }
            
            // Send email
            $result = $mailer->send();
            
            // Log success
            if ($this->db) {
                $this->logEmail($to, $subject, $body, 'sent', '');
            }
            
            return true;
            
        } catch (PHPMailerException $e) {
            $errorMessage = "PHPMailer Error: " . $e->getMessage();
            error_log("Email send failed: " . $errorMessage);
            
            // Log failure
            if ($this->db) {
                $this->logEmail($to, $subject, $body, 'failed', $errorMessage);
            }
            
            return false;
            
        } catch (\Exception $e) {
            error_log("Email send failed: " . $e->getMessage());
            
            // Log failure
            if ($this->db) {
                $this->logEmail($to, $subject, $body, 'failed', $e->getMessage());
            }
            
            return false;
        }
    }
    
    /**
     * Invia email da template
     * 
     * @param string|array $to Destinatario/i
     * @param string $templateName Nome template
     * @param array $data Dati per template
     * @param array $attachments Allegati
     * @return bool
     */
    public function sendFromTemplate($to, $templateName, $data, $attachments = []) {
        if (!$this->db) {
            error_log("Database required for template emails");
            return false;
        }
        
        // Get template from database
        $template = $this->db->fetchOne(
            "SELECT * FROM email_templates WHERE template_name = ?",
            [$templateName]
        );
        
        if (!$template) {
            error_log("Email template not found: $templateName");
            return false;
        }
        
        // Replace variables in subject and body
        $subject = $this->replaceVariables($template['subject'], $data);
        $body = $this->replaceVariables($template['body_html'], $data);
        
        return $this->send($to, $subject, $body, $attachments);
    }
    
    /**
     * Sostituisce variabili nel testo
     * 
     * @param string $text Testo con variabili
     * @param array $data Dati da sostituire
     * @return string
     */
    private function replaceVariables($text, $data) {
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $text = str_replace("{{" . $key . "}}", $value, $text);
            }
        }
        
        return $text;
    }
    
    /**
     * Aggiunge email alla coda per invio asincrono
     * 
     * @param string|array $to Destinatario/i
     * @param string $subject Oggetto
     * @param string $body Corpo
     * @param array $attachments Allegati
     * @param int $priority Priorità (1-5, 1=alta)
     * @param string $scheduledAt Data/ora programmata
     * @return int|bool ID coda o false
     */
    public function queue($to, $subject, $body, $attachments = [], $priority = 3, $scheduledAt = null) {
        if (!$this->db) {
            error_log("Database required for email queue");
            return false;
        }
        
        try {
            $toJson = is_array($to) ? json_encode($to) : $to;
            $attachmentsJson = !empty($attachments) ? json_encode($attachments) : null;
            
            $sql = "INSERT INTO email_queue 
                    (recipient, subject, body, attachments, priority, status, scheduled_at, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())";
            
            $params = [
                $toJson,
                $subject,
                $body,
                $attachmentsJson,
                $priority,
                $scheduledAt
            ];
            
            $this->db->execute($sql, $params);
            
            return $this->db->lastInsertId();
            
        } catch (\Exception $e) {
            error_log("Failed to queue email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if email_logs table exists (cached)
     * 
     * @return bool
     */
    private function emailLogsTableExists() {
        if ($this->emailLogsTableExists === null) {
            try {
                $tableCheck = $this->db->fetchOne(
                    "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES 
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_logs'"
                );
                $this->emailLogsTableExists = ($tableCheck && $tableCheck['count'] > 0);
            } catch (\Exception $e) {
                // If check fails, assume table doesn't exist
                $this->emailLogsTableExists = false;
            }
        }
        return $this->emailLogsTableExists;
    }
    
    /**
     * Registra email inviata nel database
     * 
     * @param string|array $to Destinatario
     * @param string $subject Oggetto
     * @param string $body Corpo
     * @param string $status Stato
     * @param string $error Errore se presente
     */
    private function logEmail($to, $subject, $body, $status, $error) {
        try {
            // Check if email_logs table exists (cached check)
            if (!$this->emailLogsTableExists()) {
                // Table doesn't exist, skip logging silently
                return;
            }
            
            $toStr = is_array($to) ? json_encode($to) : $to;
            
            $sql = "INSERT INTO email_logs 
                    (recipient, subject, body, status, error_message, sent_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            
            $this->db->execute($sql, [$toStr, $subject, $body, $status, $error]);
            
        } catch (\Exception $e) {
            // Silently fail email logging - it's not critical
            error_log("Failed to log email: " . $e->getMessage());
        }
    }
    
    /**
     * Processa code email (da chiamare via cron)
     * 
     * @param int $limit Numero massimo di email da processare
     * @return int Numero di email inviate
     */
    public function processQueue($limit = 50) {
        if (!$this->db) {
            return 0;
        }
        
        // Get pending emails
        $sql = "SELECT * FROM email_queue 
                WHERE status = 'pending' 
                AND (scheduled_at IS NULL OR scheduled_at <= NOW()) 
                ORDER BY priority ASC, created_at ASC 
                LIMIT ?";
        
        $emails = $this->db->fetchAll($sql, [$limit]);
        
        $sent = 0;
        
        foreach ($emails as $email) {
            // Decode recipient and attachments
            $to = json_decode($email['recipient'], true) ?? $email['recipient'];
            $attachments = json_decode($email['attachments'], true) ?? [];
            
            // Update status to processing
            $this->db->execute(
                "UPDATE email_queue SET status = 'processing' WHERE id = ?",
                [$email['id']]
            );
            
            // Try to send
            $success = $this->send($to, $email['subject'], $email['body'], $attachments);
            
            if ($success) {
                // Mark as sent
                $this->db->execute(
                    "UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?",
                    [$email['id']]
                );
                $sent++;
            } else {
                // Mark as failed
                $this->db->execute(
                    "UPDATE email_queue SET status = 'failed', attempts = attempts + 1 WHERE id = ?",
                    [$email['id']]
                );
            }
            
            // Small delay between emails to avoid rate limiting
            usleep(100000); // 0.1 seconds
        }
        
        return $sent;
    }
    
    /**
     * Estrae l'indirizzo email primario da un valore string o array
     * 
     * @param string|array $to Destinatario/i
     * @return string Indirizzo email primario
     */
    private function extractPrimaryEmailAddress($to) {
        if (is_array($to)) {
            // If array has string keys (email => name), get first key
            $firstKey = array_key_first($to);
            return is_numeric($firstKey) ? reset($to) : $firstKey;
        }
        
        return $to;
    }
    
    /**
     * Send membership application confirmation email with PDF attachment
     * 
     * @param array $application Application record from database
     * @param string $pdfPath Relative path to PDF file
     * @return bool Success status
     */
    public function sendApplicationEmail($application, $pdfPath) {
        try {
            // Parse application data
            $data = json_decode($application['application_data'], true);
            if (!$data) {
                throw new \Exception("Invalid application data");
            }
            
            $applicantEmail = $data['email'] ?? '';
            $applicantName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
            
            if (empty($applicantEmail)) {
                throw new \Exception("No email address in application");
            }
            
            // Get association info
            $assocName = $this->config['association']['name'] ?? 'Associazione';
            $assocAddress = $this->config['association']['address'] ?? '';
            $assocCity = $this->config['association']['city'] ?? '';
            $assocPhone = $this->config['association']['phone'] ?? '';
            $assocEmail = $this->config['association']['email'] ?? '';
            
            // Build email subject
            $subject = "Domanda di Iscrizione Ricevuta - Codice " . $application['application_code'];
            
            // Build HTML email body
            $body = $this->buildApplicationEmailBody(
                $applicantName,
                $application,
                $assocName,
                $assocAddress,
                $assocCity,
                $assocPhone,
                $assocEmail
            );
            
            // Prepare PDF attachment
            $attachments = [];
            if (!empty($pdfPath)) {
                // Construct and validate file path to prevent directory traversal
                $basePath = realpath(__DIR__ . '/../../');
                $fullPath = realpath(__DIR__ . '/../../' . ltrim($pdfPath, '/'));
                
                // Verify path is within expected directory and file exists
                if ($fullPath !== false && 
                    $basePath !== false && 
                    strpos($fullPath, $basePath) === 0 && 
                    file_exists($fullPath)) {
                    
                    // Sanitize filename components to prevent injection
                    $lastName = strtoupper($data['last_name'] ?? 'SOCIO');
                    $firstName = strtoupper($data['first_name'] ?? '');
                    
                    // Remove any non-alphanumeric characters except underscore and hyphen
                    $lastName = preg_replace('/[^A-Z0-9_-]/i', '', $lastName);
                    $firstName = preg_replace('/[^A-Z0-9_-]/i', '', $firstName);
                    
                    // Fallback to generic names if sanitization leaves empty strings
                    $lastName = !empty($lastName) ? $lastName : 'SOCIO';
                    $firstName = !empty($firstName) ? $firstName : 'NUOVO';
                    
                    $attachments[] = [
                        'path' => $fullPath,
                        'name' => "domanda_iscrizione_{$lastName}_{$firstName}.pdf"
                    ];
                }
            }
            
            // Send email (will CC to association email if configured)
            $result = $this->send($applicantEmail, $subject, $body, $attachments);
            
            if ($result) {
                error_log("Application email sent to: $applicantEmail");
            }
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Failed to send application email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Build HTML body for application email
     * 
     * @param string $name Applicant name
     * @param array $application Application data
     * @param string $assocName Association name
     * @param string $assocAddress Association address
     * @param string $assocCity Association city
     * @param string $assocPhone Association phone
     * @param string $assocEmail Association email
     * @return string HTML email body
     */
    private function buildApplicationEmailBody($name, $application, $assocName, $assocAddress, $assocCity, $assocPhone, $assocEmail) {
        $dateStr = $application['submitted_at'] ?? $application['created_at'] ?? '';
        $timestamp = strtotime($dateStr);
        $applicationDate = ($timestamp !== false) ? date('d/m/Y', $timestamp) : $dateStr;
        $applicationCode = $application['application_code'];
        $pdfToken = $application['pdf_download_token'] ?? '';
        
        // Build PDF download URL using configured base URL
        $baseUrl = $this->resolveEmailBaseUrl();
        $pdfDownloadUrl = $baseUrl . '/public/application_pdf.php?token=' . htmlspecialchars($pdfToken) . '&download=1';
        
        // Check if junior application
        $isJunior = ($application['application_type'] ?? '') === 'junior';
        
        return "
    <!DOCTYPE html>
    <html lang='it'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; }
            .header { background: #007bff; color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { padding: 30px 20px; background: #f9f9f9; }
            .info-box { background: #e3f2fd; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; }
            .info-box p { margin: 5px 0; }
            .download-box { background: #d4edda; border: 2px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 8px; text-align: center; }
            .download-btn { display: inline-block; background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; }
            .download-btn:hover { background: #c82333; }
            .steps { background: white; padding: 20px; margin: 20px 0; border: 1px solid #ddd; border-radius: 5px; }
            .steps h3 { margin-top: 0; color: #007bff; }
            .steps ol { padding-left: 20px; }
            .steps li { margin: 10px 0; }
            .contact-box { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; background: #f0f0f0; }
            strong { color: #007bff; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Domanda di Iscrizione Ricevuta</h1>
            </div>
            
            <div class='content'>
                <p>Gentile <strong>$name</strong>,</p>
                
                <p>La tua domanda di iscrizione è stata ricevuta con successo!</p>
                
                <div class='info-box'>
                    <p><strong>CODICE DOMANDA:</strong> $applicationCode</p>
                    <p><strong>DATA RICEZIONE:</strong> $applicationDate</p>
                </div>
                
                " . (!empty($pdfToken) ? "
                <div class='download-box'>
                    <h3 style='margin-top: 0; color: #155724;'>📄 SCARICA IL MODULO PDF</h3>
                    <p>Clicca il pulsante per scaricare il modulo PDF da stampare e firmare:</p>
                    <a href='$pdfDownloadUrl' class='download-btn'>⬇️ SCARICA PDF DOMANDA</a>
                    <p style='font-size: 12px; color: #666; margin-top: 15px;'>
                        Se il pulsante non funziona, copia e incolla questo link nel browser:<br>
                        <code style='word-break: break-all;'>$pdfDownloadUrl</code>
                    </p>
                </div>
                " : "") . "
                
                <p>Il modulo PDF contiene:</p>
                <ul>
                    <li>Tutti i dati inseriti nel modulo</li>
                    <li>Tutte le dichiarazioni obbligatorie accettate</li>
                    <li>Gli spazi per le firme</li>
                </ul>
                
                <div class='steps'>
                    <h3>📋 PROSSIMI PASSI:</h3>
                    <ol>
                        <li><strong>Scarica e stampa</strong> il modulo PDF usando il link qui sopra</li>
                        <li><strong>Firma</strong> negli spazi indicati" . ($isJunior ? " (minore e genitori/tutori)" : "") . "</li>
                        " . (!$isJunior ? "
                        <li><strong>Prepara copie di:</strong>
                            <ul>
                                <li>Patenti di guida (se in possesso)</li>
                                <li>Attestati e qualifiche di Protezione Civile (se presenti)</li>
                                <li>Brevetti o patentini speciali (se presenti)</li>
                            </ul>
                        </li>
                        " : "") . "
                        <li><strong>Consegna</strong> il tutto presso la nostra sede:<br>
                            <em>$assocAddress" . (!empty($assocCity) ? " - $assocCity" : "") . "</em>
                        </li>
                    </ol>
                </div>
                
                <p>Ti contatteremo a breve per confermare la ricezione della documentazione e procedere con l'approvazione della domanda.</p>
                
                <div class='contact-box'>
                    <p><strong>📞 Per informazioni:</strong></p>
                    <p>Tel: $assocPhone<br>
                    Email: $assocEmail</p>
                </div>
                
                <p>Cordiali saluti,<br>
                <strong>$assocName</strong></p>
            </div>
            
            <div class='footer'>
                <p>Questa è un'email automatica. Per rispondere, scrivi a $assocEmail</p>
                <p>&copy; " . date('Y') . " $assocName</p>
            </div>
        </div>
    </body>
    </html>
    ";
    }
    
    /**
     * Send email when fee request is submitted
     * 
     * @param array $member Member data
     * @param array $feeRequest Fee request data
     * @return bool Success status
     */
    public function sendFeeRequestReceivedEmail($member, $feeRequest) {
        $memberEmail = $member['email'] ?? '';
        if (empty($memberEmail)) return false;
        
        $name = htmlspecialchars(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')), ENT_QUOTES, 'UTF-8');
        $year = htmlspecialchars($feeRequest['payment_year'] ?? '', ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars(date('d/m/Y', strtotime($feeRequest['payment_date'] ?? 'now')), ENT_QUOTES, 'UTF-8');
        $amount = !empty($feeRequest['amount']) ? '€' . htmlspecialchars(number_format($feeRequest['amount'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') : 'N/A';
        $assocName = htmlspecialchars($this->config['association']['name'] ?? 'Associazione', ENT_QUOTES, 'UTF-8');
        
        $subject = "Richiesta Pagamento Quota Ricevuta - Anno $year";
        $body = "<h2>Richiesta Ricevuta</h2><p>Gentile $name,</p><p>Abbiamo ricevuto la tua richiesta di pagamento quota.</p><p><strong>Anno:</strong> $year<br><strong>Data:</strong> $date<br><strong>Importo:</strong> $amount</p><p>La richiesta è in attesa di verifica.</p><p>$assocName</p>";
        
        return $this->send($memberEmail, $subject, $body);
    }
    
    /**
     * Send email when payment is approved
     * 
     * @param array $member Member data
     * @param array $feeRequest Fee request data
     * @return bool Success status
     */
    public function sendFeePaymentApprovedEmail($member, $feeRequest) {
        $memberEmail = $member['email'] ?? '';
        if (empty($memberEmail)) return false;
        
        $name = htmlspecialchars(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')), ENT_QUOTES, 'UTF-8');
        $year = htmlspecialchars($feeRequest['payment_year'] ?? '', ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars(date('d/m/Y', strtotime($feeRequest['payment_date'] ?? 'now')), ENT_QUOTES, 'UTF-8');
        $amount = !empty($feeRequest['amount']) ? '€' . htmlspecialchars(number_format($feeRequest['amount'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') : 'N/A';
        $assocName = htmlspecialchars($this->config['association']['name'] ?? 'Associazione', ENT_QUOTES, 'UTF-8');
        
        $subject = "✅ Pagamento Quota Confermato - Anno $year";
        $body = "<h2>✅ Pagamento Confermato</h2><p>Gentile $name,</p><p>Il pagamento della quota è stato approvato!</p><p><strong>Anno:</strong> $year<br><strong>Data:</strong> $date<br><strong>Importo:</strong> $amount</p><p><strong>Grazie per il tuo contributo!</strong></p><p>$assocName</p>";
        
        return $this->send($memberEmail, $subject, $body);
    }

    /**
     * Send welcome email to new user with credentials
     * 
     * @param array $user User data (must include 'email', 'username')
     * @param string $plainPassword Plain text temporary password
     * @return bool Success status
     */
    public function sendNewUserEmail($user, $plainPassword) {
        try {
            $userEmail = $user['email'] ?? '';
            $username = $user['username'] ?? '';
            
            if (empty($userEmail) || empty($username)) {
                error_log("Missing email or username for new user email");
                return false;
            }
            
            $assocName = $this->config['association']['name'] ?? 'Associazione';
            $baseUrl = $this->resolveEmailBaseUrl();
            $loginUrl = $baseUrl . '/public/login.php';
            
            $subject = "🎉 Benvenuto in EasyVol - Credenziali di Accesso";
            
            $body = "
        <!DOCTYPE html>
        <html lang='it'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .credentials { background: #e7f3ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; font-family: monospace; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                .button { display: inline-block; background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 15px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🎉 Benvenuto in EasyVol!</h2>
                </div>
                
                <div class='content'>
                    <p>Benvenuto!</p>
                    
                    <p>È stato creato un account per te nel sistema di gestione <strong>EasyVol</strong>.</p>
                    
                    <div class='credentials'>
                        <p style='margin: 0;'><strong>CREDENZIALI DI ACCESSO:</strong></p>
                        <p style='margin: 10px 0 5px 0;'>👤 <strong>Username:</strong> $username</p>
                        <p style='margin: 5px 0;'>🔑 <strong>Password:</strong> $plainPassword</p>
                    </div>
                    
                    <div class='warning'>
                        <p style='margin: 0;'><strong>⚠️ IMPORTANTE:</strong></p>
                        <p style='margin: 10px 0 0 0;'>Questa è una <strong>password TEMPORANEA</strong>. Al primo accesso ti verrà richiesto di cambiarla con una nuova password personale.</p>
                    </div>
                    
                    <p style='text-align: center;'>
                        <a href='$loginUrl' class='button'>Accedi Ora</a>
                    </p>
                    
                    <p><strong>PRIMO ACCESSO:</strong></p>
                    <ol>
                        <li>Clicca sul pulsante \"Accedi Ora\" qui sopra</li>
                        <li>Inserisci username e password indicati</li>
                        <li>Cambia la password quando richiesto</li>
                        <li>Accedi al sistema</li>
                    </ol>
                    
                    <p>Per qualsiasi problema, contatta l'amministratore del sistema.</p>
                    
                    <p>Cordiali saluti,<br><strong>$assocName</strong></p>
                </div>
                
                <div class='footer'>
                    <p>&copy; " . date('Y') . " $assocName</p>
                    <p>Questa è un'email automatica, non rispondere a questo messaggio.</p>
                </div>
            </div>
        </body>
        </html>
        ";
            
            $result = $this->send($userEmail, $subject, $body);
            
            if ($result) {
                error_log("New user email sent to: $userEmail (username: $username)");
            }
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Failed to send new user email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send password reset email with new credentials
     * 
     * @param array $user User data (must include 'email', 'username')
     * @param string $newPassword Plain text new temporary password
     * @return bool Success status
     */
    public function sendPasswordResetEmail($user, $newPassword) {
        try {
            $userEmail = $user['email'] ?? '';
            $username = $user['username'] ?? '';
            
            if (empty($userEmail) || empty($username)) {
                error_log("Missing email or username for password reset email");
                return false;
            }
            
            $assocName = $this->config['association']['name'] ?? 'Associazione';
            $baseUrl = $this->resolveEmailBaseUrl();
            $loginUrl = $baseUrl . '/public/login.php';
            
            $subject = "🔑 Reset Password - EasyVol";
            
            $body = "
        <!DOCTYPE html>
        <html lang='it'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .credentials { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; font-family: monospace; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                .security-warning { background: #ffe5e5; border: 2px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .button { display: inline-block; background: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 15px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🔑 Reset Password</h2>
                </div>
                
                <div class='content'>
                    <p>Hai richiesto il reset della password per il tuo account <strong>EasyVol</strong>.</p>
                    
                    <div class='credentials'>
                        <p style='margin: 0;'><strong>NUOVE CREDENZIALI:</strong></p>
                        <p style='margin: 10px 0 5px 0;'>👤 <strong>Username:</strong> $username</p>
                        <p style='margin: 5px 0;'>🔑 <strong>Nuova Password:</strong> $newPassword</p>
                    </div>
                    
                    <div class='warning'>
                        <p style='margin: 0;'><strong>⚠️ IMPORTANTE:</strong></p>
                        <p style='margin: 10px 0 0 0;'>Questa è una <strong>password TEMPORANEA</strong>. Al prossimo accesso ti verrà richiesto di cambiarla.</p>
                    </div>
                    
                    <p style='text-align: center;'>
                        <a href='$loginUrl' class='button'>Accedi Ora</a>
                    </p>
                    
                    <div class='security-warning'>
                        <p style='margin: 0;'><strong>🚨 ATTENZIONE SICUREZZA</strong></p>
                        <p style='margin: 10px 0 0 0;'>Se <strong>NON hai richiesto</strong> questo reset, contatta <strong>immediatamente</strong> l'amministratore del sistema. Il tuo account potrebbe essere a rischio.</p>
                    </div>
                    
                    <p>Cordiali saluti,<br><strong>$assocName</strong></p>
                </div>
                
                <div class='footer'>
                    <p>&copy; " . date('Y') . " $assocName</p>
                    <p>Questa è un'email automatica, non rispondere a questo messaggio.</p>
                </div>
            </div>
        </body>
        </html>
        ";
            
            $result = $this->send($userEmail, $subject, $body);
            
            if ($result) {
                error_log("Password reset email sent to: $userEmail (username: $username)");
            }
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Failed to send password reset email: " . $e->getMessage());
            return false;
        }
    }

}
