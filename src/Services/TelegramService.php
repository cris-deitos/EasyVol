<?php
namespace EasyVol\Services;

use EasyVol\Database;

/**
 * Telegram Service
 * 
 * Service layer for sending Telegram notifications using the Telegram Bot API
 */
class TelegramService {
    private $db;
    private $config;
    private $botToken;
    private $isEnabled;
    
    /**
     * Constructor
     * 
     * @param Database $db Database instance
     * @param array $config Application configuration
     */
    public function __construct(Database $db, array $config) {
        $this->db = $db;
        $this->config = $config;
        
        // Load Telegram configuration from database
        $this->loadConfiguration();
    }
    
    /**
     * Load Telegram configuration from database
     */
    private function loadConfiguration() {
        try {
            $sql = "SELECT config_key, config_value FROM config 
                    WHERE config_key IN ('telegram_bot_token', 'telegram_bot_enabled')";
            $configs = $this->db->fetchAll($sql);
            
            foreach ($configs as $config) {
                if ($config['config_key'] === 'telegram_bot_token') {
                    $this->botToken = $config['config_value'];
                } elseif ($config['config_key'] === 'telegram_bot_enabled') {
                    $this->isEnabled = (bool)$config['config_value'];
                }
            }
        } catch (\Exception $e) {
            error_log("TelegramService: Failed to load configuration: " . $e->getMessage());
            $this->isEnabled = false;
        }
    }
    
    /**
     * Check if Telegram notifications are enabled
     * 
     * @return bool
     */
    public function isEnabled() {
        return $this->isEnabled && !empty($this->botToken);
    }
    
    /**
     * Send a message via Telegram
     * 
     * @param string $chatId Chat ID or username (can be a user ID or group ID)
     * @param string $message Message text
     * @param array $options Optional parameters (parse_mode, reply_markup, etc.)
     * @return bool True if message was sent successfully
     */
    public function sendMessage($chatId, $message, $options = []) {
        if (!$this->isEnabled()) {
            error_log("TelegramService: Service is not enabled or bot token is missing");
            return false;
        }
        
        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
            
            $data = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $options['parse_mode'] ?? 'HTML',
            ];
            
            // Add optional parameters
            if (isset($options['reply_markup'])) {
                $data['reply_markup'] = $options['reply_markup'];
            }
            if (isset($options['disable_web_page_preview'])) {
                $data['disable_web_page_preview'] = $options['disable_web_page_preview'];
            }
            if (isset($options['disable_notification'])) {
                $data['disable_notification'] = $options['disable_notification'];
            }
            
            $response = $this->makeApiRequest($url, $data);
            
            if ($response && isset($response['ok']) && $response['ok'] === true) {
                return true;
            } else {
                $errorMsg = isset($response['description']) ? $response['description'] : 'Unknown error';
                error_log("TelegramService: Failed to send message to {$chatId}: {$errorMsg}");
                return false;
            }
            
        } catch (\Exception $e) {
            error_log("TelegramService: Exception while sending message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Make an API request to Telegram Bot API
     * 
     * @param string $url API endpoint URL
     * @param array $data POST data
     * @return array|null Response data or null on failure
     */
    private function makeApiRequest($url, $data) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            error_log("TelegramService: cURL error: {$error}");
            return null;
        }
        
        if ($httpCode !== 200) {
            error_log("TelegramService: HTTP error {$httpCode}: {$response}");
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Get recipients for a specific action type
     * 
     * @param string $actionType Action type (e.g., 'member_application', 'vehicle_departure')
     * @return array Array of recipients with chat IDs
     */
    public function getRecipients($actionType) {
        try {
            $sql = "SELECT tnr.*, 
                           m.id as member_id,
                           m.first_name,
                           m.last_name,
                           mc.value as telegram_id
                    FROM telegram_notification_recipients tnr
                    INNER JOIN telegram_notification_config tnc ON tnr.config_id = tnc.id
                    LEFT JOIN members m ON tnr.member_id = m.id
                    LEFT JOIN member_contacts mc ON m.id = mc.member_id AND mc.contact_type = 'telegram_id'
                    WHERE tnc.action_type = ? AND tnc.is_enabled = 1";
            
            $recipients = $this->db->fetchAll($sql, [$actionType]);
            
            $result = [];
            foreach ($recipients as $recipient) {
                if ($recipient['recipient_type'] === 'member' && !empty($recipient['telegram_id'])) {
                    $result[] = [
                        'chat_id' => $recipient['telegram_id'],
                        'name' => $recipient['first_name'] . ' ' . $recipient['last_name'],
                        'type' => 'member'
                    ];
                } elseif ($recipient['recipient_type'] === 'group' && !empty($recipient['telegram_group_id'])) {
                    $result[] = [
                        'chat_id' => $recipient['telegram_group_id'],
                        'name' => $recipient['telegram_group_name'] ?: $recipient['telegram_group_id'],
                        'type' => 'group'
                    ];
                }
            }
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("TelegramService: Failed to get recipients: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Send notification to all configured recipients for an action
     * 
     * @param string $actionType Action type
     * @param string $message Message to send
     * @param array $options Optional parameters for message
     * @return array Results for each recipient
     */
    public function sendNotification($actionType, $message, $options = []) {
        if (!$this->isEnabled()) {
            return [];
        }
        
        $recipients = $this->getRecipients($actionType);
        $results = [];
        
        foreach ($recipients as $recipient) {
            $success = $this->sendMessage($recipient['chat_id'], $message, $options);
            $results[] = [
                'recipient' => $recipient['name'],
                'chat_id' => $recipient['chat_id'],
                'type' => $recipient['type'],
                'success' => $success
            ];
        }
        
        return $results;
    }
    
    /**
     * Test bot connection
     * 
     * @return array Result with 'success' boolean and 'message' or 'bot_info'
     */
    public function testConnection() {
        if (empty($this->botToken)) {
            return [
                'success' => false,
                'message' => 'Token del bot non configurato'
            ];
        }
        
        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/getMe";
            $response = $this->makeApiRequest($url, []);
            
            if ($response && isset($response['ok']) && $response['ok'] === true) {
                return [
                    'success' => true,
                    'bot_info' => $response['result']
                ];
            } else {
                $errorMsg = isset($response['description']) ? $response['description'] : 'Errore sconosciuto';
                return [
                    'success' => false,
                    'message' => $errorMsg
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
