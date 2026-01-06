<?php
namespace EasyVol\Controllers;

use EasyVol\Database;

/**
 * User Controller
 * 
 * Gestisce utenti, ruoli e permessi
 */
class UserController {
    private $db;
    private $config;
    private $phpmailerAvailable = null;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
 * Lista utenti con filtri
 */
public function index($filters = [], $page = 1, $perPage = 20) {
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filters['role_id'])) {
        $where[] = "u.role_id = ? ";
        $params[] = $filters['role_id'];
    }
    
    if (isset($filters['is_active']) && $filters['is_active'] !== '') {
        $where[] = "u.is_active = ? ";
        $params[] = (int)$filters['is_active'];
    }
    
    if (! empty($filters['search'])) {
        $where[] = "(u.username LIKE ? OR u. full_name LIKE ? OR u.email LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = implode(' AND ', $where);
    $offset = ($page - 1) * $perPage;
    
    // FIX: LIMIT e OFFSET direttamente nella query (non come parametri)
    $sql = "SELECT u.*, r.name as role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r. id
            WHERE $whereClause 
            ORDER BY u.username 
            LIMIT $perPage OFFSET $offset";
    
    return $this->db->fetchAll($sql, $params);
}
    
    /**
     * Ottieni statistiche utenti
     */
    public function getStats() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
                FROM users";
        
        return $this->db->fetchOne($sql);
    }
    
    /**
     * Ottieni singolo utente
     */
    public function get($id) {
        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }
        
        $sql = "SELECT u.*, r.name as role_name, r.description as role_description
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = :id";
        // Pass parameter directly to execute() to ensure proper binding
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Ottieni utente per username
     */
    public function getByUsername($username) {
        $stmt = $this->db->getConnection()->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Crea nuovo utente
     */
    public function create($data, $creatorId) {
        try {
            $this->db->beginTransaction();
            
            // Verifica username univoco
            if ($this->getByUsername($data['username'])) {
                return ['error' => 'Username già utilizzato'];
            }
            
            // Verifica email univoca
            $sql = "SELECT id FROM users WHERE email = ?";
            $existing = $this->db->fetchOne($sql, [$data['email']]);
            if ($existing) {
                return ['error' => 'Email già utilizzata'];
            }
            
            // Set default password if not provided
            $password = $data['password'] ?? \EasyVol\App::DEFAULT_PASSWORD;
            // Check if password is default to set must_change_password flag
            // This is done before hashing to determine if password change is required
            $mustChangePassword = !isset($data['password']) || $data['password'] === \EasyVol\App::DEFAULT_PASSWORD ? 1 : 0;
            
            $sql = "INSERT INTO users (
                username, password, email, full_name, member_id, 
                role_id, is_active, is_operations_center_user, must_change_password, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['username'],
                password_hash($password, PASSWORD_BCRYPT),
                $data['email'],
                $data['full_name'] ?? null,
                $data['member_id'] ?? null,
                $data['role_id'] ?? null,
                isset($data['is_active']) ? (int)$data['is_active'] : 1,
                isset($data['is_operations_center_user']) ? (int)$data['is_operations_center_user'] : 0,
                $mustChangePassword
            ];
            
            $this->db->execute($sql, $params);
            $userId = $this->db->lastInsertId();
            
            $this->logActivity($creatorId, 'users', 'create', $userId, 'Creato utente: ' . $data['username']);
            
            $this->db->commit();
            
            // Send welcome email with credentials (after commit, non-blocking)
            // If email fails, user creation still succeeds
            try {
                require_once __DIR__ . '/../Utils/EmailSender.php';
                $emailSender = new \EasyVol\Utils\EmailSender($this->config, $this->db);
                
                $userData = [
                    'email' => $data['email'],
                    'username' => $data['username']
                ];
                
                $emailSender->sendNewUserEmail($userData, $password);
            } catch (\Exception $e) {
                error_log("Failed to send new user email: " . $e->getMessage());
                // Don't fail user creation if email fails
            }
            
            return $userId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Errore creazione utente: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna utente
     */
    public function update($id, $data, $updaterId) {
        try {
            // Verifica username univoco (escludi utente corrente)
            $stmt = $this->db->getConnection()->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
            $stmt->execute([':username' => $data['username'], ':id' => (int)$id]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($existing) {
                return ['error' => 'Username già utilizzato'];
            }
            
            // Verifica email univoca (escludi utente corrente)
            $stmt = $this->db->getConnection()->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $stmt->execute([':email' => $data['email'], ':id' => (int)$id]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($existing) {
                return ['error' => 'Email già utilizzata'];
            }
            
            $sql = "UPDATE users SET
                username = :username, email = :email, full_name = :full_name, member_id = :member_id,
                role_id = :role_id, is_active = :is_active, is_operations_center_user = :is_operations_center_user, updated_at = NOW()
                WHERE id = :id";
            
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':full_name' => $data['full_name'] ?? null,
                ':member_id' => $data['member_id'] ?? null,
                ':role_id' => $data['role_id'] ?? null,
                ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                ':is_operations_center_user' => isset($data['is_operations_center_user']) ? (int)$data['is_operations_center_user'] : 0,
                ':id' => (int)$id
            ]);
            
            $this->logActivity($updaterId, 'users', 'update', $id, 'Aggiornato utente');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore aggiornamento utente: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cambia password utente
     */
    public function changePassword($id, $newPassword, $updaterId) {
        try {
            $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            
            $this->db->execute($sql, [$hashedPassword, $id]);
            
            $this->logActivity($updaterId, 'users', 'change_password', $id, 'Password modificata');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore cambio password: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina utente
     */
    public function delete($id, $deleterId) {
        try {
            // Non permettere eliminazione se stesso
            if ($id == $deleterId) {
                return ['error' => 'Non puoi eliminare il tuo stesso account'];
            }
            
            // Verifica se l'utente è l'unico admin
            $user = $this->get($id);
            
            // Check if user has admin role name
            if ($user['role_name'] === 'admin') {
                // Count active users with the same role
                $sql = "SELECT COUNT(*) as count FROM users WHERE role_id = ? AND is_active = 1";
                $adminCount = $this->db->fetchOne($sql, [$user['role_id']]);
                
                if ($adminCount && $adminCount['count'] <= 1) {
                    return ['error' => 'Non è possibile eliminare l\'ultimo amministratore attivo'];
                }
            }
            
            $sql = "DELETE FROM users WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            $this->logActivity($deleterId, 'users', 'delete', $id, 'Eliminato utente');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore eliminazione utente: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni tutti i ruoli
     */
    public function getRoles() {
        $sql = "SELECT * FROM roles ORDER BY name";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Ottieni ruolo singolo
     */
    public function getRole($id) {
        $sql = "SELECT * FROM roles WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Crea ruolo
     */
    public function createRole($data, $creatorId) {
        try {
            $sql = "INSERT INTO roles (name, description, created_at) VALUES (?, ?, NOW())";
            $this->db->execute($sql, [$data['name'], $data['description'] ?? null]);
            $roleId = $this->db->lastInsertId();
            
            $this->logActivity($creatorId, 'roles', 'create', $roleId, 'Creato ruolo: ' . $data['name']);
            
            return $roleId;
            
        } catch (\Exception $e) {
            error_log("Errore creazione ruolo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna ruolo
     */
    public function updateRole($id, $data, $updaterId) {
        try {
            $sql = "UPDATE roles SET name = ?, description = ?, updated_at = NOW() WHERE id = ?";
            $this->db->execute($sql, [$data['name'], $data['description'] ?? null, $id]);
            
            $this->logActivity($updaterId, 'roles', 'update', $id, 'Aggiornato ruolo');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore aggiornamento ruolo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni permessi per ruolo
     */
    public function getRolePermissions($roleId) {
        $sql = "SELECT p.* FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
                ORDER BY p.module, p.action";
        return $this->db->fetchAll($sql, [$roleId]);
    }
    
    /**
     * Ottieni tutti i permessi
     */
    public function getAllPermissions() {
        $sql = "SELECT * FROM permissions ORDER BY module, action";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Assegna permesso a ruolo
     */
    public function assignPermission($roleId, $permissionId, $assignerId) {
        try {
            $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
            $this->db->execute($sql, [$roleId, $permissionId]);
            
            $this->logActivity($assignerId, 'roles', 'assign_permission', $roleId, 
                "Assegnato permesso ID: $permissionId");
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore assegnazione permesso: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rimuovi permesso da ruolo
     */
    public function removePermission($roleId, $permissionId, $removerId) {
        try {
            $sql = "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?";
            $this->db->execute($sql, [$roleId, $permissionId]);
            
            $this->logActivity($removerId, 'roles', 'remove_permission', $roleId, 
                "Rimosso permesso ID: $permissionId");
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore rimozione permesso: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica se PHPMailer è disponibile (con cache)
     */
    private function isPhpMailerAvailable() {
        if ($this->phpmailerAvailable === null) {
            $this->phpmailerAvailable = class_exists('\PHPMailer\PHPMailer\PHPMailer');
        }
        return $this->phpmailerAvailable;
    }
    
    /**
     * Invia email di benvenuto
     */
    private function sendWelcomeEmail($username, $email, $fullName, $password) {
        try {
            // Only send if email is enabled
            if (!($this->config['email']['enabled'] ?? false)) {
                error_log("Email invio disabilitato nella configurazione");
                return false;
            }
            
            // Check if PHPMailer is available (cached check)
            if (!$this->isPhpMailerAvailable()) {
                error_log("PHPMailer non installato. Eseguire: composer install");
                return false;
            }
            
            $emailSender = new \EasyVol\Utils\EmailSender($this->config, $this->db);
            
            $data = [
                'app_name' => $this->config['app']['name'] ?? 'EasyVol',
                'full_name' => $fullName,
                'username' => $username,
                'password' => $password,
                'login_url' => ($this->config['app']['url'] ?? 'http://localhost') . '/login.php'
            ];
            
            return $emailSender->sendFromTemplate($email, 'user_welcome', $data);
            
        } catch (\Exception $e) {
            error_log("Errore invio email benvenuto: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset password per username o email
     */
    public function resetPassword($usernameOrEmail) {
        try {
            // Find user by username or email
            $sql = "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1";
            $user = $this->db->fetchOne($sql, [$usernameOrEmail, $usernameOrEmail]);
            
            if (!$user) {
                return ['error' => 'Utente non trovato'];
            }
            
            // Set default password
            $newPassword = \EasyVol\App::DEFAULT_PASSWORD;
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            
            // Update password and set must_change_password flag
            $sql = "UPDATE users SET password = ?, must_change_password = 1, updated_at = NOW() WHERE id = ?";
            $this->db->execute($sql, [$hashedPassword, $user['id']]);
            
            $this->logActivity($user['id'], 'users', 'password_reset', $user['id'], 'Password resettata');
            
            // Send email with new password (non-blocking)
            try {
                require_once __DIR__ . '/../Utils/EmailSender.php';
                $emailSender = new \EasyVol\Utils\EmailSender($this->config, $this->db);
                
                $userData = [
                    'email' => $user['email'],
                    'username' => $user['username']
                ];
                
                $emailSender->sendPasswordResetEmail($userData, $newPassword);
            } catch (\Exception $e) {
                error_log("Failed to send password reset email: " . $e->getMessage());
                // Don't fail password reset if email fails
            }
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore reset password: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Invia email di reset password
     */
    private function sendPasswordResetEmail($username, $email, $fullName, $password) {
        try {
            // Only send if email is enabled
            if (!($this->config['email']['enabled'] ?? false)) {
                error_log("Email invio disabilitato nella configurazione");
                return false;
            }
            
            // Check if PHPMailer is available (cached check)
            if (!$this->isPhpMailerAvailable()) {
                error_log("PHPMailer non installato. Eseguire: composer install");
                return false;
            }
            
            $emailSender = new \EasyVol\Utils\EmailSender($this->config, $this->db);
            
            $data = [
                'app_name' => $this->config['app']['name'] ?? 'EasyVol',
                'username' => $username,
                'password' => $password,
                'login_url' => ($this->config['app']['url'] ?? 'http://localhost') . '/login.php'
            ];
            
            return $emailSender->sendFromTemplate($email, 'password_reset', $data);
            
        } catch (\Exception $e) {
            error_log("Errore invio email reset password: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottiene utente per email
     */
    public function getByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ?";
        return $this->db->fetchOne($sql, [$email]);
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

