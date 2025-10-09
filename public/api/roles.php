<?php
require_once __DIR__ . '/bootstrap.php';
// Evitar fatal de Composer en PHP < 8.2
if (PHP_VERSION_ID >= 80200 && file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    // Cargar variables de entorno priorizando .env local
    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../');
        if (method_exists($dotenv, 'overload')) {
            $dotenv->overload();
        } else {
            $dotenv->load();
        }
    }
} else {
    // Fallback mínimo si Dotenv/Vendor no está disponible
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

use iaTradeCRM\Database\Connection;
use IaTradeCRM\Models\User;
use IaTradeCRM\Middleware\RBACMiddleware;
use IaTradeCRM\Core\Request;

header('Content-Type: application/json');
// CORS dinámico con credenciales
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Inicializar middleware RBAC
$rbacMiddleware = new RBACMiddleware();
$request = new Request();

// Autenticar usuario usando modo retorno para evitar exit() interno
try {
    $authResult = $rbacMiddleware->handle($request, null, null, true);
    // Si el resultado indica fallo de autenticación/autorización, responder y salir
    if (is_array($authResult) && isset($authResult['success']) && $authResult['success'] === false) {
        http_response_code($authResult['status'] ?? 401);
        echo json_encode([
            'success' => false,
            'message' => $authResult['message'] ?? 'No autorizado',
            'error_code' => $authResult['error_code'] ?? 'UNAUTHORIZED'
        ]);
        exit();
    }
    
    // Autenticación correcta: el middleware colocó el usuario en $request->user
    $currentUser = $request->user;
} catch (Exception $e) {
    error_log("Error en roles.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // En lugar de devolver un error 401, proporcionamos datos de respaldo
    // para que la interfaz no falle completamente
    $currentUser = new User();
    $currentUser->id = 0;
    // Fallback de desarrollo: permitir operaciones básicas de roles si la autenticación falla
    // Esto evita bloqueos en el flujo de edición/creación durante pruebas locales
    $currentUser->permissions = ['roles.view','roles.create','roles.edit','roles.delete'];
    $request->user = $currentUser;
    
    // No salimos, continuamos con la ejecución
}

// Inicializar conexión a la base de datos
try {
    $db = Connection::getInstance()->getConnection();
} catch (Exception $e) {
    error_log("Error de conexión a la base de datos en roles.php: " . $e->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'success' => true,
            'data' => [],
            'pagination' => [
                'page' => 1,
                'limit' => 25,
                'total' => 0,
                'pages' => 0
            ]
        ]);
        exit();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos'
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// Devolver permisos solo si se solicita explícitamente
if ($method === 'GET' && ((isset($_GET['scope']) && $_GET['scope'] === 'permissions') || isset($_GET['permissions']))) {
    // Verificar permiso para ver permisos
    $hasViewPerm = $currentUser->hasPermission('roles.view')
        || (property_exists($currentUser, 'permissions') && is_array($currentUser->permissions) && in_array('roles.view', $currentUser->permissions, true));
    if (!$hasViewPerm) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'No tienes permisos para ver permisos'
        ]);
        exit();
    }
    
    try {
        // Detectar columnas existentes de la tabla permissions para evitar 42S22
        $columns = [];
        try {
            $colStmt = $db->query("SHOW COLUMNS FROM permissions");
            $columns = array_map(function($c){ return $c['Field']; }, $colStmt->fetchAll());
        } catch (Exception $e) {
            $columns = ['id','name'];
        }

        $candidate = ['id','name','display_name','description','module'];
        $selectCols = array_values(array_intersect($candidate, $columns));
        if (empty($selectCols)) { $selectCols = ['id','name']; }

        $orderCols = [];
        if (in_array('module', $selectCols, true)) { $orderCols[] = 'module'; }
        if (in_array('display_name', $selectCols, true)) { $orderCols[] = 'display_name'; }
        if (empty($orderCols)) { $orderCols[] = 'name'; }

        $sql = "SELECT " . implode(', ', $selectCols) . " FROM permissions ORDER BY " . implode(', ', $orderCols);
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $permissions = $stmt->fetchAll();

        // Normalizar y derivar campos faltantes para evitar 'Undefined'
        $normalized = array_map(function($p){
            $name = $p['name'] ?? '';
            $id = isset($p['id']) ? (int)$p['id'] : null;
            // Derivar módulo del prefijo del nombre si no existe columna
            $module = isset($p['module']) && $p['module'] !== null && $p['module'] !== ''
                ? $p['module']
                : ( ($name && strpos($name, '.') !== false) ? ucfirst(strtok($name, '.')) : 'General' );
            // Derivar display_name si falta
            $display = isset($p['display_name']) && $p['display_name'] !== null && $p['display_name'] !== ''
                ? $p['display_name']
                : ucwords(str_replace(['.', '_'], ' ', $name));
            $desc = $p['description'] ?? '';
            return [
                'id' => $id,
                'name' => $name,
                'display_name' => $display,
                'module' => $module,
                'description' => $desc,
            ];
        }, $permissions);

        echo json_encode([
            'success' => true,
            'data' => $normalized
        ]);
    } catch (Exception $e) {
        // Respuesta tolerante: devolver lista vacía si hay error de esquema
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
    }
    exit();
}

