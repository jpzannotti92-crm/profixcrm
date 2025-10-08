<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO('mysql:host=localhost;dbname=iatrade_crm', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== AGREGANDO PERMISOS DINÁMICOS PARA LEADS ===\n\n";
    
    // Nuevos permisos específicos para leads
    $newPermissions = [
        [
            'name' => 'leads.view.all',
            'description' => 'Ver todos los leads del sistema'
        ],
        [
            'name' => 'leads.view.assigned',
            'description' => 'Ver solo leads asignados al usuario'
        ],
        [
            'name' => 'leads.view.desk',
            'description' => 'Ver leads de su escritorio/mesa'
        ],
        [
            'name' => 'leads.assign',
            'description' => 'Asignar leads a otros usuarios'
        ],
        [
            'name' => 'leads.reassign',
            'description' => 'Reasignar leads existentes'
        ]
    ];
    
    // Insertar nuevos permisos
    foreach ($newPermissions as $permission) {
        // Verificar si el permiso ya existe
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
        $stmt->execute([$permission['name']]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO permissions (name, description) VALUES (?, ?)");
            $stmt->execute([$permission['name'], $permission['description']]);
            echo "✅ Permiso agregado: {$permission['name']}\n";
        } else {
            echo "ℹ️  Permiso ya existe: {$permission['name']}\n";
        }
    }
    
    echo "\n=== CONFIGURANDO PERMISOS POR ROL ===\n";
    
    // Configurar permisos por defecto para cada rol
    $rolePermissions = [
        'super_admin' => ['leads.view.all', 'leads.assign', 'leads.reassign'],
        'admin' => ['leads.view.all', 'leads.assign', 'leads.reassign'],
        'manager' => ['leads.view.desk', 'leads.assign', 'leads.reassign'],
        'Sales Agent' => ['leads.view.assigned'],
        'agent' => ['leads.view.assigned']
    ];
    
    foreach ($rolePermissions as $roleName => $permissions) {
        // Obtener ID del rol
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
        $stmt->execute([$roleName]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($role) {
            echo "\nConfigurando rol: {$roleName}\n";
            
            foreach ($permissions as $permissionName) {
                // Obtener ID del permiso
                $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
                $stmt->execute([$permissionName]);
                $permission = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($permission) {
                    // Verificar si ya existe la relación
                    $stmt = $pdo->prepare("SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?");
                    $stmt->execute([$role['id'], $permission['id']]);
                    
                    if (!$stmt->fetch()) {
                        $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                        $stmt->execute([$role['id'], $permission['id']]);
                        echo "  ✅ Asignado: {$permissionName}\n";
                    } else {
                        echo "  ℹ️  Ya asignado: {$permissionName}\n";
                    }
                }
            }
        } else {
            echo "⚠️  Rol no encontrado: {$roleName}\n";
        }
    }
    
    echo "\n=== RESUMEN DE PERMISOS DINÁMICOS ===\n";
    echo "✅ leads.view.all - Para ver todos los leads (Super Admin, Admin)\n";
    echo "✅ leads.view.desk - Para ver leads de su escritorio (Manager)\n";
    echo "✅ leads.view.assigned - Para ver solo leads asignados (Sales Agent)\n";
    echo "✅ leads.assign - Para asignar leads\n";
    echo "✅ leads.reassign - Para reasignar leads\n";
    echo "\nAhora el sistema será completamente dinámico y configurable por usuario.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>