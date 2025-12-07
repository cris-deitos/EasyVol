<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Utils\QrCodeGenerator;

/**
 * Warehouse Controller
 * 
 * Gestisce magazzino, DPI e movimenti
 */
class WarehouseController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Lista articoli magazzino con filtri
     */
    public function index($filters = [], $page = 1, $perPage = 20) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['category'])) {
            $where[] = "category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['low_stock'])) {
            $where[] = "quantity <= minimum_quantity";
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(name LIKE ? OR code LIKE ? OR description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM warehouse_items 
                WHERE $whereClause 
                ORDER BY name 
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Ottieni singolo articolo
     */
    public function get($id) {
        $sql = "SELECT * FROM warehouse_items WHERE id = ?";
        $item = $this->db->fetchOne($sql, [$id]);
        
        if (!$item) {
            return false;
        }
        
        // Carica movimenti recenti
        $item['movements'] = $this->getMovements($id, 20);
        
        // Carica DPI assegnati
        $item['dpi_assignments'] = $this->getDpiAssignments($id);
        
        return $item;
    }
    
    /**
     * Crea nuovo articolo
     */
    public function create($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $this->validateItemData($data);
            
            $sql = "INSERT INTO warehouse_items (
                code, name, category, description, quantity, minimum_quantity,
                unit, location, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $params = [
                $data['code'] ?? null,
                $data['name'],
                $data['category'] ?? null,
                $data['description'] ?? null,
                $data['quantity'] ?? 0,
                $data['minimum_quantity'] ?? 0,
                $data['unit'] ?? 'pz',
                $data['location'] ?? null,
                $data['status'] ?? 'disponibile'
            ];
            
            $this->db->execute($sql, $params);
            $itemId = $this->db->lastInsertId();
            
            // Genera QR code se richiesto
            if (!empty($data['generate_qr'])) {
                $this->generateQrCode($itemId);
            }
            
            $this->logActivity($userId, 'warehouse_item', 'create', $itemId, 'Creato nuovo articolo: ' . $data['name']);
            
            $this->db->commit();
            return $itemId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore creazione articolo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna articolo
     */
    public function update($id, $data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $this->validateItemData($data, $id);
            
            $sql = "UPDATE warehouse_items SET
                code = ?, name = ?, category = ?, description = ?,
                quantity = ?, minimum_quantity = ?, unit = ?, location = ?,
                status = ?, updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['code'] ?? null,
                $data['name'],
                $data['category'] ?? null,
                $data['description'] ?? null,
                $data['quantity'] ?? 0,
                $data['minimum_quantity'] ?? 0,
                $data['unit'] ?? 'pz',
                $data['location'] ?? null,
                $data['status'] ?? 'disponibile',
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            $this->logActivity($userId, 'warehouse_item', 'update', $id, 'Aggiornato articolo');
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore aggiornamento articolo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra movimento
     */
    public function addMovement($itemId, $data, $userId) {
        try {
            $this->db->beginTransaction();
            
            // Registra movimento
            $sql = "INSERT INTO warehouse_movements (
                item_id, movement_type, quantity, member_id, destination, notes, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $itemId,
                $data['movement_type'],
                $data['quantity'],
                $data['member_id'] ?? null,
                $data['destination'] ?? null,
                $data['notes'] ?? null,
                $userId
            ];
            
            $this->db->execute($sql, $params);
            $movementId = $this->db->lastInsertId();
            
            // Aggiorna quantità articolo
            $quantityChange = in_array($data['movement_type'], ['carico', 'restituzione']) ? 
                $data['quantity'] : -$data['quantity'];
            
            $sql = "UPDATE warehouse_items SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?";
            $this->db->execute($sql, [$quantityChange, $itemId]);
            
            $this->logActivity($userId, 'warehouse_movement', 'create', $movementId, 
                'Registrato movimento ' . $data['movement_type'] . ' per articolo ID ' . $itemId);
            
            $this->db->commit();
            return $movementId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore registrazione movimento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni movimenti articolo
     */
    public function getMovements($itemId, $limit = null) {
        $sql = "SELECT wm.*, m.first_name, m.last_name, u.username as created_by_name
                FROM warehouse_movements wm
                LEFT JOIN members m ON wm.member_id = m.id
                LEFT JOIN users u ON wm.created_by = u.id
                WHERE wm.item_id = ? 
                ORDER BY wm.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        return $this->db->fetchAll($sql, [$itemId]);
    }
    
    /**
     * Ottieni DPI assegnati
     */
    public function getDpiAssignments($itemId) {
        $sql = "SELECT da.*, m.first_name, m.last_name, m.registration_number
                FROM dpi_assignments da
                JOIN members m ON da.member_id = m.id
                WHERE da.item_id = ? 
                ORDER BY da.assignment_date DESC";
        
        return $this->db->fetchAll($sql, [$itemId]);
    }
    
    /**
     * Genera QR code per articolo
     */
    private function generateQrCode($itemId) {
        try {
            $item = $this->db->fetchOne("SELECT * FROM warehouse_items WHERE id = ?", [$itemId]);
            if (!$item) {
                return false;
            }
            
            $qrGen = new QrCodeGenerator();
            $qrData = "ITEM:" . $itemId . ":" . $item['name'];
            $filename = 'item_' . $itemId . '.png';
            
            $qrPath = $qrGen->generate($qrData, $filename);
            
            // Aggiorna path nel database
            $sql = "UPDATE warehouse_items SET qr_code = ? WHERE id = ?";
            $this->db->execute($sql, [$qrPath, $itemId]);
            
            return $qrPath;
            
        } catch (\Exception $e) {
            error_log("Errore generazione QR: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Valida dati articolo
     */
    private function validateItemData($data, $id = null) {
        $required = ['name'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Campo obbligatorio mancante: $field");
            }
        }
        
        // Valida codice se presente
        if (!empty($data['code'])) {
            $sql = "SELECT id FROM warehouse_items WHERE code = ?";
            $params = [$data['code']];
            
            if ($id !== null) {
                $sql .= " AND id != ?";
                $params[] = $id;
            }
            
            $existing = $this->db->fetchOne($sql, $params);
            if ($existing) {
                throw new \Exception("Codice articolo già esistente");
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
    
    /**
     * Elimina articolo magazzino
     */
    public function delete($id, $userId) {
        try {
            // Get item details for log
            $sql = "SELECT item_name FROM warehouse_items WHERE id = ?";
            $item = $this->db->fetchOne($sql, [$id]);
            
            if (!$item) {
                return ['success' => false, 'message' => 'Articolo non trovato'];
            }
            
            // Check if item has movements
            $sql = "SELECT COUNT(*) as count FROM warehouse_movements WHERE item_id = ?";
            $result = $this->db->fetchOne($sql, [$id]);
            
            if ($result['count'] > 0) {
                return ['success' => false, 'message' => 'Impossibile eliminare: articolo ha movimenti registrati'];
            }
            
            // Delete item
            $sql = "DELETE FROM warehouse_items WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            // Log activity
            $this->logActivity($userId, 'warehouse', 'delete', $id, "Eliminato articolo: {$item['item_name']}");
            
            return ['success' => true];
        } catch (\Exception $e) {
            error_log("Errore eliminazione articolo: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione'];
        }
    }
}
