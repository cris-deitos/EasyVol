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
     * Get Italian page name for display
     */
    private static function getPageNameItalian($pageName) {
        $pageNames = [
            'dashboard' => 'Dashboard',
            'members' => 'Lista Soci',
            'member_view' => 'Visualizzazione Socio',
            'member_edit' => 'Modifica Socio',
            'member_data' => 'Dati Socio',
            'member_contact_edit' => 'Modifica Contatto Socio',
            'member_address_edit' => 'Modifica Indirizzo Socio',
            'member_employment_edit' => 'Modifica Lavoro Socio',
            'member_role_edit' => 'Modifica Qualifica Socio',
            'member_course_edit' => 'Modifica Corso Socio',
            'member_license_edit' => 'Modifica Patente Socio',
            'member_health_edit' => 'Modifica Info Sanitarie Socio',
            'member_availability_edit' => 'Modifica Disponibilità Socio',
            'member_fee_edit' => 'Modifica Quota Socio',
            'member_sanction_edit' => 'Modifica Provvedimento Socio',
            'member_note_edit' => 'Modifica Nota Socio',
            'member_attachment_edit' => 'Modifica Allegato Socio',
            'junior_members' => 'Lista Cadetti',
            'junior_member_view' => 'Visualizzazione Cadetto',
            'junior_member_edit' => 'Modifica Cadetto',
            'junior_member_contact_edit' => 'Modifica Contatto Cadetto',
            'junior_member_address_edit' => 'Modifica Indirizzo Cadetto',
            'junior_member_guardian_edit' => 'Modifica Tutore Cadetto',
            'junior_member_health_edit' => 'Modifica Info Sanitarie Cadetto',
            'junior_member_sanction_edit' => 'Modifica Provvedimento Cadetto',
            'events' => 'Lista Eventi',
            'event_view' => 'Visualizzazione Evento',
            'event_edit' => 'Modifica Evento',
            'vehicles' => 'Lista Mezzi',
            'vehicle_view' => 'Visualizzazione Mezzo',
            'vehicle_edit' => 'Modifica Mezzo',
            'warehouse' => 'Magazzino',
            'warehouse_view' => 'Visualizzazione Articolo',
            'warehouse_edit' => 'Modifica Articolo',
            'documents' => 'Documenti',
            'document_view' => 'Visualizzazione Documento',
            'document_edit' => 'Modifica Documento',
            'meetings' => 'Riunioni',
            'meeting_view' => 'Visualizzazione Riunione',
            'meeting_edit' => 'Modifica Riunione',
            'training' => 'Corsi di Formazione',
            'training_view' => 'Visualizzazione Corso',
            'training_edit' => 'Modifica Corso',
            'applications' => 'Domande di Iscrizione',
            'users' => 'Gestione Utenti',
            'user_edit' => 'Modifica Utente',
            'roles' => 'Gestione Ruoli',
            'role_edit' => 'Modifica Ruolo',
            'reports' => 'Report',
            'settings' => 'Impostazioni',
            'profile' => 'Profilo Utente',
            'scheduler' => 'Scadenziario',
            'scheduler_edit' => 'Modifica Scadenza',
            'operations_center' => 'Centro Operativo',
            'radio_directory' => 'Rubrica Radio',
            'radio_view' => 'Visualizzazione Radio',
            'radio_edit' => 'Modifica Radio',
            'fee_payments' => 'Pagamenti Quote',
            'pay_fee' => 'Pagamento Quota',
            'activity_logs' => 'Log Attività',
            'import_data' => 'Importazione Dati CSV',
        ];
        
        return $pageNames[$pageName] ?? ucfirst(str_replace('_', ' ', $pageName));
    }
    
    /**
     * Build description from request parameters
     */
    private static function buildDescription() {
        $parts = [];
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $pageName = basename($scriptName, '.php');
        
        // Add page name in Italian
        $parts[] = self::getPageNameItalian($pageName);
        
        // Add query parameters
        if (!empty($_GET)) {
            $params = $_GET;
            unset($params['PHPSESSID']); // Remove session
            
            // Format search/filter parameters with labels
            if (isset($params['search']) && $params['search']) {
                $parts[] = "Filtro ricerca testuale: '" . $params['search'] . "'";
            }
            
            if (isset($params['status']) && $params['status']) {
                $statusLabels = [
                    'attivo' => 'Attivo',
                    'decaduto' => 'Decaduto',
                    'dimesso' => 'Dimesso',
                    'in_aspettativa' => 'In Aspettativa',
                    'sospeso' => 'Sospeso',
                    'in_congedo' => 'In Congedo',
                    'pending' => 'In Attesa',
                    'approved' => 'Approvato',
                    'rejected' => 'Rifiutato',
                    'operativo' => 'Operativo',
                    'in_manutenzione' => 'In Manutenzione',
                    'fuori_servizio' => 'Fuori Servizio'
                ];
                $statusLabel = $statusLabels[$params['status']] ?? $params['status'];
                $parts[] = "Filtro stato: " . $statusLabel;
            }
            
            if (isset($params['volunteer_status']) && $params['volunteer_status']) {
                $volStatusLabels = [
                    'operativo' => 'Operativo',
                    'non_operativo' => 'Non Operativo',
                    'in_formazione' => 'In Formazione'
                ];
                $statusLabel = $volStatusLabels[$params['volunteer_status']] ?? $params['volunteer_status'];
                $parts[] = "Filtro stato volontario: " . $statusLabel;
            }
            
            if (isset($params['member_type']) && $params['member_type']) {
                $typeLabels = [
                    'ordinario' => 'Ordinario',
                    'fondatore' => 'Fondatore'
                ];
                $typeLabel = $typeLabels[$params['member_type']] ?? $params['member_type'];
                $parts[] = "Filtro tipo socio: " . $typeLabel;
            }
            
            if (isset($params['type']) && $params['type']) {
                $parts[] = "Filtro tipo: " . $params['type'];
            }
            
            if (isset($params['date_from']) && $params['date_from']) {
                $parts[] = "Filtro data da: " . $params['date_from'];
            }
            
            if (isset($params['date_to']) && $params['date_to']) {
                $parts[] = "Filtro data a: " . $params['date_to'];
            }
            
            if (isset($params['tab']) && $params['tab']) {
                $tabLabels = [
                    'personal' => 'Dati Personali',
                    'contacts' => 'Contatti',
                    'address' => 'Indirizzi',
                    'employment' => 'Lavoro',
                    'qualifications' => 'Qualifiche',
                    'courses' => 'Corsi',
                    'licenses' => 'Patenti',
                    'health' => 'Informazioni Sanitarie',
                    'availability' => 'Disponibilità',
                    'fees' => 'Quote Sociali',
                    'sanctions' => 'Provvedimenti',
                    'notes' => 'Note',
                    'attachments' => 'Allegati',
                    'guardians' => 'Tutori'
                ];
                $tabLabel = $tabLabels[$params['tab']] ?? $params['tab'];
                $parts[] = "Scheda: " . $tabLabel;
            }
            
            // If there are other parameters not yet captured
            // Filter out sensitive parameters before logging
            $remainingParams = array_diff_key($params, array_flip(array_merge(
                ['search', 'status', 'volunteer_status', 'member_type', 'type', 'date_from', 'date_to', 'page', 'id', 'tab', 'success', 'error'],
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
                $parts[] = "Altri parametri: " . json_encode($sanitizedParams, JSON_UNESCAPED_UNICODE);
            }
        }
        
        return !empty($parts) ? implode(' | ', $parts) : null;
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
