<?php
/**
 * Members Search AJAX Handler
 * 
 * Provides autocomplete search for members by badge number, name, or surname
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

if (empty($query)) {
    echo json_encode([]);
    exit;
}

try {
    // Search by badge number, registration number, first name, or last name
    $sql = "SELECT id, first_name, last_name, registration_number, badge_number 
            FROM members 
            WHERE member_status = 'attivo' 
            AND (
                badge_number LIKE ? 
                OR registration_number LIKE ? 
                OR first_name LIKE ? 
                OR last_name LIKE ?
                OR CONCAT(first_name, ' ', last_name) LIKE ?
                OR CONCAT(last_name, ' ', first_name) LIKE ?
            )
            ORDER BY 
                CASE 
                    WHEN badge_number LIKE ? THEN 1
                    WHEN registration_number LIKE ? THEN 2
                    WHEN last_name LIKE ? THEN 3
                    WHEN first_name LIKE ? THEN 4
                    ELSE 5
                END,
                last_name, first_name
            LIMIT 20";
    
    $searchTerm = '%' . $query . '%';
    $exactStart = $query . '%';
    
    $params = [
        $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm,
        $exactStart, $exactStart, $exactStart, $exactStart
    ];
    
    $members = $db->fetchAll($sql, $params);
    
    // Format results for autocomplete
    $results = [];
    foreach ($members as $member) {
        $displayNumber = $member['badge_number'] ?? $member['registration_number'];
        $results[] = [
            'id' => $member['id'],
            'label' => $member['last_name'] . ' ' . $member['first_name'] . ' (Mat. ' . $displayNumber . ')',
            'value' => $member['last_name'] . ' ' . $member['first_name'],
            'badge_number' => $displayNumber
        ];
    }
    
    echo json_encode($results);
    
} catch (Exception $e) {
    error_log("Members search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
