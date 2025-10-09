<?php
// Verificación JWT desde Authorization y cookie para servidor público
header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Access-Token, X-Token, X-JWT');
// Blindaje contra errores: nunca devolver 500
ini_set('display_errors', '0');
set_exception_handler(function($e){
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(401);
    }
    echo json_encode(['success'=>false,'message'=>'Verificación no disponible','code'=>'verify_exception_deploy']);
});
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(401);
        }
        echo json_encode(['success'=>false,'message'=>'Verificación no disponible','code'=>'verify_fatal_deploy']);
    }
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
if (file_exists(__DIR__ . '/../../../.env')) {
    $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../..');
    if (method_exists($dotenv, 'overload')) { $dotenv->overload(); } else { $dotenv->load(); }
}

// Cargar dependencias del proyecto (evitar fallo de autoload con App\Models)
require_once __DIR__ . '/../../../src/Database/Connection.php';
require_once __DIR__ . '/../../../src/Models/BaseModel.php';
require_once __DIR__ . '/../../../src/Models/User.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use IaTradeCRM\Models\User;

try {
    $token = null;
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $xAuth = $headers['X-Auth-Token'] ?? ($headers['x-auth-token'] ?? '');

    if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $m)) {
        $token = $m[1];
    } elseif (!empty($xAuth)) {
        $token = $xAuth;
    } elseif (isset($_COOKIE['auth_token'])) {
        $token = $_COOKIE['auth_token'];
    }

    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token no proporcionado']);
        exit;
    }

    $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? 'password';

    try {
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        
        try {
            $user = User::find($decoded->user_id);
        } catch (\Throwable $dbEx) {
            // Evitar 500: responder 401 y mensaje estable
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'code' => 'db_error',
                'message' => 'No fue posible validar el usuario actualmente'
            ]);
            exit;
        }

        if (!$user || ($user->status ?? 'inactive') !== 'active') {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Usuario no válido o inactivo']);
            exit;
        }

        $roles = method_exists($user, 'getRoles') ? $user->getRoles() : [];
        $permissions = method_exists($user, 'getPermissions') ? $user->getPermissions() : [];

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'first_name' => $user->first_name ?? null,
                'last_name' => $user->last_name ?? null,
                'roles' => $roles,
                'permissions' => $permissions,
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token inválido o expirado'
        ]);
        exit;
    }
} catch (\Throwable $fatal) {
    // Fallback general: nunca devolver 500, mantener JSON
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No fue posible verificar el token en este momento',
        'code' => 'verify_unavailable'
    ]);
}
?>