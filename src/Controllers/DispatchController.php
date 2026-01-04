<?php
namespace EasyVol\Controllers;

use EasyVol\Database;

/**
 * Dispatch Controller
 * 
 * Handles the dispatch system for real-time radio monitoring,
 * GPS tracking, audio streaming, and emergency alerts
 */
class DispatchController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    // ============================================
    // TALKGROUP MANAGEMENT
    // ============================================
    
    /**
     * Get all TalkGroups
     */
    public function getTalkGroups() {
        $sql = "SELECT * FROM dispatch_talkgroups ORDER BY talkgroup_id";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get single TalkGroup
     */
    public function getTalkGroup($id) {
        $sql = "SELECT * FROM dispatch_talkgroups WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Create TalkGroup
     */
    public function createTalkGroup($data) {
        $sql = "INSERT INTO dispatch_talkgroups (talkgroup_id, name, description, created_at) 
                VALUES (?, ?, ?, NOW())";
        $this->db->execute($sql, [
            $data['talkgroup_id'],
            $data['name'],
            $data['description'] ?? null
        ]);
        $id = $this->db->lastInsertId();
        
        // Log activity with full details
        $description = "Creato TalkGroup: {$data['name']} (TG ID: {$data['talkgroup_id']}). Dettagli: " . 
                      json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->logActivity('dispatch', 'create', $id, $description);
        
        return $id;
    }
    
    /**
     * Update TalkGroup
     */
    public function updateTalkGroup($id, $data) {
        // Get old data before update
        $oldData = $this->getTalkGroup($id);
        
        if (!$oldData) {
            throw new \Exception("TalkGroup non trovato con ID: $id");
        }
        
        $sql = "UPDATE dispatch_talkgroups 
                SET talkgroup_id = ?, name = ?, description = ?, updated_at = NOW() 
                WHERE id = ?";
        $this->db->execute($sql, [
            $data['talkgroup_id'],
            $data['name'],
            $data['description'] ?? null,
            $id
        ]);
        
        // Log activity with before/after data
        $changes = [];
        foreach ($data as $key => $value) {
            if (isset($oldData[$key]) && $oldData[$key] != $value) {
                $changes[$key] = ['da' => $oldData[$key], 'a' => $value];
            }
        }
        $description = "Aggiornato TalkGroup: {$data['name']} (ID: $id). Modifiche: " . 
                      json_encode($changes, JSON_UNESCAPED_UNICODE);
        $this->logActivity('dispatch', 'update', $id, $description);
        
        return true;
    }
    
    /**
     * Delete TalkGroup
     */
    public function deleteTalkGroup($id) {
        // Get talkgroup data before deletion
        $talkgroup = $this->getTalkGroup($id);
        
        if (!$talkgroup) {
            throw new \Exception("TalkGroup non trovato con ID: $id");
        }
        
        $sql = "DELETE FROM dispatch_talkgroups WHERE id = ?";
        $this->db->execute($sql, [$id]);
        
        // Log deletion with full data
        $deletedData = [
            'id' => $talkgroup['id'],
            'talkgroup_id' => $talkgroup['talkgroup_id'],
            'name' => $talkgroup['name'],
            'description' => $talkgroup['description'] ?? ''
        ];
        $description = "Eliminato TalkGroup: {$talkgroup['name']} (TG ID: {$talkgroup['talkgroup_id']}). " .
                      "Dati completi eliminati: " . json_encode($deletedData, JSON_UNESCAPED_UNICODE);
        $this->logActivity('dispatch', 'delete', $id, $description);
        
        return true;
    }
    
    // ============================================
    // REAL-TIME TRANSMISSION STATUS
    // ============================================
    
    /**
     * Get current transmission status for both slots
     */
    public function getCurrentTransmissionStatus() {
        $status = [
            'slot1' => null,
            'slot2' => null
        ];
        
        // Get active transmissions for both slots
        $sql = "SELECT t.*, 
                rd.name as radio_name,
                rd.identifier as radio_identifier,
                rd.dmr_id,
                tg.name as talkgroup_name,
                ra.member_id,
                m.first_name,
                m.last_name,
                mc.value as phone,
                ra.assignee_organization as organization
                FROM dispatch_transmissions t
                LEFT JOIN radio_directory rd ON t.radio_dmr_id = rd.dmr_id
                LEFT JOIN dispatch_talkgroups tg ON t.talkgroup_id = tg.talkgroup_id
                LEFT JOIN radio_assignments ra ON (rd.id = ra.radio_id AND ra.return_date IS NULL AND ra.status = 'assegnata')
                LEFT JOIN members m ON ra.member_id = m.id
                LEFT JOIN member_contacts mc ON (m.id = mc.member_id AND mc.contact_type = 'cellulare')
                WHERE t.is_active = 1
                ORDER BY t.transmission_start DESC";
        
        $transmissions = $this->db->fetchAll($sql);
        
        foreach ($transmissions as $trans) {
            $slot = 'slot' . $trans['slot'];
            if ($status[$slot] === null) {
                $status[$slot] = $trans;
            }
        }
        
        return $status;
    }
    
    /**
     * Start a new transmission
     */
    public function startTransmission($slot, $radioDmrId, $talkgroupId) {
        // End any existing active transmission for this slot
        $sql = "UPDATE dispatch_transmissions 
                SET is_active = 0, 
                    transmission_end = NOW(),
                    duration_seconds = TIMESTAMPDIFF(SECOND, transmission_start, NOW())
                WHERE slot = ? AND is_active = 1";
        $this->db->execute($sql, [$slot]);
        
        // Get radio_id if exists
        $radioIdSql = "SELECT id FROM radio_directory WHERE dmr_id = ?";
        $radio = $this->db->fetchOne($radioIdSql, [$radioDmrId]);
        $radioId = $radio ? $radio['id'] : null;
        
        // Start new transmission
        $sql = "INSERT INTO dispatch_transmissions 
                (slot, radio_id, radio_dmr_id, talkgroup_id, transmission_start, is_active, created_at)
                VALUES (?, ?, ?, ?, NOW(), 1, NOW())";
        $this->db->execute($sql, [$slot, $radioId, $radioDmrId, $talkgroupId]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * End a transmission
     */
    public function endTransmission($transmissionId) {
        $sql = "UPDATE dispatch_transmissions 
                SET is_active = 0, 
                    transmission_end = NOW(),
                    duration_seconds = TIMESTAMPDIFF(SECOND, transmission_start, NOW()),
                    updated_at = NOW()
                WHERE id = ?";
        $this->db->execute($sql, [$transmissionId]);
        return true;
    }
    
    // ============================================
    // GPS POSITIONS
    // ============================================
    
    /**
     * Get active radio positions (last 30 minutes)
     */
    public function getActiveRadioPositions() {
        $sql = "SELECT p.*, 
                rd.name as radio_name,
                rd.identifier as radio_identifier,
                rd.dmr_id,
                ra.member_id,
                m.first_name,
                m.last_name,
                mc.value as phone,
                ra.assignee_organization as organization
                FROM dispatch_positions p
                INNER JOIN (
                    SELECT radio_dmr_id, MAX(timestamp) as max_timestamp
                    FROM dispatch_positions
                    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                    GROUP BY radio_dmr_id
                ) latest ON p.radio_dmr_id = latest.radio_dmr_id AND p.timestamp = latest.max_timestamp
                LEFT JOIN radio_directory rd ON p.radio_dmr_id = rd.dmr_id
                LEFT JOIN radio_assignments ra ON (rd.id = ra.radio_id AND ra.return_date IS NULL AND ra.status = 'assegnata')
                LEFT JOIN members m ON ra.member_id = m.id
                LEFT JOIN member_contacts mc ON (m.id = mc.member_id AND mc.contact_type = 'cellulare')
                ORDER BY p.timestamp DESC";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Save GPS position
     */
    public function savePosition($radioDmrId, $latitude, $longitude, $timestamp, $additionalData = []) {
        // Get radio_id if exists
        $radioIdSql = "SELECT id FROM radio_directory WHERE dmr_id = ?";
        $radio = $this->db->fetchOne($radioIdSql, [$radioDmrId]);
        $radioId = $radio ? $radio['id'] : null;
        
        $sql = "INSERT INTO dispatch_positions 
                (radio_id, radio_dmr_id, latitude, longitude, altitude, speed, heading, accuracy, timestamp, received_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [
            $radioId,
            $radioDmrId,
            $latitude,
            $longitude,
            $additionalData['altitude'] ?? null,
            $additionalData['speed'] ?? null,
            $additionalData['heading'] ?? null,
            $additionalData['accuracy'] ?? null,
            $timestamp
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get position history with filters
     */
    public function getPositionHistory($filters = [], $page = 1, $perPage = 100) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['radio_dmr_id'])) {
            $where[] = "p.radio_dmr_id = ?";
            $params[] = $filters['radio_dmr_id'];
        }
        
        if (!empty($filters['start_date'])) {
            $where[] = "p.timestamp >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where[] = "p.timestamp <= ?";
            $params[] = $filters['end_date'];
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT p.*, 
                rd.name as radio_name,
                rd.identifier as radio_identifier
                FROM dispatch_positions p
                LEFT JOIN radio_directory rd ON p.radio_dmr_id = rd.dmr_id
                WHERE $whereClause
                ORDER BY p.timestamp DESC
                LIMIT $perPage OFFSET $offset";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // ============================================
    // EVENTS LOG
    // ============================================
    
    /**
     * Get recent events
     */
    public function getRecentEvents($limit = 50) {
        $sql = "SELECT e.*, 
                rd.name as radio_name,
                rd.identifier as radio_identifier,
                tg.name as talkgroup_name
                FROM dispatch_events e
                LEFT JOIN radio_directory rd ON e.radio_dmr_id = rd.dmr_id
                LEFT JOIN dispatch_talkgroups tg ON e.talkgroup_id = tg.talkgroup_id
                ORDER BY e.event_timestamp DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Log an event
     */
    public function logEvent($eventType, $data) {
        // Get radio_id if exists
        $radioId = null;
        if (!empty($data['radio_dmr_id'])) {
            $radioIdSql = "SELECT id FROM radio_directory WHERE dmr_id = ?";
            $radio = $this->db->fetchOne($radioIdSql, [$data['radio_dmr_id']]);
            $radioId = $radio ? $radio['id'] : null;
        }
        
        $sql = "INSERT INTO dispatch_events 
                (slot, event_type, radio_id, radio_dmr_id, talkgroup_id, event_data, event_timestamp, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [
            $data['slot'] ?? null,
            $eventType,
            $radioId,
            $data['radio_dmr_id'] ?? null,
            $data['talkgroup_id'] ?? null,
            isset($data['event_data']) ? json_encode($data['event_data']) : null,
            $data['event_timestamp'] ?? date('Y-m-d H:i:s')
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get event history with filters
     */
    public function getEventHistory($filters = [], $page = 1, $perPage = 100) {
        // Validate pagination parameters
        $page = max(1, (int)$page);
        $perPage = max(1, min(1000, (int)$perPage));
        
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['slot'])) {
            $where[] = "e.slot = ?";
            $params[] = $filters['slot'];
        }
        
        if (!empty($filters['event_type'])) {
            $where[] = "e.event_type = ?";
            $params[] = $filters['event_type'];
        }
        
        if (!empty($filters['radio_dmr_id'])) {
            $where[] = "e.radio_dmr_id = ?";
            $params[] = $filters['radio_dmr_id'];
        }
        
        if (!empty($filters['talkgroup_id'])) {
            $where[] = "e.talkgroup_id = ?";
            $params[] = $filters['talkgroup_id'];
        }
        
        if (!empty($filters['start_date'])) {
            $where[] = "e.event_timestamp >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where[] = "e.event_timestamp <= ?";
            $params[] = $filters['end_date'];
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT e.*, 
                rd.name as radio_name,
                rd.identifier as radio_identifier,
                tg.name as talkgroup_name
                FROM dispatch_events e
                LEFT JOIN radio_directory rd ON e.radio_dmr_id = rd.dmr_id
                LEFT JOIN dispatch_talkgroups tg ON e.talkgroup_id = tg.talkgroup_id
                WHERE $whereClause
                ORDER BY e.event_timestamp DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // ============================================
    // AUDIO RECORDINGS
    // ============================================
    
    /**
     * Get recent audio recordings
     */
    public function getRecentAudioRecordings($limit = 50) {
        $sql = "SELECT a.*, 
                rd.name as radio_name,
                rd.identifier as radio_identifier,
                tg.name as talkgroup_name
                FROM dispatch_audio_recordings a
                LEFT JOIN radio_directory rd ON a.radio_dmr_id = rd.dmr_id
                LEFT JOIN dispatch_talkgroups tg ON a.talkgroup_id = tg.talkgroup_id
                ORDER BY a.recorded_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Save audio recording
     */
    public function saveAudioRecording($slot, $radioDmrId, $talkgroupId, $filePath, $durationSeconds, $recordedAt) {
        // Get radio_id if exists
        $radioIdSql = "SELECT id FROM radio_directory WHERE dmr_id = ?";
        $radio = $this->db->fetchOne($radioIdSql, [$radioDmrId]);
        $radioId = $radio ? $radio['id'] : null;
        
        $fileSize = file_exists($filePath) ? filesize($filePath) : null;
        
        $sql = "INSERT INTO dispatch_audio_recordings 
                (slot, radio_id, radio_dmr_id, talkgroup_id, file_path, duration_seconds, file_size_bytes, recorded_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [
            $slot,
            $radioId,
            $radioDmrId,
            $talkgroupId,
            $filePath,
            $durationSeconds,
            $fileSize,
            $recordedAt
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get audio recording history with filters
     */
    public function getAudioHistory($filters = [], $page = 1, $perPage = 100) {
        // Validate pagination parameters
        $page = max(1, (int)$page);
        $perPage = max(1, min(1000, (int)$perPage));
        
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['slot'])) {
            $where[] = "a.slot = ?";
            $params[] = $filters['slot'];
        }
        
        if (!empty($filters['radio_dmr_id'])) {
            $where[] = "a.radio_dmr_id = ?";
            $params[] = $filters['radio_dmr_id'];
        }
        
        if (!empty($filters['talkgroup_id'])) {
            $where[] = "a.talkgroup_id = ?";
            $params[] = $filters['talkgroup_id'];
        }
        
        if (!empty($filters['start_date'])) {
            $where[] = "a.recorded_at >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where[] = "a.recorded_at <= ?";
            $params[] = $filters['end_date'];
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT a.*, 
                rd.name as radio_name,
                rd.identifier as radio_identifier,
                tg.name as talkgroup_name
                FROM dispatch_audio_recordings a
                LEFT JOIN radio_directory rd ON a.radio_dmr_id = rd.dmr_id
                LEFT JOIN dispatch_talkgroups tg ON a.talkgroup_id = tg.talkgroup_id
                WHERE $whereClause
                ORDER BY a.recorded_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // ============================================
    // TEXT MESSAGES
    // ============================================
    
    /**
     * Get recent text messages
     */
    public function getRecentTextMessages($limit = 50) {
        $sql = "SELECT tm.*, 
                rd_from.name as from_radio_name,
                rd_from.identifier as from_radio_identifier,
                rd_to.name as to_radio_name,
                rd_to.identifier as to_radio_identifier,
                tg.name as to_talkgroup_name
                FROM dispatch_text_messages tm
                LEFT JOIN radio_directory rd_from ON tm.from_radio_dmr_id = rd_from.dmr_id
                LEFT JOIN radio_directory rd_to ON tm.to_radio_dmr_id = rd_to.dmr_id
                LEFT JOIN dispatch_talkgroups tg ON tm.to_talkgroup_id = tg.talkgroup_id
                ORDER BY tm.message_timestamp DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Save text message
     */
    public function saveTextMessage($slot, $fromRadioDmrId, $toRadioDmrId, $toTalkgroupId, $messageText, $messageTimestamp) {
        // Get radio_id if exists
        $fromRadioId = null;
        if (!empty($fromRadioDmrId)) {
            $radioIdSql = "SELECT id FROM radio_directory WHERE dmr_id = ?";
            $radio = $this->db->fetchOne($radioIdSql, [$fromRadioDmrId]);
            $fromRadioId = $radio ? $radio['id'] : null;
        }
        
        $sql = "INSERT INTO dispatch_text_messages 
                (slot, from_radio_id, from_radio_dmr_id, to_radio_dmr_id, to_talkgroup_id, message_text, message_timestamp, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [
            $slot,
            $fromRadioId,
            $fromRadioDmrId,
            $toRadioDmrId,
            $toTalkgroupId,
            $messageText,
            $messageTimestamp
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get text message history with filters
     */
    public function getMessageHistory($filters = [], $page = 1, $perPage = 100) {
        // Validate pagination parameters
        $page = max(1, (int)$page);
        $perPage = max(1, min(1000, (int)$perPage));
        
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['slot'])) {
            $where[] = "tm.slot = ?";
            $params[] = $filters['slot'];
        }
        
        if (!empty($filters['from_radio_dmr_id'])) {
            $where[] = "tm.from_radio_dmr_id = ?";
            $params[] = $filters['from_radio_dmr_id'];
        }
        
        if (!empty($filters['to_radio_dmr_id'])) {
            $where[] = "tm.to_radio_dmr_id = ?";
            $params[] = $filters['to_radio_dmr_id'];
        }
        
        if (!empty($filters['to_talkgroup_id'])) {
            $where[] = "tm.to_talkgroup_id = ?";
            $params[] = $filters['to_talkgroup_id'];
        }
        
        if (!empty($filters['start_date'])) {
            $where[] = "tm.message_timestamp >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where[] = "tm.message_timestamp <= ?";
            $params[] = $filters['end_date'];
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT tm.*, 
                rd_from.name as from_radio_name,
                rd_from.identifier as from_radio_identifier,
                rd_to.name as to_radio_name,
                rd_to.identifier as to_radio_identifier,
                tg.name as to_talkgroup_name
                FROM dispatch_text_messages tm
                LEFT JOIN radio_directory rd_from ON tm.from_radio_dmr_id = rd_from.dmr_id
                LEFT JOIN radio_directory rd_to ON tm.to_radio_dmr_id = rd_to.dmr_id
                LEFT JOIN dispatch_talkgroups tg ON tm.to_talkgroup_id = tg.talkgroup_id
                WHERE $whereClause
                ORDER BY tm.message_timestamp DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // ============================================
    // EMERGENCY CODES
    // ============================================
    
    /**
     * Get active emergency codes
     */
    public function getActiveEmergencies() {
        $sql = "SELECT ec.*, 
                rd.name as radio_name,
                rd.identifier as radio_identifier,
                rd.dmr_id,
                ra.member_id,
                m.first_name,
                m.last_name,
                mc.value as phone,
                ra.assignee_organization as organization,
                ra.notes as assignment_notes
                FROM dispatch_emergency_codes ec
                LEFT JOIN radio_directory rd ON ec.radio_dmr_id = rd.dmr_id
                LEFT JOIN radio_assignments ra ON (rd.id = ra.radio_id AND ra.return_date IS NULL AND ra.status = 'assegnata')
                LEFT JOIN members m ON ra.member_id = m.id
                LEFT JOIN member_contacts mc ON (m.id = mc.member_id AND mc.contact_type = 'cellulare')
                WHERE ec.status = 'active'
                ORDER BY ec.emergency_timestamp DESC";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Save emergency code
     */
    public function saveEmergencyCode($radioDmrId, $latitude, $longitude, $emergencyTimestamp) {
        // Get radio_id if exists
        $radioIdSql = "SELECT id FROM radio_directory WHERE dmr_id = ?";
        $radio = $this->db->fetchOne($radioIdSql, [$radioDmrId]);
        $radioId = $radio ? $radio['id'] : null;
        
        $sql = "INSERT INTO dispatch_emergency_codes 
                (radio_id, radio_dmr_id, latitude, longitude, emergency_timestamp, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW())";
        
        $this->db->execute($sql, [
            $radioId,
            $radioDmrId,
            $latitude,
            $longitude,
            $emergencyTimestamp
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Acknowledge emergency
     */
    public function acknowledgeEmergency($emergencyId, $userId, $notes = null) {
        $sql = "UPDATE dispatch_emergency_codes 
                SET status = 'acknowledged', 
                    acknowledged_by = ?, 
                    acknowledged_at = NOW(),
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?";
        
        $this->db->execute($sql, [$userId, $notes, $emergencyId]);
        return true;
    }
    
    /**
     * Resolve emergency
     */
    public function resolveEmergency($emergencyId, $userId, $notes = null) {
        $sql = "UPDATE dispatch_emergency_codes 
                SET status = 'resolved', 
                    acknowledged_by = ?, 
                    acknowledged_at = COALESCE(acknowledged_at, NOW()),
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?";
        
        $this->db->execute($sql, [$userId, $notes, $emergencyId]);
        return true;
    }
    
    // ============================================
    // RASPBERRY PI CONFIGURATION
    // ============================================
    
    /**
     * Get Raspberry Pi configuration
     */
    public function getRaspberryConfig() {
        $sql = "SELECT * FROM dispatch_raspberry_config";
        $configs = $this->db->fetchAll($sql);
        
        $config = [];
        foreach ($configs as $item) {
            $config[$item['config_key']] = $item['config_value'];
        }
        
        return $config;
    }
    
    /**
     * Update Raspberry Pi configuration
     */
    public function updateRaspberryConfig($key, $value) {
        $sql = "UPDATE dispatch_raspberry_config 
                SET config_value = ?, updated_at = NOW() 
                WHERE config_key = ?";
        
        $this->db->execute($sql, [$value, $key]);
        return true;
    }
    
    /**
     * Log activity helper method
     */
    private function logActivity($module, $action, $recordId = null, $description = null) {
        try {
            $app = \EasyVol\App::getInstance();
            $app->logActivity($action, $module, $recordId, $description);
        } catch (\Exception $e) {
            error_log("Failed to log dispatch activity: " . $e->getMessage());
        }
    }
}
