<?php
/**
 * Vehicle Checklist API
 * 
 * AJAX endpoint for managing vehicle checklists
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

header('Content-Type: application/json');

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

$db = $app->getDb();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            // Get checklists for a vehicle
            if (!$app->checkPermission('vehicles', 'view')) {
                throw new Exception('Accesso negato');
            }
            
            $vehicleId = intval($_GET['vehicle_id'] ?? 0);
            if ($vehicleId <= 0) {
                throw new Exception('ID veicolo non valido');
            }
            
            $checklists = $db->fetchAll(
                "SELECT * FROM vehicle_checklists 
                WHERE vehicle_id = ? 
                ORDER BY display_order, id",
                [$vehicleId]
            );
            
            echo json_encode(['success' => true, 'checklists' => $checklists]);
            break;
            
        case 'create':
            // Create a new checklist item
            if (!$app->checkPermission('vehicles', 'edit')) {
                throw new Exception('Accesso negato');
            }
            
            $vehicleId = intval($_POST['vehicle_id'] ?? 0);
            if ($vehicleId <= 0) {
                throw new Exception('ID veicolo non valido');
            }
            
            $itemName = trim($_POST['item_name'] ?? '');
            if (empty($itemName)) {
                throw new Exception('Nome elemento obbligatorio');
            }
            
            $itemType = $_POST['item_type'] ?? 'boolean';
            if (!in_array($itemType, ['boolean', 'numeric', 'text'])) {
                throw new Exception('Tipo elemento non valido');
            }
            
            $checkTiming = $_POST['check_timing'] ?? 'both';
            if (!in_array($checkTiming, ['departure', 'return', 'both'])) {
                throw new Exception('Timing non valido');
            }
            
            $isRequired = isset($_POST['is_required']) ? 1 : 0;
            $displayOrder = intval($_POST['display_order'] ?? 0);
            
            $db->execute(
                "INSERT INTO vehicle_checklists (vehicle_id, item_name, item_type, check_timing, is_required, display_order) 
                VALUES (?, ?, ?, ?, ?, ?)",
                [$vehicleId, $itemName, $itemType, $checkTiming, $isRequired, $displayOrder]
            );
            
            $newId = $db->lastInsertId();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Elemento aggiunto con successo',
                'id' => $newId
            ]);
            break;
            
        case 'update':
            // Update an existing checklist item
            if (!$app->checkPermission('vehicles', 'edit')) {
                throw new Exception('Accesso negato');
            }
            
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID elemento non valido');
            }
            
            $itemName = trim($_POST['item_name'] ?? '');
            if (empty($itemName)) {
                throw new Exception('Nome elemento obbligatorio');
            }
            
            $itemType = $_POST['item_type'] ?? 'boolean';
            if (!in_array($itemType, ['boolean', 'numeric', 'text'])) {
                throw new Exception('Tipo elemento non valido');
            }
            
            $checkTiming = $_POST['check_timing'] ?? 'both';
            if (!in_array($checkTiming, ['departure', 'return', 'both'])) {
                throw new Exception('Timing non valido');
            }
            
            $isRequired = isset($_POST['is_required']) ? 1 : 0;
            $displayOrder = intval($_POST['display_order'] ?? 0);
            
            $db->execute(
                "UPDATE vehicle_checklists 
                SET item_name = ?, item_type = ?, check_timing = ?, is_required = ?, display_order = ? 
                WHERE id = ?",
                [$itemName, $itemType, $checkTiming, $isRequired, $displayOrder, $id]
            );
            
            echo json_encode([
                'success' => true, 
                'message' => 'Elemento aggiornato con successo'
            ]);
            break;
            
        case 'delete':
            // Delete a checklist item
            if (!$app->checkPermission('vehicles', 'edit')) {
                throw new Exception('Accesso negato');
            }
            
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID elemento non valido');
            }
            
            $db->execute("DELETE FROM vehicle_checklists WHERE id = ?", [$id]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Elemento eliminato con successo'
            ]);
            break;
            
        case 'reorder':
            // Reorder checklist items
            if (!$app->checkPermission('vehicles', 'edit')) {
                throw new Exception('Accesso negato');
            }
            
            $items = json_decode($_POST['items'] ?? '[]', true);
            if (!is_array($items)) {
                throw new Exception('Dati non validi');
            }
            
            foreach ($items as $index => $id) {
                $db->execute(
                    "UPDATE vehicle_checklists SET display_order = ? WHERE id = ?",
                    [$index, intval($id)]
                );
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Ordine aggiornato con successo'
            ]);
            break;
            
        default:
            throw new Exception('Azione non valida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
