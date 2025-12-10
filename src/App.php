<?php
namespace EasyVol;

/**
 * Main Application Class
 */
class App {
    /**
     * Default password for new users and password resets
     */
    const DEFAULT_PASSWORD = 'Pw@12345678';
    
    /**
     * Optional email configuration fields that can have empty values
     */
    const OPTIONAL_EMAIL_FIELDS = ['reply_to', 'return_path', 'sendmail_params', 'additional_headers'];
    
    private static $instance = null;
    private $config;
    private $db;
    private $session;
    private $permissionsRefreshed = false;
    
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
            $this->loadAssociationData();
            $this->loadEmailConfigFromDatabase();
        }
    }
    
    private function loadAssociationData() {
        try {
            $association = $this->db->fetchOne("SELECT * FROM association ORDER BY id ASC LIMIT 1");
            if ($association) {
                // Build address string
                $addressParts = array_filter([
                    $association['address_street'] ?? '',
                    $association['address_number'] ?? ''
                ]);
                $address = !empty($addressParts) ? implode(' ', $addressParts) : '';
                
                // Build city string
                $cityParts = [];
                if (!empty($association['address_city'])) {
                    $cityParts[] = $association['address_city'];
                }
                if (!empty($association['address_province']) || !empty($association['address_cap'])) {
                    $provinceCap = array_filter([
                        $association['address_province'] ?? '',
                        $association['address_cap'] ?? ''
                    ]);
                    if (!empty($provinceCap)) {
                        $cityParts[] = '(' . implode(') ', $provinceCap) . ')';
                    }
                }
                $city = !empty($cityParts) ? implode(' ', $cityParts) : '';
                
                $this->config['association'] = [
                    'name' => $association['name'] ?? '',
                    'address' => $address,
                    'city' => $city,
                    'email' => $association['email'] ?? '',
                    'pec' => $association['pec'] ?? '',
                    'tax_code' => $association['tax_code'] ?? '',
                ];
            } else {
                $this->config['association'] = [
                    'name' => 'N/D',
                    'address' => 'N/D',
                    'city' => 'N/D',
                    'email' => 'N/D',
                    'pec' => 'N/D',
                    'tax_code' => 'N/D',
                ];
            }
        } catch (\Exception $e) {
            error_log("Failed to load association data: " . $e->getMessage());
            $this->config['association'] = [
                'name' => 'N/D',
                'address' => 'N/D',
                'city' => 'N/D',
                'email' => 'N/D',
                'pec' => 'N/D',
                'tax_code' => 'N/D',
            ];
        }
    }
    
    private function loadEmailConfigFromDatabase() {
        try {
            // Load email configuration from database
            $emailConfigs = $this->db->fetchAll(
                "SELECT config_key, config_value FROM config WHERE config_key LIKE 'email_%'"
            );
            
            if (!empty($emailConfigs)) {
                // Map database config keys to config array keys
                $keyMapping = [
                    'email_from_address' => 'from_address',
                    'email_from_name' => 'from_name',
                    'email_reply_to' => 'reply_to',
                    'email_return_path' => 'return_path',
                    'email_charset' => 'charset',
                    'email_encoding' => 'encoding',
                    'email_sendmail_params' => 'sendmail_params',
                    'email_additional_headers' => 'additional_headers',
                ];
                
                foreach ($emailConfigs as $row) {
                    $dbKey = $row['config_key'];
                    $value = $row['config_value'];
                    
                    if (isset($keyMapping[$dbKey])) {
                        $configKey = $keyMapping[$dbKey];
                        
                        // Special handling for additional_headers - convert to array
                        if ($configKey === 'additional_headers' && !empty($value)) {
                            $value = array_filter(array_map('trim', explode("\n", $value)));
                        }
                        
                        // Override config if value is not empty, or if it's an optional field
                        if ($value !== '' || in_array($configKey, self::OPTIONAL_EMAIL_FIELDS)) {
                            $this->config['email'][$configKey] = $value;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Failed to load email config from database: " . $e->getMessage());
            // Continue with file-based config
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
    
    public function getUserId() {
        $user = $this->getCurrentUser();
        return $user['id'] ?? null;
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
        
        // Refresh permissions from database once per request
        // This ensures permission changes take effect immediately without logout/login
        if (!$this->permissionsRefreshed) {
            $this->refreshUserPermissions();
            $this->permissionsRefreshed = true;
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
    
    /**
     * Refresh user permissions from database
     * Call this method when permissions might have changed (e.g., after role/permission updates)
     * This reloads the user's role and permissions from the database into the session
     * 
     * @return bool True if permissions were refreshed successfully, false otherwise
     */
    public function refreshUserPermissions() {
        if (!$this->isLoggedIn() || !$this->isInstalled()) {
            return false;
        }
        
        try {
            $userId = $_SESSION['user']['id'];
            
            // Reload user data with role - only for active users
            $stmt = $this->db->query(
                "SELECT u.*, r.name as role_name 
                 FROM users u 
                 LEFT JOIN roles r ON u.role_id = r.id 
                 WHERE u.id = ? AND u.is_active = 1",
                [$userId]
            );
            
            $userData = $stmt->fetch();
            
            if (!$userData) {
                // User not found or inactive - clear session and force logout
                unset($_SESSION['user']);
                session_destroy();
                return false;
            }
            
            // Use the loadUserPermissions helper to avoid code duplication
            $permissions = $this->loadUserPermissions($userId, $userData['role_id']);
            
            // Update session with refreshed data
            $_SESSION['user']['role_id'] = $userData['role_id'];
            $_SESSION['user']['role_name'] = $userData['role_name'];
            $_SESSION['user']['permissions'] = $permissions;
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Failed to refresh user permissions: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Load permissions for a specific user (helper method)
     * This is a helper that can be used during login or permission refresh
     * 
     * @param int $userId The user ID
     * @param int|null $roleId The role ID (optional)
     * @return array Array of permission objects
     */
    public function loadUserPermissions($userId, $roleId = null) {
        if (!$this->isInstalled()) {
            return [];
        }
        
        try {
            $allPermissions = [];
            
            // Use a single query with UNION to reduce database round trips
            // This combines role-based permissions and user-specific permissions
            if ($roleId) {
                $stmt = $this->db->query(
                    "SELECT p.id, p.module, p.action, p.description, 'role' as source 
                     FROM permissions p
                     INNER JOIN role_permissions rp ON p.id = rp.permission_id
                     WHERE rp.role_id = ?
                     UNION
                     SELECT p.id, p.module, p.action, p.description, 'user' as source
                     FROM permissions p
                     INNER JOIN user_permissions up ON p.id = up.permission_id
                     WHERE up.user_id = ?",
                    [$roleId, $userId]
                );
                $allPermissions = $stmt->fetchAll();
            } else {
                // No role assigned - only get user-specific permissions
                $stmt = $this->db->query(
                    "SELECT p.id, p.module, p.action, p.description, 'user' as source
                     FROM permissions p
                     INNER JOIN user_permissions up ON p.id = up.permission_id
                     WHERE up.user_id = ?",
                    [$userId]
                );
                $allPermissions = $stmt->fetchAll();
            }
            
            // Deduplicate permissions (user permissions take precedence)
            $permissionsMap = [];
            foreach ($allPermissions as $perm) {
                $key = $perm['module'] . '::' . $perm['action'];
                // User permissions override role permissions if duplicated
                if (!isset($permissionsMap[$key]) || $perm['source'] === 'user') {
                    $permissionsMap[$key] = $perm;
                }
            }
            
            return array_values($permissionsMap);
            
        } catch (\Exception $e) {
            error_log("Failed to load user permissions: " . $e->getMessage());
            return [];
        }
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
