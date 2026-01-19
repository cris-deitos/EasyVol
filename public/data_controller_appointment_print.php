<?php
/**
 * Stampa Nomina Responsabile Trattamento Dati
 * 
 * Genera PDF per la nomina di responsabile del trattamento dati
 * con dati anagrafici del socio collegato all'utente
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\GdprController;
use EasyVol\Utils\PdfGenerator;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('gdpr_compliance', 'print_appointment')) {
    die('Accesso negato: non hai i permessi per stampare le nomine');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new GdprController($db, $config);

// Ottieni ID nomina
$appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$appointmentId) {
    die('ID nomina non specificato');
}

// Ottieni dati nomina con dati anagrafici completi
$appointment = $controller->getAppointmentWithMemberData($appointmentId);

if (!$appointment) {
    die('Nomina non trovata');
}

// Determine appointee type and get appropriate data
$appointeeType = 'unknown';
$appointeeData = [];

if (!empty($appointment['external_person_name'])) {
    // External person
    $appointeeType = 'external';
    $appointeeData = [
        'first_name' => $appointment['external_person_name'],
        'last_name' => $appointment['external_person_surname'],
        'tax_code' => $appointment['external_person_tax_code'] ?? '',
        'birth_date' => $appointment['external_person_birth_date'] ?? '',
        'birth_place' => $appointment['external_person_birth_place'] ?? '',
        'birth_province' => $appointment['external_person_birth_province'] ?? '',
        'gender' => $appointment['external_person_gender'] ?? '',
        'address' => $appointment['external_person_address'] ?? '',
        'city' => $appointment['external_person_city'] ?? '',
        'province' => $appointment['external_person_province'] ?? '',
        'postal_code' => $appointment['external_person_postal_code'] ?? '',
        'phone' => $appointment['external_person_phone'] ?? '',
        'mobile' => '',
        'email' => $appointment['external_person_email'] ?? '',
        'civic_number' => '',
    ];
} elseif (!empty($appointment['member_id']) || !empty($appointment['member_first_name'])) {
    // Member (either direct or via user)
    $appointeeType = 'member';
    $appointeeData = [
        'first_name' => $appointment['member_first_name'] ?? '',
        'last_name' => $appointment['member_last_name'] ?? '',
        'tax_code' => $appointment['tax_code'] ?? '',
        'birth_date' => $appointment['birth_date'] ?? '',
        'birth_place' => $appointment['birth_place'] ?? '',
        'birth_province' => $appointment['birth_province'] ?? '',
        'gender' => $appointment['gender'] ?? '',
        'address' => $appointment['address'] ?? '',
        'civic_number' => $appointment['civic_number'] ?? '',
        'city' => $appointment['city'] ?? '',
        'province' => $appointment['province'] ?? '',
        'postal_code' => $appointment['postal_code'] ?? '',
        'phone' => $appointment['phone'] ?? '',
        'mobile' => $appointment['mobile'] ?? '',
        'email' => $appointment['member_email'] ?? $appointment['email'] ?? '',
    ];
} else {
    die('Errore: impossibile determinare i dati anagrafici del nominato. Assicurarsi che sia collegato a un socio o che i dati della persona esterna siano compilati.');
}

// Prepara i dati per il template
$data = [
    'association_name' => $config['association']['name'] ?? 'Associazione',
    'association_address' => $config['association']['address'] ?? '',
    'association_city' => $config['association']['city'] ?? '',
    'association_province' => $config['association']['province'] ?? '',
    'association_postal_code' => $config['association']['postal_code'] ?? '',
    'association_vat' => $config['association']['vat_number'] ?? '',
    'association_email' => $config['association']['email'] ?? '',
    'association_phone' => $config['association']['phone'] ?? '',
    'today_date' => date('d/m/Y'),
    'appointment_date' => date('d/m/Y', strtotime($appointment['appointment_date'])),
    'appointment_type' => $appointment['appointment_type'],
    'appointment_type_label' => getAppointmentTypeLabel($appointment['appointment_type']),
    'appointee_type' => $appointeeType,
    'member_first_name' => $appointeeData['first_name'],
    'member_last_name' => $appointeeData['last_name'],
    'member_full_name' => trim($appointeeData['first_name'] . ' ' . $appointeeData['last_name']),
    'member_tax_code' => $appointeeData['tax_code'],
    'member_birth_date' => $appointeeData['birth_date'] ? date('d/m/Y', strtotime($appointeeData['birth_date'])) : '',
    'member_birth_place' => trim($appointeeData['birth_place'] . ($appointeeData['birth_province'] ? ' (' . $appointeeData['birth_province'] . ')' : '')),
    'member_address' => $appointeeData['address'],
    'member_civic_number' => $appointeeData['civic_number'],
    'member_city' => $appointeeData['city'],
    'member_province' => $appointeeData['province'],
    'member_postal_code' => $appointeeData['postal_code'],
    'member_full_address' => buildFullAddress($appointeeData),
    'member_phone' => $appointeeData['phone'],
    'member_mobile' => $appointeeData['mobile'],
    'member_email' => $appointeeData['email'],
    'scope' => $appointment['scope'] ?? '',
    'responsibilities' => $appointment['responsibilities'] ?? '',
    'data_categories_access' => $appointment['data_categories_access'] ?? '',
    'training_completed' => $appointment['training_completed'] ? 'Sì' : 'No',
    'training_date' => $appointment['training_date'] ? date('d/m/Y', strtotime($appointment['training_date'])) : 'Non completata',
];

// Genera HTML del documento
$html = generateAppointmentHtml($data);

// Genera PDF
$pdfGenerator = new PdfGenerator($config);
$filename = 'Nomina_Responsabile_' . sanitizeFilename($data['member_last_name']) . '_' . date('Y-m-d') . '.pdf';

try {
    $pdfGenerator->generate($html, $filename, 'I'); // I = inline display in browser
} catch (Exception $e) {
    die('Errore generazione PDF: ' . $e->getMessage());
}

/**
 * Ottieni etichetta tipo nomina
 */
