<?php
namespace EasyVol\Controllers;

use EasyVol\Database;

/**
 * Password Controller
 * 
 * Gestisce le password condivise con controllo di accesso individuale
 */
class PasswordController {
    private $db;
    private $config;
    private $encryptionKey;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
        
        // Get encryption key from config or generate one
        $this->encryptionKey = $config['encryption_key'] ?? $this->getOrCreateEncryptionKey();
    }
    
    /**
     * Get or create encryption key for password encryption
     */
    private function getOrCreateEncryptionKey() {
        // Try to get from database config
        $sql = "SELECT config_value FROM config WHERE config_key = 'password_encryption_key' LIMIT 1";
        $result = $this->db->fetchOne($sql);
        
        if ($result && !empty($result['config_value'])) {
            return $result['config_value'];
        }
        
        // Generate a new key
        $key = bin2hex(random_bytes(32));
        
        // Store it in database
        $sql = "INSERT INTO config (config_key, config_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)";
        $this->db->execute($sql, ['password_encryption_key', $key]);
        
        return $key;
    }
    
    /**
     * Encrypt a password
     */
    private function encryptPassword($password) {
        $iv = random_bytes(16);
        // Convert hex key to binary for encryption
        $keyBinary = hex2bin($this->encryptionKey);
        $encrypted = openssl_encrypt($password, 'aes-256-cbc', $keyBinary, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt a password
     */
    private function decryptPassword($encryptedPassword) {
        $data = base64_decode($encryptedPassword);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        // Convert hex key to binary for decryption
        $keyBinary = hex2bin($this->encryptionKey);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $keyBinary, 0, $iv);
    }
    
    /**
     * Check if a user has access to a specific password
     */
    public function userHasAccess($passwordId, $userId, $checkEdit = false) {
        // Check if user created the password (creator has full access)
        $sql = "SELECT created_by FROM passwords WHERE id = ?";
        $password = $this->db->fetchOne($sql, [$passwordId]);
        
        if (!$password) {
            return false;
        }
        
        if ($password['created_by'] == $userId) {
            return true;
        }
        
        // Check password_permissions table
        if ($checkEdit) {
            $sql = "SELECT can_edit FROM password_permissions 
                    WHERE password_id = ? AND user_id = ? AND can_edit = 1";
        } else {
            $sql = "SELECT can_view FROM password_permissions 
                    WHERE password_id = ? AND user_id = ? AND can_view = 1";
        }
        
        $permission = $this->db->fetchOne($sql, [$passwordId, $userId]);
        return $permission ? true : false;
    }
    
    /**
     * Get passwords accessible by a user
     */
    public function getPasswordsForUser($userId, $filters = [], $page = 1, $perPage = 20) {
        $where = ["(p.created_by = ? OR pp.user_id = ?)"];
        $params = [$userId, $userId];
        
        if (!empty($filters['search'])) {
            $where[] = "(p.title LIKE ? OR p.link LIKE ? OR p.username LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT DISTINCT p.*, 
                       u.username as creator_username, u.full_name as creator_name,
                       CASE 
                           WHEN p.created_by = ? THEN 1
                           WHEN pp.can_edit = 1 THEN 1
                           ELSE 0
                       END as can_edit_permission
                FROM passwords p
                LEFT JOIN password_permissions pp ON p.id = pp.password_id
                LEFT JOIN users u ON p.created_by = u.id
                WHERE $whereClause
                ORDER BY p.title ASC
                LIMIT $perPage OFFSET $offset";
        
        $params[] = $userId;
        $passwords = $this->db->fetchAll($sql, $params);
        
        // Don't decrypt passwords in list view - they'll be decrypted on demand
        return $passwords;
    }
    
    /**
     * Count passwords accessible by a user
     */
    public function countPasswordsForUser($userId, $filters = []) {
        $where = ["(p.created_by = ? OR pp.user_id = ?)"];
        $params = [$userId, $userId];
        
        if (!empty($filters['search'])) {
            $where[] = "(p.title LIKE ? OR p.link LIKE ? OR p.username LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(DISTINCT p.id) as total
                FROM passwords p
                LEFT JOIN password_permissions pp ON p.id = pp.password_id
                WHERE $whereClause";
        
        $result = $this->db->fetchOne($sql, $params);
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Get a single password by ID (with decryption)
     */
    public function getPassword($passwordId, $userId, $decrypt = false) {
        if (!$this->userHasAccess($passwordId, $userId)) {
            return null;
        }
        
        $sql = "SELECT p.*, u.username as creator_username, u.full_name as creator_name
                FROM passwords p
                LEFT JOIN users u ON p.created_by = u.id
                WHERE p.id = ?";
        
        $password = $this->db->fetchOne($sql, [$passwordId]);
        
        if ($password && $decrypt) {
            $password['password_decrypted'] = $this->decryptPassword($password['password']);
        }
        
        return $password;
    }
    
    /**
     * Get decrypted password (for AJAX requests)
     */
    public function getDecryptedPassword($passwordId, $userId) {
        if (!$this->userHasAccess($passwordId, $userId)) {
            return null;
        }
        
        $sql = "SELECT password FROM passwords WHERE id = ?";
        $result = $this->db->fetchOne($sql, [$passwordId]);
        
        if ($result) {
            return $this->decryptPassword($result['password']);
        }
        
        return null;
    }
    
    /**
     * Create a new password
     */
    public function create($data, $userId) {
        $sql = "INSERT INTO passwords (title, link, username, password, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $encryptedPassword = $this->encryptPassword($data['password']);
        
        $params = [
            $data['title'],
            $data['link'] ?? null,
            $data['username'] ?? null,
            $encryptedPassword,
            $data['notes'] ?? null,
            $userId
        ];
        
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }
    
    /**
     * Update a password
     */
    public function update($passwordId, $data, $userId) {
        if (!$this->userHasAccess($passwordId, $userId, true)) {
            return false;
        }
        
        $sql = "UPDATE passwords 
                SET title = ?, link = ?, username = ?, notes = ?";
        
        $params = [
            $data['title'],
            $data['link'] ?? null,
            $data['username'] ?? null,
            $data['notes'] ?? null
        ];
        
        // Only update password if provided
        if (!empty($data['password'])) {
            $sql .= ", password = ?";
            $params[] = $this->encryptPassword($data['password']);
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $passwordId;
        
        return $this->db->execute($sql, $params);
    }
    
    /**
     * Delete a password
     */
    public function delete($passwordId, $userId) {
        // Only creator can delete
        $sql = "SELECT created_by FROM passwords WHERE id = ?";
        $password = $this->db->fetchOne($sql, [$passwordId]);
        
        if (!$password || $password['created_by'] != $userId) {
            return false;
        }
        
        $sql = "DELETE FROM passwords WHERE id = ?";
        return $this->db->execute($sql, [$passwordId]);
    }
    
    /**
     * Grant permission to a user for a password
     */
    public function grantPermission($passwordId, $targetUserId, $canView = true, $canEdit = false, $grantingUserId) {
        // Only creator can grant permissions
        $sql = "SELECT created_by FROM passwords WHERE id = ?";
        $password = $this->db->fetchOne($sql, [$passwordId]);
        
        if (!$password || $password['created_by'] != $grantingUserId) {
            return false;
        }
        
        $sql = "INSERT INTO password_permissions (password_id, user_id, can_view, can_edit)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_edit = VALUES(can_edit)";
        
        return $this->db->execute($sql, [$passwordId, $targetUserId, $canView ? 1 : 0, $canEdit ? 1 : 0]);
    }
    
    /**
     * Revoke permission from a user for a password
     */
    public function revokePermission($passwordId, $targetUserId, $revokingUserId) {
        // Only creator can revoke permissions
        $sql = "SELECT created_by FROM passwords WHERE id = ?";
        $password = $this->db->fetchOne($sql, [$passwordId]);
        
        if (!$password || $password['created_by'] != $revokingUserId) {
            return false;
        }
        
        $sql = "DELETE FROM password_permissions WHERE password_id = ? AND user_id = ?";
        return $this->db->execute($sql, [$passwordId, $targetUserId]);
    }
    
    /**
     * Get permissions for a password
     */
    public function getPasswordPermissions($passwordId, $userId) {
        // Only creator can view permissions
        $sql = "SELECT created_by FROM passwords WHERE id = ?";
        $password = $this->db->fetchOne($sql, [$passwordId]);
        
        if (!$password || $password['created_by'] != $userId) {
            return [];
        }
        
        $sql = "SELECT pp.*, u.username, u.full_name
                FROM password_permissions pp
                JOIN users u ON pp.user_id = u.id
                WHERE pp.password_id = ?
                ORDER BY u.full_name, u.username";
        
        return $this->db->fetchAll($sql, [$passwordId]);
    }
    
    /**
     * Get all users for permission assignment
     */
    public function getAllUsers($excludeUserId = null) {
        $sql = "SELECT id, username, full_name, email
                FROM users
                WHERE is_active = 1";
        
        $params = [];
        if ($excludeUserId) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }
        
        $sql .= " ORDER BY full_name, username";
        
        return $this->db->fetchAll($sql, $params);
    }
}
