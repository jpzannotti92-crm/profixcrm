<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Obtener datos del request
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input || !isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Usuario y contraseña requeridos',
        'debug' => [
            'raw_input' => $rawInput,
            'decoded_input' => $input,
            'json_error' => json_last_error_msg()
        ]
    ]);
    exit();
}

$username = trim($input['username']);
$password = trim($input['password']);
$remember = $input['remember'] ?? false;

// Validación básica
if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Usuario y contraseña no pueden estar vacíos']);
    exit();
}

try {
    $db = Connection::getInstance()->getConnection();
    
    // Buscar usuario en la base de datos con información completa
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
        WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'
        GROUP BY u.id
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado o inactivo']);
        exit();
    }
    
    // Verificar contraseña
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
        exit();
    }
    
    // Actualizar último login
    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
    exit();
}

// Configuración JWT
$secret = $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-in-production-2024';
$algorithm = 'HS256';

// Duración del token (24 horas por defecto, 7 días si "recordarme")
$expiration = $remember ? (7 * 24 * 60 * 60) : (24 * 60 * 60);

// Payload del JWT
$payload = [
    'iss' => 'iatrade-crm',
    'aud' => 'iatrade-crm-users',
    'iat' => time(),
    'exp' => time() + $expiration,
    'user_id' => (int)$user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'roles' => $user['roles'] ? explode(',', $user['roles']) : [],
    'permissions' => $user['permissions'] ? array_unique(explode(',', $user['permissions'])) : [],
    'desk_id' => $user['desk_id'] ? (int)$user['desk_id'] : null
];

// Generar JWT
$jwt = JWT::encode($payload, $secret, $algorithm);

// Preparar datos del usuario para la respuesta
$userData = [
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'phone' => $user['phone'],
    'avatar' => $user['avatar'],
    'department' => $user['department'] ?? null,
    'position' => $user['position'] ?? null,
    'desk' => [
        'id' => $user['desk_id'] ? (int)$user['desk_id'] : null,
        'name' => $user['desk_name']
    ],
    'supervisor' => $user['supervisor_first_name'] ? [
        'name' => $user['supervisor_first_name'] . ' ' . $user['supervisor_last_name']
    ] : null,
    'roles' => $user['roles'] ? explode(',', $user['roles']) : [],
    'role_names' => $user['role_names'] ? explode(',', $user['role_names']) : [],
    'permissions' => $user['permissions'] ? array_unique(explode(',', $user['permissions'])) : [],
    'status' => $user['status'],
    'last_login' => date('Y-m-d H:i:s'),
    'settings' => json_decode($user['settings'] ?? '{}', true)
];

// Respuesta exitosa
echo json_encode([
    'success' => true,
    'message' => 'Login exitoso',
    'token' => $jwt,
    'user' => $userData,
    'expires_in' => $expiration
]);
?>

// Validación básica
if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Usuario y contraseña no pueden estar vacíos']);
    exit();
}

try {
    $db = Connection::getInstance()->getConnection();
    
    // Buscar usuario en la base de datos con información completa
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
        WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'
        GROUP BY u.id
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado o inactivo']);
        exit();
    }
    
    // Verificar contraseña
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
        exit();
    }
    
    // Actualizar último login
    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
    exit();
}

// Configuración JWT
$secret = $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-in-production-2024';
$algorithm = 'HS256';

// Duración del token (24 horas por defecto, 7 días si "recordarme")
$expiration = $remember ? (7 * 24 * 60 * 60) : (24 * 60 * 60);

// Payload del JWT
$payload = [
    'iss' => 'iatrade-crm',
    'aud' => 'iatrade-crm-users',
    'iat' => time(),
    'exp' => time() + $expiration,
    'user_id' => (int)$user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'roles' => $user['roles'] ? explode(',', $user['roles']) : [],
    'permissions' => $user['permissions'] ? array_unique(explode(',', $user['permissions'])) : [],
    'desk_id' => $user['desk_id'] ? (int)$user['desk_id'] : null
];

// Generar JWT
$jwt = JWT::encode($payload, $secret, $algorithm);

// Preparar datos del usuario para la respuesta
$userData = [
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'phone' => $user['phone'],
    'avatar' => $user['avatar'],
    'department' => $user['department'] ?? null,
    'position' => $user['position'] ?? null,
    'desk' => [
        'id' => $user['desk_id'] ? (int)$user['desk_id'] : null,
        'name' => $user['desk_name']
    ],
    'supervisor' => $user['supervisor_first_name'] ? [
        'name' => $user['supervisor_first_name'] . ' ' . $user['supervisor_last_name']
    ] : null,
    'roles' => $user['roles'] ? explode(',', $user['roles']) : [],
    'role_names' => $user['role_names'] ? explode(',', $user['role_names']) : [],
    'permissions' => $user['permissions'] ? array_unique(explode(',', $user['permissions'])) : [],
    'status' => $user['status'],
    'last_login' => date('Y-m-d H:i:s'),
    'settings' => json_decode($user['settings'] ?? '{}', true)
];

