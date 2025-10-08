<?php
// Configuración de la base de datos
$host = 'localhost';
$dbname = 'spin2pay_profixcrm';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Conectado a la base de datos exitosamente\n\n";
    
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
    
    // Array de permisos del frontend que necesitamos agregar
    $frontendPermissions = [
        'view_leads' => 'Permiso para ver leads',
        'view_users' => 'Permiso para ver usuarios', 
        'view_roles' => 'Permiso para ver roles',
        'view_desks' => 'Permiso para ver mesas',
        'view_trading_accounts' => 'Permiso para ver cuentas de trading'
    ];
    
    echo "Agregando permisos del frontend al rol admin:\n";
    echo "============================================\n";
    
    foreach ($frontendPermissions as $permissionName => $description) {
        try {
            // Verificar si el permiso ya existe
            $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
            $stmt->execute([$permissionName]);
            $existingPermission = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingPermission) {
                $permissionId = $existingPermission['id'];
                echo "ℹ️  El permiso '$permissionName' ya existe (ID: $permissionId)\n";
            } else {
                // Crear el permiso
                $stmt = $pdo->prepare("INSERT INTO permissions (name, description) VALUES (?, ?)");
                $stmt->execute([$permissionName, $description]);
                $permissionId = $pdo->lastInsertId();
                echo "✅ Permiso '$permissionName' creado exitosamente (ID: $permissionId)\n";
            }
            
            // Verificar si el rol admin ya tiene este permiso
            $stmt = $pdo->prepare("SELECT * FROM role_permissions WHERE role_id = ? AND permission_id = ?");
            $stmt->execute([$adminRoleId, $permissionId]);
            $existingAssignment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingAssignment) {
                echo "ℹ️  El rol admin ya tiene asignado el permiso '$permissionName'\n";
            } else {
                // Asignar el permiso al rol admin
                $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                $stmt->execute([$adminRoleId, $permissionId]);
                echo "✅ Permiso '$permissionName' asignado al rol admin\n";
            }
            
            echo "\n";
            
        } catch(PDOException $e) {
            echo "❌ Error al procesar el permiso '$permissionName': " . $e->getMessage() . "\n\n";
        }
    }
    
    echo "¡Proceso completado!\n";
    
} catch(PDOException $e) {
    echo "Error de conexión: " . $e->getMessage() . "\n";
}
?>