<?php
require_once 'vendor/autoload.php';
require_once 'src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

try {
    $db = Connection::getInstance()->getConnection();
    
    echo "=== AGREGAR PERMISO users.view AL ROL Sales Agent ===\n";
    
    // 1. Obtener el ID del rol Sales Agent
    $stmt = $db->prepare("SELECT id FROM roles WHERE name = 'Sales Agent'");
    $stmt->execute();
    $salesAgentRole = $stmt->fetch();
    
    if (!$salesAgentRole) {
        echo "❌ Rol 'Sales Agent' no encontrado\n";
        exit(1);
    }
    
    $roleId = $salesAgentRole['id'];
    echo "✅ Rol 'Sales Agent' encontrado con ID: $roleId\n";
    
    // 2. Obtener el ID del permiso users.view
    $stmt = $db->prepare("SELECT id FROM permissions WHERE name = 'users.view'");
    $stmt->execute();
    $usersViewPermission = $stmt->fetch();
    
    if (!$usersViewPermission) {
        echo "❌ Permiso 'users.view' no encontrado\n";
        exit(1);
    }
    
    $permissionId = $usersViewPermission['id'];
    echo "✅ Permiso 'users.view' encontrado con ID: $permissionId\n";
    
    // 3. Verificar si ya existe la relación
    $stmt = $db->prepare("SELECT * FROM role_permissions WHERE role_id = ? AND permission_id = ?");
    $stmt->execute([$roleId, $permissionId]);
    $existingRelation = $stmt->fetch();
    
    if ($existingRelation) {
        echo "ℹ️ La relación ya existe, no es necesario agregarla\n";
    } else {
        // 4. Agregar la relación
        $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        $stmt->execute([$roleId, $permissionId]);
        echo "✅ Permiso 'users.view' agregado al rol 'Sales Agent'\n";
    }
    
    // 5. Verificar que se agregó correctamente
    $stmt = $db->prepare("
        SELECT p.name, p.display_name 
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE rp.role_id = ? AND p.name = 'users.view'
    ");
    $stmt->execute([$roleId]);
    $verification = $stmt->fetch();
    
    if ($verification) {
        echo "✅ Verificación exitosa: El rol 'Sales Agent' ahora tiene el permiso 'users.view'\n";
    } else {
        echo "❌ Error: No se pudo verificar que el permiso se agregó correctamente\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>