// Respuesta exitosa
echo json_encode([
    'success' => true,
    'message' => 'Login exitoso',
    'token' => $jwt,
    'user' => $userData,
    'expires_in' => $expiration
]);
?>

// Validación básica
if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Usuario y contraseña no pueden estar vacíos']);
    exit();
}

try {
    $db = Connection::getInstance()->getConnection();
    
    // Buscar usuario en la base de datos con información completa
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
        WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'
        GROUP BY u.id
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado o inactivo']);
        exit();
    }
    
    // Verificar contraseña
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
        exit();
    }
    
    // Actualizar último login
    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
    exit();
}

// Configuración JWT
$secret = $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-in-production-2024';
$algorithm = 'HS256';

// Duración del token (24 horas por defecto, 7 días si "recordarme")
$expiration = $remember ? (7 * 24 * 60 * 60) : (24 * 60 * 60);

// Payload del JWT
$payload = [
    'iss' => 'iatrade-crm',
    'aud' => 'iatrade-crm-users',
    'iat' => time(),
    'exp' => time() + $expiration,
    'user_id' => (int)$user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'roles' => $user['roles'] ? explode(',', $user['roles']) : [],
    'permissions' => $user['permissions'] ? array_unique(explode(',', $user['permissions'])) : [],
    'desk_id' => $user['desk_id'] ? (int)$user['desk_id'] : null
];

// Generar JWT
$jwt = JWT::encode($payload, $secret, $algorithm);

// Preparar datos del usuario para la respuesta
$userData = [
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'phone' => $user['phone'],
    'avatar' => $user['avatar'],
    'department' => $user['department'],
    'position' => $user['position'],
    'desk' => [
        'id' => $user['desk_id'] ? (int)$user['desk_id'] : null,
        'name' => $user['desk_name']
    ],
    'supervisor' => $user['supervisor_first_name'] ? [
        'name' => $user['supervisor_first_name'] . ' ' . $user['supervisor_last_name']
    ] : null,
    'roles' => $user['roles'] ? explode(',', $user['roles']) : [],
    'role_names' => $user['role_names'] ? explode(',', $user['role_names']) : [],
    'permissions' => $user['permissions'] ? array_unique(explode(',', $user['permissions'])) : [],
    'status' => $user['status'],
    'last_login' => date('Y-m-d H:i:s'),
    'settings' => json_decode($user['settings'] ?? '{}', true)
];

// Respuesta exitosa
echo json_encode([
    'success' => true,
    'message' => 'Login exitoso',
    'token' => $jwt,
    'user' => $userData,
    'expires_in' => $expiration
]);
?>
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'
        GROUP BY u.id
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado o inactivo']);
        exit();
    }
    
    // Verificar contraseña
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
        exit();
    }
    
    // Actualizar último login
    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
    exit();
}

// Generar token JWT simple con expiración de 24 horas
$header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
$payload = json_encode([
    'user_id' => $user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'roles' => $user['roles'] ? explode(',', $user['roles']) : [],
    'role_names' => $user['role_names'] ? explode(',', $user['role_names']) : [],
    'exp' => time() + (24 * 60 * 60) // 24 horas
]);

$base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
$base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

// Clave secreta (en producción debería estar en variables de entorno)
$secret = 'iatrade_crm_secret_key_2024';
$signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret, true);
$base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

$jwt = $base64Header . "." . $base64Payload . "." . $base64Signature;

// Respuesta exitosa
echo json_encode([
    'success' => true,
    'message' => 'Login exitoso',
    'token' => $jwt,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'roles' => $user['roles'] ? explode(',', $user['roles']) : [],
        'role_names' => $user['role_names'] ? explode(',', $user['role_names']) : [],
        'last_login' => date('Y-m-d H:i:s')
    ]
]);
?>
$header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
$payload = json_encode([
    'user_id' => 1,
    'username' => $username,
    'exp' => time() + (24 * 60 * 60), // 24 horas
    'iat' => time() // Issued at
]);

$base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
$base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

$signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, 'your-secret-key', true);
$base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

$token = $base64Header . "." . $base64Payload . "." . $base64Signature;

// Datos del usuario
$userData = [
    'id' => 1,
    'username' => $username,
    'first_name' => ucfirst($username),
    'last_name' => 'Usuario',
    'email' => $username . '@iatrade.com',
    'role' => $username === 'admin' ? 'Administrator' : 'User',
    'status' => 'active',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];

// Respuesta exitosa
echo json_encode([
    'success' => true,
    'message' => 'Login exitoso',
    'data' => [
        'token' => $token,
        'user' => $userData
    ]
]);
?>