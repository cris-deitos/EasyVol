<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Utils\FileUploader;
use EasyVol\Utils\ImageProcessor;
use EasyVol\Utils\PdfGenerator;

/**
 * Junior Member Controller
 * 
 * Gestisce tutte le operazioni CRUD per i soci minorenni
 */
class JuniorMemberController {
    private $db;
    private $config;
    
    /**
     * Constructor
     * 
     * @param Database $db Database instance
     * @param array $config Configuration
     */
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Lista soci minorenni con filtri e paginazione
     * 
     * @param array $filters Filtri
     * @param int $page Pagina corrente
     * @param int $perPage Elementi per pagina
     * @return array
     */
    public function index($filters = [], $page = 1, $perPage = 20) {
        $where = ["jm.deleted_at IS NULL"];
        $params = [];
        
        // Filtro status
        if (!empty($filters['status'])) {
            $where[] = "jm.member_status = ?";
            $params[] = $filters['status'];
        }
        
        // Filtro ricerca
        if (!empty($filters['search'])) {
            $where[] = "(jm.first_name LIKE ? OR jm.last_name LIKE ? OR jm.registration_number LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT jm.*, 
                CONCAT(jm.first_name, ' ', jm.last_name) as full_name
                FROM junior_members jm
                WHERE $whereClause
                ORDER BY jm.last_name, jm.first_name
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Ottieni singolo socio minorenne con tutti i dettagli
     * 
     * @param int $id ID socio minorenne
     * @return array|false
     */
    public function get($id) {
        $sql = "SELECT jm.*,
                CONCAT(jm.first_name, ' ', jm.last_name) as full_name,
                TIMESTAMPDIFF(YEAR, jm.birth_date, CURDATE()) as age
                FROM junior_members jm
                WHERE jm.id = ? AND jm.deleted_at IS NULL";
        
        $member = $this->db->fetchOne($sql, [$id]);
        
        if (!$member) {
            return false;
        }
        
        // Carica contatti
        $member['contacts'] = $this->getContacts($id);
        
        // Carica indirizzi
        $member['addresses'] = $this->getAddresses($id);
        
        // Carica gruppi
        $member['groups'] = $this->getGroups($id);
        
        return $member;
    }
    
    /**
     * Crea nuovo socio minorenne
     * 
     * @param array $data Dati socio
     * @param int $userId ID utente che crea
     * @return int|false ID socio creato o false
     */
    public function create($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            // Genera matricola se non fornita
            if (empty($data['registration_number'])) {
                $data['registration_number'] = $this->generateRegistrationNumber();
            }
            
            // Valida dati
            $this->validateJuniorMemberData($data);
            
            // Inserisci socio minorenne
            $sql = "INSERT INTO junior_members (
                registration_number, member_type, member_status,
                last_name, first_name, birth_date, birth_place, birth_province,
                tax_code, gender, nationality, registration_date,
                guardian_last_name, guardian_first_name, guardian_tax_code,
                guardian_phone, guardian_email, guardian_relationship,
                created_at, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            
            $params = [
                $data['registration_number'],
                $data['member_type'] ?? 'giovane',
                $data['member_status'] ?? 'attivo',
                $data['last_name'],
                $data['first_name'],
                $data['birth_date'],
                $data['birth_place'] ?? null,
                $data['birth_province'] ?? null,
                $data['tax_code'] ?? null,
                $data['gender'] ?? null,
                $data['nationality'] ?? 'Italiana',
                $data['registration_date'] ?? date('Y-m-d'),
                $data['guardian_last_name'],
                $data['guardian_first_name'],
                $data['guardian_tax_code'] ?? null,
                $data['guardian_phone'] ?? null,
                $data['guardian_email'] ?? null,
                $data['guardian_relationship'] ?? 'genitore',
                $userId
            ];
            
            $this->db->execute($sql, $params);
            $memberId = $this->db->lastInsertId();
            
            // Log attività
            $this->logActivity($userId, 'junior_member', 'create', $memberId, 'Creato nuovo socio minorenne: ' . $data['first_name'] . ' ' . $data['last_name']);
            
            $this->db->commit();
            
            return $memberId;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Errore creazione socio minorenne: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna socio minorenne
     * 
     * @param int $id ID socio
     * @param array $data Dati da aggiornare
     * @param int $userId ID utente che aggiorna
     * @return bool
     */
    public function update($id, $data, $userId) {
        try {
            $this->db->beginTransaction();
            
            // Valida dati
            $this->validateJuniorMemberData($data, $id);
            
            $sql = "UPDATE junior_members SET
                member_type = ?, member_status = ?,
                last_name = ?, first_name = ?, birth_date = ?,
                birth_place = ?, birth_province = ?, tax_code = ?,
                gender = ?, nationality = ?,
                guardian_last_name = ?, guardian_first_name = ?, guardian_tax_code = ?,
                guardian_phone = ?, guardian_email = ?, guardian_relationship = ?,
                updated_at = NOW(), updated_by = ?
                WHERE id = ?";
            
            $params = [
                $data['member_type'] ?? 'giovane',
                $data['member_status'] ?? 'attivo',
                $data['last_name'],
                $data['first_name'],
                $data['birth_date'],
                $data['birth_place'] ?? null,
                $data['birth_province'] ?? null,
                $data['tax_code'] ?? null,
                $data['gender'] ?? null,
                $data['nationality'] ?? 'Italiana',
                $data['guardian_last_name'],
                $data['guardian_first_name'],
                $data['guardian_tax_code'] ?? null,
                $data['guardian_phone'] ?? null,
                $data['guardian_email'] ?? null,
                $data['guardian_relationship'] ?? 'genitore',
                $userId,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            // Log attività
            $this->logActivity($userId, 'junior_member', 'update', $id, 'Aggiornato socio minorenne');
            
            $this->db->commit();
            
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Errore aggiornamento socio minorenne: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina socio minorenne (soft delete)
     * 
     * @param int $id ID socio
     * @param int $userId ID utente che elimina
     * @return bool
     */
    public function delete($id, $userId) {
        try {
            $sql = "UPDATE junior_members SET 
                    member_status = 'cancellato',
                    deleted_at = NOW(),
                    deleted_by = ?
                    WHERE id = ?";
            
            $this->db->execute($sql, [$userId, $id]);
            
            // Log attività
            $this->logActivity($userId, 'junior_member', 'delete', $id, 'Eliminato socio minorenne');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore eliminazione socio minorenne: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upload foto socio minorenne
     * 
     * @param int $memberId ID socio
     * @param array $file $_FILES element
     * @param int $userId ID utente
     * @return array|false
     */
    public function uploadPhoto($memberId, $file, $userId) {
        try {
            $uploader = new FileUploader(
                __DIR__ . '/../../uploads/junior_members/photos',
                FileUploader::getImageMimeTypes(),
                5242880 // 5MB
            );
            
            $result = $uploader->upload($file, (string)$memberId);
            
            if (!$result['success']) {
                return $result;
            }
            
            // Crea thumbnail
            $thumbPath = dirname($result['path']) . '/thumb_' . basename($result['path']);
            ImageProcessor::createThumbnail($result['path'], $thumbPath, 200);
            
            // Aggiorna database
            $sql = "UPDATE junior_members SET photo_path = ?, updated_at = NOW(), updated_by = ? WHERE id = ?";
            $this->db->execute($sql, [$result['path'], $userId, $memberId]);
            
            // Log attività
            $this->logActivity($userId, 'junior_member', 'upload_photo', $memberId, 'Caricata foto socio minorenne');
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Errore upload foto: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Genera PDF tesserino socio minorenne
     * 
     * @param int $memberId ID socio
     * @return string|false Path PDF o false
     */
    public function generateMemberCard($memberId) {
        try {
            $member = $this->get($memberId);
            if (!$member) {
                return false;
            }
            
            $pdfGen = new PdfGenerator($this->config);
            $filename = 'tesserino_junior_' . $member['registration_number'] . '.pdf';
            
            return $pdfGen->generateJuniorMemberCard($member, 'F');
            
        } catch (\Exception $e) {
            error_log("Errore generazione tesserino: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni contatti del socio minorenne
     * 
     * @param int $memberId ID socio
     * @return array
     */
    private function getContacts($memberId) {
        $sql = "SELECT * FROM junior_member_contacts WHERE junior_member_id = ? ORDER BY is_primary DESC, id";
        return $this->db->fetchAll($sql, [$memberId]);
    }
    
    /**
     * Ottieni indirizzi del socio minorenne
     * 
     * @param int $memberId ID socio
     * @return array
     */
    private function getAddresses($memberId) {
        $sql = "SELECT * FROM junior_member_addresses WHERE junior_member_id = ? ORDER BY is_primary DESC, id";
        return $this->db->fetchAll($sql, [$memberId]);
    }
    
    /**
     * Ottieni gruppi del socio minorenne
     * 
     * @param int $memberId ID socio
     * @return array
     */
    private function getGroups($memberId) {
        $sql = "SELECT g.*, jmg.joined_date, jmg.role
                FROM junior_member_groups jmg
                JOIN junior_groups g ON jmg.group_id = g.id
                WHERE jmg.junior_member_id = ?
                ORDER BY jmg.joined_date DESC";
        return $this->db->fetchAll($sql, [$memberId]);
    }
    
    /**
     * Genera matricola univoca per soci minorenni
     * 
     * @return string
     */
    private function generateRegistrationNumber() {
        // Get last registration number for junior members (prefixed with J)
        $sql = "SELECT registration_number FROM junior_members 
                WHERE registration_number LIKE 'J%'
                ORDER BY id DESC 
                LIMIT 1";
        
        $last = $this->db->fetchOne($sql);
        
        if ($last && preg_match('/^J(\d+)$/', $last['registration_number'], $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }
        
        return 'J' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
    
    /**
     * Valida dati socio minorenne
     * 
     * @param array $data Dati da validare
     * @param int|null $id ID socio (per update)
     * @throws \Exception Se validazione fallisce
     */
    private function validateJuniorMemberData($data, $id = null) {
        $required = ['last_name', 'first_name', 'birth_date', 'guardian_last_name', 'guardian_first_name'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Campo obbligatorio mancante: $field");
            }
        }
        
        // Verifica che sia effettivamente minorenne
        $birthDate = new \DateTime($data['birth_date']);
        $today = new \DateTime();
        $age = $today->diff($birthDate)->y;
        
        if ($age >= 18) {
            throw new \Exception("Il socio deve essere minorenne (età < 18 anni)");
        }
        
        // Valida codice fiscale se presente
        if (!empty($data['tax_code'])) {
            $taxCode = strtoupper($data['tax_code']);
            if (strlen($taxCode) !== 16) {
                throw new \Exception("Codice fiscale non valido");
            }
            
            // Verifica unicità
            $sql = "SELECT id FROM junior_members WHERE tax_code = ?";
            $params = [$taxCode];
            
            if ($id !== null) {
                $sql .= " AND id != ?";
                $params[] = $id;
            }
            
            $existing = $this->db->fetchOne($sql, $params);
            if ($existing) {
                throw new \Exception("Codice fiscale già esistente");
            }
        }
        
        // Valida codice fiscale tutore se presente
        if (!empty($data['guardian_tax_code'])) {
            $taxCode = strtoupper($data['guardian_tax_code']);
            if (strlen($taxCode) !== 16) {
                throw new \Exception("Codice fiscale tutore non valido");
            }
        }
    }
    
    /**
     * Registra attività nel log
     * 
     * @param int $userId ID utente
     * @param string $module Modulo
     * @param string $action Azione
     * @param int $recordId ID record
     * @param string $details Dettagli
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
