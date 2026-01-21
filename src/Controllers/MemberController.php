<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Models\Member;
use EasyVol\Utils\FileUploader;
use EasyVol\Utils\ImageProcessor;
use EasyVol\Utils\PdfGenerator;
use EasyVol\Utils\PathHelper;
use EasyVol\Utils\FiscalCodeValidator;

/**
 * Member Controller
 * 
 * Gestisce tutte le operazioni CRUD per i soci maggiorenni
 */
class MemberController {
    private $db;
    private $memberModel;
    private $config;
    private $schedulerSyncController = null;
    
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
     * Ottieni un'istanza del SchedulerSyncController (lazy loading)
     * Helper method per ridurre duplicazione del codice
     * 
     * @return SchedulerSyncController
     */
    private function getSchedulerSyncController() {
        if ($this->schedulerSyncController === null) {
            $this->schedulerSyncController = new SchedulerSyncController($this->db, $this->config);
        }
        return $this->schedulerSyncController;
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
     * Conta totale soci con filtri
     * 
     * @param array $filters Filtri
     * @return int
     */
    public function count($filters = []) {
        return $this->memberModel->getCount($filters);
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
            
            // Force uppercase on text fields
            $data = $this->uppercaseTextFields($data);
            
            // Valida dati
            $this->validateMemberData($data);
            
            // Inserisci socio
            $sql = "INSERT INTO members (
                registration_number, member_type, member_status, volunteer_status,
                last_name, first_name, birth_date, birth_place, birth_province,
                tax_code, gender, nationality, worker_type, education_level, registration_date,
                corso_base_completato, corso_base_anno,
                created_at, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            
            $params = [
                $data['registration_number'],
                $data['member_type'] ?? 'ordinario',
                $data['member_status'] ?? 'attivo',
                $data['volunteer_status'] ?? 'in_formazione',
                $data['last_name'],
                $data['first_name'],
                $data['birth_date'],
                $data['birth_place'] ?? null,
                $data['birth_province'] ?? null,
                $data['tax_code'] ?? null,
                $data['gender'] ?? null,
                $data['nationality'] ?? 'Italiana',
                $data['worker_type'] ?? null,
                $data['education_level'] ?? null,
                $data['registration_date'] ?? date('Y-m-d'),
                $data['corso_base_completato'] ?? 0,
                $data['corso_base_anno'] ?? null,
                $userId
            ];
            
            $this->db->execute($sql, $params);
            $memberId = $this->db->lastInsertId();
            
            // Se corso base completato, aggiungi corso al registro del socio
            if (!empty($data['corso_base_completato']) && !empty($data['corso_base_anno'])) {
                $this->addCorsoBaseToMemberCourses($memberId, $data['corso_base_anno'], $userId);
            }
            
            // Log attività
            $this->logActivity($userId, 'member', 'create', $memberId, 'Creato nuovo socio: ' . $data['first_name'] . ' ' . $data['last_name']);
            
            $this->db->commit();
            
            return $memberId;
            
        } catch (\Exception $e) {
            if ($this->db->getConnection()->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Errore creazione socio: " . $e->getMessage());
            throw $e;
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
            
            // Ottieni lo stato precedente del membro
            $previousMember = $this->get($id);
            $previousStatus = $previousMember ? $previousMember['member_status'] : null;
            $newStatus = $data['member_status'] ?? 'attivo';
            
            // Force uppercase on text fields
            $data = $this->uppercaseTextFields($data);
            
            // Valida dati
            $this->validateMemberData($data, $id);
            
            $sql = "UPDATE members SET
                member_type = ?, member_status = ?, volunteer_status = ?,
                last_name = ?, first_name = ?, birth_date = ?,
                birth_place = ?, birth_province = ?, tax_code = ?,
                gender = ?, nationality = ?, worker_type = ?, education_level = ?,
                corso_base_completato = ?, corso_base_anno = ?,
                updated_at = NOW(), updated_by = ?
                WHERE id = ?";
            
            $params = [
                $data['member_type'] ?? 'ordinario',
                $newStatus,
                $data['volunteer_status'] ?? 'in_formazione',
                $data['last_name'],
                $data['first_name'],
                $data['birth_date'],
                $data['birth_place'] ?? null,
                $data['birth_province'] ?? null,
                $data['tax_code'] ?? null,
                $data['gender'] ?? null,
                $data['nationality'] ?? 'Italiana',
                $data['worker_type'] ?? null,
                $data['education_level'] ?? null,
                $data['corso_base_completato'] ?? 0,
                $data['corso_base_anno'] ?? null,
                $userId,
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            // Se corso base completato, aggiungi/aggiorna corso nel registro del socio
            if (!empty($data['corso_base_completato']) && !empty($data['corso_base_anno'])) {
                $this->addCorsoBaseToMemberCourses($id, $data['corso_base_anno'], $userId);
            }
            
            // Gestisci sincronizzazione scadenze in base allo stato del membro
            if ($previousStatus !== $newStatus) {
                $syncController = $this->getSchedulerSyncController();
                
                if ($newStatus === 'attivo') {
                    // Membro diventato attivo: sincronizza tutte le scadenze
                    $syncController->syncAllMemberExpiries($id);
                } else {
                    // Membro diventato non attivo: rimuovi tutte le scadenze dallo scadenziario
                    $syncController->removeAllMemberSchedulerItems($id);
                }
            }
            
            // Log attività
            $this->logActivity($userId, 'member', 'update', $id, 'Aggiornato socio');
            
            $this->db->commit();
            
            return true;
            
        } catch (\Exception $e) {
            if ($this->db->getConnection()->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Errore aggiornamento socio: " . $e->getMessage());
            throw $e;
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
            
            // Convert absolute path to relative path for web display using PathHelper
            $relativePath = PathHelper::absoluteToRelative($result['path']);
            
            // Aggiorna database
            $sql = "UPDATE members SET photo_path = ?, updated_at = NOW(), updated_by = ? WHERE id = ?";
            $this->db->execute($sql, [$relativePath, $userId, $memberId]);
            
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
        // Get last registration number (numeric only, no leading zeros)
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
        
        // Return plain number without leading zeros (1, 2, 3, 20, 33, 101, etc.)
        return (string)$nextNumber;
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
     * Aggiungi corso base A1 al registro corsi del socio
     * Questo metodo crea o aggiorna un'entry nella tabella member_courses
     * per il corso base di protezione civile
     * 
     * @param int $memberId ID del socio
     * @param int $anno Anno di completamento del corso
     * @param int $userId ID utente che esegue l'operazione
     * @return bool True se successo, false altrimenti
     */
    private function addCorsoBaseToMemberCourses($memberId, $anno, $userId) {
        try {
            // Validate year using Member model constants
            $maxYear = date('Y') + Member::MAX_COURSE_YEAR_OFFSET;
            if ($anno < Member::MIN_COURSE_YEAR || $anno > $maxYear) {
                error_log("Invalid corso base year: $anno for member $memberId");
                return false;
            }
            
            // Nome e tipo corso secondo le costanti del modello Member
            $courseName = Member::CORSO_BASE_A1_NAME;
            $courseType = Member::CORSO_BASE_A1_CODE;
            
            // Data di completamento: primo giorno dell'anno specificato
            $completionDate = sprintf('%04d-01-01', $anno);
            
            // Corso base non ha scadenza
            $expiryDate = null;
            
            // Verifica se esiste già un corso base per questo socio
            $sql = "SELECT id FROM member_courses 
                    WHERE member_id = ? AND course_type = ?";
            $existing = $this->db->fetchOne($sql, [$memberId, $courseType]);
            
            if ($existing) {
                // Aggiorna record esistente
                $sql = "UPDATE member_courses SET
                        course_name = ?,
                        completion_date = ?,
                        expiry_date = ?
                        WHERE id = ?";
                
                $this->db->execute($sql, [
                    $courseName,
                    $completionDate,
                    $expiryDate,
                    $existing['id']
                ]);
                
                $this->logActivity($userId, 'member', 'update_corso_base', $existing['id'], 
                    "Aggiornato corso base A1 anno $anno per socio ID: $memberId");
            } else {
                // Crea nuovo record
                $sql = "INSERT INTO member_courses 
                        (member_id, course_name, course_type, completion_date, expiry_date)
                        VALUES (?, ?, ?, ?, ?)";
                
                $this->db->execute($sql, [
                    $memberId,
                    $courseName,
                    $courseType,
                    $completionDate,
                    $expiryDate
                ]);
                
                $courseId = $this->db->lastInsertId();
                $this->logActivity($userId, 'member', 'add_corso_base', $courseId, 
                    "Aggiunto corso base A1 anno $anno per socio ID: $memberId");
            }
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore aggiunta corso base a member_courses: " . $e->getMessage());
            return false;
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
    
    /**
     * Force uppercase on text fields
     * 
     * @param array $data Member data
     * @return array Modified data with uppercase fields
     */
    private function uppercaseTextFields($data) {
        // Text fields that should be uppercase
        $textFields = [
            'last_name', 'first_name', 'birth_place', 'birth_province', 'nationality'
        ];
        
        foreach ($textFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = mb_strtoupper($data[$field], 'UTF-8');
            }
        }
        
        return $data;
    }
    
    /**
     * Get all anomalies for members
     * 
     * @return array Array of anomalies grouped by type
     */
    public function getAnomalies() {
        $anomalies = [
            'no_mobile' => [],
            'no_email' => [],
            'invalid_fiscal_code' => [],
            'no_health_surveillance' => [],
            'expired_health_surveillance' => [],
            'expired_licenses' => [],
            'expired_courses' => []
        ];
        
        // Get all active members with their related data
        $sql = "SELECT 
                    m.id, 
                    m.registration_number,
                    m.first_name, 
                    m.last_name,
                    m.tax_code,
                    m.birth_date,
                    m.gender,
                    m.member_status,
                    -- Get mobile contact
                    (SELECT mc.value FROM member_contacts mc 
                     WHERE mc.member_id = m.id AND mc.contact_type = 'cellulare' 
                     LIMIT 1) as mobile,
                    -- Get email contact
                    (SELECT mc.value FROM member_contacts mc 
                     WHERE mc.member_id = m.id AND mc.contact_type = 'email' 
                     LIMIT 1) as email,
                    -- Get latest health surveillance
                    (SELECT mhs.expiry_date FROM member_health_surveillance mhs 
                     WHERE mhs.member_id = m.id 
                     ORDER BY mhs.expiry_date DESC 
                     LIMIT 1) as health_surveillance_expiry
                FROM members m
                WHERE m.member_status = 'attivo'
                ORDER BY m.last_name, m.first_name";
        
        $members = $this->db->fetchAll($sql);
        
        foreach ($members as $member) {
            $memberInfo = [
                'id' => $member['id'],
                'registration_number' => $member['registration_number'],
                'name' => $member['first_name'] . ' ' . $member['last_name'],
                'member_status' => $member['member_status']
            ];
            
            // Check for missing mobile
            if (empty($member['mobile'])) {
                $anomalies['no_mobile'][] = $memberInfo;
            }
            
            // Check for missing email
            if (empty($member['email'])) {
                $anomalies['no_email'][] = $memberInfo;
            }
            
            // Check fiscal code validity
            if (!empty($member['tax_code'])) {
                $personalData = [
                    'birth_date' => $member['birth_date'],
                    'gender' => $member['gender']
                ];
                
                $verification = FiscalCodeValidator::verifyAgainstPersonalData(
                    $member['tax_code'], 
                    $personalData
                );
                
                if (!$verification['valid']) {
                    $memberInfo['fiscal_code'] = $member['tax_code'];
                    $memberInfo['errors'] = implode('; ', $verification['errors']);
                    $anomalies['invalid_fiscal_code'][] = $memberInfo;
                }
            }
            
            // Check for missing health surveillance
            if (empty($member['health_surveillance_expiry'])) {
                $anomalies['no_health_surveillance'][] = $memberInfo;
            } else {
                // Check for expired health surveillance
                $expiryDate = new \DateTime($member['health_surveillance_expiry']);
                $today = new \DateTime();
                
                if ($expiryDate < $today) {
                    $memberInfo['expiry_date'] = $member['health_surveillance_expiry'];
                    $anomalies['expired_health_surveillance'][] = $memberInfo;
                }
            }
        }
        
        // Check for expired licenses
        $licenseSql = "SELECT 
                        m.id,
                        m.registration_number,
                        m.first_name,
                        m.last_name,
                        ml.license_type,
                        ml.expiry_date
                    FROM members m
                    INNER JOIN member_licenses ml ON ml.member_id = m.id
                    WHERE m.member_status = 'attivo'
                        AND ml.expiry_date IS NOT NULL
                        AND ml.expiry_date < CURDATE()
                    ORDER BY m.last_name, m.first_name, ml.license_type";
        
        $expiredLicenses = $this->db->fetchAll($licenseSql);
        foreach ($expiredLicenses as $license) {
            $anomalies['expired_licenses'][] = [
                'id' => $license['id'],
                'registration_number' => $license['registration_number'],
                'name' => $license['first_name'] . ' ' . $license['last_name'],
                'license_type' => $license['license_type'],
                'expiry_date' => $license['expiry_date']
            ];
        }
        
        // Check for expired courses
        $courseSql = "SELECT 
                        m.id,
                        m.registration_number,
                        m.first_name,
                        m.last_name,
                        mc.course_name,
                        mc.expiry_date
                    FROM members m
                    INNER JOIN member_courses mc ON mc.member_id = m.id
                    WHERE m.member_status = 'attivo'
                        AND mc.expiry_date IS NOT NULL
                        AND mc.expiry_date < CURDATE()
                    ORDER BY m.last_name, m.first_name, mc.course_name";
        
        $expiredCourses = $this->db->fetchAll($courseSql);
        foreach ($expiredCourses as $course) {
            $anomalies['expired_courses'][] = [
                'id' => $course['id'],
                'registration_number' => $course['registration_number'],
                'name' => $course['first_name'] . ' ' . $course['last_name'],
                'course_name' => $course['course_name'],
                'expiry_date' => $course['expiry_date']
            ];
        }
        
        return $anomalies;
    }
    
    /**
     * Build WHERE clause and parameters array for navigation queries
     * Helper method to reduce code duplication
     * 
     * @param array $filters Filters applied
     * @return array [whereClause, params]
     */
    private function buildNavigationFilters($filters) {
        $where = [];
        $params = [];
        
        // Apply same filters as index method
        if (!empty($filters['status'])) {
            $where[] = "m.member_status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['volunteer_status'])) {
            $where[] = "m.volunteer_status = ?";
            $params[] = $filters['volunteer_status'];
        }
        
        if (!empty($filters['role'])) {
            $where[] = "EXISTS (SELECT 1 FROM member_roles mr WHERE mr.member_id = m.id AND mr.role_name = ?)";
            $params[] = $filters['role'];
        }
        
        if (isset($filters['hide_dismissed']) && $filters['hide_dismissed'] === '1') {
            $where[] = "m.member_status NOT IN ('dimesso', 'decaduto', 'escluso')";
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(m.first_name LIKE ? OR m.last_name LIKE ? OR m.registration_number LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        return [$whereClause, $params];
    }
    
    /**
     * Get next member ID based on current filters and sort order
     * 
     * @param int $currentId Current member ID
     * @param array $filters Filters applied
     * @return int|null Next member ID or null if none
     */
    public function getNextMemberId($currentId, $filters = []) {
        // Get current member details first
        $currentMember = $this->get($currentId);
        if (!$currentMember) {
            return null;
        }
        
        [$whereClause, $params] = $this->buildNavigationFilters($filters);
        
        // Determine sort order and comparison logic
        $sortBy = $filters['sort_by'] ?? 'registration_number';
        if ($sortBy === 'alphabetical') {
            // Next by name
            $orderBy = "ORDER BY m.last_name ASC, m.first_name ASC";
            $comparison = "(m.last_name > ? OR (m.last_name = ? AND m.first_name > ?) OR (m.last_name = ? AND m.first_name = ? AND m.id > ?))";
            $params[] = $currentMember['last_name'];
            $params[] = $currentMember['last_name'];
            $params[] = $currentMember['first_name'];
            $params[] = $currentMember['last_name'];
            $params[] = $currentMember['first_name'];
            $params[] = $currentId;
        } else {
            // Next by registration number
            $orderBy = "ORDER BY CAST(m.registration_number AS UNSIGNED) ASC, m.id ASC";
            $comparison = "(CAST(m.registration_number AS UNSIGNED) > CAST(? AS UNSIGNED) OR (m.registration_number = ? AND m.id > ?))";
            $params[] = $currentMember['registration_number'];
            $params[] = $currentMember['registration_number'];
            $params[] = $currentId;
        }
        
        if (!empty($whereClause)) {
            $whereClause .= " AND " . $comparison;
        } else {
            $whereClause = "WHERE " . $comparison;
        }
        
        $sql = "SELECT m.id FROM members m $whereClause $orderBy LIMIT 1";
        $result = $this->db->fetchOne($sql, $params);
        
        return $result ? $result['id'] : null;
    }
    
    /**
     * Get previous member ID based on current filters and sort order
     * 
     * @param int $currentId Current member ID
     * @param array $filters Filters applied
     * @return int|null Previous member ID or null if none
     */
    public function getPreviousMemberId($currentId, $filters = []) {
        // Get current member details first
        $currentMember = $this->get($currentId);
        if (!$currentMember) {
            return null;
        }
        
        [$whereClause, $params] = $this->buildNavigationFilters($filters);
        
        // Determine sort order and comparison logic (DESC for previous)
        $sortBy = $filters['sort_by'] ?? 'registration_number';
        if ($sortBy === 'alphabetical') {
            // Previous by name
            $orderBy = "ORDER BY m.last_name DESC, m.first_name DESC";
            $comparison = "(m.last_name < ? OR (m.last_name = ? AND m.first_name < ?) OR (m.last_name = ? AND m.first_name = ? AND m.id < ?))";
            $params[] = $currentMember['last_name'];
            $params[] = $currentMember['last_name'];
            $params[] = $currentMember['first_name'];
            $params[] = $currentMember['last_name'];
            $params[] = $currentMember['first_name'];
            $params[] = $currentId;
        } else {
            // Previous by registration number
            $orderBy = "ORDER BY CAST(m.registration_number AS UNSIGNED) DESC, m.id DESC";
            $comparison = "(CAST(m.registration_number AS UNSIGNED) < CAST(? AS UNSIGNED) OR (m.registration_number = ? AND m.id < ?))";
            $params[] = $currentMember['registration_number'];
            $params[] = $currentMember['registration_number'];
            $params[] = $currentId;
        }
        
        if (!empty($whereClause)) {
            $whereClause .= " AND " . $comparison;
        } else {
            $whereClause = "WHERE " . $comparison;
        }
        
        $sql = "SELECT m.id FROM members m $whereClause $orderBy LIMIT 1";
        $result = $this->db->fetchOne($sql, $params);
        
        return $result ? $result['id'] : null;
    }
}
