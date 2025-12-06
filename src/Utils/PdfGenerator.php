<?php
namespace EasyVol\Utils;

use Mpdf\Mpdf;
use Mpdf\MpdfException;

/**
 * PDF Generator Utility
 * 
 * Gestisce la generazione di documenti PDF per EasyVol
 * Supporta template HTML e configurazione personalizzata
 */
class PdfGenerator {
    private $config;
    private $mpdf;
    private $associationData;
    
    /**
     * Constructor
     * 
     * @param array $config Configurazione applicazione
     */
    public function __construct($config) {
        $this->config = $config;
        $this->associationData = $config['association'] ?? [];
    }
    
    /**
     * Inizializza mPDF con configurazione personalizzata
     * 
     * @param array $customConfig Configurazione personalizzata
     * @throws MpdfException
     */
    private function initMpdf($customConfig = []) {
        $defaultConfig = [
            'tempDir' => sys_get_temp_dir(),
            'default_font' => 'dejavusans',
            'default_font_size' => 10,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_left' => 15,
            'margin_right' => 15,
            'format' => 'A4',
            'orientation' => 'P'
        ];
        
        // Merge custom config with defaults
        $config = array_merge($defaultConfig, $customConfig);
        
        $this->mpdf = new Mpdf($config);
    }
    
    /**
     * Genera PDF da HTML
     * 
     * @param string $html Contenuto HTML
     * @param string $filename Nome file output
     * @param string $output Tipo output (D=download, I=inline, F=file, S=string)
     * @param array $config Configurazione personalizzata mPDF
     * @return mixed
     * @throws MpdfException
     */
    public function generate($html, $filename, $output = 'D', $config = []) {
        $this->initMpdf($config);
        
        $this->mpdf->WriteHTML($html);
        
        return $this->mpdf->Output($filename, $output);
    }
    
    /**
     * Genera PDF da template con sostituzione variabili
     * 
     * @param string $templatePath Percorso template HTML
     * @param array $data Dati da sostituire nel template
     * @param string $filename Nome file output
     * @param string $output Tipo output
     * @param array $config Configurazione personalizzata
     * @return mixed
     * @throws MpdfException
     */
    public function generateFromTemplate($templatePath, $data, $filename, $output = 'D', $config = []) {
        if (!file_exists($templatePath)) {
            throw new \Exception("Template non trovato: $templatePath");
        }
        
        $html = file_get_contents($templatePath);
        $html = $this->replaceVariables($html, $data);
        
        return $this->generate($html, $filename, $output, $config);
    }
    
    /**
     * Sostituisce variabili nel template
     * 
     * @param string $template Template HTML
     * @param array $data Dati da sostituire
     * @return string
     */
    private function replaceVariables($template, $data) {
        // Add association data to available variables
        $allData = array_merge($this->associationData, $data);
        
        foreach ($allData as $key => $value) {
            if (is_scalar($value)) {
                $template = str_replace("{{" . $key . "}}", $value, $template);
            }
        }
        
        return $template;
    }
    
    /**
     * Genera intestazione PDF standard
     * 
     * @return string HTML intestazione
     */
    public function getHeaderHtml() {
        $logo = $this->associationData['logo_path'] ?? '';
        $name = $this->associationData['name'] ?? '';
        $address = $this->associationData['address'] ?? '';
        $city = $this->associationData['city'] ?? '';
        $phone = $this->associationData['phone'] ?? '';
        $email = $this->associationData['email'] ?? '';
        
        $logoHtml = '';
        if ($logo && file_exists($logo)) {
            $logoHtml = '<img src="' . $logo . '" style="height: 50px;" />';
        }
        
        return '
        <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px;">
            ' . $logoHtml . '
            <h2 style="margin: 5px 0;">' . htmlspecialchars($name) . '</h2>
            <p style="margin: 2px 0; font-size: 9pt;">
                ' . htmlspecialchars($address) . ' - ' . htmlspecialchars($city) . '<br>
                Tel: ' . htmlspecialchars($phone) . ' - Email: ' . htmlspecialchars($email) . '
            </p>
        </div>';
    }
    
    /**
     * Genera piè di pagina PDF standard
     * 
     * @return string HTML piè di pagina
     */
    public function getFooterHtml() {
        $name = $this->associationData['name'] ?? '';
        $website = $this->associationData['website'] ?? '';
        
        return '
        <div style="text-align: center; border-top: 1px solid #ccc; padding-top: 10px; margin-top: 20px; font-size: 8pt; color: #666;">
            ' . htmlspecialchars($name) . ' - ' . htmlspecialchars($website) . '<br>
            Pagina {PAGENO} di {nbpg}
        </div>';
    }
    
