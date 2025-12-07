<?php
use EasyVol\App;
$app = App::getInstance();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            
            <?php if ($app->checkPermission('members', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'members.php' ? 'active' : '' ?>" href="members.php">
                    <i class="bi bi-people"></i> Soci
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($app->checkPermission('junior_members', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'junior_members.php' ? 'active' : '' ?>" href="junior_members.php">
                    <i class="bi bi-person-badge"></i> Cadetti
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($app->checkPermission('applications', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'applications.php' ? 'active' : '' ?>" href="applications.php">
                    <i class="bi bi-inbox"></i> Domande Iscrizione
                    <?php
                    // Count pending applications
                    $pendingCount = $app->getDb()->fetchOne("SELECT COUNT(*) as count FROM member_applications WHERE status = 'pending'")['count'] ?? 0;
                    if ($pendingCount > 0):
                    ?>
                        <span class="badge bg-warning rounded-pill"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($app->checkPermission('members', 'edit')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'fee_payments.php' ? 'active' : '' ?>" href="fee_payments.php">
                    <i class="bi bi-receipt-cutoff"></i> Quote Associative
                    <?php
                    // Count pending fee payment requests
                    $pendingFeeCount = $app->getDb()->fetchOne("SELECT COUNT(*) as count FROM fee_payment_requests WHERE status = 'pending'")['count'] ?? 0;
                    if ($pendingFeeCount > 0):
                    ?>
                        <span class="badge bg-warning rounded-pill"><?= $pendingFeeCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($app->checkPermission('meetings', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'meetings.php' ? 'active' : '' ?>" href="meetings.php">
                    <i class="bi bi-calendar3"></i> Riunioni/Assemblee
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($app->checkPermission('vehicles', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'vehicles.php' ? 'active' : '' ?>" href="vehicles.php">
                    <i class="bi bi-truck"></i> Mezzi
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($app->checkPermission('warehouse', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'warehouse.php' ? 'active' : '' ?>" href="warehouse.php">
                    <i class="bi bi-box-seam"></i> Magazzino
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($app->checkPermission('training', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'training.php' ? 'active' : '' ?>" href="training.php">
                    <i class="bi bi-mortarboard"></i> Formazione
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($app->checkPermission('events', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'events.php' ? 'active' : '' ?>" href="events.php">
                    <i class="bi bi-calendar-event"></i> Eventi/Interventi
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($app->checkPermission('scheduler', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'scheduler.php' ? 'active' : '' ?>" href="scheduler.php">
                    <i class="bi bi-calendar-check"></i> Scadenzario
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($app->checkPermission('documents', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'documents.php' ? 'active' : '' ?>" href="documents.php">
                    <i class="bi bi-folder"></i> Documenti
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($app->checkPermission('operations_center', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'operations_center.php' ? 'active' : '' ?>" href="operations_center.php">
                    <i class="bi bi-broadcast"></i> Centrale Operativa
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Amministrazione</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <?php if ($app->checkPermission('users', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>" href="users.php">
                    <i class="bi bi-person-gear"></i> Utenti
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($app->checkPermission('reports', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="reports.php">
                    <i class="bi bi-file-earmark-bar-graph"></i> Report
                </a>
            </li>
            <?php endif; ?>
            
            <?php
            // Show Activity Logs only for admin users
            $user = $app->getCurrentUser();
            if (isset($user['role_name']) && $user['role_name'] === 'admin'):
            ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'activity_logs.php' ? 'active' : '' ?>" href="activity_logs.php">
                    <i class="bi bi-journal-text"></i> Registro Attività
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($app->checkPermission('settings', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>" href="settings.php">
                    <i class="bi bi-gear"></i> Impostazioni
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<style>
.sidebar {
    position: fixed;
    top: 56px;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 48px 0 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
    overflow-y: auto;
}

.sidebar .nav-link {
    font-weight: 500;
    color: #333;
    padding: 10px 15px;
    font-size: 0.9rem; /* Fixed size to prevent variation between pages */
}

.sidebar .nav-link:hover {
    background-color: #f8f9fa;
}

.sidebar .nav-link.active {
    color: #0d6efd;
    background-color: #e7f1ff;
}

.sidebar .nav-link i {
    margin-right: 8px;
    font-size: 1rem; /* Fixed icon size */
}

.sidebar-heading {
    font-size: .75rem;
    text-transform: uppercase;
}
</style>
