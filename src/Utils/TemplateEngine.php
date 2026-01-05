<?php
namespace EasyVol\Utils;

/**
 * Template Engine
 * 
 * File-based template system for generating documents
 * Supports multi-table data and advanced formatting
 */
class TemplateEngine {
    private $templateDir;
    private $config;
    private $db;
    
    /**
     * Constructor
     * 
     * @param string $templateDir Base directory for templates
     * @param array $config Application configuration
     * @param object $db Database instance
     */
    public function __construct($templateDir, $config, $db) {
        $this->templateDir = rtrim($templateDir, '/');
        $this->config = $config;
        $this->db = $db;
    }
    
    /**
     * Get available templates for entity type
     * 
     * @param string $entityType Entity type (members, vehicles, etc.)
     * @return array List of available templates
     */
    public function getAvailableTemplates($entityType) {
        $entityDir = $this->templateDir . '/' . $entityType;
        
        if (!is_dir($entityDir)) {
            return [];
        }
        
        $templates = [];
        $files = glob($entityDir . '/*.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['name'])) {
                $templates[] = [
                    'file' => basename($file),
                    'path' => $file,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'type' => $data['type'] ?? 'single',
                    'format' => $data['format'] ?? 'A4',
                    'orientation' => $data['orientation'] ?? 'portrait'
                ];
            }
        }
        
