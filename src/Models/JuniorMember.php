<?php
namespace EasyVol\Models;

use EasyVol\Database;

/**
 * Junior Member Model
 * Handles all database operations for junior members (minors)
 */
class JuniorMember {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Get all junior members with optional filters
     */
    public function getAll($filters = [], $page = 1, $perPage = 20) {
        $sql = "SELECT jm.*, 
                COUNT(DISTINCT jmc.id) as contact_count,
                COUNT(DISTINCT jma.id) as address_count
                FROM junior_members jm
                LEFT JOIN junior_member_contacts jmc ON jm.id = jmc.junior_member_id
                LEFT JOIN junior_member_addresses jma ON jm.id = jma.junior_member_id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND jm.member_status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (jm.last_name LIKE ? OR jm.first_name LIKE ? OR jm.registration_number LIKE ? OR jm.tax_code LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " GROUP BY jm.id ORDER BY jm.last_name, jm.first_name";
        
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
        $sql = "SELECT COUNT(*) as total FROM junior_members WHERE 1=1";
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
     * Get junior member by ID with all related data
     */
    public function getById($id) {
        $member = $this->db->fetchOne("SELECT * FROM junior_members WHERE id = ?", [$id]);
        
        if ($member) {
            $member['guardians'] = $this->getGuardians($id);
            $member['addresses'] = $this->getAddresses($id);
            $member['contacts'] = $this->getContacts($id);
            $member['health'] = $this->getHealth($id);
            $member['fees'] = $this->getFees($id);
            $member['sanctions'] = $this->getSanctions($id);
            $member['attachments'] = $this->getAttachments($id);
        }
        
        return $member;
    }
    
    /**
     * Create new junior member
     */
    public function create($data) {
        // Generate registration number if not provided
        if (empty($data['registration_number'])) {
            $data['registration_number'] = $this->generateRegistrationNumber();
        }
        
        return $this->db->insert('junior_members', $data);
    }
    
    /**
     * Update junior member
     */
    public function update($id, $data) {
        return $this->db->update('junior_members', $data, 'id = ?', [$id]);
    }
    
    /**
     * Delete junior member (soft delete by changing status)
     */
    public function delete($id) {
        return $this->update($id, ['member_status' => 'dimesso']);
    }
    
    /**
     * Generate unique registration number
     */
    private function generateRegistrationNumber() {
        // Get the highest numeric part from existing registration numbers
        $result = $this->db->fetchOne("SELECT registration_number FROM junior_members WHERE registration_number REGEXP '^[0-9]+$' ORDER BY CAST(registration_number AS UNSIGNED) DESC LIMIT 1");
        
        if ($result && is_numeric($result['registration_number'])) {
            $nextNum = intval($result['registration_number']) + 1;
        } else {
            $nextNum = 1;
        }
        
        return str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }
    
    // Guardians
    public function getGuardians($juniorMemberId) {
        return $this->db->fetchAll("SELECT * FROM junior_member_guardians WHERE junior_member_id = ?", [$juniorMemberId]);
    }
    
    public function addGuardian($juniorMemberId, $data) {
        $data = $this->uppercaseFields($data, 'guardian');
        $data['junior_member_id'] = $juniorMemberId;
        return $this->db->insert('junior_member_guardians', $data);
    }
    
    public function updateGuardian($id, $data) {
        $data = $this->uppercaseFields($data, 'guardian');
        return $this->db->update('junior_member_guardians', $data, 'id = ?', [$id]);
    }
    
    public function deleteGuardian($id) {
        return $this->db->delete('junior_member_guardians', 'id = ?', [$id]);
    }
    
    // Addresses
    public function getAddresses($juniorMemberId) {
        return $this->db->fetchAll("SELECT * FROM junior_member_addresses WHERE junior_member_id = ?", [$juniorMemberId]);
    }
    
    public function addAddress($juniorMemberId, $data) {
        $data = $this->uppercaseFields($data, 'address');
        $data['junior_member_id'] = $juniorMemberId;
        return $this->db->insert('junior_member_addresses', $data);
    }
    
    public function updateAddress($id, $data) {
        $data = $this->uppercaseFields($data, 'address');
        return $this->db->update('junior_member_addresses', $data, 'id = ?', [$id]);
    }
    
    public function deleteAddress($id) {
        return $this->db->delete('junior_member_addresses', 'id = ?', [$id]);
    }
    
    // Contacts
    public function getContacts($juniorMemberId) {
        return $this->db->fetchAll("SELECT * FROM junior_member_contacts WHERE junior_member_id = ?", [$juniorMemberId]);
    }
    
    public function addContact($juniorMemberId, $data) {
        $data['junior_member_id'] = $juniorMemberId;
        return $this->db->insert('junior_member_contacts', $data);
    }
    
    public function updateContact($id, $data) {
        return $this->db->update('junior_member_contacts', $data, 'id = ?', [$id]);
    }
    
    public function deleteContact($id) {
        return $this->db->delete('junior_member_contacts', 'id = ?', [$id]);
    }
    
    // Health
    public function getHealth($juniorMemberId) {
        return $this->db->fetchAll("SELECT * FROM junior_member_health WHERE junior_member_id = ?", [$juniorMemberId]);
    }
    
    public function addHealth($juniorMemberId, $data) {
        $data['junior_member_id'] = $juniorMemberId;
        return $this->db->insert('junior_member_health', $data);
    }
    
    public function deleteHealth($id) {
        return $this->db->delete('junior_member_health', 'id = ?', [$id]);
    }
    
    // Fees
    public function getFees($juniorMemberId) {
        return $this->db->fetchAll("SELECT * FROM junior_member_fees WHERE junior_member_id = ? ORDER BY year DESC", [$juniorMemberId]);
    }
    
    public function addFee($juniorMemberId, $data) {
        $data['junior_member_id'] = $juniorMemberId;
        return $this->db->insert('junior_member_fees', $data);
    }
    
    public function updateFee($id, $data) {
        return $this->db->update('junior_member_fees', $data, 'id = ?', [$id]);
    }
    
    public function deleteFee($id) {
        return $this->db->delete('junior_member_fees', 'id = ?', [$id]);
    }
    
    // Attachments
    public function getAttachments($juniorMemberId) {
        return $this->db->fetchAll("SELECT * FROM junior_member_attachments WHERE junior_member_id = ? ORDER BY uploaded_at DESC", [$juniorMemberId]);
    }
    
    public function addAttachment($juniorMemberId, $data) {
        $data['junior_member_id'] = $juniorMemberId;
        return $this->db->insert('junior_member_attachments', $data);
    }
    
    public function deleteAttachment($id) {
        return $this->db->delete('junior_member_attachments', 'id = ?', [$id]);
    }
    
    // Notes
    public function getNotes($juniorMemberId) {
        return $this->db->fetchAll("SELECT * FROM junior_member_notes WHERE junior_member_id = ? ORDER BY created_at DESC", [$juniorMemberId]);
    }
    
    public function addNote($juniorMemberId, $data) {
        $data['junior_member_id'] = $juniorMemberId;
        return $this->db->insert('junior_member_notes', $data);
    }
    
    public function updateNote($id, $data) {
        return $this->db->update('junior_member_notes', $data, 'id = ?', [$id]);
    }
    
    public function deleteNote($id) {
        return $this->db->delete('junior_member_notes', 'id = ?', [$id]);
    }
    
    // Sanctions
    public function getSanctions($juniorMemberId) {
        return $this->db->fetchAll("SELECT * FROM junior_member_sanctions WHERE junior_member_id = ? ORDER BY sanction_date DESC", [$juniorMemberId]);
    }
    
    public function addSanction($juniorMemberId, $data) {
        $data['junior_member_id'] = $juniorMemberId;
        return $this->db->insert('junior_member_sanctions', $data);
    }
    
    public function updateSanction($id, $data) {
        return $this->db->update('junior_member_sanctions', $data, 'id = ?', [$id]);
    }
    
    public function deleteSanction($id) {
        return $this->db->delete('junior_member_sanctions', 'id = ?', [$id]);
    }
    
    /**
     * Force uppercase on text fields before saving
     * 
     * @param array $data Data to transform
     * @param string $type Type of data (address, guardian, etc.)
     * @return array Transformed data
     */
    private function uppercaseFields($data, $type = 'default') {
        $fieldMap = [
            'address' => ['street', 'city', 'province'],
            'guardian' => ['last_name', 'first_name'],
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
