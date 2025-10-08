<?php
header('Content-Type: application/json');
// CORS dinámico y credenciales para permitir envío de cookies/headers reales
$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
} else {
    // Fallback seguro para desarrollo
    header('Access-Control-Allow-Origin: http://localhost:3000');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configurar bypass de platform check para desarrollo (PHP < 8.2)
require_once __DIR__ . '/../../platform_check_bypass.php';

// Cargar autoloader de Composer de forma compatible
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    // Asegurar que Composer no haga platform check en desarrollo
    putenv('DEV_SKIP_PLATFORM_CHECK=1');
    $_ENV['DEV_SKIP_PLATFORM_CHECK'] = '1';
    $_SERVER['DEV_SKIP_PLATFORM_CHECK'] = '1';

    require_once __DIR__ . '/../../vendor/autoload.php';

    // Cargar variables de entorno si Dotenv está disponible
    if (class_exists('Dotenv\\Dotenv')) {
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
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (strpos(ltrim($line), '#') === 0) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $_ENV[trim($parts[0])] = trim($parts[1]);
                    putenv(trim($parts[0]) . '=' . trim($parts[1]));
                }
            }
        }
    }
} else {
    // Fallback si no existe vendor/autoload.php
    $envFile = __DIR__ . '/../../.env';
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos(ltrim($line), '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $_ENV[trim($parts[0])] = trim($parts[1]);
                putenv(trim($parts[0]) . '=' . trim($parts[1]));
            }
        }
    }
}

// Cargar archivos necesarios
require_once '../../src/Database/Connection.php';
require_once '../../src/Models/BaseModel.php';
require_once '../../src/Models/User.php';
require_once '../../src/Middleware/RBACMiddleware.php';
require_once '../../src/Core/Request.php';
require_once '../../src/Core/ResponseFormatter.php';

use iaTradeCRM\Database\Connection;
use IaTradeCRM\Models\User;
use IaTradeCRM\Middleware\RBACMiddleware;
use IaTradeCRM\Core\Request;
use IaTradeCRM\Core\ResponseFormatter;