        return $templates;
    }
    
    /**
     * Load template from file
     * 
     * @param string $entityType Entity type
     * @param string $templateFile Template filename
     * @return array|false Template data
     */
    public function loadTemplate($entityType, $templateFile) {
        $filepath = $this->templateDir . '/' . $entityType . '/' . $templateFile;
        
        if (!file_exists($filepath)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($filepath), true);
        
        if (!$data) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Generate document from template
     * 
     * @param string $entityType Entity type
     * @param string $templateFile Template filename
     * @param array $options Generation options (record_id, filters, etc.)
     * @return array Generated document data
     */
    public function generate($entityType, $templateFile, $options = []) {
        $template = $this->loadTemplate($entityType, $templateFile);
        
        if (!$template) {
            throw new \Exception("Template not found: $entityType/$templateFile");
        }
        
        $type = $template['type'] ?? 'single';
        
        switch ($type) {
            case 'single':
                return $this->generateSingle($entityType, $template, $options);
            case 'list':
                return $this->generateList($entityType, $template, $options);
            case 'multi_page':
                return $this->generateMultiPage($entityType, $template, $options);
            default:
                throw new \Exception("Unknown template type: $type");
        }
    }
    
    /**
     * Generate single record document
     * 
     * @param string $entityType Entity type
     * @param array $template Template data
     * @param array $options Options with record_id
     * @return array
     */
    private function generateSingle($entityType, $template, $options) {
        $recordId = $options['record_id'] ?? null;
        
        if (!$recordId) {
            throw new \Exception("record_id required for single record template");
        }
        
        // Load main record
        $data = $this->loadRecord($entityType, $recordId);
        
        if (!$data) {
            throw new \Exception("Record not found: $entityType #$recordId");
        }
        
        // Load related data if specified
        if (isset($template['relations']) && is_array($template['relations'])) {
            foreach ($template['relations'] as $relationKey => $relationConfig) {
                $data[$relationKey] = $this->loadRelatedData(
                    $entityType,
                    $recordId,
                    $relationConfig
                );
            }
        }
        
        // Add association data
        $data['association'] = $this->config['association'] ?? [];
        $data['current_date'] = date('d/m/Y');
        $data['current_year'] = date('Y');
        
        // Render template
        $html = $this->renderTemplate($template['html'], $data);
        
        return [
            'html' => $html,
            'css' => $template['css'] ?? '',
            'format' => $template['format'] ?? 'A4',
            'orientation' => $template['orientation'] ?? 'portrait',
            'margins' => $template['margins'] ?? ['top' => 20, 'bottom' => 20, 'left' => 15, 'right' => 15]
        ];
    }
    
    /**
     * Generate list document
     * 
     * @param string $entityType Entity type
     * @param array $template Template data
     * @param array $options Options with filters
     * @return array
     */
    private function generateList($entityType, $template, $options) {
        $filters = $options['filters'] ?? [];
        
        // Load records with filters
        $records = $this->loadRecords($entityType, $filters);
        
        // Load related data for each record if needed
        if (isset($template['relations']) && is_array($template['relations'])) {
            foreach ($records as &$record) {
                foreach ($template['relations'] as $relationKey => $relationConfig) {
                    $record[$relationKey] = $this->loadRelatedData(
                        $entityType,
                        $record['id'],
                        $relationConfig
                    );
                }
            }
        }
        
        $data = [
            'records' => $records,
            'total' => count($records),
            'association' => $this->config['association'] ?? [],
            'current_date' => date('d/m/Y'),
            'current_year' => date('Y')
        ];
        
        // Render template
        $html = $this->renderTemplate($template['html'], $data);
        
        return [
            'html' => $html,
            'css' => $template['css'] ?? '',
            'format' => $template['format'] ?? 'A4',
            'orientation' => $template['orientation'] ?? 'portrait',
            'margins' => $template['margins'] ?? ['top' => 20, 'bottom' => 20, 'left' => 15, 'right' => 15]
        ];
    }
    
    /**
     * Generate multi-page document (one page per record)
     * 
     * @param string $entityType Entity type
     * @param array $template Template data
     * @param array $options Options with record_ids or filters
     * @return array
     */
    private function generateMultiPage($entityType, $template, $options) {
        $recordIds = $options['record_ids'] ?? null;
        $filters = $options['filters'] ?? [];
        
        if ($recordIds) {
            $records = [];
            foreach ($recordIds as $id) {
                $record = $this->loadRecord($entityType, $id);
                if ($record) {
                    $records[] = $record;
                }
            }
        } else {
            $records = $this->loadRecords($entityType, $filters);
        }
        
        $pages = [];
        foreach ($records as $record) {
            // Load related data
            if (isset($template['relations']) && is_array($template['relations'])) {
                foreach ($template['relations'] as $relationKey => $relationConfig) {
                    $record[$relationKey] = $this->loadRelatedData(
                        $entityType,
                        $record['id'],
                        $relationConfig
                    );
                }
            }
            
            // Add common data
            $record['association'] = $this->config['association'] ?? [];
            $record['current_date'] = date('d/m/Y');
            $record['current_year'] = date('Y');
            
            // Render page
            $pages[] = $this->renderTemplate($template['html'], $record);
        }
        
        // Join pages with page break
        $html = implode('<div style="page-break-after: always;"></div>', $pages);
        
        return [
            'html' => $html,
            'css' => $template['css'] ?? '',
            'format' => $template['format'] ?? 'A4',
            'orientation' => $template['orientation'] ?? 'portrait',
            'margins' => $template['margins'] ?? ['top' => 20, 'bottom' => 20, 'left' => 15, 'right' => 15]
        ];
    }
    
    /**
     * Load single record from database
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
     * Load multiple records from database
     * 
     * @param string $entityType Entity type
     * @param array $filters Filters to apply
     * @return array
     */
    private function loadRecords($entityType, $filters = []) {
        $table = $this->getTableName($entityType);
        $sql = "SELECT * FROM {$table} WHERE 1=1";
        $params = [];
        
        // Apply common filters
        if (isset($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['member_status'])) {
            $sql .= " AND member_status = ?";
            $params[] = $filters['member_status'];
        }
        
        if (isset($filters['member_type'])) {
            $sql .= " AND member_type = ?";
            $params[] = $filters['member_type'];
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
        
        // Add ordering
        $sql .= " ORDER BY id ASC";
        
        // Add limit if specified
        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . intval($filters['limit']);
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Load related data from related tables
     * 
     * @param string $entityType Main entity type
     * @param int $recordId Record ID
     * @param array $relationConfig Relation configuration
     * @return array
     */
    private function loadRelatedData($entityType, $recordId, $relationConfig) {
        $table = $relationConfig['table'] ?? null;
        $foreignKey = $relationConfig['foreign_key'] ?? $this->getForeignKey($entityType);
        
        if (!$table) {
            return [];
        }
        
        // Validate table name to prevent SQL injection
        $allowedTables = $this->getAllowedRelationTables($entityType);
        if (!in_array($table, $allowedTables)) {
            throw new \Exception("Invalid relation table: $table for entity $entityType");
        }
        
        $sql = "SELECT * FROM {$table} WHERE {$foreignKey} = ?";
        $params = [$recordId];
        
        // Add additional filters if specified
        if (isset($relationConfig['filters']) && is_array($relationConfig['filters'])) {
            foreach ($relationConfig['filters'] as $field => $value) {
                $sql .= " AND {$field} = ?";
                $params[] = $value;
            }
        }
        
        // Add ordering if specified
        if (isset($relationConfig['order_by'])) {
            $sql .= " ORDER BY " . $relationConfig['order_by'];
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Render template with data
     * 
     * @param string $template Template HTML
     * @param array $data Data to render
     * @return string
     */
    private function renderTemplate($template, $data) {
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
                        
                        // Replace @index (1-based)
                        $itemOutput = str_replace('{{@index}}', $index + 1, $itemOutput);
                        $itemOutput = str_replace('{{@index0}}', $index, $itemOutput); // 0-based
                        
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
        $template = $this->processConditionals($template, $data);
        
        // Replace simple variables
        $template = $this->replaceVariables($template, $data);
        
        return $template;
    }
    
    /**
     * Process conditional blocks
     * 
     * @param string $template Template content
     * @param array $data Data
     * @return string
     */
    private function processConditionals($template, $data) {
        return preg_replace_callback(
            '/\{\{#if\s+([a-zA-Z_\.]+)\}\}(.*?)\{\{\/if\}\}/s',
            function($matches) use ($data) {
                $fieldName = $matches[1];
                $content = $matches[2];
                
                // Support nested field access (e.g., association.name)
                $value = $this->getNestedValue($data, $fieldName);
                
                // Show content if field exists and is truthy
                if ($value !== null && $value !== '' && $value !== false && $value !== 0) {
                    return $content;
                }
                return '';
            },
            $template
        );
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
     * Get nested value from array using dot notation
     * 
     * @param array $data Data array
     * @param string $path Dot-separated path
     * @return mixed
     */
    private function getNestedValue($data, $path) {
        $keys = explode('.', $path);
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
     * Flatten nested array
     * 
     * @param array $array Input array
     * @param string $prefix Prefix for keys
     * @return array
     */
    private function flattenArray($array, $prefix = '') {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;
            
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
     * Get table name for entity type
     * 
     * @param string $entityType Entity type
     * @return string
     */
    private function getTableName($entityType) {
        // Whitelist of allowed entity types
        $allowed = [
            'members' => 'members',
            'junior_members' => 'junior_members',
            'vehicles' => 'vehicles',
            'meetings' => 'meetings',
            'events' => 'events',
            'applications' => 'member_applications'
        ];
        
        if (!isset($allowed[$entityType])) {
            throw new \Exception("Invalid entity type: $entityType");
        }
        
        return $allowed[$entityType];
    }
    
    /**
     * Get foreign key column name for entity type
     * 
     * @param string $entityType Entity type
     * @return string
     */
    private function getForeignKey($entityType) {
        $map = [
            'members' => 'member_id',
            'junior_members' => 'junior_member_id',
            'vehicles' => 'vehicle_id',
            'meetings' => 'meeting_id',
            'events' => 'event_id',
            'applications' => 'application_id'
        ];
        
        return $map[$entityType] ?? 'id';
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
            'applications' => 'submitted_at'
        ];
        
        return $map[$entityType] ?? 'created_at';
    }
    
    /**
     * Get allowed relation tables for entity type
     * 
     * @param string $entityType Entity type
     * @return array
     */
    private function getAllowedRelationTables($entityType) {
        $relations = [
            'members' => [
                'member_contacts',
                'member_addresses',
                'member_licenses',
                'member_courses',
                'member_roles',
                'member_employment',
                'member_education',
                'member_health',
                'member_fees',
                'member_notes',
                'member_sanctions'
            ],
            'junior_members' => [
                'junior_member_guardians',
                'junior_member_contacts',
                'junior_member_addresses',
                'junior_member_health',
                'junior_member_fees',
                'junior_member_notes',
                'junior_member_sanctions'
            ],
            'vehicles' => [
                'vehicle_maintenance',
                'vehicle_documents',
                'vehicle_movements'
            ],
            'meetings' => [
                'meeting_participants',
                'meeting_agenda',
                'meeting_attachments'
            ],
            'events' => [
                'interventions',
                'event_participants',
                'event_vehicles'
            ],
            'applications' => [
                'member_application_guardians'
            ]
        ];
        
        return $relations[$entityType] ?? [];
    }
}
