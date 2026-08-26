<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Utils\PDFSignatureExtractor;

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
            $convention['amounts'] = $this->getAmounts($id);
            $convention['attachments'] = $this->getAttachments($id);
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
     * Ottieni importi annuali di una convenzione
     */
    public function getAmounts($conventionId) {
        $sql = "SELECT * FROM convention_amounts WHERE convention_id = ? ORDER BY year";
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
            
            // Add amounts
            if (!empty($data['amounts'])) {
                $this->saveAmounts($conventionId, $data['amounts']);
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
            
            // Replace amounts
            $this->db->execute("DELETE FROM convention_amounts WHERE convention_id = ?", [$id]);
            if (!empty($data['amounts'])) {
                $this->saveAmounts($id, $data['amounts']);
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
     * Salva importi annuali
     */
    private function saveAmounts($conventionId, $amounts) {
        $sql = "INSERT INTO convention_amounts (convention_id, year, amount, notes)
                VALUES (?, ?, ?, ?)";
        
        foreach ($amounts as $amount) {
            if (empty($amount['year']) || (empty($amount['amount']) && $amount['amount'] !== '0')) continue;
            $params = [
                $conventionId,
                (int)$amount['year'],
                (float)($amount['amount'] ?? 0),
                $amount['notes'] ?? null
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
     * Ottieni allegati di una convenzione
     */
    public function getAttachments($conventionId) {
        $sql = "SELECT ca.*, u.full_name as uploaded_by_name
                FROM convention_attachments ca
                LEFT JOIN users u ON ca.uploaded_by = u.id
                WHERE ca.convention_id = ?
                ORDER BY ca.uploaded_at DESC";
        return $this->db->fetchAll($sql, [$conventionId]);
    }

    /**
     * Aggiungi allegato a una convenzione con rilevamento firma digitale
     */
    public function addAttachment($conventionId, $data, $userId) {
        try {
            $signatureInfo = PDFSignatureExtractor::getEmptyResult();
            $filePath = __DIR__ . '/../../' . $data['file_path'];
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if (file_exists($filePath) && in_array($extension, ['pdf', 'p7m'])) {
                $signatureInfo = PDFSignatureExtractor::extractSignatures($filePath);
            }

            $sql = "INSERT INTO convention_attachments
                    (convention_id, file_name, file_path, file_type, file_size, title, description, uploaded_by,
                     has_signature, signature_format, signature_count, signature_data, signature_validity, signature_checked_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $params = [
                $conventionId,
                $data['file_name'],
                $data['file_path'],
                $data['file_type'] ?? null,
                $data['file_size'] ?? 0,
                $data['title'] ?? null,
                $data['description'] ?? null,
                $userId,
                !empty($signatureInfo['has_signature']) ? 1 : 0,
                $signatureInfo['format'] ?? null,
                $signatureInfo['count'] ?? 0,
                !empty($signatureInfo['signatures']) ? json_encode($signatureInfo['signatures'], JSON_UNESCAPED_UNICODE) : null,
                $signatureInfo['validity'] ?? 'unknown'
            ];
            $this->db->execute($sql, $params);
            $attachmentId = $this->db->lastInsertId();

            $this->logActivity($userId, 'conventions', 'add_attachment', $conventionId,
                'Aggiunto allegato: ' . $data['file_name']);

            return $attachmentId;
        } catch (\Throwable $e) {
            error_log("Errore aggiunta allegato convenzione: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina allegato convenzione
     */
    public function deleteAttachment($attachmentId, $userId) {
        try {
            $sql = "SELECT * FROM convention_attachments WHERE id = ?";
            $attachment = $this->db->fetchOne($sql, [$attachmentId]);
            if (!$attachment) {
                return ['success' => false, 'message' => 'Allegato non trovato'];
            }
            $this->db->execute("DELETE FROM convention_attachments WHERE id = ?", [$attachmentId]);
            $this->logActivity($userId, 'conventions', 'delete_attachment', $attachment['convention_id'],
                'Eliminato allegato: ' . $attachment['file_name']);
            return ['success' => true, 'file_path' => $attachment['file_path']];
        } catch (\Throwable $e) {
            error_log("Errore eliminazione allegato convenzione: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione'];
        }
    }

    /**
     * Ri-verifica firme digitali di un allegato
     */
    public function recheckAttachmentSignatures($attachmentId, $userId) {
        try {
            $sql = "SELECT * FROM convention_attachments WHERE id = ?";
            $attachment = $this->db->fetchOne($sql, [$attachmentId]);
            if (!$attachment) {
                return ['success' => false, 'message' => 'Allegato non trovato'];
            }

            $filePath = __DIR__ . '/../../' . $attachment['file_path'];
            $signatureInfo = PDFSignatureExtractor::extractSignatures($filePath);

            $updateSql = "UPDATE convention_attachments SET
                          has_signature = ?, signature_format = ?, signature_count = ?,
                          signature_data = ?, signature_validity = ?, signature_checked_at = NOW()
                          WHERE id = ?";
            $this->db->execute($updateSql, [
                !empty($signatureInfo['has_signature']) ? 1 : 0,
                $signatureInfo['format'] ?? null,
                $signatureInfo['count'] ?? 0,
                !empty($signatureInfo['signatures']) ? json_encode($signatureInfo['signatures'], JSON_UNESCAPED_UNICODE) : null,
                $signatureInfo['validity'] ?? 'unknown',
                $attachmentId
            ]);

            return ['success' => true, 'has_signature' => !empty($signatureInfo['has_signature'])];
        } catch (\Throwable $e) {
            error_log("Errore ri-verifica firma: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante la verifica'];
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