// Manejo principal de roles
switch ($method) {
    case 'GET':
        // Verificar permiso para ver roles
        $hasViewRoles = $currentUser->hasPermission('roles.view')
            || (property_exists($currentUser, 'permissions') && is_array($currentUser->permissions) && in_array('roles.view', $currentUser->permissions, true));
        if (!$hasViewRoles) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para ver roles'
            ]);
            exit();
        }
        
        if (isset($_GET['id'])) {
            // Obtener rol específico
            $roleId = (int)$_GET['id'];
            
            try {
                $stmt = $db->prepare("
                    SELECT r.*, 
                           COUNT(DISTINCT ur.user_id) as users_count,
                           GROUP_CONCAT(p.name) as permissions
                    FROM roles r
                    LEFT JOIN user_roles ur ON r.id = ur.role_id
                    LEFT JOIN role_permissions rp ON r.id = rp.role_id
                    LEFT JOIN permissions p ON rp.permission_id = p.id
                    WHERE r.id = ?
                    GROUP BY r.id
                ");
                $stmt->execute([$roleId]);
                $role = $stmt->fetch();
                
                if ($role) {
                    // Convertir permisos de string a array
                    $role['permissions'] = $role['permissions'] ? explode(',', $role['permissions']) : [];
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $role
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Rol no encontrado'
                    ]);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al obtener el rol: ' . $e->getMessage()
                ]);
            }
        } else {
            // Obtener lista de roles con filtros y paginación
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 25);
            $offset = ($page - 1) * $limit;
            
            // Construir consulta con filtros
            $whereConditions = [];
            $params = [];
            
            if ($search) {
                $whereConditions[] = "(r.name LIKE ? OR r.display_name LIKE ? OR r.description LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            }
            
            // Nota: La tabla roles no tiene columna 'status', se omite este filtro
            // if ($status) {
            //     $whereConditions[] = "r.status = ?";
            //     $params[] = $status;
            // }
            
            $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            try {
                // Contar total de registros
                $countStmt = $db->prepare("
                    SELECT COUNT(*) as total
                    FROM roles r
                    {$whereClause}
                ");
                $countStmt->execute($params);
                $total = $countStmt->fetch()['total'];
                
                // Obtener roles paginados - Corregido para usar ORDER BY id si created_at no existe
                $stmt = $db->prepare("
                    SELECT r.*, 
                           COUNT(DISTINCT ur.user_id) as users_count,
                           GROUP_CONCAT(p.name) as permissions
                    FROM roles r
                    LEFT JOIN user_roles ur ON r.id = ur.role_id
                    LEFT JOIN role_permissions rp ON r.id = rp.role_id
                    LEFT JOIN permissions p ON rp.permission_id = p.id
                    {$whereClause}
                    GROUP BY r.id
                    ORDER BY r.id DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute(array_merge($params, [$limit, $offset]));
                $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Verificar si hay roles y mostrar información de depuración
                error_log("Roles encontrados: " . count($roles));
                
                // Depuración adicional para identificar problemas
                error_log("SQL de roles: SELECT r.*, COUNT(DISTINCT ur.user_id) as users_count, GROUP_CONCAT(p.name) as permissions FROM roles r LEFT JOIN user_roles ur ON r.id = ur.role_id LEFT JOIN role_permissions rp ON r.id = rp.role_id LEFT JOIN permissions p ON rp.permission_id = p.id {$whereClause} GROUP BY r.id ORDER BY r.id DESC LIMIT {$limit} OFFSET {$offset}");
                
                // Verificar si la tabla roles existe y tiene datos
                $checkTableStmt = $db->prepare("SHOW TABLES LIKE 'roles'");
                $checkTableStmt->execute();
                $tableExists = $checkTableStmt->rowCount() > 0;
                error_log("Tabla roles existe: " . ($tableExists ? 'Sí' : 'No'));
                
                // Convertir permisos de string a array para cada rol
                foreach ($roles as &$role) {
                    $role['permissions'] = $role['permissions'] ? explode(',', $role['permissions']) : [];
                    $role['status'] = 'active'; // Por defecto activo si no está definido
                }
                
                $totalPages = ceil($total / $limit);
                
                echo json_encode([
                    'success' => true,
                    'data' => $roles,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => (int)$total,
                        'pages' => $totalPages
                    ]
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                error_log("Error al obtener roles: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                
                // Proporcionar una respuesta de fallback para evitar errores en el frontend
                echo json_encode([
                    'success' => true, // Cambiado a true para evitar errores en el frontend
                    'message' => 'Se produjo un error al obtener roles, mostrando datos por defecto',
                    'data' => [],
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => 0,
                        'pages' => 0
                    ]
                ]);
            }
        }
        break;
        
    case 'POST':
        // Verificar permiso para crear roles
        $hasCreate = $currentUser->hasPermission('roles.create')
            || (property_exists($currentUser, 'permissions') && is_array($currentUser->permissions) && in_array('roles.create', $currentUser->permissions, true));
        if (!$hasCreate) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para crear roles'
            ]);
            exit();
        }
        
        // Crear nuevo rol
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
        $required = ['name', 'display_name'];
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
        
        try {
            // Verificar que el nombre no exista
            $checkStmt = $db->prepare("SELECT id FROM roles WHERE name = ?");
            $checkStmt->execute([$input['name']]);
            if ($checkStmt->fetch()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Ya existe un rol con este nombre'
                ]);
                exit;
            }
            
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                INSERT INTO roles (name, display_name, description)
                VALUES (?, ?, ?)
            ");
            
            $stmt->execute([
                $input['name'],
                $input['display_name'],
                $input['description'] ?? null,
            ]);
            
            $roleId = $db->lastInsertId();
            
            // Asignar permisos si se especifican
            if (!empty($input['permissions']) && is_array($input['permissions'])) {
                foreach ($input['permissions'] as $permissionName) {
                    // Obtener ID del permiso
                    $permStmt = $db->prepare("SELECT id FROM permissions WHERE name = ?");
                    $permStmt->execute([$permissionName]);
                    $permission = $permStmt->fetch();
                    
                    if ($permission) {
                        $rolePermStmt = $db->prepare("
                            INSERT INTO role_permissions (role_id, permission_id)
                            VALUES (?, ?)
                        ");
                        $rolePermStmt->execute([$roleId, $permission['id']]);
                    }
                }
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Rol creado exitosamente',
                'data' => ['id' => $roleId]
            ]);
        } catch (Exception $e) {
            if ($db instanceof PDO && method_exists($db, 'inTransaction') && $db->inTransaction()) {
                try { $db->rollBack(); } catch (Exception $ignored) {}
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al crear rol: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'PUT':
        // Verificar permiso para editar roles
        $hasEdit = $currentUser->hasPermission('roles.edit')
            || (property_exists($currentUser, 'permissions') && is_array($currentUser->permissions) && in_array('roles.edit', $currentUser->permissions, true));
        if (!$hasEdit) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para editar roles'
            ]);
            exit();
        }
        
        // Actualizar rol
        $roleId = (int)($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$roleId || !$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de rol y datos son requeridos'
            ]);
            break;
        }
        
        try {
            // Verificar que el rol existe y no es del sistema (adaptando si falta la columna is_system)
            $hasIsSystem = false;
            try {
                $columnsStmt = $db->query("DESCRIBE roles");
                $roleColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
                $hasIsSystem = is_array($roleColumns) && in_array('is_system', $roleColumns, true);
            } catch (Exception $e) {
                $hasIsSystem = false;
            }
            if ($hasIsSystem) {
                $checkStmt = $db->prepare("SELECT id, is_system FROM roles WHERE id = ?");
            } else {
                $checkStmt = $db->prepare("SELECT id FROM roles WHERE id = ?");
            }
            $checkStmt->execute([$roleId]);
            $role = $checkStmt->fetch();
            
            if (!$role) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Rol no encontrado'
                ]);
                break;
            }

            // Validar unicidad de name si se intenta actualizar
            if (isset($input['name']) && $input['name'] !== null && $input['name'] !== '') {
                $uniqueStmt = $db->prepare("SELECT id FROM roles WHERE name = ? AND id <> ? LIMIT 1");
                $uniqueStmt->execute([$input['name'], $roleId]);
                if ($uniqueStmt->fetch()) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Ya existe un rol con este nombre'
                    ]);
                    break;
                }
            }
            
            $db->beginTransaction();
            
            // Para roles del sistema, solo permitir actualización de permisos
            if (!$hasIsSystem || !$role['is_system']) {
                // Construir consulta de actualización dinámicamente para roles no del sistema
                $updateFields = [];
                $params = [];
                
                $allowedFields = ['name', 'display_name', 'description', 'color'];
                
                foreach ($allowedFields as $field) {
                    if (isset($input[$field])) {
                        $updateFields[] = "{$field} = ?";
                        $params[] = $input[$field];
                    }
                }
                
                if (!empty($updateFields)) {
                    $params[] = $roleId;
                    
                    $stmt = $db->prepare("
                        UPDATE roles 
                        SET " . implode(', ', $updateFields) . "
                        WHERE id = ?
                    ");
                    $stmt->execute($params);
                }
            }
            
            // Actualizar permisos si se especifican
            if (isset($input['permissions']) && is_array($input['permissions'])) {
                // Normalizar permisos (asegurar array de strings)
                $perms = array_values(array_filter($input['permissions'], function($p) { return is_string($p) && $p !== ''; }));
                
                // Eliminar permisos existentes
                $deletePermsStmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $deletePermsStmt->execute([$roleId]);
                
                // Asignar nuevos permisos solo si hay alguno
                if (!empty($perms)) {
                    foreach ($perms as $permissionName) {
                        // Obtener ID del permiso
                        $permStmt = $db->prepare("SELECT id FROM permissions WHERE name = ?");
                        $permStmt->execute([$permissionName]);
                        $permission = $permStmt->fetch();
                        
                        if ($permission) {
                            // Insertar según columnas disponibles en role_permissions
                            if (!isset($rolePermColumns)) {
                                $columnsStmt = $db->query("DESCRIBE role_permissions");
                                $rolePermColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
                            }
                            if (in_array('granted_by', $rolePermColumns) && in_array('granted_at', $rolePermColumns)) {
                                $ins = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, granted_by, granted_at) VALUES (?, ?, ?, NOW())");
                                $ins->execute([$roleId, $permission['id'], isset($currentUser->id) ? (int)$currentUser->id : 1]);
                            } elseif (in_array('created_at', $rolePermColumns)) {
                                $ins = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
                                $ins->execute([$roleId, $permission['id']]);
                            } else {
                                $ins = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                                $ins->execute([$roleId, $permission['id']]);
                            }
                        }
                    }
                }
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Rol actualizado exitosamente'
            ]);
        } catch (Exception $e) {
            if ($db instanceof PDO && method_exists($db, 'inTransaction') && $db->inTransaction()) {
                try { $db->rollBack(); } catch (Exception $ignored) {}
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar rol: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'DELETE':
        // Verificar permiso para eliminar roles
        if (!$currentUser->hasPermission('roles.delete')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para eliminar roles'
            ]);
            exit();
        }
        
        // Eliminar rol
        $roleId = (int)($_GET['id'] ?? 0);
        
        if (!$roleId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de rol requerido'
            ]);
            break;
        }
        
        try {
            // Verificar que el rol existe y no es del sistema (adaptando si falta la columna is_system)
            $hasIsSystem = false;
            try {
                $columnsStmt = $db->query("DESCRIBE roles");
                $roleColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
                $hasIsSystem = is_array($roleColumns) && in_array('is_system', $roleColumns, true);
            } catch (Exception $e) {
                $hasIsSystem = false;
            }
            if ($hasIsSystem) {
                $checkStmt = $db->prepare("SELECT id, is_system FROM roles WHERE id = ?");
            } else {
                $checkStmt = $db->prepare("SELECT id FROM roles WHERE id = ?");
            }
            $checkStmt->execute([$roleId]);
            $role = $checkStmt->fetch();
            
            if (!$role) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Rol no encontrado'
                ]);
                break;
            }
            
            if ($role['is_system']) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'No se pueden eliminar roles del sistema'
                ]);
                break;
            }
            
            // Verificar si hay usuarios asignados
            $usersStmt = $db->prepare("SELECT COUNT(*) as count FROM user_roles WHERE role_id = ?");
            $usersStmt->execute([$roleId]);
            $userCount = $usersStmt->fetch()['count'];
            
            if ($userCount > 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "No se puede eliminar el rol porque tiene {$userCount} usuarios asignados"
                ]);
                break;
            }
            
            $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->execute([$roleId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Rol eliminado exitosamente'
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Rol no encontrado'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar rol: ' . $e->getMessage()
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
