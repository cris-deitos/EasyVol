<?php
/**
 * Genera PDF con elenco soci attivi senza pagamento quota
 * 
 * Genera un documento PDF contenente Matricola, Nome, Cognome
 * di Soci e Cadetti che non hanno versato la quota per l'anno specificato,
 * ordinati per matricola in ordine crescente.
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Utils\PdfGenerator;
use EasyVol\Controllers\FeePaymentController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    http_response_code(403);
    die('Accesso negato');
}

// Verifica permessi (requires members management permission)
if (!$app->checkPermission('members', 'edit')) {
    http_response_code(403);
    die('Accesso negato - permessi insufficienti');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new FeePaymentController($db, $config);

// Parametri
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validazione anno
$minYear = date('Y') - 5;
$maxYear = date('Y') + 1;
if ($year < $minYear || $year > $maxYear) {
    http_response_code(400);
    die('Anno non valido');
}

try {
    // Ottieni tutti i soci senza pagamento per l'anno specificato
    $unpaidMembers = $controller->getAllUnpaidMembersForExport($year);
    
    // Log activity
    AutoLogger::logActivity('fee_payments', 'export', null, "Export PDF soci senza pagamento quota - Anno {$year}");
    
    // Ottieni dati dell'associazione per l'intestazione
    $associationSql = "SELECT * FROM association LIMIT 1";
    $associationData = $db->fetchOne($associationSql);
    
    $associationName = $associationData['name'] ?? 'Associazione di Volontariato';
    
    // Costruisci l'HTML per il PDF
    $html = '
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; }
        h1 { text-align: center; font-size: 16pt; margin-bottom: 5px; }
        h2 { text-align: center; font-size: 12pt; color: #666; margin-top: 0; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background-color: #f0f0f0; border: 1px solid #333; padding: 8px; text-align: left; font-weight: bold; }
        td { border: 1px solid #ccc; padding: 6px; }
        tr:nth-child(even) { background-color: #fafafa; }
        .header-info { text-align: center; margin-bottom: 20px; }
        .total { margin-top: 15px; font-weight: bold; }
        .empty-message { text-align: center; padding: 30px; color: #666; font-style: italic; }
        .badge { padding: 2px 6px; border-radius: 3px; font-size: 8pt; }
        .badge-socio { background-color: #0d6efd; color: white; }
        .badge-cadetto { background-color: #0dcaf0; color: #000; }
    </style>
    
    <div class="header-info">
        <h1>' . htmlspecialchars($associationName) . '</h1>
        <h2>Soci Attivi senza Pagamento Quota - Anno ' . $year . '</h2>
        <p>Data di generazione: ' . date('d/m/Y H:i') . '</p>
    </div>';
    
    if (empty($unpaidMembers)) {
        $html .= '<div class="empty-message">Tutti i soci attivi hanno versato la quota per l\'anno ' . $year . '.</div>';
    } else {
        $html .= '
        <table>
            <thead>
                <tr>
                    <th style="width: 20%;">Matricola</th>
                    <th style="width: 30%;">Nome</th>
                    <th style="width: 35%;">Cognome</th>
                    <th style="width: 15%;">Tipo</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($unpaidMembers as $member) {
            $typeLabel = $member['member_type'] === 'junior' ? 'Cadetto' : 'Socio';
            $typeClass = $member['member_type'] === 'junior' ? 'badge-cadetto' : 'badge-socio';
            
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($member['registration_number']) . '</td>
                    <td>' . htmlspecialchars($member['first_name']) . '</td>
                    <td>' . htmlspecialchars($member['last_name']) . '</td>
                    <td><span class="badge ' . $typeClass . '">' . $typeLabel . '</span></td>
                </tr>';
        }
        
        $html .= '
            </tbody>
        </table>
        <p class="total">Totale soci senza pagamento: ' . count($unpaidMembers) . '</p>';
    }
    
    // Genera il PDF
    $pdfGenerator = new PdfGenerator($config);
    $filename = 'soci_senza_quota_' . $year . '.pdf';
    
    $pdfGenerator->generate($html, $filename, 'D', [
        'margin_top' => 15,
        'margin_bottom' => 15,
        'margin_left' => 15,
        'margin_right' => 15
    ]);
    
} catch (\Exception $e) {
    error_log("Errore generazione PDF soci senza pagamento: " . $e->getMessage());
    http_response_code(500);
    die('Errore durante la generazione del PDF: ' . htmlspecialchars($e->getMessage()));
}
