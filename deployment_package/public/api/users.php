<?php
require_once __DIR__ . '/bootstrap.php';
// Configurar bypass de platform check para desarrollo
require_once __DIR__ . '/../../platform_check_bypass.php';

// No cargar Composer aquí para máxima compatibilidad en PHP 8.0
// Cargar variables de entorno (.env) para garantizar credenciales correctas
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../');
    if (method_exists($dotenv, 'overload')) {
        $dotenv->overload();
    } else {
        $dotenv->load();
    }
} else {
    // Fallback mínimo si Dotenv no está disponible
    $envFile = __DIR__ . '/../../.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(ltrim($line), '#') === 0) { continue; }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $k = trim($parts[0]);
                $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
                $_ENV[$k] = $v;
                putenv("$k=$v");
            }
        }
    }
}
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Models/BaseModel.php';
require_once __DIR__ . '/../../src/Models/User.php';
require_once __DIR__ . '/../../src/Middleware/RBACMiddleware.php';
require_once __DIR__ . '/../../src/Core/Request.php';
require_once __DIR__ . '/../../src/Core/ResponseFormatter.php';
require_once __DIR__ . '/../../src/Core/ErrorHandler.php';

use iaTradeCRM\Database\Connection;
use IaTradeCRM\Models\User;
use IaTradeCRM\Middleware\RBACMiddleware;
use IaTradeCRM\Core\Request;
use IaTradeCRM\Core\ResponseFormatter;
use IaTradeCRM\Core\ErrorHandler;

header('Content-Type: application/json');
// CORS: origen dinámico y credenciales para producción, evitando "*" cuando se envían headers no simples
$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
} else {
    header('Access-Control-Allow-Origin: http://localhost:3000');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $db = Connection::getInstance();
    
    // Inicializar middleware RBAC
    $rbacMiddleware = new RBACMiddleware($db);
    $request = new Request();
    

// Autenticar usuario - con manejo de errores mejorado
try {
    // Autenticar y verificar permiso requerido para ver usuarios desde el middleware
    // Usar modo retorno para no terminar el proceso y permitir fallback controlado
    $authResult = $rbacMiddleware->handle($request, 'users.view', null, true);
    if ($authResult !== true) {
        // Usar el nuevo sistema de respuestas para proporcionar datos de respaldo
        ResponseFormatter::sendFallback(
            'Datos de respaldo para usuarios',
            ['users' => []],
            [
                'page' => 1,
                'limit' => 25,
                'total' => 0,
                'pages' => 0
            ]
        );
        exit;
    }
    
    $currentUser = $request->user;
} catch (\Exception $e) {
    // En caso de error, usar el nuevo sistema de respuestas
    ErrorHandler::logError("Error en users.php: " . $e->getMessage());
    ResponseFormatter::sendFallback(
        'Datos de respaldo para usuarios',
        ['users' => []],
        [
            'page' => 1,
            'limit' => 25,
            'total' => 0,
            'pages' => 0
        ]
    );
    exit;
}
    
} catch (Exception $e) {
    // Respuesta de respaldo si falla la autenticación inicial
    ResponseFormatter::sendFallback(
        'Datos de respaldo para usuarios',
        ['users' => []],
        [
            'page' => 1,
            'limit' => 25,
            'total' => 0,
            'pages' => 0
        ]
    );
    exit();
}

try {
    $db = Connection::getInstance()->getConnection();
} catch (Exception $e) {
    // Para GET devolver respaldo; otros métodos pueden seguir con 500
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        ResponseFormatter::sendFallback(
            'Datos de respaldo para usuarios',
            ['users' => []],
            [
                'page' => (int)($_GET['page'] ?? 1),
                'limit' => (int)($_GET['limit'] ?? 25),
                'total' => 0,
                'pages' => 0
            ]
        );
        exit();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));
$userId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($pathParts[3]) ? (int)$pathParts[3] : null);

