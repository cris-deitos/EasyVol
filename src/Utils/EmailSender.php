<?php
namespace EasyVol\Utils;

/**
 * Email Sender Utility
 * 
 * Gestisce l'invio di email per EasyVol usando la funzione mail() nativa di PHP
 */
class EmailSender {
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
     * Build email headers from configuration
     * 
     * @param string|null $replyTo Optional Reply-To address
     * @return array Array of email headers
     */
    private function buildHeaders($replyTo = null) {
        $charset = $this->config['email']['charset'] ?? 'UTF-8';
        $encoding = $this->config['email']['encoding'] ?? '8bit';
        $fromEmail = $this->config['email']['from_address'] ?? $this->config['email']['from_email'] ?? 'noreply@localhost';
        $fromName = $this->config['email']['from_name'] ?? 'EasyVol';
        $replyToEmail = $replyTo ?? $this->config['email']['reply_to'] ?? $fromEmail;
        $returnPath = $this->config['email']['return_path'] ?? $fromEmail;
        
        $headers = [
            'MIME-Version: 1.0',
            "Content-type: text/html; charset=$charset",
            "Content-Transfer-Encoding: $encoding",
            "From: $fromName <$fromEmail>",
            "Reply-To: $replyToEmail",
            "Return-Path: $returnPath",
            'X-Mailer: EasyVol/' . ($this->config['app']['version'] ?? '1.0')
        ];
        
        // Add custom headers if configured
        if (!empty($this->config['email']['additional_headers'])) {
            if (is_array($this->config['email']['additional_headers'])) {
                // Validate and filter dangerous headers
                $dangerousHeaders = ['bcc:', 'cc:', 'to:', 'from:', 'content-type:', 'mime-version:'];
                foreach ($this->config['email']['additional_headers'] as $header) {
                    $headerLower = strtolower(trim($header));
                    $isDangerous = false;
                    foreach ($dangerousHeaders as $dangerous) {
                        if (strpos($headerLower, $dangerous) === 0) {
                            $isDangerous = true;
                            error_log("Blocked dangerous header: $header");
                            break;
                        }
                    }
                    if (!$isDangerous && preg_match('/^[a-zA-Z0-9\-]+:/', $header)) {
                        $headers[] = $header;
                    }
                }
            }
        }
        
        return $headers;
    }
    
    /**
     * Invia email
     * 
     * @param string|array $to Destinatario/i
     * @param string $subject Oggetto
     * @param string $body Corpo HTML
     * @param array $attachments Allegati (array di path) - Not supported with native mail()
     * @param string|array $cc CC - Not supported with native mail()
     * @param string|array $bcc BCC - Not supported with native mail()
     * @param string $replyTo Reply-To email
     * @return bool
     */
    public function send($to, $subject, $body, $attachments = [], $cc = [], $bcc = [], $replyTo = null) {
        try {
            // Check if email is enabled
            if (!($this->config['email']['enabled'] ?? false)) {
                throw new \Exception('Email sending is disabled in configuration');
            }
            
            // Get primary recipient email
            $toEmail = $this->extractPrimaryEmailAddress($to);
            
            // Validate email
            if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception("Invalid email address: $toEmail");
            }
            
            // Build headers
            $headers = $this->buildHeaders($replyTo);
            
            // Add CC to association if not already in headers and configured
            $assocEmail = $this->config['association']['email'] ?? '';
            $ccEmail = $this->config['email']['cc'] ?? $assocEmail;
            
            if (!empty($ccEmail) && $ccEmail !== $toEmail) {
                $headers[] = "Cc: $ccEmail";
            }
            
            // Additional sendmail parameters if configured
            $additionalParams = $this->config['email']['sendmail_params'] ?? '';
            
            // Handle attachments using MIME multipart
            if (!empty($attachments) && is_array($attachments)) {
                // Create boundary
                $boundary = md5(time());
                
                // Override Content-Type for multipart (MIME-Version already set in buildHeaders)
                $headers[] = "Content-Type: multipart/mixed; boundary=\"$boundary\"";
                
                // Build multipart message
                $message = "--$boundary\r\n";
                $message .= "Content-Type: text/html; charset=UTF-8\r\n";
                $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
                $message .= $body . "\r\n\r\n";
                
                // Add each attachment
                foreach ($attachments as $attachment) {
                    if (isset($attachment['path']) && file_exists($attachment['path'])) {
                        // Validate file size (max 10MB)
                        $fileSize = filesize($attachment['path']);
                        $maxSize = 10 * 1024 * 1024; // 10MB
                        if ($fileSize > $maxSize) {
                            error_log("Attachment too large: {$attachment['path']} ($fileSize bytes)");
                            continue;
                        }
                        
                        // Detect MIME type
                        $mimeType = 'application/octet-stream';
                        if (function_exists('mime_content_type')) {
                            $detectedType = mime_content_type($attachment['path']);
                            if ($detectedType !== false) {
                                $mimeType = $detectedType;
                            }
                        }
                        
                        $fileName = $attachment['name'] ?? basename($attachment['path']);
                        $fileContent = file_get_contents($attachment['path']);
                        $encodedContent = chunk_split(base64_encode($fileContent));
                        
                        $message .= "--$boundary\r\n";
                        $message .= "Content-Type: $mimeType; name=\"$fileName\"\r\n";
                        $message .= "Content-Transfer-Encoding: base64\r\n";
                        $message .= "Content-Disposition: attachment; filename=\"$fileName\"\r\n\r\n";
                        $message .= $encodedContent . "\r\n";
                    }
                }
                
                $message .= "--$boundary--";
                $body = $message;
            }
            
            // Send email (PHP 8 compatible)
            if (!empty($additionalParams)) {
                $result = mail($toEmail, $subject, $body, implode("\r\n", $headers), $additionalParams);
            } else {
                $result = mail($toEmail, $subject, $body, implode("\r\n", $headers));
            }
            
            if (!$result) {
                throw new \Exception("mail() function returned false");
            }
            
            // Log success
            if ($this->db) {
                $this->logEmail($to, $subject, $body, 'sent', '');
            }
            
            return true;
            
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
        $applicationDate = date('d/m/Y', strtotime($application['created_at']));
        $applicationCode = $application['application_code'];
        
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
                
                <p><strong>In allegato</strong> trovi il modulo PDF completo precompilato con:</p>
                <ul>
                    <li>Tutti i dati inseriti</li>
                    <li>Tutte le dichiarazioni obbligatorie accettate</li>
                    <li>Gli spazi per le firme</li>
                </ul>
                
                <div class='steps'>
                    <h3>📋 PROSSIMI PASSI:</h3>
                    <ol>
                        <li><strong>Stampa</strong> il modulo PDF allegato a questa email</li>
                        <li><strong>Firma</strong> negli spazi indicati</li>
                        <li><strong>Prepara copie di:</strong>
                            <ul>
                                <li>Patenti di guida (se in possesso)</li>
                                <li>Attestati e qualifiche di Protezione Civile (se presenti)</li>
                                <li>Brevetti o patentini speciali (se presenti)</li>
                            </ul>
                        </li>
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

}
