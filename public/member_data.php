<?php
/**
 * Handler for member data operations (add/delete contacts, addresses, etc.)
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Models\Member;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('members', 'edit')) {
    die('Accesso negato');
}

$db = $app->getDb();
$memberModel = new Member($db);

$action = $_GET['action'] ?? '';
$memberId = intval($_GET['member_id'] ?? 0);

if ($memberId <= 0) {
    header('Location: members.php');
    exit;
}

try {
    switch ($action) {
        case 'add_contact':
            $type = $_GET['type'] ?? '';
            $value = trim($_GET['value'] ?? '');
            
            // Validate contact type
            $validTypes = ['telefono_fisso', 'cellulare', 'email', 'pec'];
            if (!in_array($type, $validTypes)) {
                throw new \Exception('Tipo di contatto non valido');
            }
            
            // Validate value
            if (empty($value)) {
                throw new \Exception('Valore contatto obbligatorio');
            }
            
            // Validate email format for email types
            if (in_array($type, ['email', 'pec']) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Formato email non valido');
            }
            
            $memberModel->addContact($memberId, [
                'contact_type' => $type,
                'value' => $value
            ]);
            break;
            
        case 'delete_contact':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteContact($id);
            }
            break;
            
        case 'delete_address':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteAddress($id);
            }
            break;
            
        case 'delete_employment':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteEmployment($id);
            }
            break;
            
        case 'delete_role':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteRole($id);
            }
            break;
            
        case 'delete_course':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteCourse($id);
            }
            break;
            
        case 'delete_license':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteLicense($id);
            }
            break;
            
        case 'delete_health':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteHealth($id);
            }
            break;
            
        case 'delete_availability':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteAvailability($id);
            }
            break;
            
        case 'delete_fee':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteFee($id);
            }
            break;
            
        case 'delete_sanction':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteSanction($id);
            }
            break;
            
        case 'delete_note':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteNote($id);
            }
            break;
            
        case 'delete_attachment':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                // Get attachment details to delete file
                $attachments = $memberModel->getAttachments($memberId);
                foreach ($attachments as $att) {
                    if ($att['id'] == $id) {
                        // Delete physical file
                        $filePath = __DIR__ . '/' . $att['file_path'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        break;
                    }
                }
                $memberModel->deleteAttachment($id);
            }
            break;
    }
    
    header('Location: member_view.php?id=' . $memberId . '&success=1');
    
} catch (\Exception $e) {
    error_log("Errore operazione socio: " . $e->getMessage());
    header('Location: member_view.php?id=' . $memberId . '&error=' . urlencode($e->getMessage()));
}
exit;
