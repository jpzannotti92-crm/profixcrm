<?php
$config = require_once 'config/database.php';
$dbConfig = $config['connections']['mysql'];

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8", 
        $dbConfig['username'], 
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Asignando permisos a usuarios existentes...\n\n";
    
    // Obtener todos los usuarios con sus roles
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.email, r.name as role_name 
        FROM users u 
        LEFT JOIN user_roles ur ON u.id = ur.user_id 
        LEFT JOIN roles r ON ur.role_id = r.id
        ORDER BY u.id
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener IDs de permisos
    $permissions = [];
    $permissionNames = ['leads.view.all', 'leads.view.assigned', 'leads.view.desk', 'leads.assign'];
    
    foreach ($permissionNames as $permName) {
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
        $stmt->execute([$permName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $permissions[$permName] = $result['id'];
        }
    }
    
    echo "Permisos encontrados:\n";
    foreach ($permissions as $name => $id) {
        echo "- $name (ID: $id)\n";
    }
    echo "\n";
    
    // Asignar permisos según el rol del usuario
    foreach ($users as $user) {
        $userId = $user['id'];
        $roleName = $user['role_name'];
        $username = $user['username'];
        
        echo "Procesando usuario: $username (Rol: $roleName)\n";
        
        // Limpiar permisos existentes del usuario para estos permisos específicos
        $placeholders = implode(',', array_fill(0, count($permissions), '?'));
        $stmt = $pdo->prepare("
            DELETE FROM user_permissions 
            WHERE user_id = ? AND permission_id IN ($placeholders)
        ");
        $stmt->execute(array_merge([$userId], array_values($permissions)));
        
        // Asignar permisos según el rol
        $permissionsToAssign = [];
        
        switch ($roleName) {
            case 'super_admin':
            case 'admin':
                // Admins pueden ver todos los leads y asignar
                $permissionsToAssign = ['leads.view.all', 'leads.assign'];
                break;
                
            case 'manager':
                // Managers pueden ver leads de su desk y asignar
                $permissionsToAssign = ['leads.view.desk', 'leads.assign'];
                break;
                
            case 'Sales Agent':
            case 'sales':
            case 'agent':
                // Sales agents solo ven leads asignados, no pueden asignar
                $permissionsToAssign = ['leads.view.assigned'];
                break;
                
            default:
                // Roles desconocidos solo ven leads asignados
                $permissionsToAssign = ['leads.view.assigned'];
                echo "  - Rol desconocido '$roleName', asignando permisos básicos\n";
                break;
        }
        
        // Insertar los nuevos permisos
        foreach ($permissionsToAssign as $permName) {
            if (isset($permissions[$permName])) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_permissions (user_id, permission_id, created_at) 
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$userId, $permissions[$permName]]);
                echo "  ✓ Asignado: $permName\n";
            }
        }
        
        echo "\n";
    }
    
    // Mostrar resumen final
    echo "=== RESUMEN FINAL ===\n";
    $stmt = $pdo->query("
        SELECT 
            u.username,
            u.email,
            r.name as role_name,
            GROUP_CONCAT(p.name ORDER BY p.name) as permissions
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        LEFT JOIN user_permissions up ON u.id = up.user_id
        LEFT JOIN permissions p ON up.permission_id = p.id
        WHERE p.name LIKE 'leads.%'
        GROUP BY u.id, u.username, u.email, r.name
        ORDER BY u.username
    ");
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $result) {
        echo "Usuario: {$result['username']} ({$result['role_name']})\n";
        echo "  Permisos: " . ($result['permissions'] ?: 'Ninguno') . "\n\n";
    }
    
    echo "✅ Asignación de permisos completada exitosamente!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>