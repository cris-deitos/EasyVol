<?php
use EasyVol\App;
$app = App::getInstance();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse easyco-sidebar">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'operations_center.php' ? 'active' : '' ?>" href="operations_center.php">
                    <i class="bi bi-speedometer2"></i> Dashboard CO
                </a>
            </li>
            
            <?php if ($app->checkPermission('events', 'view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'events.php' ? 'active' : '' ?>" href="events.php">
                    <i class="bi bi-calendar-event"></i> Eventi
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link <?= in_array($currentPage, ['radio_directory.php', 'radio_view.php', 'radio_edit.php', 'radio_assign.php', 'radio_return.php']) ? 'active' : '' ?>" href="radio_directory.php">
                    <i class="bi bi-broadcast"></i> Radio Rubrica
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'operations_members.php' ? 'active' : '' ?>" href="operations_members.php">
                    <i class="bi bi-people"></i> Volontari Attivi
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'operations_vehicles.php' ? 'active' : '' ?>" href="operations_vehicles.php">
                    <i class="bi bi-truck"></i> Mezzi Attivi
                </a>
            </li>
        </ul>
    </div>
</nav>
