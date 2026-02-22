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
    const OPTIONAL_EMAIL_FIELDS = ['reply_to', 'return_path', 'base_url', 'smtp_host', 'smtp_username', 'smtp_password', 'smtp_encryption'];
    
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
            // Build address string:  "Via Scavi Romani, 39"
            $addressParts = [];
            if (!empty($association['address_street'])) {
                $addressParts[] = $association['address_street'];
            }
            if (!empty($association['address_number'])) {
                $addressParts[] = $association['address_number'];
            }
            $address = !empty($addressParts) ? implode(', ', $addressParts) : '';
            
            // Build city string: "25015 Desenzano del Garda (BS)"
            $cityParts = [];
            if (!empty($association['address_cap'])) {
                $cityParts[] = $association['address_cap'];
            }
            if (! empty($association['address_city'])) {
                $cityParts[] = $association['address_city'];
            }
            if (!empty($association['address_province'])) {
                $cityParts[] = '(' . $association['address_province'] . ')';
            }
            $city = !empty($cityParts) ? implode(' ', $cityParts) : '';
            
            $this->config['association'] = [
                'name' => $association['name'] ?? '',
                'logo' => $association['logo'] ?? '',
                'address' => $address,
                'city' => $city,
                'phone' => $association['phone'] ?? '',
                'email' => $association['email'] ??  '',
                'pec' => $association['pec'] ??  '',
                'tax_code' => $association['tax_code'] ?? '',
            ];
        } else {
            $this->config['association'] = [
                'name' => 'N/D',
                'logo' => '',
                'address' => 'N/D',
                'city' => 'N/D',
                'phone' => 'N/D',
                'email' => 'N/D',
                'pec' => 'N/D',
                'tax_code' => 'N/D',
            ];
        }
    } catch (\Exception $e) {
        error_log("Failed to load association data: " .  $e->getMessage());
        $this->config['association'] = [
            'name' => 'N/D',
            'logo' => '',
            'address' => 'N/D',
            'city' => 'N/D',
            'phone' => 'N/D',
            'email' => 'N/D',
            'pec' => 'N/D',
            'tax_code' => 'N/D',
        ];
    }
}
    
