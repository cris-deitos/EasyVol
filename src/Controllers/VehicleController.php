<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Utils\QrCodeGenerator;

/**
 * Vehicle Controller
 * 
 * Gestisce tutte le operazioni CRUD per i mezzi (veicoli, natanti, rimorchi)
 */
class VehicleController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Lista mezzi con filtri e paginazione
     */
    public function index($filters = [], $page = 1, $perPage = 20) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['type'])) {
            $where[] = "vehicle_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(name LIKE ? OR license_plate LIKE ? OR brand LIKE ? OR model LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM vehicles 
                WHERE $whereClause 
                ORDER BY name 
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Ottieni singolo mezzo con dettagli
     */
    public function get($id) {
        $sql = "SELECT * FROM vehicles WHERE id = ?";
        $vehicle = $this->db->fetchOne($sql, [$id]);
        
        if (!$vehicle) {
            return false;
        }
        
        // Carica manutenzioni recenti
        $vehicle['maintenances'] = $this->getMaintenances($id, 10);
        
        // Carica documenti
        $vehicle['documents'] = $this->getDocuments($id);
        
        return $vehicle;
    }
    
    /**
     * Crea nuovo mezzo
     */
    public function create($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $this->validateVehicleData($data);
            
            $sql = "INSERT INTO vehicles (
                vehicle_type, name, license_plate, brand, model, year,
                serial_number, status, insurance_expiry, inspection_expiry, notes,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $params = [
                $data['vehicle_type'],
                $data['name'],
                $data['license_plate'] ?? null,
                $data['brand'] ?? null,
                $data['model'] ?? null,
                $data['year'] ?? null,
                $data['serial_number'] ?? null,
                $data['status'] ?? 'operativo',
                $data['insurance_expiry'] ?? null,
                $data['inspection_expiry'] ?? null,
                $data['notes'] ?? null
            ];
            
            $this->db->execute($sql, $params);
            $vehicleId = $this->db->lastInsertId();
            
            // Genera QR code se richiesto
            if (!empty($data['generate_qr'])) {
                $this->generateQrCode($vehicleId);
            }
            
            $this->logActivity($userId, 'vehicle', 'create', $vehicleId, 'Creato nuovo mezzo: ' . $data['name']);
            
            $this->db->commit();
            return $vehicleId;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Errore creazione mezzo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna mezzo
     */
    public function update($id, $data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $this->validateVehicleData($data, $id);
            
            $sql = "UPDATE vehicles SET
                vehicle_type = ?, name = ?, license_plate = ?, brand = ?, 
                model = ?, year = ?, serial_number = ?, status = ?,
                insurance_expiry = ?, inspection_expiry = ?, notes = ?,
                updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['vehicle_type'],
                $data['name'],
                $data['license_plate'] ?? null,
                $data['brand'] ?? null,
                $data['model'] ?? null,
                $data['year'] ?? null,
                $data['serial_number'] ?? null,
                $data['status'] ?? 'operativo',
                $data['insurance_expiry'] ?? null,
                $data['inspection_expiry'] ?? null,
                $data['notes'] ?? null,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            $this->logActivity($userId, 'vehicle', 'update', $id, 'Aggiornato mezzo');
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Errore aggiornamento mezzo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina mezzo (soft delete con status)
     */
    public function delete($id, $userId) {
        try {
            $sql = "UPDATE vehicles SET status = 'dismesso', updated_at = NOW() WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            $this->logActivity($userId, 'vehicle', 'delete', $id, 'Eliminato/dismesso mezzo');
            
            return true;
        } catch (\Exception $e) {
            error_log("Errore eliminazione mezzo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiungi manutenzione
     */
    public function addMaintenance($vehicleId, $data, $userId) {
        try {
            $sql = "INSERT INTO vehicle_maintenance (
                vehicle_id, maintenance_type, date, description, cost, performed_by, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $vehicleId,
                $data['maintenance_type'],
                $data['date'],
                $data['description'],
                $data['cost'] ?? null,
                $data['performed_by'] ?? null,
                $data['notes'] ?? null
            ];
            
            $this->db->execute($sql, $params);
            $maintenanceId = $this->db->lastInsertId();
            
            $this->logActivity($userId, 'vehicle_maintenance', 'create', $maintenanceId, 
                'Aggiunta manutenzione per mezzo ID ' . $vehicleId);
            
            return $maintenanceId;
            
        } catch (\Exception $e) {
            error_log("Errore aggiunta manutenzione: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni manutenzioni di un mezzo
     */
    public function getMaintenances($vehicleId, $limit = null) {
        $sql = "SELECT * FROM vehicle_maintenance WHERE vehicle_id = ? ORDER BY date DESC";
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        return $this->db->fetchAll($sql, [$vehicleId]);
    }
    
    /**
     * Ottieni documenti di un mezzo
     */
    public function getDocuments($vehicleId) {
        $sql = "SELECT * FROM vehicle_documents WHERE vehicle_id = ? ORDER BY uploaded_at DESC";
        return $this->db->fetchAll($sql, [$vehicleId]);
    }
    
    /**
     * Genera QR code per il mezzo
     */
    private function generateQrCode($vehicleId) {
        try {
            $vehicle = $this->db->fetchOne("SELECT * FROM vehicles WHERE id = ?", [$vehicleId]);
            if (!$vehicle) {
                return false;
            }
            
            $qrGen = new QrCodeGenerator();
            $qrData = "VEHICLE:" . $vehicleId . ":" . $vehicle['name'];
            $filename = 'vehicle_' . $vehicleId . '.png';
            
            $qrPath = $qrGen->generate($qrData, $filename);
            
            // Aggiorna path nel database se necessario
            // Per ora il QR viene salvato ma non tracciato nel DB
            
            return $qrPath;
            
        } catch (\Exception $e) {
            error_log("Errore generazione QR: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Valida dati mezzo
     */
    private function validateVehicleData($data, $id = null) {
        $required = ['vehicle_type', 'name'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Campo obbligatorio mancante: $field");
            }
        }
        
        // Valida targa se presente e se è un veicolo
        if (!empty($data['license_plate']) && $data['vehicle_type'] === 'veicolo') {
            $sql = "SELECT id FROM vehicles WHERE license_plate = ? AND vehicle_type = 'veicolo'";
            $params = [$data['license_plate']];
            
            if ($id !== null) {
                $sql .= " AND id != ?";
                $params[] = $id;
            }
            
            $existing = $this->db->fetchOne($sql, $params);
            if ($existing) {
                throw new \Exception("Targa già esistente");
            }
        }
    }
    
    /**
     * Registra attività nel log
     */
    private function logActivity($userId, $module, $action, $recordId, $details) {
        try {
            $sql = "INSERT INTO activity_logs 
                    (user_id, module, action, record_id, details, ip_address, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $userId,
                $module,
                $action,
                $recordId,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ];
            
            $this->db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Errore log attività: " . $e->getMessage());
        }
    }
}
