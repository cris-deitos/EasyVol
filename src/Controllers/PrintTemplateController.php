<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Models\Member;
use EasyVol\Models\JuniorMember;
use EasyVol\Utils\XmlTemplateProcessor;

/**
 * Print Template Controller
 * 
 * Gestisce la generazione di stampe e PDF per EasyVol
 * Supporta template singoli, liste, multi-pagina e relazionali
 * Supporta template in formato HTML e XML
 */
class PrintTemplateController {
    private $db;
    private $config;
    private $xmlProcessor;
    
    /**
     * Constructor
     * 
     * @param Database $db Database instance
     * @param array $config Configuration
     */
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
        $this->xmlProcessor = new XmlTemplateProcessor($config);
    }
    
    /**
     * Get all templates
     * 
     * @param array $filters Filtri
     * @return array
     */
    public function getAll($filters = []) {
        $sql = "SELECT * FROM print_templates WHERE 1=1";
        $params = [];
        
        if (!empty($filters['entity_type'])) {
            $sql .= " AND entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['template_type'])) {
            $sql .= " AND template_type = ?";
            $params[] = $filters['template_type'];
        }
        
        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        $sql .= " ORDER BY is_default DESC, name ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get template by ID
     * 
     * @param int $id Template ID
     * @return array|false
     */
    public function getById($id) {
        $sql = "SELECT * FROM print_templates WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Create new template
     * 
     * @param array $data Template data
     * @param int $userId User ID
     * @return int Template ID
     */
    public function create($data, $userId) {
        $sql = "INSERT INTO print_templates (
            name, description, template_type, template_format, data_scope, entity_type,
            html_content, xml_content, xml_schema_version, css_content, relations, filter_config, variables,
            page_format, page_orientation, show_header, show_footer,
            header_content, footer_content, watermark, is_active, is_default,
            created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $params = [
            $data['name'],
            $data['description'] ?? null,
            $data['template_type'],
            $data['template_format'] ?? 'html',
            $data['data_scope'],
            $data['entity_type'],
            $data['html_content'] ?? null,
            $data['xml_content'] ?? null,
            $data['xml_schema_version'] ?? '1.0',
            $data['css_content'] ?? null,
            $data['relations'] ?? null,
            $data['filter_config'] ?? null,
            $data['variables'] ?? null,
            $data['page_format'] ?? 'A4',
            $data['page_orientation'] ?? 'portrait',
            $data['show_header'] ?? 1,
            $data['show_footer'] ?? 1,
            $data['header_content'] ?? null,
            $data['footer_content'] ?? null,
            $data['watermark'] ?? null,
            $data['is_active'] ?? 1,
            $data['is_default'] ?? 0,
            $userId
        ];
        
        $this->db->query($sql, $params);
        return $this->db->getConnection()->lastInsertId();
    }
    
    /**
     * Update template
     * 
     * @param int $id Template ID
     * @param array $data Template data
     * @param int $userId User ID
     * @return bool
     */
    public function update($id, $data, $userId) {
        $sql = "UPDATE print_templates SET
            name = ?, description = ?, template_type = ?, template_format = ?, data_scope = ?,
            entity_type = ?, html_content = ?, xml_content = ?, xml_schema_version = ?, 
            css_content = ?, relations = ?, filter_config = ?, variables = ?, 
            page_format = ?, page_orientation = ?, show_header = ?, show_footer = ?, 
            header_content = ?, footer_content = ?, watermark = ?, is_active = ?, 
            is_default = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?";
        
        $params = [
            $data['name'],
            $data['description'] ?? null,
            $data['template_type'],
            $data['template_format'] ?? 'html',
            $data['data_scope'],
            $data['entity_type'],
            $data['html_content'] ?? null,
            $data['xml_content'] ?? null,
            $data['xml_schema_version'] ?? '1.0',
            $data['css_content'] ?? null,
            $data['relations'] ?? null,
            $data['filter_config'] ?? null,
            $data['variables'] ?? null,
            $data['page_format'] ?? 'A4',
            $data['page_orientation'] ?? 'portrait',
            $data['show_header'] ?? 1,
            $data['show_footer'] ?? 1,
            $data['header_content'] ?? null,
            $data['footer_content'] ?? null,
            $data['watermark'] ?? null,
            $data['is_active'] ?? 1,
            $data['is_default'] ?? 0,
            $userId,
            $id
        ];
        
        $this->db->query($sql, $params);
        return true;
    }
    
    /**
     * Delete template
     * 
     * @param int $id Template ID
     * @return bool
     */
    public function delete($id) {
        $sql = "DELETE FROM print_templates WHERE id = ?";
        $this->db->query($sql, [$id]);
        return true;
    }
    
    /**
     * Generate document from template
     * 
     * @param int $templateId Template ID
     * @param array $options Generation options
     * @return array Generated HTML and metadata
     */
    public function generate($templateId, $options = []) {
        $template = $this->getById($templateId);
        
        if (!$template) {
            throw new \Exception("Template non trovato");
        }
        
        switch ($template['template_type']) {
            case 'single':
                return $this->generateSingle($template, $options);
            case 'list':
                return $this->generateList($template, $options);
            case 'multi_page':
                return $this->generateMultiPage($template, $options);
            case 'relational':
                // Check if this is a list with relations or single with relations
                if ($template['data_scope'] === 'filtered' || $template['data_scope'] === 'all') {
                    return $this->generateListWithRelations($template, $options);
                }
                return $this->generateRelational($template, $options);
            default:
                throw new \Exception("Tipo template non valido");
        }
    }
    
    /**
     * Generate single record document
     * 
     * @param array $template Template data
     * @param array $options Options (record_id)
     * @return array
     */
    private function generateSingle($template, $options) {
        $recordId = $options['record_id'] ?? null;
        
        if (!$recordId) {
            throw new \Exception("ID record richiesto per template singolo");
        }
        
        $record = $this->loadRecord($template['entity_type'], $recordId);
        
        if (!$record) {
            throw new \Exception("Record non trovato");
        }
        
        $html = $this->replaceVariables($template['html_content'], $record);
        
        return [
            'html' => $html,
            'css' => $template['css_content'],
            'header' => $template['show_header'] ? $template['header_content'] : '',
            'footer' => $template['show_footer'] ? $template['footer_content'] : '',
            'watermark' => $template['watermark'],
            'page_format' => $template['page_format'],
            'page_orientation' => $template['page_orientation']
        ];
    }
    
    /**
     * Generate list document
     * 
     * @param array $template Template data
     * @param array $options Options (filters)
     * @return array
     */
    private function generateList($template, $options) {
        $filters = $options['filters'] ?? [];
        $records = $this->loadRecords($template['entity_type'], $filters);
        
        // Parse filter configuration
        $filterConfig = $template['filter_config'] ? json_decode($template['filter_config'], true) : [];
        
        $html = $this->renderHandlebars($template['html_content'], ['records' => $records]);
        
        return [
            'html' => $html,
            'css' => $template['css_content'],
            'header' => $template['show_header'] ? $template['header_content'] : '',
            'footer' => $template['show_footer'] ? $template['footer_content'] : '',
            'watermark' => $template['watermark'],
            'page_format' => $template['page_format'],
            'page_orientation' => $template['page_orientation']
        ];
    }
    
    /**
     * Generate multi-page document (one page per record)
     * 
     * @param array $template Template data
     * @param array $options Options (record_ids or filters)
     * @return array
     */
    private function generateMultiPage($template, $options) {
        $recordIds = $options['record_ids'] ?? null;
        $filters = $options['filters'] ?? [];
        
        if ($recordIds) {
            // Load specific records
            $records = [];
            foreach ($recordIds as $id) {
                $record = $this->loadRecord($template['entity_type'], $id);
                if ($record) {
                    $records[] = $record;
                }
            }
        } else {
            // Load filtered records
            $records = $this->loadRecords($template['entity_type'], $filters);
        }
        
        $pages = [];
        foreach ($records as $record) {
            $pages[] = $this->replaceVariables($template['html_content'], $record);
        }
        
        // Join pages with page break
        $html = implode('<div style="page-break-after: always;"></div>', $pages);
        
        return [
            'html' => $html,
            'css' => $template['css_content'],
            'header' => $template['show_header'] ? $template['header_content'] : '',
            'footer' => $template['show_footer'] ? $template['footer_content'] : '',
            'watermark' => $template['watermark'],
            'page_format' => $template['page_format'],
            'page_orientation' => $template['page_orientation']
        ];
    }
    
    /**
     * Generate relational document (with related data)
     * 
     * @param array $template Template data
     * @param array $options Options (record_id)
     * @return array
     */
    private function generateRelational($template, $options) {
        $recordId = $options['record_id'] ?? null;
        
        if (!$recordId) {
            throw new \Exception("ID record richiesto per template relazionale");
        }
        
        $record = $this->loadRecord($template['entity_type'], $recordId);
        
        if (!$record) {
            throw new \Exception("Record non trovato");
        }
        
        // Load related data
        $relations = $template['relations'] ? json_decode($template['relations'], true) : [];
        foreach ($relations as $relationTable) {
            $relatedData = $this->loadRelatedData($template['entity_type'], $recordId, $relationTable);
            $record[$relationTable] = $relatedData;
        }
        
        $html = $this->renderHandlebars($template['html_content'], $record);
        
        return [
            'html' => $html,
            'css' => $template['css_content'],
            'header' => $template['show_header'] ? $template['header_content'] : '',
            'footer' => $template['show_footer'] ? $template['footer_content'] : '',
            'watermark' => $template['watermark'],
            'page_format' => $template['page_format'],
            'page_orientation' => $template['page_orientation']
        ];
    }
    
    /**
     * Generate list document with related data for each record
     * 
     * @param array $template Template data
     * @param array $options Options (filters)
     * @return array
     */
    private function generateListWithRelations($template, $options) {
        $filters = $options['filters'] ?? [];
        $records = $this->loadRecords($template['entity_type'], $filters);
        
        // Load related data for each record
        $relations = $template['relations'] ? json_decode($template['relations'], true) : [];
        
        foreach ($records as &$record) {
            foreach ($relations as $relationTable) {
                $relatedData = $this->loadRelatedData($template['entity_type'], $record['id'], $relationTable);
                $record[$relationTable] = $relatedData;
            }
        }
        
        $html = $this->renderHandlebars($template['html_content'], ['records' => $records]);
        
        return [
            'html' => $html,
            'css' => $template['css_content'],
            'header' => $template['show_header'] ? $template['header_content'] : '',
            'footer' => $template['show_footer'] ? $template['footer_content'] : '',
            'watermark' => $template['watermark'],
            'page_format' => $template['page_format'],
            'page_orientation' => $template['page_orientation']
        ];
    }
    
    /**
     * Load single record
     * 
     * @param string $entityType Entity type
     * @param int $recordId Record ID
     * @return array|false
     */
    private function loadRecord($entityType, $recordId) {
        $table = $this->getTableName($entityType);
        $sql = "SELECT * FROM {$table} WHERE id = ?";
        return $this->db->fetchOne($sql, [$recordId]);
    }
    
    /**
     * Load records with filters
     * 
     * @param string $entityType Entity type
     * @param array $filters Filters
     * @return array
     */
    private function loadRecords($entityType, $filters = []) {
        $table = $this->getTableName($entityType);
        $sql = "SELECT * FROM {$table} WHERE 1=1";
        $params = [];
        
        // Apply filters based on entity type
        switch ($entityType) {
            case 'members':
                if (!empty($filters['member_status'])) {
                    $sql .= " AND member_status = ?";
                    $params[] = $filters['member_status'];
                }
                if (!empty($filters['member_type'])) {
                    $sql .= " AND member_type = ?";
                    $params[] = $filters['member_type'];
                }
                if (!empty($filters['date_from'])) {
                    $sql .= " AND registration_date >= ?";
                    $params[] = $filters['date_from'];
                }
                if (!empty($filters['date_to'])) {
                    $sql .= " AND registration_date <= ?";
                    $params[] = $filters['date_to'];
                }
                $sql .= " ORDER BY registration_number ASC";
                break;
                
            case 'junior_members':
                if (!empty($filters['member_status'])) {
                    $sql .= " AND member_status = ?";
                    $params[] = $filters['member_status'];
                }
                if (!empty($filters['date_from'])) {
                    $sql .= " AND registration_date >= ?";
                    $params[] = $filters['date_from'];
                }
                if (!empty($filters['date_to'])) {
                    $sql .= " AND registration_date <= ?";
                    $params[] = $filters['date_to'];
                }
                $sql .= " ORDER BY registration_number ASC";
                break;
                
            case 'vehicles':
                if (!empty($filters['vehicle_type'])) {
                    $sql .= " AND vehicle_type = ?";
                    $params[] = $filters['vehicle_type'];
                }
                if (!empty($filters['status'])) {
                    $sql .= " AND status = ?";
                    $params[] = $filters['status'];
                }
                $sql .= " ORDER BY license_plate ASC";
                break;
                
            case 'meetings':
                if (!empty($filters['date_from'])) {
                    $sql .= " AND meeting_date >= ?";
                    $params[] = $filters['date_from'];
                }
                if (!empty($filters['date_to'])) {
                    $sql .= " AND meeting_date <= ?";
                    $params[] = $filters['date_to'];
                }
                $sql .= " ORDER BY meeting_date DESC";
                break;
                
            case 'member_applications':
                if (!empty($filters['application_type'])) {
                    $sql .= " AND application_type = ?";
                    $params[] = $filters['application_type'];
                }
                if (!empty($filters['status'])) {
                    $sql .= " AND status = ?";
                    $params[] = $filters['status'];
                }
                if (!empty($filters['date_from'])) {
                    $sql .= " AND submitted_at >= ?";
                    $params[] = $filters['date_from'];
                }
                if (!empty($filters['date_to'])) {
                    $sql .= " AND submitted_at <= ?";
                    $params[] = $filters['date_to'];
                }
                $sql .= " ORDER BY submitted_at DESC";
                break;
                
            case 'events':
                if (!empty($filters['event_type'])) {
                    $sql .= " AND event_type = ?";
                    $params[] = $filters['event_type'];
                }
                if (!empty($filters['status'])) {
                    $sql .= " AND status = ?";
                    $params[] = $filters['status'];
                }
                if (!empty($filters['date_from'])) {
                    $sql .= " AND start_date >= ?";
                    $params[] = $filters['date_from'];
                }
                if (!empty($filters['date_to'])) {
                    $sql .= " AND start_date <= ?";
                    $params[] = $filters['date_to'];
                }
                $sql .= " ORDER BY start_date DESC";
                break;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Load related data
     * 
     * @param string $entityType Entity type
     * @param int $recordId Record ID
     * @param string $relationTable Related table name
     * @return array
     * @throws \Exception if relation table is not valid for entity type
     */
    private function loadRelatedData($entityType, $recordId, $relationTable) {
        // Validate that the relation table is allowed for this entity type
        $availableRelations = $this->getAvailableRelations($entityType);
        if (!isset($availableRelations[$relationTable])) {
            throw new \Exception("Invalid relation table: " . htmlspecialchars($relationTable) . " for entity type: " . htmlspecialchars($entityType));
        }
        
        // Determine foreign key based on entity type
        $foreignKey = $this->getForeignKey($entityType);
        
        // The relation table is now validated through getAvailableRelations
        $sql = "SELECT * FROM {$relationTable} WHERE {$foreignKey} = ?";
        return $this->db->fetchAll($sql, [$recordId]);
    }
    
    /**
     * Get table name from entity type
     * 
     * @param string $entityType Entity type
     * @return string
     * @throws \Exception if entity type is not whitelisted
     */
    private function getTableName($entityType) {
        // Whitelist of allowed entity types to prevent SQL injection
        $allowedTypes = [
            'members',
            'junior_members',
            'member_applications',
            'vehicles',
            'meetings',
            'events',
        ];
        
        if (!in_array($entityType, $allowedTypes, true)) {
            throw new \Exception("Invalid entity type: " . htmlspecialchars($entityType));
        }
        
        return $entityType;
    }
    
    /**
     * Get foreign key column name
     * 
     * @param string $entityType Entity type
     * @return string
     */
    private function getForeignKey($entityType) {
        // Map entity types to their foreign key column names
        $foreignKeys = [
            'members' => 'member_id',
            'junior_members' => 'junior_member_id',
            'member_applications' => 'application_id',
            'vehicles' => 'vehicle_id',
            'meetings' => 'meeting_id',
            'events' => 'event_id',
        ];
        
        return $foreignKeys[$entityType] ?? 'id';
    }
    
    /**
     * Replace variables in template
     * 
     * @param string $content Template content
     * @param array $data Data to replace
     * @return string
     */
    private function replaceVariables($content, $data) {
        foreach ($data as $key => $value) {
            // Handle null values
            if ($value === null) {
                $value = '';
            }
            
            // Handle dates
            if ($key === 'birth_date' || $key === 'registration_date' || strpos($key, '_date') !== false) {
                if (!empty($value) && $value !== '0000-00-00') {
                    $value = date('d/m/Y', strtotime($value));
                } else {
                    $value = '';
                }
            }
            
            // Replace variable
            $content = str_replace('{{' . $key . '}}', htmlspecialchars($value), $content);
        }
        
        // Remove unreplaced variables
        $content = preg_replace('/\{\{[^}]+\}\}/', '', $content);
        
        return $content;
    }
    
    /**
     * Render Handlebars-style loops
     * 
     * @param string $content Template content
     * @param array $data Data with arrays
     * @return string
     */
    private function renderHandlebars($content, $data) {
        // Handle {{#each array}} loops
        $pattern = '/\{\{#each\s+([a-zA-Z_]+)\}\}(.*?)\{\{\/each\}\}/s';
        
        $content = preg_replace_callback($pattern, function($matches) use ($data) {
            $arrayKey = $matches[1];
            $loopContent = $matches[2];
            $output = '';
            
            if (isset($data[$arrayKey]) && is_array($data[$arrayKey])) {
                $index = 0;
                foreach ($data[$arrayKey] as $item) {
                    $itemOutput = $loopContent;
                    
                    // Replace @index helper (1-based for display)
                    $itemOutput = str_replace('{{@index}}', $index + 1, $itemOutput);
                    
                    // Replace variables in loop
                    foreach ($item as $key => $value) {
                        if ($value === null) {
                            $value = '';
                        }
                        
                        // Handle dates
                        if (strpos($key, '_date') !== false && !empty($value) && $value !== '0000-00-00') {
                            $value = date('d/m/Y', strtotime($value));
                        }
                        
                        // Handle datetime fields - only for fields ending with '_datetime' or starting with 'start_' or 'end_' containing 'time'
                        if ((strpos($key, '_datetime') !== false || 
                            (strpos($key, 'start_') === 0 && strpos($key, 'time') !== false) ||
                            (strpos($key, 'end_') === 0 && strpos($key, 'time') !== false)) 
                            && !empty($value)) {
                            // Check if value contains both date and time
                            if (preg_match('/\d{4}-\d{2}-\d{2}/', $value) && strtotime($value)) {
                                $value = date('d/m/Y H:i', strtotime($value));
                            }
                        }
                        
                        $itemOutput = str_replace('{{' . $key . '}}', htmlspecialchars($value), $itemOutput);
                    }
                    
                    // Handle {{#if field}} conditional blocks within loops
                    $itemOutput = $this->processConditionals($itemOutput, $item);
                    
                    $output .= $itemOutput;
                    $index++;
                }
            }
            
            return $output;
        }, $content);
        
        // Handle top-level {{#if field}} conditional blocks
        $content = $this->processConditionals($content, $data);
        
        // Replace remaining simple variables
        $content = $this->replaceVariables($content, $data);
        
        return $content;
    }
    
    /**
     * Process {{#if field}} conditional blocks
     * 
     * @param string $content Template content
     * @param array $data Data array
     * @return string
     */
    private function processConditionals($content, $data) {
        return preg_replace_callback(
            '/\{\{#if\s+([a-zA-Z_]+)\}\}(.*?)\{\{\/if\}\}/s',
            function($matches) use ($data) {
                $fieldName = $matches[1];
                $ifContent = $matches[2];
                
                // Show content only if field exists and is not null/empty string
                // Allow zero values (0, '0') as valid content
                if (isset($data[$fieldName]) && $data[$fieldName] !== null && $data[$fieldName] !== '') {
                    return $ifContent;
                }
                return '';
            },
            $content
        );
    }
    
    /**
     * Get available variables for entity type
     * 
     * @param string $entityType Entity type
     * @return array
     */
    public function getAvailableVariables($entityType) {
        $table = $this->getTableName($entityType);
        
        // Get columns from table
        $sql = "SHOW COLUMNS FROM {$table}";
        $columns = $this->db->fetchAll($sql);
        
        $variables = [];
        foreach ($columns as $column) {
            $variables[] = [
                'name' => $column['Field'],
                'type' => $column['Type'],
                'description' => $this->getFieldDescription($entityType, $column['Field'])
            ];
        }
        
        return $variables;
    }
    
    /**
     * Get field description
     * 
     * @param string $entityType Entity type
     * @param string $field Field name
     * @return string
     */
    private function getFieldDescription($entityType, $field) {
        // Italian field descriptions
        $descriptions = [
            'id' => 'ID',
            'registration_number' => 'Matricola',
            'first_name' => 'Nome',
            'last_name' => 'Cognome',
            'birth_date' => 'Data di nascita',
            'birth_place' => 'Luogo di nascita',
            'birth_province' => 'Provincia di nascita',
            'tax_code' => 'Codice fiscale',
            'gender' => 'Sesso',
            'nationality' => 'Nazionalità',
            'registration_date' => 'Data iscrizione',
            'member_type' => 'Tipo socio',
            'member_status' => 'Stato socio',
            'volunteer_status' => 'Stato volontario',
            'email' => 'Email',
            'phone' => 'Telefono',
            'mobile' => 'Cellulare',
            'pec' => 'PEC',
            'license_plate' => 'Targa',
            'vehicle_type' => 'Tipo mezzo',
            'model' => 'Modello',
            'meeting_date' => 'Data riunione',
            'meeting_type' => 'Tipo riunione',
            'location' => 'Luogo',
            'application_code' => 'Codice domanda',
            'application_type' => 'Tipo domanda',
            'status' => 'Stato',
            'submitted_at' => 'Data invio',
            'approved_at' => 'Data approvazione',
            'application_data' => 'Dati domanda',
            'event_type' => 'Tipo evento',
            'title' => 'Titolo',
            'description' => 'Descrizione',
            'start_date' => 'Data inizio',
            'end_date' => 'Data fine',
        ];
        
        return $descriptions[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }
    
    /**
     * Get available relations for entity type
     * 
     * @param string $entityType Entity type
     * @return array
     */
    public function getAvailableRelations($entityType) {
        $relations = [
            'members' => [
                'member_contacts' => 'Contatti',
                'member_addresses' => 'Indirizzi',
                'member_licenses' => 'Patenti',
                'member_courses' => 'Corsi',
                'member_roles' => 'Ruoli',
                'member_employment' => 'Datore di lavoro',
                'member_education' => 'Istruzione',
                'member_health' => 'Salute',
                'member_fees' => 'Quote',
                'member_notes' => 'Note',
                'member_sanctions' => 'Sanzioni',
            ],
            'junior_members' => [
                'junior_member_guardians' => 'Genitori/Tutori',
                'junior_member_contacts' => 'Contatti',
                'junior_member_addresses' => 'Indirizzi',
                'junior_member_health' => 'Salute',
                'junior_member_fees' => 'Quote',
                'junior_member_notes' => 'Note',
                'junior_member_sanctions' => 'Sanzioni',
            ],
            'vehicles' => [
                'vehicle_maintenance' => 'Manutenzioni',
                'vehicle_documents' => 'Documenti',
            ],
            'meetings' => [
                'meeting_participants' => 'Partecipanti',
                'meeting_agenda' => 'Ordine del giorno',
                'meeting_attachments' => 'Allegati',
            ],
            'member_applications' => [
                // Guardian data is stored in application_data JSON field, not in a separate table
            ],
            'events' => [
                'interventions' => 'Interventi',
                'event_participants' => 'Membri Coinvolti',
                'event_vehicles' => 'Mezzi Utilizzati',
            ],
        ];
        
        return $relations[$entityType] ?? [];
    }
    
    /**
     * Export template
     * 
     * @param int $templateId Template ID
     * @return array Template data for export
     */
    public function exportTemplate($templateId) {
        $template = $this->getById($templateId);
        
        if (!$template) {
            throw new \Exception("Template non trovato");
        }
        
        // Remove system fields
        unset($template['id']);
        unset($template['created_by']);
        unset($template['created_at']);
        unset($template['updated_by']);
        unset($template['updated_at']);
        
        return $template;
    }
    
    /**
     * Import template
     * 
     * @param array $templateData Template data
     * @param int $userId User ID
     * @return int Template ID
     */
    public function importTemplate($templateData, $userId) {
        // Ensure required fields
        if (!isset($templateData['name']) || !isset($templateData['entity_type'])) {
            throw new \Exception("Dati template non validi");
        }
        
        // Check if template with same name exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM print_templates WHERE name = ? AND entity_type = ?",
            [$templateData['name'], $templateData['entity_type']]
        );
        
        if ($existing) {
            $templateData['name'] .= ' (importato)';
        }
        
        return $this->create($templateData, $userId);
    }
    
    /**
     * Get sample data for entity type (for preview)
     * 
     * @param string $entityType Entity type
     * @return array Sample data
     */
    public function getSampleData($entityType) {
        $data = [
            'current_date' => date('d/m/Y'),
            'current_year' => date('Y'),
            'association' => [
                'name' => $this->config['association_name'] ?? 'Nome Associazione',
                'address' => 'Via Example 123, 12345 Città',
                'phone' => '+39 012 3456789',
                'email' => 'info@example.com'
            ]
        ];
        
        switch ($entityType) {
            case 'members':
                // Get a sample member
                $member = $this->db->fetchOne("SELECT * FROM members WHERE is_active = 1 LIMIT 1");
                if ($member) {
                    $data = array_merge($data, $member);
                    // Get contacts
                    $contacts = $this->db->fetchAll("SELECT * FROM member_contacts WHERE member_id = ?", [$member['id']]);
                    $data['contacts'] = $contacts;
                } else {
                    // Default sample data
                    $data = array_merge($data, [
                        'first_name' => 'Mario',
                        'last_name' => 'Rossi',
                        'badge_number' => '001',
                        'member_type' => 'Volontario',
                        'birth_date' => '1990-01-15',
                        'phone' => '+39 333 1234567',
                        'email' => 'mario.rossi@example.com',
                        'contacts' => []
                    ]);
                }
                break;
                
            case 'junior_members':
                $juniorMember = $this->db->fetchOne("SELECT * FROM junior_members WHERE is_active = 1 LIMIT 1");
                if ($juniorMember) {
                    $data = array_merge($data, $juniorMember);
                } else {
                    $data = array_merge($data, [
                        'first_name' => 'Luca',
                        'last_name' => 'Bianchi',
                        'badge_number' => 'C001',
                        'birth_date' => '2010-05-20',
                        'parent_name' => 'Giovanni Bianchi'
                    ]);
                }
                break;
                
            case 'vehicles':
                $vehicle = $this->db->fetchOne("SELECT * FROM vehicles WHERE is_active = 1 LIMIT 1");
                if ($vehicle) {
                    $data = array_merge($data, $vehicle);
                } else {
                    $data = array_merge($data, [
                        'vehicle_name' => 'Ambulanza A1',
                        'plate_number' => 'AB123CD',
                        'brand' => 'Fiat',
                        'model' => 'Ducato',
                        'year' => 2020,
                        'vehicle_type' => 'Ambulanza'
                    ]);
                }
                break;
                
            case 'meetings':
                $meeting = $this->db->fetchOne("SELECT * FROM meetings ORDER BY meeting_date DESC LIMIT 1");
                if ($meeting) {
                    $data = array_merge($data, $meeting);
                    // Get participants
                    $participants = $this->db->fetchAll(
                        "SELECT m.first_name, m.last_name, mp.role 
                         FROM meeting_participants mp 
                         JOIN members m ON mp.member_id = m.id 
                         WHERE mp.meeting_id = ?",
                        [$meeting['id']]
                    );
                    $data['participants'] = $participants;
                } else {
                    $data = array_merge($data, [
                        'meeting_title' => 'Assemblea Ordinaria',
                        'meeting_date' => date('Y-m-d'),
                        'meeting_time' => '18:00',
                        'location' => 'Sede Associazione',
                        'participants' => []
                    ]);
                }
                break;
        }
        
        return $data;
    }
    
    /**
     * Convert HTML template to XML format (helper method)
     * 
     * @param array $template Template data
     * @return string XML content
     */
    public function htmlToXml($template) {
        // Create basic XML structure from existing HTML template
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<template version="1.0">' . "\n";
        $xml .= '  <metadata>' . "\n";
        $xml .= '    <name>' . htmlspecialchars($template['name']) . '</name>' . "\n";
        $xml .= '    <description>' . htmlspecialchars($template['description'] ?? '') . '</description>' . "\n";
        $xml .= '    <entity_type>' . htmlspecialchars($template['entity_type']) . '</entity_type>' . "\n";
        $xml .= '    <template_type>' . htmlspecialchars($template['template_type']) . '</template_type>' . "\n";
        $xml .= '  </metadata>' . "\n\n";
        
        $xml .= '  <page format="' . htmlspecialchars($template['page_format']) . '" ';
        $xml .= 'orientation="' . htmlspecialchars($template['page_orientation']) . '">' . "\n";
        $xml .= '    <margins top="20" bottom="20" left="15" right="15" />' . "\n";
        $xml .= '  </page>' . "\n\n";
        
        $xml .= '  <styles><![CDATA[' . "\n";
        $xml .= $template['css_content'] ?? '';
        $xml .= '  ]]></styles>' . "\n\n";
        
        $xml .= '  <body>' . "\n";
        $xml .= '    <!-- Converted from HTML template -->' . "\n";
        $xml .= '    <section>' . "\n";
        // Note: HTML content would need proper parsing and conversion
        // For now, we just wrap it
        $xml .= '      ' . $template['html_content'] . "\n";
        $xml .= '    </section>' . "\n";
        $xml .= '  </body>' . "\n";
        $xml .= '</template>';
        
        return $xml;
    }
    
    /**
     * Process XML template and generate HTML
     * 
     * @param array $template Template data
     * @param array $data Data for variables
     * @return array [html, css, format, orientation]
     */
    public function processXmlTemplate($template, $data) {
        if ($template['template_format'] === 'xml' && !empty($template['xml_content'])) {
            return $this->xmlProcessor->process($template['xml_content'], $data);
        }
        
        // Fallback to HTML template
        return [
            'html' => $template['html_content'],
            'css' => $template['css_content'] ?? '',
            'format' => $template['page_format'],
            'orientation' => $template['page_orientation']
        ];
    }
}

