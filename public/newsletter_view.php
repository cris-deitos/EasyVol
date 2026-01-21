<?php
/**
 * Newsletter Management - View
 * 
 * Page to view newsletter details
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\NewsletterController;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

// Check authentication
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check permissions
if (!$app->checkPermission('newsletters', 'view')) {
    die('Accesso negato');
}

$newsletterId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$newsletterId) {
    header('Location: newsletters.php');
    exit;
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new NewsletterController($db, $config);

$newsletter = $controller->getById($newsletterId);
if (!$newsletter) {
    header('Location: newsletters.php?error=not_found');
    exit;
}

// Load attachments
$attachments = $db->fetchAll("SELECT * FROM newsletter_attachments WHERE newsletter_id = ?", [$newsletterId]);

// Load recipients
$recipients = $db->fetchAll("SELECT * FROM newsletter_recipients WHERE newsletter_id = ? ORDER BY email", [$newsletterId]);

// Decode recipient filter
$recipientFilter = json_decode($newsletter['recipient_filter'], true) ?? [];

$pageTitle = 'Dettagli Newsletter';
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
                    <h1 class="h2"><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($newsletter['status'] === 'draft' && $app->checkPermission('newsletters', 'edit')): ?>
                            <a href="newsletter_edit.php?id=<?php echo $newsletter['id']; ?>" class="btn btn-warning me-2">
                                <i class="bi bi-pencil"></i> Modifica
                            </a>
                        <?php endif; ?>
                        <?php if ($app->checkPermission('newsletters', 'create')): ?>
                            <a href="newsletter_edit.php?clone=<?php echo $newsletter['id']; ?>" class="btn btn-info me-2">
                                <i class="bi bi-files"></i> Clona
                            </a>
                        <?php endif; ?>
                        <a href="newsletters.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Torna all'elenco
                        </a>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <!-- Newsletter Details -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Dettagli Newsletter</h5>
                                <?php
                                $statusColors = [
                                    'draft' => 'secondary',
                                    'scheduled' => 'warning',
                                    'sent' => 'success',
                                    'failed' => 'danger'
                                ];
                                $statusLabels = [
                                    'draft' => 'Bozza',
                                    'scheduled' => 'Programmata',
                                    'sent' => 'Inviata',
                                    'failed' => 'Fallita'
                                ];
                                $status = $newsletter['status'];
                                ?>
                                <span class="badge bg-<?php echo $statusColors[$status] ?? 'secondary'; ?>">
                                    <?php echo $statusLabels[$status] ?? $status; ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-3">ID</dt>
                                    <dd class="col-sm-9"><?php echo $newsletter['id']; ?></dd>
                                    
                                    <dt class="col-sm-3">Oggetto</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($newsletter['subject']); ?></dd>
                                    
                                    <?php if ($newsletter['reply_to']): ?>
                                    <dt class="col-sm-3">Reply To</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($newsletter['reply_to']); ?></dd>
                                    <?php endif; ?>
                                    
                                    <dt class="col-sm-3">Creata da</dt>
                                    <dd class="col-sm-9">
                                        <?php echo htmlspecialchars($newsletter['created_by_name'] ?? 'N/D'); ?>
                                        <small class="text-muted">
                                            il <?php echo date('d/m/Y H:i', strtotime($newsletter['created_at'])); ?>
                                        </small>
                                    </dd>
                                    
                                    <?php if ($newsletter['sent_at']): ?>
                                    <dt class="col-sm-3">Inviata da</dt>
                                    <dd class="col-sm-9">
                                        <?php echo htmlspecialchars($newsletter['sent_by_name'] ?? 'N/D'); ?>
                                        <small class="text-muted">
                                            il <?php echo date('d/m/Y H:i', strtotime($newsletter['sent_at'])); ?>
                                        </small>
                                    </dd>
                                    <?php endif; ?>
                                    
                                    <?php if ($newsletter['scheduled_at']): ?>
                                    <dt class="col-sm-3">Programmata per</dt>
                                    <dd class="col-sm-9"><?php echo date('d/m/Y H:i', strtotime($newsletter['scheduled_at'])); ?></dd>
                                    <?php endif; ?>
                                    
                                    <?php if ($newsletter['cloned_from']): ?>
                                    <dt class="col-sm-3">Clonata da</dt>
                                    <dd class="col-sm-9">
                                        <a href="newsletter_view.php?id=<?php echo $newsletter['cloned_from']; ?>">
                                            Newsletter #<?php echo $newsletter['cloned_from']; ?>
                                        </a>
                                        <?php if ($newsletter['cloned_from_subject']): ?>
                                            <small class="text-muted">
                                                (<?php echo htmlspecialchars($newsletter['cloned_from_subject']); ?>)
                                            </small>
                                        <?php endif; ?>
                                    </dd>
                                    <?php endif; ?>
                                </dl>
                                
                                <hr>
                                
                                <h6>Contenuto HTML</h6>
                                <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                                    <?php echo $newsletter['body_html']; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Attachments -->
                        <?php if (!empty($attachments)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Allegati</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group">
                                    <?php foreach ($attachments as $attachment): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="bi bi-paperclip"></i>
                                                <?php echo htmlspecialchars($attachment['filename']); ?>
                                                <small class="text-muted">
                                                    (<?php echo number_format($attachment['filesize'] / 1024, 2); ?> KB)
                                                </small>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Recipient Info -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Informazioni Destinatari</h5>
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-6">Tipo Filtro:</dt>
                                    <dd class="col-sm-6">
                                        <?php
                                        $filterLabels = [
                                            'all_members' => 'Tutti i Soci',
                                            'all_cadets' => 'Tutti i Cadetti',
                                            'all_cadets_with_parents' => 'Cadetti + Genitori',
                                            'custom_members' => 'Soci Selezionati',
                                            'custom_cadets' => 'Cadetti Selezionati'
                                        ];
                                        echo $filterLabels[$recipientFilter['type'] ?? ''] ?? 'N/D';
                                        ?>
                                    </dd>
                                    
                                    <?php if ($newsletter['total_recipients'] > 0): ?>
                                    <dt class="col-sm-6">Totale:</dt>
                                    <dd class="col-sm-6"><?php echo $newsletter['total_recipients']; ?></dd>
                                    
                                    <dt class="col-sm-6">Inviati:</dt>
                                    <dd class="col-sm-6 text-success"><?php echo $newsletter['sent_count']; ?></dd>
                                    
                                    <dt class="col-sm-6">Falliti:</dt>
                                    <dd class="col-sm-6 text-danger"><?php echo $newsletter['failed_count']; ?></dd>
                                    <?php endif; ?>
                                </dl>
                            </div>
                        </div>
                        
                        <!-- Recipients List -->
                        <?php if (!empty($recipients)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Elenco Destinatari</h5>
                            </div>
                            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Email</th>
                                            <th>Nome</th>
                                            <th>Stato</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recipients as $recipient): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($recipient['email']); ?></td>
                                                <td><?php echo htmlspecialchars($recipient['recipient_name'] ?? '-'); ?></td>
                                                <td>
                                                    <?php
                                                    $recipientStatusColors = [
                                                        'pending' => 'warning',
                                                        'sent' => 'success',
                                                        'failed' => 'danger'
                                                    ];
                                                    $recipientStatusLabels = [
                                                        'pending' => 'In coda',
                                                        'sent' => 'Inviato',
                                                        'failed' => 'Fallito'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?php echo $recipientStatusColors[$recipient['status']] ?? 'secondary'; ?>">
                                                        <?php echo $recipientStatusLabels[$recipient['status']] ?? $recipient['status']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