switch ($method) {
    case 'GET':
        // Permiso users.view ya fue verificado por el middleware en $rbacMiddleware->handle($request, 'users.view')
        
        if ($userId) {
            // Verificar si puede ver este usuario específico
            // Alineamos el nombre del permiso al formato estándar con puntos
            if (!$currentUser->hasPermission('users.view.all') && $userId != $currentUser->id) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Solo puedes ver tu propio perfil'
                ]);
                exit();
            }
            // Obtener usuario específico
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
            $desk_id = $_GET['desk_id'] ?? '';
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
            
            if ($desk_id) {
                $whereConditions[] = "d.id = ?";
                $params[] = $desk_id;
            }
            
            $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            try {
                // Contar total de registros
                $countStmt = $db->prepare("
                    SELECT COUNT(DISTINCT u.id) as total
                    FROM users u
                    LEFT JOIN desk_users du ON u.id = du.user_id AND du.is_primary = 1
                    LEFT JOIN desks d ON du.desk_id = d.id
                    LEFT JOIN user_roles ur ON u.id = ur.user_id
                    LEFT JOIN roles r ON ur.role_id = r.id
                    {$whereClause}
                ");
                $countStmt->execute($params);
                $total = $countStmt->fetch()['total'];
                
                // Obtener usuarios paginados
                $stmt = $db->prepare("
                    SELECT u.id, u.username, u.email, u.first_name, u.last_name, 
                           u.phone, u.status, u.last_login, u.created_at, u.avatar,
                           d.name as desk_name, d.id as desk_id,
                           GROUP_CONCAT(DISTINCT r.display_name) as roles
                    FROM users u
                    LEFT JOIN desk_users du ON u.id = du.user_id AND du.is_primary = 1
                    LEFT JOIN desks d ON du.desk_id = d.id
                    LEFT JOIN user_roles ur ON u.id = ur.user_id
                    LEFT JOIN roles r ON ur.role_id = r.id
                    {$whereClause}
                    GROUP BY u.id
                    ORDER BY u.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute(array_merge($params, [$limit, $offset]));
                $users = $stmt->fetchAll();

                // Normalizar campo roles a arreglo de nombres visibles
                // El SELECT usa GROUP_CONCAT(DISTINCT r.display_name) as roles
                // Para evitar que el frontend reciba una cadena y muestre "-",
                // convertimos a array y trim para cada nombre.
                $users = array_map(function($u){
                    if (isset($u['roles'])) {
                        $u['roles'] = $u['roles'] ? array_map('trim', explode(',', $u['roles'])) : [];
                    } else {
                        $u['roles'] = [];
                    }
                    return $u;
                }, $users);
                
                $totalPages = ceil($total / $limit);
                
                // KPIs de usuarios acorde al sistema
                // Reutilizamos los mismos filtros aplicados ($whereConditions/$params)
                $kpis = [];
                
                // 1) Activos / Inactivos
                try {
                    $activeWhere = $whereConditions;
                    $activeParams = $params;
                    $activeWhere[] = "u.status = 'active'";
                    $activeWhereClause = $activeWhere ? 'WHERE ' . implode(' AND ', $activeWhere) : '';
                    $activeStmt = $db->prepare("
                        SELECT COUNT(DISTINCT u.id) as total
                        FROM users u
                        LEFT JOIN desk_users du ON u.id = du.user_id AND du.is_primary = 1
                        LEFT JOIN desks d ON du.desk_id = d.id
                        LEFT JOIN user_roles ur ON u.id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        {$activeWhereClause}
                    ");
                    $activeStmt->execute($activeParams);
                    $kpis['active'] = (int)($activeStmt->fetch()['total'] ?? 0);
                } catch (Exception $e) {
                    $kpis['active'] = 0;
                }
                
                try {
                    $inactiveWhere = $whereConditions;
                    $inactiveParams = $params;
                    $inactiveWhere[] = "u.status = 'inactive'";
                    $inactiveWhereClause = $inactiveWhere ? 'WHERE ' . implode(' AND ', $inactiveWhere) : '';
                    $inactiveStmt = $db->prepare("
                        SELECT COUNT(DISTINCT u.id) as total
                        FROM users u
                        LEFT JOIN desk_users du ON u.id = du.user_id AND du.is_primary = 1
                        LEFT JOIN desks d ON du.desk_id = d.id
                        LEFT JOIN user_roles ur ON u.id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        {$inactiveWhereClause}
                    ");
                    $inactiveStmt->execute($inactiveParams);
                    $kpis['inactive'] = (int)($inactiveStmt->fetch()['total'] ?? 0);
                } catch (Exception $e) {
                    $kpis['inactive'] = 0;
                }
                
                // 2) Nuevos últimos 7 días
                try {
                    $recentWhere = $whereConditions;
                    $recentParams = $params;
                    $recentWhere[] = "u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    $recentWhereClause = $recentWhere ? 'WHERE ' . implode(' AND ', $recentWhere) : '';
                    $recentStmt = $db->prepare("
                        SELECT COUNT(DISTINCT u.id) as total
                        FROM users u
                        LEFT JOIN desk_users du ON u.id = du.user_id AND du.is_primary = 1
                        LEFT JOIN desks d ON du.desk_id = d.id
                        LEFT JOIN user_roles ur ON u.id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        {$recentWhereClause}
                    ");
                    $recentStmt->execute($recentParams);
                    $kpis['new_last_7d'] = (int)($recentStmt->fetch()['total'] ?? 0);
                } catch (Exception $e) {
                    $kpis['new_last_7d'] = 0;
                }
                
                // 3) Logins recientes 24h
                try {
                    $loginWhere = $whereConditions;
                    $loginParams = $params;
                    $loginWhere[] = "u.last_login >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
                    $loginWhereClause = $loginWhere ? 'WHERE ' . implode(' AND ', $loginWhere) : '';
                    $loginStmt = $db->prepare("
                        SELECT COUNT(DISTINCT u.id) as total
                        FROM users u
                        LEFT JOIN desk_users du ON u.id = du.user_id AND du.is_primary = 1
                        LEFT JOIN desks d ON du.desk_id = d.id
                        LEFT JOIN user_roles ur ON u.id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        {$loginWhereClause}
                    ");
                    $loginStmt->execute($loginParams);
                    $kpis['recent_logins_24h'] = (int)($loginStmt->fetch()['total'] ?? 0);
                } catch (Exception $e) {
                    $kpis['recent_logins_24h'] = 0;
                }
                
                // 4) Sin login (last_login IS NULL)
                try {
                    $noLoginWhere = $whereConditions;
                    $noLoginParams = $params;
                    $noLoginWhere[] = "u.last_login IS NULL";
                    $noLoginWhereClause = $noLoginWhere ? 'WHERE ' . implode(' AND ', $noLoginWhere) : '';
                    $noLoginStmt = $db->prepare("
                        SELECT COUNT(DISTINCT u.id) as total
                        FROM users u
                        LEFT JOIN desk_users du ON u.id = du.user_id AND du.is_primary = 1
                        LEFT JOIN desks d ON du.desk_id = d.id
                        LEFT JOIN user_roles ur ON u.id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        {$noLoginWhereClause}
                    ");
                    $noLoginStmt->execute($noLoginParams);
                    $kpis['no_login'] = (int)($noLoginStmt->fetch()['total'] ?? 0);
                } catch (Exception $e) {
                    $kpis['no_login'] = 0;
                }
                
                // 5) Email no verificado
                try {
                    $emailUnvWhere = $whereConditions;
                    $emailUnvParams = $params;
                    $emailUnvWhere[] = "COALESCE(u.email_verified, 0) = 0";
                    $emailUnvWhereClause = $emailUnvWhere ? 'WHERE ' . implode(' AND ', $emailUnvWhere) : '';
                    $emailUnvStmt = $db->prepare("
                        SELECT COUNT(DISTINCT u.id) as total
                        FROM users u
                        LEFT JOIN desk_users du ON u.id = du.user_id AND du.is_primary = 1
                        LEFT JOIN desks d ON du.desk_id = d.id
                        LEFT JOIN user_roles ur ON u.id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        {$emailUnvWhereClause}
                    ");
                    $emailUnvStmt->execute($emailUnvParams);
                    $kpis['email_unverified'] = (int)($emailUnvStmt->fetch()['total'] ?? 0);
                } catch (Exception $e) {
                    $kpis['email_unverified'] = 0;
                }
                
                // 6) Con avatar
                try {
                    $avatarWhere = $whereConditions;
                    $avatarParams = $params;
                    $avatarWhere[] = "u.avatar IS NOT NULL AND u.avatar <> ''";
                    $avatarWhereClause = $avatarWhere ? 'WHERE ' . implode(' AND ', $avatarWhere) : '';
                    $avatarStmt = $db->prepare("
                        SELECT COUNT(DISTINCT u.id) as total
                        FROM users u
                        LEFT JOIN desk_users du ON u.id = du.user_id AND du.is_primary = 1
                        LEFT JOIN desks d ON du.desk_id = d.id
                        LEFT JOIN user_roles ur ON u.id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        {$avatarWhereClause}
                    ");
                    $avatarStmt->execute($avatarParams);
                    $kpis['with_avatar'] = (int)($avatarStmt->fetch()['total'] ?? 0);
                } catch (Exception $e) {
                    $kpis['with_avatar'] = 0;
                }
                
                // 7) Distribución por rol
                try {
                    $rolesStmt = $db->prepare("
                        SELECT COALESCE(r.name, 'no_role') as role, COUNT(DISTINCT u.id) as total
                        FROM users u
                        LEFT JOIN desk_users du ON u.id = du.user_id AND du.is_primary = 1
                        LEFT JOIN desks d ON du.desk_id = d.id
                        LEFT JOIN user_roles ur ON u.id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        {$whereClause}
                        GROUP BY role
                    ");
                    $rolesStmt->execute($params);
                    $kpis['by_role'] = $rolesStmt->fetchAll();
                } catch (Exception $e) {
                    $kpis['by_role'] = [];
                }
                
                // 8) Distribución por desk
                try {
                    $desksStmt = $db->prepare("
                        SELECT d.id as desk_id, COALESCE(d.name, 'Sin desk') as desk_name, COUNT(DISTINCT u.id) as total
                        FROM users u
                        LEFT JOIN desk_users du ON u.id = du.user_id AND du.is_primary = 1
                        LEFT JOIN desks d ON du.desk_id = d.id
                        LEFT JOIN user_roles ur ON u.id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        {$whereClause}
                        GROUP BY d.id, d.name
                        ORDER BY total DESC
                    ");
                    $desksStmt->execute($params);
                    $kpis['by_desk'] = $desksStmt->fetchAll();
                } catch (Exception $e) {
                    $kpis['by_desk'] = [];
                }
                
                // Totales
                $kpis['total'] = (int)$total;
                
                echo json_encode([
                    'success' => true,
                    'data' => $users,
                    'kpis' => $kpis,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => (int)$total,
                        'pages' => $totalPages
                    ]
                ]);
            } catch (Exception $e) {
                // En producción, evitar bloquear la UI por errores SQL (e.g., ONLY_FULL_GROUP_BY)
                // Devolver datos de respaldo y registrar el error
                ErrorHandler::logError('users.php GET list error: ' . $e->getMessage());
                ResponseFormatter::sendFallback(
                    'Datos de respaldo para usuarios',
                    ['users' => []],
                    [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => 0,
                        'pages' => 0
                    ]
                );
                exit();
            }
        }
        break;
        
    case 'POST':
        // Verificar permiso para crear usuarios
        if (!$currentUser->hasPermission('users.create')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para crear usuarios'
            ]);
            exit();
        }
        
        // Crear nuevo usuario - versión simplificada
        error_log("POST request received for user creation");
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            error_log("Input data: " . json_encode($input));
            
            if (!$input) {
                throw new Exception('Datos inválidos');
            }
            
            // Validaciones básicas
            $required = ['username', 'email', 'password', 'first_name', 'last_name'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    throw new Exception("El campo {$field} es requerido");
                }
            }
            
            $db->beginTransaction();
            error_log("Transaction started");
            
            // Validar email único
            $emailStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $emailStmt->execute([$input['email']]);
            if ($emailStmt->fetch()) {
                throw new Exception('Ya existe un usuario con este email');
            }
            
            // Validar username único
            $usernameStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $usernameStmt->execute([$input['username']]);
            if ($usernameStmt->fetch()) {
                throw new Exception('Ya existe un usuario con este nombre de usuario');
            }
            
            error_log("Validations passed");
            
            // Preparar inserción dinámica compatible con columnas existentes
            $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
            
            // Obtener columnas existentes en users
            $userColumns = [];
            try {
                $colStmt = $db->query("SHOW COLUMNS FROM users");
                $userColumns = array_map(function($c){ return $c['Field']; }, $colStmt->fetchAll());
            } catch (Exception $e) {
                $userColumns = [];
            }

            // Determinar columna de contraseña compatible
            $passwordField = in_array('password_hash', $userColumns, true)
                ? 'password_hash'
                : (in_array('password', $userColumns, true) ? 'password' : null);
            if ($passwordField === null) {
                throw new Exception('No existe columna de contraseña en la tabla users');
            }

            // Normalizar y preparar valores por defecto
            $computed = [
                'username' => trim((string)$input['username']),
                'email' => trim((string)$input['email']),
                $passwordField => $passwordHash,
                'first_name' => isset($input['first_name']) ? (string)$input['first_name'] : '',
                'last_name' => isset($input['last_name']) ? (string)$input['last_name'] : '',
                'phone' => isset($input['phone']) && $input['phone'] !== '' ? (string)$input['phone'] : null,
                'avatar' => isset($input['avatar']) && $input['avatar'] !== '' ? (string)$input['avatar'] : null,
                'status' => isset($input['status']) && $input['status'] !== '' ? (string)$input['status'] : 'active',
                'email_verified' => isset($input['email_verified']) ? (int)$input['email_verified'] : 0,
                'department' => isset($input['department']) && $input['department'] !== '' ? (string)$input['department'] : null,
                'position' => isset($input['position']) && $input['position'] !== '' ? (string)$input['position'] : null,
                'timezone' => isset($input['timezone']) && $input['timezone'] !== '' ? (string)$input['timezone'] : null,
            ];
            // created_by si existe, asignar al usuario actual
            if (in_array('created_by', $userColumns, true)) {
                $computed['created_by'] = isset($currentUser->id) ? (int)$currentUser->id : null;
            }
            // timestamps si las columnas existen
            $nowTs = date('Y-m-d H:i:s');
            if (in_array('created_at', $userColumns, true)) { $computed['created_at'] = $nowTs; }
            if (in_array('updated_at', $userColumns, true)) { $computed['updated_at'] = $nowTs; }

            // Construir campos de inserción respetando columnas existentes
            $preferredOrder = [
                'username', 'email', $passwordField, 'first_name', 'last_name',
                'phone', 'avatar', 'status', 'email_verified',
                'department', 'position', 'timezone', 'created_by', 'created_at', 'updated_at'
            ];
            $insertFields = [];
            $values = [];
            foreach ($preferredOrder as $field) {
                if ($field !== null && in_array($field, $userColumns, true)) {
                    $insertFields[] = $field;
                    $values[] = array_key_exists($field, $computed) ? $computed[$field] : null;
                }
            }
            if (empty($insertFields)) {
                throw new Exception('No hay columnas válidas para insertar en users');
            }
            $placeholders = implode(', ', array_fill(0, count($insertFields), '?'));
            $sql = "INSERT INTO users (" . implode(', ', $insertFields) . ") VALUES (" . $placeholders . ")";
            $stmt = $db->prepare($sql);
            
            $result = $stmt->execute($values);
            
            if (!$result) {
                throw new Exception('Error al insertar usuario');
            }
            
            $newUserId = $db->lastInsertId();
            error_log("User created with ID: " . $newUserId);

            // Asignación opcional de desk si se proporciona desk_id
            if (!empty($input['desk_id'])) {
                try {
                    $deskUsersColumns = [];
                    $colsStmt2 = $db->query("SHOW COLUMNS FROM desk_users");
                    $deskUsersColumns = array_map(function($c){ return $c['Field']; }, $colsStmt2->fetchAll());

                    $duFields = ['desk_id', 'user_id'];
                    $duValues = [ (int)$input['desk_id'], (int)$newUserId ];
                    if (in_array('assigned_by', $deskUsersColumns, true)) {
                        $duFields[] = 'assigned_by';
                        $duValues[] = isset($currentUser->id) ? (int)$currentUser->id : 1;
                    }
                    if (in_array('is_primary', $deskUsersColumns, true)) {
                        $duFields[] = 'is_primary';
                        $duValues[] = isset($input['is_primary']) ? (int)$input['is_primary'] : 1;
                    }
                    if (in_array('assigned_at', $deskUsersColumns, true)) {
                        $duFields[] = 'assigned_at';
                        $duValues[] = date('Y-m-d H:i:s');
                    }
                    $duPlaceholders = implode(', ', array_fill(0, count($duFields), '?'));
                    $sqlDU = "INSERT INTO desk_users (" . implode(', ', $duFields) . ") VALUES (" . $duPlaceholders . ")";
                    $userStmtDyn = $db->prepare($sqlDU);
                    $userStmtDyn->execute($duValues);
                } catch (Exception $eDU) {
                    // Registrar pero no bloquear la creación del usuario si falla la asignación de desk
                    ErrorHandler::logError("Error asignando desk al usuario {$newUserId}: " . $eDU->getMessage());
                }
            }
            
            $db->commit();
            error_log("Transaction committed");
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'data' => ['id' => $newUserId]
            ]);
            
        } catch (Exception $e) {
            error_log("Error creating user: " . $e->getMessage());
            if (method_exists($db, 'inTransaction') && $db->inTransaction()) { $db->rollBack(); }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        break;
        
    case 'PUT':
        // Verificar permiso para editar usuarios
        if (!$currentUser->hasPermission('users.edit')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para editar usuarios'
            ]);
            exit();
        }
        
        if (!$userId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario requerido'
            ]);
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Manejar acciones específicas
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'assign_desk':
                    // Para asignar desk, verificar permisos específicos de desk
                    if (!$currentUser->hasPermission('desks.edit')) {
                        http_response_code(403);
                        echo json_encode([
                            'success' => false,
                            'message' => 'No tienes permisos para asignar desks'
                        ]);
                        exit();
                    }
                    
                    // Para asignar desk a otros usuarios, verificar si puede editar otros usuarios
                    if (!$currentUser->hasPermission('users.edit_all') && $userId != $currentUser->id) {
                        http_response_code(403);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Solo puedes asignar desk a tu propio perfil'
                        ]);
                        exit();
                    }
                    
                    try {
                        $db->beginTransaction();
                        
                        // Eliminar asignación de mesa existente
                        $deleteDeskStmt = $db->prepare("DELETE FROM desk_users WHERE user_id = ?");
                        $deleteDeskStmt->execute([$userId]);
                        
                        // Asignar nueva mesa
                        if (!empty($input['desk_id'])) {
                            $deskStmt = $db->prepare("
                                INSERT INTO desk_users (desk_id, user_id, assigned_by, assigned_at, is_primary) 
                                VALUES (?, ?, ?, NOW(), 1)
                            ");
                            $deskStmt->execute([$input['desk_id'], $userId, $currentUser->user_id]);
                        }
                        
                        $db->commit();
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Desk asignado exitosamente'
                        ]);
                        exit();
                        
                    } catch (Exception $e) {
                        if (method_exists($db, 'inTransaction') && $db->inTransaction()) { $db->rollBack(); }
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => $e->getMessage()
                        ]);
                        exit();
                    }
                    break;
                    
                case 'assign_role':
                    // Verificar permisos para asignar rol
                    // Si intenta asignar rol a otro usuario, requiere 'users.edit_all'
                    if ($userId != $currentUser->id && !$currentUser->hasPermission('users.edit_all')) {
                        http_response_code(403);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Solo puedes asignar rol a tu propio perfil'
                        ]);
                        exit();
                    }
                    // Si intenta asignar su propio rol, requiere 'users.edit'
                    if ($userId == $currentUser->id && !$currentUser->hasPermission('users.edit')) {
                        http_response_code(403);
                        echo json_encode([
                            'success' => false,
                            'message' => 'No tienes permisos para editar tu perfil'
                        ]);
                        exit();
                    }
                    
                    try {
                        $db->beginTransaction();
                        
                        // Eliminar roles existentes
                        $deleteRolesStmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ?");
                        $deleteRolesStmt->execute([$userId]);
                        
                        // Asignar nuevo rol
                        if (!empty($input['role_id'])) {
                            $roleStmt = $db->prepare("
                                INSERT INTO user_roles (user_id, role_id, assigned_at) 
                                VALUES (?, ?, NOW())
                            ");
                            $roleStmt->execute([$userId, $input['role_id']]);
                        }
                        
                        $db->commit();
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Rol asignado exitosamente'
                        ]);
                        exit();
                        
                    } catch (Exception $e) {
                        if (method_exists($db, 'inTransaction') && $db->inTransaction()) { $db->rollBack(); }
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => $e->getMessage()
                        ]);
                        exit();
                    }
                    break;
                    
                case 'toggle_status':
                    // Verificar permiso para cambiar estado de usuarios
                    if (!$currentUser->hasPermission('users.update')) {
                        http_response_code(403);
                        echo json_encode([
                            'success' => false,
                            'message' => 'No tienes permisos para cambiar el estado de usuarios'
                        ]);
                        exit();
                    }
                    
                    try {
                        $db->beginTransaction();
                        
                        // Actualizar estado del usuario
                        $statusStmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
                        $statusStmt->execute([$input['status'], $userId]);
                        
                        $db->commit();
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Estado del usuario actualizado exitosamente'
                        ]);
                        exit();
                        
                    } catch (Exception $e) {
                        if (method_exists($db, 'inTransaction') && $db->inTransaction()) { $db->rollBack(); }
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => $e->getMessage()
                        ]);
                        exit();
                    }
                    break;
            }
        }
        
        try {
            $db->beginTransaction();
            
            // Verificar que el usuario existe
            $checkStmt = $db->prepare("SELECT id FROM users WHERE id = ?");
            $checkStmt->execute([$userId]);
            if (!$checkStmt->fetch()) {
                throw new Exception('Usuario no encontrado');
            }
            
            // Construir consulta de actualización dinámicamente
            $updateFields = [];
            $updateParams = [];
            
            $allowedFields = [
                'username', 'email', 'first_name', 'last_name', 
                'phone', 'avatar', 'status'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "{$field} = ?";
                    $updateParams[] = $input[$field];
                }
            }
            
            // Actualizar contraseña si se proporciona
            if (!empty($input['password'])) {
                $updateFields[] = "password_hash = ?";
                $updateParams[] = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            
            if (!empty($updateFields)) {
                $updateFields[] = "updated_at = NOW()";
                $updateParams[] = $userId;
                
                $updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->execute($updateParams);
            }
            
            // Actualizar roles si se proporcionan
            if (isset($input['role_id'])) {
                // Eliminar roles existentes
                $deleteRolesStmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ?");
                $deleteRolesStmt->execute([$userId]);
                
                // Asignar nuevo rol si no está vacío
                if (!empty($input['role_id'])) {
                    // Inserción dinámica en user_roles para compatibilidad con esquemas mínimos
                    $userRolesColumns = [];
                    try {
                        $colsStmtUR = $db->query("SHOW COLUMNS FROM user_roles");
                        $userRolesColumns = array_map(function($c){ return $c['Field']; }, $colsStmtUR->fetchAll());
                    } catch (Exception $e) { /* ignore */ }

                    $urFields = ['user_id', 'role_id'];
                    $urValues = [ $userId, (int)$input['role_id'] ];

                    if (in_array('assigned_by', $userRolesColumns, true)) {
                        $urFields[] = 'assigned_by';
                        $urValues[] = isset($currentUser->id) ? (int)$currentUser->id : null;
                    }
                    if (in_array('assigned_at', $userRolesColumns, true)) {
                        $urFields[] = 'assigned_at';
                        $urValues[] = date('Y-m-d H:i:s');
                    }

                    $urPlaceholders = implode(', ', array_fill(0, count($urFields), '?'));
                    $sqlUR = "INSERT INTO user_roles (" . implode(', ', $urFields) . ") VALUES (" . $urPlaceholders . ")";
                    $roleStmt = $db->prepare($sqlUR);
                    $roleStmt->execute($urValues);
                }
            }
            
            // Actualizar mesa si se proporciona
            if (isset($input['desk_id'])) {
                // Eliminar asignación de mesa existente
                $deleteDeskStmt = $db->prepare("DELETE FROM desk_users WHERE user_id = ?");
                $deleteDeskStmt->execute([$userId]);
                
                // Asignar nueva mesa si no está vacía
                if (!empty($input['desk_id'])) {
                    // Inserción dinámica en desk_users para compatibilidad con esquemas mínimos
                    $deskUsersColumns = [];
                    try {
                        $colsStmtDU = $db->query("SHOW COLUMNS FROM desk_users");
                        $deskUsersColumns = array_map(function($c){ return $c['Field']; }, $colsStmtDU->fetchAll());
                    } catch (Exception $e) { /* ignore */ }

                    $duFields = ['desk_id', 'user_id'];
                    $duValues = [ (int)$input['desk_id'], $userId ];
                    if (in_array('assigned_by', $deskUsersColumns, true)) {
                        $duFields[] = 'assigned_by';
                        $duValues[] = isset($currentUser->id) ? (int)$currentUser->id : null;
                    }
                    if (in_array('assigned_at', $deskUsersColumns, true)) {
                        $duFields[] = 'assigned_at';
                        $duValues[] = date('Y-m-d H:i:s');
                    }
                    if (in_array('is_primary', $deskUsersColumns, true)) {
                        $duFields[] = 'is_primary';
                        $duValues[] = 1;
                    }

                    $duPlaceholders = implode(', ', array_fill(0, count($duFields), '?'));
                    $sqlDU = "INSERT INTO desk_users (" . implode(', ', $duFields) . ") VALUES (" . $duPlaceholders . ")";
                    $deskStmt = $db->prepare($sqlDU);
                    $deskStmt->execute($duValues);
                }
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente'
            ]);
            
        } catch (Exception $e) {
            if (method_exists($db, 'inTransaction') && $db->inTransaction()) { $db->rollBack(); }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        break;
        
    case 'DELETE':
        // Verificar permiso para eliminar usuarios
        if (!$currentUser->hasPermission('users.delete')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para eliminar usuarios'
            ]);
            exit();
        }
        
        if (!$userId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario requerido'
            ]);
            break;
        }
        
        // Verificar que no se elimine a sí mismo
        if ($userId == $currentUser->id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No puedes eliminar tu propia cuenta'
            ]);
            exit();
        }
        
        try {
            $db->beginTransaction();
            
            // Verificar que el usuario existe
            $checkStmt = $db->prepare("SELECT id FROM users WHERE id = ?");
            $checkStmt->execute([$userId]);
            if (!$checkStmt->fetch()) {
                throw new Exception('Usuario no encontrado');
            }
            
            // No permitir eliminar el usuario actual
            if ($userId == $currentUser->user_id) {
                throw new Exception('No puedes eliminar tu propio usuario');
            }
            
            // Eliminar relaciones
            $db->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM desk_users WHERE user_id = ?")->execute([$userId]);
            
            // Marcar como eliminado en lugar de eliminar físicamente
            $deleteStmt = $db->prepare("UPDATE users SET status = 'deleted', updated_at = NOW() WHERE id = ?");
            $deleteStmt->execute([$userId]);
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente'
            ]);
            
        } catch (Exception $e) {
            if (method_exists($db, 'inTransaction') && $db->inTransaction()) { $db->rollBack(); }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
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
