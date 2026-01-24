<?php
/**
 * Generate HTML preview for print templates
 * Returns processed HTML without PDF conversion
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\PrintTemplateController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    http_response_code(403);
    die('Accesso negato:  non autenticato');
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
    // Get template
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
    
    // Build options for generation
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
        if (isset($_GET['vehicle_type'])) {
            // Validate vehicle_type against allowed enum values
            $allowedVehicleTypes = ['veicolo', 'natante', 'rimorchio'];
            if (in_array($_GET['vehicle_type'], $allowedVehicleTypes, true)) {
                $filters['vehicle_type'] = $_GET['vehicle_type'];
            }
        }
        if (isset($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }
    }
    if (!empty($filters)) {
        $options['filters'] = $filters;
    }
    
    // Generate HTML (not PDF)
    require_once __DIR__ . '/../src/Utils/SimplePdfGenerator.php';
    $pdfGenerator = new EasyVol\Utils\SimplePdfGenerator($db, $config);
    
    // Prepare data
    $data = $pdfGenerator->prepareData($template, $options);
    
    // Process template to get HTML
    $html = $pdfGenerator->processTemplate($template['html_content'], $data);
    
    // Wrap in a complete HTML document with CSS
    ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview - <?php echo htmlspecialchars($template['name']); ?></title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        <?php echo $template['css_content'] ?? ''; ?>
    </style>
</head>
<body>
    <?php echo $html; ?>
</body>
</html>
    <?php
    
} catch (\Exception $e) {
    http_response_code(500);
    error_log("HTML Preview Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Errore Preview</title>
</head>
<body>
    <div style="padding: 20px; color: red;">
        <h3>Errore durante la generazione dell'anteprima</h3>
        <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
    </div>
</body>
</html>
    <?php
}