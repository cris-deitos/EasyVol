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
    
    public function create($data, $userId) {
        // Validate required fields
        $requiredFields = ['name', 'template_type', 'data_scope', 'entity_type', 'html_content'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Campo obbligatorio mancante: $field");
            }
        }
        
        $sql = "INSERT INTO print_templates (
            name, description, template_type, data_scope, entity_type,
            html_content, css_content, page_format, page_orientation,
            is_active, is_default, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['name'],
            $data['description'] ?? null,
            $data['template_type'],
            $data['data_scope'],
            $data['entity_type'],
            $data['html_content'],
            $data['css_content'] ?? null,
            $data['page_format'] ?? 'A4',
            $data['page_orientation'] ?? 'portrait',
            $data['is_active'] ?? 1,
            $data['is_default'] ?? 0,
            $userId,
            $userId
        ];
        
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data, $userId) {
        // Validate ID
        if (!is_numeric($id) || $id <= 0) {
            throw new \InvalidArgumentException("ID template non valido");
        }
        
        // Validate required fields
        $requiredFields = ['name', 'template_type', 'data_scope', 'entity_type', 'html_content'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Campo obbligatorio mancante: $field");
            }
        }
        
        $sql = "UPDATE print_templates SET
            name = ?,
            description = ?,
            template_type = ?,
            data_scope = ?,
            entity_type = ?,
            html_content = ?,
            css_content = ?,
            page_format = ?,
            page_orientation = ?,
            is_active = ?,
            is_default = ?,
            updated_by = ?
        WHERE id = ?";
        
        $params = [
            $data['name'],
            $data['description'] ?? null,
            $data['template_type'],
            $data['data_scope'],
            $data['entity_type'],
            $data['html_content'],
            $data['css_content'] ?? null,
            $data['page_format'] ?? 'A4',
            $data['page_orientation'] ?? 'portrait',
            $data['is_active'] ?? 1,
            $data['is_default'] ?? 0,
            $userId,
            $id
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function getAvailableVariables($entityType) {
        // Validate entity type
        $validEntityTypes = ['members', 'junior_members', 'member_applications', 'vehicles', 'meetings', 'events'];
        if (!in_array($entityType, $validEntityTypes)) {
            throw new \InvalidArgumentException("Tipo entità non valido: $entityType");
        }
        
        $variables = [
            'association_name' => 'Nome associazione',
            'association_address' => 'Indirizzo associazione',
            'association_city' => 'Città associazione',
            'association_postal_code' => 'CAP associazione',
            'association_phone' => 'Telefono associazione',
            'association_email' => 'Email associazione',
            'association_fiscal_code' => 'Codice Fiscale associazione',
            'current_date' => 'Data corrente',
            'current_year' => 'Anno corrente'
        ];
        
        switch ($entityType) {
            case 'members':
                $variables = array_merge($variables, [
                    'registration_number' => 'Numero Matricola',
                    'badge_number' => 'Numero Tesserino',
                    'member_type' => 'Tipo Socio',
                    'member_status' => 'Stato Socio',
                    'volunteer_status' => 'Stato Volontario',
                    'first_name' => 'Nome',
                    'last_name' => 'Cognome',
                    'birth_date' => 'Data di Nascita',
                    'birth_place' => 'Luogo di Nascita',
                    'birth_province' => 'Provincia di Nascita',
                    'tax_code' => 'Codice Fiscale',
                    'gender' => 'Sesso',
                    'nationality' => 'Nazionalità',
                    'registration_date' => 'Data Iscrizione',
                    'approval_date' => 'Data Approvazione',
                    'address' => 'Indirizzo',
                    'city' => 'Città',
                    'postal_code' => 'CAP',
                    'province' => 'Provincia',
                    'country' => 'Paese',
                    'phone' => 'Telefono',
                    'mobile' => 'Cellulare',
                    'email' => 'Email',
                    'photo' => 'Foto',
                    'education_level' => 'Titolo di Studio',
                    'worker_type' => 'Tipo Lavoratore',
                    'corso_base_completato' => 'Corso Base Completato',
                    'corso_base_anno' => 'Anno Corso Base',
                    'notes' => 'Note'
                ]);
                break;
                
            case 'junior_members':
                $variables = array_merge($variables, [
                    'first_name' => 'Nome',
                    'last_name' => 'Cognome',
                    'birth_date' => 'Data di Nascita',
                    'birth_place' => 'Luogo di Nascita',
                    'birth_province' => 'Provincia di Nascita',
                    'tax_code' => 'Codice Fiscale',
                    'gender' => 'Sesso',
                    'school' => 'Scuola',
                    'class_year' => 'Anno Scolastico',
                    'address' => 'Indirizzo',
                    'city' => 'Città',
                    'postal_code' => 'CAP',
                    'province' => 'Provincia',
                    'guardian1_first_name' => 'Nome Tutore 1',
                    'guardian1_last_name' => 'Cognome Tutore 1',
                    'guardian1_phone' => 'Telefono Tutore 1',
                    'guardian1_email' => 'Email Tutore 1',
                    'guardian2_first_name' => 'Nome Tutore 2',
                    'guardian2_last_name' => 'Cognome Tutore 2',
                    'guardian2_phone' => 'Telefono Tutore 2',
                    'guardian2_email' => 'Email Tutore 2',
                    'registration_date' => 'Data Iscrizione',
                    'notes' => 'Note'
                ]);
                break;
                
            case 'member_applications':
                $variables = array_merge($variables, [
                    'application_date' => 'Data Domanda',
                    'first_name' => 'Nome',
                    'last_name' => 'Cognome',
                    'birth_date' => 'Data di Nascita',
                    'birth_place' => 'Luogo di Nascita',
                    'tax_code' => 'Codice Fiscale',
                    'address' => 'Indirizzo',
                    'city' => 'Città',
                    'postal_code' => 'CAP',
                    'province' => 'Provincia',
                    'phone' => 'Telefono',
                    'mobile' => 'Cellulare',
                    'email' => 'Email',
                    'status' => 'Stato Domanda',
                    'notes' => 'Note'
                ]);
                break;
                
            case 'vehicles':
                $variables = array_merge($variables, [
                    'license_plate' => 'Targa',
                    'brand' => 'Marca',
                    'model' => 'Modello',
                    'vehicle_type' => 'Tipo Mezzo',
                    'year' => 'Anno',
                    'purchase_date' => 'Data Acquisto',
                    'registration_date' => 'Data Immatricolazione',
                    'insurance_company' => 'Compagnia Assicurazione',
                    'insurance_policy' => 'Numero Polizza',
                    'insurance_expiry' => 'Scadenza Assicurazione',
                    'revision_expiry' => 'Scadenza Revisione',
                    'status' => 'Stato',
                    'notes' => 'Note'
                ]);
                break;
                
            case 'meetings':
                $variables = array_merge($variables, [
                    'title' => 'Titolo',
                    'meeting_type' => 'Tipo Riunione',
                    'meeting_date' => 'Data Riunione',
                    'start_time' => 'Ora Inizio',
                    'end_time' => 'Ora Fine',
                    'location' => 'Luogo',
                    'description' => 'Descrizione',
                    'agenda' => 'Ordine del Giorno',
                    'notes' => 'Note',
                    'status' => 'Stato'
                ]);
                break;
                
            case 'events':
                $variables = array_merge($variables, [
                    'title' => 'Titolo',
                    'event_type' => 'Tipo Evento',
                    'start_date' => 'Data Inizio',
                    'end_date' => 'Data Fine',
                    'location' => 'Luogo',
                    'description' => 'Descrizione',
                    'max_participants' => 'Partecipanti Massimi',
                    'status' => 'Stato',
                    'notes' => 'Note'
                ]);
                break;
        }
        
        // Convert to format expected by the editor
        $result = [];
        foreach ($variables as $name => $description) {
            $result[] = [
                'name' => $name,
                'description' => $description
            ];
        }
        
        return $result;
    }
    
    public function getAvailableRelations($entityType) {
        // Relations are no longer used after migration 023_simplify_print_templates.sql
        // Keeping this method for backwards compatibility
        return [];
    }
    
    public function delete($id) {
        $template = $this->getById($id);
        if (!$template) {
            throw new \Exception("Template non trovato");
        }
        
        $sql = "DELETE FROM print_templates WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    /**
     * Import template from JSON
     * 
     * @param array $templateData Template data from JSON
     * @param int $userId User ID
     * @return int Inserted template ID
     */
    public function importTemplate($templateData, $userId) {
        // Use the create method which handles all validation and insertion
        $templateData['created_by'] = $userId;
        $templateData['updated_by'] = $userId;
        return $this->create($templateData, $userId);
    }
}
