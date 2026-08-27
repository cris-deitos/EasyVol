<?php
/**
 * Signature Extraction Diagnostics
 *
 * Shows OpenSSL extension availability and, for a given document,
 * the output of PDFSignatureExtractor::extractSignatures() in JSON.
 *
 * SECURITY: protected by login + admin/superadmin permission check.
 * Remove or restrict this file on production once debugging is done.
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\PDFSignatureExtractor;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Restrict to admin/superadmin only
$role = $app->getUserRole();
if (!in_array($role, ['admin', 'superadmin'], true)) {
    http_response_code(403);
    die('Accesso negato: solo gli amministratori possono usare questa pagina di diagnostica.');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== EasyVol Signature Diagnostics ===\n\n";

// OpenSSL extension availability
echo "--- PHP OpenSSL Extension ---\n";
echo "ext openssl:           " . (extension_loaded('openssl') ? 'SI' : 'NO') . "\n";
echo "openssl_x509_parse:    " . (function_exists('openssl_x509_parse')  ? 'SI' : 'NO') . "\n";
echo "openssl_pkcs7_read:    " . (function_exists('openssl_pkcs7_read')   ? 'SI' : 'NO') . "\n";
echo "openssl_pkcs7_verify:  " . (function_exists('openssl_pkcs7_verify') ? 'SI' : 'NO') . "\n";
if (defined('OPENSSL_VERSION_TEXT')) {
    echo "versione:              " . OPENSSL_VERSION_TEXT . "\n";
}

// Shell execution availability (for information only – not used by extractor)
echo "\n--- Shell Execution (disabled on shared hosting) ---\n";
$disabled = array_map('trim', explode(',', ini_get('disable_functions')));
$shellFns = ['exec', 'shell_exec', 'proc_open', 'popen', 'system', 'passthru'];
foreach ($shellFns as $fn) {
    echo sprintf("%-20s %s\n", $fn . ':', in_array($fn, $disabled) ? 'DISABILITATA' : 'disponibile');
}

// Optional: extract signatures from a specific file passed as ?file=<path>
// Restrict to the application's upload directory to prevent arbitrary file reads.
$filePath = null;
if (isset($_GET['file'])) {
    $requestedPath = trim($_GET['file']);
    $realPath = realpath($requestedPath);
    // Determine the uploads base directory (two levels up from public/)
    $uploadsBase = realpath(__DIR__ . '/../uploads');
    if ($uploadsBase === false) {
        // Fallback: accept nothing if uploads dir is not found
        $realPath = false;
    }
    if ($realPath !== false && $uploadsBase !== false && strncmp($realPath, $uploadsBase, strlen($uploadsBase)) === 0) {
        $filePath = $realPath;
    } else {
        echo "\n--- Signature Extraction ---\n";
        echo "ERRORE: percorso non consentito. Il file deve trovarsi nella cartella uploads dell'applicazione.\n";
        echo "\n=== Fine diagnostica ===\n";
        exit;
    }
}

if ($filePath !== null) {
    echo "\n--- Signature Extraction ---\n";
    echo "File: " . htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8') . "\n";

    if (!file_exists($filePath)) {
        echo "ERRORE: file non trovato.\n";
    } elseif (!is_readable($filePath)) {
        echo "ERRORE: file non leggibile.\n";
    } else {
        $result = PDFSignatureExtractor::extractSignatures($filePath);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "\n--- Signature Extraction ---\n";
    echo "Passa ?file=/percorso/assoluto/al/documento.pdf per estrarne le firme.\n";
}

echo "\n=== Fine diagnostica ===\n";
