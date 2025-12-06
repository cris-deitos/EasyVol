<?php
require_once '../src/Autoloader.php';
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\OperationsCenterController;
use EasyVol\Controllers\MemberController;

$app = new App();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->hasPermission('operations_center', 'view')) {
    die('Accesso negato');
}

$controller = new OperationsCenterController($app->getDatabase(), $app->getConfig());
$memberController = new MemberController($app->getDatabase(), $app->getConfig());

// Get radio ID
$radioId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$radioId) {
    die('ID radio non valido');
}

$radio = $controller->getRadio($radioId);
if (!$radio) {
    die('Radio non trovata');
}

$pageTitle = 'Dettaglio Radio';
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
    <?php include '../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../src/Views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-broadcast"></i> <?php echo htmlspecialchars($radio['name']); ?>
                        <?php
                        $statusClass = [
                            'disponibile' => 'success',
                            'assegnata' => 'warning',
                            'manutenzione' => 'danger',
                            'fuori_servizio' => 'secondary'
                        ];
                        $class = $statusClass[$radio['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $class; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $radio['status'])); ?>
                        </span>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="radio_directory.php" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="bi bi-arrow-left"></i> Torna alla rubrica
                        </a>
                        <?php if ($app->hasPermission('operations_center', 'edit')): ?>
                            <a href="radio_edit.php?id=<?php echo $radio['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-pencil"></i> Modifica
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">
                    <!-- Radio Details -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Informazioni Radio</h5>
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-4">Nome:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($radio['name']); ?></dd>
                                    
                                    <?php if ($radio['identifier']): ?>
                                        <dt class="col-sm-4">Identificativo:</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($radio['identifier']); ?></dd>
                                    <?php endif; ?>
                                    
                                    <?php if ($radio['device_type']): ?>
                                        <dt class="col-sm-4">Tipo:</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($radio['device_type']); ?></dd>
                                    <?php endif; ?>
                                    
                                    <?php if ($radio['brand']): ?>
                                        <dt class="col-sm-4">Marca:</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($radio['brand']); ?></dd>
                                    <?php endif; ?>
                                    
                                    <?php if ($radio['model']): ?>
                                        <dt class="col-sm-4">Modello:</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($radio['model']); ?></dd>
                                    <?php endif; ?>
                                    
                                    <?php if ($radio['serial_number']): ?>
                                        <dt class="col-sm-4">Seriale:</dt>
                                        <dd class="col-sm-8"><code><?php echo htmlspecialchars($radio['serial_number']); ?></code></dd>
                                    <?php endif; ?>
                                    
                                    <dt class="col-sm-4">Stato:</dt>
                                    <dd class="col-sm-8">
                                        <span class="badge bg-<?php echo $class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $radio['status'])); ?>
                                        </span>
                                    </dd>
                                </dl>
                                
                                <?php if ($radio['notes']): ?>
                                    <hr>
                                    <h6>Note:</h6>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($radio['notes'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Current Assignment -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Assegnazione Corrente</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($radio['current_assignment']): ?>
                                    <div class="alert alert-warning">
                                        <h6 class="alert-heading"><i class="bi bi-person-badge"></i> Assegnata a:</h6>
                                        <p class="mb-2">
                                            <strong>
                                                <?php echo htmlspecialchars($radio['current_assignment']['first_name'] . ' ' . $radio['current_assignment']['last_name']); ?>
                                            </strong>
                                            <?php if ($radio['current_assignment']['badge_number']): ?>
                                                <br>Matricola: <?php echo htmlspecialchars($radio['current_assignment']['badge_number']); ?>
                                            <?php endif; ?>
                                        </p>
                                        <p class="mb-2">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i> 
                                                Assegnata il: <?php echo date('d/m/Y H:i', strtotime($radio['current_assignment']['assignment_date'])); ?>
                                            </small>
                                        </p>
                                        <?php if ($radio['current_assignment']['notes']): ?>
                                            <p class="mb-2">
                                                <small>Note: <?php echo htmlspecialchars($radio['current_assignment']['notes']); ?></small>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($app->hasPermission('operations_center', 'edit')): ?>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="returnRadio(<?php echo $radio['current_assignment']['id']; ?>)">
                                                <i class="bi bi-check-circle"></i> Registra Restituzione
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-3">Questa radio non è attualmente assegnata.</p>
                                    <?php if ($radio['status'] === 'disponibile' && $app->hasPermission('operations_center', 'edit')): ?>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal">
                                            <i class="bi bi-person-plus"></i> Assegna Radio
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- QR Code for serial scan (if available) -->
                        <?php if ($radio['serial_number']): ?>
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Scan Seriale</h5>
                                </div>
                                <div class="card-body text-center">
                                    <p class="mb-2">Scansiona il codice a barre per identificare rapidamente questa radio</p>
                                    <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($radio['serial_number']); ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=96&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0" 
                                         alt="Barcode" style="max-width: 100%;">
                                    <br>
                                    <code><?php echo htmlspecialchars($radio['serial_number']); ?></code>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Assignment History -->
                <?php if (!empty($radio['assignment_history'])): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Storico Assegnazioni</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Volontario</th>
                                            <th>Data Assegnazione</th>
                                            <th>Data Restituzione</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($radio['assignment_history'] as $assignment): ?>
                                            <tr>
                                                <td>
                                                    <strong>
                                                        <?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>
                                                    </strong>
                                                    <?php if ($assignment['badge_number']): ?>
                                                        <br><small class="text-muted">Mat. <?php echo htmlspecialchars($assignment['badge_number']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($assignment['assignment_date'])); ?></td>
                                                <td>
                                                    <?php if ($assignment['return_date']): ?>
                                                        <?php echo date('d/m/Y H:i', strtotime($assignment['return_date'])); ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">In uso</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($assignment['notes']): ?>
                                                        <small><?php echo htmlspecialchars($assignment['notes']); ?></small>
                                                    <?php endif; ?>
                                                    <?php if ($assignment['return_notes']): ?>
                                                        <br><small class="text-muted">Restituzione: <?php echo htmlspecialchars($assignment['return_notes']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Assign Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assegna Radio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="radio_assign.php">
                    <div class="modal-body">
                        <input type="hidden" name="radio_id" value="<?php echo $radio['id']; ?>">
                        <div class="mb-3">
                            <label for="member_id" class="form-label">Volontario *</label>
                            <select class="form-select" id="member_id" name="member_id" required>
                                <option value="">Seleziona volontario...</option>
                                <!-- This would be populated dynamically -->
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Note</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">Assegna</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function returnRadio(assignmentId) {
            const notes = prompt('Note sulla restituzione (opzionale):');
            if (notes !== null) {  // null means cancelled
                window.location.href = 'radio_return.php?assignment_id=' + assignmentId + '&notes=' + encodeURIComponent(notes);
            }
        }
    </script>
</body>
</html>
