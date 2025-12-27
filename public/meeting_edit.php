<?php
/**
 * Gestione Riunioni - Modifica/Crea
 * 
 * Pagina per creare o modificare una riunione/assemblea
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\MeetingController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$meetingId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $meetingId > 0;

// Verifica permessi
if ($isEdit && !$app->checkPermission('meetings', 'edit')) {
    die('Accesso negato');
}
if (!$isEdit && !$app->checkPermission('meetings', 'create')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new MeetingController($db, $config);

$meeting = [];
$errors = [];
$success = false;

// Se in modifica, carica dati esistenti
if ($isEdit) {
    $meeting = $controller->get($meetingId);
    if (!$meeting) {
        header('Location: meetings.php?error=not_found');
        exit;
    }
    // Load existing agenda items
    $agendaItems = $db->fetchAll("SELECT * FROM meeting_agenda WHERE meeting_id = ? ORDER BY order_number", [$meetingId]);
    // Load existing participants
    $participants = $db->fetchAll("SELECT * FROM meeting_participants WHERE meeting_id = ?", [$meetingId]);
} else {
    $agendaItems = [];
    $participants = [];
}

// Get all active members for participant selection
$activeMembers = $db->fetchAll("SELECT id, first_name, last_name, registration_number FROM members WHERE member_status = 'attivo' ORDER BY last_name, first_name");
$activeJuniorMembers = $db->fetchAll("SELECT id, first_name, last_name, registration_number FROM junior_members WHERE member_status = 'attivo' ORDER BY last_name, first_name");

// Parse convocator field to extract member_id and role if it exists
$convocatorMemberId = null;
$convocatorRole = '';
if ($isEdit && !empty($meeting['convocator'])) {
    // Try to parse format: "member_id|role" (we'll store it in this format)
    if (strpos($meeting['convocator'], '|') !== false) {
        list($convocatorMemberId, $convocatorRole) = explode('|', $meeting['convocator'], 2);
    }
}

// Gestione submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        // Build convocator field from member_id and role
        $convocatorMemberId = trim($_POST['convocator_member_id'] ?? '');
        $convocatorRole = trim($_POST['convocator_role'] ?? '');
        $convocatorValue = '';
        if (!empty($convocatorMemberId) && !empty($convocatorRole)) {
            $convocatorValue = $convocatorMemberId . '|' . $convocatorRole;
        }
        
        $data = [
            'meeting_type' => $_POST['meeting_type'] ?? 'consiglio_direttivo',
            'title' => trim($_POST['title'] ?? ''),
            'meeting_date' => $_POST['meeting_date'] ?? '',
            'start_time' => $_POST['start_time'] ?? '',
            'end_time' => $_POST['end_time'] ?? '',
            'location' => trim($_POST['location'] ?? ''),
            'convocator' => $convocatorValue,
            'description' => trim($_POST['description'] ?? ''),
            'notes' => trim($_POST['notes'] ?? '')
        ];
        
        try {
            $db->beginTransaction();
            
            if ($isEdit) {
                // Update meeting
                $db->query("UPDATE meetings SET meeting_type = ?, title = ?, meeting_date = ?, start_time = ?, end_time = ?, location = ?, convocator = ?, description = ?, updated_at = NOW() WHERE id = ?",
                    [$data['meeting_type'], $data['title'], $data['meeting_date'], $data['start_time'], $data['end_time'], $data['location'], $data['convocator'], $data['description'], $meetingId]);
            } else {
                // Create new meeting (location_type defaults to 'fisico' for physical meetings)
                $locationType = 'fisico'; // Physical meeting default
                $db->query("INSERT INTO meetings (meeting_type, title, meeting_date, start_time, end_time, location, convocator, description, location_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$data['meeting_type'], $data['title'], $data['meeting_date'], $data['start_time'], $data['end_time'], $data['location'], $data['convocator'], $data['description'], $locationType]);
                $meetingId = $db->lastInsertId();
            }
            
            // Save participants
            if (!empty($_POST['participants'])) {
                // Delete existing participants
                $db->query("DELETE FROM meeting_participants WHERE meeting_id = ?", [$meetingId]);
                
                // Validate that there's only one Presidente and one Segretario
                $presidenteCount = 0;
                $segretarioCount = 0;
                foreach ($_POST['participants'] as $participant) {
                    $role = $participant['role'] ?? null;
                    if ($role === 'Presidente') $presidenteCount++;
                    if ($role === 'Segretario') $segretarioCount++;
                }
                
                if ($presidenteCount > 1) {
                    throw new \Exception('È possibile assegnare solo un Presidente per riunione');
                }
                if ($segretarioCount > 1) {
                    throw new \Exception('È possibile assegnare solo un Segretario per riunione');
                }
                
                foreach ($_POST['participants'] as $participant) {
                    $memberType = $participant['type'] ?? 'adult';
                    $memberId = $participant['id'] ?? null;
                    $role = $participant['role'] ?? null;
                    
                    if ($memberId) {
                        if ($memberType === 'adult') {
                            $db->query("INSERT INTO meeting_participants (meeting_id, member_id, member_type, role, present) VALUES (?, ?, 'adult', ?, 0)",
                                [$meetingId, $memberId, $role]);
                        } else {
                            $db->query("INSERT INTO meeting_participants (meeting_id, junior_member_id, member_type, role, present) VALUES (?, ?, 'junior', ?, 0)",
                                [$meetingId, $memberId, $role]);
                        }
                    }
                }
            }
            
            // Save agenda items
            if (!empty($_POST['agenda_items'])) {
                // Delete existing agenda items
                $db->query("DELETE FROM meeting_agenda WHERE meeting_id = ?", [$meetingId]);
                
                foreach ($_POST['agenda_items'] as $index => $item) {
                    $hasVoting = isset($item['has_voting']) ? 1 : 0;
                    $votingTotal = intval($item['voting_total'] ?? 0);
                    $votingInFavor = intval($item['voting_in_favor'] ?? 0);
                    $votingAgainst = intval($item['voting_against'] ?? 0);
                    $votingAbstentions = intval($item['voting_abstentions'] ?? 0);
                    $votingResult = $item['voting_result'] ?? 'non_votato';
                    
                    $db->query("INSERT INTO meeting_agenda (meeting_id, order_number, subject, description, discussion, has_voting, voting_total, voting_in_favor, voting_against, voting_abstentions, voting_result) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$meetingId, ($index + 1), trim($item['subject'] ?? ''), trim($item['description'] ?? ''), trim($item['discussion'] ?? ''), $hasVoting, $votingTotal, $votingInFavor, $votingAgainst, $votingAbstentions, $votingResult]);
                }
            }
            
            $db->commit();
            $success = true;
            header('Location: meeting_view.php?id=' . $meetingId . '&success=1');
            exit;
            
        } catch (\Exception $e) {
            $db->rollBack();
            $errors[] = 'Errore durante il salvataggio: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Modifica Riunione' : 'Nuova Riunione';
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
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                    
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-calendar3"></i> Dati Riunione</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="title" class="form-label">Titolo Riunione <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($meeting['title'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="meeting_type" class="form-label">Tipo <span class="text-danger">*</span></label>
                                    <select class="form-select" id="meeting_type" name="meeting_type" required>
                                        <option value="assemblea_ordinaria" <?php echo ($meeting['meeting_type'] ?? '') === 'assemblea_ordinaria' ? 'selected' : ''; ?>>Assemblea Ordinaria</option>
                                        <option value="assemblea_straordinaria" <?php echo ($meeting['meeting_type'] ?? '') === 'assemblea_straordinaria' ? 'selected' : ''; ?>>Assemblea Straordinaria</option>
                                        <option value="consiglio_direttivo" <?php echo ($meeting['meeting_type'] ?? 'consiglio_direttivo') === 'consiglio_direttivo' ? 'selected' : ''; ?>>Consiglio Direttivo</option>
                                        <option value="altro" <?php echo ($meeting['meeting_type'] ?? '') === 'altro' ? 'selected' : ''; ?>>Altro</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="meeting_date" class="form-label">Data <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="meeting_date" name="meeting_date" 
                                           value="<?php echo !empty($meeting['meeting_date']) ? date('Y-m-d', strtotime($meeting['meeting_date'])) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="start_time" class="form-label">Ora Inizio</label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" 
                                           value="<?php echo htmlspecialchars($meeting['start_time'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="end_time" class="form-label">Ora Fine</label>
                                    <input type="time" class="form-control" id="end_time" name="end_time" 
                                           value="<?php echo htmlspecialchars($meeting['end_time'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label">Luogo</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($meeting['location'] ?? ''); ?>"
                                           placeholder="es. Sede Associazione">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="convocator_member" class="form-label">Convocata da - Socio</label>
                                    <select class="form-select" id="convocator_member" name="convocator_member_id">
                                        <option value="">Seleziona socio...</option>
                                        <?php foreach ($activeMembers as $member): ?>
                                            <option value="<?= $member['id'] ?>" <?= ($convocatorMemberId == $member['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($member['last_name'] . ' ' . $member['first_name'] . ' (' . $member['registration_number'] . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="convocator_role" class="form-label">Convocata da - Ruolo</label>
                                    <select class="form-select" id="convocator_role" name="convocator_role">
                                        <option value="">Seleziona ruolo...</option>
                                        <option value="Presidente" <?= ($convocatorRole === 'Presidente') ? 'selected' : '' ?>>Presidente</option>
                                        <option value="Vice Presidente" <?= ($convocatorRole === 'Vice Presidente') ? 'selected' : '' ?>>Vice Presidente</option>
                                        <option value="Segretario" <?= ($convocatorRole === 'Segretario') ? 'selected' : '' ?>>Segretario</option>
                                        <option value="Tesoriere" <?= ($convocatorRole === 'Tesoriere') ? 'selected' : '' ?>>Tesoriere</option>
                                        <option value="Consigliere" <?= ($convocatorRole === 'Consigliere') ? 'selected' : '' ?>>Consigliere</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descrizione</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($meeting['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-people"></i> Partecipanti</h5>
                        </div>
                        <div class="card-body">
                            <div id="participants-container">
                                <?php if (!empty($participants)): ?>
                                    <?php foreach ($participants as $index => $participant): ?>
                                        <div class="row mb-2 participant-row">
                                            <div class="col-md-4">
                                                <select class="form-select" name="participants[<?= $index ?>][type]" onchange="updateParticipantOptions(this)">
                                                    <option value="adult" <?= ($participant['member_type'] ?? 'adult') === 'adult' ? 'selected' : '' ?>>Socio Maggiorenne</option>
                                                    <option value="junior" <?= ($participant['member_type'] ?? '') === 'junior' ? 'selected' : '' ?>>Socio Minorenne (Cadetto)</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <select class="form-select participant-select" name="participants[<?= $index ?>][id]">
                                                    <option value="">Seleziona...</option>
                                                    <?php if (($participant['member_type'] ?? 'adult') === 'adult'): ?>
                                                        <?php foreach ($activeMembers as $member): ?>
                                                            <option value="<?= $member['id'] ?>" <?= ($participant['member_id'] ?? 0) == $member['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($member['last_name'] . ' ' . $member['first_name'] . ' (' . $member['registration_number'] . ')') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <?php foreach ($activeJuniorMembers as $member): ?>
                                                            <option value="<?= $member['id'] ?>" <?= ($participant['junior_member_id'] ?? 0) == $member['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($member['last_name'] . ' ' . $member['first_name'] . ' (' . $member['registration_number'] . ')') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <select class="form-select" name="participants[<?= $index ?>][role]">
                                                    <option value="">Nessun ruolo</option>
                                                    <option value="Presidente" <?= ($participant['role'] ?? '') === 'Presidente' ? 'selected' : '' ?>>Presidente</option>
                                                    <option value="Segretario" <?= ($participant['role'] ?? '') === 'Segretario' ? 'selected' : '' ?>>Segretario</option>
                                                    <option value="Uditore" <?= ($participant['role'] ?? '') === 'Uditore' ? 'selected' : '' ?>>Uditore</option>
                                                    <option value="Scrutatore" <?= ($participant['role'] ?? '') === 'Scrutatore' ? 'selected' : '' ?>>Scrutatore</option>
                                                    <option value="Presidente del Seggio Elettorale" <?= ($participant['role'] ?? '') === 'Presidente del Seggio Elettorale' ? 'selected' : '' ?>>Presidente del Seggio Elettorale</option>
                                                </select>
                                            </div>
                                            <div class="col-md-1">
                                                <button type="button" class="btn btn-danger btn-sm" onclick="removeParticipant(this)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" onclick="addParticipant()">
                                <i class="bi bi-plus-circle"></i> Aggiungi Partecipante
                            </button>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-list-ol"></i> Ordini del Giorno</h5>
                        </div>
                        <div class="card-body">
                            <div id="agenda-container">
                                <?php if (!empty($agendaItems)): ?>
                                    <?php foreach ($agendaItems as $index => $item): ?>
                                        <div class="card mb-3 agenda-item">
                                            <div class="card-header bg-light">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <strong>Ordine del Giorno #<?= $index + 1 ?></strong>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeAgendaItem(this)">
                                                        <i class="bi bi-trash"></i> Rimuovi
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Oggetto <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="agenda_items[<?= $index ?>][subject]" 
                                                           value="<?= htmlspecialchars($item['subject'] ?? '') ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Descrizione</label>
                                                    <textarea class="form-control" name="agenda_items[<?= $index ?>][description]" rows="2"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Discussione</label>
                                                    <textarea class="form-control" name="agenda_items[<?= $index ?>][discussion]" rows="3"><?= htmlspecialchars($item['discussion'] ?? '') ?></textarea>
                                                </div>
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="checkbox" name="agenda_items[<?= $index ?>][has_voting]" 
                                                           id="voting_<?= $index ?>" <?= ($item['has_voting'] ?? 0) ? 'checked' : '' ?> 
                                                           onchange="toggleVotingFields(this)">
                                                    <label class="form-check-label" for="voting_<?= $index ?>">
                                                        Votazione effettuata
                                                    </label>
                                                </div>
                                                <div class="voting-fields" style="display: <?= ($item['has_voting'] ?? 0) ? 'block' : 'none' ?>;">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <label class="form-label">Votanti</label>
                                                            <input type="number" class="form-control" name="agenda_items[<?= $index ?>][voting_total]" 
                                                                   value="<?= htmlspecialchars($item['voting_total'] ?? 0) ?>" min="0">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Favorevoli</label>
                                                            <input type="number" class="form-control" name="agenda_items[<?= $index ?>][voting_in_favor]" 
                                                                   value="<?= htmlspecialchars($item['voting_in_favor'] ?? 0) ?>" min="0">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Contrari</label>
                                                            <input type="number" class="form-control" name="agenda_items[<?= $index ?>][voting_against]" 
                                                                   value="<?= htmlspecialchars($item['voting_against'] ?? 0) ?>" min="0">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Astenuti</label>
                                                            <input type="number" class="form-control" name="agenda_items[<?= $index ?>][voting_abstentions]" 
                                                                   value="<?= htmlspecialchars($item['voting_abstentions'] ?? 0) ?>" min="0">
                                                        </div>
                                                    </div>
                                                    <div class="mt-3">
                                                        <label class="form-label">Esito</label>
                                                        <select class="form-select" name="agenda_items[<?= $index ?>][voting_result]">
                                                            <option value="non_votato" <?= ($item['voting_result'] ?? 'non_votato') === 'non_votato' ? 'selected' : '' ?>>Non Votato</option>
                                                            <option value="approvato" <?= ($item['voting_result'] ?? '') === 'approvato' ? 'selected' : '' ?>>Approvato</option>
                                                            <option value="respinto" <?= ($item['voting_result'] ?? '') === 'respinto' ? 'selected' : '' ?>>Respinto</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" onclick="addAgendaItem()">
                                <i class="bi bi-plus-circle"></i> Aggiungi Ordine del Giorno
                            </button>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> Note</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($meeting['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <a href="meetings.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Annulla
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salva
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let participantIndex = <?= count($participants) ?>;
    let agendaIndex = <?= count($agendaItems) ?>;
    
    const activeMembersData = <?= json_encode($activeMembers) ?>;
    const activeJuniorMembersData = <?= json_encode($activeJuniorMembers) ?>;
    
    function addParticipant() {
        const container = document.getElementById('participants-container');
        const index = participantIndex++;
        
        const html = `
            <div class="row mb-2 participant-row">
                <div class="col-md-4">
                    <select class="form-select" name="participants[${index}][type]" onchange="updateParticipantOptions(this)">
                        <option value="adult">Socio Maggiorenne</option>
                        <option value="junior">Socio Minorenne (Cadetto)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select participant-select" name="participants[${index}][id]">
                        <option value="">Seleziona...</option>
                        ${activeMembersData.map(m => `<option value="${m.id}">${m.last_name} ${m.first_name} (${m.registration_number})</option>`).join('')}
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="participants[${index}][role]">
                        <option value="">Nessun ruolo</option>
                        <option value="Presidente">Presidente</option>
                        <option value="Segretario">Segretario</option>
                        <option value="Uditore">Uditore</option>
                        <option value="Scrutatore">Scrutatore</option>
                        <option value="Presidente del Seggio Elettorale">Presidente del Seggio Elettorale</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeParticipant(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', html);
    }
    
    function removeParticipant(button) {
        button.closest('.participant-row').remove();
    }
    
    function updateParticipantOptions(select) {
        const row = select.closest('.participant-row');
        const memberSelect = row.querySelector('.participant-select');
        const type = select.value;
        
        memberSelect.innerHTML = '<option value="">Seleziona...</option>';
        
        const members = type === 'adult' ? activeMembersData : activeJuniorMembersData;
        members.forEach(m => {
            const option = document.createElement('option');
            option.value = m.id;
            option.textContent = `${m.last_name} ${m.first_name} (${m.registration_number})`;
            memberSelect.appendChild(option);
        });
    }
    
    function addAgendaItem() {
        const container = document.getElementById('agenda-container');
        const index = agendaIndex++;
        
        const html = `
            <div class="card mb-3 agenda-item">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong>Ordine del Giorno #${index + 1}</strong>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeAgendaItem(this)">
                            <i class="bi bi-trash"></i> Rimuovi
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Oggetto <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="agenda_items[${index}][subject]" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrizione</label>
                        <textarea class="form-control" name="agenda_items[${index}][description]" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Discussione</label>
                        <textarea class="form-control" name="agenda_items[${index}][discussion]" rows="3"></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="agenda_items[${index}][has_voting]" 
                               id="voting_${index}" onchange="toggleVotingFields(this)">
                        <label class="form-check-label" for="voting_${index}">
                            Votazione effettuata
                        </label>
                    </div>
                    <div class="voting-fields" style="display: none;">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Votanti</label>
                                <input type="number" class="form-control" name="agenda_items[${index}][voting_total]" value="0" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Favorevoli</label>
                                <input type="number" class="form-control" name="agenda_items[${index}][voting_in_favor]" value="0" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Contrari</label>
                                <input type="number" class="form-control" name="agenda_items[${index}][voting_against]" value="0" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Astenuti</label>
                                <input type="number" class="form-control" name="agenda_items[${index}][voting_abstentions]" value="0" min="0">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Esito</label>
                            <select class="form-select" name="agenda_items[${index}][voting_result]">
                                <option value="non_votato" selected>Non Votato</option>
                                <option value="approvato">Approvato</option>
                                <option value="respinto">Respinto</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', html);
    }
    
    function removeAgendaItem(button) {
        button.closest('.agenda-item').remove();
        // Renumber remaining items
        document.querySelectorAll('.agenda-item').forEach((item, index) => {
            item.querySelector('.card-header strong').textContent = `Ordine del Giorno #${index + 1}`;
        });
    }
    
    function toggleVotingFields(checkbox) {
        const card = checkbox.closest('.card-body');
        const votingFields = card.querySelector('.voting-fields');
        votingFields.style.display = checkbox.checked ? 'block' : 'none';
    }
    </script>
</body>
</html>
