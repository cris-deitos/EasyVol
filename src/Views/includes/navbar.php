<?php
use EasyVol\App;
$app = App::getInstance();
$user = $app->getCurrentUser();

// Get unread notifications count
$db = $app->getDb();
$notificationsCount = 0;
$notifications = [];
try {
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", [$user['id']]);
    $notificationsCount = $result['count'] ?? 0;
    
    // Get recent notifications (max 5)
    if ($notificationsCount > 0) {
        $notifications = $db->fetchAll(
            "SELECT id, title, message, created_at FROM notifications 
            WHERE user_id = ? AND is_read = 0 
            ORDER BY created_at DESC LIMIT 5",
            [$user['id']]
        );
    }
} catch (\Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
}
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
                        <?php if ($notificationsCount > 0): ?>
                            <span class="badge bg-danger"><?= $notificationsCount ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 300px;">
                        <li><h6 class="dropdown-header">Notifiche <?php if ($notificationsCount > 0): ?>(<?= $notificationsCount ?>)<?php endif; ?></h6></li>
                        <?php if (empty($notifications)): ?>
                            <li><span class="dropdown-item text-muted">Nessuna notifica</span></li>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <li>
                                    <a class="dropdown-item" href="#" onclick="markNotificationRead(<?= $notification['id'] ?>); return false;">
                                        <strong><?= htmlspecialchars($notification['title']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars(substr($notification['message'], 0, 50)) ?><?= strlen($notification['message']) > 50 ? '...' : '' ?></small><br>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?></small>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="#" onclick="markAllNotificationsRead(); return false;"><small>Segna tutte come lette</small></a></li>
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

<script>
function markNotificationRead(notificationId) {
    fetch('/api/notifications/mark-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ notification_id: notificationId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAllNotificationsRead() {
    fetch('/api/notifications/mark-all-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>
