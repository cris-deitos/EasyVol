<?php
/**
 * API Endpoint for Real-time Notification Updates
 * 
 * Returns notification counts and dashboard statistics for auto-refresh
 */

require_once __DIR__ . '/../../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\NotificationHelper;

header('Content-Type: application/json');

$app = App::getInstance();

// Check if user is logged in
if (!$app->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = $app->getDb();
$user = $app->getCurrentUser();

try {
    $response = [];
    
    // Reset cache to get fresh data
    NotificationHelper::resetCache();
    
    // Get notification counts
    $notifications = NotificationHelper::getNotifications();
    $response['notifications'] = [
        'total' => NotificationHelper::getNotificationCount(),
        'items' => $notifications
    ];
    
    // Get individual notification type counts
    $response['counts'] = [
        'applications' => NotificationHelper::getNotificationCountByType('applications'),
        'fee_payments' => NotificationHelper::getNotificationCountByType('fee_payments')
    ];
    
    // Get dashboard statistics if requested
    if (isset($_GET['include_dashboard']) && $_GET['include_dashboard'] === '1') {
        $stats = [];
        
        // Total active members
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE member_status = 'attivo'");
        $stats['active_members'] = $result['count'] ?? 0;
        
        // Total junior members
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM junior_members WHERE member_status = 'attivo'");
        $stats['junior_members'] = $result['count'] ?? 0;
        
        // Pending applications
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM member_applications WHERE status = 'pending'");
        $stats['pending_applications'] = $result['count'] ?? 0;
        
        // Upcoming events (scheduled/planned events)
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM events WHERE status IN ('pianificato', 'programmato') AND start_date >= NOW()");
        $stats['upcoming_events'] = $result['count'] ?? 0;
        
        // Pending fee payment requests
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM fee_payment_requests WHERE status = 'pending'");
        $stats['pending_fee_requests'] = $result['count'] ?? 0;
        
        $response['dashboard_stats'] = $stats;
    }
    
    // Get Operations Center statistics if requested
    if (isset($_GET['include_operations_center']) && $_GET['include_operations_center'] === '1') {
        $oc_stats = [];
        
        // Active events
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM events WHERE status = 'in_corso'");
        $oc_stats['active_events'] = $result['count'] ?? 0;
        
        // Available radios
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM radio_directory WHERE status = 'disponibile'");
        $oc_stats['available_radios'] = $result['count'] ?? 0;
        
        // Available vehicles
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM vehicles WHERE status = 'operativo'");
        $oc_stats['available_vehicles'] = $result['count'] ?? 0;
        
        // Available members
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE member_status = 'attivo' AND volunteer_status = 'operativo'");
        $oc_stats['available_members'] = $result['count'] ?? 0;
        
        $response['operations_center_stats'] = $oc_stats;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Notifications update API error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal server error']);
}
