<?php
/**
 * Event Excel Export - Internal Management
 * 
 * Exports volunteer details (registration number, name, surname, fiscal code) 
 * grouped by day for internal management purposes
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$app = App::getInstance();

// Verify authentication
if (!$app->isLoggedIn()) {
    die('Accesso non autorizzato. Effettuare il login.');
}

// Verify permissions
if (!$app->checkPermission('events', 'view')) {
    die('Accesso negato: permessi insufficienti');
}

// Get event ID from URL
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if ($eventId <= 0) {
    die('ID evento non valido');
}

$db = $app->getDb();

// Load event
$event = $db->fetchOne(
    "SELECT * FROM events WHERE id = ?",
    [$eventId]
);

if (!$event) {
    die('Evento non trovato');
}

// Get all interventions with members - including full member details
$interventions = $db->fetchAll(
    "SELECT i.*, im.member_id, m.registration_number, m.first_name, m.last_name, m.tax_code, im.hours_worked, im.role
     FROM interventions i
     LEFT JOIN intervention_members im ON i.id = im.intervention_id
     LEFT JOIN members m ON im.member_id = m.id
     WHERE i.event_id = ?
     ORDER BY i.start_time, m.last_name, m.first_name",
    [$eventId]
);

// Group data by date
$dataByDate = [];
foreach ($interventions as $intervention) {
    if (empty($intervention['member_id'])) {
        continue; // Skip interventions without members
    }
    
    $date = date('Y-m-d', strtotime($intervention['start_time']));
    $dateLabel = date('d/m/Y', strtotime($intervention['start_time']));
    
    if (!isset($dataByDate[$date])) {
        $dataByDate[$date] = [
            'label' => $dateLabel,
            'members' => []
        ];
    }
    
    // Use member_id as key to avoid duplicates per day
    $memberId = $intervention['member_id'];
    if (!isset($dataByDate[$date]['members'][$memberId])) {
        $dataByDate[$date]['members'][$memberId] = [
            'registration_number' => $intervention['registration_number'] ?? '-',
            'first_name' => $intervention['first_name'],
            'last_name' => $intervention['last_name'],
            'tax_code' => $intervention['tax_code']
        ];
    }
}

// Sort dates
ksort($dataByDate);

// Create Excel file
$spreadsheet = new Spreadsheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator('EasyVol')
    ->setTitle('Volontari Evento - ' . $event['title'])
    ->setSubject('Elenco Volontari per Gestionale Interno')
    ->setDescription('Elenco completo volontari suddivisi per giorno con matricola, nome, cognome e codice fiscale')
    ->setCategory('Report');

$sheetIndex = 0;
foreach ($dataByDate as $date => $dayData) {
    // Create or get sheet
    if ($sheetIndex === 0) {
        $sheet = $spreadsheet->getActiveSheet();
    } else {
        $sheet = $spreadsheet->createSheet($sheetIndex);
    }
    
    $sheet->setTitle(date('d-m-Y', strtotime($date)));
    
    // Header
    $sheet->setCellValue('A1', 'ELENCO VOLONTARI - ' . strtoupper($dayData['label']));
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'Evento: ' . $event['title']);
    $sheet->mergeCells('A2:E2');
    $sheet->getStyle('A2')->getFont()->setSize(11);
    
    $sheet->setCellValue('A3', 'Tipo: ' . ucfirst($event['event_type']));
    $sheet->mergeCells('A3:E3');
    
    // Column headers
    $sheet->setCellValue('A5', 'N°');
    $sheet->setCellValue('B5', 'MATRICOLA');
    $sheet->setCellValue('C5', 'NOME');
    $sheet->setCellValue('D5', 'COGNOME');
    $sheet->setCellValue('E5', 'CODICE FISCALE');
    
    // Style column headers
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4472C4']
        ],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];
    $sheet->getStyle('A5:E5')->applyFromArray($headerStyle);
    
    // Data rows
    $row = 6;
    $counter = 1;
    foreach ($dayData['members'] as $member) {
        $sheet->setCellValue('A' . $row, $counter);
        $sheet->setCellValue('B' . $row, $member['registration_number']);
        $sheet->setCellValue('C' . $row, $member['first_name']);
        $sheet->setCellValue('D' . $row, $member['last_name']);
        $sheet->setCellValue('E' . $row, $member['tax_code']);
        
        // Style data rows
        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ];
        $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($dataStyle);
        
        // Alternate row colors
        if ($counter % 2 == 0) {
            $sheet->getStyle('A' . $row . ':E' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F2F2F2');
        }
        
        $row++;
        $counter++;
    }
    
    // Summary row
    $totalVolunteers = count($dayData['members']);
    
    $sheet->setCellValue('A' . $row, 'TOTALI:');
    $sheet->mergeCells('A' . $row . ':D' . $row);
    $sheet->setCellValue('E' . $row, $totalVolunteers . ' volontari');
    
    $summaryStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E7E6E6']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_MEDIUM,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];
    $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($summaryStyle);
    
    // Auto-size columns
    $sheet->getColumnDimension('A')->setWidth(5);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(20);
    $sheet->getColumnDimension('D')->setWidth(20);
    $sheet->getColumnDimension('E')->setWidth(20);
    
    $sheetIndex++;
}

// If no data, create an empty sheet with message
if (count($dataByDate) === 0) {
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Nessun Dato');
    $sheet->setCellValue('A1', 'Nessun volontario registrato per questo evento');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
}

// Set active sheet to first sheet
$spreadsheet->setActiveSheetIndex(0);

// Generate filename
$filename = 'Volontari_Dettaglio_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $event['title']) . '_' . date('Ymd') . '.xlsx';

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

// Write file to output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
