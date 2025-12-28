<?php
namespace EasyVol\Services;

use EasyVol\Database;
use EasyVol\Utils\EmailSender;

/**
 * Email Service
 * 
 * Service layer for sending emails using the EmailSender utility
 */
class EmailService {
    private $db;
    private $config;
    private $emailSender;
    
    /**
     * Constructor
     * 
     * @param Database $db Database instance
     * @param array $config Application configuration
     */
    public function __construct(Database $db, array $config) {
        $this->db = $db;
        $this->config = $config;
        $this->emailSender = new EmailSender($config, $db);
    }
    
    /**
     * Send an email
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param array $options Optional parameters (cc, bcc, attachments, etc.)
     * @return bool True if email was sent successfully
     */
    public function sendEmail($to, $subject, $body, $options = []) {
        try {
            // Get sender name and email from config
            $fromName = $this->config['email']['from_name'] ?? 'EasyVol';
            $fromEmail = $this->config['email']['from_address'] ?? 'noreply@example.com';
            
            // Prepare recipients
            $recipients = is_array($to) ? $to : [$to];
            
            // Use EmailSender to send the email
            return $this->emailSender->send(
                $recipients,
                $subject,
                $body,
                $fromEmail,
                $fromName,
                $options
            );
            
        } catch (\Exception $e) {
            error_log("EmailService error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email to multiple recipients
     * 
     * @param array $recipients Array of email addresses
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param array $options Optional parameters
     * @return array Array of results for each recipient
     */
    public function sendBulkEmail($recipients, $subject, $body, $options = []) {
        $results = [];
        
        foreach ($recipients as $recipient) {
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $results[$recipient] = $this->sendEmail($recipient, $subject, $body, $options);
            } else {
                $results[$recipient] = false;
                error_log("Invalid email address: " . $recipient);
            }
        }
        
        return $results;
    }
}