    /**
     * Genera tesserino socio
     * 
     * @param array $memberData Dati socio
     * @param string $output Tipo output
     * @return mixed
     * @throws MpdfException
     */
    public function generateMemberCard($memberData, $output = 'D') {
        $html = $this->getHeaderHtml();
        
        $html .= '
        <div style="border: 2px solid #333; padding: 20px; margin: 20px 0;">
            <h3 style="text-align: center; margin: 0 0 20px 0;">TESSERINO SOCIO</h3>
            
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 70%;">
                        <p><strong>Matricola:</strong> ' . htmlspecialchars($memberData['registration_number'] ?? '') . '</p>
                        <p><strong>Nome:</strong> ' . htmlspecialchars($memberData['first_name'] ?? '') . '</p>
                        <p><strong>Cognome:</strong> ' . htmlspecialchars($memberData['last_name'] ?? '') . '</p>
                        <p><strong>Data di nascita:</strong> ' . htmlspecialchars($memberData['birth_date'] ?? '') . '</p>
                        <p><strong>Codice Fiscale:</strong> ' . htmlspecialchars($memberData['tax_code'] ?? '') . '</p>
                        <p><strong>Tipo socio:</strong> ' . htmlspecialchars($memberData['member_type'] ?? '') . '</p>
                        <p><strong>Data iscrizione:</strong> ' . htmlspecialchars($memberData['registration_date'] ?? '') . '</p>
                    </td>
                    <td style="width: 30%; text-align: center; vertical-align: top;">';
        
        if (!empty($memberData['photo_path']) && file_exists($memberData['photo_path'])) {
            $html .= '<img src="' . $memberData['photo_path'] . '" style="max-width: 100px; max-height: 120px; border: 1px solid #333;" />';
        }
        
        $html .= '
                    </td>
                </tr>
            </table>
            
            <div style="margin-top: 30px; text-align: center; font-size: 8pt; color: #666;">
                <p>Valido per l\'anno ' . date('Y') . '</p>
            </div>
        </div>';
        
        $html .= $this->getFooterHtml();
        
        $filename = 'tesserino_' . ($memberData['registration_number'] ?? 'socio') . '.pdf';
        
        return $this->generate($html, $filename, $output, ['format' => [85, 54], 'orientation' => 'L']);
    }
    
    /**
     * Genera scheda socio completa
     * 
     * @param array $memberData Dati socio completi
     * @param string $output Tipo output
     * @return mixed
     * @throws MpdfException
     */
    public function generateMemberSheet($memberData, $output = 'D') {
        $html = $this->getHeaderHtml();
        
        $html .= '
        <h2 style="text-align: center;">SCHEDA SOCIO</h2>
        
        <h3>Dati Anagrafici</h3>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px;"><strong>Matricola:</strong></td>
                <td style="border: 1px solid #ccc; padding: 5px;">' . htmlspecialchars($memberData['registration_number'] ?? '') . '</td>
                <td style="border: 1px solid #ccc; padding: 5px;"><strong>Stato:</strong></td>
                <td style="border: 1px solid #ccc; padding: 5px;">' . htmlspecialchars($memberData['member_status'] ?? '') . '</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px;"><strong>Cognome:</strong></td>
                <td style="border: 1px solid #ccc; padding: 5px;">' . htmlspecialchars($memberData['last_name'] ?? '') . '</td>
                <td style="border: 1px solid #ccc; padding: 5px;"><strong>Nome:</strong></td>
                <td style="border: 1px solid #ccc; padding: 5px;">' . htmlspecialchars($memberData['first_name'] ?? '') . '</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px;"><strong>Codice Fiscale:</strong></td>
                <td style="border: 1px solid #ccc; padding: 5px;">' . htmlspecialchars($memberData['tax_code'] ?? '') . '</td>
                <td style="border: 1px solid #ccc; padding: 5px;"><strong>Data di nascita:</strong></td>
                <td style="border: 1px solid #ccc; padding: 5px;">' . htmlspecialchars($memberData['birth_date'] ?? '') . '</td>
            </tr>
        </table>';
        
        // Add more sections as needed
        
        $html .= $this->getFooterHtml();
        
        $filename = 'scheda_' . ($memberData['registration_number'] ?? 'socio') . '.pdf';
        
        return $this->generate($html, $filename, $output);
    }
}
