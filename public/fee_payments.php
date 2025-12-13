<?php
/**
 * Gestione Richieste Pagamento Quote
 * 
 * Pagina interna per gestire le richieste di pagamento quote in sospeso
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\FeePaymentController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi (requires members management permission)
if (!$app->checkPermission('members', 'edit')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new FeePaymentController($db, $config);

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token di sicurezza non valido';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $requestId = intval($_POST['request_id'] ?? 0);
        $userId = $app->getUserId();
        
        if ($action === 'approve' && $requestId > 0) {
            if ($controller->approvePaymentRequest($requestId, $userId)) {
                $message = 'Richiesta approvata con successo';
                $messageType = 'success';
            } else {
                $message = 'Errore durante l\'approvazione della richiesta';
                $messageType = 'danger';
            }
        } elseif ($action === 'reject' && $requestId > 0) {
            if ($controller->rejectPaymentRequest($requestId, $userId)) {
                $message = 'Richiesta rifiutata';
                $messageType = 'warning';
            } else {
                $message = 'Errore durante il rifiuto della richiesta';
                $messageType = 'danger';
            }
        }
    }
}

// Get filters
$filters = [
    'status' => $_GET['status'] ?? 'pending',
    'year' => $_GET['year'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// Get payment requests
$result = $controller->getPaymentRequests($filters, $page, $perPage);
$requests = $result['requests'];
$totalPages = $result['totalPages'];

$pageTitle = 'Gestione Richieste Pagamento Quote';
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
                        <i class="bi bi-receipt-cutoff"></i> <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>
                
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>In Sospeso</option>
                                    <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>Approvate</option>
                                    <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rifiutate</option>
                                    <option value="" <?php echo $filters['status'] === '' ? 'selected' : ''; ?>>Tutte</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="year" class="form-label">Anno</label>
                                <select class="form-select" id="year" name="year">
                                    <option value="">Tutti</option>
                                    <?php
                                    $currentYear = date('Y');
                                    for ($year = $currentYear + 1; $year >= $currentYear - 5; $year--) {
                                        $selected = ($filters['year'] == $year) ? 'selected' : '';
                                        echo "<option value=\"{$year}\" {$selected}>{$year}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-md-5">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Matricola o cognome..."
                                       value="<?php echo htmlspecialchars($filters['search']); ?>">
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-search"></i> Filtra
                                </button>
                                <a href="fee_payments.php" class="btn btn-secondary">
                                    <i class="bi bi-x"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Statistics -->
                <div class="row mb-4">
                    <?php
                    $stats = $controller->getStatistics();
                    ?>
                    <div class="col-md-4">
                        <div class="card text-bg-warning">
                            <div class="card-body">
                                <h5 class="card-title">In Sospeso</h5>
                                <h2><?php echo $stats['pending_count'] ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Approvate</h5>
                                <h2><?php echo $stats['approved_count'] ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-bg-danger">
                            <div class="card-body">
                                <h5 class="card-title">Rifiutate</h5>
                                <h2><?php echo $stats['rejected_count'] ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Requests Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Richieste (<?php echo $result['total']; ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($requests)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-inbox" style="font-size: 48px;"></i>
                            <p class="mt-3">Nessuna richiesta trovata</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Matricola</th>
                                        <th>Socio</th>
                                        <th>Anno</th>
                                        <th>Data Pagamento</th>
                                        <th>Importo</th>
                                        <th>Data Invio</th>
                                        <th>Stato</th>
                                        <th>Ricevuta</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['registration_number']); ?></td>
                                        <td>
                                            <?php if ($request['member_id']): ?>
                                            <?php 
                                            // Check if it's a junior member (registration number starts with C)
                                            $isJunior = FeePaymentController::isJuniorMember($request['registration_number']);
                                            $viewPage = $isJunior ? 'junior_member_view.php' : 'member_view.php';
                                            ?>
                                            <a href="<?php echo $viewPage; ?>?id=<?php echo $request['member_id']; ?>">
                                                <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                            </a>
                                            <?php else: ?>
                                            <?php echo htmlspecialchars($request['last_name']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['payment_year']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($request['payment_date'])); ?></td>
                                        <td>
                                            <?php if (!empty($request['amount'])): ?>
                                            €<?php echo htmlspecialchars(number_format($request['amount'], 2, ',', '.'), ENT_QUOTES, 'UTF-8'); ?>
                                            <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($request['submitted_at'])); ?></td>
                                        <td>
                                            <?php
                                            $statusBadge = [
                                                'pending' => 'bg-warning',
                                                'approved' => 'bg-success',
                                                'rejected' => 'bg-danger'
                                            ];
                                            $statusLabel = [
                                                'pending' => 'In Sospeso',
                                                'approved' => 'Approvata',
                                                'rejected' => 'Rifiutata'
                                            ];
                                            ?>
                                            <span class="badge <?php echo $statusBadge[$request['status']]; ?>">
                                                <?php echo $statusLabel[$request['status']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($request['receipt_file'] && file_exists(__DIR__ . '/../' . $request['receipt_file'])): ?>
                                            <a href="download.php?type=fee_receipt&id=<?php echo $request['id']; ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-file-earmark-pdf"></i> Visualizza
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($request['status'] === 'pending'): ?>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-success" 
                                                        onclick="confirmAction('approve', <?php echo $request['id']; ?>)">
                                                    <i class="bi bi-check-lg"></i> Approva
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="confirmAction('reject', <?php echo $request['id']; ?>)">
                                                    <i class="bi bi-x-lg"></i> Rifiuta
                                                </button>
                                            </div>
                                            <?php else: ?>
                                            <small class="text-muted">
                                                <?php if ($request['processed_by_name']): ?>
                                                da <?php echo htmlspecialchars($request['processed_by_name']); ?><br>
                                                <?php endif; ?>
                                                <?php echo date('d/m/Y H:i', strtotime($request['processed_at'])); ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <nav>
                            <ul class="pagination justify-content-center mb-0">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filters['status']); ?>&year=<?php echo urlencode($filters['year']); ?>&search=<?php echo urlencode($filters['search']); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Hidden form for actions -->
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
        <input type="hidden" name="action" id="actionInput">
        <input type="hidden" name="request_id" id="requestIdInput">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmAction(action, requestId) {
            const messages = {
                'approve': 'Sei sicuro di voler approvare questa richiesta? Il pagamento verrà registrato nella scheda del socio.',
                'reject': 'Sei sicuro di voler rifiutare questa richiesta?'
            };
            
            if (confirm(messages[action])) {
                document.getElementById('actionInput').value = action;
                document.getElementById('requestIdInput').value = requestId;
                document.getElementById('actionForm').submit();
            }
        }
    </script>
</body>
</html>
