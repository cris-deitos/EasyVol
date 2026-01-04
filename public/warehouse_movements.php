<?php
/**
 * Gestione Movimenti Magazzino
 * 
 * Pagina per visualizzare tutti i movimenti di magazzino
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;

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

$db = $app->getDb();

// Filtri
$filters = [
    'item_id' => $_GET['item_id'] ?? '',
    'movement_type' => $_GET['movement_type'] ?? '',
    'member_id' => $_GET['member_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Costruisci query con filtri
$where = ["1=1"];
$params = [];

if (!empty($filters['item_id'])) {
    $where[] = "wm.item_id = ?";
    $params[] = $filters['item_id'];
}

if (!empty($filters['movement_type'])) {
    $where[] = "wm.movement_type = ?";
    $params[] = $filters['movement_type'];
}

if (!empty($filters['member_id'])) {
    $where[] = "wm.member_id = ?";
    $params[] = $filters['member_id'];
}

if (!empty($filters['date_from'])) {
    $where[] = "DATE(wm.created_at) >= ?";
    $params[] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $where[] = "DATE(wm.created_at) <= ?";
    $params[] = $filters['date_to'];
}

$whereClause = implode(' AND ', $where);

// Paginazione
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Query movimenti with safe pagination
$sql = "SELECT wm.*, 
        wi.name as item_name, wi.code as item_code,
        CONCAT(m.first_name, ' ', m.last_name) as member_name,
        u.username as created_by_name
        FROM warehouse_movements wm
        JOIN warehouse_items wi ON wm.item_id = wi.id
        LEFT JOIN members m ON wm.member_id = m.id
        LEFT JOIN users u ON wm.created_by = u.id
        WHERE $whereClause
        ORDER BY wm.created_at DESC
        LIMIT ? OFFSET ?";

// Add pagination parameters
$queryParams = array_merge($params, [$perPage, $offset]);
$movements = $db->fetchAll($sql, $queryParams);

// Conta totale per paginazione
$countSql = "SELECT COUNT(*) as total FROM warehouse_movements wm WHERE $whereClause";
$totalCount = $db->fetchOne($countSql, $params)['total'] ?? 0;
$totalPages = ceil($totalCount / $perPage);

// Get items for filter
$items = $db->fetchAll("SELECT id, name, code FROM warehouse_items ORDER BY name");

$pageTitle = 'Movimenti Magazzino';
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
                </div>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="item_id" class="form-label">Articolo</label>
                                <select class="form-select" id="item_id" name="item_id">
                                    <option value="">Tutti</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>" <?php echo $filters['item_id'] == $item['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($item['name']); ?>
                                            <?php if ($item['code']): ?>(<?php echo htmlspecialchars($item['code']); ?>)<?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="movement_type" class="form-label">Tipo Movimento</label>
                                <select class="form-select" id="movement_type" name="movement_type">
                                    <option value="">Tutti</option>
                                    <option value="carico" <?php echo $filters['movement_type'] === 'carico' ? 'selected' : ''; ?>>Carico</option>
                                    <option value="scarico" <?php echo $filters['movement_type'] === 'scarico' ? 'selected' : ''; ?>>Scarico</option>
                                    <option value="assegnazione" <?php echo $filters['movement_type'] === 'assegnazione' ? 'selected' : ''; ?>>Assegnazione</option>
                                    <option value="restituzione" <?php echo $filters['movement_type'] === 'restituzione' ? 'selected' : ''; ?>>Restituzione</option>
                                    <option value="trasferimento" <?php echo $filters['movement_type'] === 'trasferimento' ? 'selected' : ''; ?>>Trasferimento</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Data Da</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Data A</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Filtra
                                    </button>
                                    <a href="warehouse_movements.php" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabella Movimenti -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-arrow-left-right"></i> Storico Movimenti
                            <span class="badge bg-secondary"><?php echo number_format($totalCount); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($movements)): ?>
                            <p class="text-muted text-center">Nessun movimento trovato.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>Data/Ora</th>
                                            <th>Articolo</th>
                                            <th>Tipo</th>
                                            <th>Quantità</th>
                                            <th>Volontario</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($movements as $movement): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($movement['created_at']))); ?></td>
                                                <td>
                                                    <a href="warehouse_view.php?id=<?php echo $movement['item_id']; ?>">
                                                        <?php echo htmlspecialchars($movement['item_name']); ?>
                                                    </a>
                                                    <?php if ($movement['item_code']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($movement['item_code']); ?></small>
                                                    <?php endif; ?>
                                                </td>
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
                            
                            <!-- Paginazione -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Navigazione pagine">
                                    <ul class="pagination justify-content-center mt-4">
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                                    foreach ($filters as $key => $value) {
                                                        if ($value !== '') echo '&' . urlencode($key) . '=' . urlencode($value);
                                                    }
                                                ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
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
                                    <td id="detail-date"></td>
                                </tr>
                                <tr>
                                    <th>Articolo:</th>
                                    <td id="detail-item"></td>
                                </tr>
                                <tr>
                                    <th>Codice Articolo:</th>
                                    <td id="detail-item-code"></td>
                                </tr>
                                <tr>
                                    <th>Tipo Movimento:</th>
                                    <td id="detail-type"></td>
                                </tr>
                                <tr>
                                    <th>Quantità:</th>
                                    <td id="detail-quantity"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Volontario:</th>
                                    <td id="detail-member"></td>
                                </tr>
                                <tr>
                                    <th>Destinazione:</th>
                                    <td id="detail-destination"></td>
                                </tr>
                                <tr>
                                    <th>Creato da:</th>
                                    <td id="detail-created-by"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Note:</h6>
                            <div class="border rounded p-3 bg-light" id="detail-notes"></div>
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
        // Add event listeners to all movement detail buttons
        document.addEventListener('DOMContentLoaded', function() {
            const detailButtons = document.querySelectorAll('.movement-detail-btn');
            detailButtons.forEach(button => {
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
        });
        
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
            document.getElementById('detail-date').textContent = formattedDate;
            document.getElementById('detail-item').textContent = movement.item_name || '-';
            document.getElementById('detail-item-code').textContent = movement.item_code || '-';
            
            // Type badge
            const typeBadge = document.createElement('span');
            typeBadge.className = 'badge bg-' + color;
            typeBadge.textContent = movement.movement_type.charAt(0).toUpperCase() + movement.movement_type.slice(1);
            document.getElementById('detail-type').innerHTML = '';
            document.getElementById('detail-type').appendChild(typeBadge);
            
            // Quantity
            const qtyElement = document.createElement('strong');
            qtyElement.className = qtyClass;
            qtyElement.textContent = sign + movement.quantity;
            document.getElementById('detail-quantity').innerHTML = '';
            document.getElementById('detail-quantity').appendChild(qtyElement);
            
            document.getElementById('detail-member').textContent = movement.member_name || '-';
            document.getElementById('detail-destination').textContent = movement.destination || '-';
            document.getElementById('detail-created-by').textContent = movement.created_by_name || '-';
            document.getElementById('detail-notes').textContent = movement.notes || 'Nessuna nota';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('movementDetailModal'));
            modal.show();
        }
    </script>
</body>
</html>