private function loadEmailConfigFromDatabase() {
    try {
        // Inizializza SEMPRE i valori di default
        if (!isset($this->config['email'])) {
            $this->config['email'] = [];
        }
        
        // Valori di default se il database è vuoto
        $defaults = [
            'enabled' => false,
            'method' => 'smtp',
            'from_address' => '',
            'from_name' => 'EasyVol',
            'reply_to' => '',
            'return_path' => '',
            'charset' => 'UTF-8',
            'base_url' => '',
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'smtp_auth' => true,
            'smtp_debug' => false,
        ];
        
        // Applica i defaults
        foreach ($defaults as $key => $value) {
            if (! isset($this->config['email'][$key])) {
                $this->config['email'][$key] = $value;
            }
        }
        
        // Carica dal database e sovrascrivi i defaults se esistono
        $emailConfigs = $this->db->fetchAll(
            "SELECT config_key, config_value FROM config WHERE config_key LIKE 'email_%'"
        );
        
        if (!empty($emailConfigs)) {
            // Map database config keys to config array keys
            $keyMapping = [
                'email_enabled' => 'enabled',
                'email_method' => 'method',
                'email_from_address' => 'from_address',
                'email_from_name' => 'from_name',
                'email_reply_to' => 'reply_to',
                'email_return_path' => 'return_path',
                'email_charset' => 'charset',
                'email_base_url' => 'base_url',
                'email_smtp_host' => 'smtp_host',
                'email_smtp_port' => 'smtp_port',
                'email_smtp_username' => 'smtp_username',
                'email_smtp_password' => 'smtp_password',
                'email_smtp_encryption' => 'smtp_encryption',
                'email_smtp_auth' => 'smtp_auth',
                'email_smtp_debug' => 'smtp_debug',
            ];
            
            foreach ($emailConfigs as $config) {
                $dbKey = $config['config_key'];
                if (isset($keyMapping[$dbKey])) {
                    $configKey = $keyMapping[$dbKey];
                    $value = $config['config_value'];
                    
                    // Converti valori booleani
                    if (in_array($configKey, ['enabled', 'smtp_auth', 'smtp_debug'])) {
                        $value = ($value === '1' || $value === 'true' || $value === true);
                    }
                    
                    // Converti porta a intero
                    if ($configKey === 'smtp_port') {
                        $value = (int)$value;
                    }
                    
                    $this->config['email'][$configKey] = $value;
                }
            }
        }
    } catch (\Exception $e) {
        error_log("Failed to load email config from database: " . $e->getMessage());
        // In caso di errore, mantieni i defaults
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
            // Enhanced session security settings
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            // Set secure flag if using HTTPS
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', 1);
            }
            
            // Prevent session fixation
            ini_set('session.use_strict_mode', 1);
            
            // Session timeout (2 hours by default)
            $sessionLifetime = $this->config['security']['session_lifetime'] ?? 7200;
            ini_set('session.gc_maxlifetime', $sessionLifetime);
            
            // Set cookie to expire with browser close (0) for better security
            // This prevents session cookies from persisting after browser closes
            ini_set('session.cookie_lifetime', 0);
            
            session_start();
            
            // Regenerate session ID periodically to prevent session fixation
            if (!isset($_SESSION['session_started'])) {
                $_SESSION['session_started'] = time();
            } elseif (time() - $_SESSION['session_started'] > 1800) { // Regenerate every 30 minutes
                session_regenerate_id(true);
                $_SESSION['session_started'] = time();
            }
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
    
    public function getAssociation() {
        return $this->config['association'] ?? [];
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
        
        // Check specific permission from database
        // Note: Admin role no longer has automatic access - permissions must be explicitly granted
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
    
    public function logActivity($action, $module = null, $recordId = null, $description = null, $oldData = null, $newData = null) {
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
            'old_data' => is_array($oldData) ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : $oldData,
            'new_data' => is_array($newData) ? json_encode($newData, JSON_UNESCAPED_UNICODE) : $newData,
        ];
        
        try {
            $this->db->insert('activity_logs', $data);
        } catch (\Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
    
    /**
     * Log access to sensitive personal data for GDPR compliance
     * 
     * @param string $entityType Type of entity: 'member', 'junior_member', 'user'
     * @param int|null $entityId ID of the entity being accessed (required for single entity access)
     * @param string $accessType Type of access: 'view', 'edit', 'export', 'print', 'delete'
     * @param string $module Module performing the access
     * @param array|null $dataFields Array of sensitive data fields accessed (e.g., ['personal_data', 'contacts', 'health'])
     * @param string|null $purpose Purpose of the access
     */
    public function logSensitiveDataAccess($entityType, $entityId, $accessType, $module, $dataFields = null, $purpose = null) {
        if (!$this->isInstalled() || !$this->isLoggedIn()) {
            return;
        }
        
        $currentUser = $this->getCurrentUser();
        if (!$currentUser || !isset($currentUser['id'])) {
            error_log("Failed to log sensitive data access: User not properly authenticated");
            return;
        }
        
        $userId = $currentUser['id'];
        
        // Validate required entity_id for database constraint
        if ($entityId === null) {
            error_log("Failed to log sensitive data access: entity_id is required but was null");
            return;
        }
        
        // Encode data fields to JSON with error handling
        $dataFieldsJson = null;
        if ($dataFields) {
            $dataFieldsJson = json_encode($dataFields, JSON_UNESCAPED_UNICODE);
            if ($dataFieldsJson === false) {
                error_log("Failed to encode data fields for sensitive data access log: " . json_last_error_msg());
                $dataFieldsJson = json_encode(['encoding_error' => 'Failed to encode data fields']);
            }
        }
        
        $data = [
            'user_id' => $userId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'access_type' => $accessType,
            'module' => $module,
            'data_fields' => $dataFieldsJson,
            'purpose' => $purpose,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];
        
        try {
            $this->db->insert('sensitive_data_access_log', $data);
        } catch (\Exception $e) {
            error_log("Failed to log sensitive data access: " . $e->getMessage());
        }
    }
}
