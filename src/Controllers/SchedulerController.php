<?php
namespace EasyVol\Controllers;

use EasyVol\Database;

/**
 * Scheduler Controller
 * 
 * Gestisce scadenzario, promemoria e convenzioni
 */
class SchedulerController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Lista scadenze con filtri
     */
    public function index($filters = [], $page = 1, $perPage = 50) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['priority'])) {
            $where[] = "priority = ?";
            $params[] = $filters['priority'];
        }
        
        if (!empty($filters['category'])) {
            $where[] = "category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['from_date'])) {
            $where[] = "due_date >= ?";
            $params[] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $where[] = "due_date <= ?";
            $params[] = $filters['to_date'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(title LIKE ? OR description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT s.*, u.full_name as assigned_name 
                FROM scheduler_items s
                LEFT JOIN users u ON s.assigned_to = u.id
                WHERE $whereClause 
                ORDER BY 
                    CASE s.priority 
                        WHEN 'urgente' THEN 1 
                        WHEN 'alta' THEN 2 
                        WHEN 'media' THEN 3 
                        WHEN 'bassa' THEN 4 
                    END,
                    s.due_date ASC
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Ottieni singola scadenza
     */
    public function get($id) {
        $sql = "SELECT s.*, u.full_name as assigned_name 
                FROM scheduler_items s
                LEFT JOIN users u ON s.assigned_to = u.id
                WHERE s.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Crea nuova scadenza
     */
    public function create($data, $userId) {
        try {
            $sql = "INSERT INTO scheduler_items (
                title, description, due_date, category, priority, 
                status, reminder_days, assigned_to, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['title'],
                $data['description'] ?? null,
                $data['due_date'],
                $data['category'] ?? null,
                $data['priority'] ?? 'media',
                $data['status'] ?? 'in_attesa',
                $data['reminder_days'] ?? 7,
                $data['assigned_to'] ?? null
            ];
            
            $this->db->execute($sql, $params);
            $itemId = $this->db->lastInsertId();
            
            // Log activity
            $this->logActivity($userId, 'scheduler', 'create', $itemId, 
                "Creata scadenza: {$data['title']}");
            
            return $itemId;
        } catch (\Exception $e) {
            error_log("Error creating scheduler item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna scadenza
     */
    public function update($id, $data, $userId) {
        try {
            // Check if completing
            $completedAt = null;
            if (isset($data['status']) && $data['status'] === 'completato') {
                $completedAt = date('Y-m-d H:i:s');
            }
            
            $sql = "UPDATE scheduler_items SET 
                title = ?, 
                description = ?, 
                due_date = ?, 
                category = ?, 
                priority = ?, 
                status = ?, 
                reminder_days = ?, 
                assigned_to = ?,
                completed_at = ?,
                updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['title'],
                $data['description'] ?? null,
                $data['due_date'],
                $data['category'] ?? null,
                $data['priority'] ?? 'media',
                $data['status'] ?? 'in_attesa',
                $data['reminder_days'] ?? 7,
                $data['assigned_to'] ?? null,
                $completedAt,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            // Log activity
            $this->logActivity($userId, 'scheduler', 'update', $id, 
                "Aggiornata scadenza: {$data['title']}");
            
            return true;
        } catch (\Exception $e) {
            error_log("Error updating scheduler item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina scadenza
     */
    public function delete($id, $userId) {
        try {
            // Get item for log
            $item = $this->get($id);
            
            $sql = "DELETE FROM scheduler_items WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            // Log activity
            $this->logActivity($userId, 'scheduler', 'delete', $id, 
                "Eliminata scadenza: {$item['title']}");
            
            return ['success' => true];
        } catch (\Exception $e) {
            error_log("Error deleting scheduler item: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione'];
        }
    }
    
    /**
     * Segna come completato
     */
    public function complete($id, $userId) {
        try {
            // Get item for log before updating
            $item = $this->get($id);
            
            if (!$item) {
                return false;
            }
            
            $sql = "UPDATE scheduler_items SET 
                    status = 'completato', 
                    completed_at = NOW(),
                    updated_at = NOW()
                    WHERE id = ?";
            
            $this->db->execute($sql, [$id]);
            
            // Log activity
            $this->logActivity($userId, 'scheduler', 'complete', $id, 
                "Completata scadenza: {$item['title']}");
            
            return true;
        } catch (\Exception $e) {
            error_log("Error completing scheduler item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni scadenze in arrivo
     */
    public function getUpcoming($days = 30) {
        $sql = "SELECT s.*, u.full_name as assigned_name 
                FROM scheduler_items s
                LEFT JOIN users u ON s.assigned_to = u.id
                WHERE s.status IN ('in_attesa', 'in_corso')
                AND s.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY s.due_date ASC, 
                    CASE s.priority 
                        WHEN 'urgente' THEN 1 
                        WHEN 'alta' THEN 2 
                        WHEN 'media' THEN 3 
                        WHEN 'bassa' THEN 4 
                    END";
        
        return $this->db->fetchAll($sql, [$days]);
    }
    
    /**
     * Ottieni scadenze scadute
     */
    public function getOverdue() {
        $sql = "SELECT s.*, u.full_name as assigned_name 
                FROM scheduler_items s
                LEFT JOIN users u ON s.assigned_to = u.id
                WHERE s.status IN ('in_attesa', 'in_corso')
                AND s.due_date < CURDATE()
                ORDER BY s.due_date ASC";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Aggiorna automaticamente stato scadenze scadute
     */
    public function updateOverdueStatus() {
        try {
            $sql = "UPDATE scheduler_items 
                    SET status = 'scaduto', updated_at = NOW()
                    WHERE status IN ('in_attesa', 'in_corso')
                    AND due_date < CURDATE()";
            
            $this->db->execute($sql);
            
            $sql = "SELECT ROW_COUNT() as count";
            $result = $this->db->fetchOne($sql);
            
            return $result['count'];
        } catch (\Exception $e) {
            error_log("Error updating overdue status: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Statistiche scadenze
     */
    public function getStats() {
        $stats = [];
        
        // Count by status
        $sql = "SELECT status, COUNT(*) as count 
                FROM scheduler_items 
                GROUP BY status";
        $stats['by_status'] = $this->db->fetchAll($sql);
        
        // Count by priority
        $sql = "SELECT priority, COUNT(*) as count 
                FROM scheduler_items 
                WHERE status IN ('in_attesa', 'in_corso')
                GROUP BY priority";
        $stats['by_priority'] = $this->db->fetchAll($sql);
        
        // Count by category
        $sql = "SELECT category, COUNT(*) as count 
                FROM scheduler_items 
                WHERE category IS NOT NULL
                GROUP BY category";
        $stats['by_category'] = $this->db->fetchAll($sql);
        
        // Upcoming (next 7 days)
        $sql = "SELECT COUNT(*) as count FROM scheduler_items 
                WHERE status IN ('in_attesa', 'in_corso')
                AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $result = $this->db->fetchOne($sql);
        $stats['upcoming_week'] = $result['count'];
        
        // Overdue
        $sql = "SELECT COUNT(*) as count FROM scheduler_items 
                WHERE status IN ('in_attesa', 'in_corso', 'scaduto')
                AND due_date < CURDATE()";
        $result = $this->db->fetchOne($sql);
        $stats['overdue'] = $result['count'];
        
        return $stats;
    }
    
    /**
     * Ottieni conteggi per dashboard
     */
    public function getCounts() {
        $counts = [];
        
        // Total active
        $sql = "SELECT COUNT(*) as count FROM scheduler_items 
                WHERE status IN ('in_attesa', 'in_corso')";
        $result = $this->db->fetchOne($sql);
        $counts['active'] = $result['count'];
        
        // Urgent
        $sql = "SELECT COUNT(*) as count FROM scheduler_items 
                WHERE status IN ('in_attesa', 'in_corso') 
                AND priority = 'urgente'";
        $result = $this->db->fetchOne($sql);
        $counts['urgent'] = $result['count'];
        
        // Due this week
        $sql = "SELECT COUNT(*) as count FROM scheduler_items 
                WHERE status IN ('in_attesa', 'in_corso')
                AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $result = $this->db->fetchOne($sql);
        $counts['this_week'] = $result['count'];
        
        // Overdue
        $sql = "SELECT COUNT(*) as count FROM scheduler_items 
                WHERE status IN ('in_attesa', 'in_corso') 
                AND due_date < CURDATE()";
        $result = $this->db->fetchOne($sql);
        $counts['overdue'] = $result['count'];
        
        return $counts;
    }
    
    /**
     * Ottieni scadenze per invio reminder
     */
    public function getItemsForReminder() {
        $sql = "SELECT s.*, u.email as assigned_email, u.full_name as assigned_name 
                FROM scheduler_items s
                LEFT JOIN users u ON s.assigned_to = u.id
                WHERE s.status IN ('in_attesa', 'in_corso')
                AND s.due_date = DATE_ADD(CURDATE(), INTERVAL s.reminder_days DAY)
                ORDER BY s.priority";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Log activity
     */
    private function logActivity($userId, $module, $action, $recordId, $details) {
        $sql = "INSERT INTO activity_logs (user_id, module, action, record_id, details, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $this->db->execute($sql, [$userId, $module, $action, $recordId, $details]);
    }
}
