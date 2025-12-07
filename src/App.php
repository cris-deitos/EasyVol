<?php
namespace EasyVol;

/**
 * Main Application Class
 */
class App {
    private static $instance = null;
    private $config;
    private $db;
    private $session;
    
    private function __construct() {
        // Set error reporting
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        
        // Load configuration
        $configFile = __DIR__ . '/../config/config.php';
        if (!file_exists($configFile)) {
            throw new \Exception("Configuration file not found. Please copy config.sample.php to config.php");
        }
        
        $this->config = require $configFile;
        
        // Set timezone
        date_default_timezone_set($this->config['app']['timezone']);
        
        // Initialize session
        $this->initSession();
        
        // Initialize database if configured
        if ($this->isInstalled()) {
            $this->db = Database::getInstance($this->config['database']);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', 1);
            }
            
            session_start();
        }
    }
    
    public function isInstalled() {
        return isset($this->config['database']['name']) && 
               !empty($this->config['database']['name']) &&
               $this->config['database']['name'] !== 'easyvol';
    }
    
    public function getConfig($key = null) {
        if ($key === null) {
            return $this->config;
        }
        
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    public function getDb() {
        return $this->db;
    }
    
    public function redirect($url) {
        header("Location: $url");
        exit;
    }
    
    public function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    public function getCurrentUser() {
        return isset($_SESSION['user']) ? $_SESSION['user'] : null;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user']) && isset($_SESSION['user']['id']);
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            $this->redirect('login.php');
        }
    }
    
    public function checkPermission($module, $action) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $user = $this->getCurrentUser();
        
        // Admin has all permissions
        if (isset($user['role_name']) && $user['role_name'] === 'admin') {
            return true;
        }
        
        // Check specific permission
        if (!isset($user['permissions'])) {
            return false;
        }
        
        foreach ($user['permissions'] as $perm) {
            if ($perm['module'] === $module && $perm['action'] === $action) {
                return true;
            }
        }
        
        return false;
    }
    
    public function logActivity($action, $module = null, $recordId = null, $description = null) {
        if (!$this->isInstalled()) {
            return;
        }
        
        $userId = $this->isLoggedIn() ? $this->getCurrentUser()['id'] : null;
        
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];
        
        try {
            $this->db->insert('activity_logs', $data);
        } catch (\Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
