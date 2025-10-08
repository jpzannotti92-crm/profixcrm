<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Middleware/RBACMiddleware.php';

use Config\Database;
use Middleware\RBACMiddleware;

try {
    $db = Database::getConnection();
    
    // Verificar permisos existentes
    $permissionsToAdd = [
        'leads.view.assigned' => 'Ver leads asignados',
        'leads.view.desk' => 'Ver leads de mesa',
        'leads.view.all' => 'Ver todos los leads',
        'leads.create' => 'Crear leads'
    ];
    
    echo "=== AGREGANDO PERMISOS FALTANTES DE LEADS ===\n\n";
    
    foreach ($permissionsToAdd as $code => $name) {
        // Verificar si el permiso ya existe
        $stmt = $db->prepare("SELECT id FROM permissions WHERE code = ?");
        $stmt->execute([$code]);
        $existingPermission = $stmt->fetch();
        
        if (!$existingPermission) {
            // Crear el permiso
            $stmt = $db->prepare("INSERT INTO permissions (code, name, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->execute([$code, $name, $name]);
            $permissionId = $db->lastInsertId();
            echo "✓ Permiso creado: $code\n";
            
            // Asignar a los roles de Sales Agent y test_role
            $rolesToAssign = ['Sales Agent', 'test_role', 'admin'];
            foreach ($rolesToAssign as $roleName) {
                $stmt = $db->prepare("SELECT id FROM roles WHERE name = ?");
                $stmt->execute([$roleName]);
                $role = $stmt->fetch();
                
                if ($role) {
                    // Verificar si ya está asignado
                    $stmt = $db->prepare("SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?");
                    $stmt->execute([$role['id'], $permissionId]);
                    $existingAssignment = $stmt->fetch();
                    
                    if (!$existingAssignment) {
                        $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
                        $stmt->execute([$role['id'], $permissionId]);
                        echo "  ✓ Asignado a rol: $roleName\n";
                    } else {
                        echo "  - Ya estaba asignado a: $roleName\n";
                    }
                }
            }
        } else {
            echo "- Permiso ya existe: $code\n";
            
            // Verificar asignaciones existentes
            $rolesToAssign = ['Sales Agent', 'test_role', 'admin'];
            foreach ($rolesToAssign as $roleName) {
                $stmt = $db->prepare("SELECT id FROM roles WHERE name = ?");
                $stmt->execute([$roleName]);
                $role = $stmt->fetch();
                
                if ($role) {
                    $stmt = $db->prepare("SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?");
                    $stmt->execute([$role['id'], $existingPermission['id']]);
                    $existingAssignment = $stmt->fetch();
                    
                    if (!$existingAssignment) {
                        $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
                        $stmt->execute([$role['id'], $existingPermission['id']]);
                        echo "  ✓ Asignado a rol: $roleName\n";
                    }
                }
            }
        }
        echo "\n";
    }
    
    echo "=== PERMISOS ACTUALIZADOS ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}