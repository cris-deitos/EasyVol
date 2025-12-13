<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use PDO;
use PDOException;

/**
 * Import Controller
 * 
 * Gestisce l'import di dati CSV da struttura MONOTABELLA a MULTI-TABELLA
 */
class ImportController {
    private $db;
    private $config;
    private $logId;
    
    /**
     * Constructor
     * 
     * @param Database $db Database instance
     * @param array $config Configuration
     */
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Rileva encoding del file CSV
     * 
     * @param string $filePath Path al file
     * @return string Encoding rilevato
     */
    public function detectEncoding($filePath) {
        // Leggi solo i primi 8KB per rilevare encoding (più efficiente)
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return 'UTF-8';
        }
        
        $sample = fread($handle, 8192);
        fclose($handle);
        
        // Check BOM per UTF-8
        if (substr($sample, 0, 3) === "\xEF\xBB\xBF") {
            return 'UTF-8';
        }
        
        // Prova a rilevare encoding
        $encoding = mb_detect_encoding($sample, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        
        return $encoding ?: 'UTF-8';
    }
    
    /**
     * Leggi CSV con encoding detection
     * 
     * @param string $filePath Path al file
     * @param string $delimiter Delimitatore CSV
     * @return array Array di righe
     */
    public function readCsv($filePath, $delimiter = ',') {
        $encoding = $this->detectEncoding($filePath);
        
        $rows = [];
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            throw new \Exception("Impossibile aprire il file CSV");
        }
        
        try {
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                if ($encoding !== 'UTF-8') {
                    // Converti da encoding rilevato a UTF-8
                    $row = array_map(function($cell) use ($encoding) {
                        return mb_convert_encoding($cell, 'UTF-8', $encoding);
                    }, $row);
                }
                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }
        
