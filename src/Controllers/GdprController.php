<?php
namespace EasyVol\Controllers;

use EasyVol\Database;

/**
 * GDPR Controller
 * 
 * Gestisce tutte le operazioni relative alla conformità GDPR
 */
class GdprController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    // ==================== PRIVACY CONSENTS ====================
    
    /**
     * Lista consensi privacy con filtri
     */
    public function indexConsents($filters = [], $page = 1, $perPage = 20) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['entity_type'])) {
            $where[] = "pc.entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['consent_type'])) {
            $where[] = "pc.consent_type = ?";
            $params[] = $filters['consent_type'];
        }
        
        if (!empty($filters['consent_given'])) {
            $where[] = "pc.consent_given = ?";
            $params[] = $filters['consent_given'];
        }
        
        if (!empty($filters['revoked'])) {
            $where[] = "pc.revoked = ?";
            $params[] = $filters['revoked'];
        }
        
        if (!empty($filters['expiring_soon'])) {
            $where[] = "pc.consent_expiry_date IS NOT NULL AND pc.consent_expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(m.first_name LIKE ? OR m.last_name LIKE ? OR jm.first_name LIKE ? OR jm.last_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT pc.*, 
                    CASE 
                        WHEN pc.entity_type = 'member' THEN CONCAT(m.first_name, ' ', m.last_name)
                        WHEN pc.entity_type = 'junior_member' THEN CONCAT(jm.first_name, ' ', jm.last_name)
                    END as entity_name,
                    CASE 
                        WHEN pc.entity_type = 'member' THEN m.registration_number
                        WHEN pc.entity_type = 'junior_member' THEN jm.registration_number
                    END as entity_registration_number,
                    u1.username as created_by_username,
                    u2.username as updated_by_username
                FROM privacy_consents pc
                LEFT JOIN members m ON pc.entity_type = 'member' AND pc.entity_id = m.id
                LEFT JOIN junior_members jm ON pc.entity_type = 'junior_member' AND pc.entity_id = jm.id
                LEFT JOIN users u1 ON pc.created_by = u1.id
                LEFT JOIN users u2 ON pc.updated_by = u2.id
                WHERE $whereClause 
                ORDER BY pc.consent_date DESC 
                LIMIT $perPage OFFSET $offset";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Conta consensi privacy
     */
    public function countConsents($filters = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['entity_type'])) {
            $where[] = "pc.entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['consent_type'])) {
            $where[] = "pc.consent_type = ?";
            $params[] = $filters['consent_type'];
        }
        
        if (!empty($filters['consent_given'])) {
            $where[] = "pc.consent_given = ?";
            $params[] = $filters['consent_given'];
        }
        
        if (!empty($filters['revoked'])) {
            $where[] = "pc.revoked = ?";
            $params[] = $filters['revoked'];
        }
        
        if (!empty($filters['expiring_soon'])) {
            $where[] = "pc.consent_expiry_date IS NOT NULL AND pc.consent_expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(m.first_name LIKE ? OR m.last_name LIKE ? OR jm.first_name LIKE ? OR jm.last_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) as total FROM privacy_consents pc
                LEFT JOIN members m ON pc.entity_type = 'member' AND pc.entity_id = m.id
                LEFT JOIN junior_members jm ON pc.entity_type = 'junior_member' AND pc.entity_id = jm.id
                WHERE $whereClause";
        $result = $this->db->fetchOne($sql, $params);
        
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Ottieni singolo consenso privacy
     */
    public function getConsent($id) {
        $sql = "SELECT pc.*, 
                    CASE 
                        WHEN pc.entity_type = 'member' THEN CONCAT(m.first_name, ' ', m.last_name)
                        WHEN pc.entity_type = 'junior_member' THEN CONCAT(jm.first_name, ' ', jm.last_name)
                    END as entity_name,
                    u1.username as created_by_username,
                    u2.username as updated_by_username
                FROM privacy_consents pc
                LEFT JOIN members m ON pc.entity_type = 'member' AND pc.entity_id = m.id
                LEFT JOIN junior_members jm ON pc.entity_type = 'junior_member' AND pc.entity_id = jm.id
                LEFT JOIN users u1 ON pc.created_by = u1.id
                LEFT JOIN users u2 ON pc.updated_by = u2.id
                WHERE pc.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Crea nuovo consenso privacy
     */
    public function createConsent($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO privacy_consents (
                entity_type, entity_id, consent_type, consent_given, consent_date,
                consent_expiry_date, consent_version, consent_method, consent_document_path,
                revoked, revoked_date, notes, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['entity_type'],
                $data['entity_id'],
                $data['consent_type'],
                $data['consent_given'] ?? 0,
                $data['consent_date'],
                $data['consent_expiry_date'] ?? null,
                $data['consent_version'] ?? null,
                $data['consent_method'] ?? 'paper',
                $data['consent_document_path'] ?? null,
                $data['revoked'] ?? 0,
                $data['revoked_date'] ?? null,
                $data['notes'] ?? null,
                $userId
            ];
            
            $this->db->execute($sql, $params);
            $consentId = $this->db->lastInsertId();
            
            $this->logActivity($userId, 'gdpr_compliance', 'create_consent', $consentId, 'Creato consenso privacy');
            
            $this->db->commit();
            return $consentId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore creazione consenso: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Crea multipli consensi privacy (uno per ogni tipo selezionato)
     * Usato quando si creano consensi multipli da un singolo form
     */
    public function createMultipleConsents($consentTypes, $data, $userId) {
        if (empty($consentTypes) || !is_array($consentTypes)) {
            throw new \Exception('Nessun tipo di consenso selezionato');
        }
        
        // Validate required fields
        if (empty($data['entity_type']) || !in_array($data['entity_type'], ['member', 'junior_member'])) {
            throw new \Exception('Tipo entità non valido');
        }
        
        if (empty($data['entity_id']) || !is_numeric($data['entity_id']) || $data['entity_id'] <= 0) {
            throw new \Exception('ID entità non valido');
        }
        
        if (empty($data['consent_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['consent_date'])) {
            throw new \Exception('Data consenso non valida');
        }
        
        // Validate consent types
        $validConsentTypes = ['privacy_policy', 'data_processing', 'sensitive_data', 'marketing', 'third_party_communication', 'image_rights'];
        foreach ($consentTypes as $type) {
            if (!in_array($type, $validConsentTypes)) {
                throw new \Exception('Tipo di consenso non valido: ' . htmlspecialchars($type));
            }
        }
        
        try {
            $this->db->beginTransaction();
            
            $createdIds = [];
            $sql = "INSERT INTO privacy_consents (
                entity_type, entity_id, consent_type, consent_given, consent_date,
                consent_expiry_date, consent_version, consent_method, consent_document_path,
                revoked, revoked_date, notes, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            // Crea un consenso per ogni tipo selezionato
            foreach ($consentTypes as $consentType) {
                $params = [
                    $data['entity_type'],
                    $data['entity_id'],
                    $consentType,  // Il tipo di consenso varia per ogni record
                    $data['consent_given'] ?? 0,
                    $data['consent_date'],
                    $data['consent_expiry_date'] ?? null,
                    $data['consent_version'] ?? null,
                    $data['consent_method'] ?? 'paper',
                    $data['consent_document_path'] ?? null,  // Lo stesso file per tutti i consensi
                    $data['revoked'] ?? 0,
                    $data['revoked_date'] ?? null,
                    $data['notes'] ?? null,
                    $userId
                ];
                
                $this->db->execute($sql, $params);
                $createdIds[] = $this->db->lastInsertId();
            }
            
            $this->logActivity($userId, 'gdpr_compliance', 'create_multiple_consents', 
                implode(',', $createdIds), 
                'Creati ' . count($createdIds) . ' consensi privacy');
            
            $this->db->commit();
            return $createdIds;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore creazione consensi multipli: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Aggiorna consenso privacy
     */
    public function updateConsent($id, $data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "UPDATE privacy_consents SET
                entity_type = ?, entity_id = ?, consent_type = ?, consent_given = ?,
                consent_date = ?, consent_expiry_date = ?, consent_version = ?,
                consent_method = ?, consent_document_path = ?, revoked = ?,
                revoked_date = ?, notes = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['entity_type'],
                $data['entity_id'],
                $data['consent_type'],
                $data['consent_given'] ?? 0,
                $data['consent_date'],
                $data['consent_expiry_date'] ?? null,
                $data['consent_version'] ?? null,
                $data['consent_method'] ?? 'paper',
                $data['consent_document_path'] ?? null,
                $data['revoked'] ?? 0,
                $data['revoked_date'] ?? null,
                $data['notes'] ?? null,
                $userId,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            $this->logActivity($userId, 'gdpr_compliance', 'update_consent', $id, 'Aggiornato consenso privacy');
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore aggiornamento consenso: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Elimina consenso privacy
     */
    public function deleteConsent($id, $userId) {
        try {
            $sql = "DELETE FROM privacy_consents WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            $this->logActivity($userId, 'gdpr_compliance', 'delete_consent', $id, 'Eliminato consenso privacy');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore eliminazione consenso: " . $e->getMessage());
            return false;
        }
    }
    
    // ==================== PERSONAL DATA EXPORT REQUESTS ====================
    
    /**
     * Lista richieste export dati personali con filtri
     */
    public function indexExportRequests($filters = [], $page = 1, $perPage = 20) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['entity_type'])) {
            $where[] = "pder.entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "pder.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(m.first_name LIKE ? OR m.last_name LIKE ? OR jm.first_name LIKE ? OR jm.last_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT pder.*, 
                    CASE 
                        WHEN pder.entity_type = 'member' THEN CONCAT(m.first_name, ' ', m.last_name)
                        WHEN pder.entity_type = 'junior_member' THEN CONCAT(jm.first_name, ' ', jm.last_name)
                    END as entity_name,
                    CASE 
                        WHEN pder.entity_type = 'member' THEN m.registration_number
                        WHEN pder.entity_type = 'junior_member' THEN jm.registration_number
                    END as entity_registration_number,
                    u.username as requested_by_username
                FROM personal_data_export_requests pder
                LEFT JOIN members m ON pder.entity_type = 'member' AND pder.entity_id = m.id
                LEFT JOIN junior_members jm ON pder.entity_type = 'junior_member' AND pder.entity_id = jm.id
                LEFT JOIN users u ON pder.requested_by_user_id = u.id
                WHERE $whereClause 
                ORDER BY pder.request_date DESC 
                LIMIT $perPage OFFSET $offset";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Conta richieste export
     */
    public function countExportRequests($filters = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['entity_type'])) {
            $where[] = "pder.entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "pder.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(m.first_name LIKE ? OR m.last_name LIKE ? OR jm.first_name LIKE ? OR jm.last_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) as total FROM personal_data_export_requests pder
                LEFT JOIN members m ON pder.entity_type = 'member' AND pder.entity_id = m.id
                LEFT JOIN junior_members jm ON pder.entity_type = 'junior_member' AND pder.entity_id = jm.id
                WHERE $whereClause";
        $result = $this->db->fetchOne($sql, $params);
        
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Ottieni singola richiesta export
     */
    public function getExportRequest($id) {
        $sql = "SELECT pder.*, 
                    CASE 
                        WHEN pder.entity_type = 'member' THEN CONCAT(m.first_name, ' ', m.last_name)
                        WHEN pder.entity_type = 'junior_member' THEN CONCAT(jm.first_name, ' ', jm.last_name)
                    END as entity_name,
                    u.username as requested_by_username
                FROM personal_data_export_requests pder
                LEFT JOIN members m ON pder.entity_type = 'member' AND pder.entity_id = m.id
                LEFT JOIN junior_members jm ON pder.entity_type = 'junior_member' AND pder.entity_id = jm.id
                LEFT JOIN users u ON pder.requested_by_user_id = u.id
                WHERE pder.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Crea nuova richiesta export
     */
    public function createExportRequest($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO personal_data_export_requests (
                entity_type, entity_id, request_date, requested_by_user_id,
                request_reason, status, completed_date, export_file_path, notes, created_at
            ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['entity_type'],
                $data['entity_id'],
                $userId,
                $data['request_reason'] ?? null,
                $data['status'] ?? 'pending',
                $data['completed_date'] ?? null,
                $data['export_file_path'] ?? null,
                $data['notes'] ?? null
            ];
            
            $this->db->execute($sql, $params);
            $requestId = $this->db->lastInsertId();
            
            $this->logActivity($userId, 'gdpr_compliance', 'create_export_request', $requestId, 'Creata richiesta export dati personali');
            
            $this->db->commit();
            return $requestId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore creazione richiesta export: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Aggiorna richiesta export
     */
    public function updateExportRequest($id, $data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "UPDATE personal_data_export_requests SET
                entity_type = ?, entity_id = ?, request_reason = ?,
                status = ?, completed_date = ?, export_file_path = ?,
                notes = ?, updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['entity_type'],
                $data['entity_id'],
                $data['request_reason'] ?? null,
                $data['status'] ?? 'pending',
                $data['completed_date'] ?? null,
                $data['export_file_path'] ?? null,
                $data['notes'] ?? null,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            $this->logActivity($userId, 'gdpr_compliance', 'update_export_request', $id, 'Aggiornata richiesta export dati personali');
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore aggiornamento richiesta export: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Elimina richiesta export
     */
    public function deleteExportRequest($id, $userId) {
        try {
            $sql = "DELETE FROM personal_data_export_requests WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            $this->logActivity($userId, 'gdpr_compliance', 'delete_export_request', $id, 'Eliminata richiesta export dati personali');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore eliminazione richiesta export: " . $e->getMessage());
            return false;
        }
    }
    
    // ==================== SENSITIVE DATA ACCESS LOG ====================
    
    /**
     * Lista accessi dati sensibili (sola lettura)
     */
    public function indexAccessLogs($filters = [], $page = 1, $perPage = 20) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where[] = "sdal.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['entity_type'])) {
            $where[] = "sdal.entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['access_type'])) {
            $where[] = "sdal.access_type = ?";
            $params[] = $filters['access_type'];
        }
        
        if (!empty($filters['module'])) {
            $where[] = "sdal.module = ?";
            $params[] = $filters['module'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(sdal.accessed_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(sdal.accessed_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT sdal.*, 
                    u.username as user_username,
                    CASE 
                        WHEN sdal.entity_type = 'member' THEN CONCAT(m.first_name, ' ', m.last_name)
                        WHEN sdal.entity_type = 'junior_member' THEN CONCAT(jm.first_name, ' ', jm.last_name)
                        WHEN sdal.entity_type = 'user' THEN u2.username
                    END as entity_name
                FROM sensitive_data_access_log sdal
                LEFT JOIN users u ON sdal.user_id = u.id
                LEFT JOIN members m ON sdal.entity_type = 'member' AND sdal.entity_id = m.id
                LEFT JOIN junior_members jm ON sdal.entity_type = 'junior_member' AND sdal.entity_id = jm.id
                LEFT JOIN users u2 ON sdal.entity_type = 'user' AND sdal.entity_id = u2.id
                WHERE $whereClause 
                ORDER BY sdal.accessed_at DESC 
                LIMIT $perPage OFFSET $offset";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Conta accessi dati sensibili
     */
    public function countAccessLogs($filters = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['entity_type'])) {
            $where[] = "entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['access_type'])) {
            $where[] = "access_type = ?";
            $params[] = $filters['access_type'];
        }
        
        if (!empty($filters['module'])) {
            $where[] = "module = ?";
            $params[] = $filters['module'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(accessed_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(accessed_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) as total FROM sensitive_data_access_log WHERE $whereClause";
        $result = $this->db->fetchOne($sql, $params);
        
        return $result ? (int)$result['total'] : 0;
    }
    
    // ==================== DATA PROCESSING REGISTRY ====================
    
    /**
     * Lista registro trattamenti con filtri
     */
    public function indexProcessingRegistry($filters = [], $page = 1, $perPage = 20) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['legal_basis'])) {
            $where[] = "dpr.legal_basis = ?";
            $params[] = $filters['legal_basis'];
        }
        
        if (!empty($filters['is_active'])) {
            $where[] = "dpr.is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(dpr.processing_name LIKE ? OR dpr.processing_purpose LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT dpr.*, 
                    u1.username as created_by_username,
                    u2.username as updated_by_username
                FROM data_processing_registry dpr
                LEFT JOIN users u1 ON dpr.created_by = u1.id
                LEFT JOIN users u2 ON dpr.updated_by = u2.id
                WHERE $whereClause 
                ORDER BY dpr.processing_name 
                LIMIT $perPage OFFSET $offset";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Conta registri trattamenti
     */
    public function countProcessingRegistry($filters = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['legal_basis'])) {
            $where[] = "legal_basis = ?";
            $params[] = $filters['legal_basis'];
        }
        
        if (!empty($filters['is_active'])) {
            $where[] = "is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(processing_name LIKE ? OR processing_purpose LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) as total FROM data_processing_registry WHERE $whereClause";
        $result = $this->db->fetchOne($sql, $params);
        
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Ottieni singolo registro trattamento
     */
    public function getProcessingRegistry($id) {
        $sql = "SELECT dpr.*, 
                    u1.username as created_by_username,
                    u2.username as updated_by_username
                FROM data_processing_registry dpr
                LEFT JOIN users u1 ON dpr.created_by = u1.id
                LEFT JOIN users u2 ON dpr.updated_by = u2.id
                WHERE dpr.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Crea nuovo registro trattamento
     */
    public function createProcessingRegistry($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO data_processing_registry (
                processing_name, processing_purpose, data_categories, data_subjects,
                recipients, third_country_transfer, third_country_details, retention_period,
                security_measures, legal_basis, legal_basis_details, data_controller,
                data_processor, dpo_contact, is_active, notes, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['processing_name'],
                $data['processing_purpose'],
                $data['data_categories'],
                $data['data_subjects'],
                $data['recipients'] ?? null,
                $data['third_country_transfer'] ?? 0,
                $data['third_country_details'] ?? null,
                $data['retention_period'] ?? null,
                $data['security_measures'] ?? null,
                $data['legal_basis'],
                $data['legal_basis_details'] ?? null,
                $data['data_controller'] ?? null,
                $data['data_processor'] ?? null,
                $data['dpo_contact'] ?? null,
                $data['is_active'] ?? 1,
                $data['notes'] ?? null,
                $userId
            ];
            
            $this->db->execute($sql, $params);
            $registryId = $this->db->lastInsertId();
            
            $this->logActivity($userId, 'gdpr_compliance', 'create_processing_registry', $registryId, 'Creato registro trattamento: ' . $data['processing_name']);
            
            $this->db->commit();
            return $registryId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore creazione registro trattamento: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Aggiorna registro trattamento
     */
    public function updateProcessingRegistry($id, $data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "UPDATE data_processing_registry SET
                processing_name = ?, processing_purpose = ?, data_categories = ?,
                data_subjects = ?, recipients = ?, third_country_transfer = ?,
                third_country_details = ?, retention_period = ?, security_measures = ?,
                legal_basis = ?, legal_basis_details = ?, data_controller = ?,
                data_processor = ?, dpo_contact = ?, is_active = ?, notes = ?,
                updated_by = ?, updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['processing_name'],
                $data['processing_purpose'],
                $data['data_categories'],
                $data['data_subjects'],
                $data['recipients'] ?? null,
                $data['third_country_transfer'] ?? 0,
                $data['third_country_details'] ?? null,
                $data['retention_period'] ?? null,
                $data['security_measures'] ?? null,
                $data['legal_basis'],
                $data['legal_basis_details'] ?? null,
                $data['data_controller'] ?? null,
                $data['data_processor'] ?? null,
                $data['dpo_contact'] ?? null,
                $data['is_active'] ?? 1,
                $data['notes'] ?? null,
                $userId,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            $this->logActivity($userId, 'gdpr_compliance', 'update_processing_registry', $id, 'Aggiornato registro trattamento');
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore aggiornamento registro trattamento: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Elimina registro trattamento
     */
    public function deleteProcessingRegistry($id, $userId) {
        try {
            $sql = "DELETE FROM data_processing_registry WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            $this->logActivity($userId, 'gdpr_compliance', 'delete_processing_registry', $id, 'Eliminato registro trattamento');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore eliminazione registro trattamento: " . $e->getMessage());
            return false;
        }
    }
    
    // ==================== DATA CONTROLLER APPOINTMENTS ====================
    
    /**
     * Lista nomine responsabili con filtri
     */
    public function indexAppointments($filters = [], $page = 1, $perPage = 20) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['appointment_type'])) {
            $where[] = "dca.appointment_type = ?";
            $params[] = $filters['appointment_type'];
        }
        
        if (!empty($filters['is_active'])) {
            $where[] = "dca.is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(u.username LIKE ? OR u.full_name LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ? OR dca.external_person_name LIKE ? OR dca.external_person_surname LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT dca.*, 
                    u.username, u.full_name as user_full_name,
                    m.registration_number, m.first_name as member_first_name, m.last_name as member_last_name,
                    CONCAT(COALESCE(m.first_name, ''), ' ', COALESCE(m.last_name, '')) as member_full_name,
                    CASE 
                        WHEN dca.external_person_name IS NOT NULL THEN CONCAT(dca.external_person_name, ' ', dca.external_person_surname)
                        WHEN dca.member_id IS NOT NULL THEN CONCAT(m.first_name, ' ', m.last_name)
                        WHEN dca.user_id IS NOT NULL THEN u.full_name
                    END as appointee_name,
                    CASE 
                        WHEN dca.external_person_name IS NOT NULL THEN 'external'
                        WHEN dca.member_id IS NOT NULL THEN 'member'
                        WHEN dca.user_id IS NOT NULL THEN 'user'
                    END as appointee_type,
                    u1.username as created_by_username,
                    u2.username as updated_by_username
                FROM data_controller_appointments dca
                LEFT JOIN users u ON dca.user_id = u.id
                LEFT JOIN members m ON dca.member_id = m.id
                LEFT JOIN users u1 ON dca.created_by = u1.id
                LEFT JOIN users u2 ON dca.updated_by = u2.id
                WHERE $whereClause 
                ORDER BY dca.appointment_date DESC 
                LIMIT $perPage OFFSET $offset";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Conta nomine responsabili
     */
    public function countAppointments($filters = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['appointment_type'])) {
            $where[] = "dca.appointment_type = ?";
            $params[] = $filters['appointment_type'];
        }
        
        if (!empty($filters['is_active'])) {
            $where[] = "dca.is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(u.username LIKE ? OR u.full_name LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ? OR dca.external_person_name LIKE ? OR dca.external_person_surname LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) as total FROM data_controller_appointments dca
                LEFT JOIN users u ON dca.user_id = u.id
                LEFT JOIN members m ON dca.member_id = m.id
                WHERE $whereClause";
        $result = $this->db->fetchOne($sql, $params);
        
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Ottieni singola nomina responsabile
     */
    public function getAppointment($id) {
        $sql = "SELECT dca.*, 
                    u.username, u.full_name as user_full_name, u.email as user_email,
                    m.registration_number, m.first_name as member_first_name, m.last_name as member_last_name,
                    CONCAT(COALESCE(m.first_name, ''), ' ', COALESCE(m.last_name, '')) as member_full_name,
                    CASE 
                        WHEN dca.external_person_name IS NOT NULL THEN CONCAT(dca.external_person_name, ' ', dca.external_person_surname)
                        WHEN dca.member_id IS NOT NULL THEN CONCAT(m.first_name, ' ', m.last_name)
                        WHEN dca.user_id IS NOT NULL THEN u.full_name
                    END as appointee_name,
                    CASE 
                        WHEN dca.external_person_name IS NOT NULL THEN 'external'
                        WHEN dca.member_id IS NOT NULL THEN 'member'
                        WHEN dca.user_id IS NOT NULL THEN 'user'
                    END as appointee_type,
                    u1.username as created_by_username,
                    u2.username as updated_by_username
                FROM data_controller_appointments dca
                LEFT JOIN users u ON dca.user_id = u.id
                LEFT JOIN members m ON dca.member_id = m.id
                LEFT JOIN users u1 ON dca.created_by = u1.id
                LEFT JOIN users u2 ON dca.updated_by = u2.id
                WHERE dca.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Ottieni nomina responsabile con dati anagrafici completi
     */
public function getAppointmentWithMemberData($id) {
    $sql = "SELECT dca.*, 
                u.username, u.email, u.member_id,
                m.registration_number, m. first_name as member_first_name, m.last_name as member_last_name, m. tax_code,
                m. birth_date, m.birth_place, m.birth_province,
                m.gender, m. nationality,
                ma.street, ma.number, ma.city, ma.province, ma.cap,
                mc_phone. value as phone,
                mc_mobile.value as mobile,
                mc_email.value as member_email,
                u1.username as created_by_username,
                u2.username as updated_by_username
            FROM data_controller_appointments dca
            LEFT JOIN users u ON dca. user_id = u.id
            LEFT JOIN members m ON dca.member_id = m.id OR u.member_id = m.id
            LEFT JOIN member_addresses ma ON m.id = ma.member_id AND ma.address_type = 'residenza'
            LEFT JOIN member_contacts mc_phone ON m.id = mc_phone.member_id AND mc_phone.contact_type = 'telefono_fisso'
            LEFT JOIN member_contacts mc_mobile ON m.id = mc_mobile.member_id AND mc_mobile.contact_type = 'cellulare'
            LEFT JOIN member_contacts mc_email ON m. id = mc_email.member_id AND mc_email.contact_type = 'email'
            LEFT JOIN users u1 ON dca.created_by = u1.id
            LEFT JOIN users u2 ON dca. updated_by = u2.id
            WHERE dca.id = ? ";
    return $this->db->fetchOne($sql, [$id]);
}
    
    /**
     * Crea nuova nomina responsabile
     */
    public function createAppointment($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO data_controller_appointments (
                user_id, member_id, external_person_name, external_person_surname, external_person_tax_code,
                external_person_birth_date, external_person_birth_place, external_person_birth_province,
                external_person_gender, external_person_address, external_person_city, external_person_province,
                external_person_postal_code, external_person_phone, external_person_email,
                appointment_type, appointment_date, revocation_date, is_active,
                scope, responsibilities, data_categories_access, appointment_document_path,
                training_completed, training_date, notes, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['user_id'] ?? null,
                $data['member_id'] ?? null,
                $data['external_person_name'] ?? null,
                $data['external_person_surname'] ?? null,
                $data['external_person_tax_code'] ?? null,
                $data['external_person_birth_date'] ?? null,
                $data['external_person_birth_place'] ?? null,
                $data['external_person_birth_province'] ?? null,
                $data['external_person_gender'] ?? null,
                $data['external_person_address'] ?? null,
                $data['external_person_city'] ?? null,
                $data['external_person_province'] ?? null,
                $data['external_person_postal_code'] ?? null,
                $data['external_person_phone'] ?? null,
                $data['external_person_email'] ?? null,
                $data['appointment_type'],
                $data['appointment_date'],
                $data['revocation_date'] ?? null,
                $data['is_active'] ?? 1,
                $data['scope'] ?? null,
                $data['responsibilities'] ?? null,
                $data['data_categories_access'] ?? null,
                $data['appointment_document_path'] ?? null,
                $data['training_completed'] ?? 0,
                $data['training_date'] ?? null,
                $data['notes'] ?? null,
                $userId
            ];
            
            $this->db->execute($sql, $params);
            $appointmentId = $this->db->lastInsertId();
            
            $this->logActivity($userId, 'gdpr_compliance', 'create_appointment', $appointmentId, 'Creata nomina responsabile trattamento dati');
            
            $this->db->commit();
            return $appointmentId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore creazione nomina: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Aggiorna nomina responsabile
     */
    public function updateAppointment($id, $data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "UPDATE data_controller_appointments SET
                user_id = ?, member_id = ?, external_person_name = ?, external_person_surname = ?,
                external_person_tax_code = ?, external_person_birth_date = ?, external_person_birth_place = ?,
                external_person_birth_province = ?, external_person_gender = ?, external_person_address = ?,
                external_person_city = ?, external_person_province = ?, external_person_postal_code = ?,
                external_person_phone = ?, external_person_email = ?,
                appointment_type = ?, appointment_date = ?, revocation_date = ?, is_active = ?,
                scope = ?, responsibilities = ?, data_categories_access = ?, appointment_document_path = ?,
                training_completed = ?, training_date = ?, notes = ?,
                updated_by = ?, updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['user_id'] ?? null,
                $data['member_id'] ?? null,
                $data['external_person_name'] ?? null,
                $data['external_person_surname'] ?? null,
                $data['external_person_tax_code'] ?? null,
                $data['external_person_birth_date'] ?? null,
                $data['external_person_birth_place'] ?? null,
                $data['external_person_birth_province'] ?? null,
                $data['external_person_gender'] ?? null,
                $data['external_person_address'] ?? null,
                $data['external_person_city'] ?? null,
                $data['external_person_province'] ?? null,
                $data['external_person_postal_code'] ?? null,
                $data['external_person_phone'] ?? null,
                $data['external_person_email'] ?? null,
                $data['appointment_type'],
                $data['appointment_date'],
                $data['revocation_date'] ?? null,
                $data['is_active'] ?? 1,
                $data['scope'] ?? null,
                $data['responsibilities'] ?? null,
                $data['data_categories_access'] ?? null,
                $data['appointment_document_path'] ?? null,
                $data['training_completed'] ?? 0,
                $data['training_date'] ?? null,
                $data['notes'] ?? null,
                $userId,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            $this->logActivity($userId, 'gdpr_compliance', 'update_appointment', $id, 'Aggiornata nomina responsabile trattamento dati');
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore aggiornamento nomina: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Elimina nomina responsabile
     */
    public function deleteAppointment($id, $userId) {
        try {
            $sql = "DELETE FROM data_controller_appointments WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            $this->logActivity($userId, 'gdpr_compliance', 'delete_appointment', $id, 'Eliminata nomina responsabile trattamento dati');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore eliminazione nomina: " . $e->getMessage());
            return false;
        }
    }
    
    // ==================== HELPER METHODS ====================
    
    /**
     * Ottieni tutti i membri per dropdown
     */
    public function getMembers() {
        $sql = "SELECT id, registration_number, first_name, last_name, member_status
                FROM members 
                WHERE member_status = 'attivo'
                ORDER BY last_name, first_name";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Ottieni tutti i cadetti per dropdown
     */
    public function getJuniorMembers() {
        $sql = "SELECT id, registration_number, first_name, last_name, member_status
                FROM junior_members 
                WHERE member_status = 'attivo'
                ORDER BY last_name, first_name";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Ottieni tutti gli utenti per dropdown
     */
    public function getUsers() {
        $sql = "SELECT id, username, full_name
                FROM users 
                WHERE is_active = 1
                ORDER BY full_name";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Registra attività nel log
     */
    private function logActivity($userId, $module, $action, $recordId, $details, $oldData = null, $newData = null) {
        try {
            $sql = "INSERT INTO activity_logs 
                    (user_id, module, action, record_id, description, ip_address, user_agent, old_data, new_data, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $userId,
                $module,
                $action,
                $recordId,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                is_array($oldData) ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : $oldData,
                is_array($newData) ? json_encode($newData, JSON_UNESCAPED_UNICODE) : $newData,
            ];
            
            $this->db->execute($sql, $params);
            
        } catch (\Exception $e) {
            error_log("Errore log attività: " . $e->getMessage());
        }
    }
}
