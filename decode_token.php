<?php
// Decodificar y verificar el token actual
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$token = trim(file_get_contents('current_token.txt'));
$secret = $_ENV['JWT_SECRET'] ?? 'password';

echo "=== ANALIZANDO TOKEN ACTUAL ===\n\n";
echo "Token: " . substr($token, 0, 50) . "...\n\n";

try {
    $decoded = JWT::decode($token, new Key($secret, 'HS256'));
    
    echo "✅ Token válido\n\n";
    echo "Información del token:\n";
    echo "- Usuario ID: {$decoded->user_id}\n";
    echo "- Username: {$decoded->username}\n";
    echo "- Email: {$decoded->email}\n";
    echo "- Roles: " . implode(', ', $decoded->roles) . "\n";
    echo "- Permisos: " . count($decoded->permissions) . " permisos\n";
    echo "- Expira: " . date('Y-m-d H:i:s', $decoded->exp) . "\n";
    echo "- Tiempo restante: " . ($decoded->exp - time()) . " segundos\n\n";
    
    // Verificar permisos clave
    $requiredPermissions = ['view_leads', 'view_users', 'view_roles', 'view_desks', 'manage_states', 'view_trading_accounts'];
    
    echo "Permisos requeridos para los módulos:\n";
    foreach ($requiredPermissions as $permission) {
        $hasPermission = in_array($permission, $decoded->permissions);
        echo "- {$permission}: " . ($hasPermission ? '✅ SÍ' : '❌ NO') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "El token puede estar expirado o ser inválido\n";
}