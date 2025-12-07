<?php
namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Utils\PdfGenerator;
use EasyVol\Utils\EmailSender;

/**
 * Application Controller
 * 
 * Gestisce le domande di iscrizione pubbliche
 */
class ApplicationController {
    private $db;
    private $config;
    
    public function __construct(Database $db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Crea nuova domanda di iscrizione
     * 
     * @param array $data Dati domanda
     * @param bool $isJunior Se è un minorenne
     * @return array ['success' => bool, 'id' => int, 'code' => string, 'error' => string]
     */
    public function create($data, $isJunior = false) {
        try {
            $this->db->beginTransaction();
            
            // Genera codice univoco
            $code = $this->generateUniqueCode();
            
            // Inserisci domanda
            $sql = "INSERT INTO member_applications (
                application_code, application_type, status,
                last_name, first_name, birth_date, birth_place, birth_province,
                tax_code, gender, nationality,
                email, phone,
                privacy_accepted, terms_accepted, photo_release_accepted,
                created_at
            ) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $code,
                $isJunior ? 'junior' : 'adult',
                $data['last_name'],
                $data['first_name'],
                $data['birth_date'],
                $data['birth_place'] ?? null,
                $data['birth_province'] ?? null,
                $data['tax_code'] ?? null,
                $data['gender'] ?? null,
                $data['nationality'] ?? 'Italiana',
                $data['email'],
                $data['phone'] ?? null,
                $data['privacy_accepted'] ? 1 : 0,
                $data['terms_accepted'] ? 1 : 0,
                $data['photo_release_accepted'] ? 1 : 0
            ];
            
            $this->db->execute($sql, $params);
            $applicationId = $this->db->lastInsertId();
            
            // Se minorenne, inserisci dati tutore
            if ($isJunior && !empty($data['guardian_data'])) {
                $this->saveGuardianData($applicationId, $data['guardian_data']);
            }
            
            // Genera PDF
            $pdfPath = $this->generateApplicationPdf($applicationId, $data, $isJunior);
            
            // Aggiorna con path PDF
            $this->db->execute(
                "UPDATE member_applications SET pdf_path = ? WHERE id = ?",
                [$pdfPath, $applicationId]
            );
            
            // Invia email
            $this->sendApplicationEmails($applicationId, $data, $pdfPath);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'id' => $applicationId,
                'code' => $code,
                'error' => null
            ];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Errore creazione domanda: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Crea nuova domanda di iscrizione per adulto
     * 
     * @param array $data Dati domanda completa
     * @return array ['success' => bool, 'id' => int, 'code' => string, 'error' => string]
     */
    public function createAdult($data) {
        try {
            $this->db->beginTransaction();
            
            // Genera codice univoco
            $code = $this->generateUniqueCode();
            
            // Prepara i dati JSON
            $applicationData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            
            // Inserisci domanda
            $sql = "INSERT INTO member_applications (
                application_code, application_type, status,
                application_data, submitted_at
            ) VALUES (?, 'adult', 'pending', ?, NOW())";
            
            $params = [$code, $applicationData];
            
            $this->db->execute($sql, $params);
            $applicationId = $this->db->lastInsertId();
            
            // Genera PDF
            $pdfPath = $this->generateAdultApplicationPdf($applicationId, $data, $code);
            
            // Aggiorna con path PDF
            $this->db->execute(
                "UPDATE member_applications SET pdf_file = ? WHERE id = ?",
                [$pdfPath, $applicationId]
            );
            
            // Invia email
            $this->sendAdultApplicationEmails($data, $code, $pdfPath);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'id' => $applicationId,
                'code' => $code,
                'error' => null
            ];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Errore creazione domanda adulto: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Crea nuova domanda di iscrizione per minorenne
     * 
     * @param array $data Dati domanda completa
     * @return array ['success' => bool, 'id' => int, 'code' => string, 'error' => string]
     */
    public function createJunior($data) {
        try {
            $this->db->beginTransaction();
            
            // Genera codice univoco
            $code = $this->generateUniqueCode();
            
            // Prepara i dati JSON
            $applicationData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            
            // Inserisci domanda
            $sql = "INSERT INTO member_applications (
                application_code, application_type, status,
                application_data, submitted_at
            ) VALUES (?, 'junior', 'pending', ?, NOW())";
            
            $params = [$code, $applicationData];
            
            $this->db->execute($sql, $params);
            $applicationId = $this->db->lastInsertId();
            
            // Genera PDF
            $pdfPath = $this->generateJuniorApplicationPdf($applicationId, $data, $code);
            
            // Aggiorna con path PDF
            $this->db->execute(
                "UPDATE member_applications SET pdf_file = ? WHERE id = ?",
                [$pdfPath, $applicationId]
            );
            
            // Invia email
            $this->sendJuniorApplicationEmails($data, $code, $pdfPath);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'id' => $applicationId,
                'code' => $code,
                'error' => null
            ];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Errore creazione domanda minorenne: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Ottieni lista domande
     * 
     * @param array $filters Filtri
     * @param int $page Pagina
     * @param int $perPage Elementi per pagina
     * @return array
     */
    public function getAll($filters = [], $page = 1, $perPage = 20) {
        $sql = "SELECT * FROM member_applications WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['type'])) {
            $sql .= " AND application_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['search'])) {
            // Use JSON functions for more efficient searching
            $sql .= " AND (application_code LIKE ? 
                      OR JSON_EXTRACT(application_data, '$.last_name') LIKE ?
                      OR JSON_EXTRACT(application_data, '$.first_name') LIKE ?
                      OR JSON_EXTRACT(application_data, '$.email') LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY submitted_at DESC";
        
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $applications = $this->db->fetchAll($sql, $params);
        
        // Decode JSON data and extract basic info
        foreach ($applications as &$app) {
            $data = json_decode($app['application_data'], true);
            if ($data) {
                $app['last_name'] = $data['last_name'] ?? '';
                $app['first_name'] = $data['first_name'] ?? '';
                $app['email'] = $data['email'] ?? '';
                $app['birth_date'] = $data['birth_date'] ?? '';
                $app['tax_code'] = $data['tax_code'] ?? '';
                $app['phone'] = $data['mobile'] ?? $data['phone'] ?? '';
            }
        }
        
        return $applications;
    }
    
    /**
     * Ottieni singola domanda
     * 
     * @param int $id ID domanda
     * @return array|false
     */
    public function get($id) {
        return $this->db->fetchOne("SELECT * FROM member_applications WHERE id = ?", [$id]);
    }
    
