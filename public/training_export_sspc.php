<?php
/**
 * Training SSPC Excel Export
 * 
 * Exports training course participants in a simple format for SSPC (Regione Lombardia)
 * Format: Nome, Cognome, Codice_Fiscale, Email
 * Plain text only - no borders, colors, or formatting
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\TrainingController;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$app = App::getInstance();

// Verify authentication
if (!$app->isLoggedIn()) {
    die('Accesso non autorizzato. Effettuare il login.');
}

// Verify permissions
if (!$app->checkPermission('training', 'view')) {
    die('Accesso negato: permessi insufficienti');
}

// Get course ID from URL
$courseId = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($courseId <= 0) {
    die('ID corso non valido');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new TrainingController($db, $config);

// Load course
$course = $controller->get($courseId);

if (!$course) {
    die('Corso non trovato');
}

// Get participants with email
$participants = $controller->getParticipantsWithEmail($courseId);

if (empty($participants)) {
    die('Nessun partecipante registrato per questo corso');
}

// Create Excel file
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator('EasyVol')
    ->setTitle('Partecipanti Corso SSPC - ' . $course['course_name'])
    ->setSubject('Elenco Partecipanti per SSPC Regione Lombardia')
    ->setDescription('Formato semplice: Nome, Cognome, Codice Fiscale, Email')
    ->setCategory('Export');

// Column headers - simple text only
$sheet->setCellValue('A1', 'Nome');
$sheet->setCellValue('B1', 'Cognome');
$sheet->setCellValue('C1', 'Codice_Fiscale');
$sheet->setCellValue('D1', 'Email');

// Data rows
$row = 2;
foreach ($participants as $participant) {
    $sheet->setCellValue('A' . $row, $participant['Nome'] ?? '');
    $sheet->setCellValue('B' . $row, $participant['Cognome'] ?? '');
    $sheet->setCellValue('C' . $row, $participant['Codice_Fiscale'] ?? '');
    $sheet->setCellValue('D' . $row, $participant['Email'] ?? '');
    $row++;
}

// Auto-size columns for better readability
$sheet->getColumnDimension('A')->setAutoSize(true);
$sheet->getColumnDimension('B')->setAutoSize(true);
$sheet->getColumnDimension('C')->setAutoSize(true);
$sheet->getColumnDimension('D')->setAutoSize(true);

// Generate filename - sanitize course name to prevent path traversal and header injection
$sanitizedCourseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $course['course_name']);
$sanitizedCourseName = substr($sanitizedCourseName, 0, 50); // Limit length
$filename = 'Partecipanti_SSPC_' . $sanitizedCourseName . '_' . date('Ymd') . '.xlsx';

// Properly encode filename for Content-Disposition header (RFC 5987)
$encodedFilename = rawurlencode($filename);

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"{$filename}\"; filename*=UTF-8''{$encodedFilename}");
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
