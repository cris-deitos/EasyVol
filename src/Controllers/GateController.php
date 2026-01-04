<?php

namespace EasyVol\Controllers;

use EasyVol\Database;
use PDO;

class GateController
{
    private $db;
    private $config;

    public function __construct(Database $db, $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Get gate system status (active or disabled)
     */
    public function getSystemStatus()
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT is_active, updated_at 
            FROM gate_system_config 
            WHERE id = 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result : ['is_active' => 0, 'updated_at' => null];
    }

    /**
     * Set gate system status
     */
    public function setSystemStatus($isActive, $userId = null)
    {
        $stmt = $this->db->getConnection()->prepare("
            UPDATE gate_system_config 
            SET is_active = ?, updated_by = ? 
            WHERE id = 1
        ");
        return $stmt->execute([$isActive ? 1 : 0, $userId]);
    }

    /**
     * Get all gates
     */
    public function getAllGates()
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT * FROM gates 
            ORDER BY CAST(gate_number AS UNSIGNED), gate_number
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get gate by ID
     */
    public function getGateById($gateId)
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT * FROM gates WHERE id = ?
        ");
        $stmt->execute([$gateId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create new gate
     */
    public function createGate($data)
    {
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO gates (
                gate_number, name, status, latitude, longitude,
                limit_a, limit_b, limit_c, limit_manual, limit_in_use, people_count
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $data['gate_number'],
            $data['name'],
            $data['status'] ?? 'non_gestito',
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['limit_a'] ?? 0,
            $data['limit_b'] ?? 0,
            $data['limit_c'] ?? 0,
            $data['limit_manual'] ?? 0,
            $data['limit_in_use'] ?? 'manual',
            $data['people_count'] ?? 0
        ]);

        if ($result) {
            $gateId = $this->db->getConnection()->lastInsertId();
            $this->logActivity($gateId, 'create_gate', null, json_encode($data));
            return $gateId;
        }
        
        return false;
    }

    /**
     * Update gate
     */
    public function updateGate($gateId, $data)
    {
        $gate = $this->getGateById($gateId);
        if (!$gate) {
            return false;
        }

        $fields = [];
        $values = [];
        
        $allowedFields = [
            'gate_number', 'name', 'status', 'latitude', 'longitude',
            'limit_a', 'limit_b', 'limit_c', 'limit_manual', 'limit_in_use', 'people_count'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $gateId;
        $sql = "UPDATE gates SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        $result = $stmt->execute($values);

        if ($result) {
            $this->logActivity($gateId, 'update_limit', json_encode($gate), json_encode($data));
        }

        return $result;
    }

    /**
     * Delete gate
     */
    public function deleteGate($gateId)
    {
        $gate = $this->getGateById($gateId);
        if (!$gate) {
            return false;
        }

        $this->logActivity($gateId, 'delete_gate', json_encode($gate), null);

        $stmt = $this->db->getConnection()->prepare("DELETE FROM gates WHERE id = ?");
        return $stmt->execute([$gateId]);
    }

    /**
     * Add person to gate
     */
    public function addPerson($gateId)
    {
        $gate = $this->getGateById($gateId);
        if (!$gate) {
            return false;
        }

        $oldCount = $gate['people_count'];
        $newCount = $oldCount + 1;

        $stmt = $this->db->getConnection()->prepare("
            UPDATE gates SET people_count = ? WHERE id = ?
        ");
        $result = $stmt->execute([$newCount, $gateId]);

        if ($result) {
            $this->logActivity($gateId, 'add_person', $oldCount, $newCount);
        }

        return $result;
    }

    /**
     * Remove person from gate
     */
    public function removePerson($gateId)
    {
        $gate = $this->getGateById($gateId);
        if (!$gate) {
            return false;
        }

        $oldCount = $gate['people_count'];
        $newCount = max(0, $oldCount - 1); // Don't go below 0

        $stmt = $this->db->getConnection()->prepare("
            UPDATE gates SET people_count = ? WHERE id = ?
        ");
        $result = $stmt->execute([$newCount, $gateId]);

        if ($result) {
            $this->logActivity($gateId, 'remove_person', $oldCount, $newCount);
        }

        return $result;
    }

    /**
     * Open gate
     */
    public function openGate($gateId)
    {
        $gate = $this->getGateById($gateId);
        if (!$gate) {
            return false;
        }

        $stmt = $this->db->getConnection()->prepare("
            UPDATE gates SET status = 'aperto' WHERE id = ?
        ");
        $result = $stmt->execute([$gateId]);

        if ($result) {
            $this->logActivity($gateId, 'open_gate', $gate['status'], 'aperto');
        }

        return $result;
    }

    /**
     * Close gate
     */
    public function closeGate($gateId)
    {
        $gate = $this->getGateById($gateId);
        if (!$gate) {
            return false;
        }

        $stmt = $this->db->getConnection()->prepare("
            UPDATE gates SET status = 'chiuso' WHERE id = ?
        ");
        $result = $stmt->execute([$gateId]);

        if ($result) {
            $this->logActivity($gateId, 'close_gate', $gate['status'], 'chiuso');
        }

        return $result;
    }

    /**
     * Set manual people count
     */
    public function setManualCount($gateId, $count)
    {
        $gate = $this->getGateById($gateId);
        if (!$gate) {
            return false;
        }

        $oldCount = $gate['people_count'];
        $count = max(0, intval($count));

        $stmt = $this->db->getConnection()->prepare("
            UPDATE gates SET people_count = ? WHERE id = ?
        ");
        $result = $stmt->execute([$count, $gateId]);

        if ($result) {
            $this->logActivity($gateId, 'set_manual_count', $oldCount, $count);
        }

        return $result;
    }

    /**
     * Get total people count for open and closed gates
     */
    public function getTotalPeopleCount()
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT SUM(people_count) as total 
            FROM gates 
            WHERE status IN ('aperto', 'chiuso')
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total'] ?? 0;
    }

    /**
     * Get current limit value for a gate
     */
    public function getCurrentLimit($gate)
    {
        switch ($gate['limit_in_use']) {
            case 'a':
                return $gate['limit_a'];
            case 'b':
                return $gate['limit_b'];
            case 'c':
                return $gate['limit_c'];
            case 'manual':
            default:
                return $gate['limit_manual'];
        }
    }

    /**
     * Log activity to both gate_activity_log and main activity_logs
     */
    private function logActivity($gateId, $actionType, $previousValue, $newValue)
    {
        // Log to gate-specific activity log
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO gate_activity_log 
            (gate_id, action_type, previous_value, new_value, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $gateId,
            $actionType,
            $previousValue,
            $newValue,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        // Also log to main activity_logs for comprehensive tracking
        try {
            $app = \EasyVol\App::getInstance();
            
            // Build detailed description
            $gate = $this->getGateById($gateId);
            $gateName = $gate ? $gate['name'] : "Varco ID $gateId";
            
            $actionLabels = [
                'create_gate' => 'Creazione',
                'update_limit' => 'Aggiornamento limiti',
                'delete_gate' => 'Eliminazione',
                'add_person' => 'Aggiunta persona',
                'remove_person' => 'Rimozione persona',
                'open_gate' => 'Apertura varco',
                'close_gate' => 'Chiusura varco',
                'set_manual_count' => 'Impostazione conteggio manuale'
            ];
            
            $actionLabel = $actionLabels[$actionType] ?? $actionType;
            $description = "$actionLabel - Varco: $gateName";
            
            // Add previous/new value details
            if ($actionType === 'add_person' || $actionType === 'remove_person' || $actionType === 'set_manual_count') {
                $description .= " (Da: $previousValue, A: $newValue)";
            } elseif ($actionType === 'open_gate' || $actionType === 'close_gate') {
                $description .= " (Stato da: $previousValue, a: $newValue)";
            } elseif ($actionType === 'update_limit' || $actionType === 'create_gate') {
                // For these, previous/new values are JSON encoded
                $description .= ". Dati: " . (is_string($newValue) ? $newValue : json_encode($newValue, JSON_UNESCAPED_UNICODE));
            }
            
            $app->logActivity('update', 'gate_management', $gateId, $description);
        } catch (\Exception $e) {
            error_log("Failed to log gate activity to main log: " . $e->getMessage());
        }
    }
}
