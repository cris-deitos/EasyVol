<?php
/**
 * Gestione Eventi - Lista
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\EventController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('events', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new EventController($db, $config);

$filters = [
    'type' => $_GET['type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

$events = $controller->index($filters, $page, $perPage);

// Conteggi per status
$statusCounts = [
    'in_corso' => $db->fetchOne("SELECT COUNT(*) as count FROM events WHERE status = 'in_corso'")['count'] ?? 0,
    'concluso' => $db->fetchOne("SELECT COUNT(*) as count FROM events WHERE status = 'concluso'")['count'] ?? 0,
];

$pageTitle = 'Gestione Eventi e Interventi';
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
                    <h1 class="h2"><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($app->checkPermission('events', 'create')): ?>
                            <a href="event_edit.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuovo Evento
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistiche Rapide -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">In Corso</h5>
                                <h2><?php echo number_format($statusCounts['in_corso']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Conclusi</h5>
                                <h2><?php echo number_format($statusCounts['concluso']); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Titolo, descrizione, luogo...">
                            </div>
                            <div class="col-md-3">
                                <label for="type" class="form-label">Tipo</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">Tutti</option>
                                    <option value="emergenza" <?php echo $filters['type'] === 'emergenza' ? 'selected' : ''; ?>>Emergenza</option>
                                    <option value="esercitazione" <?php echo $filters['type'] === 'esercitazione' ? 'selected' : ''; ?>>Esercitazione</option>
                                    <option value="attivita" <?php echo $filters['type'] === 'attivita' ? 'selected' : ''; ?>>Attività</option>
                                    <option value="servizio" <?php echo $filters['type'] === 'servizio' ? 'selected' : ''; ?>>Servizio</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="in_corso" <?php echo $filters['status'] === 'in_corso' ? 'selected' : ''; ?>>In Corso</option>
                                    <option value="concluso" <?php echo $filters['status'] === 'concluso' ? 'selected' : ''; ?>>Concluso</option>
                                    <option value="annullato" <?php echo $filters['status'] === 'annullato' ? 'selected' : ''; ?>>Annullato</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Cerca
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabella Eventi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Eventi</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Titolo</th>
                                        <th>Data Inizio</th>
                                        <th>Data Fine</th>
                                        <th>Luogo</th>
                                        <th>Benefici</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($events)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                Nessun evento trovato
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($events as $event): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    $typeIcons = [
                                                        'emergenza' => 'exclamation-triangle-fill text-danger',
                                                        'esercitazione' => 'shield-check text-primary',
                                                        'attivita' => 'calendar-event text-success',
                                                        'servizio' => 'briefcase text-info'
                                                    ];
                                                    $icon = $typeIcons[$event['event_type']] ?? 'calendar';
                                                    ?>
                                                    <i class="bi bi-<?php echo $icon; ?>"></i>
                                                    <?php echo ucfirst($event['event_type']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($event['start_date'])); ?></td>
                                                <td>
                                                    <?php 
                                                    echo $event['end_date'] ? 
                                                        date('d/m/Y H:i', strtotime($event['end_date'])) : '-'; 
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($event['location'] ?? '-'); ?></td>
                                                <td>
                                                    <?php
                                                    $benefitsValue = $event['legal_benefits_recognized'] ?? 'no';
                                                    $benefitsClass = $benefitsValue === 'si' ? 'success' : 'secondary';
                                                    $benefitsLabel = $benefitsValue === 'si' ? 'SI' : 'NO';
                                                    ?>
                                                    <span class="badge bg-<?php echo $benefitsClass; ?>" title="Art. 39 e 40 D. Lgs. n. 1 del 2018">
                                                        <?php echo htmlspecialchars($benefitsLabel); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusColors = [
                                                        'in_corso' => 'warning',
                                                        'concluso' => 'success',
                                                        'annullato' => 'danger'
                                                    ];
                                                    $color = $statusColors[$event['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $event['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="event_view.php?id=<?php echo $event['id']; ?>" 
                                                           class="btn btn-sm btn-info" title="Visualizza">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($app->checkPermission('events', 'edit')): ?>
                                                            <a href="event_edit.php?id=<?php echo $event['id']; ?>" 
                                                               class="btn btn-sm btn-warning" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <?php if ($event['status'] !== 'concluso' && $event['status'] !== 'annullato'): ?>
                                                                <button type="button" class="btn btn-sm btn-success" 
                                                                        onclick="openQuickCloseModal(<?php echo $event['id']; ?>, <?php echo htmlspecialchars(json_encode($event['title']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($event['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)"
                                                                        title="Chiusura Rapida">
                                                                    <i class="bi bi-check-circle"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Quick Close Event Modal -->
    <div class="modal fade" id="quickCloseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Chiusura Rapida Evento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="quick_close_event_id">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong id="quick_close_event_title"></strong>
                    </div>
                    <div class="mb-3">
                        <label for="quick_close_description" class="form-label">Descrizione Evento</label>
                        <textarea class="form-control" id="quick_close_description" rows="6" 
                                  placeholder="Aggiungi o modifica la descrizione dell'evento..."></textarea>
                        <small class="form-text text-muted">Puoi integrare la descrizione esistente con ulteriori dettagli</small>
                    </div>
                    <div class="mb-3">
                        <label for="quick_close_end_date" class="form-label">Data e Ora Chiusura <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="quick_close_end_date" required>
                        <small class="form-text text-muted">Impostata automaticamente all'ora corrente, modificabile se necessario</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-success" onclick="confirmQuickClose()">
                        <i class="bi bi-check-circle"></i> Chiudi Evento
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const csrfToken = '<?php echo CsrfProtection::generateToken(); ?>';
        
        function formatDateTimeLocal(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }
        
        function openQuickCloseModal(eventId, eventTitle, eventDescription) {
            document.getElementById('quick_close_event_id').value = eventId;
            document.getElementById('quick_close_event_title').textContent = eventTitle;
            document.getElementById('quick_close_description').value = eventDescription || '';
            
            // Set current date/time as default
            const now = new Date();
            document.getElementById('quick_close_end_date').value = formatDateTimeLocal(now);
            
            const modal = new bootstrap.Modal(document.getElementById('quickCloseModal'));
            modal.show();
        }
        
        function confirmQuickClose() {
            const eventId = document.getElementById('quick_close_event_id').value;
            const description = document.getElementById('quick_close_description').value.trim();
            const endDate = document.getElementById('quick_close_end_date').value;
            
            if (!endDate) {
                alert('La data di chiusura è obbligatoria');
                return;
            }
            
            fetch('event_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'quick_close_event',
                    event_id: eventId,
                    description: description,
                    end_date: endDate,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Evento chiuso con successo');
                    window.location.reload();
                } else {
                    alert('Errore: ' + (data.error || 'Errore durante la chiusura dell\'evento'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante la chiusura dell\'evento');
            });
        }
    </script>
</body>
</html>
