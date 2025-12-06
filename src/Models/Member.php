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
     * Get all members with optional filters
     */
    public function getAll($filters = [], $page = 1, $perPage = 20) {
        $sql = "SELECT m.*, 
                COUNT(DISTINCT mc.id) as contact_count,
                COUNT(DISTINCT ma.id) as address_count
                FROM members m
                LEFT JOIN member_contacts mc ON m.id = mc.member_id
                LEFT JOIN member_addresses ma ON m.id = ma.member_id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND m.member_status = ?";
            $params[] = $filters['status'];
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
        
        $sql .= " GROUP BY m.id ORDER BY m.last_name, m.first_name";
        
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
        $sql = "SELECT COUNT(*) as total FROM members WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND member_status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (last_name LIKE ? OR first_name LIKE ? OR registration_number LIKE ?)";
            $search = "%{$filters['search']}%";
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
        $data['member_id'] = $memberId;
        return $this->db->insert('member_addresses', $data);
    }
    
    public function updateAddress($id, $data) {
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
        $data['member_id'] = $memberId;
        return $this->db->insert('member_licenses', $data);
    }
    
    public function deleteLicense($id) {
        return $this->db->delete('member_licenses', 'id = ?', [$id]);
    }
    
    // Courses
    public function getCourses($memberId) {
        return $this->db->fetchAll("SELECT * FROM member_courses WHERE member_id = ?", [$memberId]);
    }
    
    public function addCourse($memberId, $data) {
        $data['member_id'] = $memberId;
        return $this->db->insert('member_courses', $data);
    }
    
    public function deleteCourse($id) {
        return $this->db->delete('member_courses', 'id = ?', [$id]);
    }
    
    // Roles
    public function getRoles($memberId) {
        return $this->db->fetchAll("SELECT * FROM member_roles WHERE member_id = ? AND (end_date IS NULL OR end_date >= CURDATE())", [$memberId]);
    }
    
    public function addRole($memberId, $data) {
        $data['member_id'] = $memberId;
        return $this->db->insert('member_roles', $data);
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
}