try {
    $db = Connection::getInstance();
    
    // Inicializar middleware RBAC
    $rbacMiddleware = new RBACMiddleware();
    $request = new Request();
    
    // Autenticar usuario utilizando returnMode para evitar exit() y manejar fallback
    // Firma: handle(Request $request, $requiredPermission = null, $requiredRole = null, $returnMode = false)
    $authResult = $rbacMiddleware->handle($request, null, null, true);
    if (is_array($authResult) && isset($authResult['success']) && $authResult['success'] === false) {
        // Si no está autenticado y es GET, devolver datos de respaldo para no romper la UI
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            ResponseFormatter::sendFallback(
                'No autenticado. Mostrando permisos vacíos como respaldo',
                ['permissions' => []]
            );
        }
        // Para métodos distintos de GET, devolver el error explícito
        ResponseFormatter::sendError(
            $authResult['message'] ?? 'No autorizado',
            ['error_code' => $authResult['error_code'] ?? 'UNAUTHORIZED'],
            $authResult['status'] ?? 401
        );
    }
    
    $currentUser = $request->user;

} catch (Exception $e) {
    error_log("Error en permissions.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de autenticación: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Verificar permiso para ver permisos (si hay usuario)
        if (!$currentUser || !$currentUser->hasPermission('roles.view')) {
            // En GET devolver fallback para evitar romper la UI
            ResponseFormatter::sendFallback(
                'Acceso limitado: sin permisos para ver permisos',
                ['permissions' => []]
            );
        }
        
        try {
            // Obtener todos los permisos disponibles
            $stmt = $db->getConnection()->prepare("
                SELECT 
                    id,
                    name,
                    display_name,
                    description,
                    module,
                    created_at
                FROM permissions 
                ORDER BY module, name
            ");
            $stmt->execute();
            $permissions = $stmt->fetchAll();
            
            // Si no hay permisos en la base de datos, crear los permisos básicos
            if (empty($permissions)) {
                $defaultPermissions = [
                    // Roles
                    ['name' => 'roles.view', 'display_name' => 'Ver Roles', 'description' => 'Permite ver la lista de roles', 'module' => 'roles', 'action' => 'view'],
                    ['name' => 'roles.create', 'display_name' => 'Crear Roles', 'description' => 'Permite crear nuevos roles', 'module' => 'roles', 'action' => 'create'],
                    ['name' => 'roles.edit', 'display_name' => 'Editar Roles', 'description' => 'Permite editar roles existentes', 'module' => 'roles', 'action' => 'edit'],
                    ['name' => 'roles.delete', 'display_name' => 'Eliminar Roles', 'description' => 'Permite eliminar roles', 'module' => 'roles', 'action' => 'delete'],
                    
                    // Usuarios
                    ['name' => 'users.view', 'display_name' => 'Ver Usuarios', 'description' => 'Permite ver la lista de usuarios', 'module' => 'users', 'action' => 'view'],
                    ['name' => 'users.view_all', 'display_name' => 'Ver Todos los Usuarios', 'description' => 'Permite ver todos los usuarios sin restricciones', 'module' => 'users', 'action' => 'view_all'],
                    ['name' => 'users.create', 'display_name' => 'Crear Usuarios', 'description' => 'Permite crear nuevos usuarios', 'module' => 'users', 'action' => 'create'],
                    ['name' => 'users.edit', 'display_name' => 'Editar Usuarios', 'description' => 'Permite editar usuarios existentes', 'module' => 'users', 'action' => 'edit'],
                    ['name' => 'users.delete', 'display_name' => 'Eliminar Usuarios', 'description' => 'Permite eliminar usuarios', 'module' => 'users', 'action' => 'delete'],
                    
                    // Leads
                    ['name' => 'leads.view', 'display_name' => 'Ver Leads', 'description' => 'Permite ver la lista de leads', 'module' => 'leads', 'action' => 'view'],
                    ['name' => 'leads.create', 'display_name' => 'Crear Leads', 'description' => 'Permite crear nuevos leads', 'module' => 'leads', 'action' => 'create'],
                    ['name' => 'leads.edit', 'display_name' => 'Editar Leads', 'description' => 'Permite editar leads existentes', 'module' => 'leads', 'action' => 'edit'],
                    ['name' => 'leads.delete', 'display_name' => 'Eliminar Leads', 'description' => 'Permite eliminar leads', 'module' => 'leads', 'action' => 'delete'],
                    
                    // Escritorios
                    ['name' => 'desks.view', 'display_name' => 'Ver Escritorios', 'description' => 'Permite ver la lista de escritorios', 'module' => 'desks', 'action' => 'view'],
                    ['name' => 'desks.create', 'display_name' => 'Crear Escritorios', 'description' => 'Permite crear nuevos escritorios', 'module' => 'desks', 'action' => 'create'],
                    ['name' => 'desks.edit', 'display_name' => 'Editar Escritorios', 'description' => 'Permite editar escritorios existentes', 'module' => 'desks', 'action' => 'edit'],
                    ['name' => 'desks.delete', 'display_name' => 'Eliminar Escritorios', 'description' => 'Permite eliminar escritorios', 'module' => 'desks', 'action' => 'delete'],
                    
                    // Cuentas de Trading
                    ['name' => 'trading_accounts.view', 'display_name' => 'Ver Cuentas de Trading', 'description' => 'Permite ver la lista de cuentas de trading', 'module' => 'trading_accounts', 'action' => 'view'],
                    ['name' => 'trading_accounts.create', 'display_name' => 'Crear Cuentas de Trading', 'description' => 'Permite crear nuevas cuentas de trading', 'module' => 'trading_accounts', 'action' => 'create'],
                    ['name' => 'trading_accounts.edit', 'display_name' => 'Editar Cuentas de Trading', 'description' => 'Permite editar cuentas de trading existentes', 'module' => 'trading_accounts', 'action' => 'edit'],
                    ['name' => 'trading_accounts.delete', 'display_name' => 'Eliminar Cuentas de Trading', 'description' => 'Permite eliminar cuentas de trading', 'module' => 'trading_accounts', 'action' => 'delete'],
                    
                    // Depósitos y Retiros
                    ['name' => 'deposits_withdrawals.view', 'display_name' => 'Ver Depósitos y Retiros', 'description' => 'Permite ver la lista de depósitos y retiros', 'module' => 'deposits_withdrawals', 'action' => 'view'],
                    ['name' => 'deposits_withdrawals.create', 'display_name' => 'Crear Depósitos y Retiros', 'description' => 'Permite crear nuevos depósitos y retiros', 'module' => 'deposits_withdrawals', 'action' => 'create'],
                    ['name' => 'deposits_withdrawals.edit', 'display_name' => 'Editar Depósitos y Retiros', 'description' => 'Permite editar depósitos y retiros existentes', 'module' => 'deposits_withdrawals', 'action' => 'edit'],
                    ['name' => 'deposits_withdrawals.delete', 'display_name' => 'Eliminar Depósitos y Retiros', 'description' => 'Permite eliminar depósitos y retiros', 'module' => 'deposits_withdrawals', 'action' => 'delete'],
                ];
                
                // Insertar permisos por defecto
                $insertStmt = $db->getConnection()->prepare("
                    INSERT INTO permissions (name, display_name, description, module, action, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                foreach ($defaultPermissions as $permission) {
                    $insertStmt->execute([
                        $permission['name'],
                        $permission['display_name'],
                        $permission['description'],
                        $permission['module'],
                        $permission['action']
                    ]);
                }
                
                // Obtener los permisos recién insertados
                $stmt->execute();
                $permissions = $stmt->fetchAll();
            }
            
            ResponseFormatter::sendSuccess(['permissions' => $permissions]);
            
        } catch (Exception $e) {
            ResponseFormatter::sendError('Error al obtener permisos: ' . $e->getMessage());
        }
        break;
        
    default:
        ResponseFormatter::sendError('Método no permitido', [], 405);
        break;
}
?>