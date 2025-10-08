<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Models/StateTransition.php';
use iaTradeCRM\Database\Connection;
use IaTradeCRM\Models\StateTransition as StateTransitionModel;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Inicializar PDO y repositorio
$pdo = Connection::getInstance()->getConnection();
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

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'matrix':
                        // Get transition matrix for a desk
                        if (!checkPermission($user, 'view_states')) {
                            http_response_code(403);
                            echo json_encode(['error' => 'Sin permisos para ver transiciones']);
                            exit();
                        }
                        
                        $deskId = $_GET['desk_id'] ?? $user->desk_id;
                        try {
                            $transitions = $transitionsRepo->getTransitionsByDesk($deskId);
                            echo json_encode(['success' => true, 'data' => $transitions]);
                        } catch (Exception $e) {
                            echo json_encode(['success' => true, 'data' => [], 'message' => 'Sin transiciones disponibles']);
                        }
                        break;
                        
                    case 'available':
                        // Get available transitions from a specific state
                        $fromStateId = $_GET['from_state_id'] ?? null;
                        $deskId = $_GET['desk_id'] ?? $user->desk_id;
                        
                        if (!$fromStateId) {
                            http_response_code(400);
                            echo json_encode(['error' => 'Estado origen requerido']);
                            exit();
                        }
                        
                        try {
                            $transitions = $transitionsRepo->getAvailableTransitions($fromStateId, null);
                            echo json_encode(['success' => true, 'data' => $transitions]);
                        } catch (Exception $e) {
                            echo json_encode(['success' => true, 'data' => [], 'message' => 'Sin transiciones disponibles']);
                        }
                        break;
                        
                    case 'list':
                        // Get all transitions for a desk
                        if (!checkPermission($user, 'manage_states')) {
                            http_response_code(403);
                            echo json_encode(['error' => 'Sin permisos para gestionar transiciones']);
                            exit();
                        }
                        
                        $deskId = $_GET['desk_id'] ?? $user->desk_id;
                        try {
                            $transitions = $transitionsRepo->getTransitionsByDesk($deskId);
                            echo json_encode(['success' => true, 'data' => $transitions]);
                        } catch (Exception $e) {
                            echo json_encode(['success' => true, 'data' => [], 'message' => 'Sin transiciones disponibles']);
                        }
                        break;
                        
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Acción no válida']);
                }
            } else {
                // Get single transition
                $transitionId = $_GET['id'] ?? null;
                if (!$transitionId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de transición requerido']);
                    exit();
                }
                
                $transition = $transitionsRepo->getTransition($transitionId);
                if (!$transition) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Transición no encontrada']);
                    exit();
                }
                
                echo json_encode(['success' => true, 'data' => $transition]);
            }
            break;
            
        case 'POST':
            if (!checkPermission($user, 'manage_states')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para crear transiciones']);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (isset($_GET['action']) && $_GET['action'] === 'bulk') {
                // Create multiple transitions
                $transitions = $input['transitions'] ?? [];
                $deskId = $input['desk_id'] ?? $user->desk_id;
                
                $results = [];
                foreach ($transitions as $transition) {
                    $fromStateId = $transition['from_state_id'];
                    $toStateId = $transition['to_state_id'];
                    $conditions = $transition['conditions'] ?? null;
                    
                    $transitionId = $transitionsRepo->createTransition([
                        'desk_id' => (int)$deskId,
                        'from_state_id' => $fromStateId,
                        'to_state_id' => $toStateId,
                        'conditions' => $conditions,
                        'created_by' => $user->user_id ?? null,
                    ]);
                    $results[] = [
                        'from_state_id' => $fromStateId,
                        'to_state_id' => $toStateId,
                        'transition_id' => $transitionId,
                        'success' => $transitionId !== false
                    ];
                }
                
                echo json_encode(['success' => true, 'data' => $results, 'message' => 'Transiciones creadas']);
            } else {
                // Create single transition
                $fromStateId = $input['from_state_id'] ?? null;
                $toStateId = $input['to_state_id'] ?? null;
                $deskId = $input['desk_id'] ?? $user->desk_id;
                $conditions = $input['conditions'] ?? null;
                
                if (!$fromStateId || !$toStateId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Estados origen y destino requeridos']);
                    exit();
                }
                
                $transitionId = $transitionsRepo->createTransition([
                    'desk_id' => (int)$deskId,
                    'from_state_id' => $fromStateId,
                    'to_state_id' => $toStateId,
                    'conditions' => $conditions,
                    'created_by' => $user->user_id ?? null,
                ]);
                if ($transitionId) {
                    $transition = $transitionsRepo->getTransition($transitionId);
                    echo json_encode(['success' => true, 'data' => $transition, 'message' => 'Transición creada exitosamente']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Error al crear transición']);
                }
            }
            break;
            
        case 'PUT':
            if (!checkPermission($user, 'manage_states')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para actualizar transiciones']);
                exit();
            }
            
            $transitionId = $_GET['id'] ?? null;
            if (!$transitionId) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de transición requerido']);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $result = $transitionsRepo->updateTransition($transitionId, $input ?? []);
            if ($result) {
                $transition = $transitionsRepo->getTransition($transitionId);
                echo json_encode(['success' => true, 'data' => $transition, 'message' => 'Transición actualizada exitosamente']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al actualizar transición']);
            }
            break;
            
        case 'DELETE':
            if (!checkPermission($user, 'manage_states')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para eliminar transiciones']);
                exit();
            }
            
            $transitionId = $_GET['id'] ?? null;
            if (!$transitionId) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de transición requerido']);
                exit();
            }
            
            $result = $transitionsRepo->deleteTransition($transitionId);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Transición eliminada exitosamente']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al eliminar transición']);
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