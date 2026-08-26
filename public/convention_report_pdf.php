<?php
/**
 * Convention Report PDF Generator
 * 
 * Genera un report PDF degli eventi associati a una convenzione per un dato anno,
 * raggruppati per tipo evento e comune.
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('conventions', 'view')) {
    http_response_code(403);
    die('Accesso negato');
}

$db = $app->getDb();
$config = $app->getConfig();

$conventionId = isset($_GET['convention_id']) ? intval($_GET['convention_id']) : 0;
$year = isset($_GET['year']) ? intval($_GET['year']) : (int)date('Y');

if ($conventionId <= 0) {
    die('Convenzione non valida');
}

// Get convention data
$convention = $db->fetchOne("SELECT * FROM conventions WHERE id = ?", [$conventionId]);
if (!$convention) {
    die('Convenzione non trovata');
}

// Get association data
$association = $db->fetchOne("SELECT * FROM association LIMIT 1");
if (!$association) {
    $association = ['name' => 'Associazione di Volontariato', 'address_street' => '', 'address_city' => '', 'address_province' => '', 'phone' => '', 'email' => ''];
}

// Get events for this convention in the selected year
$events = $db->fetchAll(
    "SELECT * FROM events WHERE convention_id = ? AND YEAR(start_date) = ? ORDER BY start_date ASC",
    [$conventionId, $year]
);

// Group events by type and municipality
$eventTypes = [
    'emergenza' => 'Emergenze',
    'esercitazione' => 'Esercitazioni',
    'attivita' => 'Attività',
    'servizio' => 'Servizi',
];

$grouped = [];
$totalByMunicipality = [];

foreach ($eventTypes as $typeKey => $typeLabel) {
    $grouped[$typeKey] = [];
}

foreach ($events as $event) {
    $type = $event['event_type'] ?? 'altro';
    $municipality = !empty($event['municipality']) ? $event['municipality'] : 'Non specificato';
    
    if (!isset($grouped[$type])) {
        continue; // skip types not in our list
    }
    
    if (!isset($grouped[$type][$municipality])) {
        $grouped[$type][$municipality] = [];
    }
    $grouped[$type][$municipality][] = $event;
    
    if (!isset($totalByMunicipality[$municipality])) {
        $totalByMunicipality[$municipality] = 0;
    }
    $totalByMunicipality[$municipality]++;
}

// Sort municipalities
foreach ($grouped as $type => &$municipalities) {
    ksort($municipalities);
}
unset($municipalities);
ksort($totalByMunicipality);

// Log activity
AutoLogger::logActivity('conventions', 'report_pdf', $conventionId, "Generazione Report PDF Convenzione - Anno {$year}");

// Generate PDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 20,
    'margin_bottom' => 20,
    'margin_header' => 10,
    'margin_footer' => 10,
]);

// Build HTML
$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10pt; line-height: 1.5; }
h1 { color: #003366; text-align: center; font-size: 16pt; margin-bottom: 5px; }
h2 { color: #003366; font-size: 13pt; margin-top: 20px; border-bottom: 2px solid #003366; padding-bottom: 5px; }
h3 { color: #0066cc; font-size: 11pt; margin-top: 10px; margin-bottom: 5px; }
.header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #003366; padding-bottom: 10px; }
.subtitle { text-align: center; font-size: 9pt; color: #555; }
.municipality { font-weight: bold; margin-top: 10px; margin-bottom: 5px; }
.event-list { margin-left: 15px; margin-bottom: 10px; }
.event-item { margin-bottom: 3px; }
.totals-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
.totals-table th, .totals-table td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; }
.totals-table th { background-color: #003366; color: white; }
</style></head><body>';

// Header
$html .= '<div class="header">';
$html .= '<h1>' . htmlspecialchars($association['name']) . '</h1>';
$address = trim(($association['address_street'] ?? '') . ' ' . ($association['address_number'] ?? ''));
$cityLine = trim(($association['address_cap'] ?? '') . ' ' . ($association['address_city'] ?? '') . ' (' . ($association['address_province'] ?? '') . ')');
if ($address || $cityLine) {
    $html .= '<p class="subtitle">' . htmlspecialchars(trim($address . ' - ' . $cityLine, ' -')) . '</p>';
}
$contacts = [];
if (!empty($association['phone'])) $contacts[] = 'Tel: ' . $association['phone'];
if (!empty($association['email'])) $contacts[] = 'Email: ' . $association['email'];
if ($contacts) {
    $html .= '<p class="subtitle">' . htmlspecialchars(implode(' | ', $contacts)) . '</p>';
}
$html .= '</div>';

// Title
$html .= '<h1>Report Convenzione: ' . htmlspecialchars($convention['name']) . '</h1>';
$html .= '<p style="text-align:center; font-size:11pt;">Anno ' . $year . '</p>';

// Sections by event type
foreach ($eventTypes as $typeKey => $typeLabel) {
    $html .= '<h2>' . htmlspecialchars($typeLabel) . '</h2>';
    
    if (empty($grouped[$typeKey])) {
        $html .= '<p><em>Nessun evento registrato.</em></p>';
        continue;
    }
    
    foreach ($grouped[$typeKey] as $municipality => $municipalityEvents) {
        $count = count($municipalityEvents);
        $html .= '<p class="municipality">Comune di ' . htmlspecialchars($municipality) . ': ' . $count . '</p>';
        $html .= '<div class="event-list">';
        $num = 1;
        foreach ($municipalityEvents as $ev) {
            $date = date('d/m/Y', strtotime($ev['start_date']));
            $title = htmlspecialchars($ev['title'] ?? '');
            $desc = htmlspecialchars($ev['description'] ?? '');
            $html .= '<p class="event-item">' . $num . ': ' . $date . ' - ' . $title . ' - ' . $desc . '</p>';
            $num++;
        }
        $html .= '</div>';
    }
}

// Totals
$html .= '<h2>Totale Eventi</h2>';
if (empty($totalByMunicipality)) {
    $html .= '<p><em>Nessun evento registrato per questa convenzione nell\'anno ' . $year . '.</em></p>';
} else {
    $html .= '<table class="totals-table">';
    $html .= '<tr><th>Comune</th><th>Totale Eventi</th></tr>';
    $grandTotal = 0;
    foreach ($totalByMunicipality as $municipality => $count) {
        $html .= '<tr><td>Comune di ' . htmlspecialchars($municipality) . '</td><td>' . $count . '</td></tr>';
        $grandTotal += $count;
    }
    $html .= '<tr><td><strong>TOTALE</strong></td><td><strong>' . $grandTotal . '</strong></td></tr>';
    $html .= '</table>';
}

$html .= '</body></html>';

$mpdf->WriteHTML($html);

$filename = 'report_convenzione_' . $conventionId . '_' . $year . '.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
exit;
