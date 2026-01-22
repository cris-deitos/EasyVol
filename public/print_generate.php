<?php
// Debug mode - remove after testing
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Print Generation Endpoint
 * 
 * Genera documenti da template
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\PrintTemplateController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

// Check permission based on entity type
$entityType = $_GET['entity'] ?? 'members';
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
    echo json_encode(['error' => 'Accesso negato']);
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new PrintTemplateController($db, $config);

try {
    $templateId = $_GET['template_id'] ?? null;
    
    if (!$templateId) {
        throw new \Exception('Template ID richiesto');
    }
    
    $options = [];
    
    // Get record ID for single/relational templates
    if (isset($_GET['record_id'])) {
        $options['record_id'] = intval($_GET['record_id']);
    }
    
    // Get record IDs for multi-page templates
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
        if (isset($_GET['vehicle_type'])) {
            $filters['vehicle_type'] = $_GET['vehicle_type'];
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
    
    // Generate document
    $result = $controller->generate($templateId, $options);
    
    // Return as JSON or HTML based on request
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        // Return HTML document
        header('Content-Type: text/html; charset=utf-8');
        
        echo '<!DOCTYPE html>';
        echo '<html lang="it">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Stampa</title>';
        
        // Add CSS
        echo '<style>';
        echo 'body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }';
        echo '@page { size: ' . $result['page_format'] . ' ' . $result['page_orientation'] . '; margin: 2cm; }';
        echo '@media print { body { margin: 0; padding: 0; } }';
        echo '.page-break { page-break-after: always; }';
        
        // Watermark
        if (!empty($result['watermark'])) {
            echo 'body::before { content: "' . htmlspecialchars($result['watermark']) . '"; ';
            echo 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); ';
            echo 'font-size: 120px; color: rgba(0, 0, 0, 0.05); z-index: -1; white-space: nowrap; }';
        }
        
        // Custom CSS
        if (!empty($result['css'])) {
            echo $result['css'];
        }
        echo '</style>';
        echo '</head>';
        echo '<body>';
        
        // Header
        if (!empty($result['header'])) {
            echo '<div class="document-header">' . $result['header'] . '</div>';
        }
        
        // Content
        echo $result['html'];
        
        // Footer
        if (!empty($result['footer'])) {
            echo '<div class="document-footer">' . $result['footer'] . '</div>';
        }
        
        echo '</body>';
        echo '</html>';
    }
    
} catch (\Exception $e) {
    http_response_code(400);
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        echo '<html><body><h1>Errore</h1><p>' . htmlspecialchars($e->getMessage()) . '</p></body></html>';
    }
}
