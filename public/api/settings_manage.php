<?php
/**
 * API endpoint for managing settings data (qualifications and course types)
 */

require_once __DIR__ . '/../../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Middleware\CsrfProtection;

header('Content-Type: application/json');

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

$db = $app->getDb();
$action = $_GET['action'] ?? '';
$type = $_GET['type'] ?? ''; // 'qualifications' or 'course-types'

try {
    // List items
    if ($action === 'list') {
        if ($type === 'qualifications') {
            $sql = "SELECT id, name, description, sort_order, is_active FROM member_qualification_types ORDER BY sort_order ASC, name ASC";
            $items = $db->fetchAll($sql);
            echo json_encode(['success' => true, 'data' => $items]);
        } elseif ($type === 'course-types') {
            $sql = "SELECT id, code, name, category, description, sort_order, is_active FROM training_course_types ORDER BY sort_order ASC, code ASC";
            $items = $db->fetchAll($sql);
            echo json_encode(['success' => true, 'data' => $items]);
        } else {
            throw new Exception('Tipo non valido');
        }
    }
    // Get single item
    elseif ($action === 'get') {
        if (!$app->checkPermission('settings', 'view')) {
            throw new Exception('Permesso negato');
        }
        
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception('ID non valido');
        }
        
        if ($type === 'qualifications') {
            $sql = "SELECT id, name, description, sort_order, is_active FROM member_qualification_types WHERE id = ?";
            $item = $db->fetchOne($sql, [$id]);
        } elseif ($type === 'course-types') {
            $sql = "SELECT id, code, name, category, description, sort_order, is_active FROM training_course_types WHERE id = ?";
            $item = $db->fetchOne($sql, [$id]);
        } else {
            throw new Exception('Tipo non valido');
        }
        
        if (!$item) {
            throw new Exception('Elemento non trovato');
        }
        
        echo json_encode(['success' => true, 'data' => $item]);
    }
    // Create or update item
    elseif ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$app->checkPermission('settings', 'edit')) {
            throw new Exception('Permesso negato');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            throw new Exception('Dati non validi');
        }
        
        $id = (int)($input['id'] ?? 0);
        
        if ($type === 'qualifications') {
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $is_active = !empty($input['is_active']) ? 1 : 0;
            
            if (empty($name)) {
                throw new Exception('Il nome è obbligatorio');
            }
            
            if ($id > 0) {
                // Update
                $sql = "UPDATE member_qualification_types SET name = ?, description = ?, is_active = ?, updated_at = NOW() WHERE id = ?";
                $db->execute($sql, [$name, $description, $is_active, $id]);
                echo json_encode(['success' => true, 'message' => 'Qualifica aggiornata con successo']);
            } else {
                // Insert - get max sort_order
                $maxOrder = $db->fetchOne("SELECT MAX(sort_order) as max_order FROM member_qualification_types");
                $sortOrder = ($maxOrder['max_order'] ?? 0) + 10;
                
                $sql = "INSERT INTO member_qualification_types (name, description, sort_order, is_active) VALUES (?, ?, ?, ?)";
                $db->execute($sql, [$name, $description, $sortOrder, $is_active]);
                echo json_encode(['success' => true, 'message' => 'Qualifica creata con successo']);
            }
        } elseif ($type === 'course-types') {
            $code = trim($input['code'] ?? '');
            $name = trim($input['name'] ?? '');
            $category = trim($input['category'] ?? '');
            $description = trim($input['description'] ?? '');
            $is_active = !empty($input['is_active']) ? 1 : 0;
            
            if (empty($code) || empty($name)) {
                throw new Exception('Codice e nome sono obbligatori');
            }
            
            if ($id > 0) {
                // Update
                $sql = "UPDATE training_course_types SET code = ?, name = ?, category = ?, description = ?, is_active = ?, updated_at = NOW() WHERE id = ?";
                $db->execute($sql, [$code, $name, $category, $description, $is_active, $id]);
                echo json_encode(['success' => true, 'message' => 'Tipo corso aggiornato con successo']);
            } else {
                // Insert - get max sort_order
                $maxOrder = $db->fetchOne("SELECT MAX(sort_order) as max_order FROM training_course_types");
                $sortOrder = ($maxOrder['max_order'] ?? 0) + 10;
                
                $sql = "INSERT INTO training_course_types (code, name, category, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)";
                $db->execute($sql, [$code, $name, $category, $description, $sortOrder, $is_active]);
                echo json_encode(['success' => true, 'message' => 'Tipo corso creato con successo']);
            }
        } else {
            throw new Exception('Tipo non valido');
        }
    }
    // Delete item
    elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$app->checkPermission('settings', 'edit')) {
            throw new Exception('Permesso negato');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        
        if ($id <= 0) {
            throw new Exception('ID non valido');
        }
        
        if ($type === 'qualifications') {
            // Check if used in member_roles (case-insensitive with trimming)
            $qualType = $db->fetchOne("SELECT name FROM member_qualification_types WHERE id = ?", [$id]);
            if ($qualType) {
                $used = $db->fetchOne("SELECT COUNT(*) as cnt FROM member_roles 
                                       WHERE TRIM(LOWER(role_name)) = TRIM(LOWER(?))", [$qualType['name']]);
                if ($used && $used['cnt'] > 0) {
                    throw new Exception('Impossibile eliminare: qualifica utilizzata da ' . $used['cnt'] . ' soci');
                }
            }
            
            $sql = "DELETE FROM member_qualification_types WHERE id = ?";
            $db->execute($sql, [$id]);
            echo json_encode(['success' => true, 'message' => 'Qualifica eliminata con successo']);
        } elseif ($type === 'course-types') {
            // Check if used in training_courses (case-insensitive with trimming)
            $courseType = $db->fetchOne("SELECT code FROM training_course_types WHERE id = ?", [$id]);
            if ($courseType) {
                $used = $db->fetchOne("SELECT COUNT(*) as cnt FROM training_courses 
                                       WHERE TRIM(LOWER(course_type)) = TRIM(LOWER(?))", [$courseType['code']]);
                if ($used && $used['cnt'] > 0) {
                    throw new Exception('Impossibile eliminare: tipo corso utilizzato in ' . $used['cnt'] . ' corsi');
                }
            }
            
            $sql = "DELETE FROM training_course_types WHERE id = ?";
            $db->execute($sql, [$id]);
            echo json_encode(['success' => true, 'message' => 'Tipo corso eliminato con successo']);
        } else {
            throw new Exception('Tipo non valido');
        }
    }
    // Update sort order
    elseif ($action === 'reorder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$app->checkPermission('settings', 'edit')) {
            throw new Exception('Permesso negato');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $items = $input['items'] ?? [];
        
        if (empty($items) || !is_array($items)) {
            throw new Exception('Dati non validi');
        }
        
        $db->getConnection()->beginTransaction();
        
        try {
            if ($type === 'qualifications') {
                $sql = "UPDATE member_qualification_types SET sort_order = ? WHERE id = ?";
                foreach ($items as $index => $id) {
                    $sortOrder = ($index + 1) * 10;
                    $db->execute($sql, [$sortOrder, (int)$id]);
                }
            } elseif ($type === 'course-types') {
                $sql = "UPDATE training_course_types SET sort_order = ? WHERE id = ?";
                foreach ($items as $index => $id) {
                    $sortOrder = ($index + 1) * 10;
                    $db->execute($sql, [$sortOrder, (int)$id]);
                }
            } else {
                throw new Exception('Tipo non valido');
            }
            
            $db->getConnection()->commit();
            echo json_encode(['success' => true, 'message' => 'Ordine aggiornato con successo']);
        } catch (Exception $e) {
            $db->getConnection()->rollBack();
            throw $e;
        }
    }
    else {
        throw new Exception('Azione non valida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
