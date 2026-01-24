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
use EasyVol\Utils\WarehouseCategories;
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
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                            <i class="bi bi-info-circle"></i> Informazioni Generali
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="movements-tab" data-bs-toggle="tab" data-bs-target="#movements" type="button" role="tab">
                            <i class="bi bi-arrow-left-right"></i> Movimenti
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="dpi-tab" data-bs-toggle="tab" data-bs-target="#dpi" type="button" role="tab">
                            <i class="bi bi-person-badge"></i> DPI Assegnati
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="itemTabContent">
                    <!-- Tab Informazioni Generali -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel">
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
                                                        <?php echo htmlspecialchars(WarehouseCategories::getLabel($item['category'] ?? null)); ?>
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
                    <div class="tab-pane fade" id="movements" role="tabpanel">
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
                                                    <th>Volontario</th>
                                                    <th>Azioni</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($item['movements'] as $movement): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($movement['created_at']))); ?></td>
                                                        <td>
                                                            <?php
                                                            $typeColors = [
                                                                'carico' => 'success',
                                                                'scarico' => 'danger',
                                                                'assegnazione' => 'info',
                                                                'restituzione' => 'warning',
                                                                'trasferimento' => 'secondary'
                                                            ];
                                                            $color = $typeColors[$movement['movement_type']] ?? 'secondary';
                                                            ?>
                                                            <span class="badge bg-<?php echo $color; ?>">
                                                                <?php echo htmlspecialchars(ucfirst($movement['movement_type'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $sign = in_array($movement['movement_type'], ['carico', 'restituzione']) ? '+' : '-';
                                                            $class = $sign === '+' ? 'text-success' : 'text-danger';
                                                            ?>
                                                            <strong class="<?php echo $class; ?>">
                                                                <?php echo $sign . htmlspecialchars($movement['quantity']); ?>
                                                            </strong>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($movement['member_name'] ?? '-'); ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-info movement-detail-btn" 
                                                                    data-movement='<?php echo htmlspecialchars(json_encode($movement), ENT_QUOTES); ?>'>
                                                                <i class="bi bi-eye"></i> Dettagli
                                                            </button>
                                                        </td>
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
                    <div class="tab-pane fade" id="dpi" role="tabpanel">
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
                                                    <th>Azioni</th>
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
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <button type="button" class="btn btn-sm btn-info dpi-detail-btn" 
                                                                        data-assignment='<?php echo htmlspecialchars(json_encode($assignment), ENT_QUOTES); ?>'>
                                                                    <i class="bi bi-eye"></i> Dettagli
                                                                </button>
                                                                <?php if ($assignment['status'] == 'assegnato' && $app->checkPermission('warehouse', 'edit')): ?>
                                                                    <button type="button" class="btn btn-sm btn-warning dpi-return-btn" 
                                                                            data-assignment-id="<?php echo $assignment['id']; ?>"
                                                                            data-member-name="<?php echo htmlspecialchars($memberName, ENT_QUOTES); ?>">
                                                                        <i class="bi bi-arrow-return-left"></i> Restituzione
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
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
                                        <label class="form-label" id="memberSearchLabel">Socio/Volontario</label>
                                        <input type="hidden" name="member_id" id="memberIdInput">
                                        <input type="text" class="form-control" id="memberSearch" 
                                               placeholder="Cerca per matricola, nome o cognome..." 
                                               autocomplete="off"
                                               aria-label="Cerca socio o volontario"
                                               aria-describedby="memberSearchHelp"
                                               aria-expanded="false"
                                               aria-controls="memberSearchResults"
                                               role="combobox">
                                        <div id="memberSearchResults" class="list-group position-absolute" 
                                             style="z-index: 1000; max-height: 300px; overflow-y: auto; display: none;"
                                             role="listbox"></div>
                                        <small class="text-muted" id="memberSearchHelp">Opzionale - lascia vuoto se non associato a un volontario</small>
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
                                        <label class="form-label" id="dpiMemberSearchLabel">Socio/Volontario <span class="text-danger">*</span></label>
                                        <input type="hidden" name="member_id" id="dpiMemberIdInput">
                                        <input type="text" class="form-control" id="dpiMemberSearch" 
                                               placeholder="Cerca per matricola, nome o cognome..." 
                                               autocomplete="off" 
                                               required
                                               aria-label="Cerca socio o volontario per assegnazione DPI"
                                               aria-describedby="dpiMemberSearchHelp"
                                               aria-expanded="false"
                                               aria-controls="dpiMemberSearchResults"
                                               role="combobox">
                                        <div id="dpiMemberSearchResults" class="list-group position-absolute" 
                                             style="z-index: 1000; max-height: 300px; overflow-y: auto; display: none;"
                                             role="listbox"></div>
                                        <small class="text-muted" id="dpiMemberSearchHelp">Inizia a digitare per cercare un volontario</small>
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
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal: Dettaglio DPI Assignment -->
    <div class="modal fade" id="dpiDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Dettagli Assegnazione DPI</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Volontario:</th>
                                    <td id="dpi-detail-member"></td>
                                </tr>
                                <tr>
                                    <th>Matricola:</th>
                                    <td id="dpi-detail-registration"></td>
                                </tr>
                                <tr>
                                    <th>Quantità:</th>
                                    <td id="dpi-detail-quantity"></td>
                                </tr>
                                <tr>
                                    <th>Stato:</th>
                                    <td id="dpi-detail-status"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Data Assegnazione:</th>
                                    <td id="dpi-detail-assignment-date"></td>
                                </tr>
                                <tr>
                                    <th>Data Scadenza:</th>
                                    <td id="dpi-detail-expiry-date"></td>
                                </tr>
                                <tr>
                                    <th>Data Restituzione:</th>
                                    <td id="dpi-detail-return-date"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Note:</h6>
                            <div class="border rounded p-3 bg-light" id="dpi-detail-notes"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal: Dettaglio Movimento -->
    <div class="modal fade" id="movementDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Dettagli Movimento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Data/Ora:</th>
                                    <td id="movement-detail-date"></td>
                                </tr>
                                <tr>
                                    <th>Tipo Movimento:</th>
                                    <td id="movement-detail-type"></td>
                                </tr>
                                <tr>
                                    <th>Quantità:</th>
                                    <td id="movement-detail-quantity"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Volontario:</th>
                                    <td id="movement-detail-member"></td>
                                </tr>
                                <tr>
                                    <th>Destinazione:</th>
                                    <td id="movement-detail-destination"></td>
                                </tr>
                                <tr>
                                    <th>Creato da:</th>
                                    <td id="movement-detail-created-by"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Note:</h6>
                            <div class="border rounded p-3 bg-light" id="movement-detail-notes"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const itemId = <?php echo $item['id']; ?>;
        
        // Member search autocomplete functionality
        function setupMemberSearch(searchInputId, resultsId, hiddenInputId) {
            const searchInput = document.getElementById(searchInputId);
            const resultsDiv = document.getElementById(resultsId);
            const hiddenInput = document.getElementById(hiddenInputId);
            
            // Check if all elements exist
            if (!searchInput || !resultsDiv || !hiddenInput) {
                console.warn('Member search elements not found:', { searchInputId, resultsId, hiddenInputId });
                return;
            }
            
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length < 2) {
                    resultsDiv.style.display = 'none';
                    resultsDiv.innerHTML = '';
                    hiddenInput.value = '';
                    searchInput.setAttribute('aria-expanded', 'false');
                    return;
                }
                
                searchTimeout = setTimeout(async () => {
                    try {
                        // Construct API URL securely
                        const url = new URL('warehouse_api.php', window.location.href);
                        url.searchParams.set('action', 'get_members');
                        url.searchParams.set('search', query);
                        
                        const response = await fetch(url.toString());
                        const result = await response.json();
                        
                        if (result.success && result.members.length > 0) {
                            // Build results safely using DOM manipulation to prevent XSS
                            resultsDiv.innerHTML = '';
                            
                            result.members.forEach(member => {
                                const button = document.createElement('button');
                                button.type = 'button';
                                button.className = 'list-group-item list-group-item-action';
                                button.setAttribute('role', 'option');
                                button.dataset.id = String(member.id);
                                // Safe string concatenation - no template literals with user data
                                button.dataset.name = member.last_name + ' ' + member.first_name + ' (' + member.registration_number + ')';
                                
                                const strong = document.createElement('strong');
                                // Safe textContent assignment - no template literals
                                strong.textContent = member.last_name + ' ' + member.first_name;
                                
                                const small = document.createElement('small');
                                small.className = 'text-muted';
                                small.textContent = ' - Matricola: ' + member.registration_number;
                                
                                button.appendChild(strong);
                                button.appendChild(small);
                                
                                button.addEventListener('click', function() {
                                    hiddenInput.value = this.dataset.id;
                                    searchInput.value = this.dataset.name;
                                    resultsDiv.style.display = 'none';
                                    searchInput.setAttribute('aria-expanded', 'false');
                                });
                                
                                resultsDiv.appendChild(button);
                            });
                            
                            resultsDiv.style.display = 'block';
                            searchInput.setAttribute('aria-expanded', 'true');
                        } else {
                            // Use consistent DOM manipulation approach with safe content
                            const noResultMsg = document.createElement('div');
                            noResultMsg.className = 'list-group-item text-muted';
                            noResultMsg.setAttribute('role', 'option');
                            noResultMsg.textContent = 'Nessun volontario trovato';
                            resultsDiv.innerHTML = '';
                            resultsDiv.appendChild(noResultMsg);
                            resultsDiv.style.display = 'block';
                            searchInput.setAttribute('aria-expanded', 'true');
                            hiddenInput.value = '';
                        }
                    } catch (error) {
                        console.error('Errore nella ricerca:', error);
                        resultsDiv.style.display = 'none';
                        searchInput.setAttribute('aria-expanded', 'false');
                    }
                }, 300);
            });
            
            // Hide results when clicking outside
            document.addEventListener('click', function(e) {
                if (e.target !== searchInput && !resultsDiv.contains(e.target)) {
                    resultsDiv.style.display = 'none';
                    searchInput.setAttribute('aria-expanded', 'false');
                }
            });
        }
        
        // Debug: Log when tabs are clicked
        document.addEventListener('DOMContentLoaded', function() {
    // Setup member search autocomplete for both forms
    setupMemberSearch('memberSearch', 'memberSearchResults', 'memberIdInput');
    setupMemberSearch('dpiMemberSearch', 'dpiMemberSearchResults', 'dpiMemberIdInput');
    
    // Setup movement detail buttons
    const movementDetailButtons = document.querySelectorAll('.movement-detail-btn');
    movementDetailButtons.forEach(button => {
        button.addEventListener('click', function() {
            const movementData = this.getAttribute('data-movement');
            if (movementData) {
                try {
                    const movement = JSON.parse(movementData);
                    showMovementDetail(movement);
                } catch (e) {
                    console.error('Error parsing movement data:', e);
                    alert('Errore nel caricamento dei dettagli');
                }
            }
        });
    });
    
    // Setup DPI detail buttons
    const dpiDetailButtons = document.querySelectorAll('.dpi-detail-btn');
    dpiDetailButtons.forEach(button => {
        button.addEventListener('click', function() {
            const assignmentData = this.getAttribute('data-assignment');
            if (assignmentData) {
                try {
                    const assignment = JSON.parse(assignmentData);
                    showDpiDetail(assignment);
                } catch (e) {
                    console.error('Error parsing assignment data:', e);
                    alert('Errore nel caricamento dei dettagli');
                }
            }
        });
    });
    
    // Setup DPI return buttons
    const dpiReturnButtons = document.querySelectorAll('.dpi-return-btn');
    dpiReturnButtons.forEach(button => {
        button.addEventListener('click', function() {
            const assignmentId = this.getAttribute('data-assignment-id');
            const memberName = this.getAttribute('data-member-name');
            if (assignmentId && memberName) {
                confirmDpiReturn(parseInt(assignmentId), memberName);
            }
        });
    });
    
    // Clear forms when modals are closed
    document.getElementById('addMovementModal')?.addEventListener('hidden.bs.modal', function() {
        document.getElementById('movementForm').reset();
        document.getElementById('memberIdInput').value = '';
        document.getElementById('memberSearch').value = '';
        document.getElementById('memberSearchResults').style.display = 'none';
    });
    
    document.getElementById('assignDpiModal')?.addEventListener('hidden.bs.modal', function() {
        document.getElementById('dpiAssignmentForm').reset();
        document.getElementById('dpiMemberIdInput').value = '';
        document.getElementById('dpiMemberSearch').value = '';
        document.getElementById('dpiMemberSearchResults').style.display = 'none';
    });
    
    // Handle tab parameter from URL
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    const allowedTabs = ['info', 'movements', 'dpi'];
    if (tab && allowedTabs.includes(tab)) {
        const tabElement = document.getElementById(tab + '-tab');
        if (tabElement) {
            const bsTab = new bootstrap.Tab(tabElement);
            bsTab.show();
        }
    }
    
    console.log('Warehouse view loaded - Item ID:', itemId);
    console.log('Movements data:', <?php echo json_encode($item['movements'] ?? []); ?>);
    console.log('DPI assignments data:', <?php echo json_encode($item['dpi_assignments'] ?? []); ?>);
});
        
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
                    // Redirect to movements tab after successful submission
                    window.location.href = 'warehouse_view.php?id=' + itemId + '&tab=movements';
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
            
            // Custom validation: ensure a member is selected
            const dpiMemberIdInput = document.getElementById('dpiMemberIdInput');
            const dpiMemberSearch = document.getElementById('dpiMemberSearch');
            
            if (!dpiMemberIdInput.value) {
                alert('Errore: Seleziona un volontario dalla lista dei risultati');
                dpiMemberSearch.focus();
                return;
            }
            
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
                    // Redirect to DPI tab after successful submission
                    window.location.href = 'warehouse_view.php?id=' + itemId + '&tab=dpi';
                } else {
                    alert('Errore: ' + result.message);
                }
            } catch (error) {
                alert('Errore durante l\'assegnazione: ' + error.message);
            }
        });
        
        // Handle DPI return
        async function confirmDpiReturn(assignmentId, memberName) {
            if (!confirm('Confermi la restituzione del DPI da parte di ' + memberName + '?')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'return_dpi');
                formData.append('assignment_id', assignmentId);
                formData.append('csrf_token', '<?php echo \EasyVol\Middleware\CsrfProtection::generateToken(); ?>');
                
                const response = await fetch('warehouse_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    // Reload page to show updated DPI list
                    window.location.href = 'warehouse_view.php?id=' + itemId + '&tab=dpi';
                } else {
                    alert('Errore: ' + result.message);
                }
            } catch (error) {
                alert('Errore durante la restituzione: ' + error.message);
            }
        }
        
        // Show DPI assignment detail modal
        function showDpiDetail(assignment) {
            // Member info
            const memberName = (assignment.first_name || '') + ' ' + (assignment.last_name || '');
            document.getElementById('dpi-detail-member').textContent = memberName.trim() || 'N/A';
            document.getElementById('dpi-detail-registration').textContent = assignment.registration_number || '-';
            
            // Quantity
            document.getElementById('dpi-detail-quantity').textContent = assignment.quantity || '1';
            
            // Status
            const statusBadge = document.createElement('span');
            statusBadge.className = 'badge bg-' + (assignment.status === 'assegnato' ? 'success' : 'secondary');
            statusBadge.textContent = assignment.status ? assignment.status.charAt(0).toUpperCase() + assignment.status.slice(1) : 'N/A';
            document.getElementById('dpi-detail-status').innerHTML = '';
            document.getElementById('dpi-detail-status').appendChild(statusBadge);
            
            // Dates
            if (assignment.assignment_date || assignment.assigned_date) {
                const assignDate = new Date(assignment.assignment_date || assignment.assigned_date);
                document.getElementById('dpi-detail-assignment-date').textContent = assignDate.toLocaleDateString('it-IT');
            } else {
                document.getElementById('dpi-detail-assignment-date').textContent = '-';
            }
            
            if (assignment.expiry_date) {
                const expiryDate = new Date(assignment.expiry_date);
                const now = new Date();
                const isExpired = expiryDate < now;
                
                const expiryBadge = document.createElement('span');
                expiryBadge.className = 'badge bg-' + (isExpired ? 'danger' : 'success');
                expiryBadge.textContent = expiryDate.toLocaleDateString('it-IT');
                document.getElementById('dpi-detail-expiry-date').innerHTML = '';
                document.getElementById('dpi-detail-expiry-date').appendChild(expiryBadge);
            } else {
                document.getElementById('dpi-detail-expiry-date').textContent = '-';
            }
            
            if (assignment.return_date) {
                const returnDate = new Date(assignment.return_date);
                document.getElementById('dpi-detail-return-date').textContent = returnDate.toLocaleDateString('it-IT');
            } else {
                document.getElementById('dpi-detail-return-date').textContent = '-';
            }
            
            // Notes
            document.getElementById('dpi-detail-notes').textContent = assignment.notes || 'Nessuna nota';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('dpiDetailModal'));
            modal.show();
        }
        
        // Show movement detail modal
        function showMovementDetail(movement) {
            // Format date
            const date = new Date(movement.created_at);
            const formattedDate = date.toLocaleDateString('it-IT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            // Type colors
            const typeColors = {
                'carico': 'success',
                'scarico': 'danger',
                'assegnazione': 'info',
                'restituzione': 'warning',
                'trasferimento': 'secondary'
            };
            const color = typeColors[movement.movement_type] || 'secondary';
            
            // Quantity sign
            const sign = ['carico', 'restituzione'].includes(movement.movement_type) ? '+' : '-';
            const qtyClass = sign === '+' ? 'text-success' : 'text-danger';
            
            // Populate modal with safe text content
            document.getElementById('movement-detail-date').textContent = formattedDate;
            
            // Type badge
            const typeBadge = document.createElement('span');
            typeBadge.className = 'badge bg-' + color;
            typeBadge.textContent = movement.movement_type.charAt(0).toUpperCase() + movement.movement_type.slice(1);
            document.getElementById('movement-detail-type').innerHTML = '';
            document.getElementById('movement-detail-type').appendChild(typeBadge);
            
            // Quantity
            const qtyElement = document.createElement('strong');
            qtyElement.className = qtyClass;
            qtyElement.textContent = sign + movement.quantity;
            document.getElementById('movement-detail-quantity').innerHTML = '';
            document.getElementById('movement-detail-quantity').appendChild(qtyElement);
            
            document.getElementById('movement-detail-member').textContent = movement.member_name || '-';
            document.getElementById('movement-detail-destination').textContent = movement.destination || '-';
            document.getElementById('movement-detail-created-by').textContent = movement.created_by_name || '-';
            document.getElementById('movement-detail-notes').textContent = movement.notes || 'Nessuna nota';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('movementDetailModal'));
            modal.show();
        }
    </script>
</body>
</html>
