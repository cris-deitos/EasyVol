<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Utils\TemplateEngine;
use Mpdf\Mpdf;

/**
 * Enhanced Print Controller
 * 
 * Manages both file-based and database-based print templates
 * Supports multi-table data and advanced PDF generation
 */
class EnhancedPrintController {
    private $db;
    private $config;
    private $templateEngine;
    private $templateDir;
    
    /**
     * Constructor
     * 
     * @param Database $db Database instance
     * @param array $config Configuration
     */
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
        $this->templateDir = __DIR__ . '/../../templates';
        $this->templateEngine = new TemplateEngine($this->templateDir, $config, $db);
    }
    
    /**
     * Get all available templates for an entity type
     * 
     * @param string $entityType Entity type
     * @return array Combined list of file-based and database templates
     */
    public function getAvailableTemplates($entityType) {
        $templates = [];
        
        // Get file-based templates
        $fileTemplates = $this->templateEngine->getAvailableTemplates($entityType);
        foreach ($fileTemplates as $template) {
            $templates[] = [
                'id' => 'file_' . $template['file'],
                'name' => $template['name'],
                'description' => $template['description'],
                'type' => $template['type'],
                'source' => 'file',
                'file' => $template['file'],
                'format' => $template['format'],
                'orientation' => $template['orientation']
            ];
        }
        
        // Get database templates (for backward compatibility)
        $dbTemplates = $this->getDbTemplates($entityType);
        foreach ($dbTemplates as $template) {
            $templates[] = [
                'id' => 'db_' . $template['id'],
                'name' => $template['name'],
                'description' => $template['description'] ?? '',
                'type' => $template['template_type'],
                'source' => 'database',
                'db_id' => $template['id'],
                'format' => $template['page_format'],
                'orientation' => $template['page_orientation']
            ];
        }
        
        return $templates;
    }
    
    /**
     * Get database templates (legacy)
     * 
     * @param string $entityType Entity type
     * @return array
     */
    private function getDbTemplates($entityType) {
        $sql = "SELECT * FROM print_templates 
                WHERE entity_type = ? AND is_active = 1 
                ORDER BY is_default DESC, name ASC";
        return $this->db->fetchAll($sql, [$entityType]);
    }
    
    /**
     * Generate document from template
     * 
     * @param string $templateId Template ID (format: source_identifier)
     * @param string $entityType Entity type
     * @param array $options Generation options
     * @return array Document data
     */
    public function generate($templateId, $entityType, $options = []) {
        // Parse template ID to determine source
        list($source, $identifier) = $this->parseTemplateId($templateId);
        
        if ($source === 'file') {
            return $this->templateEngine->generate($entityType, $identifier, $options);
        } else {
            // Use legacy database template system
            return $this->generateFromDbTemplate($identifier, $options);
        }
    }
    
    /**
     * Generate PDF from template
     * 
     * @param string $templateId Template ID
     * @param string $entityType Entity type
     * @param array $options Generation options
     * @param string $outputMode Output mode (D=download, I=inline, F=file, S=string)
     * @return mixed PDF output
     */
    public function generatePdf($templateId, $entityType, $options = [], $outputMode = 'D') {
        // Generate document data
        $document = $this->generate($templateId, $entityType, $options);
        
        // Configure mPDF
        $mpdfConfig = [
            'tempDir' => sys_get_temp_dir(),
            'default_font' => 'dejavusans',
            'default_font_size' => 10,
            'margin_top' => $document['margins']['top'] ?? 20,
            'margin_bottom' => $document['margins']['bottom'] ?? 20,
            'margin_left' => $document['margins']['left'] ?? 15,
            'margin_right' => $document['margins']['right'] ?? 15,
            'format' => $document['format'] ?? 'A4',
            'orientation' => $document['orientation'] === 'landscape' ? 'L' : 'P'
        ];
        
        // Handle custom page sizes
        if ($document['format'] === 'custom' && isset($document['page_size'])) {
            $size = $document['page_size'];
            $mpdfConfig['format'] = [$size['width'], $size['height']];
        }
        
        $mpdf = new Mpdf($mpdfConfig);
        
        // Add CSS
        if (!empty($document['css'])) {
            $mpdf->WriteHTML($document['css'], \Mpdf\HTMLParserMode::HEADER_CSS);
        }
        
        // Add HTML content
        $mpdf->WriteHTML($document['html'], \Mpdf\HTMLParserMode::HTML_BODY);
        
        // Generate filename
        $filename = 'documento_' . date('Y-m-d_His') . '.pdf';
        if (isset($options['filename'])) {
            $filename = $options['filename'];
        }
        
        return $mpdf->Output($filename, $outputMode);
    }
    
    /**
     * Parse template ID to get source and identifier
     * 
     * @param string $templateId Template ID
     * @return array [source, identifier]
     */
    private function parseTemplateId($templateId) {
        if (strpos($templateId, '_') !== false) {
            $parts = explode('_', $templateId, 2);
            return [$parts[0], $parts[1]];
        }
        
        // Default to database for backward compatibility
        return ['db', $templateId];
    }
    
    /**
     * Generate from database template (legacy support)
     * 
     * @param int $dbId Database template ID
     * @param array $options Generation options
     * @return array
     */
    private function generateFromDbTemplate($dbId, $options) {
        // Use the existing PrintTemplateController for database templates
        require_once __DIR__ . '/PrintTemplateController.php';
        $controller = new PrintTemplateController($this->db, $this->config);
        return $controller->generate($dbId, $options);
    }
    
    /**
     * Create a new file-based template
     * 
     * @param string $entityType Entity type
     * @param array $templateData Template data
     * @return bool Success status
     */
    public function createFileTemplate($entityType, $templateData) {
        $entityDir = $this->templateDir . '/' . $entityType;
        
        if (!is_dir($entityDir)) {
            mkdir($entityDir, 0755, true);
        }
        
        // Generate filename from template name
        $filename = $this->sanitizeFilename($templateData['name']) . '.json';
        $filepath = $entityDir . '/' . $filename;
        
        // Check if file already exists
        if (file_exists($filepath)) {
            throw new \Exception("Template file already exists: $filename");
        }
        
        // Ensure required fields
        if (!isset($templateData['name']) || !isset($templateData['html'])) {
            throw new \Exception("Template must have 'name' and 'html' fields");
        }
        
        // Set defaults
        $templateData['type'] = $templateData['type'] ?? 'single';
        $templateData['format'] = $templateData['format'] ?? 'A4';
        $templateData['orientation'] = $templateData['orientation'] ?? 'portrait';
        
        // Save to file
        $json = json_encode($templateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($filepath, $json) === false) {
            throw new \Exception("Failed to save template file");
        }
        
        return true;
    }
    
    /**
     * Update file-based template
     * 
     * @param string $entityType Entity type
     * @param string $filename Template filename
     * @param array $templateData Updated template data
     * @return bool
     */
    public function updateFileTemplate($entityType, $filename, $templateData) {
        $filepath = $this->templateDir . '/' . $entityType . '/' . $filename;
        
        if (!file_exists($filepath)) {
            throw new \Exception("Template file not found: $filename");
        }
        
        // Save updated data
        $json = json_encode($templateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($filepath, $json) === false) {
            throw new \Exception("Failed to update template file");
        }
        
        return true;
    }
    
    /**
     * Delete file-based template
     * 
     * @param string $entityType Entity type
     * @param string $filename Template filename
     * @return bool
     */
    public function deleteFileTemplate($entityType, $filename) {
        $filepath = $this->templateDir . '/' . $entityType . '/' . $filename;
        
        if (!file_exists($filepath)) {
            throw new \Exception("Template file not found: $filename");
        }
        
        return unlink($filepath);
    }
    
    /**
     * Migrate database template to file-based template
     * 
     * @param int $dbId Database template ID
     * @return string Created filename
     */
    public function migrateDbTemplateToFile($dbId) {
        // Load database template
        $sql = "SELECT * FROM print_templates WHERE id = ?";
        $dbTemplate = $this->db->fetchOne($sql, [$dbId]);
        
        if (!$dbTemplate) {
            throw new \Exception("Database template not found: $dbId");
        }
        
        // Convert to file template format
        $fileTemplate = [
            'name' => $dbTemplate['name'],
            'description' => $dbTemplate['description'] ?? '',
            'type' => $dbTemplate['template_type'],
            'format' => $dbTemplate['page_format'],
            'orientation' => $dbTemplate['page_orientation'],
            'html' => $dbTemplate['html_content'],
            'css' => $dbTemplate['css_content'] ?? '',
            'margins' => [
                'top' => 20,
                'bottom' => 20,
                'left' => 15,
                'right' => 15
            ]
        ];
        
        // Add relations if present
        if (!empty($dbTemplate['relations'])) {
            $relations = json_decode($dbTemplate['relations'], true);
            if ($relations) {
                $fileTemplate['relations'] = $relations;
            }
        }
        
        // Create file template
        $this->createFileTemplate($dbTemplate['entity_type'], $fileTemplate);
        
        $filename = $this->sanitizeFilename($fileTemplate['name']) . '.json';
        return $filename;
    }
    
    /**
     * Sanitize filename
     * 
     * @param string $name Input name
     * @return string Sanitized filename
     */
    private function sanitizeFilename($name) {
        // Remove accents
        $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        // Remove special characters
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        // Remove multiple underscores
        $name = preg_replace('/_+/', '_', $name);
        // Trim underscores
        $name = trim($name, '_');
        // Lowercase
        $name = strtolower($name);
        
        return $name;
    }
}
