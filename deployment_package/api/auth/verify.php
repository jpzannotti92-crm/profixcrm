<?php
// Verificación de autenticación compatible con Authorization y cookie auth_token
header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

// Autoload y entorno
require_once __DIR__ . '/../../vendor/autoload.php';
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../');
    if (method_exists($dotenv, 'overload')) { $dotenv->overload(); } else { $dotenv->load(); }
}

// Cargar dependencias del proyecto (evitar fallo de autoload con App\Models)
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Models/BaseModel.php';
require_once __DIR__ . '/../../src/Models/User.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;

// Extraer token desde múltiples fuentes
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
        echo json_encode([
            'success' => false,
            'message' => 'Token no proporcionado'
        ]);
        exit;
    }

    $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? 'your-super-secret-jwt-key-change-in-production-2024';

    try {
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        $user = User::find($decoded->user_id);

        if (!$user || ($user->status ?? 'inactive') !== 'active') {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Usuario no válido o inactivo'
            ]);
            exit;
        }

        // Cargar roles y permisos si están disponibles
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
    // Fallback general: nunca devolver 500 para no romper la UI
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No fue posible verificar el token en este momento',
        'code' => 'verify_unavailable'
    ]);
}
?>