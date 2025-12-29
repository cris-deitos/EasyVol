<?php
namespace EasyVol\Services;

/**
 * Geocoding Service
 * 
 * Utilizza Nominatim (OpenStreetMap) per la georeferenziazione
 * Nessuna API key richiesta, completamente open source
 */
class GeocodingService {
    private $baseUrl = 'https://nominatim.openstreetmap.org';
    private $userAgent;
    
    public function __construct($config = []) {
        // User agent richiesto da Nominatim
        $this->userAgent = $config['app_name'] ?? 'EasyVol';
    }
    
    /**
     * Cerca indirizzi in base a una query
     * 
     * @param string $query Query di ricerca (es. "Via Roma 1, Milano")
     * @param int $limit Numero massimo di risultati
     * @return array Array di risultati con coordinate e indirizzi completi
     */
    public function searchAddress($query, $limit = 5) {
        if (empty($query)) {
            return [];
        }
        
        $params = [
            'q' => $query,
            'format' => 'json',
            'addressdetails' => 1,
            'limit' => $limit,
            'countrycodes' => 'it' // Limita all'Italia
        ];
        
        $url = $this->baseUrl . '/search?' . http_build_query($params);
        
        try {
            $response = $this->makeRequest($url);
            
            if (!$response) {
                return [];
            }
            
            $data = json_decode($response, true);
            
            if (!is_array($data)) {
                return [];
            }
            
            // Formatta i risultati
            $results = [];
            foreach ($data as $item) {
                $results[] = [
                    'display_name' => $item['display_name'] ?? '',
                    'latitude' => floatval($item['lat'] ?? 0),
                    'longitude' => floatval($item['lon'] ?? 0),
                    'address' => $this->formatAddress($item['address'] ?? []),
                    'municipality' => $this->extractMunicipality($item['address'] ?? []),
                    'raw' => $item
                ];
            }
            
            return $results;
            
        } catch (\Exception $e) {
            error_log("Errore geocoding: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Geocodifica un indirizzo e restituisce coordinate e dati completi
     * 
     * @param string $address Indirizzo da geocodificare
     * @return array|null Dati georeferenziati o null se non trovato
     */
    public function geocode($address) {
        $results = $this->searchAddress($address, 1);
        
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Reverse geocoding: da coordinate a indirizzo
     * 
     * @param float $latitude Latitudine
     * @param float $longitude Longitudine
     * @return array|null Dati indirizzo o null se non trovato
     */
    public function reverseGeocode($latitude, $longitude) {
        $params = [
            'lat' => $latitude,
            'lon' => $longitude,
            'format' => 'json',
            'addressdetails' => 1
        ];
        
        $url = $this->baseUrl . '/reverse?' . http_build_query($params);
        
        try {
            $response = $this->makeRequest($url);
            
            if (!$response) {
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['address'])) {
                return null;
            }
            
            return [
                'display_name' => $data['display_name'] ?? '',
                'latitude' => floatval($data['lat'] ?? $latitude),
                'longitude' => floatval($data['lon'] ?? $longitude),
                'address' => $this->formatAddress($data['address'] ?? []),
                'municipality' => $this->extractMunicipality($data['address'] ?? []),
                'raw' => $data
            ];
            
        } catch (\Exception $e) {
            error_log("Errore reverse geocoding: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Estrae il comune dall'array address di Nominatim
     */
    private function extractMunicipality($address) {
        // Priorità: city, town, village, municipality
        if (!empty($address['city'])) {
            return $address['city'];
        }
        if (!empty($address['town'])) {
            return $address['town'];
        }
        if (!empty($address['village'])) {
            return $address['village'];
        }
        if (!empty($address['municipality'])) {
            return $address['municipality'];
        }
        
        return '';
    }
    
    /**
     * Formatta l'indirizzo in una stringa leggibile
     */
    private function formatAddress($address) {
        $parts = [];
        
        // Via/Piazza
        if (!empty($address['road'])) {
            $parts[] = $address['road'];
        }
        
        // Numero civico
        if (!empty($address['house_number'])) {
            $parts[count($parts) - 1] .= ' ' . $address['house_number'];
        }
        
        // CAP e Città
        $cityPart = '';
        if (!empty($address['postcode'])) {
            $cityPart .= $address['postcode'] . ' ';
        }
        
        $city = $this->extractMunicipality($address);
        if ($city) {
            $cityPart .= $city;
        }
        
        if ($cityPart) {
            $parts[] = $cityPart;
        }
        
        // Provincia
        if (!empty($address['state'])) {
            $parts[] = $address['state'];
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Effettua una richiesta HTTP a Nominatim
     */
    private function makeRequest($url) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        // Rispetta il rate limit di Nominatim (1 richiesta al secondo)
        usleep(1100000); // 1.1 secondi
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Geocoding request failed with HTTP code: " . $httpCode);
            return null;
        }
        
        return $response;
    }
}
