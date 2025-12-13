<?php
/**
 * EasyCO - Volontari Attivi (Read-Only)
 * 
 * Pagina di visualizzazione limitata per la Centrale Operativa
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login_co.php');
    exit;
}

// Verifica che sia utente CO
$user = $app->getCurrentUser();
if (!isset($user['is_operations_center_user']) || !$user['is_operations_center_user']) {
    die('Accesso negato - Solo per utenti EasyCO');
}

$db = $app->getDb();

// Gestione ricerca
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Query per ottenere solo membri attivi con dati limitati
$whereClause = "WHERE m.member_status = 'attivo'";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.badge_number LIKE ? OR m.mobile LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$sql = "SELECT 
    m.id,
    m.registration_number,
    m.badge_number,
    m.first_name,
    m.last_name,
    m.birth_date,
    m.tax_code,
    m.mobile,
    m.phone,
    m.email
FROM members m
$whereClause
ORDER BY m.last_name, m.first_name
LIMIT $perPage OFFSET $offset";

$members = $db->fetchAll($sql, $params);

// Conta totale per paginazione
$countSql = "SELECT COUNT(*) as total FROM members m $whereClause";
$totalCount = $db->fetchOne($countSql, $params)['total'] ?? 0;
$totalPages = ceil($totalCount / $perPage);

// Log page access
AutoLogger::logPageAccess();

$pageTitle = 'Volontari Attivi';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - EasyCO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/easyco.css">
</head>
<body>
    <?php include '../src/Views/includes/navbar_operations.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../src/Views/includes/sidebar_operations.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-people"></i> <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Visualizzazione limitata per la Centrale Operativa. 
                    Sono mostrati solo i dati essenziali dei volontari attivi.
                </div>
                
                <!-- Filtri e Ricerca -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Cerca per nome, cognome, matricola o telefono..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-easyco-primary" type="submit">
                                        <i class="bi bi-search"></i> Cerca
                                    </button>
                                    <?php if (!empty($search)): ?>
                                        <a href="operations_members.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-x"></i> Pulisci
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <span class="badge bg-success fs-6">
                                    <i class="bi bi-people-fill"></i> <?php echo $totalCount; ?> volontari attivi
                                </span>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Lista Volontari -->
                <?php if (empty($members)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle"></i> Nessun volontario trovato.
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped easyco-table">
                                    <thead>
                                        <tr>
                                            <th>Matricola</th>
                                            <th>Nome</th>
                                            <th>Data Nascita</th>
                                            <th>Contatti</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($members as $member): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($member['badge_number'])): ?>
                                                        <span class="badge bg-secondary">
                                                            <?php echo htmlspecialchars($member['badge_number']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/D</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong>
                                                        <?php echo htmlspecialchars($member['last_name']); ?> 
                                                        <?php echo htmlspecialchars($member['first_name']); ?>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($member['birth_date'])) {
                                                        echo date('d/m/Y', strtotime($member['birth_date']));
                                                    } else {
                                                        echo '<span class="text-muted">N/D</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($member['mobile'])): ?>
                                                        <i class="bi bi-phone"></i> 
                                                        <a href="tel:<?php echo htmlspecialchars($member['mobile']); ?>">
                                                            <?php echo htmlspecialchars($member['mobile']); ?>
                                                        </a>
                                                        <br>
                                                    <?php endif; ?>
                                                    <?php if (!empty($member['phone'])): ?>
                                                        <i class="bi bi-telephone"></i> 
                                                        <a href="tel:<?php echo htmlspecialchars($member['phone']); ?>">
                                                            <?php echo htmlspecialchars($member['phone']); ?>
                                                        </a>
                                                        <br>
                                                    <?php endif; ?>
                                                    <?php if (!empty($member['email'])): ?>
                                                        <i class="bi bi-envelope"></i> 
                                                        <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>">
                                                            <?php echo htmlspecialchars($member['email']); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (empty($member['mobile']) && empty($member['phone']) && empty($member['email'])): ?>
                                                        <span class="text-muted">N/D</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="operations_member_view.php?id=<?php echo $member['id']; ?>" 
                                                       class="btn btn-sm btn-outline-easyco-primary">
                                                        <i class="bi bi-eye"></i> Visualizza
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Paginazione -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-4" aria-label="Paginazione volontari">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
