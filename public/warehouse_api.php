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
            
            // Insert DPI assignment
            $sql = "INSERT INTO dpi_assignments 
                    (item_id, member_id, quantity, assigned_date, expiry_date, assignment_date, status, notes, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'assegnato', ?, NOW())";
            
            $db->execute($sql, [$itemId, $memberId, $quantity, $assignmentDate, $expiryDate, $assignmentDate, $notes]);
            $assignmentId = $db->lastInsertId();
            
            // Register movement for DPI assignment
            $movementData = [
                'movement_type' => 'assegnazione',
                'quantity' => $quantity,
                'member_id' => $memberId,
                'destination' => null,
                'notes' => 'Assegnazione DPI'
            ];
            
            $controller->addMovement($itemId, $movementData, $app->getUserId());
            
            echo json_encode([
                'success' => true,
                'message' => 'DPI assegnato con successo',
                'assignment_id' => $assignmentId
            ]);
            break;
            
        case 'get_members':
            // Get members list for selection
            $search = $_GET['search'] ?? '';
            $sql = "SELECT id, first_name, last_name, registration_number 
                    FROM members 
                    WHERE (first_name LIKE ? OR last_name LIKE ? OR registration_number LIKE ?)
                    AND status = 'attivo'
                    ORDER BY last_name, first_name
                    LIMIT 50";
            
            $searchTerm = '%' . $search . '%';
            $members = $db->fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm]);
            
            echo json_encode([
                'success' => true,
                'members' => $members
            ]);
            break;
            
        case 'generate_qr':
            // Verifica permessi
            if (!$app->checkPermission('warehouse', 'edit')) {
                throw new Exception('Permessi insufficienti');
            }
            
            $itemId = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            $item = $db->fetchOne("SELECT * FROM warehouse_items WHERE id = ?", [$itemId]);
            
            if (!$item) {
                throw new Exception('Articolo non trovato');
            }
            
            // Generate QR code
            $uploadsPath = $config['uploads']['path'] ?? (__DIR__ . '/../uploads');
            $qrDirectory = $uploadsPath . '/qrcodes';
            
            if (!is_dir($qrDirectory)) {
                mkdir($qrDirectory, 0755, true);
            }
            
            $filename = $qrDirectory . '/item_' . $itemId . '.png';
            $itemCode = $item['code'] ?? $item['name'];
            
            $qrPath = \EasyVol\Utils\QrCodeGenerator::generateForWarehouseItem($itemId, $itemCode, $filename);
            
            // Update database
            $sql = "UPDATE warehouse_items SET qr_code = ? WHERE id = ?";
            $db->execute($sql, [$qrPath, $itemId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'QR Code generato con successo',
                'path' => $qrPath
            ]);
            break;
            
        case 'generate_barcode':
            // Verifica permessi
            if (!$app->checkPermission('warehouse', 'edit')) {
                throw new Exception('Permessi insufficienti');
            }
            
            $itemId = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            $item = $db->fetchOne("SELECT * FROM warehouse_items WHERE id = ?", [$itemId]);
            
            if (!$item) {
                throw new Exception('Articolo non trovato');
            }
            
            // Generate barcode
            $uploadsPath = $config['uploads']['path'] ?? (__DIR__ . '/../uploads');
            $barcodeDirectory = $uploadsPath . '/barcodes';
            
            if (!is_dir($barcodeDirectory)) {
                mkdir($barcodeDirectory, 0755, true);
            }
            
            $filename = $barcodeDirectory . '/item_' . $itemId . '.png';
            $itemCode = $item['code'] ?? ('ITEM' . str_pad($itemId, 6, '0', STR_PAD_LEFT));
            
            $barcodePath = \EasyVol\Utils\BarcodeGenerator::generateForWarehouseItem($itemId, $itemCode, $filename);
            
            // Update database
            $sql = "UPDATE warehouse_items SET barcode = ? WHERE id = ?";
            $db->execute($sql, [$barcodePath, $itemId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Barcode generato con successo',
                'path' => $barcodePath
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
