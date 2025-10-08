<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== SIMPLE DEBUG PERMISSIONS ===\n\n";

try {
    // Conectar a la base de datos directamente
    $host = 'localhost';
    $dbname = 'iatrade_crm';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. VERIFICANDO USUARIO ADMIN:\n";
    
    // Buscar usuario admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        echo "❌ Usuario admin no encontrado\n";
        exit();
    }
    
    echo "✅ Usuario admin encontrado (ID: " . $admin['id'] . ")\n";
    
    echo "\n2. VERIFICANDO PERMISOS DEL USUARIO:\n";
    
    // Obtener permisos del usuario
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.name, p.display_name, p.module
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ?
        ORDER BY p.module, p.name
    ");
    $stmt->execute([$admin['id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Permisos encontrados: " . count($permissions) . "\n";
    
    $hasLeadsView = false;
    foreach ($permissions as $perm) {
        echo "- " . $perm['name'] . " (" . $perm['display_name'] . ") [" . $perm['module'] . "]\n";
        if ($perm['name'] === 'leads.view') {
            $hasLeadsView = true;
        }
    }
    
    echo "\n3. VERIFICACIÓN ESPECÍFICA:\n";
    echo "¿Tiene permiso 'leads.view'? " . ($hasLeadsView ? "✅ SÍ" : "❌ NO") . "\n";
    
    echo "\n4. SIMULANDO VERIFICACIÓN DEL FRONTEND:\n";
    echo "Frontend busca: 'view_leads'\n";
    echo "AuthService mapea 'view_leads' -> 'leads.view'\n";
    echo "¿Permiso encontrado después del mapeo? " . ($hasLeadsView ? "✅ SÍ" : "❌ NO") . "\n";
    
    echo "\n5. VERIFICANDO ENDPOINT user-permissions.php:\n";
    
    // Simular la llamada directa al endpoint
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['action'] = 'user-profile';
    
    // Crear un token JWT simple para el test
    $payload = json_encode(['user_id' => $admin['id'], 'exp' => time() + 3600]);
    $token = base64_encode($payload);
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    
    echo "Token de prueba creado para usuario ID: " . $admin['id'] . "\n";
    
    // Verificar si el endpoint está funcionando
    $endpointFile = 'public/api/user-permissions.php';
    if (file_exists($endpointFile)) {
        echo "✅ Endpoint encontrado: " . $endpointFile . "\n";
    } else {
        echo "❌ Endpoint no encontrado: " . $endpointFile . "\n";
    }
    
    echo "\n6. VERIFICANDO CONFIGURACIÓN DE CORS:\n";
    echo "Access-Control-Allow-Origin debería estar configurado para localhost:3000\n";
    
    echo "\n7. POSIBLES CAUSAS DEL PROBLEMA:\n";
    echo "a) El token JWT no se está enviando correctamente desde el frontend\n";
    echo "b) El middleware RBAC no está funcionando correctamente\n";
    echo "c) Hay un problema de CORS entre frontend (3000) y backend (8000)\n";
    echo "d) El mapeo de permisos en authService.js no está funcionando\n";
    echo "e) Los permisos se están cargando después de que se renderiza el componente\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEBUG ===\n";
?>