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
            $value = $_GET['value'] ?? '';
            if ($type && $value) {
                $memberModel->addContact($memberId, [
                    'contact_type' => $type,
                    'value' => $value
                ]);
            }
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
    }
    
    header('Location: member_view.php?id=' . $memberId . '&success=1');
    
} catch (\Exception $e) {
    error_log("Errore operazione socio: " . $e->getMessage());
    header('Location: member_view.php?id=' . $memberId . '&error=' . urlencode($e->getMessage()));
}
exit;
