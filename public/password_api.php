<?php
/**
 * Password Management API
 * Handles AJAX requests for password operations
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\PasswordController;
use EasyVol\Utils\AutoLogger;

header('Content-Type: application/json');

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

if (!$app->checkPermission('password_management', 'view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accesso negato']);
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new PasswordController($db, $config);
$user = $app->getCurrentUser();

$action = $_GET['action'] ?? '';
$passwordId = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    switch ($action) {
        case 'reveal':
            // Get decrypted password
            if ($passwordId <= 0) {
                throw new Exception('ID password non valido');
            }
            
            $password = $controller->getDecryptedPassword($passwordId, $user['id']);
            
            if ($password === null) {
                throw new Exception('Password non trovata o accesso negato');
            }
            
            // Log password view
            $logSql = "INSERT INTO activity_logs (user_id, action, module, record_id, description, ip_address, user_agent)
                       VALUES (?, 'password_revealed', 'password_management', ?, 'Password visualizzata nella tabella', ?, ?)";
            $db->execute($logSql, [
                $user['id'],
                $passwordId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            echo json_encode([
                'success' => true,
                'password' => $password
            ]);
            break;
            
        case 'delete':
            // Delete password
            if ($passwordId <= 0) {
                throw new Exception('ID password non valido');
            }
            
            if (!$app->checkPermission('password_management', 'delete')) {
                throw new Exception('Non hai i permessi per eliminare password');
            }
            
            $result = $controller->delete($passwordId, $user['id']);
            
            if (!$result) {
                throw new Exception('Impossibile eliminare la password. Solo il creatore può eliminarla.');
            }
            
            // Log deletion
            $logSql = "INSERT INTO activity_logs (user_id, action, module, record_id, description, ip_address, user_agent)
                       VALUES (?, 'password_deleted', 'password_management', ?, 'Password eliminata', ?, ?)";
            $db->execute($logSql, [
                $user['id'],
                $passwordId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Password eliminata con successo'
            ]);
            break;
            
        default:
            throw new Exception('Azione non valida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
