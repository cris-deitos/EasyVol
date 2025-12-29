<?php
/**
 * Geocoding API
 * 
 * Fornisce servizi di geocoding per indirizzi
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Services\GeocodingService;

header('Content-Type: application/json');

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    $config = $app->getConfig();
    $geocodingService = new GeocodingService($config);
    
    switch ($action) {
        case 'search':
            // Cerca indirizzi
            $query = $_GET['q'] ?? '';
            
            if (empty($query)) {
                echo json_encode(['results' => []]);
                exit;
            }
            
            $results = $geocodingService->searchAddress($query);
            
            echo json_encode([
                'success' => true,
                'results' => $results
            ]);
            break;
            
        case 'geocode':
            // Geocodifica un indirizzo
            $address = $_GET['address'] ?? '';
            
            if (empty($address)) {
                http_response_code(400);
                echo json_encode(['error' => 'Indirizzo mancante']);
                exit;
            }
            
            $result = $geocodingService->geocode($address);
            
            if (!$result) {
                http_response_code(404);
                echo json_encode(['error' => 'Indirizzo non trovato']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'result' => $result
            ]);
            break;
            
        case 'reverse':
            // Reverse geocoding
            $lat = floatval($_GET['lat'] ?? 0);
            $lon = floatval($_GET['lon'] ?? 0);
            
            if ($lat == 0 || $lon == 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Coordinate non valide']);
                exit;
            }
            
            $result = $geocodingService->reverseGeocode($lat, $lon);
            
            if (!$result) {
                http_response_code(404);
                echo json_encode(['error' => 'Località non trovata']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'result' => $result
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Azione non valida']);
            break;
    }
    
} catch (\Exception $e) {
    error_log("Errore API geocoding: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore del server: ' . $e->getMessage()]);
}
