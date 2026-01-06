<?php
/**
 * Event Map API
 * 
 * Fornisce dati georeferenziati per la mappa eventi/interventi
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

header('Content-Type: application/json');

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

// Verifica permessi
if (!$app->checkPermission('events', 'view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Accesso negato']);
    exit;
}

$db = $app->getDb();

try {
    // Recupera eventi aperti o in corso con coordinate
    $eventsSQL = "SELECT 
                    id,
                    event_type,
                    title,
                    description,
                    start_date,
                    end_date,
                    location,
                    full_address,
                    municipality,
                    latitude,
                    longitude,
                    status,
                    created_at
                FROM events
                WHERE status = 'in_corso'
                  AND latitude IS NOT NULL 
                  AND longitude IS NOT NULL
                ORDER BY start_date DESC";
    
    $events = $db->fetchAll($eventsSQL);
    
    // Recupera interventi in corso con coordinate
    $interventionsSQL = "SELECT 
                            i.id,
                            i.event_id,
                            i.title,
                            i.description,
                            i.start_time,
                            i.end_time,
                            i.location,
                            i.full_address,
                            i.municipality,
                            i.latitude,
                            i.longitude,
                            i.status,
                            e.title as event_title,
                            e.event_type
                        FROM interventions i
                        INNER JOIN events e ON i.event_id = e.id
                        WHERE i.status IN ('in_corso')
                          AND i.latitude IS NOT NULL 
                          AND i.longitude IS NOT NULL
                        ORDER BY i.start_time DESC";
    
    $interventions = $db->fetchAll($interventionsSQL);
    
    // Formatta i dati per la mappa
    $mapData = [
        'success' => true,
        'timestamp' => date('c'),
        'events' => array_map(function($event) {
            return [
                'id' => intval($event['id']),
                'type' => 'event',
                'event_type' => $event['event_type'],
                'title' => $event['title'],
                'description' => $event['description'] ?? '',
                'start_date' => $event['start_date'],
                'end_date' => $event['end_date'],
                'location' => $event['location'] ?? '',
                'full_address' => $event['full_address'] ?? '',
                'municipality' => $event['municipality'] ?? '',
                'latitude' => floatval($event['latitude']),
                'longitude' => floatval($event['longitude']),
                'status' => $event['status'],
                'status_label' => ucfirst($event['status']),
                'created_at' => $event['created_at'],
                'marker_color' => 'red',
                'icon' => $event['event_type'] === 'emergenza' ? 'exclamation-triangle' : 
                          ($event['event_type'] === 'esercitazione' ? 'bullseye' : 
                          ($event['event_type'] === 'servizio' ? 'briefcase' : 'calendar-event'))
            ];
        }, $events),
        'interventions' => array_map(function($intervention) {
            return [
                'id' => intval($intervention['id']),
                'type' => 'intervention',
                'event_id' => intval($intervention['event_id']),
                'event_title' => $intervention['event_title'],
                'event_type' => $intervention['event_type'],
                'title' => $intervention['title'],
                'description' => $intervention['description'] ?? '',
                'start_time' => $intervention['start_time'],
                'end_time' => $intervention['end_time'],
                'location' => $intervention['location'] ?? '',
                'full_address' => $intervention['full_address'] ?? '',
                'municipality' => $intervention['municipality'] ?? '',
                'latitude' => floatval($intervention['latitude']),
                'longitude' => floatval($intervention['longitude']),
                'status' => $intervention['status'],
                'status_label' => ucfirst($intervention['status']),
                'marker_color' => 'yellow',
                'icon' => 'tools'
            ];
        }, $interventions),
        'totals' => [
            'events' => count($events),
            'interventions' => count($interventions)
        ]
    ];
    
    echo json_encode($mapData);
    
} catch (\Exception $e) {
    error_log("Errore API mappa eventi: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Errore del server',
        'message' => $e->getMessage()
    ]);
}
