<?php
/**
 * Public PDF Download for Membership Applications
 * 
 * Allows public access to application PDFs via secure token.
 * No authentication required - token-based access.
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

$app = App::getInstance();
$db = $app->getDb();

// Get token from query parameter
$token = $_GET['token'] ?? '';

// Validate token format first (must be 64 hex characters)
if (empty($token) || strlen($token) !== 64 || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    http_response_code(400);
    die('Token non valido');
}

// Token is already validated as hexadecimal, no need for additional sanitization

// Find application by token
try {
    $sql = "SELECT * FROM member_applications 
            WHERE pdf_download_token = ? 
            AND (pdf_token_expires_at IS NULL OR pdf_token_expires_at > NOW())";
    
    $application = $db->fetchOne($sql, [$token]);
    
    if (!$application) {
        http_response_code(404);
        die('Link non valido o scaduto. Richiedi un nuovo link via email.');
    }
    
    $pdfPath = $application['pdf_file'] ?? '';
    
    if (empty($pdfPath)) {
        http_response_code(404);
        die('PDF non disponibile. Contatta l\'associazione.');
    }
    
    // Build full path and validate
    $basePath = realpath(__DIR__ . '/../');
    $sanitizedPdfPath = ltrim($pdfPath, '/');
    $fullPath = __DIR__ . '/../' . $sanitizedPdfPath;
    $resolvedPath = realpath($fullPath);
    
    // Security check: ensure the file exists and is within the expected directory
    if ($resolvedPath === false) {
        http_response_code(404);
        die('File non trovato. Il PDF potrebbe essere stato rigenerato. Contatta l\'associazione.');
    }
    
    if ($basePath === false || strpos($resolvedPath, $basePath . DIRECTORY_SEPARATOR) !== 0) {
        http_response_code(403);
        die('Accesso al file non consentito');
    }
    
    // Detect MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $resolvedPath);
    finfo_close($finfo);
    
    // Prepare filename - use application code for clarity
    $appCode = preg_replace('/[^A-Z0-9_-]/i', '', $application['application_code'] ?? 'DOMANDA');
    $filename = "domanda_iscrizione_{$appCode}.pdf";
    $encodedFilename = rawurlencode($filename);
    
    // Check if download or inline view
    $download = isset($_GET['download']) && $_GET['download'] === '1';
    
    // Serve file
    header('Content-Type: ' . $mimeType);
    if ($download) {
        header("Content-Disposition: attachment; filename*=UTF-8''" . $encodedFilename);
    } else {
        header("Content-Disposition: inline; filename*=UTF-8''" . $encodedFilename);
    }
    header('Content-Length: ' . filesize($resolvedPath));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    
    readfile($resolvedPath);
    exit;
    
} catch (\Exception $e) {
    error_log("PDF download error: " . $e->getMessage());
    http_response_code(500);
    die('Errore durante il download del PDF');
}
