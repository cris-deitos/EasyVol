<?php
/**
 * Radio Directory Excel Export
 *
 * Exports radio lists as Excel (.xlsx):
 *   type=assigned   – Assigned radios: Nome, Identificativo, Tipo, Marca, Modello, Seriale,
 *                     Assegnatario, Telefono, Data/Ora Assegnazione
 *   type=unassigned – Unassigned radios: Nome, Identificativo, Tipo, Marca, Modello, Seriale
 *   type=all        – All radios: Nome, Identificativo, Tipo, Marca, Modello, Seriale
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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

$db = $app->getDb();

// Retrieve association data
$associationData = $db->fetchOne("SELECT * FROM association LIMIT 1");
$associationName = $associationData['name'] ?? 'Associazione di Volontariato';

try {
    if ($type === 'assigned') {
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
        $listTitle = 'Elenco Radio Assegnate';
        $filename  = 'radio_assegnate_' . date('Ymd') . '.xlsx';
        $headers   = ['Nome', 'Identificativo', 'Tipo', 'Marca', 'Modello', 'Seriale',
                      'Assegnatario', 'Telefono', 'Data/Ora Assegnazione'];
    } elseif ($type === 'unassigned') {
        $radios = $db->fetchAll(
            "SELECT name, identifier, device_type, brand, model, serial_number
             FROM radio_directory
             WHERE status != 'assegnata'
             ORDER BY name"
        );
        $listTitle = 'Elenco Radio Non Assegnate';
        $filename  = 'radio_non_assegnate_' . date('Ymd') . '.xlsx';
        $headers   = ['Nome', 'Identificativo', 'Tipo', 'Marca', 'Modello', 'Seriale'];
    } else {
        $radios = $db->fetchAll(
            "SELECT name, identifier, device_type, brand, model, serial_number
             FROM radio_directory
             ORDER BY name"
        );
        $listTitle = 'Elenco Completo Radio';
        $filename  = 'radio_elenco_completo_' . date('Ymd') . '.xlsx';
        $headers   = ['Nome', 'Identificativo', 'Tipo', 'Marca', 'Modello', 'Seriale'];
    }

    // Log activity
    AutoLogger::logActivity('radio', 'export_excel', null, "Export Excel: $listTitle");

    // ---- Build Spreadsheet ----
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('EasyVol')
        ->setTitle($listTitle . ' - ' . $associationName)
        ->setSubject($listTitle)
        ->setDescription('Generato il ' . date('d/m/Y H:i'))
        ->setCategory('Report Radio');

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(substr($listTitle, 0, 31)); // Excel sheet name max 31 chars

    $colCount = count($headers);
    $lastCol  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

    // --- Row 1: Association name ---
    $sheet->setCellValue('A1', $associationName);
    $sheet->mergeCells('A1:' . $lastCol . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // --- Row 2: List title ---
    $sheet->setCellValue('A2', $listTitle);
    $sheet->mergeCells('A2:' . $lastCol . '2');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // --- Row 3: Generation date ---
    $sheet->setCellValue('A3', 'Data di generazione: ' . date('d/m/Y H:i'));
    $sheet->mergeCells('A3:' . $lastCol . '3');
    $sheet->getStyle('A3')->getFont()->setSize(9)->getColor()->setRGB('666666');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // --- Row 5: Column headers ---
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1A5276'],
        ],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders'   => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => '000000'],
            ],
        ],
    ];

    foreach ($headers as $colIndex => $headerText) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
        $sheet->setCellValue($colLetter . '5', $headerText);
    }
    $sheet->getStyle('A5:' . $lastCol . '5')->applyFromArray($headerStyle);

    // --- Data rows ---
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => 'CCCCCC'],
            ],
        ],
    ];

    $row     = 6;
    $counter = 1;

    foreach ($radios as $r) {
        if ($type === 'assigned') {
            $assignmentDate = !empty($r['assignment_date'])
                ? date('d/m/Y H:i', strtotime($r['assignment_date']))
                : '-';
            $rowData = [
                $r['name']           ?? '',
                $r['identifier']     ?? '-',
                $r['device_type']    ?? '-',
                $r['brand']          ?? '-',
                $r['model']          ?? '-',
                $r['serial_number']  ?? '-',
                $r['assignee_name']  ?? '-',
                $r['assignee_phone'] ?? '-',
                $assignmentDate,
            ];
        } else {
            $rowData = [
                $r['name']          ?? '',
                $r['identifier']    ?? '-',
                $r['device_type']   ?? '-',
                $r['brand']         ?? '-',
                $r['model']         ?? '-',
                $r['serial_number'] ?? '-',
            ];
        }

        foreach ($rowData as $colIndex => $value) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($colLetter . $row, $value);
        }

        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray($dataStyle);

        // Alternate row shading
        if ($counter % 2 === 0) {
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)
                  ->getFill()
                  ->setFillType(Fill::FILL_SOLID)
                  ->getStartColor()->setRGB('F2F2F2');
        }

        $row++;
        $counter++;
    }

    // --- Summary row ---
    $sheet->setCellValue('A' . $row, 'Totale:');
    if ($colCount > 1) {
        $mergeTo = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount - 1);
        $sheet->mergeCells('A' . $row . ':' . $mergeTo . $row);
    }
    $sheet->setCellValue($lastCol . $row, count($radios) . ' radio');

    $summaryStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E7E6E6'],
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_MEDIUM,
                'color'       => ['rgb' => '000000'],
            ],
        ],
    ];
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray($summaryStyle);

    // Auto-size all columns
    foreach (range(1, $colCount) as $colIndex) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }

    $spreadsheet->setActiveSheetIndex(0);

    // Send file to browser
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (\Exception $e) {
    error_log('Errore export Excel radio: ' . $e->getMessage());
    http_response_code(500);
    die('Errore durante la generazione del file Excel: ' . htmlspecialchars($e->getMessage()));
}
