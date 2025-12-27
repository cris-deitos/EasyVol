<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Utils\VehicleIdentifier;

/**
 * Scheduler Sync Controller
 * 
 * Sincronizza automaticamente le scadenze da vari moduli allo scadenziario
 * - Qualifiche/Corsi membri (member_courses.expiry_date)
 * - Patenti membri (member_licenses.expiry_date)
 * - Assicurazioni veicoli (vehicles.insurance_expiry)
 * - Revisioni veicoli (vehicles.inspection_expiry)
 */
class SchedulerSyncController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Sincronizza scadenza qualifica/corso membro
     * Solo per soci attivi - se il socio non è attivo, rimuove l'item dallo scadenziario
     * 
     * @param int $courseId ID del corso
     * @param int $memberId ID del membro
     * @return bool
     */
    public function syncQualificationExpiry($courseId, $memberId) {
        try {
            // Ottieni dettagli del corso con stato del membro
            $sql = "SELECT mc.*, m.first_name, m.last_name, m.member_status 
                    FROM member_courses mc
                    JOIN members m ON mc.member_id = m.id
                    WHERE mc.id = ?";
            $course = $this->db->fetchOne($sql, [$courseId]);
            
            if (!$course || !$course['expiry_date']) {
                return true; // Nessuna scadenza da sincronizzare
            }
            
            // Se il socio non è attivo, rimuovi l'item dallo scadenziario se esiste
            if ($course['member_status'] !== 'attivo') {
                return $this->removeSchedulerItem('qualification', $courseId);
            }
            
            // Verifica se esiste già un item per questa qualifica
            $existing = $this->findSchedulerItem('qualification', $courseId);
            
            $title = "Scadenza Qualifica: {$course['course_name']} - {$course['first_name']} {$course['last_name']}";
            $description = "La qualifica '{$course['course_name']}' del socio {$course['first_name']} {$course['last_name']} scade il {$course['expiry_date']}.";
            
            if ($existing) {
                // Aggiorna item esistente
                return $this->updateSchedulerItem(
                    $existing['id'],
                    $title,
                    $description,
                    $course['expiry_date'],
                    'qualifiche'
                );
            } else {
                // Crea nuovo item
                return $this->createSchedulerItem(
                    $title,
                    $description,
                    $course['expiry_date'],
                    'qualifiche',
                    'qualification',
                    $courseId
                );
            }
            
        } catch (\Exception $e) {
            error_log("Errore sincronizzazione qualifica: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sincronizza scadenza patente membro
     * Solo per soci attivi - se il socio non è attivo, rimuove l'item dallo scadenziario
     * 
     * @param int $licenseId ID della patente
     * @param int $memberId ID del membro
     * @return bool
     */
    public function syncLicenseExpiry($licenseId, $memberId) {
        try {
            // Ottieni dettagli della patente con stato del membro
            $sql = "SELECT ml.*, m.first_name, m.last_name, m.member_status 
                    FROM member_licenses ml
                    JOIN members m ON ml.member_id = m.id
                    WHERE ml.id = ?";
            $license = $this->db->fetchOne($sql, [$licenseId]);
            
            if (!$license || !$license['expiry_date']) {
                return true; // Nessuna scadenza da sincronizzare
            }
            
            // Se il socio non è attivo, rimuovi l'item dallo scadenziario se esiste
            if ($license['member_status'] !== 'attivo') {
                return $this->removeSchedulerItem('license', $licenseId);
            }
            
            // Verifica se esiste già un item per questa patente
            $existing = $this->findSchedulerItem('license', $licenseId);
            
            $title = "Scadenza Patente {$license['license_type']}: {$license['first_name']} {$license['last_name']}";
            $description = "La patente {$license['license_type']} del socio {$license['first_name']} {$license['last_name']} scade il {$license['expiry_date']}.";
            
            if ($existing) {
                // Aggiorna item esistente
                return $this->updateSchedulerItem(
                    $existing['id'],
                    $title,
                    $description,
                    $license['expiry_date'],
                    'patenti'
                );
            } else {
                // Crea nuovo item
                return $this->createSchedulerItem(
                    $title,
                    $description,
                    $license['expiry_date'],
                    'patenti',
                    'license',
                    $licenseId
                );
            }
            
        } catch (\Exception $e) {
            error_log("Errore sincronizzazione patente: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Build vehicle identifier from license plate, brand/model, or serial number
     * 
     * @param array $vehicle Vehicle data array
     * @return string Vehicle identifier
     */
    private function buildVehicleIdentifier($vehicle) {
        $vehicleIdent = $vehicle['license_plate'] ?? '';
        if (empty($vehicleIdent)) {
            $vehicleIdent = trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? ''));
        }
        if (empty($vehicleIdent)) {
            $vehicleIdent = $vehicle['serial_number'] ?? "Mezzo ID {$vehicle['id']}";
        }
        return $vehicleIdent;
    }
    
    /**
     * Sincronizza scadenza assicurazione veicolo
     * 
     * @param int $vehicleId ID del veicolo
     * @return bool
     */
    public function syncInsuranceExpiry($vehicleId) {
        try {
            // Ottieni dettagli del veicolo
            $sql = "SELECT * FROM vehicles WHERE id = ?";
            $vehicle = $this->db->fetchOne($sql, [$vehicleId]);
            
            if (!$vehicle || !$vehicle['insurance_expiry']) {
                return true; // Nessuna scadenza da sincronizzare
            }
            
            // Verifica se esiste già un item per questa assicurazione
            $existing = $this->findSchedulerItem('insurance', $vehicleId);
            
            $vehicleIdent = VehicleIdentifier::build($vehicle);
            
            $title = "Scadenza Assicurazione: {$vehicleIdent}";
            $description = "L'assicurazione del mezzo {$vehicleIdent} ";
            if (!empty($vehicle['license_plate'])) {
                $description .= "(targa: {$vehicle['license_plate']}) ";
            }
            $description .= "scade il {$vehicle['insurance_expiry']}.";
            
            if ($existing) {
                // Aggiorna item esistente
                return $this->updateSchedulerItem(
                    $existing['id'],
                    $title,
                    $description,
                    $vehicle['insurance_expiry'],
                    'veicoli'
                );
            } else {
                // Crea nuovo item
                return $this->createSchedulerItem(
                    $title,
                    $description,
                    $vehicle['insurance_expiry'],
                    'veicoli',
                    'insurance',
                    $vehicleId
                );
            }
            
        } catch (\Exception $e) {
            error_log("Errore sincronizzazione assicurazione: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sincronizza scadenza revisione veicolo
     * 
     * @param int $vehicleId ID del veicolo
     * @return bool
     */
    public function syncInspectionExpiry($vehicleId) {
        try {
            // Ottieni dettagli del veicolo
            $sql = "SELECT * FROM vehicles WHERE id = ?";
            $vehicle = $this->db->fetchOne($sql, [$vehicleId]);
            
            if (!$vehicle || !$vehicle['inspection_expiry']) {
                return true; // Nessuna scadenza da sincronizzare
            }
            
            // Verifica se esiste già un item per questa revisione
            $existing = $this->findSchedulerItem('inspection', $vehicleId);
            
            $vehicleIdent = VehicleIdentifier::build($vehicle);
            
            $title = "Scadenza Revisione: {$vehicleIdent}";
            $description = "La revisione del mezzo {$vehicleIdent} ";
            if (!empty($vehicle['license_plate'])) {
                $description .= "(targa: {$vehicle['license_plate']}) ";
            }
            $description .= "scade il {$vehicle['inspection_expiry']}.";
            
            if ($existing) {
                // Aggiorna item esistente
                return $this->updateSchedulerItem(
                    $existing['id'],
                    $title,
                    $description,
                    $vehicle['inspection_expiry'],
                    'veicoli'
                );
            } else {
                // Crea nuovo item
                return $this->createSchedulerItem(
                    $title,
                    $description,
                    $vehicle['inspection_expiry'],
                    'veicoli',
                    'inspection',
                    $vehicleId
                );
            }
            
        } catch (\Exception $e) {
            error_log("Errore sincronizzazione revisione: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sincronizza scadenza documento veicolo
     * 
     * @param int $documentId ID del documento
     * @param int $vehicleId ID del veicolo
     * @return bool
     */
    public function syncVehicleDocumentExpiry($documentId, $vehicleId) {
        try {
            // Ottieni dettagli del documento con dati veicolo
            $sql = "SELECT vd.*, v.license_plate, v.serial_number, v.brand, v.model, v.id as vehicle_id
                    FROM vehicle_documents vd
                    JOIN vehicles v ON vd.vehicle_id = v.id
                    WHERE vd.id = ?";
            $document = $this->db->fetchOne($sql, [$documentId]);
            
            if (!$document || !$document['expiry_date']) {
                return true; // Nessuna scadenza da sincronizzare
            }
            
            // Verifica se esiste già un item per questo documento
            $existing = $this->findSchedulerItem('vehicle_document', $documentId);
            
            // Build vehicle identifier using the utility
            $vehicleIdent = VehicleIdentifier::build($document);
            
            $title = "Scadenza Documento {$document['document_type']}: {$vehicleIdent}";
            $description = "Il documento '{$document['document_type']}' del mezzo {$vehicleIdent} ";
            if (!empty($document['license_plate'])) {
                $description .= "(targa: {$document['license_plate']}) ";
            }
            $description .= "scade il {$document['expiry_date']}.";
            
            if ($existing) {
                // Aggiorna item esistente
                return $this->updateSchedulerItem(
                    $existing['id'],
                    $title,
                    $description,
                    $document['expiry_date'],
                    'documenti_veicoli'
                );
            } else {
                // Crea nuovo item
                return $this->createSchedulerItem(
                    $title,
                    $description,
                    $document['expiry_date'],
                    'documenti_veicoli',
                    'vehicle_document',
                    $documentId
                );
            }
            
        } catch (\Exception $e) {
            error_log("Errore sincronizzazione documento veicolo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Trova un item dello scadenziario esistente per un riferimento
     * 
     * @param string $referenceType Tipo di riferimento
     * @param int $referenceId ID del riferimento
     * @return array|false
     */
    private function findSchedulerItem($referenceType, $referenceId) {
        $sql = "SELECT * FROM scheduler_items 
                WHERE reference_type = ? AND reference_id = ?
                LIMIT 1";
        return $this->db->fetchOne($sql, [$referenceType, $referenceId]);
    }
    
    /**
     * Crea un nuovo item nello scadenziario
     * 
     * @param string $title Titolo
     * @param string $description Descrizione
     * @param string $dueDate Data scadenza
     * @param string $category Categoria
     * @param string $referenceType Tipo di riferimento
     * @param int $referenceId ID del riferimento
     * @return bool
     */
    private function createSchedulerItem($title, $description, $dueDate, $category, $referenceType, $referenceId) {
        try {
            $sql = "INSERT INTO scheduler_items (
                title, description, due_date, category, priority, status,
                reference_type, reference_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            // Calcola priorità in base alla vicinanza della scadenza
            $priority = $this->calculatePriority($dueDate);
            
            $params = [
                $title,
                $description,
                $dueDate,
                $category,
                $priority,
                'in_attesa',
                $referenceType,
                $referenceId
            ];
            
            $this->db->execute($sql, $params);
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore creazione scheduler item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiorna un item esistente nello scadenziario
     * 
     * @param int $itemId ID dell'item
     * @param string $title Titolo
     * @param string $description Descrizione
     * @param string $dueDate Data scadenza
     * @param string $category Categoria
     * @return bool
     */
    private function updateSchedulerItem($itemId, $title, $description, $dueDate, $category) {
        try {
            // Calcola priorità in base alla vicinanza della scadenza
            $priority = $this->calculatePriority($dueDate);
            
            $sql = "UPDATE scheduler_items SET
                title = ?,
                description = ?,
                due_date = ?,
                category = ?,
                priority = ?,
                updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $title,
                $description,
                $dueDate,
                $category,
                $priority,
                $itemId
            ];
            
            $this->db->execute($sql, $params);
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore aggiornamento scheduler item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calcola priorità in base alla vicinanza della scadenza
     * 
     * @param string $dueDate Data scadenza
     * @return string Priority level
     */
    private function calculatePriority($dueDate) {
        $now = new \DateTime();
        $due = new \DateTime($dueDate);
        $diff = $now->diff($due);
        $days = (int)$diff->format('%r%a'); // Numero di giorni (negativo se passato)
        
        if ($days < 0) {
            return 'urgente'; // Già scaduto
        } elseif ($days <= 7) {
            return 'urgente'; // Scade entro 7 giorni
        } elseif ($days <= 30) {
            return 'alta'; // Scade entro 30 giorni
        } elseif ($days <= 60) {
            return 'media'; // Scade entro 60 giorni
        } else {
            return 'bassa'; // Scade tra più di 60 giorni
        }
    }
    
    /**
     * Rimuove un item dallo scadenziario quando il riferimento viene eliminato
     * 
     * @param string $referenceType Tipo di riferimento
     * @param int $referenceId ID del riferimento
     * @return bool
     */
    public function removeSchedulerItem($referenceType, $referenceId) {
        try {
            $sql = "DELETE FROM scheduler_items 
                    WHERE reference_type = ? AND reference_id = ?";
            $this->db->execute($sql, [$referenceType, $referenceId]);
            return true;
        } catch (\Exception $e) {
            error_log("Errore rimozione scheduler item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni l'URL e l'ID dell'entità di riferimento per un item dello scadenziario
     * 
     * @param string $referenceType Tipo di riferimento
     * @param int $referenceId ID del riferimento
     * @return array|null Array con 'url' e 'entity_id' (member_id o vehicle_id), oppure null
     */
    public function getReferenceLink($referenceType, $referenceId) {
        try {
            switch ($referenceType) {
                case 'qualification':
                    // Trova il member_id dalla tabella member_courses
                    $sql = "SELECT member_id FROM member_courses WHERE id = ?";
                    $result = $this->db->fetchOne($sql, [$referenceId]);
                    if ($result) {
                        return [
                            'url' => "member_view.php?id={$result['member_id']}#courses",
                            'entity_id' => $result['member_id'],
                            'entity_type' => 'member'
                        ];
                    }
                    break;
                    
                case 'license':
                    // Trova il member_id dalla tabella member_licenses
                    $sql = "SELECT member_id FROM member_licenses WHERE id = ?";
                    $result = $this->db->fetchOne($sql, [$referenceId]);
                    if ($result) {
                        return [
                            'url' => "member_view.php?id={$result['member_id']}#licenses",
                            'entity_id' => $result['member_id'],
                            'entity_type' => 'member'
                        ];
                    }
                    break;
                    
                case 'insurance':
                case 'inspection':
                    // L'ID è direttamente il vehicle_id
                    return [
                        'url' => "vehicle_view.php?id={$referenceId}",
                        'entity_id' => $referenceId,
                        'entity_type' => 'vehicle'
                    ];
                    
                case 'vehicle_document':
                    // Trova il vehicle_id dalla tabella vehicle_documents
                    $sql = "SELECT vehicle_id FROM vehicle_documents WHERE id = ?";
                    $result = $this->db->fetchOne($sql, [$referenceId]);
                    if ($result) {
                        return [
                            'url' => "vehicle_view.php?id={$result['vehicle_id']}#documents",
                            'entity_id' => $result['vehicle_id'],
                            'entity_type' => 'vehicle'
                        ];
                    }
                    break;
            }
            
            return null;
        } catch (\Exception $e) {
            error_log("Errore ottenimento link riferimento: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Rimuove tutti gli item dallo scadenziario per un membro specifico
     * Usato quando un membro diventa non attivo
     * Ottimizzato con operazioni bulk per performance migliori
     * 
     * @param int $memberId ID del membro
     * @return bool
     */
    public function removeAllMemberSchedulerItems($memberId) {
        try {
            // Rimuovi tutti gli item qualifiche/corsi del membro in una singola query
            $sql = "DELETE si FROM scheduler_items si
                    INNER JOIN member_courses mc ON si.reference_type = 'qualification' AND si.reference_id = mc.id
                    WHERE mc.member_id = ?";
            $this->db->execute($sql, [$memberId]);
            
            // Rimuovi tutti gli item patenti del membro in una singola query
            $sql = "DELETE si FROM scheduler_items si
                    INNER JOIN member_licenses ml ON si.reference_type = 'license' AND si.reference_id = ml.id
                    WHERE ml.member_id = ?";
            $this->db->execute($sql, [$memberId]);
            
            return true;
        } catch (\Exception $e) {
            error_log("Errore rimozione scheduler items per membro: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sincronizza tutti gli item dallo scadenziario per un membro specifico
     * Usato quando un membro diventa attivo
     * 
     * @param int $memberId ID del membro
     * @return bool
     */
    public function syncAllMemberExpiries($memberId) {
        try {
            // Sincronizza tutti i corsi del membro
            $sql = "SELECT id FROM member_courses WHERE member_id = ? AND expiry_date IS NOT NULL";
            $courses = $this->db->fetchAll($sql, [$memberId]);
            foreach ($courses as $course) {
                $this->syncQualificationExpiry($course['id'], $memberId);
            }
            
            // Sincronizza tutte le patenti del membro
            $sql = "SELECT id FROM member_licenses WHERE member_id = ? AND expiry_date IS NOT NULL";
            $licenses = $this->db->fetchAll($sql, [$memberId]);
            foreach ($licenses as $license) {
                $this->syncLicenseExpiry($license['id'], $memberId);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Errore sincronizzazione scheduler items per membro: " . $e->getMessage());
            return false;
        }
    }
}
