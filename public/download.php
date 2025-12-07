<?php
/**
 * Universal Secure File Download Handler
 * 
 * Centralized download handler that validates authentication and permissions
 * before serving files from the uploads directory.
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

$app = App::getInstance();

// Require authentication
if (!$app->isLoggedIn()) {
    http_response_code(403);
    die('Accesso negato - Autenticazione richiesta');
}

$db = $app->getDb();
$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($type) || $id <= 0) {
    http_response_code(400);
    die('Parametri non validi');
}

// Load file info and check permissions based on type
$filePath = null;
$canAccess = false;

switch ($type) {
    case 'member_attachment':
        $sql = "SELECT ma.*, m.user_id 
                FROM member_attachments ma 
                JOIN members m ON ma.member_id = m.id 
                WHERE ma.id = ?";
        $file = $db->fetchOne($sql, [$id]);
        
        if ($file) {
            $filePath = $file['file_path'];
            
            // Admin or owner can access
            if ($app->checkPermission('members', 'view')) {
                $canAccess = true;
            } else {
                // Check if user owns this member record
                $userId = $app->getUserId();
                if ($file['user_id'] == $userId) {
                    $canAccess = true;
                }
            }
        }
        break;
        
    case 'vehicle_attachment':
        $sql = "SELECT * FROM vehicle_documents WHERE id = ?";
        $file = $db->fetchOne($sql, [$id]);
        
        if ($file) {
            $filePath = $file['file_path'];
            $canAccess = $app->checkPermission('vehicles', 'view');
        }
        break;
        
    case 'fee_receipt':
        $sql = "SELECT fpr.*, m.user_id 
                FROM fee_payment_requests fpr 
                JOIN members m ON fpr.member_id = m.id 
                WHERE fpr.id = ?";
        $file = $db->fetchOne($sql, [$id]);
        
        if ($file) {
            $filePath = $file['receipt_file'];
            
            // Admin or owner can access
            if ($app->checkPermission('fees', 'view')) {
                $canAccess = true;
            } else {
                $userId = $app->getUserId();
                if ($file['user_id'] == $userId) {
                    $canAccess = true;
                }
            }
        }
        break;
        
    case 'document':
        $sql = "SELECT * FROM documents WHERE id = ?";
        $file = $db->fetchOne($sql, [$id]);
        
        if ($file) {
            $filePath = $file['file_path'];
            $canAccess = $app->checkPermission('documents', 'view');
        }
        break;
        
    case 'application_pdf':
        $sql = "SELECT ma.*, m.user_id 
                FROM member_applications ma 
                LEFT JOIN members m ON ma.member_id = m.id 
                WHERE ma.id = ?";
        $file = $db->fetchOne($sql, [$id]);
        
        if ($file) {
            $filePath = $file['pdf_file'];
            
            // Admin can access all applications
            if ($app->checkPermission('settings', 'view')) {
                $canAccess = true;
            } else {
                // If application is approved and linked to a member, check if user owns that member
                $userId = $app->getUserId();
                if ($file['member_id'] && $file['user_id'] == $userId) {
                    $canAccess = true;
                }
            }
        }
        break;
        
    case 'meeting_document':
        $sql = "SELECT * FROM meeting_attachments WHERE id = ?";
        $file = $db->fetchOne($sql, [$id]);
        
        if ($file) {
            $filePath = $file['file_path'];
            $canAccess = $app->checkPermission('meetings', 'view');
        }
        break;
        
    case 'member_photo':
        $sql = "SELECT photo_path, user_id FROM members WHERE id = ?";
        $file = $db->fetchOne($sql, [$id]);
        
        if ($file && !empty($file['photo_path'])) {
            $filePath = $file['photo_path'];
            
            // Photos can be viewed by anyone with member access
            if ($app->checkPermission('members', 'view')) {
                $canAccess = true;
            } else {
                $userId = $app->getUserId();
                if ($file['user_id'] == $userId) {
                    $canAccess = true;
                }
            }
        }
        break;
        
    case 'vehicle_photo':
        $sql = "SELECT photo FROM vehicles WHERE id = ?";
        $file = $db->fetchOne($sql, [$id]);
        
        if ($file && !empty($file['photo'])) {
            $filePath = $file['photo'];
            $canAccess = $app->checkPermission('vehicles', 'view');
        }
        break;
        
    case 'junior_member_photo':
        $sql = "SELECT photo_path, user_id FROM junior_members WHERE id = ?";
        $file = $db->fetchOne($sql, [$id]);
        
        if ($file && !empty($file['photo_path'])) {
            $filePath = $file['photo_path'];
            
            // Photos can be viewed by anyone with member access
            if ($app->checkPermission('members', 'view')) {
                $canAccess = true;
            } else {
                $userId = $app->getUserId();
                if ($file['user_id'] == $userId) {
                    $canAccess = true;
                }
            }
        }
        break;
        
    case 'junior_member_attachment':
        $sql = "SELECT jma.*, jm.user_id 
                FROM junior_member_attachments jma 
                JOIN junior_members jm ON jma.junior_member_id = jm.id 
                WHERE jma.id = ?";
        $file = $db->fetchOne($sql, [$id]);
        
        if ($file) {
            $filePath = $file['file_path'];
            
            // Admin or owner can access
            if ($app->checkPermission('members', 'view')) {
                $canAccess = true;
            } else {
                // Check if user owns this member record
                $userId = $app->getUserId();
                if ($file['user_id'] == $userId) {
                    $canAccess = true;
                }
            }
        }
        break;
        
    default:
        http_response_code(400);
        die('Tipo file non supportato');
}

// Check if file was found and user has access
if (!$file) {
    http_response_code(404);
    die('File non trovato');
}

if (!$canAccess) {
    http_response_code(403);
    die('Non hai i permessi per accedere a questo file');
}

if (empty($filePath)) {
    http_response_code(404);
    die('Percorso file non disponibile');
}

// Build full path and validate it's within uploads directory
$baseDir = realpath(__DIR__ . '/../uploads');
$sanitizedPath = ltrim($filePath, '/');
if (empty($sanitizedPath)) {
    http_response_code(404);
    die('Percorso file non valido');
}
$fullPath = realpath(__DIR__ . '/../' . $sanitizedPath);

// Security check: ensure the file is within the uploads directory
if ($fullPath === false || strpos($fullPath, $baseDir) !== 0) {
    http_response_code(403);
    die('Accesso al percorso non consentito');
}

if (!file_exists($fullPath)) {
    http_response_code(404);
    die('File non trovato sul server');
}

// Detect MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $fullPath);
finfo_close($finfo);

// Prepare filename for header (RFC 6266)
$filename = basename($fullPath);
$encodedFilename = rawurlencode($filename);

// Serve file
header('Content-Type: ' . $mimeType);
header("Content-Disposition: inline; filename*=UTF-8''" . $encodedFilename);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($fullPath);
exit;
