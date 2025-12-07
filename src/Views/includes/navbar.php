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
                    <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell"></i>
                        <!-- Notification badge removed - notifications will be implemented dynamically -->
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Notifiche</h6></li>
                        <li><a class="dropdown-item text-center text-muted">Nessuna notifica</a></li>
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
