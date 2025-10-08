<?php
require_once 'vendor/autoload.php';
require_once 'src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

try {
    $db = Connection::getInstance();
    
    echo "=== REMOVER PERMISO leads.view DEL ROL Sales Agent ===\n\n";
    
    // 1. Verificar el estado actual
    echo "=== ESTADO ACTUAL ===\n";
    $stmt = $db->getConnection()->prepare("
        SELECT p.name, p.display_name
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN roles r ON rp.role_id = r.id
        WHERE r.name = 'Sales Agent' AND p.name LIKE 'leads%'
        ORDER BY p.name
    ");
    $stmt->execute();
    $currentPermissions = $stmt->fetchAll();
    
    echo "Permisos actuales del rol 'Sales Agent':\n";
    foreach ($currentPermissions as $perm) {
        echo "- {$perm['name']} ({$perm['display_name']})\n";
    }
    echo "\n";
    
    // 2. Obtener IDs necesarios
    $stmt = $db->getConnection()->prepare("SELECT id FROM roles WHERE name = 'Sales Agent'");
    $stmt->execute();
    $salesAgentRole = $stmt->fetch();
    
    if (!$salesAgentRole) {
        echo "❌ Rol 'Sales Agent' no encontrado\n";
        exit(1);
    }
    
    $roleId = $salesAgentRole['id'];
    echo "✅ Rol 'Sales Agent' encontrado con ID: $roleId\n";
    
    $stmt = $db->getConnection()->prepare("SELECT id FROM permissions WHERE name = 'leads.view'");
    $stmt->execute();
    $leadsViewPermission = $stmt->fetch();
    
    if (!$leadsViewPermission) {
        echo "❌ Permiso 'leads.view' no encontrado\n";
        exit(1);
    }
    
    $permissionId = $leadsViewPermission['id'];
    echo "✅ Permiso 'leads.view' encontrado con ID: $permissionId\n\n";
    
    // 3. Verificar si existe la relación
    $stmt = $db->getConnection()->prepare("
        SELECT * FROM role_permissions 
        WHERE role_id = ? AND permission_id = ?
    ");
    $stmt->execute([$roleId, $permissionId]);
    $existingRelation = $stmt->fetch();
    
    if (!$existingRelation) {
        echo "ℹ️ La relación no existe, no es necesario removerla\n";
    } else {
        // 4. Remover la relación
        echo "🔄 Removiendo el permiso 'leads.view' del rol 'Sales Agent'...\n";
        $stmt = $db->getConnection()->prepare("
            DELETE FROM role_permissions 
            WHERE role_id = ? AND permission_id = ?
        ");
        $stmt->execute([$roleId, $permissionId]);
        echo "✅ Permiso 'leads.view' removido del rol 'Sales Agent'\n\n";
    }
    
    // 5. Verificar que se removió correctamente y mostrar estado final
    echo "=== ESTADO FINAL ===\n";
    $stmt = $db->getConnection()->prepare("
        SELECT p.name, p.display_name
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN roles r ON rp.role_id = r.id
        WHERE r.name = 'Sales Agent' AND p.name LIKE 'leads%'
        ORDER BY p.name
    ");
    $stmt->execute();
    $finalPermissions = $stmt->fetchAll();
    
    echo "Permisos finales del rol 'Sales Agent':\n";
    if (empty($finalPermissions)) {
        echo "- Sin permisos de leads\n";
    } else {
        foreach ($finalPermissions as $perm) {
            echo "- {$perm['name']} ({$perm['display_name']})\n";
        }
    }
    echo "\n";
    
    // 6. Verificar el impacto en el usuario jpzannotti93
    echo "=== VERIFICACIÓN DEL USUARIO jpzannotti93 ===\n";
    $stmt = $db->getConnection()->prepare("
        SELECT DISTINCT p.name, p.display_name
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        INNER JOIN users u ON ur.user_id = u.id
        WHERE u.username = 'jpzannotti93' AND p.name LIKE 'leads%'
        ORDER BY p.name
    ");
    $stmt->execute();
    $userPermissions = $stmt->fetchAll();
    
    echo "Permisos de leads del usuario jpzannotti93:\n";
    foreach ($userPermissions as $perm) {
        echo "- {$perm['name']} ({$perm['display_name']})\n";
    }
    
    // 7. Análisis final
    echo "\n=== ANÁLISIS FINAL ===\n";
    $hasViewAssigned = false;
    $hasViewGeneric = false;
    
    foreach ($userPermissions as $perm) {
        if ($perm['name'] === 'leads.view.assigned') {
            $hasViewAssigned = true;
        }
        if ($perm['name'] === 'leads.view') {
            $hasViewGeneric = true;
        }
    }
    
    if ($hasViewAssigned && !$hasViewGeneric) {
        echo "✅ PERFECTO: El usuario ahora solo tiene 'leads.view.assigned'\n";
        echo "   Esto significa que solo verá los leads que le están asignados.\n";
    } elseif ($hasViewGeneric) {
        echo "⚠️ ADVERTENCIA: El usuario aún tiene 'leads.view'\n";
        echo "   Esto podría seguir causando que vea todos los leads.\n";
    } elseif (!$hasViewAssigned) {
        echo "❌ PROBLEMA: El usuario no tiene 'leads.view.assigned'\n";
        echo "   No podrá ver ningún lead.\n";
    }
    
    echo "\n✅ Proceso completado\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>