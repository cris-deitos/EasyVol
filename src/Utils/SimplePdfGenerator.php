<?php
namespace EasyVol\Utils;

use Mpdf\Mpdf;
use Mpdf\MpdfException;

/**
 * Simple PDF Generator
 * 
 * Simplified PDF generation system that directly generates PDFs from database records
 * Supports single record and list templates with automatic data fetching
 */
class SimplePdfGenerator {
    private $db;
    private $config;
    
    /**
     * Active status constant for members (used for filtering)
     */
    private const MEMBER_ACTIVE_STATUS = 'attivo';
    
    /**
     * Active status constant for junior members (used for filtering)
     */
    private const JUNIOR_MEMBER_ACTIVE_STATUS = 'attivo';
    
    /**
     * Valid guardian types for junior members
     */
    private const VALID_GUARDIAN_TYPES = ['padre', 'madre', 'tutore'];
    
    /**
     * Guardian fields to flatten for each guardian type
     */
    private const GUARDIAN_FIELDS = [
        'first_name' => '',
        'last_name' => '',
        'phone' => '',
        'mobile' => '',
        'email' => '',
        'tax_code' => '',
        'birth_date' => '',
        'birth_place' => ''
    ];
    
    /**
     * Constructor
     * 
     * @param object $db Database instance
     * @param array $config Configuration
     */
    public function __construct($db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Generate PDF from template and data
     * 
     * @param array $template Template configuration
     * @param array $options Generation options (record_id, record_ids, filters)
     * @param string $outputMode Output mode (D=download, I=inline, S=string)
     * @return mixed PDF output
     */
    public function generate($template, $options = [], $outputMode = 'D') {
        // Get data based on template type
        $data = $this->prepareData($template, $options);
        
        // Process template HTML
        $html = $this->processTemplate($template['html_content'], $data);
        
        // Generate PDF
        return $this->generatePdf($html, $template, $outputMode);
    }
    
    /**
     * Prepare data based on template type and options
     * 
     * @param array $template Template configuration
     * @param array $options Generation options
     * @return array Prepared data
     */
    public function prepareData($template, $options) {
        $entityType = $template['entity_type'];
        $templateType = $template['template_type'];
        $dataScope = $template['data_scope'] ?? 'single';
        
        // Add association data
        $data = [
            'association' => $this->config['association'] ?? [],
            'current_date' => date('d/m/Y'),
            'current_datetime' => date('d/m/Y H:i'),
            'current_year' => date('Y')
        ];
        
        if ($templateType === 'single') {
            // Single record template
            if (empty($options['record_id'])) {
                throw new \Exception("record_id is required for single record template");
            }
            
            $record = $this->loadRecord($entityType, $options['record_id']);
            if (!$record) {
                throw new \Exception("Record not found: {$entityType} #{$options['record_id']}");
            }
            
            // Load related data
            $record = $this->loadRelatedData($entityType, $record);
            
            // Merge record data with base data
            $data = array_merge($data, $record);
            
        } elseif ($templateType === 'list') {
            // List template
            $filters = $options['filters'] ?? [];
            
            // Check if specific record IDs are provided
            if (!empty($options['record_ids'])) {
                $records = $this->loadRecordsByIds($entityType, $options['record_ids']);
            } else {
                $records = $this->loadRecords($entityType, $filters, $dataScope);
            }
            
            // Load related data ONLY if explicitly requested by template
            // List templates (elenchi) typically only need main table fields
            // This prevents memory exhaustion when generating PDFs for large lists
            if (!empty($template['relations'])) {
                // Parse relations field (could be JSON string or array)
                $relations = $template['relations'];
                if (is_string($relations)) {
                    $decoded = json_decode($relations, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log("SimplePdfGenerator: Invalid JSON in relations field: " . json_last_error_msg());
                        $relations = [];
                    } else {
                        $relations = $decoded;
                    }
                }
                
                // Load relations only if we have a valid non-empty array
                if (!empty($relations) && is_array($relations)) {
                    foreach ($records as $index => &$record) {
                        try {
                            $record = $this->loadSpecificRelations($entityType, $record, $relations);
                        } catch (\Exception $e) {
                            // Log the error but don't fail the entire list generation
                            // Use intval to sanitize index and prevent log injection
                            $safeIndex = intval($index);
                            error_log("SimplePdfGenerator: Error loading relations for record {$safeIndex}: " . $e->getMessage());
                            // Continue with the record without related data
                        }
                    }
                }
            }
            
            $data['records'] = $records;
            $data['total'] = count($records);
        }
        
        return $data;
    }
    
    /**
     * Load single record from database
     * 
     * @param string $entityType Entity type
     * @param int $recordId Record ID
     * @return array|false Record data
     */
    private function loadRecord($entityType, $recordId) {
        $table = $this->getTableName($entityType);
        $sql = "SELECT * FROM {$table} WHERE id = ?";
        return $this->db->fetchOne($sql, [$recordId]);
    }
    
    /**
     * Load multiple records by IDs
     * 
     * @param string $entityType Entity type
     * @param array $recordIds Array of record IDs
     * @return array Records
     */
    private function loadRecordsByIds($entityType, $recordIds) {
        if (empty($recordIds)) {
            return [];
        }
        
        $table = $this->getTableName($entityType);
        $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
        
        if ($entityType === 'members') {
            // Members: numeric registration number (1, 2, 3...)
            $sql = "SELECT * FROM {$table} WHERE id IN ({$placeholders}) ORDER BY (registration_number + 0) ASC";
        } elseif ($entityType === 'junior_members') {
            // Junior members: alphanumeric registration number (C-1, C-2, C-10...)
            // Extract numeric part after "C-" for correct sorting
            $sql = "SELECT * FROM {$table} WHERE id IN ({$placeholders}) ORDER BY CASE WHEN registration_number LIKE 'C-%' THEN CAST(SUBSTRING(registration_number, 3) AS UNSIGNED) ELSE 0 END ASC, registration_number ASC";
        } else {
            // Other entity types: sort by id
            $sql = "SELECT * FROM {$table} WHERE id IN ({$placeholders}) ORDER BY id ASC";
        }
        
        return $this->db->fetchAll($sql, $recordIds);
    }
    
    /**
     * Load multiple records with filters
     * 
     * @param string $entityType Entity type
     * @param array $filters Filters to apply
     * @param string $dataScope Data scope: 'all' for all records, 'filtered' for active only
     * @return array Records
     */
    private function loadRecords($entityType, $filters = [], $dataScope = 'all') {
        $table = $this->getTableName($entityType);
        $sql = "SELECT * FROM {$table} WHERE 1=1";
        $params = [];
        
        // When data_scope is 'filtered', only include active members/junior_members
        // 'all' scope exports everyone (for Libro Soci template)
        if ($dataScope === 'filtered') {
            if ($entityType === 'members') {
                $sql .= " AND member_status = ?";
                $params[] = self::MEMBER_ACTIVE_STATUS;
            } elseif ($entityType === 'junior_members') {
                $sql .= " AND member_status = ?";
                $params[] = self::JUNIOR_MEMBER_ACTIVE_STATUS;
            }
        }
        
        // Apply filters based on entity type
        if (isset($filters['status'])) {
            // For members and junior_members, use member_status column; for others use status
            if ($entityType === 'members' || $entityType === 'junior_members') {
                $sql .= " AND member_status = ?";
            } else {
                $sql .= " AND status = ?";
            }
            $params[] = $filters['status'];
        }
        
        if (isset($filters['member_status'])) {
            // member_status filter works for both members and junior_members
            if ($entityType === 'members' || $entityType === 'junior_members') {
                $sql .= " AND member_status = ?";
                $params[] = $filters['member_status'];
            }
        }
        
        if (isset($filters['member_type']) && $entityType === 'members') {
            $sql .= " AND member_type = ?";
            $params[] = $filters['member_type'];
        }
        
        // Vehicle filters
        if (isset($filters['vehicle_type']) && $entityType === 'vehicles') {
            $sql .= " AND vehicle_type = ?";
            $params[] = $filters['vehicle_type'];
        }
        
        if (isset($filters['search']) && $entityType === 'vehicles') {
            $sql .= " AND (name LIKE ? OR license_plate LIKE ? OR brand LIKE ? OR model LIKE ?)";
            // Escape LIKE wildcards to prevent unintended wildcard matching
            $escapedSearch = addcslashes($filters['search'], '%_');
            $searchTerm = '%' . $escapedSearch . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (isset($filters['date_from'])) {
            $dateField = $this->getDateField($entityType);
            $sql .= " AND {$dateField} >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $dateField = $this->getDateField($entityType);
            $sql .= " AND {$dateField} <= ?";
            $params[] = $filters['date_to'];
        }
        
        // Add ordering - use registration_number for members (matricola), id for others
        if ($entityType === 'members') {
            // Members: numeric registration number (1, 2, 3...)
            // Use COALESCE to handle NULL and fallback for non-numeric values
            $sql .= " ORDER BY CAST(COALESCE(registration_number, '0') AS UNSIGNED) ASC, registration_number ASC";
        } elseif ($entityType === 'junior_members') {
            // Junior members: alphanumeric registration number (C-1, C-2, C-10...)
            // Extract numeric part after "C-" for correct sorting
            $sql .= " ORDER BY CASE WHEN registration_number LIKE 'C-%' THEN CAST(SUBSTRING(registration_number, 3) AS UNSIGNED) ELSE 0 END ASC, registration_number ASC";
        } else {
            $sql .= " ORDER BY id ASC";
        }
        
        // Add limit if specified
        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . intval($filters['limit']);
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Load related data for a record
     * 
     * @param string $entityType Entity type
     * @param array $record Record data
     * @return array Record with related data
     */
    private function loadRelatedData($entityType, $record) {
        // Validate record has ID field
        if (!isset($record['id'])) {
            error_log("SimplePdfGenerator: Record missing 'id' field for entity type: $entityType");
            return $record;
        }
        
        $recordId = $record['id'];
        
        // Define related tables for each entity type
        $relatedTables = $this->getRelatedTables($entityType);
        
        foreach ($relatedTables as $key => $config) {
            $table = $config['table'];
            $foreignKey = $config['foreign_key'];
            
            $sql = "SELECT * FROM {$table} WHERE {$foreignKey} = ?";
            $params = [$recordId];
            
            // Add additional filters if needed
            if (isset($config['filters'])) {
                foreach ($config['filters'] as $field => $value) {
                    $sql .= " AND {$field} = ?";
                    $params[] = $value;
                }
            }
            
            // Add ordering
            if (isset($config['order_by'])) {
                // Validate ORDER BY clause to prevent SQL injection
                if ($this->isValidOrderByClause($config['order_by'])) {
                    $sql .= " ORDER BY " . $config['order_by'];
                } else {
                    error_log("SimplePdfGenerator: Invalid ORDER BY clause: '{$config['order_by']}'");
                }
            }
            
            // Try to fetch related data, but don't fail if table/column is missing
            try {
                $record[$key] = $this->db->fetchAll($sql, $params);
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
                // Only gracefully handle table/column not found errors (likely missing migrations)
                // Re-throw other errors (connection issues, memory, etc.)
                if (stripos($errorMsg, "doesn't exist") !== false || 
                    stripos($errorMsg, "Unknown column") !== false ||
                    stripos($errorMsg, "no such table") !== false) {
                    error_log("SimplePdfGenerator: Related table/column missing for {$table}: " . $errorMsg);
                    $record[$key] = [];
                } else {
                    throw $e;
                }
            }
        }
        
        // Flatten related data for direct template access
        $record = $this->flattenRelatedData($entityType, $record);

// Determina sorgente immagine per mPDF (photo_src) e flag di assenza foto (has_no_photo)
$record['photo_src'] = '';
$record['has_no_photo'] = true;

if (!empty($record['photo_path'])) {
    // Radice del progetto (aggiusta se necessario)
    $basePath = realpath(__DIR__ . '/../../');
    $relativePath = ltrim($record['photo_path'], './');
    $absolutePath = $basePath . '/' . $relativePath;

    if (file_exists($absolutePath) && is_readable($absolutePath)) {
        // Proviamo a creare data URI (raccomandata)
        $mime = @mime_content_type($absolutePath) ?: 'image/jpeg';
        $contents = @file_get_contents($absolutePath);

        if ($contents !== false) {
            $data = base64_encode($contents);
            $dataUri = 'data:' . $mime . ';base64,' . $data;
            $record['photo_src'] = $dataUri;
            $record['photo_path_data'] = $dataUri; // mantieni per retrocompatibilità se vuoi
            $record['photo_path'] = 'file://' . $absolutePath; // fallback
            $record['has_no_photo'] = false;
        } else {
            // fallback a file:// se non riusciamo a leggere il contenuto
            $record['photo_src'] = 'file://' . $absolutePath;
            $record['photo_path_data'] = '';
            $record['has_no_photo'] = false;
        }
    } else {
        // file non disponibile
        $record['photo_src'] = '';
        $record['photo_path_data'] = '';
        $record['photo_path'] = '';
        $record['has_no_photo'] = true;
    }
} else {
    // nessun percorso foto
    $record['photo_src'] = '';
    $record['photo_path_data'] = '';
    $record['has_no_photo'] = true;
}












// --- POPOLA association_logo_src (data URI o file:// fallback) ---
$record['association_logo_src'] = '';

// Build candidates list (config e record)
$logoCandidates = [];
if (!empty($this->config['association']['logo'])) {
    $logoCandidates[] = $this->config['association']['logo'];
}
if (!empty($this->config['association']['logo_path'])) {
    $logoCandidates[] = $this->config['association']['logo_path'];
}
if (!empty($record['logo'])) {
    $logoCandidates[] = $record['logo'];
}
if (!empty($record['association_logo'])) {
    $logoCandidates[] = $record['association_logo'];
}
if (!empty($record['association']['logo'])) {
    $logoCandidates[] = $record['association']['logo'];
}
if (!empty($record['association']['logo_path'])) {
    $logoCandidates[] = $record['association']['logo_path'];
}

// Try candidates
$projectRoot = realpath(__DIR__ . '/../../');
foreach ($logoCandidates as $candidate) {
    if (empty($candidate)) continue;

    // already data URI
    if (strpos($candidate, 'data:') === 0) {
        $record['association_logo_src'] = $candidate;
        break;
    }

    // http(s) URL: try download
    if (preg_match('#^https?://#i', $candidate)) {
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $contents = @file_get_contents($candidate, false, $ctx);
        if ($contents !== false) {
            $mime = @finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $contents) ?: 'image/png';
            $record['association_logo_src'] = 'data:' . $mime . ';base64,' . base64_encode($contents);
            break;
        }
        // else try next candidate
    }

    // local path: try candidate as given and relative to project root
    $pathsToTry = [$candidate];
    if ($projectRoot) {
        $pathsToTry[] = $projectRoot . '/' . ltrim($candidate, './');
    }

    foreach ($pathsToTry as $p) {
        if (empty($p)) continue;
        $abs = realpath($p) ?: false;
        if ($abs && file_exists($abs) && is_readable($abs)) {
            $contents = @file_get_contents($abs);
            if ($contents !== false) {
                $mime = @mime_content_type($abs) ?: 'image/png';
                $record['association_logo_src'] = 'data:' . $mime . ';base64,' . base64_encode($contents);
            } else {
                $record['association_logo_src'] = 'file://' . $abs;
            }
            break 2;
        }
    }
}
// leave empty string if not found

















// --- Associazione: popola association_email e association_logo (data URI) ---
$record['association_email'] = $this->config['association']['email'] ?? ($record['association_email'] ?? '');

// Recupera percorso logo da config o dal record (può essere relativo)
$logoPath = $this->config['association']['logo_path'] ?? ($record['association_logo'] ?? '');
$record['association_logo'] = '';


if (isset($record['roles']) && is_array($record['roles'])) {
    $roleCards = [];
    foreach ($record['roles'] as $role) {
        // Crea una copia del ruolo e arricchiscila con i dati del socio
        $card = $role;

        // Copia i principali campi del socio nel card (sovrascrive se esistono, scegli i campi che ti servono)
        $card['registration_number'] = $record['registration_number'] ?? ($record['id'] ?? '');
        $card['first_name'] = $record['first_name'] ?? '';
        $card['last_name'] = $record['last_name'] ?? '';
        $card['tax_code'] = $record['tax_code'] ?? '';
        $card['birth_date'] = $record['birth_date'] ?? '';
        // Foto: usa photo_src se presente (data URI), altrimenti fallback photo_path
        $card['photo_src'] = $record['photo_src'] ?? ($record['photo_path'] ?? '');
        // Logo/associazione (se presenti in $record oppure in $this->config)
$card['association_name'] = $record['association']['name'] ?? ($this->config['association']['name'] ?? $record['association_name'] ?? '');
$card['association_tax_code'] = $record['association']['tax_code'] ?? ($this->config['association']['tax_code'] ?? $record['association_tax_code'] ?? '');
$card['association_email'] = $record['association_email'] ?? '';
$card['association_logo_src'] = $record['association_logo_src'] ?? '';

        // Assicurati che il nome della mansione sia disponibile in una sola riga (trim)
        $card['role_label'] = isset($role['role_name']) ? trim(preg_replace('/\s+/', ' ', $role['role_name'])) : '';

        $roleCards[] = $card;
    }
    $record['role_cards'] = $roleCards;
} else {
    $record['role_cards'] = [];
}

        return $record;
    }
    
    /**
     * Validate SQL identifier (table or column name)
     * 
     * @param string $identifier SQL identifier to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidSqlIdentifier($identifier) {
        // Valid SQL identifiers contain only alphanumeric characters and underscores
        // and do not start with a digit
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier) === 1;
    }
    
    /**
     * Validate ORDER BY clause
     * 
     * @param string $orderBy ORDER BY clause to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidOrderByClause($orderBy) {
        // Valid ORDER BY contains column names (with optional table prefix), 
        // comma separators, and ASC/DESC keywords
        // Example: "column1 ASC, column2 DESC" or "table.column DESC"
        $pattern = '/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?\s*(ASC|DESC)?(\s*,\s*[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?\s*(ASC|DESC)?)*$/i';
        return preg_match($pattern, $orderBy) === 1;
    }
    
    /**
     * Load specific relations for a record (used by list templates)
     * Only loads the relations specified in the array parameter
     * 
     * @param string $entityType Entity type
     * @param array $record Record data
     * @param array $relations Array of relation keys to load
     * @return array Record with specified related data
     */
    private function loadSpecificRelations($entityType, $record, $relations) {
        // Validate record has ID field
        if (!isset($record['id'])) {
            error_log("SimplePdfGenerator: Record missing 'id' field for entity type: $entityType");
            return $record;
        }
        
        $recordId = $record['id'];
        
        // Get all available related tables for this entity type
        $allRelatedTables = $this->getRelatedTables($entityType);
        
        // Load only the specified relations
        foreach ($relations as $relationKey) {
            if (!isset($allRelatedTables[$relationKey])) {
                error_log("SimplePdfGenerator: Unknown relation '{$relationKey}' for entity type '{$entityType}'");
                continue;
            }
            
            $config = $allRelatedTables[$relationKey];
            $table = $config['table'];
            $foreignKey = $config['foreign_key'];
            
            // Validate table and foreign key names to prevent SQL injection
            // (defense in depth, even though values come from controlled whitelist)
            if (!$this->isValidSqlIdentifier($table) || !$this->isValidSqlIdentifier($foreignKey)) {
                error_log("SimplePdfGenerator: Invalid SQL identifier in relation config for '{$relationKey}'");
                continue;
            }
            
            $sql = "SELECT * FROM {$table} WHERE {$foreignKey} = ?";
            $params = [$recordId];
            
            // Add additional filters if needed
            if (isset($config['filters'])) {
                foreach ($config['filters'] as $field => $value) {
                    // Validate filter field names
                    if (!$this->isValidSqlIdentifier($field)) {
                        error_log("SimplePdfGenerator: Invalid SQL identifier in filter field: '{$field}'");
                        continue;
                    }
                    $sql .= " AND {$field} = ?";
                    $params[] = $value;
                }
            }
            
            // Add ordering
            if (isset($config['order_by'])) {
                // Validate ORDER BY clause to prevent SQL injection
                if ($this->isValidOrderByClause($config['order_by'])) {
                    $sql .= " ORDER BY " . $config['order_by'];
                } else {
                    error_log("SimplePdfGenerator: Invalid ORDER BY clause in relation config: '{$config['order_by']}'");
                }
            }
            
            // Try to fetch related data, but don't fail if table/column is missing
            try {
                $record[$relationKey] = $this->db->fetchAll($sql, $params);
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
                // Only gracefully handle table/column not found errors
                if (stripos($errorMsg, "doesn't exist") !== false || 
                    stripos($errorMsg, "Unknown column") !== false ||
                    stripos($errorMsg, "no such table") !== false) {
                    error_log("SimplePdfGenerator: Related table/column missing for {$table}: " . $errorMsg);
                    $record[$relationKey] = [];
                } else {
                    throw $e;
                }
            }
        }
        
        // Flatten the loaded relations to provide direct template variable access
        // This allows templates to use {{email}}, {{cellulare}}, etc.
        $record = $this->flattenRelatedData($entityType, $record);
        
        return $record;
    }
    
    /**
     * Flatten related data for direct template variable access
     * 
     * Extracts commonly needed data from related tables and adds them as flat variables:
     * - Contacts: email, cellulare (mobile), telefono_fisso
     * - Addresses: residenza_*, domicilio_*
     * - Health: allergie, intolleranze, vegano, vegetariano
     * - Fees: quota_anno_corrente (current year fee status)
     * 
     * @param string $entityType Entity type
     * @param array $record Record with related data
     * @return array Record with flattened data added
     */
    private function flattenRelatedData($entityType, $record) {
        // Flatten contacts (for members and junior_members)
        if (isset($record['contacts']) && is_array($record['contacts'])) {
            foreach ($record['contacts'] as $contact) {
                $contactType = $contact['contact_type'] ?? '';
                $value = $contact['value'] ?? '';
                
                // Map contact types to flat variables (only set if empty)
                if ($this->shouldSetField($record, $contactType, $value)) {
                    $record[$contactType] = $value;
                }
            }
        }
        
        // Flatten addresses (for members and junior_members)
        if (isset($record['addresses']) && is_array($record['addresses'])) {
            foreach ($record['addresses'] as $address) {
                $addressType = $address['address_type'] ?? '';
                $prefix = $addressType; // 'residenza' or 'domicilio'
                
                if ($prefix === 'residenza' || $prefix === 'domicilio') {
                    // Check if all address fields for this type are unset
                    $addressFields = ['_street', '_number', '_cap', '_city', '_province'];
                    $allFieldsEmpty = true;
                    foreach ($addressFields as $field) {
                        if (isset($record[$prefix . $field]) && !empty($record[$prefix . $field])) {
                            $allFieldsEmpty = false;
                            break;
                        }
                    }
                    
                    // Only set all fields if none are already set
                    if ($allFieldsEmpty) {
                        $record[$prefix . '_street'] = $address['street'] ?? '';
                        $record[$prefix . '_number'] = $address['number'] ?? '';
                        $record[$prefix . '_cap'] = $address['postal_code'] ?? '';
                        $record[$prefix . '_city'] = $address['city'] ?? '';
                        $record[$prefix . '_province'] = $address['province'] ?? '';
                    }
                }
            }
        }
        
        // Flatten health info (for members and junior_members)
        // First check which table key is used - 'health' is defined in getRelatedTables
        $healthData = $record['health'] ?? [];
        if (is_array($healthData)) {
            // Initialize defaults
            $record['allergie'] = '';
            $record['intolleranze'] = '';
            $record['vegano'] = false;
            $record['vegetariano'] = false;
            $record['patologie'] = '';
            
            $allergieList = [];
            $intolleranzeList = [];
            $patologieList = [];
            
            foreach ($healthData as $health) {
                $healthType = $health['health_type'] ?? '';
                $description = $health['description'] ?? '';
                
                switch ($healthType) {
                    case 'allergie':
                        $allergieList[] = $description;
                        break;
                    case 'intolleranze':
                        $intolleranzeList[] = $description;
                        break;
                    case 'vegano':
                        $record['vegano'] = true;
                        break;
                    case 'vegetariano':
                        $record['vegetariano'] = true;
                        break;
                    case 'patologie':
                        $patologieList[] = $description;
                        break;
                }
            }
            
            $record['allergie'] = implode(', ', $allergieList);
            $record['intolleranze'] = implode(', ', $intolleranzeList);
            $record['patologie'] = implode(', ', $patologieList);
        }
        
        // Flatten fees - check if current year fee is paid
        $currentYear = (int)date('Y');
        if (isset($record['fees']) && is_array($record['fees'])) {
            $record['quota_anno_corrente'] = false;
            $record['quota_anno_corrente_importo'] = '';
            $record['quota_anno_corrente_data'] = '';
            
            foreach ($record['fees'] as $fee) {
                $feeYear = isset($fee['year']) ? (int)$fee['year'] : 0;
                if ($feeYear === $currentYear) {
                    $record['quota_anno_corrente'] = !empty($fee['payment_date']);
                    $record['quota_anno_corrente_importo'] = $fee['amount'] ?? '';
                    $record['quota_anno_corrente_data'] = $fee['payment_date'] ?? '';
                    break;
                }
            }
            
            // Also set fee_paid as an alias for quota_anno_corrente (used by some templates)
            $record['fee_paid'] = $record['quota_anno_corrente'];
        }
        
        // Flatten guardians for junior_members
        if (isset($record['guardians']) && is_array($record['guardians'])) {
            $guardianIndex = 1;
            foreach ($record['guardians'] as $guardian) {
                if ($guardianIndex <= 2) { // Only flatten first 2 guardians
                    $prefix = 'tutore' . $guardianIndex;
                    $record[$prefix . '_nome'] = $guardian['first_name'] ?? '';
                    $record[$prefix . '_cognome'] = $guardian['last_name'] ?? '';
                    $record[$prefix . '_telefono'] = $guardian['phone'] ?? '';
                    $record[$prefix . '_cellulare'] = $guardian['mobile'] ?? '';
                    $record[$prefix . '_email'] = $guardian['email'] ?? '';
                    // Use 'relationship' if available (custom field), fallback to 'guardian_type' (DB enum: padre/madre/tutore)
                    $record[$prefix . '_relazione'] = $guardian['relationship'] ?? $guardian['guardian_type'] ?? '';
                    $guardianIndex++;
                }
                
                // Also create variables based on guardian_type (padre/madre/tutore)
                // This allows templates to use {{padre_first_name}}, {{madre_phone}}, etc.
                $guardianType = $guardian['guardian_type'] ?? '';
                // Validate guardian_type against allowed values using class constant
                if (!empty($guardianType) && in_array($guardianType, self::VALID_GUARDIAN_TYPES, true)) {
                    // Only set if not already set (first guardian of this type wins)
                    if (!isset($record[$guardianType . '_first_name'])) {
                        // Use GUARDIAN_FIELDS constant to ensure consistent field list
                        foreach (self::GUARDIAN_FIELDS as $field => $default) {
                            $record[$guardianType . '_' . $field] = $guardian[$field] ?? $default;
                        }
                    }
                }
            }
            
            // Add guardian_name and guardian_phone as convenient aliases for templates
            // Uses first guardian's data (typically padre or madre)
            if (!empty($record['guardians'])) {
                $firstGuardian = $record['guardians'][0];
                // Use array_filter and implode to avoid extra spaces when only one name is present
                $nameParts = array_filter([
                    $firstGuardian['first_name'] ?? '',
                    $firstGuardian['last_name'] ?? ''
                ], function($v) { return $v !== ''; });
                $record['guardian_name'] = implode(' ', $nameParts);
                $record['guardian_phone'] = $firstGuardian['phone'] ?? '';
                $record['guardian_email'] = $firstGuardian['email'] ?? '';
            }
        }
        
        // Initialize guardian fields if not already set (for records without guardians)
        // Generic guardian fields
        if (!isset($record['guardian_name'])) {
            $record['guardian_name'] = '';
        }
        if (!isset($record['guardian_phone'])) {
            $record['guardian_phone'] = '';
        }
        if (!isset($record['guardian_email'])) {
            $record['guardian_email'] = '';
        }
        
        // Padre/madre/tutore specific fields - initialize if not set using class constants
        foreach (self::VALID_GUARDIAN_TYPES as $guardianType) {
            if (!isset($record[$guardianType . '_first_name'])) {
                foreach (self::GUARDIAN_FIELDS as $field => $default) {
                    $record[$guardianType . '_' . $field] = $default;
                }
            }
        }
        
        return $record;
    }
    
    /**
     * Process template HTML with data
     * 
     * @param string $template Template HTML
     * @param array $data Data
     * @return string Processed HTML
     */
    public function processTemplate($template, $data) {
        // Handle {{#each array}} loops
        $template = preg_replace_callback(
            '/\{\{#each\s+([a-zA-Z_]+)\}\}(.*?)\{\{\/each\}\}/s',
            function($matches) use ($data) {
                $arrayKey = $matches[1];
                $loopContent = $matches[2];
                $output = '';
                
                if (isset($data[$arrayKey]) && is_array($data[$arrayKey])) {
                    $index = 0;
                    foreach ($data[$arrayKey] as $item) {
                        $itemOutput = $loopContent;
                        
                        // Replace @index
                        $itemOutput = str_replace('{{@index}}', $index + 1, $itemOutput);
                        $itemOutput = str_replace('{{@index0}}', $index, $itemOutput);
                        
                        // Process {{#if}} conditionals within item context
                        $itemOutput = $this->processIfConditionalsInContext($itemOutput, $item);
                        
                        // Process {{#if}}...{{else}}...{{/if}} conditionals within item context
                        $itemOutput = $this->processIfElseConditionalsInContext($itemOutput, $item);
                        
                        // Replace item variables
                        $itemOutput = $this->replaceVariables($itemOutput, $item);
                        
                        $output .= $itemOutput;
                        $index++;
                    }
                }
                
                return $output;
            },
            $template
        );
        
        // Handle {{#if field}} conditionals
        $template = preg_replace_callback(
            '/\{\{#if\s+([a-zA-Z_\.]+)\}\}(.*?)\{\{\/if\}\}/s',
            function($matches) use ($data) {
                $fieldName = $matches[1];
                $content = $matches[2];
                
                $value = $this->getNestedValue($data, $fieldName);
                
                if ($value !== null && $value !== '' && $value !== false && $value !== 0) {
                    return $content;
                }
                return '';
            },
            $template
        );
        
        // Replace simple variables
        $template = $this->replaceVariables($template, $data);
        
        return $template;
    }
    
    /**
     * Replace variables in template
     * 
     * @param string $template Template content
     * @param array $data Data
     * @return string
     */
    private function replaceVariables($template, $data) {
        // Flatten nested arrays for simple variable access
        $flatData = $this->flattenArray($data);
        
        foreach ($flatData as $key => $value) {
            if ($value === null) {
                $value = '';
            } elseif (is_array($value)) {
                $value = json_encode($value);
            } elseif (is_bool($value)) {
                $value = $value ? 'Sì' : 'No';
            }
            
            // Format dates
            if (strpos($key, '_date') !== false && !empty($value) && $value !== '0000-00-00') {
                if (strtotime($value)) {
                    $value = date('d/m/Y', strtotime($value));
                }
            }
            
            // Format datetimes
            if (strpos($key, '_datetime') !== false && !empty($value)) {
                if (strtotime($value)) {
                    $value = date('d/m/Y H:i', strtotime($value));
                }
            }
            
            $template = str_replace('{{' . $key . '}}', htmlspecialchars($value), $template);
        }
        
        // Remove unreplaced variables
        $template = preg_replace('/\{\{[^}]+\}\}/', '', $template);
        
        return $template;
    }
    
    /**
     * Get nested value from array
     * 
     * Template variables use underscore notation (e.g., {{association_name}}).
     * This method first tries direct key lookup, then attempts to access nested
     * data by treating underscores as path separators.
     * 
     * @param array $data Data array
     * @param string $path Variable path from template (uses underscore notation)
     * @return mixed Value or null if not found
     */
    private function getNestedValue($data, $path) {
        // First, try direct key access (for flattened data)
        if (isset($data[$path])) {
            return $data[$path];
        }
        
        // For nested data structures, try treating underscores as path separators
        // e.g., "association_name" could map to $data['association']['name']
        $keys = explode('_', $path);
        $value = $data;
        
        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        
        return $value;
    }
    
    /**
     * Process {{#if field}} conditionals within a specific data context
     * 
     * This is used within {{#each}} loops to evaluate conditionals against each item's data.
     * 
     * @param string $template Template content with {{#if}} blocks
     * @param array $data Data context to use for evaluation
     * @return string Processed template
     */
    private function processIfConditionalsInContext($template, $data) {
        return preg_replace_callback(
            '/\{\{#if\s+([a-zA-Z_\.]+)\}\}(.*?)\{\{\/if\}\}/s',
            function($matches) use ($data) {
                $fieldName = $matches[1];
                $content = $matches[2];
                
                // Check if this is an if/else block (skip those, they're handled separately)
                if (strpos($content, '{{else}}') !== false) {
                    return $matches[0]; // Return unchanged, let processIfElseConditionalsInContext handle it
                }
                
                $value = $this->getNestedValue($data, $fieldName);
                
                if ($value !== null && $value !== '' && $value !== false && $value !== 0) {
                    return $content;
                }
                return '';
            },
            $template
        );
    }
    
    /**
     * Process {{#if field}}...{{else}}...{{/if}} conditionals within a specific data context
     * 
     * This handles the if/else pattern used in templates.
     * 
     * @param string $template Template content with {{#if}}...{{else}}...{{/if}} blocks
     * @param array $data Data context to use for evaluation
     * @return string Processed template
     */
    private function processIfElseConditionalsInContext($template, $data) {
        return preg_replace_callback(
            '/\{\{#if\s+([a-zA-Z_\.]+)\}\}(.*?)\{\{else\}\}(.*?)\{\{\/if\}\}/s',
            function($matches) use ($data) {
                $fieldName = $matches[1];
                $trueContent = $matches[2];
                $falseContent = $matches[3];
                
                $value = $this->getNestedValue($data, $fieldName);
                
                if ($value !== null && $value !== '' && $value !== false && $value !== 0) {
                    return $trueContent;
                }
                return $falseContent;
            },
            $template
        );
    }
    
    /**
     * Flatten nested array
     * 
     * @param array $array Input array
     * @param string $prefix Prefix for keys
     * @return array
     */
    private function flattenArray($array, $prefix = '') {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '_' . $key;
            
            if (is_array($value) && !$this->isAssocArray($value)) {
                // Skip indexed arrays
                $result[$newKey] = $value;
            } elseif (is_array($value)) {
                // Flatten associative arrays
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Check if array is associative
     * 
     * @param array $array Array to check
     * @return bool
     */
    private function isAssocArray($array) {
        if (!is_array($array) || empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }
    
    /**
     * Generate PDF from HTML
     * 
     * @param string $html HTML content
     * @param array $template Template configuration
     * @param string $outputMode Output mode
     * @return mixed
     */
    private function generatePdf($html, $template, $outputMode) {
        // Configure mPDF
        $mpdfConfig = [
            'tempDir' => sys_get_temp_dir(),
            'default_font' => 'dejavusans',
            'default_font_size' => 10,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_left' => 15,
            'margin_right' => 15,
            'format' => $template['page_format'] ?? 'A4',
            'orientation' => $template['page_orientation'] === 'landscape' ? 'L' : 'P'
        ];
        
        $mpdf = new Mpdf($mpdfConfig);
        
        // Write HTML
        $mpdf->WriteHTML($html);
        
        // Generate filename
        $filename = $this->generateFilename($template);
        
        // For download mode, ensure clean output
        if ($outputMode === 'D' || $outputMode === 'I') {
            // Clean ALL output buffers (may be nested)
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Disable error reporting to prevent any PHP warnings/notices
            $oldErrorReporting = error_reporting(0);
            
            // Sanitize filename for HTTP header (remove newlines and special chars)
            $safeFilename = str_replace(["\r", "\n", '"'], '', $filename);
            
            // Set explicit headers for PDF download
            header('Content-Type: application/pdf');
            header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
            header('Pragma: public');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            
            if ($outputMode === 'D') {
                header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
            } else {
                header('Content-Disposition: inline; filename="' . $safeFilename . '"');
            }
            
            // Output PDF as string and echo it directly
            $pdfContent = $mpdf->Output('', 'S');
            header('Content-Length: ' . mb_strlen($pdfContent, '8bit'));
            echo $pdfContent;
            
            // Restore error reporting
            error_reporting($oldErrorReporting);
            
            // Must exit to prevent any trailing output
            exit;
        } else {
            // For other modes (F, S), use normal Output
            return $mpdf->Output($filename, $outputMode);
        }
    }
    
    /**
     * Generate filename for PDF
     * 
     * @param array $template Template configuration
     * @return string
     */
    private function generateFilename($template) {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $template['name']);
        $name = strtolower($name);
        $timestamp = date('Y-m-d_His');
        return "{$name}_{$timestamp}.pdf";
    }
    
    /**
     * Get table name for entity type
     * 
     * Maps UI entity types to database table names.
     * The mapping is explicit for security to prevent SQL injection via arbitrary table names.
     * 
     * @param string $entityType Entity type from template
     * @return string Database table name
     * @throws \Exception If entity type is not valid
     */
    private function getTableName($entityType) {
        // Explicit mapping for security - only these entity types are allowed
        $allowed = [
            'members' => 'members',
            'junior_members' => 'junior_members',
            'vehicles' => 'vehicles',
            'meetings' => 'meetings',
            'events' => 'events',
            'member_applications' => 'member_applications'
        ];
        
        if (!isset($allowed[$entityType])) {
            throw new \Exception("Invalid entity type: $entityType");
        }
        
        return $allowed[$entityType];
    }
    
    /**
     * Get date field for entity type
     * 
     * @param string $entityType Entity type
     * @return string
     */
    private function getDateField($entityType) {
        $map = [
            'members' => 'registration_date',
            'junior_members' => 'registration_date',
            'vehicles' => 'acquisition_date',
            'meetings' => 'meeting_date',
            'events' => 'start_date',
            'member_applications' => 'submitted_at'
        ];
        
        return $map[$entityType] ?? 'created_at';
    }
    
    /**
     * Get related tables configuration for entity type
     * 
     * @param string $entityType Entity type
     * @return array
     */
    private function getRelatedTables($entityType) {
        $relations = [
            'members' => [
                'contacts' => [
                    'table' => 'member_contacts',
                    'foreign_key' => 'member_id',
                    'order_by' => 'id ASC'
                ],
                'addresses' => [
                    'table' => 'member_addresses',
                    'foreign_key' => 'member_id',
                    'order_by' => 'id ASC'
                ],
                'licenses' => [
                    'table' => 'member_licenses',
                    'foreign_key' => 'member_id',
                    'order_by' => 'issue_date DESC'
                ],
                'courses' => [
                    'table' => 'member_courses',
                    'foreign_key' => 'member_id',
                    'order_by' => 'completion_date DESC'
                ],
                'roles' => [
                    'table' => 'member_roles',
                    'foreign_key' => 'member_id',
                    'order_by' => 'id ASC'
                ],
                'fees' => [
                    'table' => 'member_fees',
                    'foreign_key' => 'member_id',
                    'order_by' => 'year DESC'
                ],
                'health' => [
                    'table' => 'member_health',
                    'foreign_key' => 'member_id',
                    'order_by' => 'id ASC'
                ],
'health_surveillance' => [
    'table' => 'member_health_surveillance',
    'foreign_key' => 'member_id',
    'order_by' => 'visit_date DESC'
],
'sanctions' => [
    'table' => 'member_sanctions',
    'foreign_key' => 'member_id',
    'order_by' => 'sanction_date DESC'
],
                'notes' => [
                    'table' => 'member_notes',
                    'foreign_key' => 'member_id',
                    'order_by' => 'created_at DESC'
                ]
            ],
            'junior_members' => [
                'guardians' => [
                    'table' => 'junior_member_guardians',
                    'foreign_key' => 'junior_member_id',
                    'order_by' => 'id ASC'
                ],
                'contacts' => [
                    'table' => 'junior_member_contacts',
                    'foreign_key' => 'junior_member_id',
                    'order_by' => 'id ASC'
                ],
                'addresses' => [
                    'table' => 'junior_member_addresses',
                    'foreign_key' => 'junior_member_id',
                    'order_by' => 'id ASC'
                ],
                'fees' => [
                    'table' => 'junior_member_fees',
                    'foreign_key' => 'junior_member_id',
                    'order_by' => 'year DESC'
                ],
                'health' => [
                    'table' => 'junior_member_health',
                    'foreign_key' => 'junior_member_id',
                    'order_by' => 'id ASC'
                ],
'health_surveillance' => [
    'table' => 'junior_member_health_surveillance',
    'foreign_key' => 'junior_member_id',
    'order_by' => 'visit_date DESC'
],
'sanctions' => [
    'table' => 'junior_member_sanctions',
    'foreign_key' => 'junior_member_id',
    'order_by' => 'sanction_date DESC'
],
                'notes' => [
                    'table' => 'junior_member_notes',
                    'foreign_key' => 'junior_member_id',
                    'order_by' => 'created_at DESC'
                ]
            ],
            'vehicles' => [
                'maintenance' => [
                    'table' => 'vehicle_maintenance',
                    'foreign_key' => 'vehicle_id',
                    'order_by' => 'date DESC'
                ],
                'documents' => [
                    'table' => 'vehicle_documents',
                    'foreign_key' => 'vehicle_id',
                    'order_by' => 'expiry_date DESC'
                ],
                'movements' => [
                    'table' => 'vehicle_movements',
                    'foreign_key' => 'vehicle_id',
                    'order_by' => 'departure_datetime DESC',
                    'filters' => [] // Can be customized
                ]
            ],
            'meetings' => [
                'participants' => [
                    'table' => 'meeting_participants',
                    'foreign_key' => 'meeting_id',
                    'order_by' => 'id ASC'
                ],
                'agenda' => [
                    'table' => 'meeting_agenda',
                    'foreign_key' => 'meeting_id',
                    'order_by' => 'order_number ASC'
                ]
            ],
            'events' => [
                'interventions' => [
                    'table' => 'interventions',
                    'foreign_key' => 'event_id',
                    'order_by' => 'start_time DESC'
                ],
                'participants' => [
                    'table' => 'event_participants',
                    'foreign_key' => 'event_id',
                    'order_by' => 'id ASC'
                ],
                'vehicles' => [
                    'table' => 'event_vehicles',
                    'foreign_key' => 'event_id',
                    'order_by' => 'id ASC'
                ]
            ],
            // Member applications are standalone records without related child tables.
            // The application form data is stored directly in the member_applications table.
            'member_applications' => []
        ];
        
        return $relations[$entityType] ?? [];
    }
    
    /**
     * Check if a field should be set in a record
     * 
     * Returns true if the field is not already set or is empty, and the new value is not empty.
     * 
     * @param array $record Record data
     * @param string $fieldName Field name to check
     * @param mixed $newValue New value to set
     * @return bool True if field should be set
     */
    private function shouldSetField($record, $fieldName, $newValue) {
        // Don't set if new value is empty
        if (empty($newValue)) {
            return false;
        }
        
        // Set if field is not set or is empty
        return !isset($record[$fieldName]) || empty($record[$fieldName]);
    }
}

