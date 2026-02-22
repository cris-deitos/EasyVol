<?php
/**
 * Gestione Riunioni - Visualizzazione Dettaglio
 * 
 * Pagina per visualizzare i dettagli completi di una riunione/assemblea
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\MeetingController;
use EasyVol\Controllers\PrintTemplateController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('meetings', 'view')) {
    die('Accesso negato');
}

$meetingId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($meetingId <= 0) {
    header('Location: meetings.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new MeetingController($db, $config);

$meeting = $controller->get($meetingId);

if (!$meeting) {
    header('Location: meetings.php?error=not_found');
    exit;
}

// Load print templates for meetings
$printController = new PrintTemplateController($db, $config);
$printTemplates = $printController->getAll([
    'entity_type' => 'meetings',
    'is_active' => 1
]);

// Generate page title from meeting type and date
$meetingTypeName = MeetingController::MEETING_TYPE_NAMES[$meeting['meeting_type']] ?? ucfirst(str_replace('_', ' ', $meeting['meeting_type']));
$meetingDateFormatted = date('d/m/Y', strtotime($meeting['meeting_date']));
$pageTitle = $meetingTypeName . ' - ' . $meetingDateFormatted;

// Load active members for delegate autocomplete
$activeMembers = $db->fetchAll("SELECT id, first_name, last_name, registration_number FROM members WHERE member_status = 'attivo' ORDER BY last_name, first_name");
$activeJuniorMembers = $db->fetchAll("SELECT id, first_name, last_name, registration_number FROM junior_members WHERE member_status = 'attivo' ORDER BY last_name, first_name");
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
    <?php include __DIR__ . '/../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../src/Views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <a href="meetings.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                <a href="meeting_edit.php?id=<?php echo $meeting['id']; ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil"></i> Modifica
                                </a>
                            <?php endif; ?>
                            <div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="bi bi-printer"></i> Stampa
                                </button>
                                <ul class="dropdown-menu">
                                    <?php if (!empty($printTemplates)): ?>
                                        <?php 
                                        $displayedTemplates = array_slice($printTemplates, 0, 3); 
                                        foreach ($displayedTemplates as $template): 
                                        ?>
                                            <li><a class="dropdown-item" href="#" onclick="printById(<?php echo $template['id']; ?>); return false;">
                                                <i class="bi bi-file-earmark-text"></i> <?php echo htmlspecialchars($template['name']); ?>
                                            </a></li>
                                        <?php endforeach; ?>
                                        <?php if (count($printTemplates) > 3): ?>
                                            <li><hr class="dropdown-divider"></li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item" href="#" onclick="showPrintModal(); return false;">
                                        <i class="bi bi-gear"></i> Scegli Template...
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3" id="meetingTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">
                            <i class="bi bi-info-circle"></i> Informazioni Generali
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="participants-tab" data-bs-toggle="tab" data-bs-target="#participants" type="button">
                            <i class="bi bi-people"></i> Partecipanti
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="agenda-tab" data-bs-toggle="tab" data-bs-target="#agenda" type="button">
                            <i class="bi bi-list-ol"></i> Ordine del Giorno
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="documenti-tab" data-bs-toggle="tab" data-bs-target="#documenti" type="button">
                            <i class="bi bi-paperclip"></i> Documenti
                            <?php 
                            $attachmentCount = count($meeting['attachments'] ?? []);
                            if ($attachmentCount > 0): ?>
                                <span class="badge bg-secondary ms-1"><?php echo $attachmentCount; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="meetingTabContent">
                    <!-- Tab Informazioni Generali -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0"><i class="bi bi-calendar3"></i> Dati Riunione</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th>Tipo:</th>
                                                <td>
                                                    <?php 
                                                    $type = MeetingController::MEETING_TYPE_NAMES[$meeting['meeting_type']] ?? $meeting['meeting_type'];
                                                    $typeClass = [
                                                        'assemblea_ordinaria' => 'primary',
                                                        'assemblea_straordinaria' => 'danger',
                                                        'consiglio_direttivo' => 'warning',
                                                        'riunione_capisquadra' => 'info',
                                                        'riunione_nucleo' => 'success'
                                                    ];
                                                    $class = $typeClass[$meeting['meeting_type']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>">
                                                        <?php echo htmlspecialchars($type); ?>
                                                    </span>
                                                    <?php if (!empty($meeting['progressive_number'])): ?>
                                                        <span class="ms-1 text-muted">n. <?php echo intval($meeting['progressive_number']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Data e Ora:</th>
                                                <td>
                                                    <?php 
                                                    echo date('d/m/Y', strtotime($meeting['meeting_date']));
                                                    if (!empty($meeting['start_time'])) {
                                                        echo ' ' . date('H:i', strtotime($meeting['start_time']));
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Modalità:</th>
                                                <td><?php echo ($meeting['location_type'] ?? 'fisico') === 'online' ? 'Online' : 'In Presenza'; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Località:</th>
                                                <td><?php echo htmlspecialchars($meeting['location'] ?? '-'); ?></td>
                                            </tr>
                                            <?php if (($meeting['location_type'] ?? '') === 'online' && !empty($meeting['online_details'])): ?>
                                            <tr>
                                                <th>Istruzioni per il collegamento online:</th>
                                                <td><?php echo nl2br(htmlspecialchars($meeting['online_details'])); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th>Convocata da:</th>
                                                <td><?php echo htmlspecialchars($meeting['convened_by'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Presidente:</th>
                                                <td><?php echo htmlspecialchars($meeting['president'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Segretario:</th>
                                                <td><?php echo htmlspecialchars($meeting['secretary'] ?? '-'); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Statistiche</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="40%">Totale Partecipanti:</th>
                                                <td><strong><?php echo count($meeting['participants'] ?? []); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th>Presenti:</th>
                                                <td>
                                                    <strong class="text-success">
                                                        <?php 
                                                        $present = array_filter($meeting['participants'] ?? [], function($p) {
                                                            return ($p['attendance_status'] ?? '') === 'present';
                                                        });
                                                        echo count($present);
                                                        ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Assenti:</th>
                                                <td>
                                                    <strong class="text-danger">
                                                        <?php 
                                                        $absent = array_filter($meeting['participants'] ?? [], function($p) {
                                                            return ($p['attendance_status'] ?? '') === 'absent';
                                                        });
                                                        echo count($absent);
                                                        ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Punti all'OdG:</th>
                                                <td><strong><?php echo count($meeting['agenda'] ?? []); ?></strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <?php if (!empty($meeting['notes'])): ?>
                                <div class="card mb-3">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> Note</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($meeting['notes'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($meeting['minutes'])): ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card mb-3">
                                    <div class="card-header bg-secondary text-white">
                                        <h5 class="mb-0"><i class="bi bi-file-text"></i> Verbale</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($meeting['minutes'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab Partecipanti -->
                    <div class="tab-pane fade" id="participants" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-people"></i> Partecipanti</h5>
                                    <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                        <a href="meeting_participants.php?id=<?php echo $meeting['id']; ?>" class="btn btn-sm btn-success">
                                            <i class="bi bi-plus-circle"></i> Gestisci Partecipanti
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($meeting['participants'])): ?>
                                    <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                        <div class="mb-3 d-flex align-items-center gap-2">
                                            <button type="button" class="btn btn-success btn-sm" onclick="bulkUpdateAttendance('present')" id="bulk-present-btn" disabled>
                                                <i class="bi bi-check-circle"></i> Segna Selezionati come Presenti
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="bulkUpdateAttendance('absent')" id="bulk-absent-btn" disabled>
                                                <i class="bi bi-x-circle"></i> Segna Selezionati come Assenti
                                            </button>
                                            <span class="text-muted ms-2" id="selected-count">0 selezionati</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                                        <th style="width: 40px;">
                                                            <input type="checkbox" class="form-check-input" id="select-all-participants" title="Seleziona tutti">
                                                        </th>
                                                    <?php endif; ?>
                                                    <th>Nome</th>
                                                    <th>Ruolo</th>
                                                    <th>Presenza</th>
                                                    <th>Note</th>
                                                    <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                                        <th>Azioni</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($meeting['participants'] as $participant): ?>
                                                    <tr>
                                                        <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                                            <td>
                                                                <input type="checkbox" class="form-check-input participant-checkbox" 
                                                                       data-participant-id="<?php echo $participant['id']; ?>" 
                                                                       value="<?php echo $participant['id']; ?>">
                                                            </td>
                                                        <?php endif; ?>
                                                        <td>
                                                            <?php 
                                                            // Construct member name based on member type
                                                            $memberName = '';
                                                            if ($participant['member_type'] === 'junior') {
                                                                $firstName = $participant['junior_first_name'] ?? '';
                                                                $lastName = $participant['junior_last_name'] ?? '';
                                                                if ($firstName || $lastName) {
                                                                    $memberName = trim($firstName . ' ' . $lastName);
                                                                }
                                                            } else {
                                                                $firstName = $participant['first_name'] ?? '';
                                                                $lastName = $participant['last_name'] ?? '';
                                                                if ($firstName || $lastName) {
                                                                    $memberName = trim($firstName . ' ' . $lastName);
                                                                }
                                                            }
                                                            // Fallback to participant_name if available
                                                            if (empty($memberName)) {
                                                                $memberName = $participant['participant_name'] ?? 'N/A';
                                                            }
                                                            echo htmlspecialchars($memberName);
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                                                <select class="form-select form-select-sm participant-role-select" 
                                                                        data-participant-id="<?php echo $participant['id']; ?>"
                                                                        onchange="updateRole(<?php echo $participant['id']; ?>, this.value)">
                                                                    <option value="" <?php echo empty($participant['role']) ? 'selected' : ''; ?>>-</option>
                                                                    <option value="Presidente" <?php echo ($participant['role'] ?? '') === 'Presidente' ? 'selected' : ''; ?>>Presidente</option>
                                                                    <option value="Segretario" <?php echo ($participant['role'] ?? '') === 'Segretario' ? 'selected' : ''; ?>>Segretario</option>
                                                                    <option value="Uditore" <?php echo ($participant['role'] ?? '') === 'Uditore' ? 'selected' : ''; ?>>Uditore</option>
                                                                    <option value="Scrutatore" <?php echo ($participant['role'] ?? '') === 'Scrutatore' ? 'selected' : ''; ?>>Scrutatore</option>
                                                                    <option value="Presidente del Seggio Elettorale" <?php echo ($participant['role'] ?? '') === 'Presidente del Seggio Elettorale' ? 'selected' : ''; ?>>Pres. Seggio Elettorale</option>
                                                                </select>
                                                            <?php else: ?>
                                                                <?php echo htmlspecialchars($participant['role'] ?? '-'); ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $attendance = $participant['attendance_status'] ?? 'invited';
                                                            $attendanceClass = [
                                                                'present' => 'success',
                                                                'absent' => 'danger',
                                                                'delegated' => 'warning',
                                                                'invited' => 'secondary'
                                                            ];
                                                            $attendanceLabels = [
                                                                'present' => 'Presente',
                                                                'absent' => 'Assente',
                                                                'delegated' => 'Delegato',
                                                                'invited' => 'Invitato'
                                                            ];
                                                            $class = $attendanceClass[$attendance] ?? 'secondary';
                                                            $label = $attendanceLabels[$attendance] ?? 'Non definito';
                                                            ?>
                                                            <span class="badge bg-<?php echo $class; ?>">
                                                                <?php echo htmlspecialchars($label); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($participant['notes'] ?? '-'); ?></td>
                                                        <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                                            <td>
                                                                <div class="btn-group btn-group-sm" role="group">
                                                                    <button type="button" class="btn btn-success" 
                                                                            onclick="updateAttendance(<?php echo $participant['id']; ?>, 'present')"
                                                                            title="Segna come Presente">
                                                                        <i class="bi bi-check-circle"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-danger" 
                                                                            onclick="updateAttendance(<?php echo $participant['id']; ?>, 'absent')"
                                                                            title="Segna come Assente">
                                                                        <i class="bi bi-x-circle"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-warning" 
                                                                            onclick="showDelegateModal(<?php echo $participant['id']; ?>, '<?php echo htmlspecialchars(addslashes($memberName ?? '')); ?>')"
                                                                            title="Inserisci Delega">
                                                                        <i class="bi bi-person-check"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Nessun partecipante registrato.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Ordine del Giorno -->
                    <div class="tab-pane fade" id="agenda" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-list-ol"></i> Ordine del Giorno</h5>
                                    <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addAgendaModal">
                                            <i class="bi bi-plus-circle"></i> Aggiungi Punto
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($meeting['agenda'])): ?>
                                    <div class="list-group">
                                        <?php foreach ($meeting['agenda'] as $item): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <h5 class="mb-1">
                                                        <?php echo htmlspecialchars($item['order_number']); ?>. 
                                                        <?php echo htmlspecialchars($item['subject'] ?? ''); ?>
                                                    </h5>
                                                    <div>
                                                        <?php if (!empty($item['voting_result']) && $item['voting_result'] !== 'non_votato'): ?>
                                                            <span class="badge bg-<?php echo $item['voting_result'] == 'approvato' ? 'success' : 'danger'; ?>">
                                                                <?php echo htmlspecialchars(ucfirst($item['voting_result'])); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                                            <a href="meeting_agenda_edit.php?meeting_id=<?php echo $meeting['id']; ?>&agenda_id=<?php echo $item['id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary ms-2" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if (!empty($item['description'])): ?>
                                                    <p class="mb-2"><strong>Descrizione:</strong><br><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($item['discussion'])): ?>
                                                    <p class="mb-2"><strong>Discussione:</strong><br><?php echo nl2br(htmlspecialchars($item['discussion'])); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($item['has_voting']) && $item['has_voting']): ?>
                                                    <div class="mt-2 p-2 bg-light rounded">
                                                        <strong><i class="bi bi-pie-chart"></i> Votazione:</strong><br>
                                                        <small class="text-muted">
                                                            Votanti: <?php echo htmlspecialchars($item['voting_total'] ?? 0); ?> | 
                                                            Favorevoli: <?php echo htmlspecialchars($item['voting_in_favor'] ?? 0); ?> | 
                                                            Contrari: <?php echo htmlspecialchars($item['voting_against'] ?? 0); ?> | 
                                                            Astenuti: <?php echo htmlspecialchars($item['voting_abstentions'] ?? 0); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Nessun punto all'ordine del giorno.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Documenti -->
                    <div class="tab-pane fade" id="documenti" role="tabpanel">
                        <?php
                        $verbali = array_filter($meeting['attachments'] ?? [], fn($a) => $a['attachment_type'] === 'verbale');
                        $allegati = array_filter($meeting['attachments'] ?? [], fn($a) => $a['attachment_type'] === 'allegato');
                        $csrfToken = \EasyVol\Middleware\CsrfProtection::generateToken();
                        ?>

                        <!-- Verbale Firmato -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-file-earmark-check"></i> Verbale Firmato</h5>
                                    <?php if ($app->checkPermission('meetings', 'edit') && empty($verbali)): ?>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#uploadVerbaleModal">
                                            <i class="bi bi-upload"></i> Carica Verbale
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($verbali)): ?>
                                    <?php foreach ($verbali as $verbale): ?>
                                        <div class="d-flex align-items-center justify-content-between p-2 border rounded mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-file-earmark-pdf text-danger fs-4"></i>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($verbale['file_name']); ?></div>
                                                    <small class="text-muted">
                                                        Caricato il <?php echo date('d/m/Y H:i', strtotime($verbale['uploaded_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <a href="meeting_attachment_download.php?id=<?php echo $verbale['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Scarica">
                                                    <i class="bi bi-download"></i> Scarica
                                                </a>
                                                <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                                    <a href="meeting_attachment_delete.php?id=<?php echo $verbale['id']; ?>&meeting_id=<?php echo $meetingId; ?>&csrf_token=<?php echo urlencode($csrfToken); ?>"
                                                       class="btn btn-sm btn-outline-danger"
                                                       onclick="return confirm('Eliminare il verbale firmato?')" title="Elimina">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-bs-toggle="modal" data-bs-target="#uploadVerbaleModal">
                                            <i class="bi bi-upload"></i> Sostituisci Verbale
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-muted mb-0">Nessun verbale firmato caricato.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Allegati -->
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-files"></i> Allegati</h5>
                                    <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#uploadAllegatoModal">
                                            <i class="bi bi-plus-circle"></i> Aggiungi Allegato
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($allegati)): ?>
                                    <div class="list-group">
                                        <?php foreach ($allegati as $allegato): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <div class="d-flex align-items-start gap-2">
                                                        <span class="badge bg-secondary fs-6 mt-1"><?php echo intval($allegato['progressive_number']); ?></span>
                                                        <div>
                                                            <h6 class="mb-1">
                                                                <i class="bi bi-file-earmark-pdf text-danger"></i>
                                                                <?php echo htmlspecialchars($allegato['title'] ?? $allegato['file_name']); ?>
                                                            </h6>
                                                            <?php if (!empty($allegato['description'])): ?>
                                                                <p class="mb-1 text-muted small"><?php echo nl2br(htmlspecialchars($allegato['description'])); ?></p>
                                                            <?php endif; ?>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($allegato['file_name']); ?> &mdash;
                                                                Caricato il <?php echo date('d/m/Y H:i', strtotime($allegato['uploaded_at'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex gap-2 ms-2">
                                                        <a href="meeting_attachment_download.php?id=<?php echo $allegato['id']; ?>"
                                                           class="btn btn-sm btn-outline-primary" title="Scarica">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                        <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                                            <a href="meeting_attachment_delete.php?id=<?php echo $allegato['id']; ?>&meeting_id=<?php echo $meetingId; ?>&csrf_token=<?php echo urlencode($csrfToken); ?>"
                                                               class="btn btn-sm btn-outline-danger"
                                                               onclick="return confirm('Eliminare questo allegato?')" title="Elimina">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">Nessun allegato caricato.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Upload Verbale Firmato -->
    <div class="modal fade" id="uploadVerbaleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-check"></i> Carica Verbale Firmato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="meeting_attachment_upload.php" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="meeting_id" value="<?php echo $meetingId; ?>">
                        <input type="hidden" name="attachment_type" value="verbale">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <div class="mb-3">
                            <label for="verbale_pdf_file" class="form-label">File PDF <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="verbale_pdf_file" name="pdf_file" accept=".pdf,application/pdf" required>
                            <div class="form-text">Solo file PDF, max 20MB</div>
                        </div>

                        <div class="mb-3">
                            <label for="verbale_description" class="form-label">Note</label>
                            <textarea class="form-control" id="verbale_description" name="description" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-upload"></i> Carica
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Upload Allegato -->
    <div class="modal fade" id="uploadAllegatoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-files"></i> Aggiungi Allegato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="meeting_attachment_upload.php" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="meeting_id" value="<?php echo $meetingId; ?>">
                        <input type="hidden" name="attachment_type" value="allegato">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <div class="mb-3">
                            <label class="form-label">Numero Progressivo</label>
                            <input type="text" class="form-control" value="<?php echo $controller->getNextAttachmentNumber($meetingId); ?>" disabled>
                            <div class="form-text">Assegnato automaticamente</div>
                        </div>

                        <div class="mb-3">
                            <label for="allegato_title" class="form-label">Titolo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="allegato_title" name="title" maxlength="255" required>
                        </div>

                        <div class="mb-3">
                            <label for="allegato_description" class="form-label">Descrizione</label>
                            <textarea class="form-control" id="allegato_description" name="description" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="allegato_pdf_file" class="form-label">File PDF <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="allegato_pdf_file" name="pdf_file" accept=".pdf,application/pdf" required>
                            <div class="form-text">Solo file PDF, max 20MB</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-upload"></i> Carica
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Delega Partecipante -->
    <div class="modal fade" id="delegateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Inserisci Delega</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="delegate_participant_id">
                    <p>Delegante: <strong id="delegate_participant_name"></strong></p>
                    
                    <div class="mb-3 position-relative">
                        <label class="form-label">Delegato a</label>
                        <input type="text" class="form-control" id="delegate_search" 
                               placeholder="Digita nome, cognome o matricola..." 
                               autocomplete="off">
                        <input type="hidden" id="delegate_member_id">
                        <div id="delegate_search_results" class="list-group position-absolute" style="z-index: 1050; max-height: 300px; overflow-y: auto; display: none; width: 100%;"></div>
                        <small class="form-text text-muted">Inizia a digitare per cercare un socio o cadetto</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-warning" onclick="saveDelegation()">
                        <i class="bi bi-person-check"></i> Salva Delega
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Member data for delegate autocomplete
        const activeMembersData = <?php echo json_encode($activeMembers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS); ?>;
        const activeJuniorMembersData = <?php echo json_encode($activeJuniorMembers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS); ?>;
        let delegateSearchTimeout = null;
        // Print functionality
        function printById(templateId) {
            const url = 'print_preview.php?template_id=' + templateId + '&record_id=<?php echo $meeting['id']; ?>&entity=meetings';
            window.open(url, '_blank');
        }
        
        function showPrintModal() {
            const modal = new bootstrap.Modal(document.getElementById('printModal'));
            modal.show();
        }
        
        function generateFromModal() {
            const templateId = document.getElementById('templateSelect').value;
            if (templateId && templateId !== '') {
                printById(templateId);
                const modal = bootstrap.Modal.getInstance(document.getElementById('printModal'));
                modal.hide();
            }
        }
        
        // Update attendance status
        function updateAttendance(participantId, status) {
            const statusLabel = status === 'present' ? 'Presente' : 'Assente';
            if (!confirm('Confermi di voler segnare il partecipante come ' + statusLabel + '?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('participant_id', participantId);
            formData.append('status', status);
            
            fetch('meeting_update_attendance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Stato aggiornato con successo');
                    // Stay on the participants tab after reload
                    window.location.href = window.location.pathname + '?id=<?php echo $meetingId; ?>#participants';
                } else {
                    alert('Errore: ' + (data.message || 'Impossibile aggiornare lo stato'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante l\'aggiornamento dello stato');
            });
        }
        
        // Update participant role
        function updateRole(participantId, role) {
            const formData = new FormData();
            formData.append('participant_id', participantId);
            formData.append('role', role);
            
            fetch('meeting_update_role.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Errore: ' + (data.message || 'Impossibile aggiornare il ruolo'));
                    // Reload to revert
                    window.location.href = window.location.pathname + '?id=<?php echo $meetingId; ?>#participants';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante l\'aggiornamento del ruolo');
            });
        }
        
        // Show delegate modal
        function showDelegateModal(participantId, participantName) {
            document.getElementById('delegate_participant_id').value = participantId;
            document.getElementById('delegate_participant_name').textContent = participantName;
            document.getElementById('delegate_search').value = '';
            document.getElementById('delegate_member_id').value = '';
            document.getElementById('delegate_search_results').style.display = 'none';
            const modal = new bootstrap.Modal(document.getElementById('delegateModal'));
            modal.show();
        }
        
        // Delegate search autocomplete
        document.getElementById('delegate_search').addEventListener('input', function() {
            clearTimeout(delegateSearchTimeout);
            const search = this.value.trim();
            const resultsDiv = document.getElementById('delegate_search_results');
            
            if (search.length < 1) {
                resultsDiv.style.display = 'none';
                document.getElementById('delegate_member_id').value = '';
                return;
            }
            
            delegateSearchTimeout = setTimeout(function() {
                // Search in both adult and junior members
                const allMembers = activeMembersData.concat(activeJuniorMembersData);
                const filtered = allMembers.filter(function(m) {
                    const fullName = ((m.last_name || '') + ' ' + (m.first_name || '') + ' ' + (m.registration_number || '')).toLowerCase();
                    return fullName.includes(search.toLowerCase());
                });
                
                if (filtered.length === 0) {
                    resultsDiv.innerHTML = '<div class="list-group-item text-muted">Nessun socio trovato</div>';
                    resultsDiv.style.display = 'block';
                    return;
                }
                
                resultsDiv.innerHTML = filtered.slice(0, 20).map(function(member) {
                    const label = member.last_name + ' ' + member.first_name + ' (' + member.registration_number + ')';
                    return '<button type="button" class="list-group-item list-group-item-action" data-member-id="' + member.id + '" data-member-label="' + escapeHtml(label) + '">' +
                        escapeHtml(label) +
                        '</button>';
                }).join('');
                resultsDiv.style.display = 'block';
            }, 300);
        });
        
        // Event delegation for delegate selection
        document.getElementById('delegate_search_results').addEventListener('click', function(e) {
            if (e.target.classList.contains('list-group-item-action')) {
                const memberId = e.target.getAttribute('data-member-id');
                const memberLabel = e.target.getAttribute('data-member-label');
                document.getElementById('delegate_search').value = memberLabel;
                document.getElementById('delegate_member_id').value = memberId;
                document.getElementById('delegate_search_results').style.display = 'none';
            }
        });
        
        // Close delegate search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#delegate_search') && !e.target.closest('#delegate_search_results')) {
                document.getElementById('delegate_search_results').style.display = 'none';
            }
        });
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Save delegation
        function saveDelegation() {
            const participantId = document.getElementById('delegate_participant_id').value;
            const delegatedTo = document.getElementById('delegate_member_id').value;
            
            if (!delegatedTo) {
                alert('Seleziona un delegato dalla lista');
                return;
            }
            
            const formData = new FormData();
            formData.append('participant_id', participantId);
            formData.append('status', 'delegated');
            formData.append('delegated_to', delegatedTo);
            
            fetch('meeting_update_attendance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('delegateModal'));
                    modal.hide();
                    alert('Delega salvata con successo');
                    window.location.href = window.location.pathname + '?id=<?php echo $meetingId; ?>#participants';
                } else {
                    alert('Errore: ' + (data.message || 'Impossibile salvare la delega'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante il salvataggio della delega');
            });
        }
        
        // Bulk update attendance status
        function bulkUpdateAttendance(status) {
            const checkboxes = document.querySelectorAll('.participant-checkbox:checked');
            const participantIds = Array.from(checkboxes).map(cb => cb.value);
            
            if (participantIds.length === 0) {
                alert('Seleziona almeno un partecipante');
                return;
            }
            
            const statusLabel = status === 'present' ? 'Presenti' : 'Assenti';
            if (!confirm(`Confermi di voler segnare ${participantIds.length} partecipante(i) come ${statusLabel}?`)) {
                return;
            }
            
            // Send bulk update request
            const formData = new FormData();
            formData.append('participant_ids', JSON.stringify(participantIds));
            formData.append('status', status);
            
            fetch('meeting_update_attendance_bulk.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`${data.updated} partecipante(i) aggiornato(i) con successo`);
                    // Stay on the participants tab after reload
                    window.location.href = window.location.pathname + '?id=<?php echo $meetingId; ?>#participants';
                } else {
                    alert('Errore: ' + (data.message || 'Impossibile aggiornare lo stato'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante l\'aggiornamento dello stato');
            });
        }
        
        // Restore active tab from URL hash on page load
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash;
            if (hash === '#participants') {
                const participantsTab = new bootstrap.Tab(document.getElementById('participants-tab'));
                participantsTab.show();
            } else if (hash === '#agenda') {
                const agendaTab = new bootstrap.Tab(document.getElementById('agenda-tab'));
                agendaTab.show();
            } else if (hash === '#documenti') {
                const documentiTab = new bootstrap.Tab(document.getElementById('documenti-tab'));
                documentiTab.show();
            }
            
            // Handle checkbox selection
            const selectAllCheckbox = document.getElementById('select-all-participants');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.participant-checkbox');
                    checkboxes.forEach(cb => {
                        cb.checked = this.checked;
                    });
                    updateBulkActionButtons();
                });
            }
            
            // Add event listeners to participant checkboxes
            const participantCheckboxes = document.querySelectorAll('.participant-checkbox');
            participantCheckboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    updateBulkActionButtons();
                    
                    // Update select all checkbox state
                    const allChecked = Array.from(participantCheckboxes).every(checkbox => checkbox.checked);
                    const someChecked = Array.from(participantCheckboxes).some(checkbox => checkbox.checked);
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = allChecked;
                        selectAllCheckbox.indeterminate = someChecked && !allChecked;
                    }
                });
            });
        });
        
        // Update bulk action buttons state
        function updateBulkActionButtons() {
            const checkedCount = document.querySelectorAll('.participant-checkbox:checked').length;
            const bulkPresentBtn = document.getElementById('bulk-present-btn');
            const bulkAbsentBtn = document.getElementById('bulk-absent-btn');
            const selectedCount = document.getElementById('selected-count');
            
            if (bulkPresentBtn && bulkAbsentBtn && selectedCount) {
                bulkPresentBtn.disabled = checkedCount === 0;
                bulkAbsentBtn.disabled = checkedCount === 0;
                selectedCount.textContent = `${checkedCount} selezionati`;
            }
        }
    </script>

    <!-- Add Agenda Item Modal -->
    <div class="modal fade" id="addAgendaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Aggiungi Punto all'Ordine del Giorno</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addAgendaForm" method="POST" action="meeting_agenda_edit.php">
                    <div class="modal-body">
                        <input type="hidden" name="meeting_id" value="<?php echo $meetingId; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo \EasyVol\Middleware\CsrfProtection::generateToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Numero Ordine <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="order_number" 
                                   value="<?php echo intval(count($meeting['agenda'] ?? []) + 1); ?>" min="1" required>
                            <small class="text-muted">Numero progressivo del punto all'ordine del giorno</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Oggetto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrizione</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Aggiungi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Print Template Selection Modal -->
    <div class="modal fade" id="printModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Seleziona Template di Stampa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Template</label>
                        <select id="templateSelect" class="form-select">
                            <?php if (empty($printTemplates)): ?>
                                <option value="">Nessun template disponibile</option>
                            <?php else: ?>
                                <?php foreach ($printTemplates as $template): ?>
                                    <option value="<?php echo $template['id']; ?>">
                                        <?php echo htmlspecialchars($template['name']); ?>
                                        <?php if (isset($template['template_format']) && $template['template_format'] === 'xml'): ?>
                                            (XML)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" onclick="generateFromModal()" <?php echo empty($printTemplates) ? 'disabled' : ''; ?>>
                        <i class="bi bi-printer"></i> Genera
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
