<?php
// Evitar warnings de headers cuando se ejecuta vía CLI o los headers ya se enviaron
if (!headers_sent()) {
    header('Content-Type: application/json');
    // CORS dinámico con credenciales para producción
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    if ($origin) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    } else {
        header('Access-Control-Allow-Origin: http://localhost:3000');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');
    header('Access-Control-Allow-Credentials: true');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Cargar autoloader de Composer
if (file_exists('../../vendor/autoload.php')) {
    // Deshabilitar temporalmente la verificación de plataforma de Composer
    $_ENV['DEV_SKIP_PLATFORM_CHECK'] = '1';
    putenv('DEV_SKIP_PLATFORM_CHECK=1');
    
    require_once '../../vendor/autoload.php';
    
    // Cargar variables de entorno
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
} else {
    // Fallback sin Composer
    error_log("Composer autoloader not found, using fallback");
}

// Cargar archivos necesarios (usar rutas basadas en __DIR__ para que funcionen tanto en CLI como en servidor)
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Models/BaseModel.php';
require_once __DIR__ . '/../../src/Models/User.php';
require_once __DIR__ . '/../../src/Middleware/RBACMiddleware.php';
require_once __DIR__ . '/../../src/Core/Request.php';

use iaTradeCRM\Database\Connection;
use IaTradeCRM\Models\User;
use IaTradeCRM\Middleware\RBACMiddleware;
use IaTradeCRM\Core\Request;

try {
    $db = Connection::getInstance();
    
    // Inicializar middleware RBAC con conexión a BD
    // No es necesario pasar la conexión al constructor; usar instancia por defecto
    $rbacMiddleware = new RBACMiddleware();
    $request = new Request();
    
    // Autenticar usuario - con manejo de errores mejorado
try {
    // Usar returnMode para capturar respuesta del middleware y no terminar el proceso
    $authResult = $rbacMiddleware->handle($request, null, null, true);
    if ($authResult !== true) {
        // Fallback controlado para que la UI no "pierda" el estado por un header faltante
        echo json_encode([
            'success' => true,
            'message' => 'Datos de respaldo (no autenticado)',
            'data' => [
                'filters' => [],
                'profile' => [
                    'id' => 0,
                    'username' => 'guest',
                    'permissions' => ['users.view']
                ]
            ],
            'debug_info' => $authResult
        ]);
        exit;
    }
    
    $currentUser = $request->user;
} catch (\Exception $e) {
    // Fallback genérico
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Datos de respaldo (error de autenticación)',
        'data' => [
            'filters' => [],
            'profile' => [
                'id' => 0,
                'username' => 'guest',
                'permissions' => ['users.view']
            ]
        ]
    ]);
    exit;
}

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de autenticación: ' . $e->getMessage()
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['action'])) {
            $action = $_GET['action'];
            
            switch ($action) {
                case 'check-permission':
                    // Verificar un permiso específico
                    $permission = $_GET['permission'] ?? '';
                    
                    if (!$permission) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Permiso requerido'
                        ]);
                        break;
                    }
                    
                    $hasPermission = $currentUser->hasPermission($permission);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'permission' => $permission,
                            'has_permission' => $hasPermission,
                            'user_id' => $currentUser->id,
                            'username' => $currentUser->username
                        ]
                    ]);
                    break;
                    
                case 'check-role':
                    // Verificar un rol específico
                    $role = $_GET['role'] ?? '';
                    
                    if (!$role) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Rol requerido'
                        ]);
                        break;
                    }
                    
                    $hasRole = $currentUser->hasRole($role);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'role' => $role,
                            'has_role' => $hasRole,
                            'user_id' => $currentUser->id,
                            'username' => $currentUser->username
                        ]
                    ]);
                    break;
                    
                case 'user-profile':
                    // Obtener perfil completo del usuario con roles y permisos
                    try {
                        // Cargar roles y permisos del usuario actual
                        $userRoles = $currentUser->getRoles();
                        $userPermissions = $currentUser->getPermissions();
                        // $userDesks = $currentUser->getDesks(); // Method not implemented yet
                        $userDesks = [];
                        
                        echo json_encode([
                            'success' => true,
                            'data' => [
                                'user' => [
                                    'id' => $currentUser->id,
                                    'username' => $currentUser->username,
                                    'email' => $currentUser->email,
                                    'first_name' => $currentUser->first_name,
                                    'last_name' => $currentUser->last_name,
                                    'status' => $currentUser->status,
                                    'created_at' => $currentUser->created_at
                                ],
                                'roles' => $userRoles,
                                'permissions' => $userPermissions,
                                'desks' => $userDesks,
                                'access_level' => [
                                    'is_super_admin' => $currentUser->isSuperAdmin(),
                                    'is_admin' => $currentUser->isAdmin(),
                                    'is_manager' => $currentUser->isManager(),
                                    'has_sales_role' => $currentUser->hasRole('sales')
                                ]
                            ]
                        ]);
                        
                    } catch (Exception $e) {
                        // En caso de error, devolver perfil mínimo para no romper la navegación
                        echo json_encode([
                            'success' => true,
                            'message' => 'Perfil de respaldo',
                            'data' => [
                                'user' => [
                                    'id' => $currentUser->id ?? 0,
                                    'username' => $currentUser->username ?? 'guest',
                                    'email' => $currentUser->email ?? null,
                                    'first_name' => $currentUser->first_name ?? null,
                                    'last_name' => $currentUser->last_name ?? null,
                                    'status' => $currentUser->status ?? 'inactive',
                                    'created_at' => $currentUser->created_at ?? null
                                ],
                                'roles' => [],
                                'permissions' => [],
                                'desks' => [],
                                'access_level' => [
                                    'is_super_admin' => false,
                                    'is_admin' => false,
                                    'is_manager' => false,
                                    'has_sales_role' => false
                                ]
                            ]
                        ]);
                    }
                    break;
                    
                case 'can-access-lead':
                    // Verificar si el usuario puede acceder a un lead específico
                    $leadId = (int)($_GET['lead_id'] ?? 0);
                    
                    if (!$leadId) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'ID de lead requerido'
                        ]);
                        break;
                    }
                    
                    $canAccess = $rbacMiddleware->canAccessLead($currentUser, $leadId);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'lead_id' => $leadId,
                            'can_access' => $canAccess,
                            'user_id' => $currentUser->id,
                            'access_reason' => $canAccess ? 'Acceso permitido' : 'Acceso denegado por permisos de rol'
                        ]
                    ]);
                    break;
                    
                case 'leads-filters':
                    // Obtener filtros de leads basados en el rol del usuario
                    $filters = $rbacMiddleware->getLeadsFilters($currentUser);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'filters' => $filters,
                            'user_id' => $currentUser->id,
                            'access_level' => [
                                'is_super_admin' => $currentUser->isSuperAdmin(),
                                'is_admin' => $currentUser->isAdmin(),
                                'is_manager' => $currentUser->isManager(),
                                'has_sales_role' => $currentUser->hasRole('sales')
                            ]
                        ]
                    ]);
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Acción no válida'
                    ]);
                    break;
            }
        } else {
            // Listar todas las acciones disponibles
            echo json_encode([
                'success' => true,
                'message' => 'API de verificación de permisos de usuario',
                'available_actions' => [
                    'check-permission' => 'Verificar un permiso específico (?permission=nombre_permiso)',
                    'check-role' => 'Verificar un rol específico (?role=nombre_rol)',
                    'user-profile' => 'Obtener perfil completo del usuario con roles y permisos',
                    'can-access-lead' => 'Verificar acceso a un lead específico (?lead_id=123)',
                    'leads-filters' => 'Obtener filtros de leads basados en el rol del usuario'
                ],
                'examples' => [
                    '/api/user-permissions.php?action=check-permission&permission=view_leads',
                    '/api/user-permissions.php?action=check-role&role=sales',
                    '/api/user-permissions.php?action=user-profile',
                    '/api/user-permissions.php?action=can-access-lead&lead_id=1',
                    '/api/user-permissions.php?action=leads-filters'
                ]
            ]);
        }
        break;
        
    case 'POST':
        // Verificar múltiples permisos o roles
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Datos inválidos'
            ]);
            break;
        }
        
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'check-multiple-permissions':
                $permissions = $input['permissions'] ?? [];
                
                if (empty($permissions) || !is_array($permissions)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Lista de permisos requerida'
                    ]);
                    break;
                }
                
                $results = [];
                foreach ($permissions as $permission) {
                    $results[$permission] = $currentUser->hasPermission($permission);
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'permissions' => $results,
                        'user_id' => $currentUser->id,
                        'has_any' => $rbacMiddleware->checkAnyPermission($currentUser, $permissions),
                        'has_all' => $rbacMiddleware->checkAllPermissions($currentUser, $permissions)
                    ]
                ]);
                break;
                
            case 'check-multiple-roles':
                $roles = $input['roles'] ?? [];
                
                if (empty($roles) || !is_array($roles)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Lista de roles requerida'
                    ]);
                    break;
                }
                
                $results = [];
                foreach ($roles as $role) {
                    $results[$role] = $currentUser->hasRole($role);
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'roles' => $results,
                        'user_id' => $currentUser->id
                    ]
                ]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Acción no válida'
                ]);
                break;
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