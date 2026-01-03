<?php
/**
 * Province Excel Export
 * 
 * Exports volunteer fiscal codes grouped by day for provincial civil protection
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Get token from URL
$token = $_GET['token'] ?? '';

// Validate token format: must be 64 hexadecimal characters
if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
    die('Token non valido');
}

// Initialize database
$config = require __DIR__ . '/../config/config.php';
$db = new Database(
    $config['database']['host'],
    $config['database']['port'] ?? 3306,
    $config['database']['name'],
    $config['database']['user'],
    $config['database']['password']
);

// Check authentication in session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['province_token_' . $token])) {
    die('Accesso non autorizzato. Effettuare prima l\'autenticazione.');
}

// Load event by token
$event = $db->fetchOne(
    "SELECT * FROM events WHERE province_access_token = ?",
    [$token]
);

if (!$event) {
    die('Evento non trovato');
}

// Get all interventions with members
$interventions = $db->fetchAll(
    "SELECT i.*, im.member_id, m.tax_code, im.hours_worked, im.role
     FROM interventions i
     LEFT JOIN intervention_members im ON i.id = im.intervention_id
     LEFT JOIN members m ON im.member_id = m.id
     WHERE i.event_id = ?
     ORDER BY i.start_time, m.tax_code",
    [$event['id']]
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
    
    // Use tax_code as key to avoid duplicates per day
    $taxCode = $intervention['tax_code'];
    if (!isset($dataByDate[$date]['members'][$taxCode])) {
        $dataByDate[$date]['members'][$taxCode] = [
            'tax_code' => $taxCode,
            'total_hours' => 0,
            'interventions' => []
        ];
    }
    
    $dataByDate[$date]['members'][$taxCode]['total_hours'] += floatval($intervention['hours_worked'] ?? 0);
    $dataByDate[$date]['members'][$taxCode]['interventions'][] = [
        'title' => $intervention['title'],
        'hours' => $intervention['hours_worked'] ?? 0,
        'role' => $intervention['role'] ?? ''
    ];
}

// Sort dates
ksort($dataByDate);

// Create Excel file
$spreadsheet = new Spreadsheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator('EasyVol')
    ->setTitle('Volontari Evento - ' . $event['title'])
    ->setSubject('Codici Fiscali Volontari per Provincia')
    ->setDescription('Elenco codici fiscali volontari suddivisi per giorno')
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
    $sheet->mergeCells('A1:D1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'Evento: ' . $event['title']);
    $sheet->mergeCells('A2:D2');
    $sheet->getStyle('A2')->getFont()->setSize(11);
    
    $sheet->setCellValue('A3', 'Tipo: ' . ucfirst($event['event_type']));
    $sheet->mergeCells('A3:D3');
    
    // Column headers
    $sheet->setCellValue('A5', 'N°');
    $sheet->setCellValue('B5', 'CODICE FISCALE');
    $sheet->setCellValue('C5', 'ORE TOTALI');
    $sheet->setCellValue('D5', 'INTERVENTI');
    
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
    $sheet->getStyle('A5:D5')->applyFromArray($headerStyle);
    
    // Data rows
    $row = 6;
    $counter = 1;
    foreach ($dayData['members'] as $member) {
        $sheet->setCellValue('A' . $row, $counter);
        $sheet->setCellValue('B' . $row, $member['tax_code']);
        $sheet->setCellValue('C' . $row, number_format($member['total_hours'], 2));
        
        // Build interventions summary
        $interventionsSummary = [];
        foreach ($member['interventions'] as $int) {
            $interventionsSummary[] = $int['title'] . ' (' . $int['hours'] . 'h' . 
                (!empty($int['role']) ? ', ' . $int['role'] : '') . ')';
        }
        $sheet->setCellValue('D' . $row, implode('; ', $interventionsSummary));
        
        // Style data rows
        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ];
        $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray($dataStyle);
        
        // Alternate row colors
        if ($counter % 2 == 0) {
            $sheet->getStyle('A' . $row . ':D' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F2F2F2');
        }
        
        $row++;
        $counter++;
    }
    
    // Summary row
    $totalVolunteers = count($dayData['members']);
    $totalHours = array_sum(array_column($dayData['members'], 'total_hours'));
    
    $sheet->setCellValue('A' . $row, 'TOTALI:');
    $sheet->mergeCells('A' . $row . ':B' . $row);
    $sheet->setCellValue('C' . $row, number_format($totalHours, 2));
    $sheet->setCellValue('D' . $row, $totalVolunteers . ' volontari');
    
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
    $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray($summaryStyle);
    
    // Auto-size columns
    $sheet->getColumnDimension('A')->setWidth(5);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(12);
    $sheet->getColumnDimension('D')->setWidth(60);
    
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
$filename = 'Volontari_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $event['title']) . '_' . date('Ymd') . '.xlsx';

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
