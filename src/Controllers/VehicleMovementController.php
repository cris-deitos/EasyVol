<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Services\EmailService;

/**
 * Vehicle Movement Controller
 * 
 * Manages all vehicle movement operations (departures, returns, tracking)
 */
class VehicleMovementController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Authenticate member with registration number and surname
     */
    public function authenticateMember($registrationNumber, $surname) {
        $sql = "SELECT m.*, 
                GROUP_CONCAT(mr.role_name) as roles
                FROM members m
                LEFT JOIN member_roles mr ON m.id = mr.member_id 
                    AND (mr.end_date IS NULL OR mr.end_date >= CURDATE())
                WHERE m.registration_number = ? 
                AND m.last_name = ?
                AND m.member_status = 'attivo'
                GROUP BY m.id";
        
        $member = $this->db->fetchOne($sql, [$registrationNumber, $surname]);
        
        if (!$member) {
            return false;
        }
        
        // Check if member has driver qualification
        $roles = $member['roles'] ?? '';
        $hasDriverQualification = false;
        
        if ($roles) {
            $roleList = explode(',', $roles);
            foreach ($roleList as $role) {
                if (stripos($role, 'AUTISTA') !== false || stripos($role, 'PILOTA') !== false) {
                    $hasDriverQualification = true;
                    break;
                }
            }
        }
        
        if (!$hasDriverQualification) {
            return false;
        }
        
        return $member;
    }
    
    /**
     * Get all vehicles with their current status
     */
    public function getVehicleList($filters = []) {
        $where = ["v.status != 'dismesso'"];
        $params = [];
        
        if (!empty($filters['type'])) {
            $where[] = "v.vehicle_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "v.status = ?";
            $params[] = $filters['status'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT v.*,
                CASE 
                    WHEN vm.id IS NOT NULL AND vm.status = 'in_mission' THEN 1
                    WHEN vmt.id IS NOT NULL AND vmt.status = 'in_mission' THEN 1
                    ELSE 0
                END as in_mission
                FROM vehicles v
                LEFT JOIN vehicle_movements vm ON v.id = vm.vehicle_id 
                    AND vm.status = 'in_mission'
                LEFT JOIN vehicle_movements vmt ON v.id = vmt.trailer_id
                    AND vmt.status = 'in_mission'
                WHERE $whereClause
                ORDER BY v.license_plate, v.serial_number";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Update vehicle status
     */
    public function updateVehicleStatus($vehicleId, $status, $memberId) {
        $validStatuses = ['operativo', 'in_manutenzione', 'fuori_servizio'];
        if (!in_array($status, $validStatuses)) {
            throw new \Exception('Stato non valido');
        }
        
        // Check vehicle is not in mission
        $inMission = $this->isVehicleInMission($vehicleId);
        if ($inMission) {
            throw new \Exception('Impossibile modificare lo stato di un veicolo in missione');
        }
        
        $sql = "UPDATE vehicles SET status = ?, updated_at = NOW() WHERE id = ?";
        $this->db->execute($sql, [$status, $vehicleId]);
        
        return true;
    }
    
    /**
     * Check if vehicle is currently in mission
     * This includes both vehicles on mission and trailers attached to vehicles on mission
     */
    public function isVehicleInMission($vehicleId) {
        $sql = "SELECT COUNT(*) as count 
                FROM vehicle_movements 
                WHERE (vehicle_id = ? OR trailer_id = ?) AND status = 'in_mission'";
        $result = $this->db->fetchOne($sql, [$vehicleId, $vehicleId]);
        return $result['count'] > 0;
    }
    
    /**
     * Get active movement for a vehicle (or trailer)
     * If the vehicle is a trailer, return the movement where it's attached
     */
    public function getActiveMovement($vehicleId) {
        $sql = "SELECT vm.*,
                v.name as vehicle_name, v.license_plate as vehicle_license_plate,
                v.vehicle_type as vehicle_type,
                t.name as trailer_name, t.license_plate as trailer_license_plate,
                GROUP_CONCAT(DISTINCT CONCAT(md.first_name, ' ', md.last_name) 
                    ORDER BY md.last_name SEPARATOR ', ') as departure_drivers
                FROM vehicle_movements vm
                LEFT JOIN vehicles v ON vm.vehicle_id = v.id
                LEFT JOIN vehicles t ON vm.trailer_id = t.id
                LEFT JOIN vehicle_movement_drivers vmd ON vm.id = vmd.movement_id AND vmd.driver_type = 'departure'
                LEFT JOIN members md ON vmd.member_id = md.id
                WHERE (vm.vehicle_id = ? OR vm.trailer_id = ?) AND vm.status = 'in_mission'
                GROUP BY vm.id";
        
        return $this->db->fetchOne($sql, [$vehicleId, $vehicleId]);
    }
    
    /**
     * Validate drivers have required license for vehicle and trailer
     */
    public function validateDriversForVehicle($vehicleId, $driverIds, $trailerId = null) {
        // Get vehicle license requirements
        $vehicle = $this->db->fetchOne("SELECT license_type FROM vehicles WHERE id = ?", [$vehicleId]);
        
        if (!$vehicle) {
            return ['valid' => false, 'message' => 'Veicolo non trovato'];
        }
        
        $requiredLicenses = [];
        
        // Add vehicle license requirements
        if (!empty($vehicle['license_type'])) {
            $requiredLicenses = array_merge($requiredLicenses, array_map('trim', explode(',', $vehicle['license_type'])));
        }
        
        // Add trailer license requirements if trailer is attached
        if ($trailerId) {
            $trailer = $this->db->fetchOne("SELECT license_type, name FROM vehicles WHERE id = ? AND vehicle_type = 'rimorchio'", [$trailerId]);
            
            if (!$trailer) {
                return ['valid' => false, 'message' => 'Rimorchio non trovato'];
            }
            
            if (!empty($trailer['license_type'])) {
                $requiredLicenses = array_merge($requiredLicenses, array_map('trim', explode(',', $trailer['license_type'])));
            }
        }
        
        // Remove duplicates
        $requiredLicenses = array_unique($requiredLicenses);
        
        // If no license requirement
        if (empty($requiredLicenses)) {
            return ['valid' => true];
        }
        
        // Get all drivers and their qualifications
        $driverIdsStr = implode(',', array_map('intval', $driverIds));
        $sql = "SELECT m.id, m.first_name, m.last_name,
                GROUP_CONCAT(mr.role_name) as roles
                FROM members m
                LEFT JOIN member_roles mr ON m.id = mr.member_id 
                    AND (mr.end_date IS NULL OR mr.end_date >= CURDATE())
                WHERE m.id IN ($driverIdsStr)
                GROUP BY m.id";
        
        $drivers = $this->db->fetchAll($sql);
        
        // Collect all available licenses from all drivers
        $availableLicenses = [];
        foreach ($drivers as $driver) {
            $roles = $driver['roles'] ?? '';
            if ($roles) {
                $roleList = explode(',', $roles);
                foreach ($roleList as $role) {
                    // Check for AUTISTA A, B, C, D, E or PILOTA NATANTE
                    if (preg_match('/AUTISTA\s+([A-E])/i', $role, $matches)) {
                        $availableLicenses[] = $matches[1];
                    } elseif (stripos($role, 'PILOTA') !== false || stripos($role, 'NATANTE') !== false) {
                        $availableLicenses[] = 'Nautica';
                    }
                }
            }
        }
        
        $availableLicenses = array_unique($availableLicenses);
        
        // Check if all required licenses are covered
        $missingLicenses = [];
        foreach ($requiredLicenses as $required) {
            if (!in_array($required, $availableLicenses)) {
                $missingLicenses[] = $required;
            }
        }
        
        if (!empty($missingLicenses)) {
            return [
                'valid' => false,
                'message' => "Nessun autista ha le patenti richieste: " . implode(', ', $missingLicenses)
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Create vehicle departure
     */
    public function createDeparture($data, $memberId) {
        $this->db->beginTransaction();
        
        try {
            // Validate vehicle exists and can depart
            $vehicle = $this->db->fetchOne("SELECT * FROM vehicles WHERE id = ?", [$data['vehicle_id']]);
            if (!$vehicle) {
                throw new \Exception('Veicolo non trovato');
            }
            
            // Check if vehicle is a trailer - trailers cannot depart alone
            if ($vehicle['vehicle_type'] === 'rimorchio') {
                throw new \Exception('I rimorchi non possono uscire da soli. Devono essere associati ad un veicolo trainante.');
            }
            
            if ($vehicle['status'] === 'fuori_servizio') {
                throw new \Exception('Il veicolo è fuori servizio e non può essere utilizzato');
            }
            
            // Check vehicle is not already in mission
            if ($this->isVehicleInMission($data['vehicle_id'])) {
                throw new \Exception('Il veicolo è già in missione');
            }
            
            // If trailer is specified, validate it
            $trailerId = !empty($data['trailer_id']) ? intval($data['trailer_id']) : null;
            if ($trailerId) {
                // Check trailer exists and is a rimorchio
                $trailer = $this->db->fetchOne(
                    "SELECT * FROM vehicles WHERE id = ? AND vehicle_type = 'rimorchio'", 
                    [$trailerId]
                );
                if (!$trailer) {
                    throw new \Exception('Rimorchio non trovato o non valido');
                }
                
                // Check trailer is not in mission
                if ($this->isVehicleInMission($trailerId)) {
                    throw new \Exception('Il rimorchio è già in missione');
                }
                
                if ($trailer['status'] === 'fuori_servizio') {
                    throw new \Exception('Il rimorchio è fuori servizio e non può essere utilizzato');
                }
            }
            
            // Validate drivers (including trailer license if present)
            $driverValidation = $this->validateDriversForVehicle($data['vehicle_id'], $data['drivers'], $trailerId);
            if (!$driverValidation['valid']) {
                throw new \Exception($driverValidation['message']);
            }
            
            // Insert movement record
            $sql = "INSERT INTO vehicle_movements (
                vehicle_id, trailer_id, departure_datetime, departure_km, departure_fuel_level,
                service_type, destination, authorized_by, departure_notes,
                departure_anomaly_flag, status, created_by_member_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_mission', ?)";
            
            $this->db->execute($sql, [
                $data['vehicle_id'],
                $trailerId,
                $data['departure_datetime'],
                $data['departure_km'] ?? null,
                $data['departure_fuel_level'] ?? null,
                $data['service_type'] ?? null,
                $data['destination'] ?? null,
                $data['authorized_by'] ?? null,
                $data['departure_notes'] ?? null,
                $data['departure_anomaly_flag'] ?? 0,
                $memberId
            ]);
            
            $movementId = $this->db->lastInsertId();
            
            // Insert drivers
            foreach ($data['drivers'] as $driverId) {
                $sql = "INSERT INTO vehicle_movement_drivers (movement_id, member_id, driver_type)
                        VALUES (?, ?, 'departure')";
                $this->db->execute($sql, [$movementId, $driverId]);
            }
            
            // Insert checklist items
            if (!empty($data['checklist'])) {
                foreach ($data['checklist'] as $item) {
                    $sql = "INSERT INTO vehicle_movement_checklists (
                        movement_id, checklist_item_id, item_name, check_timing,
                        item_type, value_boolean, value_numeric, value_text
                    ) VALUES (?, ?, ?, 'departure', ?, ?, ?, ?)";
                    
                    $this->db->execute($sql, [
                        $movementId,
                        $item['checklist_item_id'] ?? null,
                        $item['item_name'],
                        $item['item_type'],
                        $item['value_boolean'] ?? null,
                        $item['value_numeric'] ?? null,
                        $item['value_text'] ?? null
                    ]);
                }
            }
            
            // Send anomaly email if flagged
            if (!empty($data['departure_anomaly_flag'])) {
                $this->sendAnomalyEmail($movementId, 'departure');
                $this->db->execute(
                    "UPDATE vehicle_movements SET departure_anomaly_email_sent = 1 WHERE id = ?",
                    [$movementId]
                );
            }
            
            $this->db->commit();
            return ['success' => true, 'movement_id' => $movementId];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Create vehicle return
     */
    public function createReturn($movementId, $data, $memberId) {
        $this->db->beginTransaction();
        
        try {
            // Get movement
            $movement = $this->db->fetchOne(
                "SELECT * FROM vehicle_movements WHERE id = ? AND status = 'in_mission'",
                [$movementId]
            );
            
            if (!$movement) {
                throw new \Exception('Movimento non trovato o già completato');
            }
            
            // Validate drivers if provided
            if (!empty($data['drivers'])) {
                $driverValidation = $this->validateDriversForVehicle($movement['vehicle_id'], $data['drivers']);
                if (!$driverValidation['valid']) {
                    throw new \Exception($driverValidation['message']);
                }
            }
            
            // Calculate trip duration and km
            $departureTime = new \DateTime($movement['departure_datetime']);
            $returnTime = new \DateTime($data['return_datetime']);
            $durationMinutes = ($returnTime->getTimestamp() - $departureTime->getTimestamp()) / 60;
            
            $tripKm = null;
            if (!empty($data['return_km']) && !empty($movement['departure_km'])) {
                $tripKm = $data['return_km'] - $movement['departure_km'];
            }
            
            // Update movement
            $sql = "UPDATE vehicle_movements SET
                return_datetime = ?,
                return_km = ?,
                return_fuel_level = ?,
                return_notes = ?,
                return_anomaly_flag = ?,
                traffic_violation_flag = ?,
                status = 'completed',
                trip_duration_minutes = ?,
                trip_km = ?,
                updated_at = NOW()
                WHERE id = ?";
            
            $this->db->execute($sql, [
                $data['return_datetime'],
                $data['return_km'] ?? null,
                $data['return_fuel_level'] ?? null,
                $data['return_notes'] ?? null,
                $data['return_anomaly_flag'] ?? 0,
                $data['traffic_violation_flag'] ?? 0,
                $durationMinutes,
                $tripKm,
                $movementId
            ]);
            
            // Insert return drivers if provided
            if (!empty($data['drivers'])) {
                foreach ($data['drivers'] as $driverId) {
                    $sql = "INSERT INTO vehicle_movement_drivers (movement_id, member_id, driver_type)
                            VALUES (?, ?, 'return')";
                    $this->db->execute($sql, [$movementId, $driverId]);
                }
            }
            
            // Insert return checklist items
            if (!empty($data['checklist'])) {
                foreach ($data['checklist'] as $item) {
                    $sql = "INSERT INTO vehicle_movement_checklists (
                        movement_id, checklist_item_id, item_name, check_timing,
                        item_type, value_boolean, value_numeric, value_text
                    ) VALUES (?, ?, ?, 'return', ?, ?, ?, ?)";
                    
                    $this->db->execute($sql, [
                        $movementId,
                        $item['checklist_item_id'] ?? null,
                        $item['item_name'],
                        $item['item_type'],
                        $item['value_boolean'] ?? null,
                        $item['value_numeric'] ?? null,
                        $item['value_text'] ?? null
                    ]);
                }
            }
            
            // Send emails if flagged
            if (!empty($data['return_anomaly_flag'])) {
                $this->sendAnomalyEmail($movementId, 'return');
                $this->db->execute(
                    "UPDATE vehicle_movements SET return_anomaly_email_sent = 1 WHERE id = ?",
                    [$movementId]
                );
            }
            
            if (!empty($data['traffic_violation_flag'])) {
                $this->sendTrafficViolationEmail($movementId);
                $this->db->execute(
                    "UPDATE vehicle_movements SET traffic_violation_email_sent = 1 WHERE id = ?",
                    [$movementId]
                );
            }
            
            // Handle trailer return status
            if (!empty($movement['trailer_id']) && isset($data['trailer_return_status'])) {
                if ($data['trailer_return_status'] === 'still_mission') {
                    // Add note that trailer was left on mission
                    $trailerResult = $this->db->fetchOne(
                        "SELECT name FROM vehicles WHERE id = ?", 
                        [$movement['trailer_id']]
                    );
                    $trailerName = $trailerResult ? ($trailerResult['name'] ?? 'N/D') : 'N/D';
                    $trailerNote = "\n\n[NOTA SISTEMA] Il rimorchio '" . $trailerName . "' è stato lasciato in missione e dovrà essere recuperato successivamente.";
                    $this->db->execute(
                        "UPDATE vehicle_movements SET return_notes = CONCAT(COALESCE(TRIM(return_notes), ''), ?) WHERE id = ?",
                        [$trailerNote, $movementId]
                    );
                }
            }
            
            $this->db->commit();
            return ['success' => true];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Mark trip as completed without return data
     */
    public function completeWithoutReturn($movementId) {
        $sql = "UPDATE vehicle_movements 
                SET status = 'completed_no_return', updated_at = NOW()
                WHERE id = ? AND status = 'in_mission'";
        
        $this->db->execute($sql, [$movementId]);
        return true;
    }
    
    /**
     * Get vehicle movement history
     */
    public function getMovementHistory($filters = [], $page = 1, $perPage = 20) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['vehicle_id'])) {
            $where[] = "vm.vehicle_id = ?";
            $params[] = $filters['vehicle_id'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "vm.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "vm.departure_datetime >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "vm.departure_datetime <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT vm.*,
                v.license_plate, v.brand, v.model, v.vehicle_type,
                t.id as trailer_id, t.name as trailer_name, t.license_plate as trailer_license_plate, 
                t.serial_number as trailer_serial_number,
                m.first_name as creator_first_name, m.last_name as creator_last_name,
                GROUP_CONCAT(DISTINCT CONCAT(md.first_name, ' ', md.last_name) 
                    ORDER BY md.last_name SEPARATOR ', ') as departure_drivers,
                GROUP_CONCAT(DISTINCT CONCAT(mr.first_name, ' ', mr.last_name) 
                    ORDER BY mr.last_name SEPARATOR ', ') as return_drivers
                FROM vehicle_movements vm
                JOIN vehicles v ON vm.vehicle_id = v.id
                LEFT JOIN vehicles t ON vm.trailer_id = t.id
                JOIN members m ON vm.created_by_member_id = m.id
                LEFT JOIN vehicle_movement_drivers vmd ON vm.id = vmd.movement_id AND vmd.driver_type = 'departure'
                LEFT JOIN members md ON vmd.member_id = md.id
                LEFT JOIN vehicle_movement_drivers vmr ON vm.id = vmr.movement_id AND vmr.driver_type = 'return'
                LEFT JOIN members mr ON vmr.member_id = mr.id
                WHERE $whereClause
                GROUP BY vm.id
                ORDER BY vm.departure_datetime DESC
                LIMIT $perPage OFFSET $offset";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get single movement with all details
     */
    public function getMovement($id) {
        $sql = "SELECT vm.*,
                v.license_plate, v.brand, v.model, v.vehicle_type, v.name as vehicle_name,
                t.id as trailer_id, t.name as trailer_name, t.license_plate as trailer_license_plate, 
                t.serial_number as trailer_serial_number,
                m.first_name as creator_first_name, m.last_name as creator_last_name,
                m.registration_number as creator_reg_number
                FROM vehicle_movements vm
                JOIN vehicles v ON vm.vehicle_id = v.id
                LEFT JOIN vehicles t ON vm.trailer_id = t.id
                JOIN members m ON vm.created_by_member_id = m.id
                WHERE vm.id = ?";
        
        $movement = $this->db->fetchOne($sql, [$id]);
        
        if (!$movement) {
            return false;
        }
        
        // Get departure drivers
        $sql = "SELECT m.id, m.first_name, m.last_name, m.registration_number
                FROM vehicle_movement_drivers vmd
                JOIN members m ON vmd.member_id = m.id
                WHERE vmd.movement_id = ? AND vmd.driver_type = 'departure'";
        $movement['departure_drivers'] = $this->db->fetchAll($sql, [$id]);
        
        // Get return drivers
        $sql = "SELECT m.id, m.first_name, m.last_name, m.registration_number
                FROM vehicle_movement_drivers vmd
                JOIN members m ON vmd.member_id = m.id
                WHERE vmd.movement_id = ? AND vmd.driver_type = 'return'";
        $movement['return_drivers'] = $this->db->fetchAll($sql, [$id]);
        
        // Get departure checklist
        $sql = "SELECT * FROM vehicle_movement_checklists 
                WHERE movement_id = ? AND check_timing = 'departure'
                ORDER BY id";
        $movement['departure_checklist'] = $this->db->fetchAll($sql, [$id]);
        
        // Get return checklist
        $sql = "SELECT * FROM vehicle_movement_checklists 
                WHERE movement_id = ? AND check_timing = 'return'
                ORDER BY id";
        $movement['return_checklist'] = $this->db->fetchAll($sql, [$id]);
        
        return $movement;
    }
    
    /**
     * Get vehicle checklists (including trailer checklists if trailer is specified)
     */
    public function getVehicleChecklists($vehicleId, $timing = null, $trailerId = null) {
        $checklists = [];
        
        // Get vehicle checklists
        $where = ["vehicle_id = ?"];
        $params = [$vehicleId];
        
        if ($timing) {
            $where[] = "(check_timing = ? OR check_timing = 'both')";
            $params[] = $timing;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT * FROM vehicle_checklists 
                WHERE $whereClause 
                ORDER BY display_order, id";
        
        $checklists = $this->db->fetchAll($sql, $params);
        
        // Add trailer checklists if trailer is specified
        if ($trailerId) {
            $trailerWhere = ["vehicle_id = ?"];
            $trailerParams = [$trailerId];
            
            if ($timing) {
                $trailerWhere[] = "(check_timing = ? OR check_timing = 'both')";
                $trailerParams[] = $timing;
            }
            
            $trailerWhereClause = implode(' AND ', $trailerWhere);
            
            $trailerSql = "SELECT vc.*, v.name as vehicle_name 
                          FROM vehicle_checklists vc
                          JOIN vehicles v ON vc.vehicle_id = v.id
                          WHERE $trailerWhereClause 
                          ORDER BY vc.display_order, vc.id";
            
            $trailerChecklists = $this->db->fetchAll($trailerSql, $trailerParams);
            
            // Mark trailer checklists and add them
            foreach ($trailerChecklists as &$item) {
                $item['is_trailer_item'] = true;
                $item['item_name'] = '[RIMORCHIO] ' . $item['item_name'];
            }
            
            $checklists = array_merge($checklists, $trailerChecklists);
        }
        
        return $checklists;
    }
    
    /**
     * Get available trailers (rimorchi not in mission and not fuori_servizio)
     */
    public function getAvailableTrailers() {
        $sql = "SELECT v.id, v.name, v.license_plate, v.serial_number, v.status, v.license_type
                FROM vehicles v
                WHERE v.vehicle_type = 'rimorchio' 
                AND v.status != 'fuori_servizio'
                AND v.status != 'dismesso'
                AND v.id NOT IN (
                    SELECT trailer_id 
                    FROM vehicle_movements 
                    WHERE trailer_id IS NOT NULL 
                    AND status = 'in_mission'
                )
                ORDER BY v.name, v.serial_number";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Send anomaly email
     */
    private function sendAnomalyEmail($movementId, $type) {
        try {
            $movement = $this->getMovement($movementId);
            if (!$movement) {
                return;
            }
            
            // Get email addresses from config
            $emailConfig = $this->db->fetchOne(
                "SELECT config_value FROM config WHERE config_key = 'vehicle_movement_alert_emails'"
            );
            
            if (empty($emailConfig['config_value'])) {
                return; // No emails configured
            }
            
            $emails = array_map('trim', explode(',', $emailConfig['config_value']));
            $emails = array_filter($emails);
            
            if (empty($emails)) {
                return;
            }
            
            $subject = "Segnalazione Anomalia Veicolo - " . $movement['vehicle_name'];
            
            $body = "<h2>Segnalazione Anomalia Veicolo</h2>";
            $body .= "<p><strong>Veicolo:</strong> " . htmlspecialchars($movement['vehicle_name']) . "</p>";
            $body .= "<p><strong>Targa/Matricola:</strong> " . htmlspecialchars($movement['license_plate']) . "</p>";
            $body .= "<p><strong>Tipo:</strong> " . ($type === 'departure' ? 'In Uscita' : 'Al Rientro') . "</p>";
            $body .= "<p><strong>Data/Ora:</strong> " . date('d/m/Y H:i', strtotime($type === 'departure' ? $movement['departure_datetime'] : $movement['return_datetime'])) . "</p>";
            
            if ($type === 'departure') {
                $body .= "<p><strong>Note Anomalie:</strong><br>" . nl2br(htmlspecialchars($movement['departure_notes'] ?? '')) . "</p>";
                
                // Add departure checklist
                if (!empty($movement['departure_checklist'])) {
                    $body .= "<h3>Checklist Uscita:</h3><ul>";
                    foreach ($movement['departure_checklist'] as $item) {
                        $body .= "<li>" . htmlspecialchars($item['item_name']) . ": ";
                        if ($item['item_type'] === 'boolean') {
                            $body .= $item['value_boolean'] ? 'Sì' : 'No';
                        } elseif ($item['item_type'] === 'numeric') {
                            $body .= $item['value_numeric'];
                        } else {
                            $body .= htmlspecialchars($item['value_text']);
                        }
                        $body .= "</li>";
                    }
                    $body .= "</ul>";
                }
            } else {
                $body .= "<p><strong>Note Anomalie:</strong><br>" . nl2br(htmlspecialchars($movement['return_notes'] ?? '')) . "</p>";
                
                // Add return checklist
                if (!empty($movement['return_checklist'])) {
                    $body .= "<h3>Checklist Rientro:</h3><ul>";
                    foreach ($movement['return_checklist'] as $item) {
                        $body .= "<li>" . htmlspecialchars($item['item_name']) . ": ";
                        if ($item['item_type'] === 'boolean') {
                            $body .= $item['value_boolean'] ? 'Sì' : 'No';
                        } elseif ($item['item_type'] === 'numeric') {
                            $body .= $item['value_numeric'];
                        } else {
                            $body .= htmlspecialchars($item['value_text']);
                        }
                        $body .= "</li>";
                    }
                    $body .= "</ul>";
                }
            }
            
            $body .= "<p><strong>Operatore:</strong> " . htmlspecialchars($movement['creator_first_name'] . ' ' . $movement['creator_last_name']) . "</p>";
            
            $emailService = new EmailService($this->db, $this->config);
            
            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emailService->sendEmail($email, $subject, $body);
                }
            }
            
        } catch (\Exception $e) {
            error_log("Error sending anomaly email: " . $e->getMessage());
        }
    }
    
    /**
     * Send traffic violation email
     */
    private function sendTrafficViolationEmail($movementId) {
        try {
            $movement = $this->getMovement($movementId);
            if (!$movement) {
                return;
            }
            
            // Get association email
            $association = $this->db->fetchOne("SELECT email FROM association WHERE id = 1");
            if (empty($association['email'])) {
                return;
            }
            
            $subject = "Ipotesi Sanzioni Codice della Strada - " . $movement['vehicle_name'];
            
            $body = "<h2>Segnalazione Ipotesi Sanzioni Codice della Strada</h2>";
            $body .= "<p><strong>Veicolo:</strong> " . htmlspecialchars($movement['vehicle_name']) . "</p>";
            $body .= "<p><strong>Targa/Matricola:</strong> " . htmlspecialchars($movement['license_plate']) . "</p>";
            
            $body .= "<h3>Dettagli Uscita:</h3>";
            $body .= "<p><strong>Data/Ora Uscita:</strong> " . date('d/m/Y H:i', strtotime($movement['departure_datetime'])) . "</p>";
            $body .= "<p><strong>Autisti Uscita:</strong> ";
            if (!empty($movement['departure_drivers'])) {
                $drivers = array_map(function($d) {
                    return htmlspecialchars($d['first_name'] . ' ' . $d['last_name'] . ' (' . $d['registration_number'] . ')');
                }, $movement['departure_drivers']);
                $body .= implode(', ', $drivers);
            }
            $body .= "</p>";
            
            $body .= "<h3>Dettagli Rientro:</h3>";
            $body .= "<p><strong>Data/Ora Rientro:</strong> " . date('d/m/Y H:i', strtotime($movement['return_datetime'])) . "</p>";
            $body .= "<p><strong>Autisti Rientro:</strong> ";
            if (!empty($movement['return_drivers'])) {
                $drivers = array_map(function($d) {
                    return htmlspecialchars($d['first_name'] . ' ' . $d['last_name'] . ' (' . $d['registration_number'] . ')');
                }, $movement['return_drivers']);
                $body .= implode(', ', $drivers);
            }
            $body .= "</p>";
            
            if ($movement['trip_duration_minutes']) {
                $hours = floor($movement['trip_duration_minutes'] / 60);
                $minutes = $movement['trip_duration_minutes'] % 60;
                $body .= "<p><strong>Durata Viaggio:</strong> {$hours}h {$minutes}m</p>";
            }
            
            if ($movement['trip_km']) {
                $body .= "<p><strong>Km Percorsi:</strong> " . $movement['trip_km'] . " km</p>";
            }
            
            $emailService = new EmailService($this->db, $this->config);
            $emailService->sendEmail($association['email'], $subject, $body);
            
        } catch (\Exception $e) {
            error_log("Error sending traffic violation email: " . $e->getMessage());
        }
    }
    
    /**
     * Search members for driver selection
     */
    public function searchMembers($query) {
        $searchTerm = '%' . $query . '%';
        
        $sql = "SELECT m.id, m.registration_number, m.first_name, m.last_name,
                GROUP_CONCAT(mr.role_name) as roles
                FROM members m
                LEFT JOIN member_roles mr ON m.id = mr.member_id 
                    AND (mr.end_date IS NULL OR mr.end_date >= CURDATE())
                WHERE m.member_status = 'attivo'
                AND (m.registration_number LIKE ? OR m.last_name LIKE ?)
                GROUP BY m.id
                LIMIT 10";
        
        $members = $this->db->fetchAll($sql, [$searchTerm, $searchTerm]);
        
        // Filter only drivers
        return array_filter($members, function($member) {
            $roles = $member['roles'] ?? '';
            if ($roles) {
                $roleList = explode(',', $roles);
                foreach ($roleList as $role) {
                    if (stripos($role, 'AUTISTA') !== false || stripos($role, 'PILOTA') !== false) {
                        return true;
                    }
                }
            }
            return false;
        });
    }
}
