<?php
/**
 * Gestione Ordine del Giorno - Modifica Singolo Punto
 * 
 * Pagina per modificare un singolo punto dell'ordine del giorno
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

if (!$app->checkPermission('meetings', 'edit')) {
    die('Accesso negato');
}

$meetingId = isset($_GET['meeting_id']) ? intval($_GET['meeting_id']) : (isset($_POST['meeting_id']) ? intval($_POST['meeting_id']) : 0);
$agendaId = isset($_GET['agenda_id']) ? intval($_GET['agenda_id']) : 0;

// For creation, we only need meetingId
// For editing, we need both meetingId and agendaId
$isCreating = ($agendaId === 0);

if (!$meetingId) {
    header('Location: meetings.php');
    exit;
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();

// Get meeting info
$meeting = $db->fetchOne("SELECT * FROM meetings WHERE id = ?", [$meetingId]);
if (!$meeting) {
    header('Location: meetings.php?error=not_found');
    exit;
}

// Get current agenda count for default order number
$agendaCount = $db->fetchOne("SELECT COUNT(*) as count FROM meeting_agenda WHERE meeting_id = ?", [$meetingId]);
$nextOrderNumber = ($agendaCount['count'] ?? 0) + 1;

// Get agenda item if editing
$agendaItem = null;
if (!$isCreating) {
    $agendaItem = $db->fetchOne("SELECT * FROM meeting_agenda WHERE id = ? AND meeting_id = ?", [$agendaId, $meetingId]);
    if (!$agendaItem) {
        header('Location: meeting_view.php?id=' . $meetingId . '&error=not_found');
        exit;
    }
}

$errors = [];
$success = false;

// Gestione submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'order_number' => intval($_POST['order_number'] ?? 1),
            'subject' => trim($_POST['subject'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'discussion' => trim($_POST['discussion'] ?? ''),
            'has_voting' => isset($_POST['has_voting']) ? 1 : 0,
            'voting_total' => intval($_POST['voting_total'] ?? 0),
            'voting_in_favor' => intval($_POST['voting_in_favor'] ?? 0),
            'voting_against' => intval($_POST['voting_against'] ?? 0),
            'voting_abstentions' => intval($_POST['voting_abstentions'] ?? 0),
            'voting_result' => $_POST['voting_result'] ?? 'non_votato'
        ];
        
        if (empty($data['subject'])) {
            $errors[] = 'L\'oggetto è obbligatorio';
        }
        
        if (empty($errors)) {
            try {
                if ($isCreating) {
                    // Create new agenda item
                    $db->query("INSERT INTO meeting_agenda 
                        (meeting_id, order_number, subject, description, discussion, has_voting, 
                         voting_total, voting_in_favor, voting_against, voting_abstentions, voting_result, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                        [$meetingId, $data['order_number'], $data['subject'], $data['description'], 
                         $data['discussion'], $data['has_voting'], $data['voting_total'], 
                         $data['voting_in_favor'], $data['voting_against'], $data['voting_abstentions'], 
                         $data['voting_result']]);
                } else {
                    // Update existing agenda item
                    $db->query("UPDATE meeting_agenda 
                        SET subject = ?, description = ?, discussion = ?, has_voting = ?, 
                            voting_total = ?, voting_in_favor = ?, voting_against = ?, 
                            voting_abstentions = ?, voting_result = ? 
                        WHERE id = ? AND meeting_id = ?",
                        [$data['subject'], $data['description'], $data['discussion'], $data['has_voting'], 
                         $data['voting_total'], $data['voting_in_favor'], $data['voting_against'], 
                         $data['voting_abstentions'], $data['voting_result'], $agendaId, $meetingId]);
                }
                
                $success = true;
                header('Location: meeting_view.php?id=' . $meetingId . '&success=1#agenda-tab');
                exit;
                
            } catch (\Exception $e) {
                $errors[] = 'Errore durante il salvataggio: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = $isCreating ? 'Aggiungi Punto all\'Ordine del Giorno' : 'Modifica Ordine del Giorno';
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
                        <a href="meeting_view.php?id=<?php echo $meetingId; ?>" class="text-decoration-none text-muted">
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
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-calendar3"></i> Riunione: 
                                    <?php 
                                    echo htmlspecialchars(MeetingController::MEETING_TYPE_NAMES[$meeting['meeting_type']] ?? ucfirst(str_replace('_', ' ', $meeting['meeting_type'])));
                                    echo ' - ' . date('d/m/Y', strtotime($meeting['meeting_date']));
                                    ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-0">
                                    <strong>Data:</strong> 
                                    <?php 
                                    echo date('d/m/Y', strtotime($meeting['meeting_date']));
                                    if (!empty($meeting['start_time'])) {
                                        echo ' ' . date('H:i', strtotime($meeting['start_time']));
                                    }
                                    ?> | 
                                    <strong>Tipo:</strong> <?php echo ucfirst(str_replace('_', ' ', $meeting['meeting_type'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                    <input type="hidden" name="meeting_id" value="<?php echo $meetingId; ?>">
                    
                    <div class="card mb-3">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="bi bi-list-ol"></i> 
                                <?php if ($isCreating): ?>
                                    Nuovo Punto all'Ordine del Giorno
                                <?php else: ?>
                                    Punto n. <?php echo htmlspecialchars($agendaItem['order_number']); ?>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($isCreating): ?>
                            <div class="mb-3">
                                <label class="form-label">Numero Ordine <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="order_number" 
                                       value="<?php echo $nextOrderNumber; ?>" min="1" required>
                                <small class="text-muted">Numero progressivo del punto all'ordine del giorno</small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Oggetto <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="subject" 
                                       value="<?php echo htmlspecialchars($agendaItem['subject'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Descrizione</label>
                                <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($agendaItem['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Discussione</label>
                                <textarea class="form-control" name="discussion" rows="5"><?php echo htmlspecialchars($agendaItem['discussion'] ?? ''); ?></textarea>
                                <small class="text-muted">Inserisci qui il resoconto della discussione avvenuta durante la riunione</small>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="has_voting" 
                                       id="has_voting" <?php echo ($agendaItem['has_voting'] ?? 0) ? 'checked' : ''; ?> 
                                       onchange="toggleVotingFields()">
                                <label class="form-check-label" for="has_voting">
                                    Votazione effettuata
                                </label>
                            </div>
                            
                            <div id="voting-fields" style="display: <?php echo ($agendaItem['has_voting'] ?? 0) ? 'block' : 'none'; ?>;">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Votanti</label>
                                        <input type="number" class="form-control" name="voting_total" 
                                               value="<?php echo htmlspecialchars($agendaItem['voting_total'] ?? 0); ?>" min="0">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Favorevoli</label>
                                        <input type="number" class="form-control" name="voting_in_favor" 
                                               value="<?php echo htmlspecialchars($agendaItem['voting_in_favor'] ?? 0); ?>" min="0">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Contrari</label>
                                        <input type="number" class="form-control" name="voting_against" 
                                               value="<?php echo htmlspecialchars($agendaItem['voting_against'] ?? 0); ?>" min="0">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Astenuti</label>
                                        <input type="number" class="form-control" name="voting_abstentions" 
                                               value="<?php echo htmlspecialchars($agendaItem['voting_abstentions'] ?? 0); ?>" min="0">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Esito</label>
                                    <select class="form-select" name="voting_result">
                                        <option value="non_votato" <?php echo ($agendaItem['voting_result'] ?? 'non_votato') === 'non_votato' ? 'selected' : ''; ?>>Non Votato</option>
                                        <option value="approvato" <?php echo ($agendaItem['voting_result'] ?? '') === 'approvato' ? 'selected' : ''; ?>>Approvato</option>
                                        <option value="respinto" <?php echo ($agendaItem['voting_result'] ?? '') === 'respinto' ? 'selected' : ''; ?>>Respinto</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <a href="meeting_view.php?id=<?php echo $meetingId; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Annulla
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salva Modifiche
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleVotingFields() {
        const checkbox = document.getElementById('has_voting');
        const votingFields = document.getElementById('voting-fields');
        votingFields.style.display = checkbox.checked ? 'block' : 'none';
    }
    </script>
</body>
</html>
