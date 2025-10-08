<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Core/Request.php';
require_once __DIR__ . '/../../src/Middleware/RBACMiddleware.php';
// Asegurar carga de modelos sin depender únicamente del autoload
require_once __DIR__ . '/../../src/Models/BaseModel.php';
require_once __DIR__ . '/../../src/Models/User.php';

use iaTradeCRM\Database\Connection;
use IaTradeCRM\Core\Request;
use IaTradeCRM\Middleware\RBACMiddleware;
use IaTradeCRM\Models\User;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Permitir pasar token por query string para diagnóstico si no llega el header
$token = $_GET['token'] ?? null;
if ($token) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
}

try {
    $db = Connection::getInstance();
    $request = new Request();
    $rbac = new RBACMiddleware();

    // Autenticación y extracción de usuario
    $auth = $rbac->handle($request);

    $result = [
        'success' => $auth === true,
        'message' => $auth === true ? 'Autenticado' : 'No autenticado o token inválido',
        'user' => null,
        'checks' => []
    ];

    if ($auth === true && isset($request->user)) {
        $u = $request->user;
        $result['user'] = [
            'id' => $u->id ?? null,
            'username' => $u->username ?? null,
            'roles' => $u->roles ?? [],
            'permissions' => $u->permissions ?? [],
            'is_super_admin' => method_exists($u, 'isSuperAdmin') ? $u->isSuperAdmin() : false
        ];

        // Comprobar permisos clave usando API pública del modelo
        foreach (['users.view','desks.view','leads.view.all','roles.view'] as $perm) {
            $result['checks'][$perm] = method_exists($u, 'hasPermission') && $u->hasPermission($perm) ? 1 : 0;
        }
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>