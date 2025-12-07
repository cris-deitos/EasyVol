<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Models\Member;
use EasyVol\Utils\FileUploader;
use EasyVol\Utils\ImageProcessor;
use EasyVol\Utils\PdfGenerator;

/**
 * Member Controller
 * 
 * Gestisce tutte le operazioni CRUD per i soci maggiorenni
 */
class MemberController {
    private $db;
    private $memberModel;
    private $config;
    
    /**
     * Constructor
     * 
     * @param Database $db Database instance
     * @param array $config Configuration
     */
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->memberModel = new Member($db);
        $this->config = $config;
    }
    
    /**
     * Lista soci con filtri e paginazione
     * 
     * @param array $filters Filtri
     * @param int $page Pagina corrente
     * @param int $perPage Elementi per pagina
     * @return array
     */
    public function index($filters = [], $page = 1, $perPage = 20) {
        return $this->memberModel->getAll($filters, $page, $perPage);
    }
    
    /**
     * Ottieni singolo socio con tutti i dettagli
     * 
     * @param int $id ID socio
     * @return array|false
     */
    public function get($id) {
        return $this->memberModel->getById($id);
    }
    
    /**
     * Crea nuovo socio
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
            $this->validateMemberData($data);
            
            // Inserisci socio
            $sql = "INSERT INTO members (
                registration_number, member_type, member_status, volunteer_status,
                last_name, first_name, birth_date, birth_place, birth_province,
                tax_code, gender, nationality, registration_date,
                created_at, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            
            $params = [
                $data['registration_number'],
                $data['member_type'] ?? 'ordinario',
                $data['member_status'] ?? 'attivo',
                $data['volunteer_status'] ?? 'aspirante',
                $data['last_name'],
                $data['first_name'],
                $data['birth_date'],
                $data['birth_place'] ?? null,
                $data['birth_province'] ?? null,
                $data['tax_code'] ?? null,
                $data['gender'] ?? null,
                $data['nationality'] ?? 'Italiana',
                $data['registration_date'] ?? date('Y-m-d'),
                $userId
            ];
            
            $this->db->execute($sql, $params);
            $memberId = $this->db->lastInsertId();
            
            // Log attività
            $this->logActivity($userId, 'member', 'create', $memberId, 'Creato nuovo socio: ' . $data['first_name'] . ' ' . $data['last_name']);
            
            $this->db->commit();
            
            return $memberId;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Errore creazione socio: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna socio
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
            $this->validateMemberData($data, $id);
            
            $sql = "UPDATE members SET
                member_type = ?, member_status = ?, volunteer_status = ?,
                last_name = ?, first_name = ?, birth_date = ?,
                birth_place = ?, birth_province = ?, tax_code = ?,
                gender = ?, nationality = ?,
                updated_at = NOW(), updated_by = ?
                WHERE id = ?";
            
            $params = [
                $data['member_type'] ?? 'ordinario',
                $data['member_status'] ?? 'attivo',
                $data['volunteer_status'] ?? 'aspirante',
                $data['last_name'],
                $data['first_name'],
                $data['birth_date'],
                $data['birth_place'] ?? null,
                $data['birth_province'] ?? null,
                $data['tax_code'] ?? null,
                $data['gender'] ?? null,
                $data['nationality'] ?? 'Italiana',
                $userId,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            // Log attività
            $this->logActivity($userId, 'member', 'update', $id, 'Aggiornato socio');
            
            $this->db->commit();
            
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Errore aggiornamento socio: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina socio (soft delete)
     * 
     * @param int $id ID socio
     * @param int $userId ID utente che elimina
     * @return bool
     */
    public function delete($id, $userId) {
        try {
            $sql = "UPDATE members SET 
                    member_status = 'dimesso'
                    WHERE id = ?";
            
            $this->db->execute($sql, [$id]);
            
            // Log attività
            $this->logActivity($userId, 'member', 'delete', $id, 'Eliminato socio');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore eliminazione socio: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upload foto socio
     * 
     * @param int $memberId ID socio
     * @param array $file $_FILES element
     * @param int $userId ID utente
     * @return array|false
     */
    public function uploadPhoto($memberId, $file, $userId) {
        try {
            $uploader = new FileUploader(
                __DIR__ . '/../../uploads/members/photos',
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
            $sql = "UPDATE members SET photo_path = ?, updated_at = NOW(), updated_by = ? WHERE id = ?";
            $this->db->execute($sql, [$result['path'], $userId, $memberId]);
            
            // Log attività
            $this->logActivity($userId, 'member', 'upload_photo', $memberId, 'Caricata foto socio');
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Errore upload foto: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Genera PDF tesserino socio
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
            $filename = 'tesserino_' . $member['registration_number'] . '.pdf';
            
            return $pdfGen->generateMemberCard($member, 'F');
            
        } catch (\Exception $e) {
            error_log("Errore generazione tesserino: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Genera matricola univoca
     * 
     * @return string
     */
    private function generateRegistrationNumber() {
        // Get last registration number
        $sql = "SELECT registration_number FROM members 
                WHERE registration_number REGEXP '^[0-9]+$' 
                ORDER BY CAST(registration_number AS UNSIGNED) DESC 
                LIMIT 1";
        
        $last = $this->db->fetchOne($sql);
        
        if ($last && is_numeric($last['registration_number'])) {
            $nextNumber = intval($last['registration_number']) + 1;
        } else {
            $nextNumber = 1;
        }
        
        return str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Valida dati socio
     * 
     * @param array $data Dati da validare
     * @param int|null $id ID socio (per update)
     * @throws \Exception Se validazione fallisce
     */
    private function validateMemberData($data, $id = null) {
        $required = ['last_name', 'first_name', 'birth_date'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Campo obbligatorio mancante: $field");
            }
        }
        
        // Valida codice fiscale se presente
        if (!empty($data['tax_code'])) {
            $taxCode = strtoupper($data['tax_code']);
            if (strlen($taxCode) !== 16) {
                throw new \Exception("Codice fiscale non valido");
            }
            
            // Verifica unicità
            $sql = "SELECT id FROM members WHERE tax_code = ?";
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
