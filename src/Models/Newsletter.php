<?php
/**
 * Newsletter Model
 * 
 * Manages newsletter operations including creation, sending, scheduling
 */

namespace EasyVol\Models;

use EasyVol\Database;

class Newsletter
{
    private Database $db;
    
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    /**
     * Get newsletter by ID
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT n.*, 
                       creator.full_name as created_by_name,
                       sender.full_name as sent_by_name,
                       cloned.subject as cloned_from_subject
                FROM newsletters n
                LEFT JOIN users creator ON n.created_by = creator.id
                LEFT JOIN users sender ON n.sent_by = sender.id
                LEFT JOIN newsletters cloned ON n.cloned_from = cloned.id
                WHERE n.id = ?";
        
        $result = $this->db->fetchAll($sql, [$id]);
        return $result[0] ?? null;
    }
    
    /**
     * Get all newsletters with filters
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];
        
        // Status filter
        if (!empty($filters['status'])) {
            $where[] = "n.status = ?";
            $params[] = $filters['status'];
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $where[] = "(n.subject LIKE ? OR n.body_html LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Created by filter
        if (!empty($filters['created_by'])) {
            $where[] = "n.created_by = ?";
            $params[] = $filters['created_by'];
        }
        
        // Date range filters
        if (!empty($filters['date_from'])) {
            $where[] = "n.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "n.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT n.*,
                       creator.full_name as created_by_name,
                       sender.full_name as sent_by_name
                FROM newsletters n
                LEFT JOIN users creator ON n.created_by = creator.id
                LEFT JOIN users sender ON n.sent_by = sender.id
                {$whereClause}
                ORDER BY n.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Count newsletters with filters
     */
    public function count(array $filters = []): int
    {
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(subject LIKE ? OR body_html LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['created_by'])) {
            $where[] = "created_by = ?";
            $params[] = $filters['created_by'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT COUNT(*) as count FROM newsletters {$whereClause}";
        $result = $this->db->fetchAll($sql, $params);
        
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Create a new newsletter
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO newsletters (
                    subject, body_html, reply_to, status, scheduled_at,
                    recipient_filter, created_by, cloned_from
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['subject'],
            $data['body_html'],
            $data['reply_to'] ?? null,
            $data['status'] ?? 'draft',
            $data['scheduled_at'] ?? null,
            json_encode($data['recipient_filter'] ?? []),
            $data['created_by'],
            $data['cloned_from'] ?? null
        ];
        
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }
    
    /**
     * Update newsletter (only drafts can be updated)
     */
    public function update(int $id, array $data): bool
    {
        // First check if newsletter is a draft
        $newsletter = $this->getById($id);
        if (!$newsletter || $newsletter['status'] !== 'draft') {
            return false;
        }
        
        $sql = "UPDATE newsletters SET 
                    subject = ?, body_html = ?, reply_to = ?,
                    scheduled_at = ?, recipient_filter = ?
                WHERE id = ? AND status = 'draft'";
        
        $params = [
            $data['subject'],
            $data['body_html'],
            $data['reply_to'] ?? null,
            $data['scheduled_at'] ?? null,
            json_encode($data['recipient_filter'] ?? []),
            $id
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    /**
     * Delete newsletter (only drafts can be deleted)
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM newsletters WHERE id = ? AND status = 'draft'";
        return $this->db->execute($sql, [$id]);
    }
    
    /**
     * Clone newsletter
     */
    public function clone(int $id, int $userId): ?int
    {
        $original = $this->getById($id);
        if (!$original) {
            return null;
        }
        
        $data = [
            'subject' => $original['subject'] . ' (Clone)',
            'body_html' => $original['body_html'],
            'reply_to' => $original['reply_to'],
            'status' => 'draft',
            'recipient_filter' => json_decode($original['recipient_filter'], true),
            'created_by' => $userId,
            'cloned_from' => $id
        ];
        
        $newId = $this->create($data);
        
        // Copy attachments
        $attachments = $this->getAttachments($id);
        foreach ($attachments as $attachment) {
            $this->addAttachment($newId, [
                'filename' => $attachment['filename'],
                'filepath' => $attachment['filepath'],
                'filesize' => $attachment['filesize']
            ]);
        }
        
        return $newId;
    }
    
    /**
     * Get attachments for a newsletter
     */
    public function getAttachments(int $newsletterId): array
    {
        $sql = "SELECT * FROM newsletter_attachments WHERE newsletter_id = ?";
        return $this->db->fetchAll($sql, [$newsletterId]);
    }
    
    /**
     * Add attachment to newsletter
     */
    public function addAttachment(int $newsletterId, array $data): int
    {
        $sql = "INSERT INTO newsletter_attachments (newsletter_id, filename, filepath, filesize)
                VALUES (?, ?, ?, ?)";
        
        $this->db->execute($sql, [
            $newsletterId,
            $data['filename'],
            $data['filepath'],
            $data['filesize']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Delete attachment
     */
    public function deleteAttachment(int $attachmentId): bool
    {
        $sql = "DELETE FROM newsletter_attachments WHERE id = ?";
        return $this->db->execute($sql, [$attachmentId]);
    }
    
    /**
     * Get recipients based on filter
     */
    public function getRecipients(array $filter): array
    {
        $recipients = [];
        $type = $filter['type'] ?? '';
        $customIds = $filter['ids'] ?? [];
        $customMemberIds = $filter['member_ids'] ?? [];
        $customCadetIds = $filter['cadet_ids'] ?? [];
        
        // All active members
        if ($type === 'all_members' || $type === 'all_members_cadets' || $type === 'all_members_cadets_parents' || $type === 'custom_members' || $type === 'custom_combined') {
            $sql = "SELECT DISTINCT m.id, m.email, CONCAT(m.first_name, ' ', m.last_name) as name, 'member' as type
                    FROM members m
                    WHERE m.email IS NOT NULL AND m.email != '' 
                    AND m.member_status = 'attivo'";
            
            if ($type === 'custom_members') {
                if (empty($customIds)) {
                    // No IDs provided for custom selection, return empty result
                    $members = [];
                } else {
                    $placeholders = str_repeat('?,', count($customIds) - 1) . '?';
                    $sql .= " AND m.id IN ($placeholders)";
                    $members = $this->db->fetchAll($sql, $customIds);
                }
            } elseif ($type === 'custom_combined') {
                if (empty($customMemberIds)) {
                    $members = [];
                } else {
                    $placeholders = str_repeat('?,', count($customMemberIds) - 1) . '?';
                    $sql .= " AND m.id IN ($placeholders)";
                    $members = $this->db->fetchAll($sql, $customMemberIds);
                }
            } else {
                $members = $this->db->fetchAll($sql);
            }
            
            $recipients = array_merge($recipients, $members);
        }
        
        // All active cadets
        if ($type === 'all_cadets' || $type === 'all_cadets_with_parents' || $type === 'all_members_cadets' || $type === 'all_members_cadets_parents' || $type === 'custom_cadets' || $type === 'custom_combined') {
            $sql = "SELECT DISTINCT jm.id, jm.email, CONCAT(jm.first_name, ' ', jm.last_name) as name, 'junior_member' as type
                    FROM junior_members jm
                    WHERE jm.email IS NOT NULL AND jm.email != ''
                    AND jm.member_status = 'attivo'";
            
            if ($type === 'custom_cadets') {
                if (empty($customIds)) {
                    // No IDs provided for custom selection, return empty result
                    $cadets = [];
                } else {
                    $placeholders = str_repeat('?,', count($customIds) - 1) . '?';
                    $sql .= " AND jm.id IN ($placeholders)";
                    $cadets = $this->db->fetchAll($sql, $customIds);
                }
            } elseif ($type === 'custom_combined') {
                if (empty($customCadetIds)) {
                    $cadets = [];
                } else {
                    $placeholders = str_repeat('?,', count($customCadetIds) - 1) . '?';
                    $sql .= " AND jm.id IN ($placeholders)";
                    $cadets = $this->db->fetchAll($sql, $customCadetIds);
                }
            } else {
                $cadets = $this->db->fetchAll($sql);
            }
            
            $recipients = array_merge($recipients, $cadets);
        }
        
        // Cadets' parents/guardians
        if ($type === 'all_cadets_with_parents' || $type === 'all_members_cadets_parents') {
            $sql = "SELECT DISTINCT jmg.id, jmg.email, 
                           CONCAT(jmg.first_name, ' ', jmg.last_name) as name, 
                           'guardian' as type,
                           jm.id as junior_member_id
                    FROM junior_member_guardians jmg
                    INNER JOIN junior_members jm ON jmg.junior_member_id = jm.id
                    WHERE jmg.email IS NOT NULL AND jmg.email != ''
                    AND jm.member_status = 'attivo'";
            
            $guardians = $this->db->fetchAll($sql);
            $recipients = array_merge($recipients, $guardians);
        }
        
        return $recipients;
    }
    
    /**
     * Mark newsletter as sent
     */
    public function markAsSent(int $id, int $userId, int $totalRecipients, int $sentCount, int $failedCount): bool
    {
        $sql = "UPDATE newsletters SET 
                    status = 'sent',
                    sent_at = NOW(),
                    sent_by = ?,
                    total_recipients = ?,
                    sent_count = ?,
                    failed_count = ?,
                    send_result = ?
                WHERE id = ?";
        
        $result = json_encode([
            'total' => $totalRecipients,
            'sent' => $sentCount,
            'failed' => $failedCount
        ]);
        
        return $this->db->execute($sql, [$userId, $totalRecipients, $sentCount, $failedCount, $result, $id]);
    }
    
    /**
     * Mark newsletter as scheduled
     */
    public function markAsScheduled(int $id): bool
    {
        $sql = "UPDATE newsletters SET status = 'scheduled' WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    /**
     * Add newsletter recipient record
     */
    public function addRecipient(int $newsletterId, array $data): int
    {
        $sql = "INSERT INTO newsletter_recipients 
                (newsletter_id, email, recipient_name, recipient_type, recipient_id, email_queue_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->execute($sql, [
            $newsletterId,
            $data['email'],
            $data['recipient_name'] ?? null,
            $data['recipient_type'],
            $data['recipient_id'] ?? null,
            $data['email_queue_id'] ?? null,
            $data['status'] ?? 'pending'
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get recipients for a newsletter
     */
    public function getNewsletterRecipients(int $newsletterId): array
    {
        $sql = "SELECT * FROM newsletter_recipients WHERE newsletter_id = ? ORDER BY email";
        return $this->db->fetchAll($sql, [$newsletterId]);
    }
    
    /**
     * Update recipient status
     */
    public function updateRecipientStatus(int $recipientId, string $status, ?string $errorMessage = null): bool
    {
        $sql = "UPDATE newsletter_recipients SET 
                    status = ?,
                    error_message = ?,
                    sent_at = CASE WHEN ? = 'sent' THEN NOW() ELSE sent_at END
                WHERE id = ?";
        
        return $this->db->execute($sql, [$status, $errorMessage, $status, $recipientId]);
    }
}
