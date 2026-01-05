<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\OperationsCenterController;
use EasyVol\Controllers\MemberController;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('operations_center', 'view')) {
    die('Accesso negato');
}

$controller = new OperationsCenterController($app->getDb(), $app->getConfig());
$memberController = new MemberController($app->getDb(), $app->getConfig());

// Log page access
AutoLogger::logPageAccess();

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
    <?php 
    $user = $app->getCurrentUser();
    $isCoUser = isset($user['is_operations_center_user']) && $user['is_operations_center_user'];
    ?>
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo $isCoUser ? 'EasyCO' : 'EasyVol'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <?php if ($isCoUser): ?>
        <link rel="stylesheet" href="../assets/css/easyco.css">
    <?php endif; ?>
</head>
<body>
    <?php 
    if ($isCoUser) {
        include '../src/Views/includes/navbar_operations.php';
    } else {
        include '../src/Views/includes/navbar.php';
    }
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php 
            if ($isCoUser) {
                include '../src/Views/includes/sidebar_operations.php';
            } else {
                include '../src/Views/includes/sidebar.php';
            }
            ?>
            
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
                        <?php if ($app->checkPermission('operations_center', 'edit')): ?>
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
                                    
                                    <?php if ($radio['dmr_id']): ?>
                                        <dt class="col-sm-4">DMR ID:</dt>
                                        <dd class="col-sm-8"><code><?php echo htmlspecialchars($radio['dmr_id']); ?></code></dd>
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
                                                <?php echo htmlspecialchars(($radio['current_assignment']['first_name'] ?? '') . ' ' . ($radio['current_assignment']['last_name'] ?? '')); ?>
                                            </strong>
                                            <?php if (!empty($radio['current_assignment']['badge_number'])): ?>
                                                <br>Matricola: <?php echo htmlspecialchars($radio['current_assignment']['badge_number']); ?>
                                            <?php endif; ?>
                                        </p>
                                        <?php if (!empty($radio['current_assignment']['phone_number'])): ?>
                                            <p class="mb-2">
                                                <i class="bi bi-telephone"></i> Telefono: 
                                                <strong><?php echo htmlspecialchars($radio['current_assignment']['phone_number']); ?></strong>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($radio['current_assignment']['is_external']) && $radio['current_assignment']['is_external']): ?>
                                            <?php if (!empty($radio['current_assignment']['organization'])): ?>
                                                <p class="mb-2">
                                                    <i class="bi bi-building"></i> Ente: 
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($radio['current_assignment']['organization']); ?></span>
                                                </p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <p class="mb-2">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i> 
                                                Assegnata il: <?php echo date('d/m/Y H:i', strtotime($radio['current_assignment']['assignment_date'])); ?>
                                            </small>
                                        </p>
                                        <?php if (!empty($radio['current_assignment']['assignment_notes'])): ?>
                                            <p class="mb-2">
                                                <small><strong>Note Consegna:</strong> <?php echo nl2br(htmlspecialchars($radio['current_assignment']['assignment_notes'])); ?></small>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($app->checkPermission('operations_center', 'edit')): ?>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="returnRadio(<?php echo $radio['current_assignment']['id']; ?>)">
                                                <i class="bi bi-check-circle"></i> Registra Restituzione
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-3">Questa radio non è attualmente assegnata.</p>
                                    <?php if ($radio['status'] === 'disponibile' && $app->checkPermission('operations_center', 'edit')): ?>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal">
                                            <i class="bi bi-person-plus"></i> Assegna Radio
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Serial Number Display -->
                        <?php if ($radio['serial_number']): ?>
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Numero Seriale</h5>
                                </div>
                                <div class="card-body text-center">
                                    <p class="mb-2">Numero seriale per identificazione:</p>
                                    <h3><code><?php echo htmlspecialchars($radio['serial_number']); ?></code></h3>
                                    <small class="text-muted">
                                        Nota: Per generare barcode, utilizzare una libreria locale come Endroid QR Code
                                    </small>
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
                                                        <?php echo htmlspecialchars(($assignment['first_name'] ?? '') . ' ' . ($assignment['last_name'] ?? '')); ?>
                                                    </strong>
                                                    <?php if (!empty($assignment['badge_number'])): ?>
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
                                                    <?php if (!empty($assignment['notes'])): ?>
                                                        <small><?php echo htmlspecialchars($assignment['notes']); ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($assignment['return_notes'])): ?>
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
                        <input type="hidden" name="csrf_token" value="<?php echo \EasyVol\Middleware\CsrfProtection::generateToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo Assegnazione *</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="assignment_type" id="assign_member" value="member" checked onchange="toggleAssignmentFields()">
                                <label class="form-check-label" for="assign_member">
                                    Volontario dell'Associazione
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="assignment_type" id="assign_external" value="external" onchange="toggleAssignmentFields()">
                                <label class="form-check-label" for="assign_external">
                                    Personale Esterno
                                </label>
                            </div>
                        </div>
                        
                        <div id="member_fields">
                            <div class="mb-3">
                                <label for="memberSearch" class="form-label">Volontario o Cadetto *</label>
                                <input type="text" class="form-control" id="memberSearch" 
                                       placeholder="Digita nome, cognome o matricola..." 
                                       autocomplete="off">
                                <input type="hidden" id="member_id" name="member_id">
                                <input type="hidden" id="member_type" name="member_type" value="member">
                                <small class="form-text text-muted">Inizia a digitare per cercare tra volontari attivi e cadetti</small>
                            </div>
                            <div id="memberSearchResults" class="list-group" style="max-height: 300px; overflow-y: auto; display: none;"></div>
                        </div>
                        
                        <div id="external_fields" style="display:none;">
                            <div class="mb-3">
                                <label for="external_last_name" class="form-label">Cognome *</label>
                                <input type="text" class="form-control" id="external_last_name" name="external_last_name">
                            </div>
                            <div class="mb-3">
                                <label for="external_first_name" class="form-label">Nome *</label>
                                <input type="text" class="form-control" id="external_first_name" name="external_first_name">
                            </div>
                            <div class="mb-3">
                                <label for="external_organization" class="form-label">Ente *</label>
                                <input type="text" class="form-control" id="external_organization" name="external_organization" 
                                       placeholder="Es: Protezione Civile Comunale, Vigili del Fuoco, ecc.">
                            </div>
                            <div class="mb-3">
                                <label for="external_phone" class="form-label">Numero di Cellulare *</label>
                                <input type="tel" class="form-control" id="external_phone" name="external_phone" 
                                       placeholder="Es: +39 333 1234567">
                            </div>
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
        function toggleAssignmentFields() {
            const isMember = document.getElementById('assign_member').checked;
            const memberFields = document.getElementById('member_fields');
            const externalFields = document.getElementById('external_fields');
            const memberSearchInput = document.getElementById('memberSearch');
            const externalLastName = document.getElementById('external_last_name');
            const externalFirstName = document.getElementById('external_first_name');
            const externalOrganization = document.getElementById('external_organization');
            const externalPhone = document.getElementById('external_phone');
            
            if (isMember) {
                memberFields.style.display = 'block';
                externalFields.style.display = 'none';
                if (memberSearchInput) memberSearchInput.required = true;
                externalLastName.required = false;
                externalFirstName.required = false;
                externalOrganization.required = false;
                externalPhone.required = false;
            } else {
                memberFields.style.display = 'none';
                externalFields.style.display = 'block';
                if (memberSearchInput) memberSearchInput.required = false;
                externalLastName.required = true;
                externalFirstName.required = true;
                externalOrganization.required = true;
                externalPhone.required = true;
            }
        }
        
        // Member search functionality
        let searchTimeout;
        const memberSearchInput = document.getElementById('memberSearch');
        const memberSearchResults = document.getElementById('memberSearchResults');
        const memberIdInput = document.getElementById('member_id');
        const memberTypeInput = document.getElementById('member_type');
        
        if (memberSearchInput) {
            memberSearchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length < 2) {
                    memberSearchResults.style.display = 'none';
                    memberSearchResults.innerHTML = '';
                    memberIdInput.value = '';
                    memberTypeInput.value = 'member';
                    return;
                }
                
                searchTimeout = setTimeout(async () => {
                    try {
                        const response = await fetch('radio_member_search_ajax.php?q=' + encodeURIComponent(query));
                        const results = await response.json();
                        
                        if (results.length === 0) {
                            memberSearchResults.innerHTML = '<div class="list-group-item text-muted">Nessun volontario o cadetto trovato</div>';
                            memberSearchResults.style.display = 'block';
                            return;
                        }
                        
                        memberSearchResults.innerHTML = '';
                        results.forEach(result => {
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'list-group-item list-group-item-action';
                            button.innerHTML = '<strong>' + escapeHtml(result.label) + '</strong>';
                            button.onclick = function() {
                                memberIdInput.value = result.id;
                                memberTypeInput.value = result.source_type;
                                memberSearchInput.value = result.value;
                                memberSearchResults.style.display = 'none';
                            };
                            memberSearchResults.appendChild(button);
                        });
                        memberSearchResults.style.display = 'block';
                        
                    } catch (error) {
                        console.error('Error searching members:', error);
                        memberSearchResults.innerHTML = '<div class="list-group-item text-danger">Errore durante la ricerca</div>';
                        memberSearchResults.style.display = 'block';
                    }
                }, 300);
            });
            
            // Close results when clicking outside
            document.addEventListener('click', function(e) {
                if (e.target !== memberSearchInput && !memberSearchResults.contains(e.target)) {
                    memberSearchResults.style.display = 'none';
                }
            });
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function returnRadio(assignmentId) {
            const notes = prompt('Note sulla restituzione (opzionale):');
            if (notes !== null) {  // null means cancelled
                // Create a form and submit via POST instead of GET
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'radio_return.php';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'assignment_id';
                idInput.value = assignmentId;
                form.appendChild(idInput);
                
                if (notes) {
                    const notesInput = document.createElement('input');
                    notesInput.type = 'hidden';
                    notesInput.name = 'notes';
                    notesInput.value = notes;
                    form.appendChild(notesInput);
                }
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
