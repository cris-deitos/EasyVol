<?php
/**
 * Entity Search AJAX Handler
 * 
 * Provides autocomplete search for members and junior members
 * by registration number, name, surname, or tax code
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

header('Content-Type: application/json');

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

$db = $app->getDb();
$query = trim($_GET['q'] ?? '');
$entityType = $_GET['type'] ?? 'member'; // member or junior_member

if (empty($query)) {
    echo json_encode([]);
    exit;
}

try {
    if ($entityType === 'member') {
        // Search members
        $sql = "SELECT id, first_name, last_name, registration_number, badge_number, tax_code 
                FROM members 
                WHERE member_status = 'attivo' 
                AND (
                    badge_number LIKE ? 
                    OR registration_number LIKE ? 
                    OR first_name LIKE ? 
                    OR last_name LIKE ?
                    OR tax_code LIKE ?
                    OR CONCAT(first_name, ' ', last_name) LIKE ?
                    OR CONCAT(last_name, ' ', first_name) LIKE ?
                )
                ORDER BY 
                    CASE 
                        WHEN badge_number LIKE ? THEN 1
                        WHEN registration_number LIKE ? THEN 2
                        WHEN tax_code LIKE ? THEN 3
                        WHEN last_name LIKE ? THEN 4
                        WHEN first_name LIKE ? THEN 5
                        ELSE 6
                    END,
                    last_name, first_name
                LIMIT 20";
    } else {
        // Search junior members
        $sql = "SELECT id, first_name, last_name, registration_number, tax_code 
                FROM junior_members 
                WHERE member_status = 'attivo' 
                AND (
                    registration_number LIKE ? 
                    OR first_name LIKE ? 
                    OR last_name LIKE ?
                    OR tax_code LIKE ?
                    OR CONCAT(first_name, ' ', last_name) LIKE ?
                    OR CONCAT(last_name, ' ', first_name) LIKE ?
                )
                ORDER BY 
                    CASE 
                        WHEN registration_number LIKE ? THEN 1
                        WHEN tax_code LIKE ? THEN 2
                        WHEN last_name LIKE ? THEN 3
                        WHEN first_name LIKE ? THEN 4
                        ELSE 5
                    END,
                    last_name, first_name
                LIMIT 20";
    }
    
    $searchTerm = '%' . $query . '%';
    $exactStart = $query . '%';
    
    if ($entityType === 'member') {
        $params = [
            $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm,
            $exactStart, $exactStart, $exactStart, $exactStart, $exactStart
        ];
    } else {
        $params = [
            $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm,
            $exactStart, $exactStart, $exactStart, $exactStart
        ];
    }
    
    $entities = $db->fetchAll($sql, $params);
    
    // Format results for autocomplete
    $results = [];
    foreach ($entities as $entity) {
        $displayNumber = $entity['badge_number'] ?? $entity['registration_number'];
        $results[] = [
            'id' => $entity['id'],
            'label' => $entity['last_name'] . ' ' . $entity['first_name'] . ' (Mat. ' . $displayNumber . ')',
            'value' => $entity['last_name'] . ' ' . $entity['first_name'],
            'registration_number' => $displayNumber
        ];
    }
    
    echo json_encode($results);
    
} catch (Exception $e) {
    error_log("Entity search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
