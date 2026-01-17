<?php
/**
 * Province Event View - Public Access Page
 * 
 * This page allows provincial civil protection authorities to view event details
 * using a secure token and access code.
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\Database;
use EasyVol\Middleware\CsrfProtection;

// This is a public page, so we don't check for authentication
// Instead, we use token-based access

$error = '';
$event = null;
$interventions = [];
$authenticated = false;

// Get token from URL
$token = $_GET['token'] ?? '';

// Validate token format: must be 64 hexadecimal characters
if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
    $error = 'Token non valido o mancante';
} else {
    // Initialize database connection
    $config = require __DIR__ . '/../config/config.php';
    $db = Database::getInstance($config['database']);
    
    // Check if access code has been submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_code'])) {
        if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
            $error = 'Token di sicurezza non valido';
        } else {
            $accessCode = strtoupper(trim($_POST['access_code'] ?? ''));
            
            // Find event by token and validate access code
            $event = $db->fetchOne(
                "SELECT * FROM events WHERE province_access_token = ? AND province_access_code = ?",
                [$token, $accessCode]
            );
            
            if ($event) {
                $authenticated = true;
                // Store authentication in session
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['province_token_' . $token] = true;
            } else {
                $error = 'Codice di accesso non valido';
            }
        }
    } else {
        // Check if already authenticated in session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['province_token_' . $token])) {
            // Load event data
            $event = $db->fetchOne(
                "SELECT * FROM events WHERE province_access_token = ?",
                [$token]
            );
            if ($event) {
                $authenticated = true;
            }
        } else {
            // Check if token exists but not yet authenticated
            $tokenExists = $db->fetchOne(
                "SELECT id FROM events WHERE province_access_token = ?",
                [$token]
            );
            if (!$tokenExists) {
                $error = 'Token non valido o scaduto';
            }
        }
    }
    
    // Load interventions and participants if authenticated
    if ($authenticated && $event) {
        // Load interventions with member count
        $interventions = $db->fetchAll(
            "SELECT i.id, i.event_id, i.title, i.description, i.start_time, i.end_time, 
                    i.location, i.status, i.latitude, i.longitude, i.full_address, i.municipality,
                    COUNT(DISTINCT im.member_id) as members_count
             FROM interventions i
             LEFT JOIN intervention_members im ON i.id = im.intervention_id
             WHERE i.event_id = ?
             GROUP BY i.id, i.event_id, i.title, i.description, i.start_time, i.end_time, 
                      i.location, i.status, i.latitude, i.longitude, i.full_address, i.municipality
             ORDER BY i.start_time",
            [$event['id']]
        );
        
        // For each intervention, load members with full information
        foreach ($interventions as &$intervention) {
            $members = $db->fetchAll(
                "SELECT m.first_name, m.last_name, m.tax_code
                 FROM intervention_members im
                 JOIN members m ON im.member_id = m.id
                 WHERE im.intervention_id = ?
                 ORDER BY m.last_name, m.first_name",
                [$intervention['id']]
            );
            $intervention['members'] = $members;
        }
        unset($intervention); // Important: unset reference to avoid bugs in subsequent foreach loops
    }
}

$pageTitle = $authenticated ? 'Visualizzazione Evento - Provincia' : 'Accesso Riservato';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 500px;
            margin: 0 auto;
        }
        .event-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 1200px;
        }
        .card-header-province {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body>
    <?php if (!$authenticated): ?>
        <!-- Login Form -->
        <div class="container">
            <div class="login-container">
                <div class="card shadow-lg">
                    <div class="card-header card-header-province text-center py-4">
                        <h3 class="mb-0"><i class="bi bi-shield-lock"></i> Accesso Riservato</h3>
                        <p class="mb-0 small">Ufficio di Protezione Civile della Provincia</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Inserisci il codice di accesso fornito nell'email per visualizzare i dettagli dell'evento.
                        </div>
                        
                        <form method="POST">
                            <?php echo CsrfProtection::getHiddenField(); ?>
                            
                            <div class="mb-3">
                                <label for="access_code" class="form-label">
                                    <i class="bi bi-key"></i> Codice di Accesso
                                </label>
                                <input type="text" class="form-control form-control-lg text-center" 
                                       id="access_code" name="access_code" 
                                       placeholder="Inserisci codice 8 caratteri" 
                                       maxlength="8" required autofocus
                                       style="letter-spacing: 0.3em; font-weight: bold;">
                                <small class="text-muted">Il codice è composto da 8 caratteri alfanumerici maiuscoli</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-unlock"></i> Accedi
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Event View -->
        <div class="container-fluid py-4">
            <div class="event-container p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-calendar-event"></i> Visualizzazione Evento</h2>
                    <a href="?token=<?php echo htmlspecialchars($token); ?>&logout=1" 
                       class="btn btn-sm btn-outline-secondary"
                       onclick="return confirm('Sei sicuro di voler uscire?');">
                        <i class="bi bi-box-arrow-right"></i> Esci
                    </a>
                </div>
                
                <!-- Event Details Card -->
                <div class="card mb-4">
                    <div class="card-header card-header-province">
                        <h4 class="mb-0"><i class="bi bi-info-circle"></i> Dettagli Evento</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Titolo:</th>
                                        <td><strong><?php echo htmlspecialchars($event['title']); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Tipo Evento:</th>
                                        <td>
                                            <?php 
                                            $eventTypes = [
                                                'emergenza' => ['label' => 'Emergenza', 'class' => 'danger'],
                                                'esercitazione' => ['label' => 'Esercitazione', 'class' => 'warning'],
                                                'attivita' => ['label' => 'Attività', 'class' => 'info'],
                                                'servizio' => ['label' => 'Servizio', 'class' => 'primary']
                                            ];
                                            $typeInfo = $eventTypes[$event['event_type']] ?? ['label' => $event['event_type'], 'class' => 'secondary'];
                                            ?>
                                            <span class="badge bg-<?php echo $typeInfo['class']; ?>">
                                                <?php echo htmlspecialchars($typeInfo['label']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Data Inizio:</th>
                                        <td><?php echo date('d/m/Y H:i', strtotime($event['start_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Data Fine:</th>
                                        <td>
                                            <?php 
                                            echo !empty($event['end_date']) 
                                                ? date('d/m/Y H:i', strtotime($event['end_date'])) 
                                                : '<em>In corso</em>'; 
                                            ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Località:</th>
                                        <td><?php echo htmlspecialchars($event['location'] ?? '-'); ?></td>
                                    </tr>
                                    <?php if (!empty($event['municipality'])): ?>
                                    <tr>
                                        <th>Comune:</th>
                                        <td><?php echo htmlspecialchars($event['municipality']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>Stato:</th>
                                        <td>
                                            <?php 
                                            $statusClass = [
                                                'in_corso' => 'warning',
                                                'concluso' => 'success',
                                                'annullato' => 'danger'
                                            ];
                                            $class = $statusClass[$event['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $class; ?>">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $event['status']))); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Benefici di Legge:</th>
                                        <td>
                                            <?php 
                                            $benefitsValue = $event['legal_benefits_recognized'] ?? 'no';
                                            $benefitsClass = $benefitsValue === 'si' ? 'success' : 'secondary';
                                            $benefitsLabel = $benefitsValue === 'si' ? 'SI' : 'NO';
                                            ?>
                                            <span class="badge bg-<?php echo $benefitsClass; ?>">
                                                <?php echo htmlspecialchars($benefitsLabel); ?>
                                            </span>
                                            <br><small class="text-muted">Art. 39 e 40 D. Lgs. n. 1 del 2018</small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Interventi:</th>
                                        <td><strong><?php echo count($interventions); ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if (!empty($event['description'])): ?>
                        <hr>
                        <div class="mt-3">
                            <h6><i class="bi bi-file-text"></i> Descrizione:</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Download Excel Button -->
                <div class="card mb-4 border-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="mb-2"><i class="bi bi-file-earmark-excel"></i> Esporta Dati Volontari</h5>
                                <p class="mb-0 text-muted">
                                    Scarica file Excel con i dati dei volontari partecipanti (nome, cognome, codice fiscale), suddivisi per giorni.
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="province_export_excel.php?token=<?php echo htmlspecialchars($token); ?>" 
                                   class="btn btn-success btn-lg">
                                    <i class="bi bi-download"></i> Scarica Excel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Interventions List -->
                <div class="card">
                    <div class="card-header card-header-province">
                        <h4 class="mb-0"><i class="bi bi-list-check"></i> Interventi</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($interventions)): ?>
                            <p class="text-muted mb-0">Nessun intervento registrato per questo evento.</p>
                        <?php else: ?>
                            <div class="accordion" id="interventionsAccordion">
                                <?php foreach ($interventions as $index => $intervention): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                            <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?>" 
                                                    type="button" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#collapse<?php echo $index; ?>" 
                                                    aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                                <strong><?php echo htmlspecialchars($intervention['title']); ?></strong>
                                                <span class="ms-3 badge bg-secondary">
                                                    <?php echo $intervention['members_count']; ?> volontari
                                                </span>
                                                <span class="ms-2 text-muted small">
                                                    <?php echo date('d/m/Y H:i', strtotime($intervention['start_time'])); ?>
                                                </span>
                                            </button>
                                        </h2>
                                        <div id="collapse<?php echo $index; ?>" 
                                             class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" 
                                             data-bs-parent="#interventionsAccordion">
                                            <div class="accordion-body">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <p><strong>Descrizione:</strong><br>
                                                        <?php echo !empty($intervention['description']) ? nl2br(htmlspecialchars($intervention['description'])) : '<em>Nessuna descrizione</em>'; ?>
                                                        </p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>Località:</strong> <?php echo htmlspecialchars($intervention['location'] ?? '-'); ?></p>
                                                        <p><strong>Stato:</strong> 
                                                            <?php 
                                                            $intStatusClass = [
                                                                'in_corso' => 'warning',
                                                                'concluso' => 'success',
                                                                'sospeso' => 'secondary'
                                                            ];
                                                            $class = $intStatusClass[$intervention['status']] ?? 'secondary';
                                                            ?>
                                                            <span class="badge bg-<?php echo $class; ?>">
                                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $intervention['status']))); ?>
                                                            </span>
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                <?php if (!empty($intervention['members'])): ?>
                                                    <h6><i class="bi bi-people"></i> Volontari Partecipanti:</h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-striped">
                                                            <thead>
                                                                <tr>
                                                                    <th>Nome</th>
                                                                    <th>Cognome</th>
                                                                    <th>Codice Fiscale</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($intervention['members'] as $member): ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($member['first_name'] ?? 'N/D'); ?></td>
                                                                        <td><?php echo htmlspecialchars($member['last_name'] ?? 'N/D'); ?></td>
                                                                        <td><code><?php echo htmlspecialchars($member['tax_code'] ?? 'N/D'); ?></code></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-muted">Nessun volontario assegnato a questo intervento.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-muted small">
                    <p class="mb-0">Sistema di Gestione Volontariato - Accesso Riservato Provincia</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (!$authenticated): ?>
    <script>
        // Auto-format access code to uppercase
        document.getElementById('access_code').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
    </script>
    <?php endif; ?>
    
    <?php
    // Handle logout
    if (isset($_GET['logout']) && $authenticated) {
        unset($_SESSION['province_token_' . $token]);
        header('Location: province_event_view.php?token=' . urlencode($token));
        exit;
    }
    ?>
</body>
</html>
