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
                $records = $this->loadRecords($entityType, $filters);
            }
            
            // Load related data for each record
            foreach ($records as &$record) {
                $record = $this->loadRelatedData($entityType, $record);
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
        
        // Use registration_number for members (matricola), id for others
        if ($entityType === 'members' || $entityType === 'junior_members') {
            $sql = "SELECT * FROM {$table} WHERE id IN ({$placeholders}) ORDER BY CAST(registration_number AS UNSIGNED) ASC";
        } else {
            $sql = "SELECT * FROM {$table} WHERE id IN ({$placeholders}) ORDER BY id ASC";
        }
        
        return $this->db->fetchAll($sql, $recordIds);
    }
    
    /**
     * Load multiple records with filters
     * 
     * @param string $entityType Entity type
     * @param array $filters Filters to apply
     * @return array Records
     */
    private function loadRecords($entityType, $filters = []) {
        $table = $this->getTableName($entityType);
        $sql = "SELECT * FROM {$table} WHERE 1=1";
        $params = [];
        
        // Apply filters based on entity type
        if (isset($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['member_status']) && $entityType === 'members') {
            $sql .= " AND member_status = ?";
            $params[] = $filters['member_status'];
        }
        
        if (isset($filters['member_type']) && $entityType === 'members') {
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
        
        // Add ordering - use registration_number for members (matricola), id for others
        if ($entityType === 'members' || $entityType === 'junior_members') {
            $sql .= " ORDER BY CAST(registration_number AS UNSIGNED) ASC";
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
                $sql .= " ORDER BY " . $config['order_by'];
            }
            
            $record[$key] = $this->db->fetchAll($sql, $params);
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
                    'order_by' => 'assigned_date DESC'
                ],
                'fees' => [
                    'table' => 'member_fees',
                    'foreign_key' => 'member_id',
                    'order_by' => 'year DESC'
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
                    'order_by' => 'maintenance_date DESC'
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
                    'order_by' => 'order_position ASC'
                ]
            ],
            'events' => [
                'interventions' => [
                    'table' => 'interventions',
                    'foreign_key' => 'event_id',
                    'order_by' => 'start_datetime DESC'
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
}
