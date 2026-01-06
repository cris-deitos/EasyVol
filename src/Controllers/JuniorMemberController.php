<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Utils\FileUploader;
use EasyVol\Utils\ImageProcessor;
use EasyVol\Utils\PdfGenerator;
use EasyVol\Utils\PathHelper;

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
        $where = [];
        $params = [];
        
        // Filtro status
        if (!empty($filters['status'])) {
            $where[] = "jm.member_status = ?";
            $params[] = $filters['status'];
        }
        
        // Hide dismissed/lapsed filter
        if (isset($filters['hide_dismissed']) && $filters['hide_dismissed'] === '1') {
            $where[] = "jm.member_status NOT IN ('dimesso', 'decaduto', 'escluso')";
        }
        
        // Filtro ricerca
        if (!empty($filters['search'])) {
            $where[] = "(jm.first_name LIKE ? OR jm.last_name LIKE ? OR jm.registration_number LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Build WHERE clause
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Ensure pagination parameters are safe integers
        $page = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset = ($page - 1) * $perPage;
        
        // Determine sort order
        $sortBy = $filters['sort_by'] ?? 'alphabetical';
        if ($sortBy === 'registration_number') {
            // Junior members have registration numbers prefixed with "C-" (e.g., C-1, C-23)
            // SUBSTRING(jm.registration_number, 3) removes the "C-" prefix for numeric sorting
            $orderBy = "ORDER BY CAST(SUBSTRING(jm.registration_number, 3) AS UNSIGNED) ASC";
        } else {
            $orderBy = "ORDER BY jm.last_name, jm.first_name";
        }
        
        // Note: $whereClause is built from parameterized conditions above, safe from SQL injection
        // Get first guardian by priority: padre > madre > tutore
        $sql = "SELECT jm.*, 
                CONCAT(jm.first_name, ' ', jm.last_name) as full_name,
                (SELECT jmg.first_name FROM junior_member_guardians jmg 
                 WHERE jmg.junior_member_id = jm.id 
                 ORDER BY FIELD(jmg.guardian_type, 'padre', 'madre', 'tutore'), jmg.id 
                 LIMIT 1) as guardian_first_name,
                (SELECT jmg.last_name FROM junior_member_guardians jmg 
                 WHERE jmg.junior_member_id = jm.id 
                 ORDER BY FIELD(jmg.guardian_type, 'padre', 'madre', 'tutore'), jmg.id 
                 LIMIT 1) as guardian_last_name
                FROM junior_members jm
                $whereClause
                $orderBy
                LIMIT $perPage OFFSET $offset";
        
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
                WHERE jm.id = ?";
        
        $member = $this->db->fetchOne($sql, [$id]);
        
        if (!$member) {
            return false;
        }
        
        // Carica tutori/guardians
        $member['guardians'] = $this->getGuardians($id);
        
        // Populate guardian fields from first guardian for form editing
        // Note: The edit form only shows one guardian at a time. Additional guardians
        // can be managed through the "Genitori/Tutori" tab in the member view page.
        if (!empty($member['guardians'])) {
            $guardian = $member['guardians'][0];
            $member['guardian_last_name'] = $guardian['last_name'];
            $member['guardian_first_name'] = $guardian['first_name'];
            $member['guardian_tax_code'] = $guardian['tax_code'];
            $member['guardian_phone'] = $guardian['phone'];
            $member['guardian_email'] = $guardian['email'];
            // Map guardian_type back to guardian_relationship for form
            $member['guardian_relationship'] = $this->reverseMapGuardianType($guardian['guardian_type']);
        }
        
        // Carica contatti
        $member['contacts'] = $this->getContacts($id);
        
        // Carica indirizzi
        $member['addresses'] = $this->getAddresses($id);
        
        // Carica salute
        $member['health'] = $this->getHealth($id);
        
        // Carica provvedimenti/sanctions
        $member['sanctions'] = $this->getSanctions($id);
        
        // Carica quote associative/fees
        $member['fees'] = $this->getFees($id);
        
        // Carica note
        $member['notes'] = $this->getNotes($id);
        
        // Carica allegati/attachments
        $member['attachments'] = $this->getAttachments($id);
        
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
            
            // Force uppercase on text fields
            $data = $this->uppercaseTextFields($data);
            
            // Valida dati
            $this->validateJuniorMemberData($data);
            
            // Inserisci socio minorenne
            $sql = "INSERT INTO junior_members (
                registration_number, member_type, member_status,
                last_name, first_name, birth_date, birth_place, birth_province,
                tax_code, gender, nationality, registration_date,
                created_at, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            
            $params = [
                $data['registration_number'],
                $data['member_type'] ?? 'ordinario',
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
                $userId
            ];
            
            $this->db->execute($sql, $params);
            $memberId = $this->db->lastInsertId();
            
            // Inserisci tutore se fornito
            if (!empty($data['guardian_last_name']) && !empty($data['guardian_first_name'])) {
                // Map guardian_relationship to guardian_type enum values
                $guardianType = $this->mapGuardianRelationship($data['guardian_relationship'] ?? 'genitore');
                
                $guardianSql = "INSERT INTO junior_member_guardians (
                    junior_member_id, guardian_type,
                    last_name, first_name, tax_code, phone, email
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $guardianParams = [
                    $memberId,
                    $guardianType,
                    $data['guardian_last_name'],
                    $data['guardian_first_name'],
                    $data['guardian_tax_code'] ?? null,
                    $data['guardian_phone'] ?? null,
                    $data['guardian_email'] ?? null
                ];
                
                $this->db->execute($guardianSql, $guardianParams);
            }
            
            // Log attività
            $this->logActivity($userId, 'junior_member', 'create', $memberId, 'Creato nuovo socio minorenne: ' . $data['first_name'] . ' ' . $data['last_name']);
            
            $this->db->commit();
            
            return $memberId;
            
        } catch (\Exception $e) {
            if ($this->db->getConnection()->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Errore creazione socio minorenne: " . $e->getMessage());
            throw $e;
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
            
            // Force uppercase on text fields
            $data = $this->uppercaseTextFields($data);
            
            // Valida dati
            $this->validateJuniorMemberData($data, $id);
            
            $sql = "UPDATE junior_members SET
                member_type = ?, member_status = ?,
                last_name = ?, first_name = ?, birth_date = ?,
                birth_place = ?, birth_province = ?, tax_code = ?,
                gender = ?, nationality = ?,
                updated_at = NOW(), updated_by = ?
                WHERE id = ?";
            
            $params = [
                $data['member_type'] ?? 'ordinario',
                $data['member_status'] ?? 'attivo',
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
            
            // Aggiorna tutore se fornito
            if (!empty($data['guardian_last_name']) && !empty($data['guardian_first_name'])) {
                // Map guardian_relationship to guardian_type enum values
                $guardianType = $this->mapGuardianRelationship($data['guardian_relationship'] ?? 'genitore');
                
                // Cerca tutore esistente
                $guardianSql = "SELECT id FROM junior_member_guardians WHERE junior_member_id = ? LIMIT 1";
                $guardian = $this->db->fetchOne($guardianSql, [$id]);
                
                if ($guardian) {
                    // Aggiorna tutore esistente
                    $updateGuardianSql = "UPDATE junior_member_guardians SET
                        guardian_type = ?, last_name = ?, first_name = ?,
                        tax_code = ?, phone = ?, email = ?
                        WHERE id = ?";
                    
                    $guardianParams = [
                        $guardianType,
                        $data['guardian_last_name'],
                        $data['guardian_first_name'],
                        $data['guardian_tax_code'] ?? null,
                        $data['guardian_phone'] ?? null,
                        $data['guardian_email'] ?? null,
                        $guardian['id']
                    ];
                    
                    $this->db->execute($updateGuardianSql, $guardianParams);
                } else {
                    // Inserisci nuovo tutore
                    $insertGuardianSql = "INSERT INTO junior_member_guardians (
                        junior_member_id, guardian_type,
                        last_name, first_name, tax_code, phone, email
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    
                    $guardianParams = [
                        $id,
                        $guardianType,
                        $data['guardian_last_name'],
                        $data['guardian_first_name'],
                        $data['guardian_tax_code'] ?? null,
                        $data['guardian_phone'] ?? null,
                        $data['guardian_email'] ?? null
                    ];
                    
                    $this->db->execute($insertGuardianSql, $guardianParams);
                }
            }
            
            // Log attività
            $this->logActivity($userId, 'junior_member', 'update', $id, 'Aggiornato socio minorenne');
            
            $this->db->commit();
            
            return true;
            
        } catch (\Exception $e) {
            if ($this->db->getConnection()->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Errore aggiornamento socio minorenne: " . $e->getMessage());
            throw $e;
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
                    member_status = 'decaduto',
                    updated_at = NOW()
                    WHERE id = ?";
            
            $this->db->execute($sql, [$id]);
            
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
            
            // Convert absolute path to relative path for web display using PathHelper
            $relativePath = PathHelper::absoluteToRelative($result['path']);
            
            // Aggiorna database
            $sql = "UPDATE junior_members SET photo_path = ?, updated_at = NOW(), updated_by = ? WHERE id = ?";
            $this->db->execute($sql, [$relativePath, $userId, $memberId]);
            
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
        $sql = "SELECT * FROM junior_member_contacts WHERE junior_member_id = ? ORDER BY id";
        return $this->db->fetchAll($sql, [$memberId]);
    }
    
    /**
     * Ottieni indirizzi del socio minorenne
     * 
     * @param int $memberId ID socio
     * @return array
     */
    private function getAddresses($memberId) {
        $sql = "SELECT * FROM junior_member_addresses WHERE junior_member_id = ? ORDER BY id";
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
     * Genera matricola univoca per soci minorenni (cadetti)
     * 
     * @return string
     */
    private function generateRegistrationNumber() {
        // Get last registration number for junior members (prefixed with C-)
        $sql = "SELECT registration_number FROM junior_members 
                WHERE registration_number LIKE 'C-%'
                ORDER BY CAST(SUBSTRING(registration_number, 3) AS UNSIGNED) DESC 
                LIMIT 1";
        
        $last = $this->db->fetchOne($sql);
        
        if ($last && preg_match('/^C-(\d+)$/', $last['registration_number'], $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }
        
        // Return C- prefixed number without leading zeros (C-1, C-2, C-33, etc.)
        return 'C-' . $nextNumber;
    }
    
    /**
     * Valida dati socio minorenne
     * 
     * @param array $data Dati da validare
     * @param int|null $id ID socio (per update)
     * @throws \Exception Se validazione fallisce
     */
    private function validateJuniorMemberData($data, $id = null) {
        // Basic required fields
        $required = ['last_name', 'first_name', 'birth_date'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Campo obbligatorio mancante: $field");
            }
        }
        
        // Guardian fields required only for new entries (not edits)
        if ($id === null) {
            if (empty($data['guardian_last_name']) || empty($data['guardian_first_name'])) {
                throw new \Exception("Dati tutore obbligatori per nuovi soci minorenni");
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
     * Get guardians for junior member
     * 
     * @param int $juniorMemberId ID socio minorenne
     * @return array
     */
    private function getGuardians($juniorMemberId) {
        $sql = "SELECT * FROM junior_member_guardians WHERE junior_member_id = ?";
        return $this->db->fetchAll($sql, [$juniorMemberId]);
    }
    
    /**
     * Get health info for junior member
     * 
     * @param int $juniorMemberId ID socio minorenne
     * @return array
     */
    private function getHealth($juniorMemberId) {
        $sql = "SELECT * FROM junior_member_health WHERE junior_member_id = ?";
        return $this->db->fetchAll($sql, [$juniorMemberId]);
    }
    
    /**
     * Ottieni provvedimenti del socio minorenne
     * 
     * @param int $juniorMemberId ID socio minorenne
     * @return array
     */
    private function getSanctions($juniorMemberId) {
        $sql = "SELECT * FROM junior_member_sanctions WHERE junior_member_id = ? ORDER BY sanction_date DESC";
        return $this->db->fetchAll($sql, [$juniorMemberId]);
    }
    
    /**
     * Ottieni quote associative del socio minorenne
     * 
     * @param int $juniorMemberId ID socio minorenne
     * @return array
     */
    private function getFees($juniorMemberId) {
        $sql = "SELECT * FROM junior_member_fees WHERE junior_member_id = ? ORDER BY year DESC";
        return $this->db->fetchAll($sql, [$juniorMemberId]);
    }
    
    /**
     * Ottieni note del socio minorenne
     * 
     * @param int $juniorMemberId ID socio minorenne
     * @return array
     */
    private function getNotes($juniorMemberId) {
        $sql = "SELECT * FROM junior_member_notes WHERE junior_member_id = ? ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, [$juniorMemberId]);
    }
    
    /**
     * Ottieni allegati del socio minorenne
     * 
     * @param int $juniorMemberId ID socio minorenne
     * @return array
     */
    private function getAttachments($juniorMemberId) {
        $sql = "SELECT * FROM junior_member_attachments WHERE junior_member_id = ? ORDER BY uploaded_at DESC";
        return $this->db->fetchAll($sql, [$juniorMemberId]);
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
    
    /**
     * Force uppercase on text fields
     * 
     * @param array $data Junior member data
     * @return array Modified data with uppercase fields
     */
    private function uppercaseTextFields($data) {
        // Text fields that should be uppercase
        $textFields = [
            'last_name', 'first_name', 'birth_place', 'birth_province', 'nationality',
            'guardian_last_name', 'guardian_first_name', 'guardian_birth_place'
        ];
        
        foreach ($textFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = mb_strtoupper($data[$field], 'UTF-8');
            }
        }
        
        return $data;
    }
    
    /**
     * Map guardian relationship form value to database enum value
     * 
     * The form uses simplified values while the database has more specific enum values:
     * - 'genitore' (generic parent) maps to 'padre' as default, following Italian convention
     *   where the father is typically listed first in official documents
     * - 'tutore' (legal guardian) maps directly to 'tutore'
     * - 'altro' (other) maps to 'tutore' as the closest match, since the database
     *   only supports padre/madre/tutore
     * 
     * To track multiple guardians (e.g., both father and mother), add separate 
     * guardian entries via the "Genitori/Tutori" tab in the member view page.
     * 
     * @param string $relationship The relationship from form (genitore, tutore, altro)
     * @return string The guardian_type enum value (padre, madre, tutore)
     */
    private function mapGuardianRelationship($relationship) {
        // Map form values to database enum
        $mapping = [
            'genitore' => 'padre',  // Generic parent defaults to father
            'tutore' => 'tutore',
            'altro' => 'tutore'     // Other relationships default to legal guardian
        ];
        
        return $mapping[$relationship] ?? 'padre';
    }
    
    /**
     * Reverse map guardian_type enum value to form relationship value
     * 
     * @param string $guardianType The guardian_type from database (padre, madre, tutore)
     * @return string The relationship for form (genitore, tutore)
     */
    private function reverseMapGuardianType($guardianType) {
        // Map database enum back to form values
        $mapping = [
            'padre' => 'genitore',
            'madre' => 'genitore',
            'tutore' => 'tutore'
        ];
        
        return $mapping[$guardianType] ?? 'genitore';
    }
}
