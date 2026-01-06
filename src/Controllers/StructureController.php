<?php

namespace EasyVol\Controllers;

use EasyVol\Database;
use PDO;

class StructureController
{
    private $db;
    private $config;

    public function __construct(Database $db, $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Convert empty string to null for coordinate field
     * @param mixed $value The value to normalize
     * @return float|null
     */
    private function normalizeCoordinate($value)
    {
        return ($value !== '' && $value !== null) ? (float)$value : null;
    }

    /**
     * Validate coordinate value
     * @param mixed $value The coordinate value to validate
     * @param string $fieldName The field name for error message
     * @throws \InvalidArgumentException If coordinate is not numeric
     */
    private function validateCoordinate($value, $fieldName)
    {
        if ($value !== null && !is_numeric($value)) {
            throw new \InvalidArgumentException("{$fieldName} deve essere un numero valido");
        }
    }

    /**
     * Get all structures
     */
    public function getAllStructures()
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT * FROM structures 
            ORDER BY name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get structure by ID
     */
    public function getStructureById($structureId)
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT * FROM structures WHERE id = ?
        ");
        $stmt->execute([$structureId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create new structure
     */
    public function createStructure($data, $userId = null)
    {
        // Validate required fields
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Il campo Nome è obbligatorio');
        }

        // Convert empty strings to null for coordinate fields
        $latitude = $this->normalizeCoordinate($data['latitude'] ?? null);
        $longitude = $this->normalizeCoordinate($data['longitude'] ?? null);

        // Validate data types for numeric fields
        $this->validateCoordinate($latitude, 'Latitudine');
        $this->validateCoordinate($longitude, 'Longitudine');

        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO structures (
                name, type, full_address, latitude, longitude,
                owner, owner_contacts, contracts_deadlines, keys_codes, notes,
                created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $data['name'],
            $data['type'] ?? null,
            $data['full_address'] ?? null,
            $latitude,
            $longitude,
            $data['owner'] ?? null,
            $data['owner_contacts'] ?? null,
            $data['contracts_deadlines'] ?? null,
            $data['keys_codes'] ?? null,
            $data['notes'] ?? null,
            $userId,
            $userId
        ]);

        if ($result) {
            return $this->db->getConnection()->lastInsertId();
        }
        
        return false;
    }

    /**
     * Update existing structure
     */
    public function updateStructure($structureId, $data, $userId = null)
    {
        // Check if structure exists
        $existing = $this->getStructureById($structureId);
        if (!$existing) {
            throw new \InvalidArgumentException('Struttura non trovata');
        }

        // Validate required fields
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Il campo Nome è obbligatorio');
        }

        // Convert empty strings to null for coordinate fields
        $latitude = $this->normalizeCoordinate($data['latitude'] ?? null);
        $longitude = $this->normalizeCoordinate($data['longitude'] ?? null);

        // Validate data types for numeric fields
        $this->validateCoordinate($latitude, 'Latitudine');
        $this->validateCoordinate($longitude, 'Longitudine');

        $stmt = $this->db->getConnection()->prepare("
            UPDATE structures 
            SET name = ?, type = ?, full_address = ?, latitude = ?, longitude = ?,
                owner = ?, owner_contacts = ?, contracts_deadlines = ?, 
                keys_codes = ?, notes = ?, updated_by = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['name'],
            $data['type'] ?? null,
            $data['full_address'] ?? null,
            $latitude,
            $longitude,
            $data['owner'] ?? null,
            $data['owner_contacts'] ?? null,
            $data['contracts_deadlines'] ?? null,
            $data['keys_codes'] ?? null,
            $data['notes'] ?? null,
            $userId,
            $structureId
        ]);
    }

    /**
     * Delete structure
     */
    public function deleteStructure($structureId)
    {
        $stmt = $this->db->getConnection()->prepare("
            DELETE FROM structures WHERE id = ?
        ");
        return $stmt->execute([$structureId]);
    }

    /**
     * Get structures count
     */
    public function getStructuresCount()
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT COUNT(*) as count FROM structures
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['count'] : 0;
    }

    /**
     * Get structures with GPS coordinates
     */
    public function getStructuresWithCoordinates()
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT * FROM structures 
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
            ORDER BY name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
