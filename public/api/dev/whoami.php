<?php
// Dev endpoint: muestra información del usuario autenticado y sus roles/permisos

// Carga de entorno mínima
if (PHP_VERSION_ID >= 80200 && file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../../');
        if (method_exists($dotenv, 'overload')) { $dotenv->overload(); } else { $dotenv->load(); }
    }
} else {
    $envFile = __DIR__ . '/../../../.env';
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos(ltrim($line), '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) { $_ENV[trim($parts[0])] = trim($parts[1]); }
        }
    }
}

require_once __DIR__ . '/../../../src/Database/Connection.php';
require_once __DIR__ . '/../../../src/Models/BaseModel.php';
require_once __DIR__ . '/../../../src/Models/User.php';
require_once __DIR__ . '/../../../src/Middleware/RBACMiddleware.php';
require_once __DIR__ . '/../../../src/Core/Request.php';

use IaTradeCRM\Middleware\RBACMiddleware;
use IaTradeCRM\Core\Request;

header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:3000';
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$rbac = new RBACMiddleware();
$request = new Request();

$auth = $rbac->handle($request, null, null, true);
if (is_array($auth) && isset($auth['success']) && $auth['success'] === false) {
    http_response_code($auth['status'] ?? 401);
    echo json_encode(['success' => false, 'message' => $auth['message'] ?? 'No autorizado', 'error_code' => $auth['error_code'] ?? 'UNAUTHORIZED']);
    exit();
}

$u = $request->user;
if (!$u) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit();
}

// Construir respuesta
$resp = [
    'success' => true,
    'id' => $u->id,
    'username' => $u->username ?? null,
    'email' => $u->email ?? null,
    'roles_property' => property_exists($u, 'roles') ? $u->roles : null,
    'permissions_property' => property_exists($u, 'permissions') ? $u->permissions : null,
    'is_super_admin' => method_exists($u, 'isSuperAdmin') ? $u->isSuperAdmin() : null,
    'has_roles_view' => method_exists($u, 'hasPermission') ? $u->hasPermission('roles.view') : null,
    'has_desks_view' => method_exists($u, 'hasPermission') ? $u->hasPermission('desks.view') : null,
    'db_roles' => method_exists($u, 'getRoles') ? array_map(function($r){ return is_array($r) && isset($r['name']) ? $r['name'] : $r; }, $u->getRoles()) : null,
    'db_permissions' => method_exists($u, 'getPermissions') ? array_map(function($p){ return is_array($p) && isset($p['name']) ? $p['name'] : $p; }, $u->getPermissions()) : null,
];

echo json_encode($resp);
?>