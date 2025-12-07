<?php
namespace EasyVol\Middleware;

use EasyVol\App;

/**
 * Activity Logger Middleware
 * 
 * Logs all user activities including page views, searches, and any interactions
 */
class ActivityLogger {
    private $app;
    
    public function __construct() {
        $this->app = App::getInstance();
    }
    
    /**
     * Log a page view
     */
    public function logPageView($pageName = null) {
        if (!$pageName) {
            $pageName = $_SERVER['PHP_SELF'] ?? 'unknown';
            $pageName = basename($pageName, '.php');
        }
        
        // Get query parameters for search/filter tracking
        $queryParams = $_GET;
        $description = null;
        
        if (!empty($queryParams)) {
            unset($queryParams['PHPSESSID']); // Remove session ID
            $description = 'Parametri: ' . json_encode($queryParams, JSON_UNESCAPED_UNICODE);
        }
        
        $this->app->logActivity('page_view', $pageName, null, $description);
    }
    
    /**
     * Log a search action
     */
    public function logSearch($module, $searchParams) {
        $description = 'Ricerca: ' . json_encode($searchParams, JSON_UNESCAPED_UNICODE);
        $this->app->logActivity('search', $module, null, $description);
    }
    
    /**
     * Log a filter action
     */
    public function logFilter($module, $filterParams) {
        $description = 'Filtri: ' . json_encode($filterParams, JSON_UNESCAPED_UNICODE);
        $this->app->logActivity('filter', $module, null, $description);
    }
    
    /**
     * Log a record view
     */
    public function logRecordView($module, $recordId, $recordTitle = null) {
        $description = $recordTitle ? "Visualizzazione: {$recordTitle}" : null;
        $this->app->logActivity('view', $module, $recordId, $description);
    }
    
    /**
     * Log a form submission
     */
    public function logFormSubmit($module, $action, $recordId = null, $details = null) {
        $this->app->logActivity($action, $module, $recordId, $details);
    }
    
    /**
     * Log an export action
     */
    public function logExport($module, $format, $description = null) {
        $desc = "Export {$format}";
        if ($description) {
            $desc .= ": {$description}";
        }
        $this->app->logActivity('export', $module, null, $desc);
    }
    
    /**
     * Log a print action
     */
    public function logPrint($module, $recordId = null, $description = null) {
        $this->app->logActivity('print', $module, $recordId, $description);
    }
    
    /**
     * Log an API call
     */
    public function logApiCall($endpoint, $method, $description = null) {
        $desc = "API {$method}: {$endpoint}";
        if ($description) {
            $desc .= " - {$description}";
        }
        $this->app->logActivity('api_call', 'api', null, $desc);
    }
}
