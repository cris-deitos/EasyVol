<?php
namespace EasyVol\Controllers;

use EasyVol\Database;

/**
 * Convention Controller
 * 
 * Gestisce le convenzioni con enti convenzionati e scadenze annuali
 */
class ConventionController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Lista convenzioni con filtri
     */
    public function index($filters = [], $page = 1, $perPage = 50) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where[] = "(c.name LIKE ? OR c.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $where[] = "(c.end_date IS NULL OR c.end_date >= CURDATE())";
                $where[] = "c.start_date <= CURDATE()";
            } elseif ($filters['status'] === 'expired') {
                $where[] = "c.end_date < CURDATE()";
            } elseif ($filters['status'] === 'future') {
                $where[] = "c.start_date > CURDATE()";
            }
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT c.*, u.full_name as created_by_name,
                (SELECT COUNT(*) FROM convention_entities WHERE convention_id = c.id) as entity_count,
                (SELECT COUNT(*) FROM convention_deadlines WHERE convention_id = c.id) as deadline_count
                FROM conventions c
                LEFT JOIN users u ON c.created_by = u.id
                WHERE $whereClause 
                ORDER BY c.start_date DESC
                LIMIT $perPage OFFSET $offset";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Conta totale convenzioni
     */
    public function count($filters = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where[] = "(c.name LIKE ? OR c.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $where[] = "(c.end_date IS NULL OR c.end_date >= CURDATE())";
                $where[] = "c.start_date <= CURDATE()";
            } elseif ($filters['status'] === 'expired') {
                $where[] = "c.end_date < CURDATE()";
            } elseif ($filters['status'] === 'future') {
                $where[] = "c.start_date > CURDATE()";
            }
        }
        
        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) as total FROM conventions c WHERE $whereClause";
        $result = $this->db->fetchOne($sql, $params);
        return $result['total'] ?? 0;
    }
    
    /**
     * Ottieni singola convenzione con enti e scadenze
     */
    public function get($id) {
        $sql = "SELECT c.*, u.full_name as created_by_name
                FROM conventions c
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.id = ?";
        $convention = $this->db->fetchOne($sql, [$id]);
        
        if ($convention) {
            $convention['entities'] = $this->getEntities($id);
            $convention['deadlines'] = $this->getDeadlines($id);
        }
        
        return $convention;
    }
    
    /**
     * Ottieni enti di una convenzione
     */
    public function getEntities($conventionId) {
        $sql = "SELECT * FROM convention_entities WHERE convention_id = ? ORDER BY denomination";
        return $this->db->fetchAll($sql, [$conventionId]);
    }
    
    /**
     * Ottieni scadenze di una convenzione
     */
    public function getDeadlines($conventionId) {
        $sql = "SELECT * FROM convention_deadlines WHERE convention_id = ? ORDER BY month, day_of_month";
        return $this->db->fetchAll($sql, [$conventionId]);
    }
    
    /**
     * Crea nuova convenzione
     */
    public function create($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO conventions (name, description, start_date, end_date, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['name'],
                $data['description'] ?? null,
                $data['start_date'],
                !empty($data['end_date']) ? $data['end_date'] : null,
                $userId
            ];
            
            $this->db->execute($sql, $params);
            $conventionId = $this->db->lastInsertId();
            
            // Add entities
            if (!empty($data['entities'])) {
                $this->saveEntities($conventionId, $data['entities']);
            }
            
            // Add deadlines
            if (!empty($data['deadlines'])) {
                $this->saveDeadlines($conventionId, $data['deadlines']);
            }
            
            // Log activity
            $this->logActivity($userId, 'conventions', 'create', $conventionId, 
                "Creata convenzione: {$data['name']}");
            
            $this->db->commit();
            return $conventionId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error creating convention: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna convenzione
     */
    public function update($id, $data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "UPDATE conventions SET name = ?, description = ?, start_date = ?, end_date = ?
                    WHERE id = ?";
            
            $params = [
                $data['name'],
                $data['description'] ?? null,
                $data['start_date'],
                !empty($data['end_date']) ? $data['end_date'] : null,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            // Replace entities
            $this->db->execute("DELETE FROM convention_entities WHERE convention_id = ?", [$id]);
            if (!empty($data['entities'])) {
                $this->saveEntities($id, $data['entities']);
            }
            
            // Replace deadlines
            $this->db->execute("DELETE FROM convention_deadlines WHERE convention_id = ?", [$id]);
            if (!empty($data['deadlines'])) {
                $this->saveDeadlines($id, $data['deadlines']);
            }
            
            // Log activity
            $this->logActivity($userId, 'conventions', 'edit', $id, 
                "Modificata convenzione: {$data['name']}");
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error updating convention: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina convenzione
     */
    public function delete($id, $userId) {
        try {
            $convention = $this->get($id);
            if (!$convention) {
                return false;
            }
            
            $this->db->execute("DELETE FROM conventions WHERE id = ?", [$id]);
            
            // Log activity
            $this->logActivity($userId, 'conventions', 'delete', $id, 
                "Eliminata convenzione: {$convention['name']}");
            
            return true;
        } catch (\Exception $e) {
            error_log("Error deleting convention: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva enti convenzionati
     */
    private function saveEntities($conventionId, $entities) {
        $sql = "INSERT INTO convention_entities (convention_id, denomination, entity_type, tax_code, address, phone, email, pec, contact_person, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        foreach ($entities as $entity) {
            if (empty($entity['denomination'])) continue;
            $params = [
                $conventionId,
                $entity['denomination'],
                $entity['entity_type'] ?? null,
                $entity['tax_code'] ?? null,
                $entity['address'] ?? null,
                $entity['phone'] ?? null,
                $entity['email'] ?? null,
                $entity['pec'] ?? null,
                $entity['contact_person'] ?? null,
                $entity['notes'] ?? null
            ];
            $this->db->execute($sql, $params);
        }
    }
    
    /**
     * Salva scadenze annuali
     */
    private function saveDeadlines($conventionId, $deadlines) {
        $sql = "INSERT INTO convention_deadlines (convention_id, day_of_month, month, description, notify_to, advance_days)
                VALUES (?, ?, ?, ?, ?, ?)";
        
        foreach ($deadlines as $deadline) {
            if (empty($deadline['description']) || empty($deadline['day_of_month']) || empty($deadline['month'])) continue;
            $params = [
                $conventionId,
                (int)$deadline['day_of_month'],
                (int)$deadline['month'],
                $deadline['description'],
                $deadline['notify_to'] ?? null,
                (int)($deadline['advance_days'] ?? 7)
            ];
            $this->db->execute($sql, $params);
        }
    }
    
    /**
     * Log attività
     */
    private function logActivity($userId, $module, $action, $entityId, $description, $oldData = null, $newData = null) {
        try {
            $sql = "INSERT INTO activity_logs (user_id, module, action, entity_id, description, old_data, new_data, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $this->db->execute($sql, [
                $userId,
                $module,
                $action,
                $entityId,
                $description,
                $oldData ? json_encode($oldData) : null,
                $newData ? json_encode($newData) : null
            ]);
        } catch (\Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
}
