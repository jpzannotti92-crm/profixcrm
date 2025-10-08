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
    
    // Lista de permisos del frontend que necesitamos verificar
    $frontendPermissions = [
        'view_leads',
        'view_users', 
        'view_roles',
        'view_desks',
        'manage_states',
        'view_trading_accounts'
    ];
    
    echo "Verificando permisos del frontend para el rol admin:\n";
    echo "==========================================\n";
    
    foreach ($frontendPermissions as $permissionName) {
        // Verificar si existe el permiso
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
        $stmt->execute([$permissionName]);
        $permission = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($permission) {
            $permissionId = $permission['id'];
            
            // Verificar si el rol admin tiene este permiso
            $stmt = $pdo->prepare("SELECT * FROM role_permissions WHERE role_id = ? AND permission_id = ?");
            $stmt->execute([$adminRoleId, $permissionId]);
            $hasPermission = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($hasPermission) {
                echo "✅ $permissionName: SÍ tiene este permiso\n";
            } else {
                echo "❌ $permissionName: NO tiene este permiso\n";
            }
        } else {
            echo "❓ $permissionName: Este permiso no existe en la base de datos\n";
        }
    }
    
    echo "\n";
    
    // También verifiquemos si hay mapeos de permisos en el sistema
    echo "Verificando mapeo de permisos en authService.js:\n";
    echo "================================================\n";
    
    $authServicePath = 'C:\\xampp\\htdocs\\profixcrm\\frontend\\src\\services\\authService.js';
    if (file_exists($authServicePath)) {
        $content = file_get_contents($authServicePath);
        
        // Buscar el permissionMap
        if (preg_match('/const permissionMap = \{([^}]+)\}/s', $content, $matches)) {
            echo "Mapeo de permisos encontrado en authService.js:\n";
            echo $matches[0] . "\n";
        } else {
            echo "No se encontró el permissionMap en authService.js\n";
        }
    } else {
        echo "No se encontró el archivo authService.js\n";
    }
    
} catch(PDOException $e) {
    echo "Error de conexión: " . $e->getMessage() . "\n";
}
?>