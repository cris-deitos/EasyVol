<?php
/**
 * Warehouse API - AJAX operations
 * 
 * Gestisce operazioni AJAX per magazzino, movimenti e DPI
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\WarehouseController;
use EasyVol\Middleware\CsrfProtection;

header('Content-Type: application/json');

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new WarehouseController($db, $config);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add_movement':
            // Verifica permessi
            if (!$app->checkPermission('warehouse', 'edit')) {
                throw new Exception('Permessi insufficienti');
            }
            
            // Verifica CSRF
            if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF non valido');
            }
            
            $itemId = intval($_POST['item_id'] ?? 0);
            $data = [
                'movement_type' => $_POST['movement_type'] ?? '',
                'quantity' => intval($_POST['quantity'] ?? 0),
                'member_id' => !empty($_POST['member_id']) ? intval($_POST['member_id']) : null,
                'destination' => trim($_POST['destination'] ?? ''),
                'notes' => trim($_POST['notes'] ?? '')
            ];
            
            $movementId = $controller->addMovement($itemId, $data, $app->getUserId());
            
            if ($movementId) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Movimento registrato con successo',
                    'movement_id' => $movementId
                ]);
            } else {
                throw new Exception('Errore durante la registrazione del movimento');
            }
            break;
            
        case 'assign_dpi':
            // Verifica permessi
            if (!$app->checkPermission('warehouse', 'edit')) {
                throw new Exception('Permessi insufficienti');
            }
            
            // Verifica CSRF
            if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF non valido');
            }
            
            $itemId = intval($_POST['item_id'] ?? 0);
            $memberId = intval($_POST['member_id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 1);
            $assignmentDate = $_POST['assignment_date'] ?? date('Y-m-d');
            $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
            $notes = trim($_POST['notes'] ?? '');
            
            // Begin transaction to ensure data consistency
            $db->beginTransaction();
            
            try {
                // Insert DPI assignment
                $sql = "INSERT INTO dpi_assignments 
                        (item_id, member_id, quantity, assignment_date, assigned_date, expiry_date, status, notes, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 'assegnato', ?, NOW())";
                
                $db->execute($sql, [$itemId, $memberId, $quantity, $assignmentDate, $assignmentDate, $expiryDate, $notes]);
                $assignmentId = $db->lastInsertId();
                
                // Register movement for DPI assignment
                // Pass false to useTransaction parameter since we're already in a transaction
                $movementData = [
                    'movement_type' => 'assegnazione',
                    'quantity' => $quantity,
                    'member_id' => $memberId,
                    'destination' => null,
                    'notes' => 'Assegnazione DPI'
                ];
                
                $movementId = $controller->addMovement($itemId, $movementData, $app->getUserId(), false);
                
                if (!$movementId) {
                    throw new Exception('Errore nella creazione del movimento');
                }
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'DPI assegnato con successo',
                    'assignment_id' => $assignmentId
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'get_members':
            // Get members list for selection
            $search = $_GET['search'] ?? '';
            $sql = "SELECT id, first_name, last_name, registration_number 
                    FROM members 
                    WHERE (first_name LIKE ? OR last_name LIKE ? OR registration_number LIKE ?)
                    AND member_status = 'attivo'
                    ORDER BY last_name, first_name
                    LIMIT 50";
            
            $searchTerm = '%' . $search . '%';
            $members = $db->fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm]);
            
            echo json_encode([
                'success' => true,
                'members' => $members
            ]);
            break;
            
        case 'return_dpi':
            // Verifica permessi
            if (!$app->checkPermission('warehouse', 'edit')) {
                throw new Exception('Permessi insufficienti');
            }
            
            // Verifica CSRF
            if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF non valido');
            }
            
            $assignmentId = intval($_POST['assignment_id'] ?? 0);
            
            if ($assignmentId <= 0) {
                throw new Exception('ID assegnazione non valido');
            }
            
            // Get assignment details
            $sql = "SELECT * FROM dpi_assignments WHERE id = ?";
            $assignment = $db->fetchOne($sql, [$assignmentId]);
            
            if (!$assignment) {
                throw new Exception('Assegnazione DPI non trovata');
            }
            
            if ($assignment['status'] === 'restituito') {
                throw new Exception('Il DPI è già stato restituito');
            }
            
            // Begin transaction to ensure data consistency
            $db->beginTransaction();
            
            try {
                // Update DPI assignment status
                // Note: return_date is DATE type, exact timestamp is in warehouse_movements.created_at
                $sql = "UPDATE dpi_assignments 
                        SET status = 'restituito', return_date = CURDATE() 
                        WHERE id = ?";
                $db->execute($sql, [$assignmentId]);
                
                // Register movement for DPI return
                // Pass false to useTransaction parameter since we're already in a transaction
                $movementData = [
                    'movement_type' => 'restituzione',
                    'quantity' => $assignment['quantity'],
                    'member_id' => $assignment['member_id'],
                    'destination' => null,
                    'notes' => 'Restituzione DPI'
                ];
                
                $movementId = $controller->addMovement($assignment['item_id'], $movementData, $app->getUserId(), false);
                
                if (!$movementId) {
                    throw new Exception('Errore nella creazione del movimento');
                }
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'DPI restituito con successo'
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
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
