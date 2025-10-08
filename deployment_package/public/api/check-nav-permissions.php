<?php
// Verificar permisos de navegación - versión simplificada
require_once __DIR__ . '/../src/Database/Connection.php';

use IaTradeCRM\Database\Connection;

try {
    $connection = Connection::getInstance();
    $pdo = $connection->getConnection();
    
    echo "<h3>=== VERIFICANDO PERMISOS PARA NAVEGACIÓN ===</h3>";
    
    // Obtener el usuario admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin' AND status = 'active' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "<p style='color:red;'>❌ Usuario admin no encontrado</p>";
        exit;
    }
    
    echo "<p>✅ Usuario: {$user['username']} (ID: {$user['id']})</p>";
    
    // Obtener roles del usuario
    $stmt = $pdo->prepare("
        SELECT r.name, r.slug FROM roles r 
        INNER JOIN user_roles ur ON r.id = ur.role_id 
        WHERE ur.user_id = ? AND r.status = 'active'
    ");
    $stmt->execute([$user['id']]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Roles del usuario:</h4><ul>";
    foreach ($roles as $role) {
        echo "<li>{$role['name']} ({$role['slug']})</li>";
    }
    echo "</ul>";
    
    // Obtener permisos del usuario (directos y a través de roles)
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.slug FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ? AND p.status = 'active'
        UNION
        SELECT DISTINCT p.slug FROM permissions p
        INNER JOIN user_permissions up ON p.id = up.permission_id
        WHERE up.user_id = ? AND p.status = 'active'
        ORDER BY slug
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h4>Permisos del usuario (" . count($permissions) . " total):</h4>";
    echo "<pre style='font-size:12px; max-height:200px; overflow:auto;'>";
    print_r($permissions);
    echo "</pre>";
    
    // Verificar permisos necesarios para la navegación
    $requiredPermissions = [
        'view_leads' => 'Ver módulo Leads',
        'view_users' => 'Ver módulo Usuarios', 
        'view_roles' => 'Ver módulo Roles',
        'view_desks' => 'Ver módulo Desks',
        'view_trading_accounts' => 'Ver módulo Trading Accounts',
        'manage_states' => 'Gestionar Estados',
        'view_reports' => 'Ver Reportes',
        'view_deposits_withdrawals' => 'Ver Depósitos/Retiros'
    ];
    
    echo "<h3>=== VERIFICACIÓN DE PERMISOS PARA NAVEGACIÓN ===</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Módulo</th><th>Permiso Requerido</th><th>Estado</th></tr>";
    
    foreach ($requiredPermissions as $slug => $description) {
        $hasPermission = in_array($slug, $permissions);
        $status = $hasPermission ? "<span style='color:green;'>✅ SÍ</span>" : "<span style='color:red;'>❌ NO</span>";
        echo "<tr><td>{$description}</td><td>{$slug}</td><td>{$status}</td></tr>";
    }
    echo "</table>";
    
    echo "<h3>=== ANÁLISIS ===</h3>";
    $missing = [];
    foreach ($requiredPermissions as $slug => $description) {
        if (!in_array($slug, $permissions)) {
            $missing[] = $slug;
        }
    }
    
    if (count($missing) > 0) {
        echo "<p style='color:red;'><strong>Permisos faltantes:</strong> " . implode(', ', $missing) . "</p>";
        echo "<p>Estos permisos necesitan ser agregados al rol 'admin' o al usuario directamente.</p>";
    } else {
        echo "<p style='color:green;'>✅ Todos los permisos necesarios están presentes</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}