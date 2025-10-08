<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar autenticación JWT
function verifyJWT() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token de autorización requerido']);
        exit();
    }
    
    $token = $matches[1];
    $secret = 'your-super-secret-jwt-key-change-in-production-2024';
    
    try {
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token inválido']);
        exit();
    }
}

$user = verifyJWT();

try {
    $db = Connection::getInstance()->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Obtener usuario específico
            $userId = (int)$_GET['id'];
            
            try {
                $stmt = $db->prepare("
                    SELECT u.*, 
                           d.name as desk_name,
                           d.id as desk_id,
                           supervisor.first_name as supervisor_first_name,
                           supervisor.last_name as supervisor_last_name,
                           GROUP_CONCAT(DISTINCT r.name) as roles,
                           GROUP_CONCAT(DISTINCT r.display_name) as role_names,
                           GROUP_CONCAT(DISTINCT p.name) as permissions
                    FROM users u
                    LEFT JOIN desk_users du ON u.id = du.user_id AND du.is_primary = 1
                    LEFT JOIN desks d ON du.desk_id = d.id
                    LEFT JOIN users supervisor ON d.manager_id = supervisor.id
                    LEFT JOIN user_roles ur ON u.id = ur.user_id
                    LEFT JOIN roles r ON ur.role_id = r.id
                    LEFT JOIN role_permissions rp ON r.id = rp.role_id
                    LEFT JOIN permissions p ON rp.permission_id = p.id
                    WHERE u.id = ?
                    GROUP BY u.id
                ");
                $stmt->execute([$userId]);
                $userData = $stmt->fetch();
                
                if ($userData) {
                    // No devolver información sensible
                    unset($userData['password_hash']);
                    unset($userData['password_reset_token']);
                    
                    // Formatear datos
                    $userData['roles'] = $userData['roles'] ? explode(',', $userData['roles']) : [];
                    $userData['role_names'] = $userData['role_names'] ? explode(',', $userData['role_names']) : [];
                    $userData['permissions'] = $userData['permissions'] ? array_unique(explode(',', $userData['permissions'])) : [];
                    $userData['settings'] = json_decode($userData['settings'] ?? '{}', true);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $userData
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Usuario no encontrado'
                    ]);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al obtener el usuario: ' . $e->getMessage()
                ]);
            }
        } else {
            // Obtener lista de usuarios con filtros y paginación
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $role = $_GET['role'] ?? '';
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 25);
            $offset = ($page - 1) * $limit;
            
            // Construir consulta con filtros
            $whereConditions = [];
            $params = [];
            
            if ($search) {
                $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            if ($status) {
                $whereConditions[] = "u.status = ?";
                $params[] = $status;
            }
            
            if ($role) {
                $whereConditions[] = "r.name = ?";
                $params[] = $role;
            }
            
            $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            try {
                // Contar total de registros
                $countStmt = $db->prepare("
                    SELECT COUNT(DISTINCT u.id) as total
                    FROM users u
                    LEFT JOIN user_roles ur ON u.id = ur.user_id
                    LEFT JOIN roles r ON ur.role_id = r.id
                    {$whereClause}
                ");
                $countStmt->execute($params);
                $total = $countStmt->fetch()['total'];
                
                // Obtener usuarios paginados
                $stmt = $db->prepare("
                    SELECT u.id, u.username, u.email, u.first_name, u.last_name, 
                           u.phone, u.status, u.last_login, u.created_at,
                           GROUP_CONCAT(r.display_name) as roles
                    FROM users u
                    LEFT JOIN user_roles ur ON u.id = ur.user_id
                    LEFT JOIN roles r ON ur.role_id = r.id
                    {$whereClause}
                    GROUP BY u.id
                    ORDER BY u.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute(array_merge($params, [$limit, $offset]));
                $users = $stmt->fetchAll();
                
                $totalPages = ceil($total / $limit);
                
                echo json_encode([
                    'success' => true,
                    'data' => $users,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => (int)$total,
                        'pages' => $totalPages
                    ]
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al obtener usuarios: ' . $e->getMessage()
                ]);
            }
        }
        break;
        
    case 'POST':
        // Crear nuevo usuario
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Datos inválidos'
            ]);
            break;
        }
        
        // Validaciones básicas
        $required = ['username', 'email', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "El campo {$field} es requerido"
                ]);
                exit;
            }
        }
        
        // Validar email único
        try {
            $emailStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $emailStmt->execute([$input['email']]);
            if ($emailStmt->fetch()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Ya existe un usuario con este email'
                ]);
                exit;
            }
            
            // Validar username único
            $usernameStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $usernameStmt->execute([$input['username']]);
            if ($usernameStmt->fetch()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Ya existe un usuario con este nombre de usuario'
                ]);
                exit;
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al validar datos: ' . $e->getMessage()
            ]);
            exit;
        }
        
        try {
            $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("
                INSERT INTO users (
                    username, email, password_hash, first_name, last_name, 
                    phone, status, email_verified
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $input['username'],
                $input['email'],
                $passwordHash,
                $input['first_name'],
                $input['last_name'],
                $input['phone'] ?? null,
                $input['status'] ?? 'active',
                $input['email_verified'] ?? false
            ]);
            
            $userId = $db->lastInsertId();
            
            // Asignar rol si se especifica
            if (!empty($input['role_id'])) {
                $roleStmt = $db->prepare("
                    INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $roleStmt->execute([$userId, $input['role_id'], 1]); // Asignado por admin
            }
            
            // Asignar a mesa si se especifica
            if (!empty($input['desk_id'])) {
                $deskStmt = $db->prepare("
                    INSERT INTO desk_users (desk_id, user_id, assigned_by, assigned_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $deskStmt->execute([$input['desk_id'], $userId, 1]); // Asignado por admin
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'data' => ['id' => $userId]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al crear usuario: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'PUT':
        // Actualizar usuario
        $userId = (int)($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$userId || !$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario y datos son requeridos'
            ]);
            break;
        }
        
        try {
            // Verificar que el usuario existe
            $checkStmt = $db->prepare("SELECT id FROM users WHERE id = ?");
            $checkStmt->execute([$userId]);
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ]);
                break;
            }
            
            // Construir consulta de actualización dinámicamente
            $updateFields = [];
            $params = [];
            
            $allowedFields = [
                'username', 'email', 'first_name', 'last_name', 
                'phone', 'status'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "{$field} = ?";
                    $params[] = $input[$field];
                }
            }
            
            // Actualizar contraseña si se proporciona
            if (!empty($input['password'])) {
                $updateFields[] = "password_hash = ?";
                $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'No hay campos para actualizar'
                ]);
                break;
            }
            
            $params[] = $userId;
            
            $stmt = $db->prepare("
                UPDATE users 
                SET " . implode(', ', $updateFields) . "
                WHERE id = ?
            ");
            $stmt->execute($params);
            
            // Actualizar roles si se especifican
            if (isset($input['roles']) && is_array($input['roles'])) {
                // Eliminar roles existentes
                $deleteRolesStmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ?");
                $deleteRolesStmt->execute([$userId]);
                
                // Asignar nuevos roles
                foreach ($input['roles'] as $roleId) {
                    $roleStmt = $db->prepare("
                        INSERT INTO user_roles (user_id, role_id, assigned_by)
                        VALUES (?, ?, ?)
                    ");
                    $roleStmt->execute([$userId, $roleId, 1]); // Asignado por admin
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar usuario: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'DELETE':
        // Eliminar usuario
        $userId = (int)($_GET['id'] ?? 0);
        
        if (!$userId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario requerido'
            ]);
            break;
        }
        
        // No permitir eliminar el usuario admin
        if ($userId === 1) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No se puede eliminar el usuario administrador'
            ]);
            break;
        }
        
        try {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Usuario eliminado exitosamente'
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar usuario: ' . $e->getMessage()
            ]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido'
        ]);
        break;
}
?>
