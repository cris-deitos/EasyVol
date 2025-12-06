<?php
use EasyVol\App;
$app = App::getInstance();
$user = $app->getCurrentUser();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="/public/dashboard.php">
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
                        <span class="badge bg-danger">3</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Notifiche</h6></li>
                        <li><a class="dropdown-item" href="#">Nuova domanda di iscrizione</a></li>
                        <li><a class="dropdown-item" href="#">Scadenza revisione mezzo</a></li>
                        <li><a class="dropdown-item" href="#">Prossima assemblea</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="#">Vedi tutte</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/public/profile.php"><i class="bi bi-person"></i> Profilo</a></li>
                        <li><a class="dropdown-item" href="/public/settings.php"><i class="bi bi-gear"></i> Impostazioni</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/public/logout.php"><i class="bi bi-box-arrow-right"></i> Esci</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
