<?php
require_once 'vendor/autoload.php';
require_once 'src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

try {
    $db = Connection::getInstance()->getConnection();
    
    echo "=== AGREGAR PERMISO desks.view AL ROL Sales Agent ===\n";
    
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
    
    // 2. Obtener el ID del permiso desks.view
    $stmt = $db->prepare("SELECT id FROM permissions WHERE name = 'desks.view'");
    $stmt->execute();
    $desksViewPermission = $stmt->fetch();
    
    if (!$desksViewPermission) {
        echo "❌ Permiso 'desks.view' no encontrado\n";
        exit(1);
    }
    
    $permissionId = $desksViewPermission['id'];
    echo "✅ Permiso 'desks.view' encontrado con ID: $permissionId\n";
    
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
        echo "✅ Permiso 'desks.view' agregado al rol 'Sales Agent'\n";
    }
    
    echo "✅ Proceso completado\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>