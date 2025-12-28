<?php
/**
 * Gestione Magazzino - Visualizzazione Dettaglio
 * 
 * Pagina per visualizzare i dettagli completi di un articolo di magazzino
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\WarehouseController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('warehouse', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($itemId <= 0) {
    header('Location: warehouse.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new WarehouseController($db, $config);

try {
    $item = $controller->get($itemId);
    
    if (!$item) {
        header('Location: warehouse.php?error=not_found');
        exit;
    }
} catch (\Exception $e) {
    error_log("Errore caricamento articolo magazzino: " . $e->getMessage());
    die('Errore durante il caricamento dei dati dell\'articolo');
}

$pageTitle = 'Dettaglio Articolo: ' . $item['name'];
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
                        <a href="warehouse.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if ($app->checkPermission('warehouse', 'edit')): ?>
                                <a href="warehouse_edit.php?id=<?php echo $item['id']; ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil"></i> Modifica
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3" id="itemTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="true">
                            <i class="bi bi-info-circle"></i> Informazioni Generali
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="movements-tab" data-bs-toggle="tab" data-bs-target="#movements" type="button" role="tab" aria-controls="movements" aria-selected="false">
                            <i class="bi bi-arrow-left-right"></i> Movimenti
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="dpi-tab" data-bs-toggle="tab" data-bs-target="#dpi" type="button" role="tab" aria-controls="dpi" aria-selected="false">
                            <i class="bi bi-person-badge"></i> DPI Assegnati
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="itemTabContent">
                    <!-- Tab Informazioni Generali -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0"><i class="bi bi-box-seam"></i> Dati Articolo</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="40%">Nome:</th>
                                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Codice:</th>
                                                <td><code><?php echo htmlspecialchars($item['code'] ?? '-'); ?></code></td>
                                            </tr>
                                            <tr>
                                                <th>Categoria:</th>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars(ucfirst($item['category'] ?? 'Altro')); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Descrizione:</th>
                                                <td><?php echo htmlspecialchars($item['description'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Unità di Misura:</th>
                                                <td><?php echo htmlspecialchars($item['unit'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Ubicazione:</th>
                                                <td><?php echo htmlspecialchars($item['location'] ?? '-'); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Giacenza</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="40%">Quantità Disponibile:</th>
                                                <td>
                                                    <strong class="<?php echo ($item['quantity'] ?? 0) <= ($item['minimum_quantity'] ?? 0) ? 'text-danger' : 'text-success'; ?>">
                                                        <?php echo htmlspecialchars($item['quantity'] ?? 0); ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Scorta Minima:</th>
                                                <td><?php echo htmlspecialchars($item['minimum_quantity'] ?? 0); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Stato:</th>
                                                <td>
                                                    <?php 
                                                    $status = $item['status'] ?? 'disponibile';
                                                    $statusClass = [
                                                        'disponibile' => 'success',
                                                        'esaurito' => 'danger',
                                                        'in_ordine' => 'warning'
                                                    ];
                                                    $class = $statusClass[$status] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>">
                                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php if (($item['quantity'] ?? 0) <= ($item['minimum_quantity'] ?? 0)): ?>
                                            <tr>
                                                <td colspan="2">
                                                    <div class="alert alert-warning mb-0">
                                                        <i class="bi bi-exclamation-triangle"></i>
                                                        <strong>Attenzione!</strong> Scorta sotto il livello minimo
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                </div>
                                
                                <?php if (!empty($item['notes'])): ?>
                                <div class="card mb-3">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> Note</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($item['notes'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Movimenti -->
                    <div class="tab-pane fade" id="movements" role="tabpanel" aria-labelledby="movements-tab">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-arrow-left-right"></i> Storico Movimenti</h5>
                                    <?php if ($app->checkPermission('warehouse', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addMovementModal">
                                            <i class="bi bi-plus-circle"></i> Nuovo Movimento
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($item['movements'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Data</th>
                                                    <th>Tipo</th>
                                                    <th>Quantità</th>
                                                    <th>Note</th>
                                                    <th>Utente</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($item['movements'] as $movement): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($movement['created_at']))); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $movement['movement_type'] == 'carico' ? 'success' : 'danger'; ?>">
                                                                <?php echo htmlspecialchars(ucfirst($movement['movement_type'])); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($movement['quantity']); ?></td>
                                                        <td><?php echo htmlspecialchars($movement['notes'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($movement['created_by_name'] ?? '-'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Nessun movimento registrato.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab DPI Assegnati -->
                    <div class="tab-pane fade" id="dpi" role="tabpanel" aria-labelledby="dpi-tab">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-person-badge"></i> DPI Assegnati ai Volontari</h5>
                                    <?php if ($app->checkPermission('warehouse', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#assignDpiModal">
                                            <i class="bi bi-plus-circle"></i> Assegna DPI
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($item['dpi_assignments'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Volontario</th>
                                                    <th>Quantità Assegnata</th>
                                                    <th>Data Assegnazione</th>
                                                    <th>Scadenza</th>
                                                    <th>Stato</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($item['dpi_assignments'] as $assignment): ?>
                                                    <tr>
                                                        <td><?php 
                                                            $memberName = trim(($assignment['first_name'] ?? '') . ' ' . ($assignment['last_name'] ?? ''));
                                                            echo htmlspecialchars($memberName ?: 'N/A'); 
                                                        ?></td>
                                                        <td><?php echo htmlspecialchars($assignment['quantity'] ?? 1); ?></td>
                                                        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($assignment['assignment_date'] ?? $assignment['assigned_date']))); ?></td>
                                                        <td>
                                                            <?php 
                                                            if (!empty($assignment['expiry_date'])) {
                                                                $date = new DateTime($assignment['expiry_date']);
                                                                $now = new DateTime();
                                                                $isExpired = $date < $now;
                                                                echo '<span class="badge bg-' . ($isExpired ? 'danger' : 'success') . '">' . 
                                                                     htmlspecialchars($date->format('d/m/Y')) . '</span>';
                                                            } else {
                                                                echo '-';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $assignment['status'] == 'assegnato' ? 'success' : 'secondary'; ?>">
                                                                <?php echo htmlspecialchars(ucfirst($assignment['status'])); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Nessun DPI assegnato.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal: Nuovo Movimento -->
                <div class="modal fade" id="addMovementModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Nuovo Movimento</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form id="movementForm">
                                <div class="modal-body">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo \EasyVol\Middleware\CsrfProtection::generateToken(); ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Tipo Movimento <span class="text-danger">*</span></label>
                                        <select class="form-select" name="movement_type" required>
                                            <option value="">Seleziona...</option>
                                            <option value="carico">Carico (in entrata)</option>
                                            <option value="scarico">Scarico (in uscita)</option>
                                            <option value="assegnazione">Assegnazione</option>
                                            <option value="restituzione">Restituzione</option>
                                            <option value="trasferimento">Trasferimento</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Quantità <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="quantity" min="1" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Socio/Volontario</label>
                                        <select class="form-select" name="member_id" id="memberSelect">
                                            <option value="">Nessuno</option>
                                            <?php
                                            $members = $db->fetchAll("SELECT id, first_name, last_name, registration_number FROM members WHERE status = 'attivo' ORDER BY last_name, first_name");
                                            foreach ($members as $member) {
                                                echo '<option value="' . $member['id'] . '">' . 
                                                     htmlspecialchars($member['last_name'] . ' ' . $member['first_name']) . 
                                                     ' (' . htmlspecialchars($member['registration_number']) . ')' .
                                                     '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Destinazione</label>
                                        <input type="text" class="form-control" name="destination" placeholder="es. Magazzino B, Sede operativa">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Note</label>
                                        <textarea class="form-control" name="notes" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                    <button type="submit" class="btn btn-primary">Salva Movimento</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Modal: Assegna DPI -->
                <div class="modal fade" id="assignDpiModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Assegna DPI a Volontario</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form id="dpiAssignmentForm">
                                <div class="modal-body">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo \EasyVol\Middleware\CsrfProtection::generateToken(); ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Socio/Volontario <span class="text-danger">*</span></label>
                                        <select class="form-select" name="member_id" required>
                                            <option value="">Seleziona un volontario...</option>
                                            <?php
                                            foreach ($members as $member) {
                                                echo '<option value="' . $member['id'] . '">' . 
                                                     htmlspecialchars($member['last_name'] . ' ' . $member['first_name']) . 
                                                     ' (' . htmlspecialchars($member['registration_number']) . ')' .
                                                     '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Quantità <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="quantity" min="1" value="1" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Data Assegnazione <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="assignment_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Data Scadenza</label>
                                        <input type="date" class="form-control" name="expiry_date">
                                        <small class="text-muted">Opzionale - per DPI con scadenza</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Note</label>
                                        <textarea class="form-control" name="notes" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                    <button type="submit" class="btn btn-primary">Assegna DPI</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const itemId = <?php echo $item['id']; ?>;
        
        // Handle movement form submission
        document.getElementById('movementForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_movement');
            
            try {
                const response = await fetch('warehouse_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Errore: ' + result.message);
                }
            } catch (error) {
                alert('Errore durante il salvataggio: ' + error.message);
            }
        });
        
        // Handle DPI assignment form submission
        document.getElementById('dpiAssignmentForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'assign_dpi');
            
            try {
                const response = await fetch('warehouse_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Errore: ' + result.message);
                }
            } catch (error) {
                alert('Errore durante l\'assegnazione: ' + error.message);
            }
        });
    </script>
</body>
</html>
