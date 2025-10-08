<?php
require_once __DIR__ . '/bootstrap.php';
// Evitar fatal de Composer en PHP < 8.2
if (PHP_VERSION_ID >= 80200 && file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    // Cargar variables de entorno (.env) para alinear el secreto JWT con el middleware
    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../');
        if (method_exists($dotenv, 'overload')) {
            $dotenv->overload();
        } else {
            $dotenv->load();
        }
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

use iaTradeCRM\Database\Connection;
use IaTradeCRM\Models\User;
use IaTradeCRM\Middleware\RBACMiddleware;
use IaTradeCRM\Core\Request;

header('Content-Type: application/json');
// CORS: permitir credenciales y origen dinámico (no usar "*")
$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // Fallback seguro para desarrollo
    header('Access-Control-Allow-Origin: http://localhost:3000');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Inicializar middleware RBAC
$rbacMiddleware = new RBACMiddleware();
$request = new Request();

// Incluir el formateador de respuestas y manejador de errores
require_once __DIR__ . '/../../src/Core/ResponseFormatter.php';
require_once __DIR__ . '/../../src/Core/ErrorHandler.php';

use IaTradeCRM\Core\ResponseFormatter;
use IaTradeCRM\Core\ErrorHandler;

// Autenticar usuario desde el middleware (sin exigir permiso aquí). Los permisos se validan por método más abajo
try {
    // Usar returnMode (cuarto parámetro boolean) para evitar exit() del middleware y permitir manejo controlado
    // Firma: handle(Request $request, $requiredPermission = null, $requiredRole = null, $returnMode = false)
    $authResult = $rbacMiddleware->handle($request, null, null, true);
    // Si authResult es un array con success=false, la autenticación falló
    if (is_array($authResult) && isset($authResult['success']) && $authResult['success'] === false) {
        // Si el middleware no autorizó:
        // - Para GET: devolver fallback para no romper la UI
        // - Para otros métodos (POST/PUT/DELETE): devolver error explícito con detalle real
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            ResponseFormatter::sendFallback(
                'Datos de respaldo para mesas',
                ['desks' => []],
                [
                    'page' => 1,
                    'limit' => 25,
                    'total' => 0,
                    'pages' => 0
                ]
            );
        } else {
            // Diagnóstico: registrar headers y payload cuando falla autenticación en métodos no-GET
            try {
                $diag = [
                    'method' => $request->getMethod(),
                    'server' => [
                        'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
                        'REDIRECT_HTTP_AUTHORIZATION' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
                        'HTTP_X_AUTH_TOKEN' => $_SERVER['HTTP_X_AUTH_TOKEN'] ?? null,
                    ],
                    'headers' => $request->getHeaders(),
                    'cookies' => [
                        'auth_token_present' => isset($_COOKIE['auth_token'])
                    ],
                    'data' => $request->getData(),
                ];

                // Marcas rápidas de presencia de token
                $diag['observed_token_sources'] = [
                    'auth_header_present' => $request->hasHeader('authorization'),
                    'x_auth_token_present' => $request->hasHeader('x-auth-token'),
                    'cookie_auth_token_present' => isset($_COOKIE['auth_token']),
                    'body_token_present' => $request->has('token'),
                ];

                \IaTradeCRM\Core\ErrorHandler::logError("Auth diagnóstico desks.php: " . json_encode($diag, JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $e) {
                \IaTradeCRM\Core\ErrorHandler::logError("Auth diagnóstico desks.php fallo: " . $e->getMessage());
            }

            // Incluir mensaje y código del middleware si están disponibles
            if (is_array($authResult)) {
                ResponseFormatter::sendError(
                    $authResult['message'] ?? 'No autorizado',
                    ['error_code' => $authResult['error_code'] ?? 'UNAUTHORIZED'],
                    $authResult['status'] ?? 401
                );
            } else {
                ResponseFormatter::sendError(
                    'No autorizado',
                    [],
                    401
                );
            }
        }
        exit;
    }
    
    // Autenticación correcta: el middleware colocó el usuario en $request->user
    $currentUser = $request->user;
} catch (Exception $e) {
    // En caso de error, usar el sistema centralizado de manejo de errores
    ErrorHandler::logError("Error en desks.php: " . $e->getMessage());
    
    // Usar el nuevo sistema de respuestas para proporcionar datos de respaldo
    ResponseFormatter::sendFallback(
        'Datos de respaldo para mesas',
        ['desks' => []],
        [
            'page' => 1,
            'limit' => 25,
            'total' => 0,
            'pages' => 0
        ]
    );
    exit;
}

try {
    $db = Connection::getInstance()->getConnection();
} catch (Exception $e) {
    // Para solicitudes GET, devolver datos de respaldo en lugar de 500
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        \IaTradeCRM\Core\ResponseFormatter::sendFallback(
            'Datos de respaldo para mesas',
            ['desks' => []],
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

$deskColumns = [];
$deskUsersColumns = [];
try {
    $colsStmt = $db->query("SHOW COLUMNS FROM desks");
    $deskColumns = array_map(function($c){ return $c['Field']; }, $colsStmt->fetchAll());
} catch (Exception $e) {
    // Fallback vacío: las operaciones abajo serán más defensivas
    $deskColumns = [];
}
try {
    $colsStmt2 = $db->query("SHOW COLUMNS FROM desk_users");
    $deskUsersColumns = array_map(function($c){ return $c['Field']; }, $colsStmt2->fetchAll());
} catch (Exception $e) {
    $deskUsersColumns = [];
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Validar permiso para ver escritorios (aceptar también permisos del token)
        $hasViewDesks = $currentUser->hasPermission('desks.view')
            || (property_exists($currentUser, 'permissions') && is_array($currentUser->permissions) && in_array('desks.view', $currentUser->permissions, true));
        if (!$hasViewDesks) {
            ResponseFormatter::sendError(
                'No tienes permisos para ver escritorios',
                [],
                403
            );
            exit();
        }
        
        if (isset($_GET['id'])) {
            // Obtener mesa específica
            $deskId = (int)$_GET['id'];
            
            try {
                $stmt = $db->prepare("
                    SELECT d.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as manager_name,
                           COUNT(DISTINCT du.user_id) as user_count,
                           COUNT(DISTINCT l.id) as lead_count
                    FROM desks d
                    LEFT JOIN users u ON d.manager_id = u.id
                    LEFT JOIN desk_users du ON d.id = du.desk_id
                    LEFT JOIN leads l ON d.id = l.desk_id
                    WHERE d.id = ?
                    GROUP BY d.id
                ");
                $stmt->execute([$deskId]);
                $desk = $stmt->fetch();
                
                if ($desk) {
                    // Obtener usuarios asignados a la mesa
                    $usersStmt = $db->prepare("
                        SELECT u.id, u.username, u.first_name, u.last_name, u.email,
                               du.is_primary, du.assigned_at
                        FROM desk_users du
                        JOIN users u ON du.user_id = u.id
                        WHERE du.desk_id = ?
                        ORDER BY du.is_primary DESC, u.first_name
                    ");
                    $usersStmt->execute([$deskId]);
                    $desk['users'] = $usersStmt->fetchAll();
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $desk
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Mesa no encontrada'
                    ]);
                }
            } catch (Exception $e) {
                ErrorHandler::logError("Error al obtener mesa específica: " . $e->getMessage());
                ResponseFormatter::sendFallback(
                    'Datos de respaldo para mesa específica',
                    ['desk' => null],
                    null
                );
            }
        } else {
            // Obtener lista de mesas con filtros y paginación
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $manager_id = $_GET['manager_id'] ?? '';
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 25);
            $offset = ($page - 1) * $limit;
            
            // Construir consulta con filtros
            $whereConditions = [];
            $params = [];
            
            if ($search) {
                $whereConditions[] = "(d.name LIKE ? OR d.description LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm]);
            }
            
            if ($status) {
                $whereConditions[] = "d.status = ?";
                $params[] = $status;
            }
            
            if ($manager_id) {
                $whereConditions[] = "d.manager_id = ?";
                $params[] = $manager_id;
            }
            
            $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            try {
                // Contar total de registros
                $countStmt = $db->prepare("
                    SELECT COUNT(*) as total
                    FROM desks d
                    LEFT JOIN users u ON d.manager_id = u.id
                    {$whereClause}
                ");
                $countStmt->execute($params);
                $total = $countStmt->fetch()['total'];
                
                // Obtener mesas paginadas con usuarios asignados
                $stmt = $db->prepare("
                    SELECT d.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as manager_name,
                           COUNT(DISTINCT du.user_id) as user_count,
                           COUNT(DISTINCT l.id) as lead_count,
                           GROUP_CONCAT(
                               DISTINCT CONCAT(du_users.first_name, ' ', du_users.last_name)
                               ORDER BY du_users.first_name SEPARATOR ', '
                           ) as assigned_users
                    FROM desks d
                    LEFT JOIN users u ON d.manager_id = u.id
                    LEFT JOIN desk_users du ON d.id = du.desk_id
                    LEFT JOIN users du_users ON du.user_id = du_users.id
                    LEFT JOIN leads l ON d.id = l.desk_id
                    {$whereClause}
                    GROUP BY d.id
                    ORDER BY d.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute(array_merge($params, [$limit, $offset]));
                $desks = $stmt->fetchAll();
                
                $totalPages = ceil($total / $limit);
                
                echo json_encode([
                    'success' => true,
                    'data' => $desks,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => (int)$total,
                        'pages' => $totalPages
                    ]
                ]);
            } catch (Exception $e) {
                ErrorHandler::logError("Error al obtener lista de mesas: " . $e->getMessage());
                ResponseFormatter::sendFallback(
                    'Datos de respaldo para lista de mesas',
                    ['desks' => []],
                    [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => 0,
                        'pages' => 0
                    ]
                );
            }
        }
        break;
        
    case 'POST':
        // Verificar permiso para crear escritorios (aceptar también permisos del token)
        $hasCreatePerm = $currentUser->hasPermission('desks.create')
            || (property_exists($currentUser, 'permissions') && is_array($currentUser->permissions) && in_array('desks.create', $currentUser->permissions, true));
        if (!$hasCreatePerm) {
            \IaTradeCRM\Core\ErrorHandler::logError("Permiso denegado en POST /desks para usuario {$currentUser->id}. Permisos token: " . json_encode($currentUser->permissions));
            ResponseFormatter::sendError(
                'No tienes permisos para crear escritorios',
                [],
                403
            );
            exit();
        }
        
        // Crear nueva mesa
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            ResponseFormatter::sendError(
                'Datos inválidos',
                [],
                400
            );
            break;
        }
        
        // Validaciones básicas
        $required = ['name'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                ResponseFormatter::sendError(
                    "El campo {$field} es requerido",
                    [],
                    400
                );
                exit;
            }
        }
        
        // Normalizar entradas vacías a tipos válidos
        if (isset($input['manager_id']) && $input['manager_id'] === '') { $input['manager_id'] = null; }
        if (isset($input['status']) && $input['status'] === '') { $input['status'] = 'active'; }
        if (isset($input['color']) && $input['color'] === '') { $input['color'] = '#007bff'; }
        if (isset($input['target_monthly']) && $input['target_monthly'] === '') { $input['target_monthly'] = 0; }
        if (isset($input['target_daily']) && $input['target_daily'] === '') { $input['target_daily'] = 0; }
        if (isset($input['commission_rate']) && $input['commission_rate'] === '') { $input['commission_rate'] = 0; }
        
        try {
            $stmt = $db->prepare("
                INSERT INTO desks (
                    name, description, color, manager_id, status, max_leads, 
                    auto_assign, working_hours_start, working_hours_end, timezone
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Construir INSERT compatible según columnas existentes
            $insertFields = ['name'];
            $defaults = [
                'description' => null,
                'color' => '#007bff',
                'manager_id' => null,
                'status' => 'active',
                'max_leads' => 1000,
                'auto_assign' => 0,
                'working_hours_start' => '09:00:00',
                'working_hours_end' => '18:00:00',
                'timezone' => 'UTC',
                'target_monthly' => 0,
                'target_daily' => 0,
                'commission_rate' => 0,
                'created_by' => isset($currentUser->id) ? $currentUser->id : 1
            ];
            foreach ([
                'description','color','manager_id','status','max_leads','auto_assign',
                'working_hours_start','working_hours_end','timezone','target_monthly',
                'target_daily','commission_rate','created_by'
            ] as $field) {
                if (in_array($field, $deskColumns, true)) {
                    $insertFields[] = $field;
                }
            }
            $values = [];
            $values[] = $input['name'];
            foreach (array_slice($insertFields, 1) as $field) {
                $values[] = array_key_exists($field, $input) ? $input[$field] : $defaults[$field];
            }
            // Ejecutar INSERT con tolerancia: si aparece una columna desconocida, reintenta sin ella
            $maxRetries = 3;
            $attempt = 0;
            while (true) {
                $placeholders = implode(', ', array_fill(0, count($insertFields), '?'));
                $sql = "INSERT INTO desks (" . implode(', ', $insertFields) . ") VALUES (" . $placeholders . ")";
                $stmt = $db->prepare($sql);
                try {
                    $stmt->execute($values);
                    break; // Éxito
                } catch (\Exception $eInsert) {
                    $msg = $eInsert->getMessage();
                    if (strpos($msg, 'Unknown column') !== false && $attempt < $maxRetries && preg_match("/Unknown column '([^']+)'/", $msg, $m)) {
                        $unknown = $m[1];
                        if (in_array($unknown, $insertFields, true)) {
                            \IaTradeCRM\Core\ErrorHandler::logError("Columna desconocida '{$unknown}' en INSERT de desks; reintentando sin ella.");
                            // Remover columna problemática y recalcular valores
                            $insertFields = array_values(array_filter($insertFields, function($f) use ($unknown) { return $f !== $unknown; }));
                            $values = [];
                            $values[] = $input['name'];
                            foreach (array_slice($insertFields, 1) as $field) {
                                $values[] = array_key_exists($field, $input) ? $input[$field] : $defaults[$field];
                            }
                            $attempt++;
                            continue; // Reintentar
                        }
                    }
                    throw $eInsert;
                }
            }
            
            $deskId = $db->lastInsertId();
            
            // Asignar usuarios si se especifican
            if (!empty($input['users']) && is_array($input['users'])) {
                foreach ($input['users'] as $userId) {
                    // Inserción dinámica si faltan columnas en desk_users
                    if (!in_array('assigned_by', $deskUsersColumns, true) || !in_array('is_primary', $deskUsersColumns, true)) {
                        $duFields = ['desk_id', 'user_id'];
                        $duValues = [$deskId, $userId];
                        if (in_array('assigned_by', $deskUsersColumns, true)) {
                            $duFields[] = 'assigned_by';
                            $duValues[] = 1;
                        }
                        if (in_array('is_primary', $deskUsersColumns, true)) {
                            $duFields[] = 'is_primary';
                            $duValues[] = 0;
                        }
                        if (in_array('assigned_at', $deskUsersColumns, true)) {
                            $duFields[] = 'assigned_at';
                            $duValues[] = date('Y-m-d H:i:s');
                        }
                        $placeholders = implode(', ', array_fill(0, count($duFields), '?'));
                        $sqlDU = "INSERT INTO desk_users (" . implode(', ', $duFields) . ") VALUES (" . $placeholders . ")";
                        $userStmtDyn = $db->prepare($sqlDU);
                        $userStmtDyn->execute($duValues);
                        continue;
                    }
                    $userStmt = $db->prepare("
                        INSERT INTO desk_users (desk_id, user_id, assigned_by, is_primary)
                        VALUES (?, ?, ?, ?)
                    ");
                    $userStmt->execute([$deskId, $userId, 1, false]); // Asignado por admin
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Mesa creada exitosamente',
                'data' => ['id' => $deskId]
            ]);
        } catch (Exception $e) {
            ErrorHandler::logError("Error al crear mesa: " . $e->getMessage());
            ResponseFormatter::sendError(
                'Error al crear mesa',
                ['id' => null],
                500
            );
        }
        break;
        
    case 'PUT':
        // Verificar permiso para editar escritorios (aceptar también permisos del token)
        $hasEditPerm = $currentUser->hasPermission('desks.edit')
            || (property_exists($currentUser, 'permissions') && is_array($currentUser->permissions) && in_array('desks.edit', $currentUser->permissions, true));
        if (!$hasEditPerm) {
            \IaTradeCRM\Core\ErrorHandler::logError("Permiso denegado en PUT /desks para usuario {$currentUser->id}. Permisos token: " . json_encode($currentUser->permissions));
            ResponseFormatter::sendError(
                'No tienes permisos para editar escritorios',
                [],
                403
            );
            exit();
        }
        
        // Actualizar mesa
        $deskId = (int)($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$deskId || !$input) {
            ResponseFormatter::sendError(
                'ID de mesa y datos son requeridos',
                [],
                400
            );
            break;
        }
        
        try {
            // Verificar que la mesa existe
            $checkStmt = $db->prepare("SELECT id FROM desks WHERE id = ?");
            $checkStmt->execute([$deskId]);
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Mesa no encontrada'
                ]);
                break;
            }
            
            // Construir consulta de actualización dinámicamente
            $updateFields = [];
            $params = [];
            
            // Limitar a columnas existentes en la tabla para evitar errores
            $allowedFieldsCandidate = [
                'name', 'description', 'color', 'manager_id', 'status', 
                'max_leads', 'auto_assign', 'working_hours_start', 
                'working_hours_end', 'timezone', 'target_monthly',
                'target_daily', 'commission_rate'
            ];
            $allowedFields = array_values(array_filter($allowedFieldsCandidate, function($f) use ($deskColumns) {
                return in_array($f, $deskColumns, true);
            }));
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "{$field} = ?";
                    $params[] = $input[$field];
                }
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'No hay campos para actualizar'
                ]);
                break;
            }
            
            $params[] = $deskId;
            
            $stmt = $db->prepare("
                UPDATE desks 
                SET " . implode(', ', $updateFields) . "
                WHERE id = ?
            ");
            $stmt->execute($params);
            
            // Actualizar usuarios si se especifican
            if (isset($input['users']) && is_array($input['users'])) {
                // Eliminar asignaciones existentes
                $deleteUsersStmt = $db->prepare("DELETE FROM desk_users WHERE desk_id = ?");
                $deleteUsersStmt->execute([$deskId]);
                
                // Asignar nuevos usuarios
                foreach ($input['users'] as $userId) {
                    // Inserción dinámica si faltan columnas en desk_users
                    if (!in_array('assigned_by', $deskUsersColumns, true) || !in_array('is_primary', $deskUsersColumns, true)) {
                        $duFields = ['desk_id', 'user_id'];
                        $duValues = [$deskId, $userId];
                        if (in_array('assigned_by', $deskUsersColumns, true)) {
                            $duFields[] = 'assigned_by';
                            $duValues[] = 1;
                        }
                        if (in_array('is_primary', $deskUsersColumns, true)) {
                            $duFields[] = 'is_primary';
                            $duValues[] = 0;
                        }
                        if (in_array('assigned_at', $deskUsersColumns, true)) {
                            $duFields[] = 'assigned_at';
                            $duValues[] = date('Y-m-d H:i:s');
                        }
                        $placeholders = implode(', ', array_fill(0, count($duFields), '?'));
                        $sqlDU = "INSERT INTO desk_users (" . implode(', ', $duFields) . ") VALUES (" . $placeholders . ")";
                        $userStmtDyn = $db->prepare($sqlDU);
                        $userStmtDyn->execute($duValues);
                        continue;
                    }
                    $userStmt = $db->prepare("
                        INSERT INTO desk_users (desk_id, user_id, assigned_by, is_primary)
                        VALUES (?, ?, ?, ?)
                    ");
                    $userStmt->execute([$deskId, $userId, 1, false]); // Asignado por admin
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Mesa actualizada exitosamente'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar mesa: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'DELETE':
        // Verificar permiso para eliminar escritorios (aceptar también permisos del token)
        $hasDeletePerm = $currentUser->hasPermission('desks.delete')
            || (property_exists($currentUser, 'permissions') && is_array($currentUser->permissions) && in_array('desks.delete', $currentUser->permissions, true));
        if (!$hasDeletePerm) {
            \IaTradeCRM\Core\ErrorHandler::logError("Permiso denegado en DELETE /desks para usuario {$currentUser->id}. Permisos token: " . json_encode($currentUser->permissions));
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para eliminar escritorios'
            ]);
            exit();
        }
        
        // Eliminar mesa
        $deskId = (int)($_GET['id'] ?? 0);
        
        if (!$deskId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de mesa requerido'
            ]);
            break;
        }
        
        // No permitir eliminar la mesa principal
        if ($deskId === 1) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No se puede eliminar la mesa principal'
            ]);
            break;
        }
        
        try {
            // Verificar si hay leads asignados
            $leadsStmt = $db->prepare("SELECT COUNT(*) as count FROM leads WHERE desk_id = ?");
            $leadsStmt->execute([$deskId]);
            $leadCount = $leadsStmt->fetch()['count'];
            
            if ($leadCount > 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "No se puede eliminar la mesa porque tiene {$leadCount} leads asignados"
                ]);
                break;
            }
            
            $stmt = $db->prepare("DELETE FROM desks WHERE id = ?");
            $stmt->execute([$deskId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Mesa eliminada exitosamente'
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Mesa no encontrada'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar mesa: ' . $e->getMessage()
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
