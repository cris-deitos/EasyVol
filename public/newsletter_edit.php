<?php
/**
 * Newsletter Management - Edit/Create
 * 
 * Page to create or edit a newsletter
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\NewsletterController;
use EasyVol\Utils\AutoLogger;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Check authentication
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$newsletterId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$cloneId = isset($_GET['clone']) ? intval($_GET['clone']) : 0;
$isEdit = $newsletterId > 0;
$isClone = $cloneId > 0;

// Check permissions
if ($isEdit && !$app->checkPermission('newsletters', 'edit')) {
    die('Accesso negato');
}
if (!$isEdit && !$app->checkPermission('newsletters', 'create')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new NewsletterController($db, $config);

$newsletter = [];
$errors = [];
$success = false;
$attachments = [];

// If editing, load existing data
if ($isEdit) {
    $newsletter = $controller->getById($newsletterId);
    if (!$newsletter) {
        header('Location: newsletters.php?error=not_found');
        exit;
    }
    
    // Check if it's still a draft
    if ($newsletter['status'] !== 'draft') {
        header('Location: newsletter_view.php?id=' . $newsletterId);
        exit;
    }
    
    // Load attachments
    $attachments = $db->query("SELECT * FROM newsletter_attachments WHERE newsletter_id = ?", [$newsletterId]);
    
    // Decode recipient filter
    $newsletter['recipient_filter_decoded'] = json_decode($newsletter['recipient_filter'], true) ?? [];
}

// If cloning, load original data
if ($isClone && !$isEdit) {
    $original = $controller->getById($cloneId);
    if ($original) {
        $newsletter = $original;
        $newsletter['subject'] = $original['subject'] . ' (Clone)';
        $newsletter['recipient_filter_decoded'] = json_decode($original['recipient_filter'], true) ?? [];
        $newsletter['status'] = 'draft';
        unset($newsletter['id']);
    }
}

// Get available recipients
$availableRecipients = $controller->getAvailableRecipients();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'subject' => trim($_POST['subject'] ?? ''),
            'body_html' => $_POST['body_html'] ?? '',
            'reply_to' => trim($_POST['reply_to'] ?? ''),
            'status' => $_POST['save_as'] ?? 'draft',
            'scheduled_at' => null,
            'recipient_filter' => [],
            'created_by' => $_SESSION['user_id']
        ];
        
        // Validate required fields
        if (empty($data['subject'])) {
            $errors[] = 'L\'oggetto è obbligatorio';
        }
        if (empty($data['body_html'])) {
            $errors[] = 'Il corpo del messaggio è obbligatorio';
        }
        
        // Handle recipient filter
        $recipientType = $_POST['recipient_type'] ?? '';
        $data['recipient_filter'] = ['type' => $recipientType];
        
        if ($recipientType === 'custom_members' || $recipientType === 'custom_cadets') {
            $data['recipient_filter']['ids'] = $_POST['recipient_ids'] ?? [];
        }
        
        // Handle scheduled send
        if (!empty($_POST['schedule_date']) && !empty($_POST['schedule_time'])) {
            $data['scheduled_at'] = $_POST['schedule_date'] . ' ' . $_POST['schedule_time'] . ':00';
        }
        
        if (empty($errors)) {
            try {
                $result = $controller->save($data, $isEdit ? $newsletterId : null);
                
                if ($result['success']) {
                    $savedId = $result['id'];
                    
                    // Handle file uploads
                    if (!empty($_FILES['attachments']['name'][0])) {
                        foreach ($_FILES['attachments']['name'] as $key => $fileName) {
                            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                                $file = [
                                    'name' => $_FILES['attachments']['name'][$key],
                                    'type' => $_FILES['attachments']['type'][$key],
                                    'tmp_name' => $_FILES['attachments']['tmp_name'][$key],
                                    'error' => $_FILES['attachments']['error'][$key],
                                    'size' => $_FILES['attachments']['size'][$key]
                                ];
                                
                                $uploadResult = $controller->uploadAttachment($savedId, $file);
                                if (!$uploadResult['success']) {
                                    $errors[] = $uploadResult['message'];
                                }
                            }
                        }
                    }
                    
                    // If sending or scheduling
                    if ($data['status'] === 'scheduled' || isset($_POST['send_now'])) {
                        $sendResult = $controller->send($savedId, $data['scheduled_at']);
                        if ($sendResult['success']) {
                            header('Location: newsletters.php?success=sent');
                            exit;
                        } else {
                            $errors[] = $sendResult['message'];
                        }
                    } else {
                        // Just saving as draft
                        header('Location: newsletters.php?success=saved');
                        exit;
                    }
                } else {
                    $errors[] = $result['message'];
                }
            } catch (\Exception $e) {
                error_log("Newsletter save error: " . $e->getMessage());
                $errors[] = 'Errore durante il salvataggio: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = $isEdit ? 'Modifica Newsletter' : ($isClone ? 'Clone Newsletter' : 'Nuova Newsletter');
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
    <!-- TinyMCE Editor - NOTE: Replace 'no-api-key' with a valid TinyMCE API key for production use -->
    <script src="https://cdn.tiny.cloud/1/svhvbvqwcchk5enuxule1zzpw3zpm3rvldernny7t3vwh22j/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
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
                        <a href="newsletters.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Torna all'elenco
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <h5 class="alert-heading">Errori:</h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        Newsletter salvata con successo!
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data" id="newsletterForm">
                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Newsletter Content -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Contenuto Newsletter</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="subject" class="form-label">Oggetto *</label>
                                        <input type="text" class="form-control" id="subject" name="subject" 
                                               value="<?php echo htmlspecialchars($newsletter['subject'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="reply_to" class="form-label">Reply To (Email di Risposta)</label>
                                        <input type="email" class="form-control" id="reply_to" name="reply_to" 
                                               value="<?php echo htmlspecialchars($newsletter['reply_to'] ?? ''); ?>" 
                                               placeholder="info@esempio.it">
                                        <small class="form-text text-muted">Se non specificato, verrà usato l'indirizzo email configurato nel sistema</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="body_html" class="form-label">Contenuto HTML *</label>
                                        <textarea id="body_html" name="body_html" class="form-control"><?php echo htmlspecialchars($newsletter['body_html'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="attachments" class="form-label">Allegati</label>
                                        <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                                        <small class="form-text text-muted">Massimo 10MB per file</small>
                                        
                                        <?php if (!empty($attachments)): ?>
                                            <div class="mt-3">
                                                <h6>Allegati esistenti:</h6>
                                                <ul class="list-group">
                                                    <?php foreach ($attachments as $attachment): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                            <span>
                                                                <i class="bi bi-paperclip"></i>
                                                                <?php echo htmlspecialchars($attachment['filename']); ?>
                                                                <small class="text-muted">(<?php echo number_format($attachment['filesize'] / 1024, 2); ?> KB)</small>
                                                            </span>
                                                            <button type="button" class="btn btn-sm btn-danger" 
                                                                    onclick="deleteAttachment(<?php echo $attachment['id']; ?>)">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Recipients -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Destinatari</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="recipient_type" class="form-label">Seleziona Destinatari *</label>
                                        <select class="form-select" id="recipient_type" name="recipient_type" required onchange="toggleRecipientSelection()">
                                            <option value="">Seleziona...</option>
                                            <option value="all_members" <?php echo ($newsletter['recipient_filter_decoded']['type'] ?? '') === 'all_members' ? 'selected' : ''; ?>>
                                                Tutti i Soci Attivi
                                            </option>
                                            <option value="all_cadets" <?php echo ($newsletter['recipient_filter_decoded']['type'] ?? '') === 'all_cadets' ? 'selected' : ''; ?>>
                                                Tutti i Cadetti Attivi
                                            </option>
                                            <option value="all_cadets_with_parents" <?php echo ($newsletter['recipient_filter_decoded']['type'] ?? '') === 'all_cadets_with_parents' ? 'selected' : ''; ?>>
                                                Tutti i Cadetti Attivi + Genitori/Tutori
                                            </option>
                                            <option value="custom_members" <?php echo ($newsletter['recipient_filter_decoded']['type'] ?? '') === 'custom_members' ? 'selected' : ''; ?>>
                                                Soci Selezionati
                                            </option>
                                            <option value="custom_cadets" <?php echo ($newsletter['recipient_filter_decoded']['type'] ?? '') === 'custom_cadets' ? 'selected' : ''; ?>>
                                                Cadetti Selezionati
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <!-- Custom Member Selection -->
                                    <div id="custom_members_selection" style="display: none;">
                                        <label class="form-label">Seleziona Soci</label>
                                        <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px; border-radius: 5px;">
                                            <?php foreach ($availableRecipients['members'] as $member): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="recipient_ids[]" 
                                                           value="<?php echo $member['id']; ?>" 
                                                           id="member_<?php echo $member['id']; ?>">
                                                    <label class="form-check-label" for="member_<?php echo $member['id']; ?>">
                                                        <?php echo htmlspecialchars($member['name']); ?>
                                                        <small class="text-muted">(<?php echo htmlspecialchars($member['email']); ?>)</small>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Custom Cadet Selection -->
                                    <div id="custom_cadets_selection" style="display: none;">
                                        <label class="form-label">Seleziona Cadetti</label>
                                        <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px; border-radius: 5px;">
                                            <?php foreach ($availableRecipients['junior_members'] as $cadet): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="recipient_ids[]" 
                                                           value="<?php echo $cadet['id']; ?>" 
                                                           id="cadet_<?php echo $cadet['id']; ?>">
                                                    <label class="form-check-label" for="cadet_<?php echo $cadet['id']; ?>">
                                                        <?php echo htmlspecialchars($cadet['name']); ?>
                                                        <small class="text-muted">(<?php echo htmlspecialchars($cadet['email']); ?>)</small>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Send Options -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Opzioni Invio</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="send_option" id="send_draft" value="draft" checked onchange="toggleSchedule()">
                                            <label class="form-check-label" for="send_draft">
                                                Salva come Bozza
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="send_option" id="send_now" value="now" onchange="toggleSchedule()">
                                            <label class="form-check-label" for="send_now">
                                                Invia Immediatamente
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="send_option" id="send_scheduled" value="scheduled" onchange="toggleSchedule()">
                                            <label class="form-check-label" for="send_scheduled">
                                                Programma Invio
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div id="schedule_fields" style="display: none;">
                                        <div class="mb-3">
                                            <label for="schedule_date" class="form-label">Data Invio</label>
                                            <input type="date" class="form-control" id="schedule_date" name="schedule_date" 
                                                   min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="schedule_time" class="form-label">Ora Invio</label>
                                            <input type="time" class="form-control" id="schedule_time" name="schedule_time">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="save_as" value="draft" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Salva
                                        </button>
                                        <a href="newsletters.php" class="btn btn-secondary">
                                            <i class="bi bi-x-circle"></i> Annulla
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Initialize TinyMCE
    tinymce.init({
        selector: '#body_html',
        height: 500,
        menubar: false,
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount'
        ],
        toolbar: 'undo redo | blocks | ' +
            'bold italic forecolor | alignleft aligncenter ' +
            'alignright alignjustify | bullist numlist outdent indent | ' +
            'removeformat | help',
        content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
    });
    
    function toggleRecipientSelection() {
        const recipientType = document.getElementById('recipient_type').value;
        document.getElementById('custom_members_selection').style.display = 
            recipientType === 'custom_members' ? 'block' : 'none';
        document.getElementById('custom_cadets_selection').style.display = 
            recipientType === 'custom_cadets' ? 'block' : 'none';
    }
    
    function toggleSchedule() {
        const sendOption = document.querySelector('input[name="send_option"]:checked').value;
        const scheduleFields = document.getElementById('schedule_fields');
        
        if (sendOption === 'scheduled') {
            scheduleFields.style.display = 'block';
            document.getElementById('schedule_date').required = true;
            document.getElementById('schedule_time').required = true;
        } else {
            scheduleFields.style.display = 'none';
            document.getElementById('schedule_date').required = false;
            document.getElementById('schedule_time').required = false;
        }
    }
    
    function deleteAttachment(attachmentId) {
        if (!confirm('Sei sicuro di voler eliminare questo allegato?')) {
            return;
        }
        
        fetch('newsletter_attachment_delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: attachmentId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert('Errore: ' + data.message);
            }
        })
        .catch(error => {
            alert('Errore durante l\'eliminazione');
            console.error(error);
        });
    }
    
    // Form submission handling
    document.getElementById('newsletterForm').addEventListener('submit', function(e) {
        const sendOption = document.querySelector('input[name="send_option"]:checked').value;
        
        if (sendOption === 'now') {
            if (!confirm('Sei sicuro di voler inviare immediatamente questa newsletter?')) {
                e.preventDefault();
                return false;
            }
            // Add hidden field to indicate immediate send
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'send_now';
            input.value = '1';
            this.appendChild(input);
        } else if (sendOption === 'scheduled') {
            const scheduleDate = document.getElementById('schedule_date').value;
            const scheduleTime = document.getElementById('schedule_time').value;
            
            if (!scheduleDate || !scheduleTime) {
                alert('Per programmare l\'invio, specifica data e ora');
                e.preventDefault();
                return false;
            }
            
            if (!confirm('Sei sicuro di voler programmare l\'invio di questa newsletter per il ' + scheduleDate + ' alle ' + scheduleTime + '?')) {
                e.preventDefault();
                return false;
            }
            
            // Set save_as to scheduled
            document.querySelector('button[name="save_as"]').value = 'scheduled';
        }
    });
    
    // Initialize on page load
    toggleRecipientSelection();
    toggleSchedule();
    </script>
</body>
</html>
