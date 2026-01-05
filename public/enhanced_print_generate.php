<?php
/**
 * Enhanced Print Generation
 * 
 * Generates documents using file-based or database templates
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\EnhancedPrintController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    http_response_code(403);
    die('Non autenticato');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new EnhancedPrintController($db, $config);

try {
    $templateId = $_GET['template_id'] ?? null;
    $entityType = $_GET['entity'] ?? 'members';
    $output = $_GET['output'] ?? 'html';
    
    if (!$templateId) {
        throw new \Exception('Template ID richiesto');
    }
    
    // Build options
    $options = [];
    
    // Single record options
    if (isset($_GET['record_id']) && !empty($_GET['record_id'])) {
        $options['record_id'] = intval($_GET['record_id']);
    }
    
    // Multi-page record IDs
    if (isset($_GET['record_ids']) && !empty($_GET['record_ids'])) {
        $recordIds = $_GET['record_ids'];
        if (is_string($recordIds)) {
            $recordIds = explode(',', $recordIds);
        }
        $options['record_ids'] = array_map('intval', $recordIds);
    }
    
    // Filters for list templates
    if (isset($_GET['filters']) && is_array($_GET['filters'])) {
        $options['filters'] = array_filter($_GET['filters'], function($value) {
            return $value !== '';
        });
    }
    
    if ($output === 'pdf') {
        // Generate PDF
        $filename = 'documento_' . date('Y-m-d_His') . '.pdf';
        $options['filename'] = $filename;
        $controller->generatePdf($templateId, $entityType, $options, 'D');
    } else {
        // Generate HTML document
        $document = $controller->generate($templateId, $entityType, $options);
        
        // Output HTML
        header('Content-Type: text/html; charset=utf-8');
        
        echo '<!DOCTYPE html>';
        echo '<html lang="it">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Anteprima Stampa</title>';
        
        // Add CSS
        echo '<style>';
        echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
        echo '@page { size: ' . ($document['format'] ?? 'A4') . ' ' . ($document['orientation'] ?? 'portrait') . '; }';
        echo '@media print { body { margin: 0; } }';
        
        // Custom CSS
        if (!empty($document['css'])) {
            echo $document['css'];
        }
        echo '</style>';
        echo '</head>';
        echo '<body>';
        
        // Content
        echo $document['html'];
        
        echo '</body>';
        echo '</html>';
    }
    
} catch (\Exception $e) {
    http_response_code(400);
    echo '<!DOCTYPE html>';
    echo '<html lang="it">';
    echo '<head><meta charset="UTF-8"><title>Errore</title></head>';
    echo '<body>';
    echo '<div style="padding: 20px; border: 2px solid #d32f2f; background: #ffebee; color: #c62828; font-family: Arial, sans-serif;">';
    echo '<h1>Errore</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><small>File: ' . htmlspecialchars($e->getFile()) . ' (linea ' . $e->getLine() . ')</small></p>';
    echo '</div>';
    echo '</body>';
    echo '</html>';
}
