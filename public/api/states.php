<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Models/DeskState.php';
require_once __DIR__ . '/../../src/Models/StateTransition.php';
// Usar conexión compartida del proyecto y modelos con namespace
use iaTradeCRM\Database\Connection;
use IaTradeCRM\Models\DeskState as DeskStateModel;
use IaTradeCRM\Models\StateTransition as StateTransitionModel;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Inicializar PDO y repositorios
$pdo = Connection::getInstance()->getConnection();
$deskStatesRepo = new DeskStateModel($pdo);
$transitionsRepo = new StateTransitionModel($pdo);

// JWT Authentication
function authenticateUser() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Token no proporcionado']);
        exit();
    }
    
    $token = $matches[1];
    $secretKey = $_ENV['JWT_SECRET'] ?? 'your-secret-key';
    
    try {
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido']);
        exit();
    }
}

// Check permissions
function checkPermission($user, $permission) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_roles ur 
        JOIN role_permissions rp ON ur.role_id = rp.role_id 
        JOIN permissions p ON rp.permission_id = p.id 
        WHERE ur.user_id = ? AND p.name = ?
    ");
    $stmt->execute([$user->user_id, $permission]);
    
    return $stmt->fetchColumn() > 0;
}

$user = authenticateUser();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'list':
                        // Get states for user's desk
                        if (!checkPermission($user, 'view_states')) {
                            http_response_code(403);
                            echo json_encode(['error' => 'Sin permisos para ver estados']);
                            exit();
                        }
                        
                        $deskId = $_GET['desk_id'] ?? $user->desk_id;
                        $states = $deskStatesRepo->getStatesByDesk($deskId);
                        echo json_encode(['success' => true, 'data' => $states]);
                        break;
                        
                    case 'transitions':
                        // Get available transitions for a state
                        $fromStateId = $_GET['from_state_id'] ?? null;
                        $deskId = $_GET['desk_id'] ?? $user->desk_id;
                        
                        if (!$fromStateId) {
                            http_response_code(400);
                            echo json_encode(['error' => 'Estado origen requerido']);
                            exit();
                        }
                        
                        $transitions = $transitionsRepo->getAvailableTransitions($fromStateId, null);
                        echo json_encode(['success' => true, 'data' => $transitions]);
                        break;
                        
                    case 'templates':
                        // Get state templates for creating new states
                        if (!checkPermission($user, 'manage_states')) {
                            http_response_code(403);
                            echo json_encode(['error' => 'Sin permisos para gestionar estados']);
                            exit();
                        }
                        
                        try {
                            $stmt = $pdo->query("SELECT * FROM state_templates ORDER BY category, name");
                            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            echo json_encode(['success' => true, 'data' => $templates]);
                        } catch (Exception $e) {
                            // Si la tabla no existe o hay error, devolver lista vacía y detalle
                            echo json_encode(['success' => true, 'data' => [], 'message' => 'Sin plantillas disponibles']);
                        }
                        break;
                        
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Acción no válida']);
                }
            } else {
                // Get single state
                $stateId = $_GET['id'] ?? null;
                if (!$stateId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de estado requerido']);
                    exit();
                }
                
                $state = $deskStatesRepo->getState($stateId);
                if (!$state) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Estado no encontrado']);
                    exit();
                }
                
                echo json_encode(['success' => true, 'data' => $state]);
            }
            break;
            
        case 'POST':
            if (!checkPermission($user, 'manage_states')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para crear estados']);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (isset($_GET['action']) && $_GET['action'] === 'reorder') {
                // Reorder states
                $stateIds = $input['state_ids'] ?? [];
                $deskId = $input['desk_id'] ?? $user->desk_id;
                
                $result = $deskStatesRepo->reorderStates($deskId, $stateIds);
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Estados reordenados exitosamente']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Error al reordenar estados']);
                }
            } else {
                // Create new state
                $name = $input['name'] ?? '';
                $color = $input['color'] ?? '#6B7280';
                $description = $input['description'] ?? '';
                $deskId = $input['desk_id'] ?? $user->desk_id;
                $templateId = $input['template_id'] ?? null;
                
                if (empty($name)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Nombre del estado requerido']);
                    exit();
                }
                
                $data = [
                    'desk_id' => (int)$deskId,
                    'name' => $name,
                    'display_name' => $input['display_name'] ?? $name,
                    'description' => $description ?: null,
                    'color' => $color,
                    'created_by' => $user->user_id ?? null,
                ];
                foreach (['icon','is_initial','is_final','is_active','sort_order'] as $opt) {
                    if (isset($input[$opt])) { $data[$opt] = $input[$opt]; }
                }
                $stateId = $deskStatesRepo->createState($data);
                if ($stateId) {
                    $state = $deskStatesRepo->getState($stateId);
                    echo json_encode(['success' => true, 'data' => $state, 'message' => 'Estado creado exitosamente']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Error al crear estado']);
                }
            }
            break;
            
        case 'PUT':
            if (!checkPermission($user, 'manage_states')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para actualizar estados']);
                exit();
            }
            
            $stateId = $_GET['id'] ?? null;
            if (!$stateId) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de estado requerido']);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $result = $deskStatesRepo->updateState($stateId, $input ?? []);
            if ($result) {
                $state = $deskStatesRepo->getState($stateId);
                echo json_encode(['success' => true, 'data' => $state, 'message' => 'Estado actualizado exitosamente']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al actualizar estado']);
            }
            break;
            
        case 'DELETE':
            if (!checkPermission($user, 'manage_states')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para eliminar estados']);
                exit();
            }
            
            $stateId = $_GET['id'] ?? null;
            if (!$stateId) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de estado requerido']);
                exit();
            }
            
            $result = $deskStatesRepo->deleteState($stateId);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Estado eliminado exitosamente']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al eliminar estado']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>