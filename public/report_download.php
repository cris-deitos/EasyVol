<?php
/**
 * Report Download Handler
 * 
 * Gestisce il download dei report in formato Excel
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\ReportController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    http_response_code(403);
    die('Accesso negato');
}

if (!$app->checkPermission('reports', 'view')) {
    http_response_code(403);
    die('Accesso negato - permessi insufficienti');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new ReportController($db, $config);

// Parametri
$reportType = $_GET['type'] ?? '';
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validazione anno
$minYear = $config['reports']['min_year'] ?? 2020;
$maxYear = date('Y') + 1;
if ($year < $minYear || $year > $maxYear) {
    http_response_code(400);
    die('Anno non valido');
}

try {
    switch ($reportType) {
        case 'annual_association_report':
            // Ottieni dati dell'associazione
            $associationSql = "SELECT * FROM association LIMIT 1";
            $associationData = $db->fetchOne($associationSql);
            
            if (empty($associationData)) {
                $associationData = [
                    'name' => 'Associazione di Volontariato',
                    'address_street' => '',
                    'address_number' => '',
                    'address_city' => '',
                    'address_province' => '',
                    'address_cap' => '',
                    'email' => '',
                    'phone' => '',
                ];
            }
            
            // Log activity
            AutoLogger::logActivity('reports', 'export', null, "Export Report Annuale Associativo - Anno {$year}");
            
            // Genera PDF
            $controller->generateAnnualAssociationReportPDF($year, $associationData);
            break;
            
        case 'volunteer_hours_by_event_type':
            $data = $controller->volunteerHoursByEventType($year);
            $sheetName = 'Ore Volontariato';
            $filename = "ore_volontariato_per_tipo_evento_{$year}.xlsx";
            
            // Log activity
            AutoLogger::logActivity('reports', 'export', null, "Export report ore volontariato per tipo evento - Anno {$year}");
            break;
            
        case 'events_by_type_and_count':
            $data = $controller->eventsByTypeAndCount($year);
            $sheetName = 'Eventi';
            $filename = "report_eventi_{$year}.xlsx";
            
            // Log activity
            AutoLogger::logActivity('reports', 'export', null, "Export report numero e tipologie eventi - Anno {$year}");
            break;
            
        case 'volunteer_activity':
            $data = $controller->volunteerActivityReport($year);
            $sheetName = 'Attività Volontari';
            $filename = "report_attivita_volontari_{$year}.xlsx";
            
            // Log activity
            AutoLogger::logActivity('reports', 'export', null, "Export report attività per volontario - Anno {$year}");
            break;
            
        case 'vehicle_kilometers':
            $data = $controller->vehicleKilometersReport($year);
            $sheetName = 'Km Mezzi';
            $filename = "report_km_mezzi_{$year}.xlsx";
            
            // Log activity
            AutoLogger::logActivity('reports', 'export', null, "Export report chilometri mezzi - Anno {$year}");
            break;
            
        case 'mantenimento_requisiti':
            // Ottieni dati dell'associazione
            $associationSql = "SELECT * FROM association LIMIT 1";
            $associationData = $db->fetchOne($associationSql);
            
            if (empty($associationData)) {
                $associationData = [
                    'name' => 'Associazione di Volontariato',
                    'address_street' => '',
                    'address_number' => '',
                    'address_city' => '',
                    'address_province' => '',
                    'address_cap' => '',
                    'email' => '',
                    'phone' => '',
                ];
            }
            
            // Log activity
            AutoLogger::logActivity('reports', 'export', null, "Export Report Mantenimento Requisiti - Anno {$year}");
            
            // Genera PDF
            $controller->generateMantenimentoRequisitiReportPDF($year, $associationData);
            break;
            
        case 'members_by_status':
            $filterValue = $_GET['value'] ?? '';
            $downloadFormat = $_GET['format'] ?? 'excel';
            if (empty($filterValue)) {
                http_response_code(400);
                die('Valore stato non specificato');
            }
            AutoLogger::logActivity('reports', 'export', null, "Export soci per stato: {$filterValue} - formato {$downloadFormat}");
            if ($downloadFormat === 'pdf') {
                $controller->exportMembersByStatusPdf($filterValue);
            } else {
                $controller->exportMembersByStatusExcel($filterValue);
            }
            break;
            
        case 'members_by_qualification':
            $filterValue = $_GET['value'] ?? '';
            $downloadFormat = $_GET['format'] ?? 'excel';
            if (empty($filterValue)) {
                http_response_code(400);
                die('Valore mansione non specificato');
            }
            AutoLogger::logActivity('reports', 'export', null, "Export soci per mansione: {$filterValue} - formato {$downloadFormat}");
            if ($downloadFormat === 'pdf') {
                $controller->exportMembersByQualificationPdf($filterValue);
            } else {
                $controller->exportMembersByQualificationExcel($filterValue);
            }
            break;
            
        default:
            http_response_code(400);
            die('Tipo di report non valido');
    }
    
    // Per i report Excel, verifica che ci siano dati
    if (!in_array($reportType, ['annual_association_report', 'mantenimento_requisiti', 'members_by_status', 'members_by_qualification'])) {
        if (empty($data)) {
            http_response_code(404);
            die('Nessun dato disponibile per il periodo selezionato');
        }
        
        // Genera Excel
        $controller->exportToExcel($data, $sheetName, $filename);
    }
    
} catch (\Exception $e) {
    error_log("Errore generazione report: " . $e->getMessage());
    http_response_code(500);
    die('Errore durante la generazione del report: ' . htmlspecialchars($e->getMessage()));
}
