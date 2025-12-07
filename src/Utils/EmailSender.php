<?php
namespace EasyVol\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Email Sender Utility
 * 
 * Gestisce l'invio di email per EasyVol
 * Supporta SMTP, sendmail e funzione mail() di PHP
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
     * Inizializza PHPMailer
     * 
     * @return PHPMailer
     * @throws Exception
     */
    private function initMailer() {
        $mailer = new PHPMailer(true);
        
        // Set charset
        $mailer->CharSet = 'UTF-8';
        
        // Check if email is enabled
        if (!($this->config['email']['enabled'] ?? false)) {
            throw new Exception('Email sending is disabled in configuration');
        }
        
        $method = $this->config['email']['method'] ?? 'mail';
        
        if ($method === 'smtp') {
            $mailer->isSMTP();
            $mailer->Host = $this->config['email']['smtp_host'] ?? '';
            $mailer->Port = $this->config['email']['smtp_port'] ?? 587;
            $mailer->SMTPAuth = true;
            $mailer->Username = $this->config['email']['smtp_username'] ?? '';
            $mailer->Password = $this->config['email']['smtp_password'] ?? '';
            $mailer->SMTPSecure = $this->config['email']['smtp_encryption'] ?? 'tls';
            
            // Optional SMTP debug
            // $mailer->SMTPDebug = 2;
        } elseif ($method === 'sendmail') {
            $mailer->isSendmail();
        } else {
            $mailer->isMail();
        }
        
        // Set from
        $mailer->setFrom(
            $this->config['email']['from_email'] ?? 'noreply@example.com',
            $this->config['email']['from_name'] ?? 'EasyVol'
        );
        
        return $mailer;
    }
    
    /**
     * Invia email
     * 
     * @param string|array $to Destinatario/i
     * @param string $subject Oggetto
     * @param string $body Corpo HTML
     * @param array $attachments Allegati (array di path)
     * @param string|array $cc CC
     * @param string|array $bcc BCC
     * @param string $replyTo Reply-To email
     * @return bool
     */
    public function send($to, $subject, $body, $attachments = [], $cc = [], $bcc = [], $replyTo = null) {
        try {
            $mailer = $this->initMailer();
            
            // Add recipients
            if (is_array($to)) {
                foreach ($to as $email => $name) {
                    if (is_numeric($email)) {
                        $mailer->addAddress($name);
                    } else {
                        $mailer->addAddress($email, $name);
                    }
                }
            } else {
                $mailer->addAddress($to);
            }
            
            // Add CC
            if (!empty($cc)) {
                if (is_array($cc)) {
                    foreach ($cc as $email) {
                        $mailer->addCC($email);
                    }
                } else {
                    $mailer->addCC($cc);
                }
            }
            
            // Add BCC
            if (!empty($bcc)) {
                if (is_array($bcc)) {
                    foreach ($bcc as $email) {
                        $mailer->addBCC($email);
                    }
                } else {
                    $mailer->addBCC($bcc);
                }
            }
            
            // Reply-To
            if ($replyTo) {
                $mailer->addReplyTo($replyTo);
            }
            
            // Subject and body
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body = $body;
            $mailer->AltBody = strip_tags($body);
            
            // Add attachments
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mailer->addAttachment($attachment);
                }
            }
            
            // Send
            $result = $mailer->send();
            
            // Log success
            if ($this->db) {
                $this->logEmail($to, $subject, $body, 'sent', '');
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("PHPMailer send failed: " . $e->getMessage());
            
            // Try fallback to PHP's native mail() function
            try {
                $fallbackResult = $this->sendWithNativeMail($to, $subject, $body);
                
                if ($fallbackResult) {
                    error_log("Email sent successfully using fallback mail() function");
                    
                    // Log success
                    if ($this->db) {
                        $this->logEmail($to, $subject, $body, 'sent_fallback', 'Sent using fallback mail() after PHPMailer failed');
                    }
                    
                    return true;
                }
            } catch (\Exception $fallbackException) {
                error_log("Fallback mail() also failed: " . $fallbackException->getMessage());
            }
            
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
     * Fallback: Invia email usando la funzione mail() nativa di PHP
     * 
     * @param string|array $to Destinatario/i
     * @param string $subject Oggetto
     * @param string $body Corpo HTML
     * @return bool
     * @throws \Exception Se l'indirizzo email non è valido o l'invio fallisce
     */
    private function sendWithNativeMail($to, $subject, $body) {
        // Get the primary recipient email
        $toEmail = $this->extractPrimaryEmailAddress($to);
        
        // Validate email
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Invalid email address: $toEmail");
        }
        
        // Prepare headers - use configured values or reasonable defaults
        $fromEmail = $this->config['email']['from_email'] ?? 'noreply@localhost';
        $fromName = $this->config['email']['from_name'] ?? 'EasyVol';
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            "From: $fromName <$fromEmail>",
            "Reply-To: $fromEmail",
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // Send email
        $result = mail($toEmail, $subject, $body, implode("\r\n", $headers));
        
        if (!$result) {
            throw new \Exception("mail() function returned false");
        }
        
        return true;
    }
}
