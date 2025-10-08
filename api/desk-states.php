<?php
require_once '../config/database.php';
require_once '../app/Models/DeskState.php';
require_once '../middleware/auth.php';

use App\Models\DeskState;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Verificar autenticación
    $user = authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
    
    $deskStateModel = new DeskState($pdo);
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    
    // Obtener parámetros de la URL
    $deskId = $_GET['desk_id'] ?? null;
    $stateId = $pathParts[count($pathParts) - 1] ?? null;
    
    switch ($method) {
        case 'GET':
            if ($stateId && is_numeric($stateId)) {
                // Obtener un estado específico
                $state = $deskStateModel->getState($stateId);
                if (!$state) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Estado no encontrado']);
                    exit;
                }
                
                // Verificar permisos del desk
                if (!hasPermission($user, 'desk_states.view') && $state['desk_id'] != $user['desk_id']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sin permisos para ver este estado']);
                    exit;
                }
                
                echo json_encode(['data' => $state]);
                
            } elseif ($deskId) {
                // Obtener estados de un desk
                if (!hasPermission($user, 'desk_states.view') && $deskId != $user['desk_id']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sin permisos para ver estados de este desk']);
                    exit;
                }
                
                $activeOnly = $_GET['active_only'] ?? true;
                $includeStats = $_GET['include_stats'] ?? false;
                
                if ($includeStats) {
                    $states = $deskStateModel->getStateStats($deskId);
                } else {
                    $states = $deskStateModel->getStatesByDesk($deskId, $activeOnly);
                }
                
                echo json_encode(['data' => $states]);
                
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'desk_id es requerido']);
            }
            break;
            
        case 'POST':
            // Crear nuevo estado
            if (!hasPermission($user, 'desk_states.create')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para crear estados']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos inválidos']);
                exit;
            }
            
            // Verificar que el usuario puede crear estados en este desk
            if (!hasPermission($user, 'admin') && $input['desk_id'] != $user['desk_id']) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para crear estados en este desk']);
                exit;
            }
            
            $input['created_by'] = $user['id'];
            
            try {
                $stateId = $deskStateModel->createState($input);
                $newState = $deskStateModel->getState($stateId);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Estado creado exitosamente',
                    'data' => $newState
                ]);
                
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
            
        case 'PUT':
            // Actualizar estado
            if (!$stateId || !is_numeric($stateId)) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de estado inválido']);
                exit;
            }
            
            if (!hasPermission($user, 'desk_states.edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para editar estados']);
                exit;
            }
            
            $state = $deskStateModel->getState($stateId);
            if (!$state) {
                http_response_code(404);
                echo json_encode(['error' => 'Estado no encontrado']);
                exit;
            }
            
            // Verificar permisos del desk
            if (!hasPermission($user, 'admin') && $state['desk_id'] != $user['desk_id']) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para editar este estado']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos inválidos']);
                exit;
            }
            
            try {
                $deskStateModel->updateState($stateId, $input);
                $updatedState = $deskStateModel->getState($stateId);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Estado actualizado exitosamente',
                    'data' => $updatedState
                ]);
                
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
            
        case 'DELETE':
            // Eliminar estado
            if (!$stateId || !is_numeric($stateId)) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de estado inválido']);
                exit;
            }
            
            if (!hasPermission($user, 'desk_states.delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para eliminar estados']);
                exit;
            }
            
            $state = $deskStateModel->getState($stateId);
            if (!$state) {
                http_response_code(404);
                echo json_encode(['error' => 'Estado no encontrado']);
                exit;
            }
            
            // Verificar permisos del desk
            if (!hasPermission($user, 'admin') && $state['desk_id'] != $user['desk_id']) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para eliminar este estado']);
                exit;
            }
            
            try {
                $deskStateModel->deleteState($stateId);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Estado eliminado exitosamente'
                ]);
                
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}

/**
 * Verificar si el usuario tiene un permiso específico
 */
function hasPermission($user, $permission) {
    // Si es admin, tiene todos los permisos
    if ($user['role'] === 'admin') {
        return true;
    }
    
    // Verificar permisos específicos del usuario
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM role_permissions rp
        INNER JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ? AND p.name = ?
    ");
    $stmt->execute([$user['role_id'], $permission]);
    
    return $stmt->fetchColumn() > 0;
}
?>