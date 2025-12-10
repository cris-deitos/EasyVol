<?php
namespace EasyVol\Utils;

use Mpdf\Mpdf;

/**
 * Application PDF Generator
 * 
 * Generates printable membership application forms with all data,
 * accepted clauses, and legal texts.
 * 
 * Usage:
 * ```php
 * $generator = new ApplicationPdfGenerator($db, $config);
 * $pdfPath = $generator->generateApplicationPdf($applicationId);
 * // Returns: "uploads/applications/application_123_1234567890.pdf"
 * ```
 * 
 * Features:
 * - Generates PDFs for both adult and junior applications
 * - Includes association logo and header
 * - All personal data sections
 * - Legal declarations with checkboxes
 * - Privacy and GDPR compliance texts
 * - Signature spaces (adult vs junior templates)
 * - Automatic database update with PDF path
 */
class ApplicationPdfGenerator {
    private $db;
    private $config;
    
    public function __construct($db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Main method: generate PDF for application
     * @param int $applicationId
     * @return string PDF path relative to project root
     * @throws \Exception
     */
    public function generateApplicationPdf($applicationId) {
        try {
            // 1. Load application from database
            $application = $this->loadApplication($applicationId);
            if (!$application) {
                throw new \Exception("Application not found: $applicationId");
            }
            
            // Parse JSON data
            $data = json_decode($application['application_data'], true);
            if (!$data) {
                throw new \Exception("Invalid application data");
            }
            
            // 2. Create mPDF instance
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_header' => 0,
                'margin_footer' => 0,
                'default_font' => 'dejavusans'
            ]);
            
            // 3. Build HTML content
            $html = $this->buildPdfContent($application, $data);
            
            // 4. Write HTML to PDF
            $mpdf->WriteHTML($html);
            
            // 5. Save to uploads/applications/
            $uploadsBasePath = $this->config['uploads']['path'] ?? __DIR__ . '/../../uploads';
            $uploadsDir = $uploadsBasePath . '/applications';
            if (!is_dir($uploadsDir)) {
                if (!mkdir($uploadsDir, 0755, true)) {
                    throw new \Exception("Cannot create directory: $uploadsDir");
                }
            }
            
            $timestamp = time();
            $filename = "application_{$applicationId}_{$timestamp}.pdf";
            $fullPath = $uploadsDir . '/' . $filename;
            $mpdf->Output($fullPath, 'F');
            
            if (!file_exists($fullPath)) {
                throw new \Exception("PDF file not created: $fullPath");
            }
            
            // 6. Update database with pdf_file path
            $relativePath = "uploads/applications/" . $filename;
            try {
                $this->db->execute("UPDATE member_applications SET pdf_file = ? WHERE id = ?", [$relativePath, $applicationId]);
            } catch (\Exception $dbException) {
                // Rollback: delete the PDF file
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
                throw new \Exception("Failed to update database: " . $dbException->getMessage());
            }
            
            // 7. Return relative path
            return $relativePath;
            
        } catch (\Exception $e) {
            error_log("ApplicationPdfGenerator error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Load application from database
     */
    private function loadApplication($applicationId) {
        return $this->db->fetchOne("SELECT * FROM member_applications WHERE id = ?", [$applicationId]);
    }
    
    /**
     * Build complete PDF HTML content
     */
    private function buildPdfContent($application, $data) {
        $html = $this->getStyles();
        $html .= '<body>';
        $html .= $this->addHeader();
        $html .= $this->addTitle($application);
        $html .= $this->addPersonalData($data);
        $html .= $this->addAddresses($data);
        $html .= $this->addContacts($data);
        
        if ($application['application_type'] === 'adult') {
            if (!empty($data['licenses'])) {
                $html .= $this->addLicenses($data);
            }
            // Show courses section if corso_base_pc or courses exist
            if (!empty($data['courses']) || !empty($data['corso_base_pc'])) {
                $html .= $this->addCourses($data);
            }
            // Add professional info section only if data exists
            if (!empty($data['worker_type']) || !empty($data['education_level'])) {
                $html .= $this->addProfessionalInfo($data);
            }
        }
        
        $html .= $this->addHealthInfo($data);
        
        if ($application['application_type'] === 'junior' && !empty($data['guardians'])) {
            $html .= $this->addGuardians($data);
        }
        
        $html .= $this->addRequest();
        $html .= $this->addDeclarations($application['application_type']);
        $html .= $this->addPrivacy();
        $html .= $this->addDocumentsList($application['application_type']);
        $html .= $this->addSignatures($data, $application['application_type']);
        $html .= '</body>';
        
        return $html;
    }
    
    /**
     * Get CSS styles for PDF
     */
    private function getStyles() {
        return '<style>
            body { font-family: dejavusans; font-size: 10pt; }
            h1 { font-size: 14pt; font-weight: bold; text-align: center; margin: 15px 0; }
            h2 { font-size: 11pt; font-weight: bold; background-color: #f8f9fa; padding: 5px 10px; margin: 15px 0 10px 0; border-left: 4px solid #333; }
            .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
            .header-text { font-size: 9pt; margin: 3px 0; }
            .info-row { margin: 5px 0; }
            .label { font-weight: bold; display: inline-block; width: 35%; }
            .value { display: inline-block; width: 60%; }
            .checkbox { font-size: 12pt; margin-right: 5px; }
            .declaration { margin: 10px 0; line-height: 1.6; }
            .legal-text { font-size: 8pt; line-height: 1.4; margin: 10px 0; text-align: justify; }
            .signature-box { border: 1px solid #333; padding: 40px 10px 10px 10px; margin: 20px 0; text-align: center; }
            .document-item { margin: 5px 0 5px 20px; }
        </style>';
    }
    
    /**
     * Add header with logo and association info
     */
    private function addHeader() {
        $assoc = $this->config['association'] ?? [];
        $name = $assoc['name'] ?? 'Associazione Volontari';
        $address = $assoc['address'] ?? '';
        $city = $assoc['city'] ?? '';
        $phone = $assoc['phone'] ?? '';
        $email = $assoc['email'] ?? '';
        
        $html = '<div class="header">';
        
        // Add logo if exists
        $logoPath = $assoc['logo_path'] ?? '';
        if ($logoPath) {
            // Validate logo path to prevent path traversal
            $realPath = realpath($logoPath);
            if ($realPath && file_exists($realPath) && strpos($realPath, realpath(__DIR__ . '/../..')) === 0) {
                $html .= '<img src="' . htmlspecialchars($realPath, ENT_QUOTES, 'UTF-8') . '" style="height: 50px; margin-bottom: 10px;" /><br>';
            }
        }
        
        $html .= '<div style="font-size: 12pt; font-weight: bold;">' . htmlspecialchars($name) . '</div>';
        $html .= '<div class="header-text">' . htmlspecialchars($address) . ' - ' . htmlspecialchars($city) . '</div>';
        $html .= '<div class="header-text">Tel: ' . htmlspecialchars($phone) . ' - Email: ' . htmlspecialchars($email) . '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Add title with application code and date
     */
    private function addTitle($application) {
        // Determine title based on application type
        $isJunior = ($application['application_type'] ?? '') === 'junior';
        $subtitle = $isJunior ? 'SOCIO MINORENNE (CADETTO)' : 'SOCIO MAGGIORENNE';
        
        // Format submission date with validation
        $submissionDate = $application['submitted_at'] ?? '';
        $submissionTimestamp = strtotime($submissionDate);
        $formattedSubmissionDate = ($submissionTimestamp !== false) ? date('d/m/Y', $submissionTimestamp) : $submissionDate;
        
        $html = '<h1>DOMANDA DI ISCRIZIONE<br><span style="font-size: 12pt;">' . $subtitle . '</span></h1>';
        $html .= '<div style="text-align: center; margin-bottom: 20px;">';
        $html .= '<div><strong>Codice domanda:</strong> ' . htmlspecialchars($application['application_code']) . '</div>';
        $html .= '<div><strong>Data compilazione:</strong> ' . htmlspecialchars($formattedSubmissionDate) . '</div>';
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Add personal data section
     */
    private function addPersonalData($data) {
        $html = '<h2>DATI ANAGRAFICI</h2>';
        $html .= '<div class="info-row"><span class="label">Cognome:</span> <span class="value">' . htmlspecialchars($data['last_name'] ?? '') . '</span></div>';
        $html .= '<div class="info-row"><span class="label">Nome:</span> <span class="value">' . htmlspecialchars($data['first_name'] ?? '') . '</span></div>';
        $html .= '<div class="info-row"><span class="label">Codice Fiscale:</span> <span class="value">' . htmlspecialchars($data['tax_code'] ?? '') . '</span></div>';
        
        // Format birth date with validation
        $birthDate = $data['birth_date'] ?? '';
        if (!empty($birthDate)) {
            $timestamp = strtotime($birthDate);
            if ($timestamp !== false) {
                $birthDate = date('d/m/Y', $timestamp);
            }
        }
        $html .= '<div class="info-row"><span class="label">Data di nascita:</span> <span class="value">' . htmlspecialchars($birthDate) . '</span></div>';
        
        // Luogo di nascita with provincia
        $birthPlace = ($data['birth_place'] ?? '');
        if (!empty($data['birth_province'])) {
            $birthPlace .= ' (' . $data['birth_province'] . ')';
        }
        $html .= '<div class="info-row"><span class="label">Luogo di nascita:</span> <span class="value">' . htmlspecialchars($birthPlace) . '</span></div>';
        
        // Gender with label
        $genderLabel = ($data['gender'] ?? '') === 'M' ? 'Maschile' : (($data['gender'] ?? '') === 'F' ? 'Femminile' : ($data['gender'] ?? ''));
        $html .= '<div class="info-row"><span class="label">Sesso:</span> <span class="value">' . htmlspecialchars($genderLabel) . '</span></div>';
        $html .= '<div class="info-row"><span class="label">Nazionalità:</span> <span class="value">' . htmlspecialchars($data['nationality'] ?? 'Italiana') . '</span></div>';
        return $html;
    }
    
    /**
     * Add addresses section
     */
    private function addAddresses($data) {
        $html = '<h2>RESIDENZA</h2>';
        
        // Build residence address using form field names
        $residenceAddress = trim(($data['residence_street'] ?? '') . ' ' . ($data['residence_number'] ?? ''));
        $html .= '<div class="info-row"><span class="label">Indirizzo:</span> <span class="value">' . htmlspecialchars($residenceAddress) . '</span></div>';
        $html .= '<div class="info-row"><span class="label">Città:</span> <span class="value">' . htmlspecialchars($data['residence_city'] ?? '') . '</span></div>';
        $html .= '<div class="info-row"><span class="label">Provincia:</span> <span class="value">' . htmlspecialchars($data['residence_province'] ?? '') . '</span></div>';
        $html .= '<div class="info-row"><span class="label">CAP:</span> <span class="value">' . htmlspecialchars($data['residence_cap'] ?? '') . '</span></div>';
        
        // Check for domicile using form field names
        if (!empty($data['domicile_street'])) {
            $html .= '<h2>DOMICILIO</h2>';
            $domicileAddress = trim(($data['domicile_street'] ?? '') . ' ' . ($data['domicile_number'] ?? ''));
            $html .= '<div class="info-row"><span class="label">Indirizzo:</span> <span class="value">' . htmlspecialchars($domicileAddress) . '</span></div>';
            $html .= '<div class="info-row"><span class="label">Città:</span> <span class="value">' . htmlspecialchars($data['domicile_city'] ?? '') . '</span></div>';
            $html .= '<div class="info-row"><span class="label">Provincia:</span> <span class="value">' . htmlspecialchars($data['domicile_province'] ?? '') . '</span></div>';
            $html .= '<div class="info-row"><span class="label">CAP:</span> <span class="value">' . htmlspecialchars($data['domicile_cap'] ?? '') . '</span></div>';
        }
        
        return $html;
    }
    
    /**
     * Add contacts section
     */
    private function addContacts($data) {
        $html = '<h2>CONTATTI</h2>';
        $html .= '<div class="info-row"><span class="label">Telefono:</span> <span class="value">' . htmlspecialchars($data['phone'] ?? '') . '</span></div>';
        $html .= '<div class="info-row"><span class="label">Cellulare:</span> <span class="value">' . htmlspecialchars($data['mobile'] ?? '') . '</span></div>';
        $html .= '<div class="info-row"><span class="label">Email:</span> <span class="value">' . htmlspecialchars($data['email'] ?? '') . '</span></div>';
        if (!empty($data['pec'])) {
            $html .= '<div class="info-row"><span class="label">PEC:</span> <span class="value">' . htmlspecialchars($data['pec']) . '</span></div>';
        }
        return $html;
    }
    
    /**
     * Add licenses section (adults only)
     */
    private function addLicenses($data) {
        $html = '<h2>PATENTI E ABILITAZIONI</h2>';
        if (isset($data['licenses']) && is_array($data['licenses'])) {
            foreach ($data['licenses'] as $license) {
                $licenseInfo = htmlspecialchars($license['type'] ?? '');
                if (!empty($license['description'])) {
                    $licenseInfo .= ' - ' . htmlspecialchars($license['description']);
                }
                if (!empty($license['number'])) {
                    $licenseInfo .= ' N. ' . htmlspecialchars($license['number']);
                }
                if (!empty($license['issue_date'])) {
                    $timestamp = strtotime($license['issue_date']);
                    if ($timestamp !== false) {
                        $licenseInfo .= ' - Rilasciata: ' . date('d/m/Y', $timestamp);
                    }
                }
                if (!empty($license['expiry_date'])) {
                    $timestamp = strtotime($license['expiry_date']);
                    if ($timestamp !== false) {
                        $licenseInfo .= ' - Scadenza: ' . date('d/m/Y', $timestamp);
                    }
                }
                $html .= '<div class="info-row">• ' . $licenseInfo . '</div>';
            }
        }
        return $html;
    }
    
    /**
     * Add courses section (adults only)
     */
    private function addCourses($data) {
        $html = '<h2>CORSI E SPECIALIZZAZIONI</h2>';
        
        // Corso Base Protezione Civile
        if (!empty($data['corso_base_pc'])) {
            $corsoInfo = 'Corso Base di Protezione Civile riconosciuto da Regione Lombardia - Completato';
            if (!empty($data['corso_base_pc_anno'])) {
                $corsoInfo .= ' (Anno ' . htmlspecialchars($data['corso_base_pc_anno']) . ')';
            }
            $html .= '<div class="info-row">• ' . $corsoInfo . '</div>';
        }
        
        // Other courses
        if (isset($data['courses']) && is_array($data['courses'])) {
            foreach ($data['courses'] as $course) {
                if (empty($course['name'])) continue;
                $courseInfo = htmlspecialchars($course['name'] ?? '');
                if (!empty($course['completion_date'])) {
                    $timestamp = strtotime($course['completion_date']);
                    if ($timestamp !== false) {
                        $courseInfo .= ' - Completato: ' . date('d/m/Y', $timestamp);
                    }
                }
                if (!empty($course['expiry_date'])) {
                    $timestamp = strtotime($course['expiry_date']);
                    if ($timestamp !== false) {
                        $courseInfo .= ' - Scadenza: ' . date('d/m/Y', $timestamp);
                    }
                }
                $html .= '<div class="info-row">• ' . $courseInfo . '</div>';
            }
        }
        return $html;
    }
    
    /**
     * Add health info section
     */
    private function addHealthInfo($data) {
        $html = '<h2>INFORMAZIONI ALIMENTARI</h2>';
        
        // Dietary preferences
        if (!empty($data['health_vegetarian'])) {
            $html .= '<div class="info-row">✓ Vegetariano</div>';
        }
        if (!empty($data['health_vegan'])) {
            $html .= '<div class="info-row">✓ Vegano</div>';
        }
        
        // Allergies and intolerances
        if (!empty($data['health_allergies'])) {
            $html .= '<div class="info-row"><span class="label">Allergie Alimentari:</span> <span class="value">' . htmlspecialchars($data['health_allergies']) . '</span></div>';
        }
        if (!empty($data['health_intolerances'])) {
            $html .= '<div class="info-row"><span class="label">Intolleranze Alimentari:</span> <span class="value">' . htmlspecialchars($data['health_intolerances']) . '</span></div>';
        }
        
        return $html;
    }
    
    /**
     * Add professional info section (adults only)
     */
    private function addProfessionalInfo($data) {
        $html = '';
        
        if (!empty($data['worker_type']) || !empty($data['education_level'])) {
            $html = '<h2>INFORMAZIONI PROFESSIONALI E FORMATIVE</h2>';
            
            if (!empty($data['worker_type'])) {
                $workerTypes = [
                    'studente' => 'Studente',
                    'dipendente_privato' => 'Dipendente Privato',
                    'dipendente_pubblico' => 'Dipendente Pubblico',
                    'lavoratore_autonomo' => 'Lavoratore Autonomo',
                    'disoccupato' => 'Disoccupato',
                    'pensionato' => 'Pensionato'
                ];
                $workerLabel = $workerTypes[$data['worker_type']] ?? $data['worker_type'];
                $html .= '<div class="info-row"><span class="label">Tipo di Lavoratore:</span> <span class="value">' . htmlspecialchars($workerLabel) . '</span></div>';
            }
            
            if (!empty($data['education_level'])) {
                $educationLevels = [
                    'licenza_media' => 'Licenza Media',
                    'diploma_maturita' => 'Diploma di Maturità',
                    'laurea_triennale' => 'Laurea Triennale',
                    'laurea_magistrale' => 'Laurea Magistrale',
                    'dottorato' => 'Dottorato'
                ];
                $educationLabel = $educationLevels[$data['education_level']] ?? $data['education_level'];
                $html .= '<div class="info-row"><span class="label">Titolo di Studio:</span> <span class="value">' . htmlspecialchars($educationLabel) . '</span></div>';
            }
        }
        
        return $html;
    }
    
    /**
     * Add employer section (adults only)
     */
    private function addEmployer($data) {
        $employer = $data['employer'] ?? [];
        if (empty($employer)) {
            return '';
        }
        
        $html = '<h2>DATORE DI LAVORO</h2>';
        $html .= '<div class="info-row"><span class="label">Nome azienda:</span> <span class="value">' . htmlspecialchars($employer['name'] ?? '') . '</span></div>';
        $html .= '<div class="info-row"><span class="label">Indirizzo:</span> <span class="value">' . htmlspecialchars($employer['address'] ?? '') . '</span></div>';
        $html .= '<div class="info-row"><span class="label">Telefono:</span> <span class="value">' . htmlspecialchars($employer['phone'] ?? '') . '</span></div>';
        
        return $html;
    }
    
    /**
     * Add guardians section (juniors only)
     */
    private function addGuardians($data) {
        $guardians = $data['guardians'] ?? [];
        if (empty($guardians)) {
            return '';
        }
        
        $html = '<h2>DATI GENITORI/TUTORI</h2>';
        
        foreach ($guardians as $idx => $guardian) {
            // Use 'type' field (padre, madre, tutore)
            $typeLabels = [
                'padre' => 'PADRE',
                'madre' => 'MADRE',
                'tutore' => 'TUTORE'
            ];
            $type = $guardian['type'] ?? 'tutore';
            $typeLabel = $typeLabels[$type] ?? strtoupper($type);
            
            $html .= '<div style="margin-top: 10px; background: #f8f9fa; padding: 5px;"><strong>' . htmlspecialchars($typeLabel) . '</strong></div>';
            
            // Full name from first_name + last_name
            $fullName = trim(($guardian['first_name'] ?? '') . ' ' . ($guardian['last_name'] ?? ''));
            $html .= '<div class="info-row"><span class="label">Nome e Cognome:</span> <span class="value">' . htmlspecialchars($fullName) . '</span></div>';
            
            if (!empty($guardian['tax_code'])) {
                $html .= '<div class="info-row"><span class="label">Codice Fiscale:</span> <span class="value">' . htmlspecialchars($guardian['tax_code']) . '</span></div>';
            }
            if (!empty($guardian['birth_date'])) {
                $timestamp = strtotime($guardian['birth_date']);
                if ($timestamp !== false) {
                    $html .= '<div class="info-row"><span class="label">Data di Nascita:</span> <span class="value">' . date('d/m/Y', $timestamp) . '</span></div>';
                }
            }
            if (!empty($guardian['birth_place'])) {
                $html .= '<div class="info-row"><span class="label">Luogo di Nascita:</span> <span class="value">' . htmlspecialchars($guardian['birth_place']) . '</span></div>';
            }
            if (!empty($guardian['phone'])) {
                $html .= '<div class="info-row"><span class="label">Telefono:</span> <span class="value">' . htmlspecialchars($guardian['phone']) . '</span></div>';
            }
            if (!empty($guardian['email'])) {
                $html .= '<div class="info-row"><span class="label">Email:</span> <span class="value">' . htmlspecialchars($guardian['email']) . '</span></div>';
            }
        }
        
        return $html;
    }
    
    /**
     * Add request section
     */
    private function addRequest() {
        $assocName = $this->config['association']['name'] ?? 'l\'Associazione';
        
        $html = '<div style="margin: 20px 0; text-align: justify; line-height: 1.6;">';
        $html .= '<strong>CHIEDE</strong> di essere ammesso/a a socio di ' . htmlspecialchars($assocName) . ' ';
        $html .= 'e dichiara di conoscere ed accettare lo Statuto e i Regolamenti dell\'Associazione.';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Add declarations section
     */
    private function addDeclarations($type) {
        $html = '<h2>DICHIARAZIONI E ACCETTAZIONI</h2>';
        
        // Declaration 1
        $html .= '<div class="declaration">';
        $html .= '<span class="checkbox">☑</span> <strong>1. ART. 6 - REGOLAMENTO REGIONALE 18 OTTOBRE 2010</strong><br>';
        $html .= 'Dichiara di avere compiuto il diciottesimo anno di età e di essere in possesso dei requisiti di cui all\'art. 6 del Regolamento Regionale 18 ottobre 2010.';
        $html .= '</div>';
        
        // Declaration 2
        $html .= '<div class="declaration">';
        $html .= '<span class="checkbox">☑</span> <strong>2. ART. 7 - REGOLAMENTO REGIONALE 18 OTTOBRE 2010</strong><br>';
        $html .= 'Dichiara di non incorrere in alcuna delle cause di incompatibilità previste dall\'art. 7 del Regolamento Regionale 18 ottobre 2010.';
        $html .= '</div>';
        
        // Declaration 3
        $html .= '<div class="declaration">';
        $html .= '<span class="checkbox">☑</span> <strong>3. D.LGS. 117/2017 - CODICE DEL TERZO SETTORE</strong><br>';
        $html .= 'Dichiara di conoscere le finalità dell\'associazione e di condividerne gli scopi sociali come definiti dallo Statuto e dal Codice del Terzo Settore (D.lgs. 117/2017).';
        $html .= '</div>';
        
        // Declaration 4
        $html .= '<div class="declaration">';
        $html .= '<span class="checkbox">☑</span> <strong>4. D.LGS. 81/2008 - TUTELA SICUREZZA</strong><br>';
        $html .= 'Dichiara di essere a conoscenza delle norme in materia di sicurezza e salute sui luoghi di lavoro (D.lgs. 81/2008) e si impegna a rispettare tutte le disposizioni in materia durante lo svolgimento delle attività associative.';
        $html .= '</div>';
        
        // Declaration 5
        $html .= '<div class="declaration">';
        $html .= '<span class="checkbox">☑</span> <strong>5. STATUTO ASSOCIATIVO</strong><br>';
        $html .= 'Dichiara di aver preso visione dello Statuto dell\'Associazione e si impegna a rispettarne le disposizioni, i regolamenti interni e le delibere degli organi sociali.';
        $html .= '</div>';
        
        // Declaration 6
        $html .= '<div class="declaration">';
        $html .= '<span class="checkbox">☑</span> <strong>6. CONOSCENZA DEI RISCHI</strong><br>';
        $html .= 'Dichiara di essere consapevole dei rischi connessi alle attività di protezione civile e si impegna a partecipare alle attività di formazione e aggiornamento organizzate dall\'Associazione.';
        $html .= '</div>';
        
        // Declaration 7 - DPR 445/2000
        $html .= '<div class="declaration">';
        $html .= '<span class="checkbox">☑</span> <strong>7. DICHIARAZIONE SOSTITUTIVA</strong><br>';
        $html .= '<div class="legal-text">';
        $html .= 'Ai sensi e per gli effetti degli Artt. 46 - 76 D.P.R. n. 445/2000, chiunque rilasci dichiarazioni mendaci, forma atti falsi o ne faccia uso nei casi previsti dal presente testo unico, è punibile ai sensi del Codice penale e delle leggi speciali in materia.';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Add privacy declarations
     */
    private function addPrivacy() {
        $html = '<h2>PRIVACY E TRATTAMENTO DATI</h2>';
        
        // Privacy 8 - Data processing
        $html .= '<div class="declaration">';
        $html .= '<span class="checkbox">☑</span> <strong>8. TRATTAMENTO DATI PERSONALI</strong><br>';
        $html .= '<div class="legal-text">';
        $html .= 'I dati sopra riportati sono prescritti dalle disposizioni vigenti ai fini del procedimento per il quale sono richiesti e verranno utilizzati esclusivamente per tale scopo. Il Sottoscritto autorizza il trattamento dei dati personali ai sensi dall\'art. 13 del Regolamento Europeo sulla protezione dei dati personali 2016/679, dal D.lgs. 30 giugno 2003, n. 196 e dal D.lgs. 10 agosto 2018, n. 101.';
        $html .= '</div>';
        $html .= '</div>';
        
        // Privacy 9 - Images
        $assocName = $this->config['association']['name'] ?? 'l\'Associazione';
        $html .= '<div class="declaration">';
        $html .= '<span class="checkbox">☑</span> <strong>9. AUTORIZZAZIONE IMMAGINI</strong><br>';
        $html .= '<div class="legal-text">';
        $html .= 'Acconsento a ' . htmlspecialchars($assocName) . ', in base all\'art. 13 del Regolamento Europeo sulla protezione dei dati personali 2016/679, dal D.lgs. 30 giugno 2003, n. 196 e dal D.lgs. 10 agosto 2018, n. 101, la pubblicazione di eventuali fotografie e riprese video-televisive che lo ritrarranno e riprenderanno durante le manifestazioni, corsi, esercitazioni e ad ogni altra attività alle quali prenderà parte. Tali immagini potranno essere inserite su pubblicazioni interne o esterne (notiziari, pieghevoli, brochure), locandine, poster, inviti, giornali, riviste, album, sito internet e tutti i social. Si solleva sin da ora ' . htmlspecialchars($assocName) . ' da qualsiasi responsabilità per uso improprio e fraudolento da parte di terzi dei dati, fotografie e riprese di cui sopra, sia per l\'anno corrente e per gli anni successivi in cui sarò iscritto/a.';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Add documents list
     * @param string $type Application type ('adult' or 'junior')
     */
    private function addDocumentsList($type = 'adult') {
        $html = '<h2>DOCUMENTI DA ALLEGARE</h2>';
        
        if ($type === 'adult') {
            // For adult applications
            $html .= '<div class="document-item">• Copie di Attestati e Specializzazioni personali in campi inerenti alla Protezione Civile</div>';
            $html .= '<div class="document-item">• Copie Patenti di Guida per conduzione di mezzi speciali, Brevetti o Patentini per natanti o velivoli</div>';
        } else {
            // For junior applications - no document attachments required
            $html .= '<div class="document-item">Nessun documento richiesto da allegare.</div>';
        }
        
        return $html;
    }
    
    /**
     * Add signatures section
     */
    private function addSignatures($data, $type) {
        $html = '<div style="margin-top: 30px;">';
        
        // Pre-fill date and place from form data with validation
        $compilationPlace = $data['compilation_place'] ?? '';
        $compilationDate = '';
        if (!empty($data['compilation_date'])) {
            $timestamp = strtotime($data['compilation_date']);
            if ($timestamp !== false) {
                $compilationDate = date('d/m/Y', $timestamp);
            } else {
                $compilationDate = $data['compilation_date']; // Keep original if parsing fails
            }
        }
        
        if ($type === 'junior') {
            // For juniors: minor signature first
            $html .= '<div class="signature-box">';
            $html .= '<strong>Firma del socio minorenne</strong><br><br>';
            $html .= '_______________________________________________<br>';
            $html .= '<div style="font-size: 8pt; margin-top: 5px;">(Per esteso e leggibile)</div>';
            $html .= '</div>';
            
            // Parent/guardian signatures
            $guardians = $data['guardians'] ?? [];
            
            // Father signature
            $html .= '<div class="signature-box">';
            $html .= '<strong>Firma del padre / tutore</strong><br><br>';
            $html .= '_______________________________________________<br>';
            $html .= '<div style="font-size: 8pt; margin-top: 5px;">(Per esteso e leggibile)</div>';
            $html .= '</div>';
            
            // Mother signature
            $html .= '<div class="signature-box">';
            $html .= '<strong>Firma della madre / tutore</strong><br><br>';
            $html .= '_______________________________________________<br>';
            $html .= '<div style="font-size: 8pt; margin-top: 5px;">(Per esteso e leggibile)</div>';
            $html .= '</div>';
        } else {
            // For adults: applicant signature
            $html .= '<div class="signature-box">';
            $html .= '<strong>Firma del richiedente</strong><br><br>';
            $html .= '_______________________________________________<br>';
            $html .= '<div style="font-size: 8pt; margin-top: 5px;">(Per esteso e leggibile)</div>';
            $html .= '</div>';
        }
        
        // Date and place - pre-filled from form data
        $html .= '<div style="margin-top: 20px; text-align: right;">';
        if (!empty($compilationPlace) || !empty($compilationDate)) {
            $html .= 'Luogo: <strong>' . htmlspecialchars($compilationPlace) . '</strong> &nbsp;&nbsp;&nbsp; ';
            $html .= 'Data: <strong>' . htmlspecialchars($compilationDate) . '</strong>';
        } else {
            $html .= 'Data: ___/___/_______ &nbsp;&nbsp;&nbsp; Luogo: _______________________';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
}
