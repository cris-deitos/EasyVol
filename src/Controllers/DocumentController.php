<?php
namespace EasyVol\Controllers;

use EasyVol\Database;

/**
 * Document Controller
 * 
 * Gestisce l'archivio documentale dell'associazione
 */
class DocumentController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Lista documenti con filtri
     */
    public function index($filters = [], $page = 1, $perPage = 20) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['category'])) {
            $where[] = "category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(title LIKE ? OR description LIKE ? OR tags LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM documents 
                WHERE $whereClause 
                ORDER BY uploaded_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Conta documenti con filtri
     */
    public function count($filters = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['category'])) {
            $where[] = "category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(title LIKE ? OR description LIKE ? OR tags LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) as total FROM documents WHERE $whereClause";
        $result = $this->db->fetchOne($sql, $params);
        
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Ottieni statistiche documenti
     */
    public function getStats() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(DISTINCT category) as categories,
                    SUM(file_size) as total_size
                FROM documents";
        
        return $this->db->fetchOne($sql);
    }
    
    /**
     * Ottieni categorie uniche
     */
    public function getCategories() {
        $sql = "SELECT DISTINCT category, COUNT(*) as count 
                FROM documents 
                GROUP BY category 
                ORDER BY category";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Ottieni singolo documento
     */
    public function get($id) {
        $sql = "SELECT d.*, u.username as uploaded_by_username
                FROM documents d
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Crea nuovo documento
     */
    public function create($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO documents (
                category, title, description, file_name, file_path, 
                file_size, mime_type, tags, uploaded_by, uploaded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['category'],
                $data['title'],
                $data['description'] ?? null,
                $data['file_name'],
                $data['file_path'],
                $data['file_size'] ?? null,
                $data['mime_type'] ?? null,
                $data['tags'] ?? null,
                $userId
            ];
            
            $this->db->execute($sql, $params);
            $documentId = $this->db->lastInsertId();
            
            $this->logActivity($userId, 'documents', 'create', $documentId, 'Caricato documento: ' . $data['title']);
            
            $this->db->commit();
            return $documentId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore creazione documento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna documento (solo metadati)
     */
    public function update($id, $data, $userId) {
        try {
            $sql = "UPDATE documents SET
                category = ?, title = ?, description = ?, tags = ?
                WHERE id = ?";
            
            $params = [
                $data['category'],
                $data['title'],
                $data['description'] ?? null,
                $data['tags'] ?? null,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            $this->logActivity($userId, 'documents', 'update', $id, 'Aggiornato documento');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore aggiornamento documento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina documento
     */
    public function delete($id, $userId) {
        try {
            // Ottieni info documento per eliminare file fisico
            $document = $this->get($id);
            if (!$document) {
                return false;
            }
            
            // Elimina record dal database
            $sql = "DELETE FROM documents WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            // Elimina file fisico - validate path to prevent directory traversal
            $uploadsDir = realpath(__DIR__ . '/../../uploads/documents/');
            $filePath = realpath(__DIR__ . '/../../' . $document['file_path']);
            
            // Only delete if the file is within the uploads directory
            if ($filePath && strpos($filePath, $uploadsDir) === 0 && file_exists($filePath)) {
                unlink($filePath);
            }
            
            $this->logActivity($userId, 'documents', 'delete', $id, 'Eliminato documento: ' . $document['title']);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore eliminazione documento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Download documento
     */
    public function download($id, $userId) {
        try {
            $document = $this->get($id);
            
            if (!$document) {
                return false;
            }
            
            $filePath = __DIR__ . '/../../' . $document['file_path'];
            
            if (!file_exists($filePath)) {
                return false;
            }
            
            $this->logActivity($userId, 'documents', 'download', $id, 'Scaricato documento: ' . $document['title']);
            
            return [
                'file_path' => $filePath,
                'file_name' => $document['file_name'],
                'mime_type' => $document['mime_type']
            ];
            
        } catch (\Exception $e) {
            error_log("Errore download documento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cerca documenti per tag
     */
    public function searchByTag($tag) {
        $sql = "SELECT * FROM documents 
                WHERE FIND_IN_SET(?, REPLACE(tags, ' ', '')) > 0
                ORDER BY uploaded_at DESC";
        return $this->db->fetchAll($sql, [$tag]);
    }
    
    /**
     * Ottieni documenti recenti
     */
    public function getRecent($limit = 10) {
        $sql = "SELECT * FROM documents 
                ORDER BY uploaded_at DESC 
                LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Ottieni dimensione totale documenti per categoria
     */
    public function getSizeByCategory() {
        $sql = "SELECT category, 
                COUNT(*) as count,
                SUM(file_size) as total_size 
                FROM documents 
                GROUP BY category 
                ORDER BY total_size DESC";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Verifica se il file esiste già
     */
    public function fileExists($fileName) {
        $sql = "SELECT id FROM documents WHERE file_name = ?";
        $result = $this->db->fetchOne($sql, [$fileName]);
        return (bool)$result;
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
