<?php
/**
 * Handler for junior member data operations (add/delete contacts, addresses, etc.)
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Models\JuniorMember;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('junior_members', 'edit')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$memberModel = new JuniorMember($db);

$action = $_GET['action'] ?? '';
$memberId = intval($_GET['member_id'] ?? 0);

if ($memberId <= 0) {
    header('Location: junior_members.php');
    exit;
}

try {
    switch ($action) {
        case 'add_contact':
            $type = $_GET['type'] ?? '';
            $value = trim($_GET['value'] ?? '');
            
            // Validate contact type
            $validTypes = ['telefono_fisso', 'cellulare', 'email'];
            if (!in_array($type, $validTypes)) {
                throw new \Exception('Tipo di contatto non valido');
            }
            
            // Validate value
            if (empty($value)) {
                throw new \Exception('Valore contatto obbligatorio');
            }
            
            // Validate email format for email types
            if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
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
            
        case 'delete_guardian':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteGuardian($id);
            }
            break;
            
        case 'delete_health':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteHealth($id);
            }
            break;
            
        case 'delete_sanction':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteSanction($id);
            }
            break;
            
        case 'delete_fee':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteFee($id);
            }
            break;
            
        case 'delete_note':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteNote($id);
            }
            break;
            
        case 'delete_health_surveillance':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $db->delete('junior_member_health_surveillance', ['id' => $id]);
            }
            break;
            
        case 'delete_attachment':
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $memberModel->deleteAttachment($id);
            }
            break;
    }
    
    header('Location: junior_member_view.php?id=' . $memberId . '&success=1');
    
} catch (\Exception $e) {
    error_log("Errore operazione socio minorenne: " . $e->getMessage());
    header('Location: junior_member_view.php?id=' . $memberId . '&error=' . urlencode($e->getMessage()));
}
exit;
