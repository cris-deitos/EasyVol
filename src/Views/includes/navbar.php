<?php
use EasyVol\App;
$app = App::getInstance();
$user = $app->getCurrentUser();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-heart-pulse text-danger"></i> <strong>EasyVol</strong>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <?php
                    // Get notification counts
                    $notificationCount = 0;
                    $notifications = [];
                    
                    // Pending applications
                    if ($app->checkPermission('applications', 'view')) {
                        $pendingApps = $app->getDb()->fetchOne("SELECT COUNT(*) as count FROM member_applications WHERE status = 'pending'")['count'] ?? 0;
                        if ($pendingApps > 0) {
                            $notificationCount += $pendingApps;
                            $notifications[] = [
                                'text' => "Domande iscrizione in sospeso: $pendingApps",
                                'link' => 'applications.php',
                                'icon' => 'bi-inbox'
                            ];
                        }
                    }
                    
                    // Pending fee payments
                    if ($app->checkPermission('members', 'edit')) {
                        $pendingFees = $app->getDb()->fetchOne("SELECT COUNT(*) as count FROM fee_payment_requests WHERE status = 'pending'")['count'] ?? 0;
                        if ($pendingFees > 0) {
                            $notificationCount += $pendingFees;
                            $notifications[] = [
                                'text' => "Quote associative da verificare: $pendingFees",
                                'link' => 'fee_payments.php',
                                'icon' => 'bi-receipt-cutoff'
                            ];
                        }
                    }
                    ?>
                    <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell"></i>
                        <?php if ($notificationCount > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?= $notificationCount ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 300px;">
                        <li><h6 class="dropdown-header">Notifiche</h6></li>
                        <?php if (empty($notifications)): ?>
                            <li><a class="dropdown-item text-center text-muted">Nessuna notifica</a></li>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <li>
                                    <a class="dropdown-item" href="<?= htmlspecialchars($notification['link']) ?>">
                                        <i class="bi <?= htmlspecialchars($notification['icon']) ?>"></i>
                                        <?= htmlspecialchars($notification['text']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center text-primary" href="dashboard.php">Vedi tutte</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profilo</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear"></i> Impostazioni</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Esci</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
