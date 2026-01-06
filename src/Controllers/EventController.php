<?php
namespace EasyVol\Controllers;

use EasyVol\Database;

/**
 * Event Controller
 * 
 * Gestisce eventi, esercitazioni e interventi
 */
class EventController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Lista eventi con filtri
     */
    public function index($filters = [], $page = 1, $perPage = 20) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['type'])) {
            $where[] = "event_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(title LIKE ? OR description LIKE ? OR location LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM events 
                WHERE $whereClause 
                ORDER BY start_date DESC 
                LIMIT $perPage OFFSET $offset";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Conta totale eventi con filtri
     */
    public function count($filters = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['type'])) {
            $where[] = "event_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(title LIKE ? OR description LIKE ? OR location LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) as total FROM events WHERE $whereClause";
        $result = $this->db->fetchOne($sql, $params);
        return $result['total'] ?? 0;
    }
    
    /**
     * Ottieni singolo evento
     */
    public function get($id) {
        $sql = "SELECT * FROM events WHERE id = ?";
        $event = $this->db->fetchOne($sql, [$id]);
        
        if (!$event) {
            return false;
        }
        
        // Carica interventi
        $event['interventions'] = $this->getInterventions($id);
        
        // Carica partecipanti
        $event['participants'] = $this->getParticipants($id);
        
        // Carica mezzi
        $event['vehicles'] = $this->getVehicles($id);
        
        return $event;
    }
    
    /**
     * Crea nuovo evento
     */
    public function create($data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO events (
                event_type, title, description, start_date, end_date, location, status, created_by, created_at,
                latitude, longitude, full_address, municipality, legal_benefits_recognized
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)";
            
            $params = [
                $data['event_type'],
                $data['title'],
                $data['description'] ?? null,
                $data['start_date'],
                !empty($data['end_date']) ? $data['end_date'] : null,
                $data['location'] ?? null,
                $data['status'] ?? 'in_corso',
                $userId,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['full_address'] ?? null,
                $data['municipality'] ?? null,
                $data['legal_benefits_recognized'] ?? 'no'
            ];
            
            $this->db->execute($sql, $params);
            $eventId = $this->db->lastInsertId();
            
            $this->logActivity($userId, 'event', 'create', $eventId, 'Creato nuovo evento: ' . $data['title']);
            
            // Send Telegram notification for new event
            try {
                require_once __DIR__ . '/../Services/TelegramService.php';
                $telegramService = new \EasyVol\Services\TelegramService($this->db, $this->config);
                
                if ($telegramService->isEnabled()) {
                    // Get creator info
                    $creator = $this->db->fetchOne(
                        "SELECT first_name, last_name FROM members WHERE id IN (SELECT member_id FROM users WHERE id = ?)",
                        [$userId]
                    );
                    
                    $eventTypes = [
                        'emergenza' => '🚨 Emergenza',
                        'esercitazione' => '🎯 Esercitazione',
                        'attivita' => '📅 Attività',
                        'servizio' => '🛠️ Servizio'
                    ];
                    
                    $message = "📢 <b>NUOVO EVENTO CREATO</b>\n\n";
                    $message .= ($eventTypes[$data['event_type']] ?? '📋 Evento') . "\n";
                    $message .= "<b>📌 Titolo:</b> " . htmlspecialchars($data['title']) . "\n";
                    
                    if (!empty($data['description'])) {
                        $message .= "\n<b>📝 Descrizione:</b>\n" . htmlspecialchars($data['description']) . "\n";
                    }
                    
                    $message .= "\n<b>📅 Data inizio:</b> " . date('d/m/Y H:i', strtotime($data['start_date'])) . "\n";
                    
                    if (!empty($data['end_date'])) {
                        $message .= "<b>🏁 Data fine:</b> " . date('d/m/Y H:i', strtotime($data['end_date'])) . "\n";
                    }
                    
                    if (!empty($data['location'])) {
                        $message .= "<b>📍 Luogo:</b> " . htmlspecialchars($data['location']) . "\n";
                    }
                    
                    if ($creator) {
                        $message .= "\n<b>👤 Creato da:</b> " . htmlspecialchars($creator['first_name'] . ' ' . $creator['last_name']) . "\n";
                    }
                    
                    $telegramService->sendNotification('event_created', $message);
                }
            } catch (\Exception $e) {
                error_log("Errore invio notifica Telegram per nuovo evento: " . $e->getMessage());
            }
            
            // Send province email if requested
            if (!empty($data['send_province_email'])) {
                try {
                    $this->sendProvinceEmail($eventId, $userId);
                } catch (\Exception $e) {
                    error_log("Errore invio email Provincia: " . $e->getMessage());
                    // Non bloccare la creazione dell'evento se l'email fallisce
                }
            }
            
            $this->db->commit();
            return $eventId;
            
        } catch (\Exception $e) {
            if ($this->db->getConnection()->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Errore creazione evento: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Aggiorna evento
     */
    public function update($id, $data, $userId) {
        try {
            // Verifica se si sta cercando di chiudere l'evento
            $newStatus = $data['status'] ?? 'in_corso';
            if ($newStatus === 'concluso') {
                // Controlla se ci sono interventi ancora in corso o sospesi
                if ($this->hasActiveInterventions($id)) {
                    throw new \Exception('Non è possibile chiudere l\'evento perché ci sono ancora interventi in corso o sospesi.');
                }
            }
            
            $sql = "UPDATE events SET
                event_type = ?, title = ?, description = ?, start_date = ?,
                end_date = ?, location = ?, status = ?, updated_at = NOW(),
                latitude = ?, longitude = ?, full_address = ?, municipality = ?,
                legal_benefits_recognized = ?
                WHERE id = ?";
            
            $params = [
                $data['event_type'],
                $data['title'],
                $data['description'] ?? null,
                $data['start_date'],
                !empty($data['end_date']) ? $data['end_date'] : null,
                $data['location'] ?? null,
                $newStatus,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['full_address'] ?? null,
                $data['municipality'] ?? null,
                $data['legal_benefits_recognized'] ?? 'no',
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            $this->logActivity($userId, 'event', 'update', $id, 'Aggiornato evento');
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore aggiornamento evento: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Ottieni interventi di un evento
     */
    private function getInterventions($eventId) {
        $sql = "SELECT i.*, 
                COUNT(DISTINCT im.member_id) as members_count,
                COUNT(DISTINCT iv.vehicle_id) as vehicles_count
                FROM interventions i
                LEFT JOIN intervention_members im ON i.id = im.intervention_id
                LEFT JOIN intervention_vehicles iv ON i.id = iv.intervention_id
                WHERE i.event_id = ? 
                GROUP BY i.id
                ORDER BY i.start_time DESC";
        return $this->db->fetchAll($sql, [$eventId]);
    }
    
    /**
     * Ottieni partecipanti di un evento
     */
    private function getParticipants($eventId) {
        $sql = "SELECT ep.*, m.first_name, m.last_name, m.registration_number, m.tax_code
                FROM event_participants ep
                JOIN members m ON ep.member_id = m.id
                WHERE ep.event_id = ?
                ORDER BY m.last_name, m.first_name";
        return $this->db->fetchAll($sql, [$eventId]);
    }
    
    /**
     * Ottieni mezzi di un evento
     */
    private function getVehicles($eventId) {
        $sql = "SELECT ev.*, v.name, v.license_plate, v.serial_number, v.brand, v.model, v.vehicle_type
                FROM event_vehicles ev
                JOIN vehicles v ON ev.vehicle_id = v.id
                WHERE ev.event_id = ?";
        return $this->db->fetchAll($sql, [$eventId]);
    }
    
    /**
     * Registra attività nel log
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
     * Elimina evento (soft delete)
     */
    public function delete($id, $userId) {
        try {
            // Get event details for log
            $event = $this->get($id);
            if (!$event) {
                return ['success' => false, 'message' => 'Evento non trovato'];
            }
            
            // Mark event as cancelled instead of soft delete
            $sql = "UPDATE events SET status = 'annullato' WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            // Log activity
            $this->logActivity($userId, 'events', 'delete', $id, "Eliminato evento: {$event['title']}");
            
            return ['success' => true];
        } catch (\Exception $e) {
            error_log("Errore eliminazione evento: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione'];
        }
    }
    
    /**
     * Aggiungi intervento a un evento
     */
    public function addIntervention($eventId, $data, $userId) {
        try {
            $sql = "INSERT INTO interventions (event_id, title, description, start_time, end_time, location, status,
                    latitude, longitude, full_address, municipality)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $eventId,
                $data['title'],
                $data['description'] ?? null,
                $data['start_time'],
                $data['end_time'] ?? null,
                $data['location'] ?? null,
                $data['status'] ?? 'in_corso',
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['full_address'] ?? null,
                $data['municipality'] ?? null
            ];
            
            $this->db->execute($sql, $params);
            $interventionId = $this->db->lastInsertId();
            
            $this->logActivity($userId, 'interventions', 'create', $interventionId, 'Aggiunto intervento a evento');
            
            return $interventionId;
        } catch (\Exception $e) {
            error_log("Errore aggiunta intervento a evento ID $eventId: " . $e->getMessage());
            error_log("Data: " . json_encode($data));
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Cerca membri disponibili per un evento
     */
    public function getAvailableMembers($eventId, $search = '') {
        try {
            $sql = "SELECT m.id, m.first_name, m.last_name, m.registration_number
                    FROM members m
                    WHERE m.member_status = 'attivo'
                    AND m.id NOT IN (SELECT member_id FROM event_participants WHERE event_id = ?)";
            
            $params = [$eventId];
            
            if (!empty($search)) {
                $sql .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.registration_number LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY m.last_name, m.first_name LIMIT 20";
            
            return $this->db->fetchAll($sql, $params);
        } catch (\Exception $e) {
            error_log("Errore ricerca membri: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Cerca membri disponibili per un intervento
     */
    public function getAvailableMembersForIntervention($interventionId, $search = '') {
        try {
            $sql = "SELECT m.id, m.first_name, m.last_name, m.registration_number
                    FROM members m
                    WHERE m.member_status = 'attivo'
                    AND m.id NOT IN (SELECT member_id FROM intervention_members WHERE intervention_id = ?)";
            
            $params = [$interventionId];
            
            if (!empty($search)) {
                $sql .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.registration_number LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY m.last_name, m.first_name LIMIT 20";
            
            return $this->db->fetchAll($sql, $params);
        } catch (\Exception $e) {
            error_log("Errore ricerca membri per intervento: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Aggiungi partecipante a un evento
     */
    public function addParticipant($eventId, $memberId, $userId) {
        try {
            // Check if already exists
            $sql = "SELECT id FROM event_participants WHERE event_id = ? AND member_id = ?";
            $existing = $this->db->fetchOne($sql, [$eventId, $memberId]);
            
            if ($existing) {
                return ['error' => 'Il partecipante è già presente nell\'evento'];
            }
            
            $sql = "INSERT INTO event_participants (event_id, member_id, role, hours, notes, created_at)
                    VALUES (?, ?, NULL, 0, NULL, NOW())";
            
            $this->db->execute($sql, [$eventId, $memberId]);
            
            $this->logActivity($userId, 'event_participants', 'create', $eventId, 'Aggiunto partecipante a evento');
            
            return true;
        } catch (\Exception $e) {
            error_log("Errore aggiunta partecipante: " . $e->getMessage());
            return ['error' => 'Errore durante l\'aggiunta del partecipante'];
        }
    }
    
    /**
     * Cerca veicoli disponibili per un evento
     */
    public function getAvailableVehicles($eventId, $search = '') {
        try {
            $sql = "SELECT v.id, v.name, v.license_plate, v.serial_number, v.brand, v.model, v.vehicle_type
                    FROM vehicles v
                    WHERE v.status = 'operativo'
                    AND v.id NOT IN (SELECT vehicle_id FROM event_vehicles WHERE event_id = ?)";
            
            $params = [$eventId];
            
            if (!empty($search)) {
                $sql .= " AND (v.name LIKE ? OR v.license_plate LIKE ? OR v.serial_number LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY v.name LIMIT 20";
            
            return $this->db->fetchAll($sql, $params);
        } catch (\Exception $e) {
            error_log("Errore ricerca veicoli: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Aggiungi veicolo a un evento
     */
    public function addVehicle($eventId, $vehicleId, $userId) {
        try {
            // Check if already exists
            $sql = "SELECT id FROM event_vehicles WHERE event_id = ? AND vehicle_id = ?";
            $existing = $this->db->fetchOne($sql, [$eventId, $vehicleId]);
            
            if ($existing) {
                return ['error' => 'Il veicolo è già presente nell\'evento'];
            }
            
            $sql = "INSERT INTO event_vehicles (event_id, vehicle_id, driver_name, hours, km_traveled, notes, created_at)
                    VALUES (?, ?, NULL, 0, 0, NULL, NOW())";
            
            $this->db->execute($sql, [$eventId, $vehicleId]);
            
            $this->logActivity($userId, 'event_vehicles', 'create', $eventId, 'Aggiunto veicolo a evento');
            
            return true;
        } catch (\Exception $e) {
            error_log("Errore aggiunta veicolo: " . $e->getMessage());
            return ['error' => 'Errore durante l\'aggiunta del veicolo'];
        }
    }
    
    /**
     * Aggiungi partecipante a un intervento
     */
    public function addInterventionParticipant($interventionId, $memberId, $role = null, $userId = null) {
        try {
            // Check if already exists
            $sql = "SELECT id FROM intervention_members WHERE intervention_id = ? AND member_id = ?";
            $existing = $this->db->fetchOne($sql, [$interventionId, $memberId]);
            
            if ($existing) {
                return ['error' => 'Il partecipante è già presente nell\'intervento'];
            }
            
            // Note: hours_worked is initialized to 0 and will be updated later as work progresses
            $sql = "INSERT INTO intervention_members (intervention_id, member_id, role, hours_worked)
                    VALUES (?, ?, ?, 0)";
            
            $this->db->execute($sql, [$interventionId, $memberId, $role]);
            
            if ($userId) {
                $this->logActivity($userId, 'intervention_members', 'create', $interventionId, 'Aggiunto partecipante a intervento');
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Errore aggiunta partecipante a intervento: " . $e->getMessage());
            return ['error' => 'Errore durante l\'aggiunta del partecipante'];
        }
    }
    
    /**
     * Aggiungi veicolo a un intervento
     */
    public function addInterventionVehicle($interventionId, $vehicleId, $userId = null) {
        try {
            // Check if already exists
            $sql = "SELECT id FROM intervention_vehicles WHERE intervention_id = ? AND vehicle_id = ?";
            $existing = $this->db->fetchOne($sql, [$interventionId, $vehicleId]);
            
            if ($existing) {
                return ['error' => 'Il veicolo è già presente nell\'intervento'];
            }
            
            // Note: km_start and km_end are initialized to NULL and will be recorded when the vehicle departs/returns
            $sql = "INSERT INTO intervention_vehicles (intervention_id, vehicle_id, km_start, km_end)
                    VALUES (?, ?, NULL, NULL)";
            
            $this->db->execute($sql, [$interventionId, $vehicleId]);
            
            if ($userId) {
                $this->logActivity($userId, 'intervention_vehicles', 'create', $interventionId, 'Aggiunto veicolo a intervento');
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Errore aggiunta veicolo a intervento: " . $e->getMessage());
            return ['error' => 'Errore durante l\'aggiunta del veicolo'];
        }
    }
    
    /**
     * Ottieni dettaglio completo di un intervento
     */
    public function getIntervention($interventionId) {
        try {
            $sql = "SELECT * FROM interventions WHERE id = ?";
            $intervention = $this->db->fetchOne($sql, [$interventionId]);
            
            if (!$intervention) {
                return false;
            }
            
            // Carica partecipanti dell'intervento
            $sql = "SELECT im.*, m.first_name, m.last_name, m.registration_number
                    FROM intervention_members im
                    JOIN members m ON im.member_id = m.id
                    WHERE im.intervention_id = ?
                    ORDER BY m.last_name, m.first_name";
            $intervention['participants'] = $this->db->fetchAll($sql, [$interventionId]);
            
            // Carica veicoli dell'intervento
            $sql = "SELECT iv.*, v.name, v.license_plate, v.serial_number, v.brand, v.model, v.vehicle_type
                    FROM intervention_vehicles iv
                    JOIN vehicles v ON iv.vehicle_id = v.id
                    WHERE iv.intervention_id = ?";
            $intervention['vehicles'] = $this->db->fetchAll($sql, [$interventionId]);
            
            return $intervention;
        } catch (\Exception $e) {
            error_log("Errore recupero intervento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna intervento
     */
    public function updateIntervention($interventionId, $data, $userId) {
        try {
            $sql = "UPDATE interventions SET
                title = ?, description = ?, start_time = ?, end_time = ?,
                location = ?, status = ?, latitude = ?, longitude = ?, full_address = ?, municipality = ?
                WHERE id = ?";
            
            $params = [
                $data['title'],
                $data['description'] ?? null,
                $data['start_time'],
                $data['end_time'] ?? null,
                $data['location'] ?? null,
                $data['status'] ?? 'in_corso',
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['full_address'] ?? null,
                $data['municipality'] ?? null,
                $interventionId
            ];
            
            $this->db->execute($sql, $params);
            
            $this->logActivity($userId, 'interventions', 'update', $interventionId, 'Aggiornato intervento');
            
            return true;
        } catch (\Exception $e) {
            error_log("Errore aggiornamento intervento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Chiudi intervento con esito
     */
    public function closeIntervention($interventionId, $report, $endTime = null, $userId = null) {
        try {
            // Se non specificato, usa il timestamp corrente come end_time
            if (empty($endTime)) {
                $endTime = date('Y-m-d H:i:s');
            }
            
            $sql = "UPDATE interventions SET
                status = 'concluso', report = ?, end_time = ?
                WHERE id = ?";
            
            $params = [$report, $endTime, $interventionId];
            
            $this->db->execute($sql, $params);
            
            if ($userId) {
                $this->logActivity($userId, 'interventions', 'close', $interventionId, 'Chiuso intervento con esito');
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Errore chiusura intervento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Riapri intervento concluso
     */
    public function reopenIntervention($interventionId, $userId) {
        try {
            $sql = "UPDATE interventions SET
                status = 'in_corso', end_time = NULL
                WHERE id = ?";
            
            $this->db->execute($sql, [$interventionId]);
            
            $this->logActivity($userId, 'interventions', 'reopen', $interventionId, 'Riaperto intervento');
            
            return true;
        } catch (\Exception $e) {
            error_log("Errore riapertura intervento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica se ci sono interventi attivi (in_corso o sospeso) per un evento
     * Returns true if there are active interventions or if an error occurs (fail-safe)
     */
    public function hasActiveInterventions($eventId) {
        try {
            $sql = "SELECT COUNT(*) as count 
                    FROM interventions 
                    WHERE event_id = ? 
                    AND status IN ('in_corso', 'sospeso')";
            
            $result = $this->db->fetchOne($sql, [$eventId]);
            
            return isset($result['count']) && $result['count'] > 0;
        } catch (\Exception $e) {
            error_log("Errore verifica interventi attivi: " . $e->getMessage());
            // Fail-safe: assume there are active interventions to prevent accidental closure
            return true;
        }
    }
    
    /**
     * Ottieni lista interventi attivi per un evento
     */
    public function getActiveInterventions($eventId) {
        try {
            $sql = "SELECT id, title, status 
                    FROM interventions 
                    WHERE event_id = ? 
                    AND status IN ('in_corso', 'sospeso')
                    ORDER BY start_time DESC";
            
            return $this->db->fetchAll($sql, [$eventId]);
        } catch (\Exception $e) {
            error_log("Errore recupero interventi attivi: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Chiusura rapida evento - aggiorna solo descrizione, data fine e stato
     */
    public function quickClose($id, $description, $endDate, $userId) {
        // Verifica se ci sono interventi ancora in corso o sospesi
        if ($this->hasActiveInterventions($id)) {
            throw new \Exception('Non è possibile chiudere l\'evento perché ci sono ancora interventi in corso o sospesi.');
        }
        
        $sql = "UPDATE events SET
            description = ?, end_date = ?, status = 'concluso', updated_at = NOW()
            WHERE id = ?";
        
        $params = [
            $description,
            $endDate,
            $id
        ];
        
        $this->db->execute($sql, $params);
        
        $this->logActivity($userId, 'event', 'quick_close', $id, 'Chiuso rapidamente evento ID: ' . $id);
        
        return true;
    }
    
    /**
     * Send email notification to provincial civil protection
     */
    public function sendProvinceEmail($eventId, $userId) {
        try {
            // Get event details
            $event = $this->get($eventId);
            if (!$event) {
                throw new \Exception('Evento non trovato');
            }
            
            // Check if email should be sent for this event type
            // Do NOT send email for Attività and Servizio
            if (in_array($event['event_type'], ['attivita', 'servizio'])) {
                error_log("Email non inviata alla Provincia: evento di tipo " . $event['event_type']);
                return false;
            }
            
            // Get association data including province email
            $association = $this->db->fetchOne("SELECT * FROM association ORDER BY id ASC LIMIT 1");
            $provinceEmail = $association['provincial_civil_protection_email'] ?? null;
            
            if (empty($provinceEmail)) {
                throw new \Exception('Email Ufficio Provinciale non configurata');
            }
            
            // Generate secure token and access code
            $accessToken = bin2hex(random_bytes(32)); // 64 character hex token
            $accessCode = $this->generateAccessCode(); // 8 alphanumeric characters
            
            // Update event with token and code
            $sql = "UPDATE events SET 
                    province_access_token = ?,
                    province_access_code = ?
                    WHERE id = ?";
            $this->db->execute($sql, [$accessToken, $accessCode, $eventId]);
            
            // Prepare email subject with proper prefix
            $eventTypeLabels = [
                'emergenza' => 'Emergenza',
                'esercitazione' => 'Esercitazione (Prova di Soccorso)',
                'attivita' => 'Attività',
                'servizio' => 'Servizio'
            ];
            $eventTypeLabel = $eventTypeLabels[$event['event_type']] ?? $event['event_type'];
            
            // Add event type prefix to subject for Emergenza and Esercitazione
            $emailSubject = $eventTypeLabel . " - " . $event['title'];
            
            // Get base URL from config
            $baseUrl = $this->config['email']['base_url'] ?? '';
            if (empty($baseUrl)) {
                throw new \Exception('Base URL non configurato nelle impostazioni email');
            }
            // Ensure HTTPS in production
            if (strpos($baseUrl, 'http://') === 0 && strpos($baseUrl, 'localhost') === false) {
                error_log('Warning: Using HTTP instead of HTTPS for province access URL');
            }
            $accessUrl = rtrim($baseUrl, '/') . '/public/province_event_view.php?token=' . $accessToken;
            
            // Build email body
            $emailBody = $this->buildProvinceEmailTemplate(
                $event,
                $eventTypeLabel,
                $accessUrl,
                $accessCode,
                $association
            );
            
            // Send email using EmailService
            $emailService = new \EasyVol\Services\EmailService($this->db, $this->config);
            
            $emailOptions = [
                'cc' => $association['email'] ?? null,
                'reply_to' => $association['email'] ?? null
            ];
            
            $emailSent = $emailService->sendEmail($provinceEmail, $emailSubject, $emailBody, $emailOptions);
            
            // Update event with email status
            $emailStatus = $emailSent ? 'success' : 'failure';
            $sql = "UPDATE events SET 
                    province_email_sent = 1,
                    province_email_sent_at = NOW(),
                    province_email_sent_by = ?,
                    province_email_status = ?
                    WHERE id = ?";
            $this->db->execute($sql, [$userId, $emailStatus, $eventId]);
            
            $this->logActivity($userId, 'event', 'province_email_sent', $eventId, 
                'Inviata email alla Provincia per evento: ' . $event['title']);
            
            return $emailSent;
            
        } catch (\Exception $e) {
            error_log("Errore invio email Provincia: " . $e->getMessage());
            
            // Update event with failure status
            try {
                $sql = "UPDATE events SET 
                        province_email_sent = 0,
                        province_email_sent_at = NOW(),
                        province_email_sent_by = ?,
                        province_email_status = ?
                        WHERE id = ?";
                $this->db->execute($sql, [$userId, 'failure: ' . $e->getMessage(), $eventId]);
            } catch (\Exception $updateError) {
                error_log("Errore aggiornamento stato email: " . $updateError->getMessage());
            }
            
            throw $e;
        }
    }
    
    /**
     * Generate random 8-character alphanumeric access code
     */
    private function generateAccessCode() {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        $max = strlen($characters) - 1;
        for ($i = 0; $i < 8; $i++) {
            $code .= $characters[random_int(0, $max)];
        }
        return $code;
    }
    
    /**
     * Build HTML email template for province notification
     */
    private function buildProvinceEmailTemplate($event, $eventTypeLabel, $accessUrl, $accessCode, $association) {
        $associationName = htmlspecialchars($association['name'] ?? 'Associazione');
        $eventTitle = htmlspecialchars($event['title']);
        $eventType = htmlspecialchars($eventTypeLabel);
        $startDate = date('d/m/Y H:i', strtotime($event['start_date']));
        $location = htmlspecialchars($event['location'] ?? 'Non specificato');
        $description = nl2br(htmlspecialchars($event['description'] ?? 'Nessuna descrizione'));
        
        $html = <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifica Nuovo Evento</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
        .event-details { background-color: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .event-details strong { display: inline-block; min-width: 120px; }
        .access-info { background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .access-code { font-size: 24px; font-weight: bold; color: #0d6efd; text-align: center; padding: 10px; background-color: white; border: 2px dashed #0d6efd; margin: 10px 0; }
        .button { display: inline-block; background-color: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .info-box { background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Notifica Nuovo Evento</h1>
            <p>Ufficio Provinciale di Protezione Civile</p>
        </div>
        <div class="content">
            <p>Gentile Ufficio Provinciale,</p>
            <p>l'associazione <strong>{$associationName}</strong> ha creato un nuovo evento che richiede la vostra attenzione.</p>
            
            <div class="event-details">
                <h3>📋 Dettagli Evento</h3>
                <p><strong>Titolo:</strong> {$eventTitle}</p>
                <p><strong>Tipo Evento:</strong> {$eventType}</p>
                <p><strong>Data e Ora Inizio:</strong> {$startDate}</p>
                <p><strong>Località:</strong> {$location}</p>
                <p><strong>Descrizione:</strong><br>{$description}</p>
            </div>
            
            <div class="access-info">
                <h3>🔐 Accesso alla Pagina di Monitoraggio</h3>
                <p>Per accedere ai dettagli completi dell'evento e agli interventi associati, utilizzare il seguente link e codice:</p>
                
                <p style="text-align: center;">
                    <a href="{$accessUrl}" class="button">Accedi alla Pagina Evento</a>
                </p>
                
                <p><strong>Codice di Accesso (richiesto):</strong></p>
                <div class="access-code">{$accessCode}</div>
                
                <p><small><em>Nota: Il codice di accesso sarà richiesto per visualizzare i dati dell'evento.</em></small></p>
            </div>
            
            <div class="info-box">
                <h4>ℹ️ Cosa potete visualizzare:</h4>
                <ul>
                    <li><strong>Dati generali dell'evento:</strong> Titolo, tipo, date, località e descrizione</li>
                    <li><strong>Elenco degli interventi:</strong> Tutti gli interventi associati all'evento con relativi dettagli</li>
                    <li><strong>Volontari partecipanti:</strong> Per motivi di privacy, visualizzerete solo i <strong>codici fiscali</strong> dei volontari, non i nomi e cognomi</li>
                    <li><strong>Download Excel:</strong> Potete scaricare file Excel suddivisi per giorni contenenti i codici fiscali dei volontari partecipanti per ogni giornata dell'evento</li>
                </ul>
            </div>
        </div>
        <div class="footer">
            <p>Questo messaggio è stato inviato automaticamente dal sistema di gestione di {$associationName}</p>
            <p>Per qualsiasi informazione, rispondere a questa email o contattare direttamente l'associazione.</p>
        </div>
    </div>
</body>
</html>
HTML;
        
        return $html;
    }
}
