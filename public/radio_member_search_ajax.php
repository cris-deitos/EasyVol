<?php
/**
 * Radio Member Search AJAX Handler
 * 
 * Provides autocomplete search for members and cadets by badge number, name, or surname
 * for radio assignment purposes
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

// Check if user has permission to view operations center
if (!$app->checkPermission('operations_center', 'view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permessi insufficienti']);
    exit;
}

$db = $app->getDb();
$query = trim($_GET['q'] ?? '');

if (empty($query)) {
    echo json_encode([]);
    exit;
}

try {
    $searchTerm = '%' . $query . '%';
    $exactStart = $query . '%';
    
    // Search in members table - active volunteers who are operational
    $sqlMembers = "SELECT 
            id, 
            first_name, 
            last_name, 
            registration_number, 
            badge_number,
            'member' as source_type,
            CASE 
                WHEN badge_number LIKE ? THEN 1
                WHEN registration_number LIKE ? THEN 2
                WHEN last_name LIKE ? THEN 3
                WHEN first_name LIKE ? THEN 4
                ELSE 5
            END as sort_priority
        FROM members 
        WHERE member_status = 'attivo' 
        AND volunteer_status = 'operativo'
        AND (
            badge_number LIKE ? 
            OR registration_number LIKE ? 
            OR first_name LIKE ? 
            OR last_name LIKE ?
            OR CONCAT(first_name, ' ', last_name) LIKE ?
            OR CONCAT(last_name, ' ', first_name) LIKE ?
        )";
    
    // Search in junior_members table - active cadets
    $sqlCadets = "SELECT 
            id, 
            first_name, 
            last_name, 
            registration_number,
            NULL as badge_number,
            'cadet' as source_type,
            CASE 
                WHEN registration_number LIKE ? THEN 2
                WHEN last_name LIKE ? THEN 3
                WHEN first_name LIKE ? THEN 4
                ELSE 5
            END as sort_priority
        FROM junior_members 
        WHERE member_status = 'attivo'
        AND (
            registration_number LIKE ? 
            OR first_name LIKE ? 
            OR last_name LIKE ?
            OR CONCAT(first_name, ' ', last_name) LIKE ?
            OR CONCAT(last_name, ' ', first_name) LIKE ?
        )";
    
    // Union both queries with sorting
    $sql = "SELECT * FROM (
                ($sqlMembers) 
                UNION 
                ($sqlCadets)
            ) as combined
            ORDER BY sort_priority, last_name, first_name
            LIMIT 20";
    
    // Prepare parameters for members query
    $memberParams = [
        // Sort priority params
        $exactStart, $exactStart, $exactStart, $exactStart,
        // Search params
        $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm
    ];
    
    // Prepare parameters for cadets query
    $cadetParams = [
        // Sort priority params
        $exactStart, $exactStart, $exactStart,
        // Search params
        $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm
    ];
    
    // Combine all parameters in correct order
    $params = array_merge($memberParams, $cadetParams);
    
    $results = $db->fetchAll($sql, $params);
    
    // Format results for autocomplete
    $formatted = [];
    foreach ($results as $result) {
        $displayNumber = $result['badge_number'] ?? $result['registration_number'];
        $typeLabel = $result['source_type'] === 'cadet' ? ' [Cadetto]' : '';
        
        $formatted[] = [
            'id' => $result['id'],
            'source_type' => $result['source_type'],
            'label' => $result['last_name'] . ' ' . $result['first_name'] . 
                      ($displayNumber ? ' (Mat. ' . $displayNumber . ')' : '') . 
                      $typeLabel,
            'value' => $result['last_name'] . ' ' . $result['first_name'],
            'display_number' => $displayNumber
        ];
    }
    
    echo json_encode($formatted);
    
} catch (Exception $e) {
    error_log("Radio member search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
