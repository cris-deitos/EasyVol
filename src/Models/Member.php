<?php
namespace EasyVol\Models;

use EasyVol\Database;

/**
 * Member Model
 * Handles all database operations for members (adult members)
 */
class Member {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Ottieni un'istanza del SchedulerSyncController
     * Helper method per ridurre duplicazione del codice
     */
    private function getSyncController() {
        $app = \EasyVol\App::getInstance();
        $config = $app->getConfig();
        return new \EasyVol\Controllers\SchedulerSyncController($this->db, $config);
    }
    
    /**
     * Check if an exception is due to a missing database table
     * Helper method to reduce code duplication
     * 
     * @param \Exception $e The exception to check
     * @return bool True if the exception is due to a missing table
     */
    private function isMissingTableException(\Exception $e) {
        $message = $e->getMessage();
        // Check for SQLSTATE 42S02 (Base table or view not found) or text patterns
        return strpos($message, "42S02") !== false ||
               strpos($message, "Base table or view not found") !== false || 
               strpos($message, "doesn't exist") !== false ||
               strpos($message, "Table") !== false && strpos($message, "doesn't exist") !== false;
    }
    
    /**
     * Get all members with optional filters
     */
    public function getAll($filters = [], $page = 1, $perPage = 20) {
        $sql = "SELECT m.*, 
                COUNT(DISTINCT mc.id) as contact_count,
                COUNT(DISTINCT ma.id) as address_count
                FROM members m
                LEFT JOIN member_contacts mc ON m.id = mc.member_id
                LEFT JOIN member_addresses ma ON m.id = ma.member_id";
        
        $params = [];
        
        // Add LEFT JOIN for role filter if needed (with proper parameter binding)
        if (!empty($filters['role'])) {
            $sql .= " INNER JOIN member_roles mr ON m.id = mr.member_id";
            // We'll add the role filter in WHERE clause for clarity and security
        }
        
        $sql .= " WHERE 1=1";
        
        // Add role filter in WHERE clause
        if (!empty($filters['role'])) {
            $sql .= " AND mr.role_name = ?";
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND m.member_status = ?";
            $params[] = $filters['status'];
        }
        
        // Hide dismissed/lapsed filter
        if (isset($filters['hide_dismissed']) && $filters['hide_dismissed'] === '1') {
            $sql .= " AND m.member_status NOT IN ('dimesso', 'decaduto', 'escluso')";
        }
        
        if (!empty($filters['volunteer_status'])) {
            $sql .= " AND m.volunteer_status = ?";
            $params[] = $filters['volunteer_status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (m.last_name LIKE ? OR m.first_name LIKE ? OR m.registration_number LIKE ? OR m.tax_code LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " GROUP BY m.id";
        
        // Add sorting
        $sortBy = $filters['sort_by'] ?? 'alphabetical';
        if ($sortBy === 'registration_number') {
            $sql .= " ORDER BY CAST(m.registration_number AS UNSIGNED) ASC";
        } else {
            $sql .= " ORDER BY m.last_name, m.first_name";
        }
        
        // Add pagination
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get total count for pagination
     */
    public function getCount($filters = []) {
        $sql = "SELECT COUNT(DISTINCT m.id) as total FROM members m";
        $params = [];
        
        // Add JOIN for role filter if needed
        if (!empty($filters['role'])) {
            $sql .= " INNER JOIN member_roles mr ON m.id = mr.member_id";
        }
        
        $sql .= " WHERE 1=1";
        
        // Add role filter
        if (!empty($filters['role'])) {
            $sql .= " AND mr.role_name = ?";
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND m.member_status = ?";
            $params[] = $filters['status'];
        }
        
        // Hide dismissed/lapsed filter
        if (isset($filters['hide_dismissed']) && $filters['hide_dismissed'] === '1') {
            $sql .= " AND m.member_status NOT IN ('dimesso', 'decaduto', 'escluso')";
        }
        
        if (!empty($filters['volunteer_status'])) {
            $sql .= " AND m.volunteer_status = ?";
            $params[] = $filters['volunteer_status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (m.last_name LIKE ? OR m.first_name LIKE ? OR m.registration_number LIKE ? OR m.tax_code LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result['total'] ?? 0;
    }
    
    /**
     * Get member by ID with all related data
     */
    public function getById($id) {
        $member = $this->db->fetchOne("SELECT * FROM members WHERE id = ?", [$id]);
        
        if ($member) {
            $member['addresses'] = $this->getAddresses($id);
            $member['contacts'] = $this->getContacts($id);
            $member['education'] = $this->getEducation($id);
            $member['employment'] = $this->getEmployment($id);
            $member['licenses'] = $this->getLicenses($id);
            $member['courses'] = $this->getCourses($id);
            $member['roles'] = $this->getRoles($id);
            $member['availability'] = $this->getAvailability($id);
            $member['fees'] = $this->getFees($id);
            $member['health'] = $this->getHealth($id);
            $member['sanctions'] = $this->getSanctions($id);
            $member['notes'] = $this->getNotes($id);
            $member['attachments'] = $this->getAttachments($id);
        }
        
        return $member;
    }
    
    /**
     * Create new member
     */
    public function create($data) {
        // Generate registration number if not provided
        if (empty($data['registration_number'])) {
            $data['registration_number'] = $this->generateRegistrationNumber();
        }
        
        return $this->db->insert('members', $data);
    }
    
    /**
     * Update member
     */
    public function update($id, $data) {
        return $this->db->update('members', $data, 'id = ?', [$id]);
    }
    
    /**
     * Delete member (soft delete by changing status)
     */
    public function delete($id) {
        return $this->update($id, ['member_status' => 'dimesso']);
    }
    
    /**
     * Generate unique registration number
     */
    private function generateRegistrationNumber() {
        $result = $this->db->fetchOne("SELECT MAX(CAST(registration_number AS UNSIGNED)) as max_num FROM members");
        $nextNum = ($result['max_num'] ?? 0) + 1;
        return str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }
    
    // Addresses
    public function getAddresses($memberId) {
        return $this->db->fetchAll("SELECT * FROM member_addresses WHERE member_id = ?", [$memberId]);
    }
    
    public function addAddress($memberId, $data) {
        $data = $this->uppercaseFields($data, 'address');
        $data['member_id'] = $memberId;
        return $this->db->insert('member_addresses', $data);
    }
    
    public function updateAddress($id, $data) {
        $data = $this->uppercaseFields($data, 'address');
        return $this->db->update('member_addresses', $data, 'id = ?', [$id]);
    }
    
    public function deleteAddress($id) {
        return $this->db->delete('member_addresses', 'id = ?', [$id]);
    }
    
    // Contacts
    public function getContacts($memberId) {
        return $this->db->fetchAll("SELECT * FROM member_contacts WHERE member_id = ?", [$memberId]);
    }
    
    public function addContact($memberId, $data) {
        $data['member_id'] = $memberId;
        return $this->db->insert('member_contacts', $data);
    }
    
    public function updateContact($id, $data) {
        return $this->db->update('member_contacts', $data, 'id = ?', [$id]);
    }
    
    public function deleteContact($id) {
        return $this->db->delete('member_contacts', 'id = ?', [$id]);
    }
    
    // Education
    public function getEducation($memberId) {
        return $this->db->fetchAll("SELECT * FROM member_education WHERE member_id = ?", [$memberId]);
    }
    
    public function addEducation($memberId, $data) {
        $data['member_id'] = $memberId;
        return $this->db->insert('member_education', $data);
    }
    
    public function deleteEducation($id) {
        return $this->db->delete('member_education', 'id = ?', [$id]);
    }
    
    // Employment
    public function getEmployment($memberId) {
        return $this->db->fetchAll("SELECT * FROM member_employment WHERE member_id = ?", [$memberId]);
    }
    
    public function addEmployment($memberId, $data) {
        $data = $this->uppercaseFields($data, 'employment');
        $data['member_id'] = $memberId;
        return $this->db->insert('member_employment', $data);
    }
    
    public function deleteEmployment($id) {
        return $this->db->delete('member_employment', 'id = ?', [$id]);
    }
    
    // Licenses
    public function getLicenses($memberId) {
        return $this->db->fetchAll("SELECT * FROM member_licenses WHERE member_id = ?", [$memberId]);
    }
    
    public function addLicense($memberId, $data) {
        $data = $this->uppercaseFields($data, 'license');
        $data['member_id'] = $memberId;
        $licenseId = $this->db->insert('member_licenses', $data);
        
        // Sincronizza con lo scadenziario se c'è una data di scadenza
        if ($licenseId && !empty($data['expiry_date'])) {
            $syncController = $this->getSyncController();
            $syncController->syncLicenseExpiry($licenseId, $memberId);
        }
        
        return $licenseId;
    }
    
    public function updateLicense($id, $data) {
        $data = $this->uppercaseFields($data, 'license');
        // Get member_id before update for sync
        $license = $this->db->fetchOne("SELECT member_id FROM member_licenses WHERE id = ?", [$id]);
        if (!$license) {
            return false;
        }
        
        $result = $this->db->update('member_licenses', $data, 'id = ?', [$id]);
        
        // Sincronizza con lo scadenziario
        if ($result) {
            $syncController = $this->getSyncController();
            
            if (!empty($data['expiry_date'])) {
                $syncController->syncLicenseExpiry($id, $license['member_id']);
            } else {
                // Se la scadenza è stata rimossa, rimuovi l'item dallo scadenziario
                $syncController->removeSchedulerItem('license', $id);
            }
        }
        
        return $result;
    }
    
    public function deleteLicense($id) {
        // Remove from scheduler when deleting
        $syncController = $this->getSyncController();
        $syncController->removeSchedulerItem('license', $id);
        
        return $this->db->delete('member_licenses', 'id = ?', [$id]);
    }
    
    // Courses
    public function getCourses($memberId) {
        try {
            return $this->db->fetchAll("SELECT * FROM member_courses WHERE member_id = ?", [$memberId]);
        } catch (\Exception $e) {
            // Handle missing table gracefully - return empty array if table doesn't exist
            if ($this->isMissingTableException($e)) {
                return [];
            }
            // Re-throw other exceptions
            throw $e;
        }
    }
    
    public function addCourse($memberId, $data) {
        try {
            $data = $this->uppercaseFields($data, 'course');
            $data['member_id'] = $memberId;
            $courseId = $this->db->insert('member_courses', $data);
            
            // Se il corso è A1 CORSO BASE, aggiorna anche i campi corso_base nel socio
            if ($courseId && !empty($data['course_name']) && 
                stripos($data['course_name'], 'A1 CORSO BASE PER VOLONTARI OPERATIVI DI PROTEZIONE CIVILE') !== false) {
                
                // Estrai l'anno dalla data di completamento
                $anno = null;
                if (!empty($data['completion_date'])) {
                    $anno = intval(substr($data['completion_date'], 0, 4));
                }
                
                // Aggiorna i campi corso_base del socio
                $this->db->execute(
                    "UPDATE members SET corso_base_completato = 1, corso_base_anno = ? WHERE id = ?",
                    [$anno, $memberId]
                );
            }
            
            // Sincronizza con lo scadenziario se c'è una data di scadenza
            if ($courseId && !empty($data['expiry_date'])) {
                $syncController = $this->getSyncController();
                $syncController->syncQualificationExpiry($courseId, $memberId);
            }
            
            return $courseId;
        } catch (\Exception $e) {
            // Handle missing table gracefully
            if ($this->isMissingTableException($e)) {
                throw new \Exception("La tabella member_courses non esiste. Contattare l'amministratore per applicare le migrazioni del database.");
            }
            // Re-throw other exceptions
            throw $e;
        }
    }
    
    public function updateCourse($id, $data) {
        try {
            $data = $this->uppercaseFields($data, 'course');
            // Get member_id and course info before update
            $course = $this->db->fetchOne("SELECT member_id, course_name FROM member_courses WHERE id = ?", [$id]);
            if (!$course) {
                return false;
            }
            
            $result = $this->db->update('member_courses', $data, 'id = ?', [$id]);
            
            // Se il corso è A1 CORSO BASE, aggiorna anche i campi corso_base nel socio
            if ($result && !empty($data['course_name']) && 
                stripos($data['course_name'], 'A1 CORSO BASE PER VOLONTARI OPERATIVI DI PROTEZIONE CIVILE') !== false) {
                
                // Estrai l'anno dalla data di completamento
                $anno = null;
                if (!empty($data['completion_date'])) {
                    $anno = intval(substr($data['completion_date'], 0, 4));
                }
                
                // Aggiorna i campi corso_base del socio
                $this->db->execute(
                    "UPDATE members SET corso_base_completato = 1, corso_base_anno = ? WHERE id = ?",
                    [$anno, $course['member_id']]
                );
            }
            
            // Sincronizza con lo scadenziario
            if ($result) {
                $syncController = $this->getSyncController();
                
                if (!empty($data['expiry_date'])) {
                    $syncController->syncQualificationExpiry($id, $course['member_id']);
                } else {
                    // Se la scadenza è stata rimossa, rimuovi l'item dallo scadenziario
                    $syncController->removeSchedulerItem('qualification', $id);
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            // Handle missing table gracefully
            if ($this->isMissingTableException($e)) {
                throw new \Exception("La tabella member_courses non esiste. Contattare l'amministratore per applicare le migrazioni del database.");
            }
            // Re-throw other exceptions
            throw $e;
        }
    }
    
    public function deleteCourse($id) {
        try {
            // Check if course is linked to training module
            $course = $this->db->fetchOne("SELECT training_course_id FROM member_courses WHERE id = ?", [$id]);
            
            if ($course && !empty($course['training_course_id'])) {
                // Cannot delete courses that come from training module
                return false;
            }
            
            // Remove from scheduler when deleting
            $syncController = $this->getSyncController();
            $syncController->removeSchedulerItem('qualification', $id);
            
            return $this->db->delete('member_courses', 'id = ?', [$id]);
        } catch (\Exception $e) {
            // Handle missing table gracefully
            if ($this->isMissingTableException($e)) {
                throw new \Exception("La tabella member_courses non esiste. Contattare l'amministratore per applicare le migrazioni del database.");
            }
            // Re-throw other exceptions
            throw $e;
        }
    }
    
    // Roles
    public function getRoles($memberId) {
        return $this->db->fetchAll("SELECT * FROM member_roles WHERE member_id = ? AND (end_date IS NULL OR end_date >= CURDATE())", [$memberId]);
    }
    
    public function addRole($memberId, $data) {
        $data['member_id'] = $memberId;
        return $this->db->insert('member_roles', $data);
    }
    
    public function updateRole($id, $data) {
        return $this->db->update('member_roles', $data, 'id = ?', [$id]);
    }
    
    public function deleteRole($id) {
        return $this->db->delete('member_roles', 'id = ?', [$id]);
    }
    
    // Availability
    public function getAvailability($memberId) {
        return $this->db->fetchAll("SELECT * FROM member_availability WHERE member_id = ?", [$memberId]);
    }
    
    public function addAvailability($memberId, $data) {
        $data['member_id'] = $memberId;
        return $this->db->insert('member_availability', $data);
    }
    
    public function deleteAvailability($id) {
        return $this->db->delete('member_availability', 'id = ?', [$id]);
    }
    
    // Fees
    public function getFees($memberId) {
        return $this->db->fetchAll("SELECT * FROM member_fees WHERE member_id = ? ORDER BY year DESC", [$memberId]);
    }
    
    public function addFee($memberId, $data) {
        $data['member_id'] = $memberId;
        return $this->db->insert('member_fees', $data);
    }
    
    public function deleteFee($id) {
        return $this->db->delete('member_fees', 'id = ?', [$id]);
    }
    
    // Health
    public function getHealth($memberId) {
        return $this->db->fetchAll("SELECT * FROM member_health WHERE member_id = ?", [$memberId]);
    }
    
    public function addHealth($memberId, $data) {
        $data['member_id'] = $memberId;
        return $this->db->insert('member_health', $data);
    }
    
    public function updateHealth($id, $data) {
        return $this->db->update('member_health', $data, 'id = ?', [$id]);
    }
    
    public function deleteHealth($id) {
        return $this->db->delete('member_health', 'id = ?', [$id]);
    }
    
    // Sanctions
    public function getSanctions($memberId) {
        return $this->db->fetchAll("SELECT * FROM member_sanctions WHERE member_id = ? ORDER BY sanction_date DESC", [$memberId]);
    }
    
    public function addSanction($memberId, $data) {
        $data['member_id'] = $memberId;
        return $this->db->insert('member_sanctions', $data);
    }
    
    // Notes
    public function getNotes($memberId) {
        return $this->db->fetchAll("SELECT * FROM member_notes WHERE member_id = ? ORDER BY created_at DESC", [$memberId]);
    }
    
    public function addNote($memberId, $data) {
        $data['member_id'] = $memberId;
        return $this->db->insert('member_notes', $data);
    }
    
    public function updateNote($id, $data) {
        return $this->db->update('member_notes', $data, 'id = ?', [$id]);
    }
    
    public function deleteNote($id) {
        return $this->db->delete('member_notes', 'id = ?', [$id]);
    }
    
    // Attachments
    public function getAttachments($memberId) {
        return $this->db->fetchAll("SELECT * FROM member_attachments WHERE member_id = ? ORDER BY uploaded_at DESC", [$memberId]);
    }
    
    public function addAttachment($memberId, $data) {
        $data['member_id'] = $memberId;
        return $this->db->insert('member_attachments', $data);
    }
    
    public function deleteAttachment($id) {
        return $this->db->delete('member_attachments', 'id = ?', [$id]);
    }
    
    // Update methods for other entities
    public function updateAvailability($id, $data) {
        return $this->db->update('member_availability', $data, 'id = ?', [$id]);
    }
    
    public function updateFee($id, $data) {
        return $this->db->update('member_fees', $data, 'id = ?', [$id]);
    }
    
    public function updateSanction($id, $data) {
        return $this->db->update('member_sanctions', $data, 'id = ?', [$id]);
    }
    
    public function deleteSanction($id) {
        return $this->db->delete('member_sanctions', 'id = ?', [$id]);
    }
    
    /**
     * Force uppercase on text fields before saving
     * 
     * @param array $data Data to transform
     * @param string $type Type of data (address, contact, license, course, employment, etc.)
     * @return array Transformed data
     */
    private function uppercaseFields($data, $type = 'default') {
        $fieldMap = [
            'address' => ['street', 'city', 'province'],
            'license' => ['license_type'],
            'course' => ['course_name', 'course_type'],
            'employment' => ['employer_name', 'employer_address', 'employer_city'],
            'default' => []
        ];
        
        $fields = $fieldMap[$type] ?? $fieldMap['default'];
        
        foreach ($fields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = mb_strtoupper($data[$field], 'UTF-8');
            }
        }
        
        return $data;
    }
}
