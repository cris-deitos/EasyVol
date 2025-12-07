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
                $headers = array_merge($headers, $this->config['email']['additional_headers']);
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
            
            // Additional sendmail parameters if configured
            $additionalParams = $this->config['email']['sendmail_params'] ?? null;
            
            // Send email
            $result = mail($toEmail, $subject, $body, implode("\r\n", $headers), $additionalParams);
            
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
    

}
