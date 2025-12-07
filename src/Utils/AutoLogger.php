<?php
namespace EasyVol\Utils;

use EasyVol\App;

/**
 * Automatic Activity Logger
 * 
 * This class provides automatic logging functionality that can be included in any page
 */
class AutoLogger {
    private static $app = null;
    private static $logged = false;
    
    /**
     * Sensitive parameters that should never be logged
     */
    private const SENSITIVE_PARAMS = [
        'password', 'pwd', 'pass', 'token', 'csrf_token', 'api_key', 'secret', 
        'tax_code', 'codice_fiscale', 'fiscal_code', 'cf', 
        'ssn', 'social_security', 'card_number', 'cvv', 'pin'
    ];
    
    /**
     * Maximum length for parameter values in logs
     */
    private const MAX_PARAM_LENGTH = 100;
    
    /**
     * Initialize and log page access automatically
     */
    public static function logPageAccess() {
        if (self::$logged) {
            return; // Prevent duplicate logging
        }
        
        self::$app = App::getInstance();
        
        // Only log if user is logged in and installed
        if (!self::$app->isLoggedIn() || !self::$app->isInstalled()) {
            return;
        }
        
        // Get page information
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $pageName = basename($scriptName, '.php');
        
        // Determine action and module based on page
        $action = 'page_view';
        $module = self::getModuleFromPage($pageName);
        
        // Build description with query parameters
        $description = self::buildDescription();
        
        // Get record ID if viewing a specific record
        $recordId = self::extractRecordId();
        
        // Log the activity
        self::$app->logActivity($action, $module, $recordId, $description);
        self::$logged = true;
    }
    
    /**
     * Extract module name from page name
     */
    private static function getModuleFromPage($pageName) {
        // Map page names to modules
        $moduleMap = [
            'dashboard' => 'dashboard',
            'members' => 'members',
            'member_view' => 'members',
            'member_edit' => 'members',
            'member_data' => 'members',
            'junior_members' => 'junior_members',
            'junior_member_view' => 'junior_members',
            'junior_member_edit' => 'junior_members',
            'events' => 'events',
            'event_view' => 'events',
            'event_edit' => 'events',
            'vehicles' => 'vehicles',
            'vehicle_view' => 'vehicles',
            'vehicle_edit' => 'vehicles',
            'warehouse' => 'warehouse',
            'warehouse_view' => 'warehouse',
            'warehouse_edit' => 'warehouse',
            'documents' => 'documents',
            'document_view' => 'documents',
            'document_edit' => 'documents',
            'meetings' => 'meetings',
            'meeting_view' => 'meetings',
            'meeting_edit' => 'meetings',
            'training' => 'training',
            'training_view' => 'training',
            'training_edit' => 'training',
            'applications' => 'applications',
            'users' => 'users',
            'user_edit' => 'users',
            'roles' => 'roles',
            'role_edit' => 'roles',
            'reports' => 'reports',
            'settings' => 'settings',
            'profile' => 'profile',
            'scheduler' => 'scheduler',
            'scheduler_edit' => 'scheduler',
            'operations_center' => 'operations_center',
            'radio_directory' => 'radio',
            'radio_view' => 'radio',
            'radio_edit' => 'radio',
            'fee_payments' => 'fee_payments',
            'pay_fee' => 'fee_payments',
            'activity_logs' => 'activity_logs',
        ];
        
        return $moduleMap[$pageName] ?? $pageName;
    }
    
    /**
     * Build description from request parameters
     */
    private static function buildDescription() {
        $parts = [];
        
        // Add query parameters
        if (!empty($_GET)) {
            $params = $_GET;
            unset($params['PHPSESSID']); // Remove session
            
            // Format search/filter parameters
            if (isset($params['search']) && $params['search']) {
                $parts[] = "Ricerca: " . $params['search'];
            }
            
            if (isset($params['status']) && $params['status']) {
                $parts[] = "Stato: " . $params['status'];
            }
            
            if (isset($params['volunteer_status']) && $params['volunteer_status']) {
                $parts[] = "Stato volontario: " . $params['volunteer_status'];
            }
            
            if (isset($params['type']) && $params['type']) {
                $parts[] = "Tipo: " . $params['type'];
            }
            
            if (isset($params['date_from']) && $params['date_from']) {
                $parts[] = "Da: " . $params['date_from'];
            }
            
            if (isset($params['date_to']) && $params['date_to']) {
                $parts[] = "A: " . $params['date_to'];
            }
            
            // If there are other parameters not yet captured
            // Filter out sensitive parameters before logging
            $remainingParams = array_diff_key($params, array_flip(array_merge(
                ['search', 'status', 'volunteer_status', 'type', 'date_from', 'date_to', 'page', 'id'],
                self::SENSITIVE_PARAMS
            )));
            if (!empty($remainingParams)) {
                // Sanitize values for logging
                $sanitizedParams = array_map(function($value) {
                    if (is_string($value) && strlen($value) > self::MAX_PARAM_LENGTH) {
                        return substr($value, 0, self::MAX_PARAM_LENGTH) . '...';
                    }
                    return $value;
                }, $remainingParams);
                $parts[] = "Parametri: " . json_encode($sanitizedParams, JSON_UNESCAPED_UNICODE);
            }
        }
        
        // Add request method if not GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $parts[] = "Metodo: " . $_SERVER['REQUEST_METHOD'];
        }
        
        return !empty($parts) ? implode(', ', $parts) : null;
    }
    
    /**
     * Extract record ID from common parameter names
     */
    private static function extractRecordId() {
        // Common parameter names for record IDs
        $idParams = ['id', 'member_id', 'event_id', 'vehicle_id', 'document_id', 'meeting_id', 'user_id'];
        
        foreach ($idParams as $param) {
            if (isset($_GET[$param]) && is_numeric($_GET[$param])) {
                return (int)$_GET[$param];
            }
        }
        
        return null;
    }
    
    /**
     * Log a search action
     */
    public static function logSearch($module, $searchTerm, $filters = []) {
        if (!self::$app) {
            self::$app = App::getInstance();
        }
        
        $description = "Ricerca: {$searchTerm}";
        if (!empty($filters)) {
            $description .= ", Filtri: " . json_encode($filters, JSON_UNESCAPED_UNICODE);
        }
        
        self::$app->logActivity('search', $module, null, $description);
    }
    
    /**
     * Log an export action
     */
    public static function logExport($module, $format, $description = null) {
        if (!self::$app) {
            self::$app = App::getInstance();
        }
        
        $desc = "Export {$format}";
        if ($description) {
            $desc .= ": {$description}";
        }
        
        self::$app->logActivity('export', $module, null, $desc);
    }
}
