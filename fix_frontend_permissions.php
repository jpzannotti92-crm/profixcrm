<?php
$host = 'localhost';
$dbname = 'spin2pay_profixcrm';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener el ID del rol admin
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'admin'");
    $stmt->execute();
    $adminRole = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminRole) {
        echo "Error: No se encontró el rol 'admin'\n";
        exit;
    }
    
    $adminRoleId = $adminRole['id'];
    echo "Rol admin encontrado con ID: $adminRoleId\n\n";
    
    // Permisos del frontend que necesitamos
    $frontendPermissions = [
        'view_leads' => 'view_leads',
        'view_users' => 'view_users', 
        'view_roles' => 'view_roles',
        'view_desks' => 'view_desks',
        'view_trading_accounts' => 'view_trading_accounts'
    ];
    
    echo "Verificando permisos del frontend:\n";
    echo "===================================\n";
    
    foreach ($frontendPermissions as $code => $name) {
        // Verificar si el permiso existe
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code = ? OR name = ?");
        $stmt->execute([$code, $name]);
        $permission = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($permission) {
            $permissionId = $permission['id'];
            echo "✅ Permiso '$name' existe (ID: $permissionId)\n";
            
            // Verificar si el admin tiene este permiso
            $stmt = $pdo->prepare("SELECT * FROM role_permissions WHERE role_id = ? AND permission_id = ?");
            $stmt->execute([$adminRoleId, $permissionId]);
            $hasPermission = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($hasPermission) {
                echo "   ✅ El admin tiene este permiso\n";
            } else {
                echo "   ❌ El admin NO tiene este permiso\n";
                
                // Asignar el permiso al admin
                try {
                    $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                    $stmt->execute([$adminRoleId, $permissionId]);
                    echo "   ✅ Permiso asignado al admin\n";
                } catch(PDOException $e) {
                    echo "   ❌ Error al asignar: " . $e->getMessage() . "\n";
                }
            }
        } else {
            echo "❌ Permiso '$name' no existe\n";
            
            // Crear el permiso
            try {
                $stmt = $pdo->prepare("INSERT INTO permissions (code, name, description) VALUES (?, ?, ?)");
                $stmt->execute([$code, $name, "Permiso para $name"]);
                $permissionId = $pdo->lastInsertId();
                echo "   ✅ Permiso creado (ID: $permissionId)\n";
                
                // Asignar al admin
                $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                $stmt->execute([$adminRoleId, $permissionId]);
                echo "   ✅ Permiso asignado al admin\n";
                
            } catch(PDOException $e) {
                echo "   ❌ Error al crear: " . $e->getMessage() . "\n";
            }
        }
        echo "\n";
    }
    
    echo "¡Proceso completado!\n";
    
} catch(PDOException $e) {
    echo "Error de conexión: " . $e->getMessage() . "\n";
}
?>