<?php
/**
 * Print Generation Endpoint - Simplified
 * 
 * Directly generates and downloads PDF from template
 * No editable HTML generation - direct PDF download only
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\PrintTemplateController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    http_response_code(403);
    die('Accesso negato: non autenticato');
}

// Get parameters
$templateId = $_GET['template_id'] ?? null;

if (!$templateId) {
    http_response_code(400);
    die('Errore: Template ID richiesto');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new PrintTemplateController($db, $config);

try {
    // Get template to check entity type for permissions
    $template = $controller->getById($templateId);
    
    if (!$template) {
        throw new \Exception('Template non trovato');
    }
    
    // Check permission based on entity type
    $entityType = $template['entity_type'];
    $permissionModule = '';
    switch ($entityType) {
        case 'members':
        case 'junior_members':
            $permissionModule = 'members';
            break;
        case 'vehicles':
            $permissionModule = 'vehicles';
            break;
        case 'meetings':
            $permissionModule = 'meetings';
            break;
        case 'events':
            $permissionModule = 'events';
            break;
        default:
            $permissionModule = $entityType;
    }
    
    if (!$app->checkPermission($permissionModule, 'view')) {
        http_response_code(403);
        die('Accesso negato: permessi insufficienti');
    }
    
    // Build options for PDF generation
    $options = [];
    
    // Get record ID for single templates
    if (isset($_GET['record_id'])) {
        $options['record_id'] = intval($_GET['record_id']);
    }
    
    // Get record IDs for list templates with specific records
    if (isset($_GET['record_ids'])) {
        $recordIds = $_GET['record_ids'];
        if (is_string($recordIds)) {
            $recordIds = explode(',', $recordIds);
        }
        $options['record_ids'] = array_map('intval', $recordIds);
    }
    
    // Get filters for list templates
    $filters = [];
    if (isset($_GET['filters'])) {
        $filters = $_GET['filters'];
    } else {
        // Check individual filter parameters
        if (isset($_GET['member_status'])) {
            $filters['member_status'] = $_GET['member_status'];
        }
        if (isset($_GET['member_type'])) {
            $filters['member_type'] = $_GET['member_type'];
        }
        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        if (isset($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }
        if (isset($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }
    }
    if (!empty($filters)) {
        $options['filters'] = $filters;
    }
    
    // Clear any previous output to prevent PDF corruption
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Generate and download PDF directly
    $controller->generatePdf($templateId, $options, 'D');
    exit; // Important: exit after PDF output
    
} catch (\Exception $e) {
    // Clear output buffer in case of error
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Log the full error for debugging
    error_log("Print generation error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    die('Errore nella generazione del PDF. Contattare l\'amministratore del sistema.');
}