        return ['rows' => $rows, 'encoding' => $encoding];
    }
    
    /**
     * Parse CSV e prepara dati per preview
     * 
     * @param string $filePath Path al file
     * @param string $delimiter Delimitatore
     * @return array Dati parsati
     */
    public function parseAndPreview($filePath, $delimiter = ',') {
        $result = $this->readCsv($filePath, $delimiter);
        $rows = $result['rows'];
        $encoding = $result['encoding'];
        
        if (empty($rows)) {
            throw new \Exception("File CSV vuoto");
        }
        
        // Prima riga = header
        $headers = array_shift($rows);
        
        // Pulisci headers
        $headers = array_map('trim', $headers);
        
        // Preview prime 10 righe
        $preview = array_slice($rows, 0, 10);
        
        return [
            'headers' => $headers,
            'preview' => $preview,
            'total_rows' => count($rows),
            'encoding' => $encoding
        ];
    }
    
    /**
     * Ottieni campi disponibili per tipo import con tabella di appartenenza
     * 
     * @param string $importType Tipo import
     * @return array Lista campi con tabella
     */
    public function getAvailableFields($importType) {
        $fields = [
            'soci' => [
                'members' => [
                    'registration_number' => 'Numero Matricola',
                    'first_name' => 'Nome',
                    'last_name' => 'Cognome',
                    'birth_date' => 'Data di Nascita',
                    'birth_place' => 'Luogo di Nascita',
                    'birth_province' => 'Provincia di Nascita',
                    'tax_code' => 'Codice Fiscale',
                    'gender' => 'Sesso (M/F)',
                    'nationality' => 'Nazionalità',
                    'worker_type' => 'Tipo Lavoratore (studente/dipendente_privato/dipendente_pubblico/lavoratore_autonomo/disoccupato/pensionato)',
                    'education_level' => 'Titolo di Studio (licenza_media/diploma_maturita/laurea_triennale/laurea_magistrale/dottorato)',
                    'registration_date' => 'Data Iscrizione',
                    'approval_date' => 'Data Approvazione',
                    'member_type' => 'Tipo Socio (ordinario/fondatore)',
                    'member_status' => 'Stato Socio',
                    'volunteer_status' => 'Stato Volontario',
                    'notes' => 'Note'
                ],
                'member_contacts' => [
                    'contact_email' => 'Email',
                    'contact_telefono' => 'Telefono Fisso',
                    'contact_cellulare' => 'Cellulare',
                    'contact_pec' => 'PEC'
                ],
                'member_addresses (residenza)' => [
                    'residenza_street' => 'Via Residenza',
                    'residenza_number' => 'Numero Residenza',
                    'residenza_city' => 'Città Residenza',
                    'residenza_province' => 'Provincia Residenza',
                    'residenza_cap' => 'CAP Residenza'
                ],
                'member_addresses (domicilio)' => [
                    'domicilio_street' => 'Via Domicilio',
                    'domicilio_number' => 'Numero Domicilio',
                    'domicilio_city' => 'Città Domicilio',
                    'domicilio_province' => 'Provincia Domicilio',
                    'domicilio_cap' => 'CAP Domicilio'
                ],
                'member_employment' => [
                    'employer_name' => 'Datore di Lavoro',
                    'employer_address' => 'Indirizzo Lavoro',
                    'employer_city' => 'Città Lavoro',
                    'employer_phone' => 'Telefono Lavoro'
                ]
            ],
            'cadetti' => [
                'junior_members' => [
                    'registration_number' => 'Numero Matricola',
                    'first_name' => 'Nome',
                    'last_name' => 'Cognome',
                    'birth_date' => 'Data di Nascita',
                    'birth_place' => 'Luogo di Nascita',
                    'birth_province' => 'Provincia di Nascita',
                    'tax_code' => 'Codice Fiscale',
                    'gender' => 'Sesso (M/F)',
                    'nationality' => 'Nazionalità',
                    'registration_date' => 'Data Iscrizione',
                    'approval_date' => 'Data Approvazione',
                    'member_status' => 'Stato Socio',
                    'notes' => 'Note'
                ],
                'junior_member_contacts' => [
                    'contact_email' => 'Email',
                    'contact_telefono' => 'Telefono Fisso',
                    'contact_cellulare' => 'Cellulare'
                ],
                'junior_member_addresses' => [
                    'residenza_street' => 'Via Residenza',
                    'residenza_number' => 'Numero Residenza',
                    'residenza_city' => 'Città Residenza',
                    'residenza_province' => 'Provincia Residenza',
                    'residenza_cap' => 'CAP Residenza'
                ],
                'junior_member_guardians (padre)' => [
                    'guardian_padre_first_name' => 'Nome Padre',
                    'guardian_padre_last_name' => 'Cognome Padre',
                    'guardian_padre_tax_code' => 'CF Padre',
                    'guardian_padre_phone' => 'Telefono Padre',
                    'guardian_padre_email' => 'Email Padre'
                ],
                'junior_member_guardians (madre)' => [
                    'guardian_madre_first_name' => 'Nome Madre',
                    'guardian_madre_last_name' => 'Cognome Madre',
                    'guardian_madre_tax_code' => 'CF Madre',
                    'guardian_madre_phone' => 'Telefono Madre',
                    'guardian_madre_email' => 'Email Madre'
                ]
            ],
            'mezzi' => [
                'vehicles' => [
                    'vehicle_type' => 'Tipo Veicolo (veicolo/natante/rimorchio)',
                    'name' => 'Nome',
                    'license_plate' => 'Targa',
                    'brand' => 'Marca',
                    'model' => 'Modello',
                    'year' => 'Anno',
                    'serial_number' => 'Numero di Serie',
                    'status' => 'Stato',
                    'insurance_expiry' => 'Scadenza Assicurazione',
                    'inspection_expiry' => 'Scadenza Revisione',
                    'notes' => 'Note'
                ]
            ],
            'attrezzature' => [
                'warehouse_items' => [
                    'code' => 'Codice',
                    'name' => 'Nome',
                    'category' => 'Categoria',
                    'description' => 'Descrizione',
                    'quantity' => 'Quantità',
                    'minimum_quantity' => 'Quantità Minima',
                    'unit' => 'Unità di Misura',
                    'location' => 'Posizione',
                    'qr_code' => 'Codice QR',
                    'barcode' => 'Codice a Barre',
                    'status' => 'Stato',
                    'notes' => 'Note'
                ]
            ]
        ];
        
        return $fields[$importType] ?? [];
    }
    
    /**
     * Mappa colonne CSV a campi database
     * 
     * @param array $headers Headers CSV
     * @param string $importType Tipo import
     * @return array Mappatura suggerita
     */
    public function suggestMapping($headers, $importType) {
        $mappings = [
            'soci' => [
                'matricola' => 'registration_number',
                'nome' => 'first_name',
                'cognome' => 'last_name',
                'data_nascita' => 'birth_date',
                'luogo_nascita' => 'birth_place',
                'provincia_nascita' => 'birth_province',
                'codice_fiscale' => 'tax_code',
                'sesso' => 'gender',
                'nazionalita' => 'nationality',
                'tipo_lavoratore' => 'worker_type',
                'titolo_studio' => 'education_level',
                'data_iscrizione' => 'registration_date',
                'data_approvazione' => 'approval_date',
                'tipo_socio' => 'member_type',
                'stato_socio' => 'member_status',
                'stato_volontario' => 'volunteer_status',
                'email' => 'contact_email',
                'telefono' => 'contact_telefono',
                'cellulare' => 'contact_cellulare',
                'pec' => 'contact_pec',
                'via_residenza' => 'residenza_street',
                'numero_residenza' => 'residenza_number',
                'citta_residenza' => 'residenza_city',
                'provincia_residenza' => 'residenza_province',
                'cap_residenza' => 'residenza_cap',
                'via_domicilio' => 'domicilio_street',
                'numero_domicilio' => 'domicilio_number',
                'citta_domicilio' => 'domicilio_city',
                'provincia_domicilio' => 'domicilio_province',
                'cap_domicilio' => 'domicilio_cap',
                'datore_lavoro' => 'employer_name',
                'indirizzo_lavoro' => 'employer_address',
                'citta_lavoro' => 'employer_city',
                'telefono_lavoro' => 'employer_phone',
                'note' => 'notes'
            ],
            'cadetti' => [
                'matricola' => 'registration_number',
                'nome' => 'first_name',
                'cognome' => 'last_name',
                'data_nascita' => 'birth_date',
                'luogo_nascita' => 'birth_place',
                'provincia_nascita' => 'birth_province',
                'codice_fiscale' => 'tax_code',
                'sesso' => 'gender',
                'nazionalita' => 'nationality',
                'data_iscrizione' => 'registration_date',
                'data_approvazione' => 'approval_date',
                'stato_socio' => 'member_status',
                'email' => 'contact_email',
                'telefono' => 'contact_telefono',
                'cellulare' => 'contact_cellulare',
                'via_residenza' => 'residenza_street',
                'numero_residenza' => 'residenza_number',
                'citta_residenza' => 'residenza_city',
                'provincia_residenza' => 'residenza_province',
                'cap_residenza' => 'residenza_cap',
                'nome_padre' => 'guardian_padre_first_name',
                'cognome_padre' => 'guardian_padre_last_name',
                'cf_padre' => 'guardian_padre_tax_code',
                'telefono_padre' => 'guardian_padre_phone',
                'email_padre' => 'guardian_padre_email',
                'nome_madre' => 'guardian_madre_first_name',
                'cognome_madre' => 'guardian_madre_last_name',
                'cf_madre' => 'guardian_madre_tax_code',
                'telefono_madre' => 'guardian_madre_phone',
                'email_madre' => 'guardian_madre_email',
                'note' => 'notes'
            ],
            'mezzi' => [
                'tipo' => 'vehicle_type',
                'nome' => 'name',
                'targa' => 'license_plate',
                'marca' => 'brand',
                'modello' => 'model',
                'anno' => 'year',
                'numero_serie' => 'serial_number',
                'stato' => 'status',
                'scadenza_assicurazione' => 'insurance_expiry',
                'scadenza_revisione' => 'inspection_expiry',
                'note' => 'notes'
            ],
            'attrezzature' => [
                'codice' => 'code',
                'nome' => 'name',
                'categoria' => 'category',
                'descrizione' => 'description',
                'quantita' => 'quantity',
                'quantita_minima' => 'minimum_quantity',
                'unita' => 'unit',
                'posizione' => 'location',
                'codice_qr' => 'qr_code',
                'codice_barre' => 'barcode',
                'stato' => 'status',
                'note' => 'notes'
            ]
        ];
        
        $mapping = $mappings[$importType] ?? [];
        $result = [];
        
        foreach ($headers as $header) {
            $normalizedHeader = strtolower(trim($header));
            $normalizedHeader = str_replace([' ', '_', '-'], '', $normalizedHeader);
            
            // Cerca corrispondenza
            $matched = null;
            foreach ($mapping as $key => $value) {
                $normalizedKey = str_replace([' ', '_', '-'], '', strtolower($key));
                if ($normalizedHeader === $normalizedKey || 
                    strpos($normalizedHeader, $normalizedKey) !== false ||
                    strpos($normalizedKey, $normalizedHeader) !== false) {
                    $matched = $value;
                    break;
                }
            }
            
            $result[$header] = $matched;
        }
        
        return $result;
    }
    
    /**
     * Esegui import CSV
     * 
     * @param string $filePath Path al file
     * @param string $importType Tipo import
     * @param array $columnMapping Mappatura colonne
     * @param string $delimiter Delimitatore
     * @param int $userId User ID
     * @return array Risultato import
     */
    public function import($filePath, $importType, $columnMapping, $delimiter = ',', $userId = null) {
        // Aumenta tempo esecuzione per import grandi
        set_time_limit(300); // 5 minuti
        
        // Inizio log
        $this->logId = $this->startImportLog($filePath, $importType, $userId);
        
        try {
            $result = $this->readCsv($filePath, $delimiter);
            $rows = $result['rows'];
            $encoding = $result['encoding'];
            
            if (empty($rows)) {
                throw new \Exception("File CSV vuoto");
            }
            
            // Prima riga = header
            $headers = array_shift($rows);
            $headers = array_map('trim', $headers);
            
            // Valida che headers non siano tutti vuoti
            if (empty(array_filter($headers))) {
                throw new \Exception("Header CSV non valido: tutte le colonne sono vuote");
            }
            
            // Update log encoding
            $this->updateImportLog($this->logId, [
                'file_encoding' => $encoding,
                'total_rows' => count($rows)
            ]);
            
            // Inizio transazione
            $conn = $this->db->getConnection();
            $conn->beginTransaction();
            
            $imported = 0;
            $skipped = 0;
            $errors = 0;
            $details = [];
            
            foreach ($rows as $rowIndex => $row) {
                try {
                    // Crea array associativo con mapping
                    $data = [];
                    foreach ($headers as $index => $header) {
                        if (isset($columnMapping[$header]) && $columnMapping[$header]) {
                            $data[$columnMapping[$header]] = isset($row[$index]) ? trim($row[$index]) : '';
                        }
                    }
                    
                    // Import basato su tipo
                    switch ($importType) {
                        case 'soci':
                            $result = $this->importMember($data, $conn);
                            break;
                        case 'cadetti':
                            $result = $this->importJuniorMember($data, $conn);
                            break;
                        case 'mezzi':
                            $result = $this->importVehicle($data, $conn);
                            break;
                        case 'attrezzature':
                            $result = $this->importWarehouseItem($data, $conn);
                            break;
                        default:
                            throw new \Exception("Tipo import non valido");
                    }
                    
                    if ($result['status'] === 'imported') {
                        $imported++;
                        $details[] = [
                            'row' => $rowIndex + 2, // +2 perché 1=header, 0=first data row
                            'status' => 'success',
                            'id' => $result['id']
                        ];
                    } else {
                        $skipped++;
                        $details[] = [
                            'row' => $rowIndex + 2,
                            'status' => 'skipped',
                            'reason' => $result['reason']
                        ];
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $details[] = [
                        'row' => $rowIndex + 2,
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Commit transazione
            $conn->commit();
            
            // Update log
            $status = 'completed';
            if ($errors > 0 && $imported === 0) {
                $status = 'failed';
            } elseif ($errors > 0) {
                $status = 'partial';
            }
            
            $this->completeImportLog($this->logId, [
                'imported_rows' => $imported,
                'skipped_rows' => $skipped,
                'error_rows' => $errors,
                'status' => $status,
                'import_details' => json_encode($details)
            ]);
            
            return [
                'success' => true,
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
                'details' => $details,
                'log_id' => $this->logId
            ];
            
        } catch (\Exception $e) {
            // Rollback transazione
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            
            // Update log con errore
            $this->failImportLog($this->logId, $e->getMessage());
            
            throw $e;
        }
    }
    
    /**
     * Import singolo socio
     * 
     * @param array $data Dati socio
     * @param PDO $conn Connessione DB
     * @return array Risultato
     */
    private function importMember($data, $conn) {
        // Check duplicato via matricola
        if (!empty($data['registration_number'])) {
            $stmt = $conn->prepare("SELECT id FROM members WHERE registration_number = ?");
            $stmt->execute([$data['registration_number']]);
            if ($stmt->fetch()) {
                return ['status' => 'skipped', 'reason' => 'Matricola già esistente'];
            }
        }
        
        // Prepara dati per tabella members
        $memberData = [
            'registration_number' => $data['registration_number'] ?? null,
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'birth_date' => $this->parseDate($data['birth_date'] ?? ''),
            'birth_place' => $data['birth_place'] ?? null,
            'birth_province' => $data['birth_province'] ?? null,
            'tax_code' => $data['tax_code'] ?? null,
            'gender' => $this->parseGender($data['gender'] ?? ''),
            'nationality' => $data['nationality'] ?? 'Italiana',
            'worker_type' => $this->parseWorkerType($data['worker_type'] ?? ''),
            'education_level' => $this->parseEducationLevel($data['education_level'] ?? ''),
            'registration_date' => $this->parseDate($data['registration_date'] ?? ''),
            'approval_date' => $this->parseDate($data['approval_date'] ?? ''),
            'member_type' => $this->parseMemberType($data['member_type'] ?? ''),
            'member_status' => $this->parseMemberStatus($data['member_status'] ?? ''),
            'volunteer_status' => $this->parseVolunteerStatus($data['volunteer_status'] ?? ''),
            'notes' => $data['notes'] ?? null
        ];
        
        // Insert member
        $columns = array_keys($memberData);
        $placeholders = array_map(function($col) { return ":$col"; }, $columns);
        
        $sql = "INSERT INTO members (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        $stmt->execute($memberData);
        $memberId = $conn->lastInsertId();
        
        // Insert contacts
        $contacts = [
            'email' => $data['contact_email'] ?? '',
            'pec' => $data['contact_pec'] ?? '',
            'telefono_fisso' => $data['contact_telefono'] ?? '',
            'cellulare' => $data['contact_cellulare'] ?? ''
        ];
        
        foreach ($contacts as $type => $value) {
            if (!empty($value)) {
                $stmt = $conn->prepare(
                    "INSERT INTO member_contacts (member_id, contact_type, value) VALUES (?, ?, ?)"
                );
                $stmt->execute([$memberId, $type, $value]);
            }
        }
        
        // Insert residenza address
        if (!empty($data['residenza_street']) || !empty($data['residenza_city'])) {
            $stmt = $conn->prepare(
                "INSERT INTO member_addresses (member_id, address_type, street, number, city, province, cap) 
                 VALUES (?, 'residenza', ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $memberId,
                $data['residenza_street'] ?? null,
                $data['residenza_number'] ?? null,
                $data['residenza_city'] ?? null,
                $data['residenza_province'] ?? null,
                $data['residenza_cap'] ?? null
            ]);
        }
        
        // Insert domicilio address (se diverso da residenza)
        if (!empty($data['domicilio_street']) || !empty($data['domicilio_city'])) {
            $stmt = $conn->prepare(
                "INSERT INTO member_addresses (member_id, address_type, street, number, city, province, cap) 
                 VALUES (?, 'domicilio', ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $memberId,
                $data['domicilio_street'] ?? null,
                $data['domicilio_number'] ?? null,
                $data['domicilio_city'] ?? null,
                $data['domicilio_province'] ?? null,
                $data['domicilio_cap'] ?? null
            ]);
        }
        
        // Insert employment
        if (!empty($data['employer_name'])) {
            $stmt = $conn->prepare(
                "INSERT INTO member_employment (member_id, employer_name, employer_address, employer_city, employer_phone) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $memberId,
                $data['employer_name'],
                $data['employer_address'] ?? null,
                $data['employer_city'] ?? null,
                $data['employer_phone'] ?? null
            ]);
        }
        
        return ['status' => 'imported', 'id' => $memberId];
    }
    
    /**
     * Import singolo cadetto
     * 
     * @param array $data Dati cadetto
     * @param PDO $conn Connessione DB
     * @return array Risultato
     */
    private function importJuniorMember($data, $conn) {
        // Check duplicato via matricola
        if (!empty($data['registration_number'])) {
            $stmt = $conn->prepare("SELECT id FROM junior_members WHERE registration_number = ?");
            $stmt->execute([$data['registration_number']]);
            if ($stmt->fetch()) {
                return ['status' => 'skipped', 'reason' => 'Matricola già esistente'];
            }
        }
        
        // Prepara dati per tabella junior_members
        $juniorData = [
            'registration_number' => $data['registration_number'] ?? null,
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'birth_date' => $this->parseDate($data['birth_date'] ?? ''),
            'birth_place' => $data['birth_place'] ?? null,
            'birth_province' => $data['birth_province'] ?? null,
            'tax_code' => $data['tax_code'] ?? null,
            'gender' => $this->parseGender($data['gender'] ?? ''),
            'nationality' => $data['nationality'] ?? 'Italiana',
            'registration_date' => $this->parseDate($data['registration_date'] ?? ''),
            'approval_date' => $this->parseDate($data['approval_date'] ?? ''),
            'member_status' => $this->parseMemberStatus($data['member_status'] ?? ''),
            'notes' => $data['notes'] ?? null
        ];
        
        // Insert junior member
        $columns = array_keys($juniorData);
        $placeholders = array_map(function($col) { return ":$col"; }, $columns);
        
        $sql = "INSERT INTO junior_members (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        $stmt->execute($juniorData);
        $juniorId = $conn->lastInsertId();
        
        // Insert contacts
        $contacts = [
            'email' => $data['contact_email'] ?? '',
            'telefono_fisso' => $data['contact_telefono'] ?? '',
            'cellulare' => $data['contact_cellulare'] ?? ''
        ];
        
        foreach ($contacts as $type => $value) {
            if (!empty($value)) {
                $stmt = $conn->prepare(
                    "INSERT INTO junior_member_contacts (junior_member_id, contact_type, value) VALUES (?, ?, ?)"
                );
                $stmt->execute([$juniorId, $type, $value]);
            }
        }
        
        // Insert residenza address
        if (!empty($data['residenza_street']) || !empty($data['residenza_city'])) {
            $stmt = $conn->prepare(
                "INSERT INTO junior_member_addresses (junior_member_id, address_type, street, number, city, province, cap) 
                 VALUES (?, 'residenza', ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $juniorId,
                $data['residenza_street'] ?? null,
                $data['residenza_number'] ?? null,
                $data['residenza_city'] ?? null,
                $data['residenza_province'] ?? null,
                $data['residenza_cap'] ?? null
            ]);
        }
        
        // Insert guardians
        // Padre
        if (!empty($data['guardian_padre_first_name']) || !empty($data['guardian_padre_last_name'])) {
            $stmt = $conn->prepare(
                "INSERT INTO junior_member_guardians (junior_member_id, guardian_type, first_name, last_name, tax_code, phone, email) 
                 VALUES (?, 'padre', ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $juniorId,
                $data['guardian_padre_first_name'] ?? '',
                $data['guardian_padre_last_name'] ?? '',
                $data['guardian_padre_tax_code'] ?? null,
                $data['guardian_padre_phone'] ?? null,
                $data['guardian_padre_email'] ?? null
            ]);
        }
        
        // Madre
        if (!empty($data['guardian_madre_first_name']) || !empty($data['guardian_madre_last_name'])) {
            $stmt = $conn->prepare(
                "INSERT INTO junior_member_guardians (junior_member_id, guardian_type, first_name, last_name, tax_code, phone, email) 
                 VALUES (?, 'madre', ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $juniorId,
                $data['guardian_madre_first_name'] ?? '',
                $data['guardian_madre_last_name'] ?? '',
                $data['guardian_madre_tax_code'] ?? null,
                $data['guardian_madre_phone'] ?? null,
                $data['guardian_madre_email'] ?? null
            ]);
        }
        
        return ['status' => 'imported', 'id' => $juniorId];
    }
    
    /**
     * Import singolo veicolo
     * 
     * @param array $data Dati veicolo
     * @param PDO $conn Connessione DB
     * @return array Risultato
     */
    private function importVehicle($data, $conn) {
        // Check duplicato via targa
        if (!empty($data['license_plate'])) {
            $stmt = $conn->prepare("SELECT id FROM vehicles WHERE license_plate = ?");
            $stmt->execute([$data['license_plate']]);
            if ($stmt->fetch()) {
                return ['status' => 'skipped', 'reason' => 'Targa già esistente'];
            }
        }
        
        // Prepara dati
        $vehicleData = [
            'vehicle_type' => $this->parseVehicleType($data['vehicle_type'] ?? ''),
            'name' => $data['name'] ?? '',
            'license_plate' => $data['license_plate'] ?? null,
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'year' => $this->parseInteger($data['year'] ?? null, null),
            'serial_number' => $data['serial_number'] ?? null,
            'status' => $this->parseVehicleStatus($data['status'] ?? ''),
            'insurance_expiry' => $this->parseDate($data['insurance_expiry'] ?? ''),
            'inspection_expiry' => $this->parseDate($data['inspection_expiry'] ?? ''),
            'notes' => $data['notes'] ?? null
        ];
        
        // Insert vehicle
        $columns = array_keys($vehicleData);
        $placeholders = array_map(function($col) { return ":$col"; }, $columns);
        
        $sql = "INSERT INTO vehicles (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        $stmt->execute($vehicleData);
        $vehicleId = $conn->lastInsertId();
        
        return ['status' => 'imported', 'id' => $vehicleId];
    }
    
    /**
     * Import singolo item magazzino
     * 
     * @param array $data Dati item
     * @param PDO $conn Connessione DB
     * @return array Risultato
     */
    private function importWarehouseItem($data, $conn) {
        // Check duplicato via code
        if (!empty($data['code'])) {
            $stmt = $conn->prepare("SELECT id FROM warehouse_items WHERE code = ?");
            $stmt->execute([$data['code']]);
            if ($stmt->fetch()) {
                return ['status' => 'skipped', 'reason' => 'Codice già esistente'];
            }
        }
        
        // Prepara dati
        $itemData = [
            'code' => $data['code'] ?? null,
            'name' => $data['name'] ?? '',
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'quantity' => $this->parseInteger($data['quantity'] ?? null, 0),
            'minimum_quantity' => $this->parseInteger($data['minimum_quantity'] ?? null, 0),
            'unit' => $data['unit'] ?? null,
            'location' => $data['location'] ?? null,
            'qr_code' => $data['qr_code'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'status' => $this->parseWarehouseStatus($data['status'] ?? ''),
            'notes' => $data['notes'] ?? null
        ];
        
        // Insert item
        $columns = array_keys($itemData);
        $placeholders = array_map(function($col) { return ":$col"; }, $columns);
        
        $sql = "INSERT INTO warehouse_items (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        $stmt->execute($itemData);
        $itemId = $conn->lastInsertId();
        
        return ['status' => 'imported', 'id' => $itemId];
    }
    
    /**
     * Helper: Parse integer con validazione
     */
    private function parseInteger($value, $default = null) {
        if ($value === null || $value === '') {
            return $default;
        }
        
        // Rimuovi spazi
        $value = trim($value);
        
        // Valida che sia un intero valido
        if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return (int)$value;
        }
        
        // Se contiene solo cifre, converti
        if (preg_match('/^\d+$/', $value)) {
            return (int)$value;
        }
        
        return $default;
    }
    
    /**
     * Helper: Parse date da vari formati
     */
    private function parseDate($dateStr) {
        if (empty($dateStr)) return null;
        
        $dateStr = trim($dateStr);
        
        // Formati supportati: YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];
        
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateStr);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        return null;
    }
    
    /**
     * Helper: Parse gender
     */
    private function parseGender($gender) {
        $gender = strtoupper(trim($gender));
        if (in_array($gender, ['M', 'F'])) {
            return $gender;
        }
        if (in_array($gender, ['MASCHIO', 'MALE', 'UOMO'])) {
            return 'M';
        }
        if (in_array($gender, ['FEMMINA', 'FEMALE', 'DONNA'])) {
            return 'F';
        }
        return null;
    }
    
    /**
     * Helper: Parse member type
     */
    private function parseMemberType($type) {
        $type = strtolower(trim($type));
        if (in_array($type, ['ordinario', 'fondatore'])) {
            return $type;
        }
        return 'ordinario';
    }
    
    /**
     * Helper: Parse member status
     */
    private function parseMemberStatus($status) {
        $status = strtolower(trim($status));
        $validStatuses = ['attivo', 'decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo'];
        if (in_array($status, $validStatuses)) {
            return $status;
        }
        return 'attivo';
    }
    
    /**
     * Helper: Parse volunteer status
     */
    private function parseVolunteerStatus($status) {
        $status = strtolower(trim($status));
        $validStatuses = ['operativo', 'non_operativo', 'in_formazione'];
        if (in_array($status, $validStatuses)) {
            return $status;
        }
        return 'in_formazione';
    }
    
    /**
     * Helper: Parse worker type
     */
    private function parseWorkerType($type) {
        if (empty($type)) {
            return null;
        }
        $type = strtolower(trim($type));
        $validTypes = ['studente', 'dipendente_privato', 'dipendente_pubblico', 'lavoratore_autonomo', 'disoccupato', 'pensionato'];
        if (in_array($type, $validTypes)) {
            return $type;
        }
        return null;
    }
    
    /**
     * Helper: Parse education level
     */
    private function parseEducationLevel($level) {
        if (empty($level)) {
            return null;
        }
        $level = strtolower(trim($level));
        $validLevels = ['licenza_media', 'diploma_maturita', 'laurea_triennale', 'laurea_magistrale', 'dottorato'];
        if (in_array($level, $validLevels)) {
            return $level;
        }
        return null;
    }
    
    /**
     * Helper: Parse vehicle type
     */
    private function parseVehicleType($type) {
        $type = strtolower(trim($type));
        $validTypes = ['veicolo', 'natante', 'rimorchio'];
        if (in_array($type, $validTypes)) {
            return $type;
        }
        return 'veicolo';
    }
    
    /**
     * Helper: Parse vehicle status
     */
    private function parseVehicleStatus($status) {
        $status = strtolower(trim($status));
        $validStatuses = ['operativo', 'in_manutenzione', 'fuori_servizio', 'dismesso'];
        if (in_array($status, $validStatuses)) {
            return $status;
        }
        return 'operativo';
    }
    
    /**
     * Helper: Parse warehouse status
     */
    private function parseWarehouseStatus($status) {
        $status = strtolower(trim($status));
        $validStatuses = ['disponibile', 'in_manutenzione', 'fuori_servizio'];
        if (in_array($status, $validStatuses)) {
            return $status;
        }
        return 'disponibile';
    }
    
    /**
     * Start import log
     */
    private function startImportLog($fileName, $importType, $userId) {
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO import_logs (file_name, import_type, created_by, status) 
             VALUES (?, ?, ?, 'in_progress')"
        );
        $stmt->execute([basename($fileName), $importType, $userId]);
        return $this->db->getConnection()->lastInsertId();
    }
    
    /**
     * Update import log
     */
    private function updateImportLog($logId, $data) {
        $sets = [];
        $values = [];
        foreach ($data as $key => $value) {
            $sets[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $logId;
        
        $sql = "UPDATE import_logs SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($values);
    }
    
    /**
     * Complete import log
     */
    private function completeImportLog($logId, $data) {
        $data['completed_at'] = date('Y-m-d H:i:s');
        $this->updateImportLog($logId, $data);
    }
    
    /**
     * Fail import log
     */
    private function failImportLog($logId, $errorMessage) {
        $this->updateImportLog($logId, [
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get import logs
     * 
     * @param array $filters Filtri
     * @param int $page Pagina
     * @param int $perPage Elementi per pagina
     * @return array
     */
    public function getLogs($filters = [], $page = 1, $perPage = 20) {
        $where = [];
        $params = [];
        
        if (!empty($filters['import_type'])) {
            $where[] = "import_type = ?";
            $params[] = $filters['import_type'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Count total
        $sql = "SELECT COUNT(*) as total FROM import_logs $whereClause";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Get logs
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM import_logs $whereClause ORDER BY started_at DESC LIMIT $perPage OFFSET $offset";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();
        
        return [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
}
