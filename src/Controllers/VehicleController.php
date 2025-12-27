<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Utils\QrCodeGenerator;
use EasyVol\Utils\VehicleIdentifier;
use EasyVol\Controllers\SchedulerSyncController;

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
            $where[] = "(license_plate LIKE ? OR serial_number LIKE ? OR brand LIKE ? OR model LIKE ?)";
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
                ORDER BY 
                    CASE 
                        WHEN license_plate IS NOT NULL AND license_plate != '' THEN license_plate
                        WHEN serial_number IS NOT NULL AND serial_number != '' THEN serial_number
                        ELSE CONCAT(COALESCE(brand, ''), ' ', COALESCE(model, ''))
                    END
                LIMIT $perPage OFFSET $offset";
        
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
            
            // Generate internal name for database storage
            $data['name'] = VehicleIdentifier::generateInternalName($data);
            
            $sql = "INSERT INTO vehicles (
                vehicle_type, name, license_plate, brand, model, year,
                serial_number, status, license_type, insurance_expiry, inspection_expiry, notes,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $params = [
                $data['vehicle_type'],
                $data['name'],
                $data['license_plate'] ?? null,
                $data['brand'] ?? null,
                $data['model'] ?? null,
                $data['year'] ?? null,
                $data['serial_number'] ?? null,
                $data['status'] ?? 'operativo',
                $data['license_type'] ?? null,
                $data['insurance_expiry'] ?? null,
                $data['inspection_expiry'] ?? null,
                $data['notes'] ?? null
            ];
            
            $this->db->execute($sql, $params);
            $vehicleId = $this->db->lastInsertId();
            
            // Sincronizza scadenze con lo scadenziario
            $syncController = new SchedulerSyncController($this->db, $this->config);
            if (!empty($data['insurance_expiry'])) {
                $syncController->syncInsuranceExpiry($vehicleId);
            }
            if (!empty($data['inspection_expiry'])) {
                $syncController->syncInspectionExpiry($vehicleId);
            }
            
            $vehicleIdent = VehicleIdentifier::build($data);
            $this->logActivity($userId, 'vehicle', 'create', $vehicleId, "Creato nuovo mezzo: $vehicleIdent");
            
            $this->db->commit();
            return $vehicleId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
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
            
            // Generate internal name for database storage
            $data['name'] = VehicleIdentifier::generateInternalName($data);
            
            $sql = "UPDATE vehicles SET
                vehicle_type = ?, name = ?, license_plate = ?, brand = ?, 
                model = ?, year = ?, serial_number = ?, status = ?, license_type = ?,
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
                $data['license_type'] ?? null,
                $data['insurance_expiry'] ?? null,
                $data['inspection_expiry'] ?? null,
                $data['notes'] ?? null,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            // Sincronizza scadenze con lo scadenziario
            $syncController = new SchedulerSyncController($this->db, $this->config);
            
            // Get old values to check if expiry dates changed
            $oldVehicle = $this->db->fetchOne("SELECT insurance_expiry, inspection_expiry FROM vehicles WHERE id = ?", [$id]);
            
            // Sync or remove insurance expiry
            if (!empty($data['insurance_expiry'])) {
                $syncController->syncInsuranceExpiry($id);
            } elseif ($oldVehicle && !empty($oldVehicle['insurance_expiry'])) {
                // Insurance expiry was removed, delete scheduler item
                $syncController->removeSchedulerItem('insurance', $id);
            }
            
            // Sync or remove inspection expiry
            if (!empty($data['inspection_expiry'])) {
                $syncController->syncInspectionExpiry($id);
            } elseif ($oldVehicle && !empty($oldVehicle['inspection_expiry'])) {
                // Inspection expiry was removed, delete scheduler item
                $syncController->removeSchedulerItem('inspection', $id);
            }
            
            $this->logActivity($userId, 'vehicle', 'update', $id, 'Aggiornato mezzo');
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
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
            
            // Remove scheduler items for this vehicle
            $syncController = new SchedulerSyncController($this->db, $this->config);
            $syncController->removeSchedulerItem('insurance', $id);
            $syncController->removeSchedulerItem('inspection', $id);
            
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
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO vehicle_maintenance (
                vehicle_id, maintenance_type, date, description, cost, performed_by, notes, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $vehicleId,
                $data['maintenance_type'],
                $data['date'],
                $data['description'],
                $data['cost'] ?? null,
                $data['performed_by'] ?? null,
                $data['notes'] ?? null,
                $data['vehicle_status'] ?? null,
                $userId
            ];
            
            $this->db->execute($sql, $params);
            $maintenanceId = $this->db->lastInsertId();
            
            // Se è una revisione, calcola automaticamente la nuova scadenza
            if ($data['maintenance_type'] === 'revisione') {
                $newInspectionExpiry = $this->calculateInspectionExpiry($data['date']);
                
                // Aggiorna la scadenza revisione del veicolo
                $updateSql = "UPDATE vehicles SET inspection_expiry = ?, updated_at = NOW() WHERE id = ?";
                $this->db->execute($updateSql, [$newInspectionExpiry, $vehicleId]);
                
                // Sincronizza con lo scadenziario
                $syncController = new SchedulerSyncController($this->db, $this->config);
                $syncController->syncInspectionExpiry($vehicleId);
            }
            
            // Aggiorna stato veicolo se specificato
            if (!empty($data['vehicle_status'])) {
                $updateStatusSql = "UPDATE vehicles SET status = ?, updated_at = NOW() WHERE id = ?";
                $this->db->execute($updateStatusSql, [$data['vehicle_status'], $vehicleId]);
            }
            
            $this->logActivity($userId, 'vehicle_maintenance', 'create', $maintenanceId, 
                'Aggiunta manutenzione per mezzo ID ' . $vehicleId);
            
            $this->db->commit();
            return $maintenanceId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore aggiunta manutenzione: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calcola scadenza revisione: ultimo giorno del mese + 2 anni
     * 
     * @param string $revisionDate Data della revisione (YYYY-MM-DD)
     * @return string Data scadenza (YYYY-MM-DD)
     */
    private function calculateInspectionExpiry($revisionDate) {
        $date = new \DateTime($revisionDate);
        
        // Get current month and year
        $year = (int)$date->format('Y');
        $month = (int)$date->format('m');
        
        // Add 2 years
        $year += 2;
        
        // Get the last day of that month in 2 years
        $lastDay = date('t', mktime(0, 0, 0, $month, 1, $year));
        
        // Create the expiry date
        return sprintf('%04d-%02d-%02d', $year, $month, $lastDay);
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
     * Aggiungi documento a un mezzo
     */
    public function addDocument($vehicleId, $data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO vehicle_documents (
                vehicle_id, document_type, file_name, file_path, 
                expiry_date, uploaded_at
            ) VALUES (?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $vehicleId,
                $data['document_type'],
                $data['file_name'],
                $data['file_path'],
                $data['expiry_date'] ?? null
            ];
            
            $this->db->execute($sql, $params);
            $documentId = $this->db->lastInsertId();
            
            // Sincronizza scadenza con lo scadenziario se presente
            if (!empty($data['expiry_date'])) {
                $syncController = new SchedulerSyncController($this->db, $this->config);
                $syncController->syncVehicleDocumentExpiry($documentId, $vehicleId);
            }
            
            $this->logActivity($userId, 'vehicle', 'add_document', $vehicleId, 
                "Aggiunto documento: {$data['document_type']}");
            
            $this->db->commit();
            return $documentId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore aggiunta documento veicolo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna metadati documento di un mezzo
     * Nota: Questo metodo aggiorna solo document_type e expiry_date.
     * Per sostituire il file, eliminare e ricaricare il documento.
     */
    public function updateDocument($documentId, $data, $userId) {
        try {
            $this->db->beginTransaction();
            
            // Get vehicle_id for logging and sync
            $doc = $this->db->fetchOne("SELECT vehicle_id FROM vehicle_documents WHERE id = ?", [$documentId]);
            if (!$doc) {
                throw new \Exception("Documento non trovato");
            }
            
            $sql = "UPDATE vehicle_documents SET
                document_type = ?,
                expiry_date = ?
                WHERE id = ?";
            
            $params = [
                $data['document_type'],
                $data['expiry_date'] ?? null,
                $documentId
            ];
            
            $this->db->execute($sql, $params);
            
            // Sincronizza scadenza con lo scadenziario
            $syncController = new SchedulerSyncController($this->db, $this->config);
            if (!empty($data['expiry_date'])) {
                $syncController->syncVehicleDocumentExpiry($documentId, $doc['vehicle_id']);
            } else {
                // Se la scadenza è stata rimossa, rimuovi l'item dallo scadenziario
                $syncController->removeSchedulerItem('vehicle_document', $documentId);
            }
            
            $this->logActivity($userId, 'vehicle', 'update_document', $doc['vehicle_id'], 
                "Aggiornato documento: {$data['document_type']}");
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore aggiornamento documento veicolo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina documento di un mezzo
     */
    public function deleteDocument($documentId, $userId) {
        try {
            $this->db->beginTransaction();
            
            // Get document info for logging
            $doc = $this->db->fetchOne("SELECT * FROM vehicle_documents WHERE id = ?", [$documentId]);
            if (!$doc) {
                throw new \Exception("Documento non trovato");
            }
            
            // Delete file if exists
            if (file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
            
            // Delete from database
            $this->db->execute("DELETE FROM vehicle_documents WHERE id = ?", [$documentId]);
            
            // Remove from scheduler if exists
            $syncController = new SchedulerSyncController($this->db, $this->config);
            $syncController->removeSchedulerItem('vehicle_document', $documentId);
            
            $this->logActivity($userId, 'vehicle', 'delete_document', $doc['vehicle_id'], 
                "Eliminato documento: {$doc['document_type']}");
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore eliminazione documento veicolo: " . $e->getMessage());
            return false;
        }
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
            
            // Build full path for QR code file
            $uploadsPath = $this->config['uploads']['path'] ?? (__DIR__ . '/../../uploads');
            $qrDirectory = $uploadsPath . '/qrcodes';
            
            // Create directory if it doesn't exist
            if (!is_dir($qrDirectory)) {
                mkdir($qrDirectory, 0755, true);
            }
            
            $filename = $qrDirectory . '/vehicle_' . $vehicleId . '.png';
            $plateNumber = $vehicle['license_plate'] ?? $vehicle['name'];
            
            // Use the static method properly
            $qrPath = QrCodeGenerator::generateForVehicle($vehicleId, $plateNumber, $filename);
            
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
        $required = ['vehicle_type'];
        
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
                    (user_id, module, action, record_id, description, ip_address, user_agent, created_at) 
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