function getAppointmentTypeLabel($type) {
    $labels = [
        'data_controller' => 'Titolare del Trattamento',
        'data_processor' => 'Responsabile del Trattamento',
        'dpo' => 'Data Protection Officer (DPO)',
        'authorized_person' => 'Persona Autorizzata al Trattamento'
    ];
    return $labels[$type] ?? $type;
}

/**
 * Costruisci indirizzo completo
 */
function buildFullAddress($appointment) {
    $parts = [];
    if (!empty($appointment['address'])) {
        $parts[] = $appointment['address'];
        if (!empty($appointment['civic_number'])) {
            $parts[0] .= ', ' . $appointment['civic_number'];
        }
    }
    if (!empty($appointment['postal_code']) || !empty($appointment['city'])) {
        $cityPart = trim(($appointment['postal_code'] ?? '') . ' ' . ($appointment['city'] ?? ''));
        if (!empty($appointment['province'])) {
            $cityPart .= ' (' . $appointment['province'] . ')';
        }
        $parts[] = $cityPart;
    }
    return implode('<br>', array_filter($parts));
}

/**
 * Sanitizza nome file
 */
function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    return $filename;
}

/**
 * Genera HTML del documento di nomina
 */
function generateAppointmentHtml($data) {
    $html = '
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #0066cc;
            font-size: 16pt;
            margin: 0 0 5px 0;
            text-transform: uppercase;
        }
        .header .association {
            font-size: 10pt;
            color: #666;
        }
        .document-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 30px 0 20px 0;
            color: #0066cc;
        }
        .document-type {
            text-align: center;
            font-size: 12pt;
            margin-bottom: 30px;
            font-style: italic;
        }
        .content {
            text-align: justify;
            margin: 20px 0;
        }
        .content p {
            margin: 10px 0;
        }
        .section-title {
            font-weight: bold;
            font-size: 11pt;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #0066cc;
            text-transform: uppercase;
        }
        .data-box {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0;
        }
        .data-row {
            margin: 5px 0;
        }
        .data-label {
            font-weight: bold;
            display: inline-block;
            width: 180px;
        }
        .signatures {
            margin-top: 50px;
        }
        .signature-block {
            display: inline-block;
            width: 45%;
            vertical-align: top;
            margin-top: 30px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 60px;
            padding-top: 5px;
            text-align: center;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 9pt;
            color: #666;
            text-align: center;
        }
        ul, ol {
            margin: 10px 0;
            padding-left: 25px;
        }
        li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . htmlspecialchars($data['association_name']) . '</h1>
        <div class="association">
            ' . htmlspecialchars($data['association_address']) . '<br>
            ' . htmlspecialchars($data['association_postal_code'] . ' ' . $data['association_city'] . ' (' . $data['association_province'] . ')') . '<br>
            P.IVA: ' . htmlspecialchars($data['association_vat']) . ' | Tel: ' . htmlspecialchars($data['association_phone']) . ' | Email: ' . htmlspecialchars($data['association_email']) . '
        </div>
    </div>

    <div class="document-title">
        Atto di Nomina e Designazione
    </div>
    
    <div class="document-type">
        ' . htmlspecialchars($data['appointment_type_label']) . '<br>
        ai sensi del Regolamento UE 2016/679 (GDPR)
    </div>

    <div class="content">
        <p><strong>Il/La sottoscritto/a</strong> (Titolare del Trattamento o suo delegato), in qualità di legale rappresentante di <strong>' . htmlspecialchars($data['association_name']) . '</strong>, con sede legale in ' . htmlspecialchars($data['association_city']) . ', ai sensi e per gli effetti degli articoli 4, 24, 28, 29 e 32 del Regolamento (UE) 2016/679 (GDPR),</p>

        <p style="text-align: center; font-weight: bold; margin: 20px 0;">NOMINA E DESIGNA</p>

        <div class="section-title">Dati del Nominato</div>
        <div class="data-box">
            <div class="data-row">
                <span class="data-label">Cognome e Nome:</span>
                <span>' . htmlspecialchars($data['member_full_name']) . '</span>
            </div>
            <div class="data-row">
                <span class="data-label">Codice Fiscale:</span>
                <span>' . htmlspecialchars($data['member_tax_code']) . '</span>
            </div>
            <div class="data-row">
                <span class="data-label">Nato/a il:</span>
                <span>' . htmlspecialchars($data['member_birth_date']) . ' a ' . htmlspecialchars($data['member_birth_place']) . '</span>
            </div>
            <div class="data-row">
                <span class="data-label">Residente in:</span>
                <span>' . $data['member_full_address'] . '</span>
            </div>
            <div class="data-row">
                <span class="data-label">Telefono:</span>
                <span>' . htmlspecialchars($data['member_mobile'] ?: $data['member_phone']) . '</span>
            </div>
            <div class="data-row">
                <span class="data-label">Email:</span>
                <span>' . htmlspecialchars($data['member_email']) . '</span>
            </div>
        </div>

        <div class="section-title">Ruolo e Qualifica</div>
        <p><strong>' . htmlspecialchars($data['appointment_type_label']) . '</strong> per il trattamento dei dati personali nell\'ambito delle attività dell\'associazione, con decorrenza dal <strong>' . htmlspecialchars($data['appointment_date']) . '</strong>.</p>

        ' . (!empty($data['scope']) ? '
        <div class="section-title">Ambito di Competenza</div>
        <p>' . nl2br(htmlspecialchars($data['scope'])) . '</p>
        ' : '') . '

        ' . (!empty($data['responsibilities']) ? '
        <div class="section-title">Responsabilità e Compiti</div>
        <p>' . nl2br(htmlspecialchars($data['responsibilities'])) . '</p>
        ' : '') . '

        ' . (!empty($data['data_categories_access']) ? '
        <div class="section-title">Categorie di Dati Trattati</div>
        <p>' . nl2br(htmlspecialchars($data['data_categories_access'])) . '</p>
        ' : '') . '

        <div class="section-title">Obblighi del Designato</div>
        <p>Il designato si impegna a:</p>
        <ol>
            <li>Trattare i dati personali esclusivamente per le finalità indicate e secondo le istruzioni ricevute dal Titolare del Trattamento;</li>
            <li>Garantire la riservatezza dei dati trattati e non divulgarli a soggetti non autorizzati;</li>
            <li>Adottare misure tecniche e organizzative adeguate per garantire la sicurezza dei dati personali;</li>
            <li>Segnalare tempestivamente al Titolare eventuali violazioni dei dati personali (data breach);</li>
            <li>Collaborare con l\'Autorità Garante per la protezione dei dati personali, se richiesto;</li>
            <li>Garantire che le persone autorizzate al trattamento si siano impegnate alla riservatezza;</li>
            <li>Rispettare tutte le disposizioni del GDPR e della normativa nazionale applicabile.</li>
        </ol>

        <div class="section-title">Formazione GDPR</div>
        <p>
            <strong>Formazione completata:</strong> ' . htmlspecialchars($data['training_completed']) . '<br>
            ' . ($data['training_completed'] === 'Sì' ? '<strong>Data formazione:</strong> ' . htmlspecialchars($data['training_date']) : '<em>È richiesto il completamento della formazione GDPR entro 30 giorni dalla nomina.</em>') . '
        </p>

        <div class="section-title">Riferimenti Normativi</div>
        <p>La presente nomina è effettuata ai sensi del:</p>
        <ul>
            <li>Regolamento (UE) 2016/679 del Parlamento Europeo (GDPR)</li>
            <li>D.Lgs. 196/2003 (Codice Privacy) come modificato dal D.Lgs. 101/2018</li>
            <li>Provvedimenti del Garante per la protezione dei dati personali</li>
        </ul>

        <div class="section-title">Durata e Revoca</div>
        <p>La presente nomina ha validità a tempo indeterminato e può essere revocata in qualsiasi momento dal Titolare del Trattamento mediante comunicazione scritta. Il designato decade automaticamente dalla carica in caso di cessazione del rapporto con l\'associazione.</p>
    </div>

    <div class="signatures">
        <div class="signature-block" style="float: left;">
            <strong>Il Titolare del Trattamento</strong><br>
            <em>' . htmlspecialchars($data['association_name']) . '</em>
            <div class="signature-line">
                (Firma del Legale Rappresentante)
            </div>
        </div>
        
        <div class="signature-block" style="float: right;">
            <strong>Il Designato</strong><br>
            <em>' . htmlspecialchars($data['member_full_name']) . '</em>
            <div class="signature-line">
                (Firma per accettazione)
            </div>
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="footer">
        Documento generato il ' . htmlspecialchars($data['today_date']) . ' | ' . htmlspecialchars($data['association_name']) . '<br>
        Il presente documento è stato generato elettronicamente dal sistema EasyVol
    </div>
</body>
</html>';

    return $html;
}
