<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;
use Firebase\JWT\JWT;

header('Content-Type: text/plain');

echo "=== TEST DIRECTO DE LOGIN ===\n\n";

// Simular exactamente lo que recibe login.php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Simular el input JSON
$input = [
    'username' => 'admin',
    'password' => 'admin123',
    'remember' => false
];

echo "Input simulado:\n";
print_r($input);
echo "\n";

try {
    $db = Connection::getInstance()->getConnection();
    echo "✅ Conexión a BD exitosa\n";
    
    // Buscar usuario
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
    $stmt->execute([$input['username'], $input['username']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "❌ Usuario no encontrado\n";
        exit();
    }
    
    echo "✅ Usuario encontrado: " . $user['username'] . "\n";
    
    // Verificar contraseña
    if (!password_verify($input['password'], $user['password_hash'])) {
        echo "❌ Contraseña incorrecta\n";
        exit();
    }
    
    echo "✅ Contraseña correcta\n";
    
    // Generar JWT
    $secret = 'your-super-secret-jwt-key-change-in-production-2024';
    $expiration = 24 * 60 * 60; // 24 horas
    
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
    
    $jwt = JWT::encode($payload, $secret, 'HS256');
    echo "✅ JWT generado\n";
    
    // Preparar respuesta
    $userData = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'roles' => $user['roles'] ? explode(',', $user['roles']) : [],
        'permissions' => $user['permissions'] ? array_unique(explode(',', $user['permissions'])) : []
    ];
    
    $response = [
        'success' => true,
        'message' => 'Login exitoso',
        'token' => $jwt,
        'user' => $userData,
        'expires_in' => $expiration
    ];
    
    echo "\n📋 RESPUESTA FINAL:\n";
    echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
    
    echo "\n🎯 RESULTADO: LOGIN EXITOSO\n";
    echo "- Success: true\n";
    echo "- Token: " . substr($jwt, 0, 50) . "...\n";
    echo "- User ID: " . $userData['id'] . "\n";
    echo "- Username: " . $userData['username'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
