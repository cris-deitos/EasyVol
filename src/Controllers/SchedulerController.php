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
    
    // Constants for recurring deadlines
    const MAX_RECURRENCE_ITERATIONS = 100;
    const DEFAULT_LOOKAHEAD_DAYS = 90;
    
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
        
        // Determine ordering based on sort filter
        $orderClause = "";
        if (!empty($filters['sort']) && $filters['sort'] === 'due_date') {
            // Order by due date (closest deadline first)
            $orderClause = "s.due_date ASC";
        } else {
            // Default: order by priority first, then due date
            $orderClause = "CASE s.priority 
                        WHEN 'urgente' THEN 1 
                        WHEN 'alta' THEN 2 
                        WHEN 'media' THEN 3 
                        WHEN 'bassa' THEN 4 
                    END,
                    s.due_date ASC";
        }
        
        $sql = "SELECT s.*, u.full_name as assigned_name 
                FROM scheduler_items s
                LEFT JOIN users u ON s.assigned_to = u.id
                WHERE $whereClause 
                ORDER BY $orderClause
                LIMIT $perPage OFFSET $offset";
        
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
        $item = $this->db->fetchOne($sql, [$id]);
        
        if ($item) {
            // Get recipients
            $item['recipients'] = $this->getRecipients($id);
        }
        
        return $item;
    }
    
    /**
     * Ottieni destinatari email per una scadenza
     */
    public function getRecipients($schedulerItemId) {
        $sql = "SELECT r.*, 
                u.full_name as user_name, u.email as user_email,
                m.first_name, m.last_name, 
                mc.value as member_email
                FROM scheduler_item_recipients r
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN members m ON r.member_id = m.id
                LEFT JOIN member_contacts mc ON (
                    r.member_id = mc.member_id AND mc.contact_type = 'email'
                )
                WHERE r.scheduler_item_id = ?
                ORDER BY r.id";
        
        return $this->db->fetchAll($sql, [$schedulerItemId]);
    }
    
    /**
     * Crea nuova scadenza
     */
    public function create($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO scheduler_items (
                title, description, due_date, category, priority, 
                status, reminder_days, is_recurring, recurrence_type, 
                recurrence_end_date, parent_recurrence_id, assigned_to, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['title'],
                $data['description'] ?? null,
                $data['due_date'],
                $data['category'] ?? null,
                $data['priority'] ?? 'media',
                $data['status'] ?? 'in_attesa',
                $data['reminder_days'] ?? 7,
                isset($data['is_recurring']) ? (int)$data['is_recurring'] : 0,
                $data['recurrence_type'] ?? null,
                $data['recurrence_end_date'] ?? null,
                $data['parent_recurrence_id'] ?? null,
                $data['assigned_to'] ?? null
            ];
            
            $this->db->execute($sql, $params);
            $itemId = $this->db->lastInsertId();
            
            // Add recipients if provided
            if (!empty($data['recipients'])) {
                $this->addRecipients($itemId, $data['recipients']);
            }
            
            // Log activity
            $recurrenceInfo = '';
            if (!empty($data['is_recurring'])) {
                $recurrenceInfo = ' (Ricorrente: ' . $data['recurrence_type'] . ')';
            }
            $this->logActivity($userId, 'scheduler', 'create', $itemId, 
                "Creata scadenza: {$data['title']}{$recurrenceInfo}");
            
            $this->db->commit();
            return $itemId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error creating scheduler item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiungi destinatari a una scadenza
     */
    public function addRecipients($schedulerItemId, $recipients) {
        foreach ($recipients as $recipient) {
            $sql = "INSERT INTO scheduler_item_recipients (
                scheduler_item_id, recipient_type, user_id, member_id, external_email
            ) VALUES (?, ?, ?, ?, ?)";
            
            $params = [
                $schedulerItemId,
                $recipient['type'],
                $recipient['user_id'] ?? null,
                $recipient['member_id'] ?? null,
                $recipient['external_email'] ?? null
            ];
            
            $this->db->execute($sql, $params);
        }
    }
    
    /**
     * Rimuovi tutti i destinatari di una scadenza
     */
    public function removeAllRecipients($schedulerItemId) {
        $sql = "DELETE FROM scheduler_item_recipients WHERE scheduler_item_id = ?";
        $this->db->execute($sql, [$schedulerItemId]);
    }
    
    /**
     * Aggiorna scadenza
     */
    public function update($id, $data, $userId) {
        try {
            $this->db->beginTransaction();
            
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
                is_recurring = ?,
                recurrence_type = ?,
                recurrence_end_date = ?,
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
                isset($data['is_recurring']) ? (int)$data['is_recurring'] : 0,
                $data['recurrence_type'] ?? null,
                $data['recurrence_end_date'] ?? null,
                $data['assigned_to'] ?? null,
                $completedAt,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            // Update recipients if provided
            if (isset($data['recipients'])) {
                $this->removeAllRecipients($id);
                if (!empty($data['recipients'])) {
                    $this->addRecipients($id, $data['recipients']);
                }
            }
            
            // Log activity
            $recurrenceInfo = '';
            if (!empty($data['is_recurring'])) {
                $recurrenceInfo = ' (Ricorrente: ' . $data['recurrence_type'] . ')';
            }
            $this->logActivity($userId, 'scheduler', 'update', $id, 
                "Aggiornata scadenza: {$data['title']}{$recurrenceInfo}");
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
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
        
        // Overdue - count items that are past due date and not completed
        $sql = "SELECT COUNT(*) as count FROM scheduler_items 
                WHERE status IN ('in_attesa', 'in_corso', 'scaduto') 
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
        
        $items = $this->db->fetchAll($sql);
        
        // For each item, get custom recipients
        foreach ($items as &$item) {
            $item['custom_recipients'] = $this->getRecipientEmails($item['id']);
        }
        
        return $items;
    }
    
    /**
     * Ottieni tutti gli indirizzi email dei destinatari per una scadenza
     */
    public function getRecipientEmails($schedulerItemId) {
        $sql = "SELECT 
                CASE 
                    WHEN r.recipient_type = 'user' THEN u.email
                    WHEN r.recipient_type = 'member' THEN mc.value
                    WHEN r.recipient_type = 'external' THEN r.external_email
                END as email,
                CASE 
                    WHEN r.recipient_type = 'user' THEN u.full_name
                    WHEN r.recipient_type = 'member' THEN CONCAT(m.first_name, ' ', m.last_name)
                    WHEN r.recipient_type = 'external' THEN r.external_email
                END as name
                FROM scheduler_item_recipients r
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN members m ON r.member_id = m.id
                LEFT JOIN member_contacts mc ON (
                    r.member_id = mc.member_id AND mc.contact_type = 'email'
                )
                WHERE r.scheduler_item_id = ?";
        
        return $this->db->fetchAll($sql, [$schedulerItemId]);
    }
    
    /**
     * Ottieni link di riferimento per un item dello scadenziario
     * 
     * @param array $item Item dello scadenziario
     * @return array|null Array con informazioni sul link, oppure null
     */
    public function getReferenceLink($item) {
        if (empty($item['reference_type']) || empty($item['reference_id'])) {
            return null;
        }
        
        $syncController = new SchedulerSyncController($this->db, $this->config);
        return $syncController->getReferenceLink($item['reference_type'], $item['reference_id']);
    }
    
    /**
     * Log activity
     */
    private function logActivity($userId, $module, $action, $recordId, $details) {
        $sql = "INSERT INTO activity_logs (user_id, module, action, record_id, description, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $this->db->execute($sql, [$userId, $module, $action, $recordId, $details]);
    }
    
    /**
     * Ottieni tutte le scadenze ricorrenti attive (master records)
     */
    public function getRecurringSchedules() {
        $sql = "SELECT * FROM scheduler_items 
                WHERE is_recurring = 1 
                AND parent_recurrence_id IS NULL
                AND status != 'completato'
                ORDER BY due_date";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Genera prossima occorrenza per una scadenza ricorrente
     * 
     * @param int $parentId ID della scadenza ricorrente principale
     * @param string $baseDate Data base da cui calcolare la prossima occorrenza
     * @return int|false ID della nuova occorrenza o false in caso di errore
     */
    public function generateNextRecurrence($parentId, $baseDate = null) {
        try {
            // Get parent item
            $parent = $this->get($parentId);
            if (!$parent || !$parent['is_recurring']) {
                error_log("Parent item not found or not recurring: $parentId");
                return false;
            }
            
            // Calculate next occurrence date
            if ($baseDate === null) {
                $baseDate = $parent['due_date'];
            }
            
            $nextDate = $this->calculateNextOccurrence($baseDate, $parent['recurrence_type']);
            
            // Check if we should generate (not past end date)
            if ($parent['recurrence_end_date'] && $nextDate > $parent['recurrence_end_date']) {
                return false; // Past end date, don't generate
            }
            
            // Check if this occurrence already exists
            $sql = "SELECT id FROM scheduler_items 
                    WHERE parent_recurrence_id = ? 
                    AND due_date = ?";
            $existing = $this->db->fetchOne($sql, [$parentId, $nextDate]);
            
            if ($existing) {
                return $existing['id']; // Already exists
            }
            
            // Create new occurrence
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO scheduler_items (
                title, description, due_date, category, priority, 
                status, reminder_days, is_recurring, recurrence_type, 
                recurrence_end_date, parent_recurrence_id, assigned_to, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $parent['title'],
                $parent['description'],
                $nextDate,
                $parent['category'],
                $parent['priority'],
                'in_attesa', // Always start as pending
                $parent['reminder_days'],
                0, // Not recurring itself (it's an instance)
                null,
                null,
                $parentId,
                $parent['assigned_to']
            ];
            
            $this->db->execute($sql, $params);
            $newItemId = $this->db->lastInsertId();
            
            // Copy recipients from parent
            $recipients = $this->getRecipients($parentId);
            if (!empty($recipients)) {
                $recipientData = [];
                foreach ($recipients as $recipient) {
                    $recipientData[] = [
                        'type' => $recipient['recipient_type'],
                        'user_id' => $recipient['user_id'],
                        'member_id' => $recipient['member_id'],
                        'external_email' => $recipient['external_email']
                    ];
                }
                $this->addRecipients($newItemId, $recipientData);
            }
            
            $this->db->commit();
            return $newItemId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error generating next recurrence: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calcola la prossima data di occorrenza basata sul tipo di ricorrenza
     * 
     * @param string $currentDate Data corrente
     * @param string $recurrenceType Tipo di ricorrenza (yearly, monthly, weekly)
     * @return string Prossima data in formato Y-m-d
     */
    private function calculateNextOccurrence($currentDate, $recurrenceType) {
        $date = new \DateTime($currentDate);
        
        switch ($recurrenceType) {
            case 'weekly':
                $date->modify('+1 week');
                break;
            case 'monthly':
                $date->modify('+1 month');
                break;
            case 'yearly':
                $date->modify('+1 year');
                break;
            default:
                error_log("Unknown recurrence type: $recurrenceType");
                return $currentDate;
        }
        
        return $date->format('Y-m-d');
    }
    
    /**
     * Genera tutte le occorrenze future per tutte le scadenze ricorrenti attive
     * Questo metodo dovrebbe essere chiamato da un cron job periodicamente
     * 
     * @param int $daysAhead Numero di giorni in avanti per cui generare occorrenze
     * @return int Numero di occorrenze generate
     */
    public function generateAllRecurrences($daysAhead = 90) {
        $count = 0;
        $recurringItems = $this->getRecurringSchedules();
        
        foreach ($recurringItems as $item) {
            $currentDate = $item['due_date'];
            $endDate = new \DateTime();
            $endDate->modify("+{$daysAhead} days");
            $endDateStr = $endDate->format('Y-m-d');
            
            // Generate occurrences until we reach the end date or recurrence end date
            $iteration = 0;
            
            while ($iteration < self::MAX_RECURRENCE_ITERATIONS) {
                $nextDate = $this->calculateNextOccurrence($currentDate, $item['recurrence_type']);
                
                // Stop if next date is beyond our look-ahead period
                if ($nextDate > $endDateStr) {
                    break;
                }
                
                // Stop if next date is beyond recurrence end date
                if ($item['recurrence_end_date'] && $nextDate > $item['recurrence_end_date']) {
                    break;
                }
                
                // Try to generate the occurrence
                $result = $this->generateNextRecurrence($item['id'], $currentDate);
                if ($result !== false) {
                    $count++;
                }
                
                $currentDate = $nextDate;
                $iteration++;
            }
        }
        
        return $count;
    }
    
    /**
     * Elimina una scadenza ricorrente e tutte le sue occorrenze future
     * 
     * @param int $id ID della scadenza ricorrente principale
     * @param int $userId ID dell'utente che esegue l'operazione
     * @param bool $deleteAllOccurrences Se true, elimina anche tutte le occorrenze generate
     * @return array Risultato dell'operazione
     */
    public function deleteRecurringSchedule($id, $userId, $deleteAllOccurrences = true) {
        try {
            $this->db->beginTransaction();
            
            // Get item for log
            $item = $this->get($id);
            if (!$item) {
                return ['success' => false, 'message' => 'Scadenza non trovata'];
            }
            
            if ($deleteAllOccurrences) {
                // Delete all generated occurrences
                $sql = "DELETE FROM scheduler_items WHERE parent_recurrence_id = ?";
                $this->db->execute($sql, [$id]);
            }
            
            // Delete the parent item
            $sql = "DELETE FROM scheduler_items WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            // Log activity
            $this->logActivity($userId, 'scheduler', 'delete', $id, 
                "Eliminata scadenza ricorrente: {$item['title']}");
            
            $this->db->commit();
            return ['success' => true];
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error deleting recurring schedule: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione'];
        }
    }
}
