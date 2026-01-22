<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Utils\SimplePdfGenerator;

class PrintTemplateController {
    private $db;
    private $config;
    private $pdfGenerator;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
        $this->pdfGenerator = new SimplePdfGenerator($db, $config);
    }
    
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
    
    public function getById($id) {
        $sql = "SELECT * FROM print_templates WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    public function generatePdf($templateId, $options = [], $outputMode = 'D') {
        $template = $this->getById($templateId);
        if (!$template || !$template['is_active']) {
            throw new \Exception("Template non trovato o non attivo");
        }
        return $this->pdfGenerator->generate($template, $options, $outputMode);
    }
    
    public function getSampleData($entityType) {
        return [
            'association' => $this->config['association'] ?? [],
            'current_date' => date('d/m/Y'),
            'current_year' => date('Y')
        ];
    }
    
    public function exportTemplate($id) {
        $template = $this->getById($id);
        if (!$template) {
            throw new \Exception("Template non trovato");
        }
        unset($template['id'], $template['created_by'], $template['created_at']);
        unset($template['updated_by'], $template['updated_at']);
        return $template;
    }
}
