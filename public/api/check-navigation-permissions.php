<?php
// check_navigation_permissions.php - versión para servidor web
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database/Connection.php';

try {
    $connection = IaTradeCRM\Database\Connection::getInstance();
    $pdo = $connection->getConnection();
    
    echo "<h3>=== VERIFICANDO PERMISOS PARA NAVEGACIÓN ===</h3>";
    
    // Obtener el usuario admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin' AND status = 'active'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "<p style='color:red;'>❌ Usuario admin no encontrado</p>";
        exit;
    }
    
    echo "<p>✅ Usuario: {$user['username']} (ID: {$user['id']})</p>";
    
    // Obtener roles del usuario
    $stmt = $pdo->prepare("
        SELECT r.* FROM roles r 
        INNER JOIN user_roles ur ON r.id = ur.role_id 
        WHERE ur.user_id = ? AND r.status = 'active'
    ");
    $stmt->execute([$user['id']]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Roles del usuario:</h4><ul>";
    foreach ($roles as $role) {
        echo "<li>{$role['name']} (ID: {$role['id']})</li>";
    }
    echo "</ul>";
    
    // Obtener permisos del usuario (directos y a través de roles)
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id, p.name, p.slug, p.description 
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ? AND p.status = 'active'
        UNION
        SELECT DISTINCT p.id, p.name, p.slug, p.description 
        FROM permissions p
        INNER JOIN user_permissions up ON p.id = up.permission_id
        WHERE up.user_id = ? AND p.status = 'active'
        ORDER BY p.name
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Permisos del usuario (" . count($permissions) . " total):</h4>";
    echo "<ul>";
    foreach ($permissions as $permission) {
        echo "<li>{$permission['name']} ({$permission['slug']})</li>";
    }
    echo "</ul>";
    
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
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Módulo</th><th>Permiso Requerido</th><th>Estado</th></tr>";
    
    foreach ($requiredPermissions as $slug => $description) {
        $hasPermission = false;
        foreach ($permissions as $permission) {
            if ($permission['slug'] === $slug) {
                $hasPermission = true;
                break;
            }
        }
        $status = $hasPermission ? "<span style='color:green;'>✅ SÍ</span>" : "<span style='color:red;'>❌ NO</span>";
        echo "<tr><td>{$description}</td><td>{$slug}</td><td>{$status}</td></tr>";
    }
    echo "</table>";
    
    echo "<h3>=== SOLUCIÓN PROPUESTA ===</h3>";
    echo "<ol>";
    echo "<li>Verificar si el rol 'admin' tiene estos permisos asignados</li>";
    echo "<li>Si no los tiene, agregarlos al rol</li>";
    echo "<li>Si el usuario no tiene el rol correcto, asignarle el rol adecuado</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}