    /**
     * Approva domanda e crea socio
     * 
     * @param int $id ID domanda
     * @param int $userId ID utente che approva
     * @return bool
     */
    public function approve($id, $userId) {
        try {
            $this->db->beginTransaction();
            
            $application = $this->get($id);
            if (!$application || $application['status'] !== 'pending') {
                throw new \Exception('Domanda non valida');
            }
            
            // Decode application data
            $data = json_decode($application['application_data'], true);
            if (!$data) {
                throw new \Exception('Dati domanda non validi');
            }
            
            // Crea socio o cadetto
            if ($application['application_type'] === 'junior') {
                $memberId = $this->createJuniorMemberFromApplication($data, $userId);
            } else {
                $memberId = $this->createMemberFromApplication($data, $userId);
            }
            
            // Aggiorna domanda con timestamp unico
            // processed_at e approved_at hanno lo stesso valore per domande approvate
            // processed_at viene usato anche per le domande rifiutate, mentre approved_at solo per approvate
            $now = date('Y-m-d H:i:s');
            $sql = "UPDATE member_applications SET 
                    status = 'approved',
                    processed_by = ?,
                    processed_at = ?,
                    approved_at = ?,
                    member_id = ?
                    WHERE id = ?";
            
            $this->db->execute($sql, [$userId, $now, $now, $memberId, $id]);
            
            // Invia email approvazione
            $this->sendApprovalEmailFromData($data, $application['application_type']);
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Errore approvazione domanda: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rifiuta domanda
     * 
     * @param int $id ID domanda
     * @param int $userId ID utente
     * @param string $reason Motivazione
     * @return bool
     */
    public function reject($id, $userId, $reason = '') {
        try {
            $application = $this->get($id);
            if (!$application) {
                return false;
            }
            
            $sql = "UPDATE member_applications SET 
                    status = 'rejected',
                    rejected_by = ?,
                    rejected_at = NOW(),
                    rejection_reason = ?
                    WHERE id = ?";
            
            $this->db->execute($sql, [$userId, $reason, $id]);
            
            // Invia email rifiuto
            $this->sendRejectionEmail($application, $reason);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Errore rifiuto domanda: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Genera codice univoco per domanda
     * 
     * @return string
     */
    private function generateUniqueCode() {
        do {
            $code = 'APP-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $existing = $this->db->fetchOne(
                "SELECT id FROM member_applications WHERE application_code = ?",
                [$code]
            );
        } while ($existing);
        
        return $code;
    }
    
    /**
     * Salva dati tutore
     * 
     * @param int $applicationId ID domanda
     * @param array $guardianData Dati tutore
     */
    private function saveGuardianData($applicationId, $guardianData) {
        $sql = "INSERT INTO member_application_guardians (
            application_id, guardian_type,
            last_name, first_name, birth_date, birth_place,
            tax_code, phone, email
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $applicationId,
            $guardianData['type'] ?? 'parent',
            $guardianData['last_name'],
            $guardianData['first_name'],
            $guardianData['birth_date'] ?? null,
            $guardianData['birth_place'] ?? null,
            $guardianData['tax_code'] ?? null,
            $guardianData['phone'] ?? null,
            $guardianData['email'] ?? null
        ];
        
        $this->db->execute($sql, $params);
    }
    
    /**
     * Genera PDF domanda adulto
     * 
     * @param int $id ID domanda
     * @param array $data Dati domanda
     * @param string $code Codice domanda
     * @return string Path PDF
     */
    private function generateAdultApplicationPdf($id, $data, $code) {
        $pdfGen = new PdfGenerator($this->config);
        
        $html = $pdfGen->getHeaderHtml();
        $html .= '<h2 style="text-align: center; color: #0d6efd;">DOMANDA DI ISCRIZIONE<br>SOCIO MAGGIORENNE</h2>';
        $html .= '<p style="text-align: center;"><strong>Codice domanda:</strong> ' . htmlspecialchars($code) . '</p>';
        $html .= '<hr>';
        
        // Dati anagrafici
        $html .= '<h3 style="background: #f0f0f0; padding: 5px;">DATI ANAGRAFICI</h3>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr><td style="width: 30%; padding: 5px;"><strong>Cognome:</strong></td><td>' . htmlspecialchars($data['last_name']) . '</td></tr>';
        $html .= '<tr><td style="padding: 5px;"><strong>Nome:</strong></td><td>' . htmlspecialchars($data['first_name']) . '</td></tr>';
        $html .= '<tr><td style="padding: 5px;"><strong>Codice Fiscale:</strong></td><td>' . htmlspecialchars($data['tax_code']) . '</td></tr>';
        $html .= '<tr><td style="padding: 5px;"><strong>Data di Nascita:</strong></td><td>' . date('d/m/Y', strtotime($data['birth_date'])) . '</td></tr>';
        $html .= '<tr><td style="padding: 5px;"><strong>Luogo di Nascita:</strong></td><td>' . htmlspecialchars($data['birth_place']) . ' (' . htmlspecialchars($data['birth_province']) . ')</td></tr>';
        $html .= '<tr><td style="padding: 5px;"><strong>Sesso:</strong></td><td>' . htmlspecialchars($data['gender']) . '</td></tr>';
        $html .= '<tr><td style="padding: 5px;"><strong>Nazionalità:</strong></td><td>' . htmlspecialchars($data['nationality']) . '</td></tr>';
        $html .= '</table>';
        
        // Residenza
        $html .= '<h3 style="background: #f0f0f0; padding: 5px; margin-top: 10px;">RESIDENZA</h3>';
        $html .= '<p>' . htmlspecialchars($data['residence_street']) . ' ' . htmlspecialchars($data['residence_number']) . ', ';
        $html .= htmlspecialchars($data['residence_city']) . ' (' . htmlspecialchars($data['residence_province']) . ') - ' . htmlspecialchars($data['residence_cap']) . '</p>';
        
        // Domicilio se presente
        if (!empty($data['domicile_street'])) {
            $html .= '<h3 style="background: #f0f0f0; padding: 5px; margin-top: 10px;">DOMICILIO</h3>';
            $html .= '<p>' . htmlspecialchars($data['domicile_street']) . ' ' . htmlspecialchars($data['domicile_number']) . ', ';
            $html .= htmlspecialchars($data['domicile_city']) . ' (' . htmlspecialchars($data['domicile_province']) . ') - ' . htmlspecialchars($data['domicile_cap']) . '</p>';
        }
        
        // Recapiti
        $html .= '<h3 style="background: #f0f0f0; padding: 5px; margin-top: 10px;">RECAPITI</h3>';
        $html .= '<p><strong>Cellulare:</strong> ' . htmlspecialchars($data['mobile']) . '<br>';
        if (!empty($data['phone'])) $html .= '<strong>Telefono:</strong> ' . htmlspecialchars($data['phone']) . '<br>';
        $html .= '<strong>Email:</strong> ' . htmlspecialchars($data['email']) . '<br>';
        if (!empty($data['pec'])) $html .= '<strong>PEC:</strong> ' . htmlspecialchars($data['pec']) . '<br>';
        $html .= '</p>';
        
        // Patenti
        if (!empty($data['licenses'])) {
            $html .= '<h3 style="background: #f0f0f0; padding: 5px; margin-top: 10px;">PATENTI E ABILITAZIONI</h3>';
            foreach ($data['licenses'] as $license) {
                $html .= '<p><strong>' . htmlspecialchars($license['type']) . ':</strong> ';
                if (!empty($license['description'])) $html .= htmlspecialchars($license['description']) . ' - ';
                $html .= 'N. ' . htmlspecialchars($license['number'] ?? 'N/D');
                if (!empty($license['expiry_date'])) $html .= ' - Scad.: ' . date('d/m/Y', strtotime($license['expiry_date']));
                $html .= '</p>';
            }
        }
        
        // Corsi
        if (!empty($data['courses'])) {
            $html .= '<h3 style="background: #f0f0f0; padding: 5px; margin-top: 10px;">CORSI E SPECIALIZZAZIONI</h3>';
            foreach ($data['courses'] as $course) {
                $html .= '<p><strong>' . htmlspecialchars($course['name']) . '</strong>';
                if (!empty($course['completion_date'])) $html .= ' - Completato: ' . date('d/m/Y', strtotime($course['completion_date']));
                if (!empty($course['expiry_date'])) $html .= ' - Scad.: ' . date('d/m/Y', strtotime($course['expiry_date']));
                $html .= '</p>';
            }
        }
        
        // Salute
        $html .= '<h3 style="background: #f0f0f0; padding: 5px; margin-top: 10px;">INFORMAZIONI SANITARIE</h3>';
        if ($data['health_vegetarian']) $html .= '<p>Vegetariano</p>';
        if ($data['health_vegan']) $html .= '<p>Vegano</p>';
        if (!empty($data['health_allergies'])) $html .= '<p><strong>Allergie:</strong> ' . htmlspecialchars($data['health_allergies']) . '</p>';
        if (!empty($data['health_intolerances'])) $html .= '<p><strong>Intolleranze:</strong> ' . htmlspecialchars($data['health_intolerances']) . '</p>';
        if (!empty($data['health_conditions'])) $html .= '<p><strong>Patologie:</strong> ' . htmlspecialchars($data['health_conditions']) . '</p>';
        
        // Datore di lavoro
        if (!empty($data['employer_name'])) {
            $html .= '<h3 style="background: #f0f0f0; padding: 5px; margin-top: 10px;">DATORE DI LAVORO</h3>';
            $html .= '<p><strong>' . htmlspecialchars($data['employer_name']) . '</strong><br>';
            if (!empty($data['employer_address'])) $html .= htmlspecialchars($data['employer_address']) . '<br>';
            if (!empty($data['employer_city'])) $html .= htmlspecialchars($data['employer_city']) . '<br>';
            if (!empty($data['employer_phone'])) $html .= 'Tel: ' . htmlspecialchars($data['employer_phone']) . '<br>';
            $html .= '</p>';
        }
        
        // Dichiarazioni
        $html .= '<h3 style="background: #f0f0f0; padding: 5px; margin-top: 10px;">DICHIARAZIONI</h3>';
        $html .= '<p style="font-size: 10px;"><strong>Il sottoscritto dichiara:</strong></p>';
        $html .= '<p style="font-size: 9px;">';
        $html .= '☑ Art. 6 - Di essere disponibile a svolgere compiti operativi nell\'ambito di interventi di Protezione Civile<br>';
        $html .= '☑ Art. 6 - Di dichiarare la propria operatività a favore di una sola organizzazione di volontariato di Protezione Civile<br>';
        $html .= '☑ Art. 7 - Di non avere riportato condanne penali per reati dolosi contro le persone o contro il patrimonio<br>';
        $html .= '☑ D.Lgs. 117/2017 - Di essere informato che l\'attività di volontariato è svolta in modo personale, spontaneo e gratuito<br>';
        $html .= '☑ D.Lgs. 81/2008 - Di essere a conoscenza dell\'obbligo di indossare i DPI<br>';
        $html .= '☑ Di essere disponibile ad esibire certificazione medica<br>';
        $html .= '☑ Di impegnarsi a rispettare lo Statuto Associativo ed il Regolamento Interno<br>';
        $html .= '☑ Di essere a conoscenza dei pericoli e rischi specifici nelle attività di Protezione Civile<br>';
        $html .= '☑ Dichiarazione sostitutiva di certificazione (Artt. 46-76 D.P.R. n. 445/2000)<br>';
        $html .= '☑ Autorizzazione trattamento dati personali (GDPR 2016/679)<br>';
        $html .= '☑ Autorizzazione pubblicazione foto e video<br>';
        $html .= '</p>';
        
        // Allegati
        $html .= '<h3 style="background: #f0f0f0; padding: 5px; margin-top: 10px;">ALLEGATI DA CONSEGNARE</h3>';
        $html .= '<p style="font-size: 10px;">';
        $html .= '• Copie di Attestati e Specializzazioni personali in campi inerenti alla Protezione Civile<br>';
        $html .= '• Copie Patenti di Guida per conduzione di mezzi speciali, Brevetti o Patentini per natanti o velivoli<br>';
        $html .= '</p>';
        
        // Firma
        $html .= '<div style="margin-top: 30px;">';
        $html .= '<p><strong>Luogo e data:</strong> ' . htmlspecialchars($data['compilation_place']) . ', ' . date('d/m/Y', strtotime($data['compilation_date'])) . '</p>';
        $html .= '<p style="margin-top: 30px;">Firma del richiedente: _______________________________</p>';
        $html .= '</div>';
        
        $html .= $pdfGen->getFooterHtml();
        
        $filename = 'domanda_adulto_' . $code . '.pdf';
        $path = __DIR__ . '/../../uploads/applications/' . $filename;
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        $pdfGen->generate($html, $path, 'F');
        
        return 'uploads/applications/' . $filename;
    }
    
    /**
     * Genera PDF domanda minorenne
     * 
     * @param int $id ID domanda
     * @param array $data Dati domanda
     * @param string $code Codice domanda
     * @return string Path PDF
     */
    private function generateJuniorApplicationPdf($id, $data, $code) {
        $pdfGen = new PdfGenerator($this->config);
        
        $html = $pdfGen->getHeaderHtml();
        $html .= '<h2 style="text-align: center; color: #0d6efd;">DOMANDA DI ISCRIZIONE<br>SOCIO MINORENNE (CADETTO)</h2>';
        $html .= '<p style="text-align: center;"><strong>Codice domanda:</strong> ' . htmlspecialchars($code) . '</p>';
        $html .= '<hr>';
        
        // Dati anagrafici minore
        $html .= '<h3 style="background: #f0f0f0; padding: 5px;">DATI ANAGRAFICI DEL MINORE</h3>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr><td style="width: 30%; padding: 5px;"><strong>Cognome:</strong></td><td>' . htmlspecialchars($data['last_name']) . '</td></tr>';
        $html .= '<tr><td style="padding: 5px;"><strong>Nome:</strong></td><td>' . htmlspecialchars($data['first_name']) . '</td></tr>';
        $html .= '<tr><td style="padding: 5px;"><strong>Codice Fiscale:</strong></td><td>' . htmlspecialchars($data['tax_code']) . '</td></tr>';
        $html .= '<tr><td style="padding: 5px;"><strong>Data di Nascita:</strong></td><td>' . date('d/m/Y', strtotime($data['birth_date'])) . '</td></tr>';
        $html .= '<tr><td style="padding: 5px;"><strong>Luogo di Nascita:</strong></td><td>' . htmlspecialchars($data['birth_place']) . ' (' . htmlspecialchars($data['birth_province']) . ')</td></tr>';
        $html .= '<tr><td style="padding: 5px;"><strong>Sesso:</strong></td><td>' . htmlspecialchars($data['gender']) . '</td></tr>';
        $html .= '</table>';
        
        // Residenza
        $html .= '<h3 style="background: #f0f0f0; padding: 5px; margin-top: 10px;">RESIDENZA</h3>';
        $html .= '<p>' . htmlspecialchars($data['residence_street']) . ' ' . htmlspecialchars($data['residence_number']) . ', ';
        $html .= htmlspecialchars($data['residence_city']) . ' (' . htmlspecialchars($data['residence_province']) . ') - ' . htmlspecialchars($data['residence_cap']) . '</p>';
        
        // Recapiti
        $html .= '<h3 style="background: #f0f0f0; padding: 5px; margin-top: 10px;">RECAPITI</h3>';
        $html .= '<p>';
        if (!empty($data['mobile'])) $html .= '<strong>Cellulare:</strong> ' . htmlspecialchars($data['mobile']) . '<br>';
        if (!empty($data['phone'])) $html .= '<strong>Telefono:</strong> ' . htmlspecialchars($data['phone']) . '<br>';
        if (!empty($data['email'])) $html .= '<strong>Email:</strong> ' . htmlspecialchars($data['email']) . '<br>';
        $html .= '</p>';
        
        // Salute
        if (!empty($data['health_allergies']) || !empty($data['health_intolerances']) || !empty($data['health_conditions'])) {
            $html .= '<h3 style="background: #f0f0f0; padding: 5px; margin-top: 10px;">INFORMAZIONI SANITARIE</h3>';
            if ($data['health_vegetarian']) $html .= '<p>Vegetariano</p>';
            if ($data['health_vegan']) $html .= '<p>Vegano</p>';
            if (!empty($data['health_allergies'])) $html .= '<p><strong>Allergie:</strong> ' . htmlspecialchars($data['health_allergies']) . '</p>';
            if (!empty($data['health_intolerances'])) $html .= '<p><strong>Intolleranze:</strong> ' . htmlspecialchars($data['health_intolerances']) . '</p>';
            if (!empty($data['health_conditions'])) $html .= '<p><strong>Patologie:</strong> ' . htmlspecialchars($data['health_conditions']) . '</p>';
        }
        
        // Genitori/Tutori
        if (!empty($data['guardians'])) {
            foreach ($data['guardians'] as $guardian) {
                $guardianType = strtoupper($guardian['type']);
                $html .= '<h3 style="background: #f0f0f0; padding: 5px; margin-top: 10px;">DATI ' . $guardianType . '</h3>';
                $html .= '<table style="width: 100%; border-collapse: collapse;">';
                $html .= '<tr><td style="width: 30%; padding: 5px;"><strong>Cognome:</strong></td><td>' . htmlspecialchars($guardian['last_name']) . '</td></tr>';
                $html .= '<tr><td style="padding: 5px;"><strong>Nome:</strong></td><td>' . htmlspecialchars($guardian['first_name']) . '</td></tr>';
                if (!empty($guardian['tax_code'])) $html .= '<tr><td style="padding: 5px;"><strong>Codice Fiscale:</strong></td><td>' . htmlspecialchars($guardian['tax_code']) . '</td></tr>';
                if (!empty($guardian['birth_date'])) $html .= '<tr><td style="padding: 5px;"><strong>Data di Nascita:</strong></td><td>' . date('d/m/Y', strtotime($guardian['birth_date'])) . '</td></tr>';
                if (!empty($guardian['birth_place'])) $html .= '<tr><td style="padding: 5px;"><strong>Luogo di Nascita:</strong></td><td>' . htmlspecialchars($guardian['birth_place']) . '</td></tr>';
                if (!empty($guardian['phone'])) $html .= '<tr><td style="padding: 5px;"><strong>Telefono:</strong></td><td>' . htmlspecialchars($guardian['phone']) . '</td></tr>';
                if (!empty($guardian['email'])) $html .= '<tr><td style="padding: 5px;"><strong>Email:</strong></td><td>' . htmlspecialchars($guardian['email']) . '</td></tr>';
                $html .= '</table>';
            }
        }
        
        // Dichiarazioni
        $html .= '<h3 style="background: #f0f0f0; padding: 5px; margin-top: 10px;">DICHIARAZIONI</h3>';
        $html .= '<p style="font-size: 10px;"><strong>I sottoscritti dichiarano:</strong></p>';
        $html .= '<p style="font-size: 9px;">';
        $html .= '☑ D.Lgs. 117/2017 - Di essere informati che l\'attività di volontariato è svolta in modo personale, spontaneo e gratuito<br>';
        $html .= '☑ D.Lgs. 81/2008 - Di essere disponibili ad esibire certificazione medica<br>';
        $html .= '☑ Di impegnarsi a rispettare lo Statuto Associativo ed il Regolamento Interno<br>';
        $html .= '☑ Di essere a conoscenza dei rischi che le attività associative possono comportare<br>';
        $html .= '☑ Esenzione di responsabilità per l\'Associazione, il Presidente, il Consiglio Direttivo e gli Istruttori<br>';
        $html .= '☑ Dichiarazione sostitutiva di certificazione (Artt. 46-76 D.P.R. n. 445/2000)<br>';
        $html .= '☑ Autorizzazione trattamento dati personali (GDPR 2016/679)<br>';
        $html .= '☑ Autorizzazione pubblicazione foto e video<br>';
        $html .= '</p>';
        
        // Firme
        $html .= '<div style="margin-top: 30px;">';
        $html .= '<p><strong>Luogo e data:</strong> ' . htmlspecialchars($data['compilation_place']) . ', ' . date('d/m/Y', strtotime($data['compilation_date'])) . '</p>';
        $html .= '<p style="margin-top: 30px;">Firma del socio minorenne: _______________________________</p>';
        $html .= '<p style="margin-top: 20px;">Firma del padre: _______________________________</p>';
        $html .= '<p style="margin-top: 20px;">Firma della madre: _______________________________</p>';
        $html .= '</div>';
        
        $html .= $pdfGen->getFooterHtml();
        
        $filename = 'domanda_cadetto_' . $code . '.pdf';
        $path = __DIR__ . '/../../uploads/applications/' . $filename;
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        $pdfGen->generate($html, $path, 'F');
        
        return 'uploads/applications/' . $filename;
    }
    
    /**
     * Invia email domanda adulto
     * 
     * @param array $data Dati
     * @param string $code Codice
     * @param string $pdfPath Path PDF
     */
    private function sendAdultApplicationEmails($data, $code, $pdfPath) {
        if (!($this->config['email']['enabled'] ?? false)) {
            return;
        }
        
        $emailSender = new EmailSender($this->config, $this->db);
        
        // Email al richiedente
        $subject = 'Domanda di iscrizione ricevuta - Codice ' . $code;
        $body = '<p>Gentile ' . htmlspecialchars($data['first_name']) . ' ' . htmlspecialchars($data['last_name']) . ',</p>';
        $body .= '<p>La tua domanda di iscrizione è stata ricevuta correttamente.</p>';
        $body .= '<p><strong>Codice domanda:</strong> ' . htmlspecialchars($code) . '</p>';
        $body .= '<p>In allegato troverai il PDF della domanda che <strong>deve essere stampato, firmato e consegnato in originale</strong> presso la sede dell\'associazione, insieme a:</p>';
        $body .= '<ul>';
        $body .= '<li>Copie di Attestati e Specializzazioni personali in campi inerenti alla Protezione Civile</li>';
        $body .= '<li>Copie Patenti di Guida per conduzione di mezzi speciali, Brevetti o Patentini per natanti o velivoli</li>';
        $body .= '</ul>';
        $body .= '<p>Ti contatteremo presto per aggiornamenti.</p>';
        
        $fullPdfPath = __DIR__ . '/../../' . $pdfPath;
        $emailSender->queue($data['email'], $subject, $body, [$fullPdfPath]);
        
        // Email all'associazione
        if (!empty($this->config['association']['email'])) {
            $subject = 'Nuova domanda di iscrizione socio maggiorenne - ' . $code;
            $body = '<p>È stata ricevuta una nuova domanda di iscrizione.</p>';
            $body .= '<p><strong>Codice:</strong> ' . htmlspecialchars($code) . '</p>';
            $body .= '<p><strong>Nome:</strong> ' . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']) . '</p>';
            $body .= '<p><strong>Email:</strong> ' . htmlspecialchars($data['email']) . '</p>';
            $body .= '<p>In allegato il PDF della domanda.</p>';
            
            $emailSender->queue($this->config['association']['email'], $subject, $body, [$fullPdfPath]);
        }
    }
    
    /**
     * Invia email domanda minorenne
     * 
     * @param array $data Dati
     * @param string $code Codice
     * @param string $pdfPath Path PDF
     */
    private function sendJuniorApplicationEmails($data, $code, $pdfPath) {
        if (!($this->config['email']['enabled'] ?? false)) {
            return;
        }
        
        $emailSender = new EmailSender($this->config, $this->db);
        
        // Trova email destinatario (genitore o minore)
        $recipientEmail = $data['email'];
        if (empty($recipientEmail) && !empty($data['guardians'])) {
            foreach ($data['guardians'] as $guardian) {
                if (!empty($guardian['email'])) {
                    $recipientEmail = $guardian['email'];
                    break;
                }
            }
        }
        
        if (empty($recipientEmail)) {
            error_log("Nessuna email trovata per domanda cadetto $code");
            return;
        }
        
        // Email al richiedente
        $subject = 'Domanda di iscrizione cadetto ricevuta - Codice ' . $code;
        $body = '<p>Gentile famiglia ' . htmlspecialchars($data['last_name']) . ',</p>';
        $body .= '<p>La domanda di iscrizione per il cadetto ' . htmlspecialchars($data['first_name']) . ' ' . htmlspecialchars($data['last_name']) . ' è stata ricevuta correttamente.</p>';
        $body .= '<p><strong>Codice domanda:</strong> ' . htmlspecialchars($code) . '</p>';
        $body .= '<p>In allegato troverai il PDF della domanda che <strong>deve essere stampato, firmato dal minore e dai genitori (o tutore), e consegnato in originale</strong> presso la sede dell\'associazione.</p>';
        $body .= '<p>Vi contatteremo presto per aggiornamenti.</p>';
        
        $fullPdfPath = __DIR__ . '/../../' . $pdfPath;
        $emailSender->queue($recipientEmail, $subject, $body, [$fullPdfPath]);
        
        // Email all'associazione
        if (!empty($this->config['association']['email'])) {
            $subject = 'Nuova domanda di iscrizione cadetto - ' . $code;
            $body = '<p>È stata ricevuta una nuova domanda di iscrizione per un socio minorenne.</p>';
            $body .= '<p><strong>Codice:</strong> ' . htmlspecialchars($code) . '</p>';
            $body .= '<p><strong>Nome:</strong> ' . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']) . '</p>';
            $body .= '<p><strong>Email:</strong> ' . htmlspecialchars($recipientEmail) . '</p>';
            $body .= '<p>In allegato il PDF della domanda.</p>';
            
            $emailSender->queue($this->config['association']['email'], $subject, $body, [$fullPdfPath]);
        }
    }
    
    /**
     * Genera PDF domanda
     * 
     * @param int $id ID domanda
     * @param array $data Dati domanda
     * @param bool $isJunior Se minorenne
     * @return string Path PDF
     */
    private function generateApplicationPdf($id, $data, $isJunior) {
        $pdfGen = new PdfGenerator($this->config);
        
        // TODO: Create proper PDF template
        $html = $pdfGen->getHeaderHtml();
        $html .= '<h2 style="text-align: center;">Domanda di Iscrizione</h2>';
        $html .= '<p><strong>Codice:</strong> ' . htmlspecialchars($data['application_code'] ?? '') . '</p>';
        $html .= '<p><strong>Cognome:</strong> ' . htmlspecialchars($data['last_name']) . '</p>';
        $html .= '<p><strong>Nome:</strong> ' . htmlspecialchars($data['first_name']) . '</p>';
        $html .= $pdfGen->getFooterHtml();
        
        $filename = 'domanda_' . $id . '.pdf';
        $path = __DIR__ . '/../../uploads/applications/' . $filename;
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        $pdfGen->generate($html, $filename, 'F');
        
        return $path;
    }
    
    /**
     * Invia email domanda
     * 
     * @param int $id ID domanda
     * @param array $data Dati
     * @param string $pdfPath Path PDF
     */
    private function sendApplicationEmails($id, $data, $pdfPath) {
        if (!($this->config['email']['enabled'] ?? false)) {
            return;
        }
        
        $emailSender = new EmailSender($this->config, $this->db);
        
        // Email al richiedente
        $subject = 'Domanda di iscrizione ricevuta';
        $body = '<p>Gentile ' . htmlspecialchars($data['first_name']) . ',</p>';
        $body .= '<p>La tua domanda di iscrizione è stata ricevuta correttamente.</p>';
        $body .= '<p>Ti contatteremo presto per gli aggiornamenti.</p>';
        
        $emailSender->queue($data['email'], $subject, $body, [$pdfPath]);
        
        // Email all'associazione
        if (!empty($this->config['association']['email'])) {
            $subject = 'Nuova domanda di iscrizione';
            $body = '<p>È stata ricevuta una nuova domanda di iscrizione.</p>';
            $body .= '<p><strong>Nome:</strong> ' . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']) . '</p>';
            
            $emailSender->queue($this->config['association']['email'], $subject, $body, [$pdfPath]);
        }
    }
    
    /**
     * Invia email approvazione
     * 
     * @param array $application Dati domanda
     */
    private function sendApprovalEmail($application) {
        if (!($this->config['email']['enabled'] ?? false)) {
            return;
        }
        
        $emailSender = new EmailSender($this->config, $this->db);
        
        $subject = 'Domanda di iscrizione approvata';
        $body = '<p>Gentile ' . htmlspecialchars($application['first_name']) . ',</p>';
        $body .= '<p>La tua domanda di iscrizione è stata approvata!</p>';
        $body .= '<p>Benvenuto nella nostra associazione.</p>';
        
        $emailSender->queue($application['email'], $subject, $body);
    }
    
    /**
     * Invia email rifiuto
     * 
     * @param array $application Dati domanda
     * @param string $reason Motivazione
     */
    private function sendRejectionEmail($application, $reason) {
        if (!($this->config['email']['enabled'] ?? false)) {
            return;
        }
        
        $emailSender = new EmailSender($this->config, $this->db);
        
        $subject = 'Domanda di iscrizione';
        $body = '<p>Gentile ' . htmlspecialchars($application['first_name']) . ',</p>';
        $body .= '<p>Ci dispiace informarti che la tua domanda di iscrizione non può essere accettata.</p>';
        if ($reason) {
            $body .= '<p><strong>Motivazione:</strong> ' . htmlspecialchars($reason) . '</p>';
        }
        
        $emailSender->queue($application['email'], $subject, $body);
    }
    
    /**
     * Crea socio maggiorenne da domanda approvata
     * 
     * @param array $data Dati completi dalla domanda
     * @param int $userId ID utente
     * @return int ID socio creato
     */
    private function createMemberFromApplication($data, $userId) {
        // Genera numero registrazione
        $regNumber = $this->generateRegistrationNumber('member');
        
        // Inserisci socio
        $sql = "INSERT INTO members (
            registration_number, member_type, member_status, volunteer_status,
            last_name, first_name, birth_date, birth_place, tax_code,
            registration_date, approval_date,
            created_at
        ) VALUES (?, 'ordinario', 'attivo', 'in_formazione', ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $params = [
            $regNumber,
            $data['last_name'],
            $data['first_name'],
            $data['birth_date'],
            $data['birth_place'], // Required field from form
            $data['tax_code'],
            date('Y-m-d'),
            date('Y-m-d')
        ];
        
        $this->db->execute($sql, $params);
        $memberId = $this->db->lastInsertId();
        
        // Inserisci indirizzo residenza
        if (!empty($data['residence_street'])) {
            $sql = "INSERT INTO member_addresses (member_id, address_type, street, number, city, province, cap) 
                    VALUES (?, 'residenza', ?, ?, ?, ?, ?)";
            $this->db->execute($sql, [
                $memberId,
                $data['residence_street'],
                $data['residence_number'] ?? '',
                $data['residence_city'],
                $data['residence_province'],
                $data['residence_cap']
            ]);
        }
        
        // Inserisci indirizzo domicilio se diverso
        if (!empty($data['domicile_street'])) {
            $sql = "INSERT INTO member_addresses (member_id, address_type, street, number, city, province, cap) 
                    VALUES (?, 'domicilio', ?, ?, ?, ?, ?)";
            $this->db->execute($sql, [
                $memberId,
                $data['domicile_street'],
                $data['domicile_number'] ?? '',
                $data['domicile_city'],
                $data['domicile_province'],
                $data['domicile_cap']
            ]);
        }
        
        // Inserisci recapiti
        if (!empty($data['phone'])) {
            $this->db->execute("INSERT INTO member_contacts (member_id, contact_type, value) VALUES (?, 'telefono_fisso', ?)", [$memberId, $data['phone']]);
        }
        if (!empty($data['mobile'])) {
            $this->db->execute("INSERT INTO member_contacts (member_id, contact_type, value) VALUES (?, 'cellulare', ?)", [$memberId, $data['mobile']]);
        }
        if (!empty($data['email'])) {
            $this->db->execute("INSERT INTO member_contacts (member_id, contact_type, value) VALUES (?, 'email', ?)", [$memberId, $data['email']]);
        }
        if (!empty($data['pec'])) {
            $this->db->execute("INSERT INTO member_contacts (member_id, contact_type, value) VALUES (?, 'pec', ?)", [$memberId, $data['pec']]);
        }
        
        // Inserisci patenti
        if (!empty($data['licenses'])) {
            foreach ($data['licenses'] as $license) {
                if (!empty($license['number']) || !empty($license['description'])) {
                    $licenseType = $license['type'];
                    if (!empty($license['description'])) {
                        $licenseType .= ' - ' . $license['description'];
                    }
                    $sql = "INSERT INTO member_licenses (member_id, license_type, license_number, issue_date, expiry_date) 
                            VALUES (?, ?, ?, ?, ?)";
                    $this->db->execute($sql, [
                        $memberId,
                        $licenseType,
                        $license['number'] ?? null,
                        !empty($license['issue_date']) ? $license['issue_date'] : null,
                        !empty($license['expiry_date']) ? $license['expiry_date'] : null
                    ]);
                }
            }
        }
        
        // Inserisci corsi
        if (!empty($data['courses'])) {
            foreach ($data['courses'] as $course) {
                if (!empty($course['name'])) {
                    $sql = "INSERT INTO member_courses (member_id, course_name, completion_date, expiry_date) 
                            VALUES (?, ?, ?, ?)";
                    $this->db->execute($sql, [
                        $memberId,
                        $course['name'],
                        !empty($course['completion_date']) ? $course['completion_date'] : null,
                        !empty($course['expiry_date']) ? $course['expiry_date'] : null
                    ]);
                }
            }
        }
        
        // Inserisci informazioni salute
        if (!empty($data['health_vegetarian'])) {
            $this->db->execute("INSERT INTO member_health (member_id, health_type, description) VALUES (?, 'vegetariano', '')", [$memberId]);
        }
        if (!empty($data['health_vegan'])) {
            $this->db->execute("INSERT INTO member_health (member_id, health_type, description) VALUES (?, 'vegano', '')", [$memberId]);
        }
        if (!empty($data['health_allergies'])) {
            $this->db->execute("INSERT INTO member_health (member_id, health_type, description) VALUES (?, 'allergie', ?)", [$memberId, $data['health_allergies']]);
        }
        if (!empty($data['health_intolerances'])) {
            $this->db->execute("INSERT INTO member_health (member_id, health_type, description) VALUES (?, 'intolleranze', ?)", [$memberId, $data['health_intolerances']]);
        }
        if (!empty($data['health_conditions'])) {
            $this->db->execute("INSERT INTO member_health (member_id, health_type, description) VALUES (?, 'patologie', ?)", [$memberId, $data['health_conditions']]);
        }
        
        // Inserisci datore di lavoro
        if (!empty($data['employer_name'])) {
            $sql = "INSERT INTO member_employment (member_id, employer_name, employer_address, employer_city, employer_phone) 
                    VALUES (?, ?, ?, ?, ?)";
            $this->db->execute($sql, [
                $memberId,
                $data['employer_name'],
                $data['employer_address'] ?? null,
                $data['employer_city'] ?? null,
                $data['employer_phone'] ?? null
            ]);
        }
        
        return $memberId;
    }
    
    /**
     * Crea cadetto da domanda approvata
     * 
     * @param array $data Dati completi dalla domanda
     * @param int $userId ID utente
     * @return int ID cadetto creato
     */
    private function createJuniorMemberFromApplication($data, $userId) {
        // Genera numero registrazione
        $regNumber = $this->generateRegistrationNumber('junior');
        
        // Inserisci cadetto
        $sql = "INSERT INTO junior_members (
            registration_number, member_status,
            last_name, first_name, birth_date, birth_place, tax_code,
            registration_date, approval_date,
            created_at
        ) VALUES (?, 'attivo', ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $params = [
            $regNumber,
            $data['last_name'],
            $data['first_name'],
            $data['birth_date'],
            $data['birth_place'], // Required field from form
            $data['tax_code'],
            date('Y-m-d'),
            date('Y-m-d')
        ];
        
        $this->db->execute($sql, $params);
        $juniorMemberId = $this->db->lastInsertId();
        
        // Inserisci indirizzo residenza
        if (!empty($data['residence_street'])) {
            $sql = "INSERT INTO junior_member_addresses (junior_member_id, address_type, street, number, city, province, cap) 
                    VALUES (?, 'residenza', ?, ?, ?, ?, ?)";
            $this->db->execute($sql, [
                $juniorMemberId,
                $data['residence_street'],
                $data['residence_number'] ?? '',
                $data['residence_city'],
                $data['residence_province'],
                $data['residence_cap']
            ]);
        }
        
        // Inserisci indirizzo domicilio se diverso
        if (!empty($data['domicile_street'])) {
            $sql = "INSERT INTO junior_member_addresses (junior_member_id, address_type, street, number, city, province, cap) 
                    VALUES (?, 'domicilio', ?, ?, ?, ?, ?)";
            $this->db->execute($sql, [
                $juniorMemberId,
                $data['domicile_street'],
                $data['domicile_number'] ?? '',
                $data['domicile_city'],
                $data['domicile_province'],
                $data['domicile_cap']
            ]);
        }
        
        // Inserisci recapiti
        if (!empty($data['phone'])) {
            $this->db->execute("INSERT INTO junior_member_contacts (junior_member_id, contact_type, value) VALUES (?, 'telefono_fisso', ?)", [$juniorMemberId, $data['phone']]);
        }
        if (!empty($data['mobile'])) {
            $this->db->execute("INSERT INTO junior_member_contacts (junior_member_id, contact_type, value) VALUES (?, 'cellulare', ?)", [$juniorMemberId, $data['mobile']]);
        }
        if (!empty($data['email'])) {
            $this->db->execute("INSERT INTO junior_member_contacts (junior_member_id, contact_type, value) VALUES (?, 'email', ?)", [$juniorMemberId, $data['email']]);
        }
        
        // Inserisci informazioni salute
        if (!empty($data['health_vegetarian'])) {
            $this->db->execute("INSERT INTO junior_member_health (junior_member_id, health_type, description) VALUES (?, 'vegetariano', '')", [$juniorMemberId]);
        }
        if (!empty($data['health_vegan'])) {
            $this->db->execute("INSERT INTO junior_member_health (junior_member_id, health_type, description) VALUES (?, 'vegano', '')", [$juniorMemberId]);
        }
        if (!empty($data['health_allergies'])) {
            $this->db->execute("INSERT INTO junior_member_health (junior_member_id, health_type, description) VALUES (?, 'allergie', ?)", [$juniorMemberId, $data['health_allergies']]);
        }
        if (!empty($data['health_intolerances'])) {
            $this->db->execute("INSERT INTO junior_member_health (junior_member_id, health_type, description) VALUES (?, 'intolleranze', ?)", [$juniorMemberId, $data['health_intolerances']]);
        }
        if (!empty($data['health_conditions'])) {
            $this->db->execute("INSERT INTO junior_member_health (junior_member_id, health_type, description) VALUES (?, 'patologie', ?)", [$juniorMemberId, $data['health_conditions']]);
        }
        
        // Inserisci tutori/genitori
        if (!empty($data['guardians'])) {
            foreach ($data['guardians'] as $guardian) {
                $sql = "INSERT INTO junior_member_guardians (
                    junior_member_id, guardian_type,
                    last_name, first_name, tax_code, phone, email
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $this->db->execute($sql, [
                    $juniorMemberId,
                    $guardian['type'],
                    $guardian['last_name'],
                    $guardian['first_name'],
                    $guardian['tax_code'] ?? null,
                    $guardian['phone'] ?? null,
                    $guardian['email'] ?? null
                ]);
            }
        }
        
        return $juniorMemberId;
    }
    
    /**
     * Invia email approvazione da dati
     * 
     * @param array $data Dati domanda
     * @param string $type Tipo applicazione
     */
    private function sendApprovalEmailFromData($data, $type) {
        if (!($this->config['email']['enabled'] ?? false)) {
            return;
        }
        
        $emailSender = new EmailSender($this->config, $this->db);
        
        $email = $data['email'] ?? '';
        if ($type === 'junior' && empty($email) && !empty($data['guardians'])) {
            foreach ($data['guardians'] as $guardian) {
                if (!empty($guardian['email'])) {
                    $email = $guardian['email'];
                    break;
                }
            }
        }
        
        if (empty($email)) {
            return;
        }
        
        $subject = 'Domanda di iscrizione approvata';
        $body = '<p>Gentile ' . htmlspecialchars($data['first_name']) . ' ' . htmlspecialchars($data['last_name']) . ',</p>';
        $body .= '<p>La domanda di iscrizione è stata approvata!</p>';
        $body .= '<p>Benvenuto/a nella nostra associazione.</p>';
        $body .= '<p>Riceverai a breve ulteriori comunicazioni.</p>';
        
        $emailSender->queue($email, $subject, $body);
    }
    
    /**
     * Crea socio da domanda approvata (DEPRECATO - mantenuto per compatibilità)
     * 
     * @param array $application Dati domanda
     * @param int $userId ID utente
     * @return int ID socio creato
     */
    private function createMember($application, $userId) {
        $memberController = new MemberController($this->db, $this->config);
        
        $data = [
            'last_name' => $application['last_name'],
            'first_name' => $application['first_name'],
            'birth_date' => $application['birth_date'],
            'birth_place' => $application['birth_place'],
            'birth_province' => $application['birth_province'],
            'tax_code' => $application['tax_code'],
            'gender' => $application['gender'],
            'nationality' => $application['nationality'],
            'member_type' => 'ordinario',
            'member_status' => 'attivo',
            'volunteer_status' => 'aspirante',
            'registration_date' => date('Y-m-d')
        ];
        
        return $memberController->create($data, $userId);
    }
    
    /**
     * Crea cadetto da domanda approvata
     * 
     * @param array $application Dati domanda
     * @param int $userId ID utente
     * @return int ID cadetto creato
     */
    private function createJuniorMember($application, $userId) {
        // Insert into junior_members table
        $sql = "INSERT INTO junior_members (
            registration_number, member_status,
            last_name, first_name, birth_date, birth_place, birth_province,
            tax_code, gender, nationality,
            registration_date,
            created_at, created_by
        ) VALUES (?, 'attivo', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        
        // Generate registration number
        $regNumber = $this->generateRegistrationNumber('junior');
        
        $params = [
            $regNumber,
            $application['last_name'],
            $application['first_name'],
            $application['birth_date'],
            $application['birth_place'],
            $application['birth_province'],
            $application['tax_code'],
            $application['gender'],
            $application['nationality'],
            date('Y-m-d'),
            $userId
        ];
        
        $this->db->execute($sql, $params);
        $juniorMemberId = $this->db->lastInsertId();
        
        // Add guardian data if exists
        $guardian = $this->db->fetchOne(
            "SELECT * FROM member_application_guardians WHERE application_id = ?",
            [$application['id']]
        );
        
        if ($guardian) {
            $sql = "INSERT INTO junior_member_guardians (
                junior_member_id, guardian_type,
                last_name, first_name, birth_date, birth_place,
                tax_code, phone, email
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $juniorMemberId,
                $guardian['guardian_type'] ?? 'parent',
                $guardian['last_name'],
                $guardian['first_name'],
                $guardian['birth_date'],
                $guardian['birth_place'],
                $guardian['tax_code'],
                $guardian['phone'],
                $guardian['email']
            ];
            
            $this->db->execute($sql, $params);
        }
        
        return $juniorMemberId;
    }
    
    /**
     * Genera numero registrazione per soci o cadetti
     * 
     * @param string $type 'member' o 'junior'
     * @return string
     */
    private function generateRegistrationNumber($type = 'member') {
        $table = $type === 'junior' ? 'junior_members' : 'members';
        
        $sql = "SELECT registration_number FROM $table 
                WHERE registration_number REGEXP '^[0-9]+$' 
                ORDER BY CAST(registration_number AS UNSIGNED) DESC 
                LIMIT 1";
        
        $last = $this->db->fetchOne($sql);
        
        if ($last && is_numeric($last['registration_number'])) {
            $nextNumber = intval($last['registration_number']) + 1;
        } else {
            $nextNumber = 1;
        }
        
        return str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Elimina domanda di iscrizione
     */
    public function delete($id, $userId) {
        try {
            // Get application details for log
            $application = $this->get($id);
            if (!$application) {
                return ['success' => false, 'message' => 'Domanda non trovata'];
            }
            
            // Prevent deletion of approved applications
            if ($application['status'] === 'approvata') {
                return ['success' => false, 'message' => 'Impossibile eliminare: domanda già approvata'];
            }
            
            // Delete application
            $sql = "DELETE FROM member_applications WHERE id = ?";
            $this->db->execute($sql, [$id]);
            
            // Log activity
            $this->logActivity(
                $userId, 
                'applications', 
                'delete', 
                $id, 
                "Eliminata domanda: {$application['first_name']} {$application['last_name']}"
            );
            
            return ['success' => true];
        } catch (\Exception $e) {
            error_log("Errore eliminazione domanda: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione'];
        }
    }
    
    /**
     * Registra attività nel log
     */
    private function logActivity($userId, $module, $action, $recordId, $details) {
        try {
            $sql = "INSERT INTO activity_logs 
                    (user_id, module, action, record_id, description, ip_address, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $userId,
                $module,
                $action,
                $recordId,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ];
            
            $this->db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Errore log attività: " . $e->getMessage());
        }
    }
}
