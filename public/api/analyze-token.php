<?php
// Analizador de token simple
$token = $_GET['token'] ?? '';

if (empty($token)) {
    echo "<h3>Token no proporcionado</h3>";
    echo "<p>Usa: /analyze-token.php?token=eyJ0eXAiOiJKV1Q...</p>";
    exit;
}

echo "<h3>=== ANALIZANDO TOKEN ===</h3>";
echo "<p><strong>Token:</strong> " . substr($token, 0, 50) . "...</p>";

// Decodificar manualmente el JWT (sin verificar firma)
$parts = explode('.', $token);
if (count($parts) !== 3) {
    echo "<p style='color:red;'>❌ Token inválido - formato incorrecto</p>";
    exit;
}

try {
    // Decodificar payload (segunda parte)
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    
    echo "<h4>Información del token:</h4>";
    echo "<ul>";
    echo "<li><strong>Usuario ID:</strong> " . ($payload['user_id'] ?? 'N/A') . "</li>";
    echo "<li><strong>Username:</strong> " . ($payload['username'] ?? 'N/A') . "</li>";
    echo "<li><strong>Email:</strong> " . ($payload['email'] ?? 'N/A') . "</li>";
    echo "<li><strong>Roles:</strong> " . implode(', ', $payload['roles'] ?? []) . "</li>";
    echo "<li><strong>Cantidad de permisos:</strong> " . count($payload['permissions'] ?? []) . "</li>";
    echo "<li><strong>Expira:</strong> " . (isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : 'N/A') . "</li>";
    
    if (isset($payload['exp'])) {
        $timeRemaining = $payload['exp'] - time();
        echo "<li><strong>Tiempo restante:</strong> " . $timeRemaining . " segundos";
        if ($timeRemaining <= 0) {
            echo " <span style='color:red;'>(EXPIRADO)</span>";
        }
        echo "</li>";
    }
    echo "</ul>";
    
    // Verificar permisos clave
    $permissions = $payload['permissions'] ?? [];
    echo "<h4>Permisos importantes:</h4>";
    echo "<ul>";
    
    $requiredPermissions = [
        'view_leads' => 'Ver Leads',
        'view_users' => 'Ver Usuarios', 
        'view_roles' => 'Ver Roles',
        'view_desks' => 'Ver Desks',
        'manage_states' => 'Gestionar Estados',
        'view_trading_accounts' => 'Ver Cuentas Trading'
    ];
    
    foreach ($requiredPermissions as $perm => $label) {
        $hasPermission = in_array($perm, $permissions);
        echo "<li>{$label} ({$perm}): " . ($hasPermission ? "<span style='color:green;'>✅ SÍ</span>" : "<span style='color:red;'>❌ NO</span>") . "</li>";
    }
    
    echo "</ul>";
    
    echo "<h4>Todos los permisos:</h4>";
    echo "<pre style='font-size:10px; max-height:200px; overflow:auto;'>";
    print_r($permissions);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error al decodificar: " . $e->getMessage() . "</p>";
}