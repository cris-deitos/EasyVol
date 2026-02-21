<?php
/**
 * Radio Directory PDF Export
 *
 * Exports radio lists as PDF:
 *   type=assigned   – Assigned radios: Nome, Identificativo, Tipo, Marca, Modello, Seriale,
 *                     Assegnatario, Telefono, Data/Ora Assegnazione
 *   type=unassigned – Unassigned radios: Nome, Identificativo, Tipo, Marca, Modello, Seriale
 *   type=all        – All radios: Nome, Identificativo, Tipo, Marca, Modello, Seriale
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Utils\PdfGenerator;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    http_response_code(403);
    die('Accesso negato');
}

if (!$app->checkPermission('operations_center', 'view')) {
    http_response_code(403);
    die('Accesso negato - permessi insufficienti');
}

AutoLogger::logPageAccess();

$type = isset($_GET['type']) ? $_GET['type'] : 'all';
if (!in_array($type, ['assigned', 'unassigned', 'all'], true)) {
    $type = 'all';
}

$db  = $app->getDb();
$config = $app->getConfig();

// Retrieve association data for the header
$associationData = $db->fetchOne("SELECT * FROM association LIMIT 1");
$associationName = $associationData['name'] ?? 'Associazione di Volontariato';

try {
    if ($type === 'assigned') {
        // Radios currently assigned – include assignee info
        $radios = $db->fetchAll(
            "SELECT rd.name, rd.identifier, rd.device_type, rd.brand, rd.model, rd.serial_number,
                    CONCAT(ra.assignee_first_name, ' ', ra.assignee_last_name) AS assignee_name,
                    COALESCE(mc.value, ra.assignee_phone) AS assignee_phone,
                    ra.assignment_date
             FROM radio_directory rd
             LEFT JOIN radio_assignments ra ON (rd.id = ra.radio_id
                                                AND ra.status = 'assegnata'
                                                AND ra.return_date IS NULL)
             LEFT JOIN members m ON ra.member_id = m.id
             LEFT JOIN member_contacts mc ON (m.id = mc.member_id
                                              AND mc.contact_type = 'cellulare')
             WHERE rd.status = 'assegnata'
             ORDER BY rd.name"
        );
        $listTitle   = 'Elenco Radio Assegnate';
        $filename    = 'radio_assegnate_' . date('Ymd') . '.pdf';
        $orientation = 'L'; // Landscape for the wider table
    } elseif ($type === 'unassigned') {
        $radios = $db->fetchAll(
            "SELECT name, identifier, device_type, brand, model, serial_number
             FROM radio_directory
             WHERE status != 'assegnata'
             ORDER BY name"
        );
        $listTitle   = 'Elenco Radio Non Assegnate';
        $filename    = 'radio_non_assegnate_' . date('Ymd') . '.pdf';
        $orientation = 'P';
    } else {
        $radios = $db->fetchAll(
            "SELECT name, identifier, device_type, brand, model, serial_number
             FROM radio_directory
             ORDER BY name"
        );
        $listTitle   = 'Elenco Completo Radio';
        $filename    = 'radio_elenco_completo_' . date('Ymd') . '.pdf';
        $orientation = 'P';
    }

    // Log activity
    AutoLogger::logActivity('radio', 'export_pdf', null, "Export PDF: $listTitle");

    // Build PDF HTML
    $html = '
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; }
        h1   { text-align: center; font-size: 15pt; margin-bottom: 4px; }
        h2   { text-align: center; font-size: 11pt; color: #444; margin-top: 0; margin-bottom: 18px; }
        p.gen{ text-align: center; font-size: 8pt; color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th  { background-color: #1a5276; color: #fff; border: 1px solid #333;
              padding: 6px 5px; text-align: left; font-size: 8pt; }
        td  { border: 1px solid #ccc; padding: 5px; font-size: 8pt; }
        tr:nth-child(even) td { background-color: #f5f5f5; }
        .total { margin-top: 12px; font-weight: bold; font-size: 9pt; }
        .empty { text-align: center; padding: 30px; color: #666; font-style: italic; }
    </style>
    <h1>' . htmlspecialchars($associationName) . '</h1>
    <h2>' . htmlspecialchars($listTitle) . '</h2>
    <p class="gen">Data di generazione: ' . date('d/m/Y H:i') . '</p>';

    if (empty($radios)) {
        $html .= '<p class="empty">Nessuna radio trovata.</p>';
    } else {
        if ($type === 'assigned') {
            $html .= '
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Identificativo</th>
                        <th>Tipo</th>
                        <th>Marca</th>
                        <th>Modello</th>
                        <th>Seriale</th>
                        <th>Assegnatario</th>
                        <th>Telefono</th>
                        <th>Data/Ora Assegnazione</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($radios as $r) {
                $assignmentDate = !empty($r['assignment_date'])
                    ? date('d/m/Y H:i', strtotime($r['assignment_date']))
                    : '-';
                $html .= '<tr>
                    <td>' . htmlspecialchars($r['name'] ?? '') . '</td>
                    <td>' . htmlspecialchars($r['identifier'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($r['device_type'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($r['brand'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($r['model'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($r['serial_number'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($r['assignee_name'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($r['assignee_phone'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($assignmentDate) . '</td>
                </tr>';
            }
        } else {
            $html .= '
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Identificativo</th>
                        <th>Tipo</th>
                        <th>Marca</th>
                        <th>Modello</th>
                        <th>Seriale</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($radios as $r) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($r['name'] ?? '') . '</td>
                    <td>' . htmlspecialchars($r['identifier'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($r['device_type'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($r['brand'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($r['model'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($r['serial_number'] ?? '-') . '</td>
                </tr>';
            }
        }

        $html .= '</tbody></table>';
        $html .= '<p class="total">Totale: ' . count($radios) . ' radio</p>';
    }

    $mpdfConfig = [
        'margin_top'    => 15,
        'margin_bottom' => 15,
        'margin_left'   => 10,
        'margin_right'  => 10,
        'orientation'   => $orientation,
    ];

    $pdfGenerator = new PdfGenerator($config);
    $pdfGenerator->generate($html, $filename, 'D', $mpdfConfig);

} catch (\Exception $e) {
    error_log('Errore export PDF radio: ' . $e->getMessage());
    http_response_code(500);
    die('Errore durante la generazione del PDF: ' . htmlspecialchars($e->getMessage()));
}
