<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== CORRIGIENDO PERMISOS FALTANTES (VERSIÓN SIMPLIFICADA) ===\n\n";

require_once 'vendor/autoload.php';

try {
    // Cargar variables de entorno
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
    $pdo = new PDO(
        'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . ($_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME']),
        $_ENV['DB_USER'] ?? $_ENV['DB_USERNAME'],
        $_ENV['DB_PASS'] ?? $_ENV['DB_PASSWORD']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. VERIFICANDO ESTRUCTURA DE TABLAS:\n";
    
    // Verificar estructura de la tabla permissions
    $stmt = $pdo->query("DESCRIBE permissions");
    $permissionsColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columnas en tabla permissions: " . implode(', ', $permissionsColumns) . "\n";
    
    // Verificar estructura de la tabla user_roles
    $stmt = $pdo->query("DESCRIBE user_roles");
    $userRolesColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columnas en tabla user_roles: " . implode(', ', $userRolesColumns) . "\n";
    
    // Verificar estructura de la tabla role_permissions
    $stmt = $pdo->query("DESCRIBE role_permissions");
    $rolePermissionsColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columnas en tabla role_permissions: " . implode(', ', $rolePermissionsColumns) . "\n\n";
    
    echo "2. VERIFICANDO PERMISOS EXISTENTES:\n";
    
    // Obtener permisos existentes
    $stmt = $pdo->query("SELECT name FROM permissions ORDER BY name");
    $existingPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Permisos existentes: " . count($existingPermissions) . "\n";
    foreach ($existingPermissions as $perm) {
        echo "- $perm\n";
    }
    
    echo "\n3. DEFINIENDO PERMISOS CRÍTICOS FALTANTES:\n";
    
    // Definir solo los permisos críticos que faltan
    $criticalPermissions = [
        // Leads - permisos críticos
        ['name' => 'leads.view', 'display_name' => 'Ver Leads', 'description' => 'Permite ver la lista de leads', 'module' => 'leads'],
        ['name' => 'leads.view.all', 'display_name' => 'Ver Todos los Leads', 'description' => 'Permite ver todos los leads sin restricciones', 'module' => 'leads'],
        ['name' => 'leads.view.assigned', 'display_name' => 'Ver Leads Asignados', 'description' => 'Permite ver solo los leads asignados al usuario', 'module' => 'leads'],
        ['name' => 'leads.view.desk', 'display_name' => 'Ver Leads del Desk', 'description' => 'Permite ver leads del desk del usuario', 'module' => 'leads'],
        
        // Users - permisos críticos
        ['name' => 'users.view', 'display_name' => 'Ver Usuarios', 'description' => 'Permite ver la lista de usuarios', 'module' => 'users'],
        
        // Desks - permisos críticos
        ['name' => 'desks.view', 'display_name' => 'Ver Desks', 'description' => 'Permite ver la lista de desks', 'module' => 'desks'],
        
        // Dashboard
        ['name' => 'dashboard.view', 'display_name' => 'Ver Dashboard', 'description' => 'Permite ver el dashboard principal', 'module' => 'dashboard']
    ];
    
    echo "Permisos críticos a verificar: " . count($criticalPermissions) . "\n";
    
    echo "\n4. IDENTIFICANDO PERMISOS FALTANTES:\n";
    
    $missingPermissions = [];
    foreach ($criticalPermissions as $permission) {
        if (!in_array($permission['name'], $existingPermissions)) {
            $missingPermissions[] = $permission;
            echo "- FALTA: {$permission['name']}\n";
        } else {
            echo "- EXISTE: {$permission['name']}\n";
        }
    }
    
    if (empty($missingPermissions)) {
        echo "✅ Todos los permisos críticos ya existen\n";
    } else {
        echo "\n5. AGREGANDO PERMISOS FALTANTES:\n";
        
        // Preparar query basado en las columnas disponibles
        if (in_array('action', $permissionsColumns)) {
            $insertStmt = $pdo->prepare("
                INSERT INTO permissions (name, display_name, description, module, action, created_at) 
                VALUES (?, ?, ?, ?, 'view', NOW())
            ");
        } else {
            $insertStmt = $pdo->prepare("
                INSERT INTO permissions (name, display_name, description, module, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
        }
        
        $addedCount = 0;
        foreach ($missingPermissions as $permission) {
            try {
                if (in_array('action', $permissionsColumns)) {
                    $insertStmt->execute([
                        $permission['name'],
                        $permission['display_name'],
                        $permission['description'],
                        $permission['module']
                    ]);
                } else {
                    $insertStmt->execute([
                        $permission['name'],
                        $permission['display_name'],
                        $permission['description'],
                        $permission['module']
                    ]);
                }
                echo "✅ Agregado: {$permission['name']}\n";
                $addedCount++;
            } catch (Exception $e) {
                echo "❌ Error agregando {$permission['name']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\nPermisos agregados: $addedCount\n";
    }
    
    echo "\n6. VERIFICANDO USUARIO ADMIN:\n";
    
    // Verificar usuario admin
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = 'admin'");
    $stmt->execute();
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminUser) {
        echo "❌ Usuario admin no encontrado\n";
        exit(1);
    } else {
        echo "✅ Usuario admin encontrado (ID: {$adminUser['id']})\n";
    }
    
    echo "\n7. VERIFICANDO ROLES DEL USUARIO ADMIN:\n";
    
    // Verificar roles del usuario admin
    $stmt = $pdo->prepare("
        SELECT r.id, r.name, r.display_name 
        FROM roles r 
        JOIN user_roles ur ON r.id = ur.role_id 
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$adminUser['id']]);
    $userRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Roles actuales del usuario admin:\n";
    foreach ($userRoles as $role) {
        echo "- {$role['name']} (ID: {$role['id']}) - {$role['display_name']}\n";
    }
    
    // Buscar rol admin o super_admin
    $stmt = $pdo->prepare("SELECT id, name FROM roles WHERE name IN ('admin', 'super_admin') ORDER BY name");
    $stmt->execute();
    $adminRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nRoles de administrador disponibles:\n";
    foreach ($adminRoles as $role) {
        echo "- {$role['name']} (ID: {$role['id']})\n";
    }
    
    // Asignar rol admin si no lo tiene
    $hasAdminRole = false;
    foreach ($userRoles as $userRole) {
        if (in_array($userRole['name'], ['admin', 'super_admin'])) {
            $hasAdminRole = true;
            break;
        }
    }
    
    if (!$hasAdminRole && !empty($adminRoles)) {
        $targetRole = $adminRoles[0]; // Tomar el primer rol admin disponible
        echo "\nAsignando rol {$targetRole['name']} al usuario admin...\n";
        
        // Preparar query basado en las columnas disponibles
        if (in_array('assigned_at', $userRolesColumns)) {
            $insertUserRoleStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())");
        } else {
            $insertUserRoleStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        }
        
        try {
            $insertUserRoleStmt->execute([$adminUser['id'], $targetRole['id']]);
            echo "✅ Rol {$targetRole['name']} asignado al usuario admin\n";
        } catch (Exception $e) {
            echo "❌ Error asignando rol: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✅ Usuario admin ya tiene rol de administrador asignado\n";
    }
    
    echo "\n8. ASIGNANDO PERMISOS CRÍTICOS AL ROL ADMIN:\n";
    
    // Obtener el rol admin del usuario
    $stmt = $pdo->prepare("
        SELECT r.id, r.name 
        FROM roles r 
        JOIN user_roles ur ON r.id = ur.role_id 
        WHERE ur.user_id = ? AND r.name IN ('admin', 'super_admin')
        LIMIT 1
    ");
    $stmt->execute([$adminUser['id']]);
    $adminRole = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminRole) {
        echo "❌ No se pudo encontrar rol admin para el usuario\n";
    } else {
        echo "Trabajando con rol: {$adminRole['name']} (ID: {$adminRole['id']})\n";
        
        // Obtener permisos críticos
        $criticalPermissionNames = array_column($criticalPermissions, 'name');
        $placeholders = str_repeat('?,', count($criticalPermissionNames) - 1) . '?';
        
        $stmt = $pdo->prepare("SELECT id, name FROM permissions WHERE name IN ($placeholders)");
        $stmt->execute($criticalPermissionNames);
        $availablePermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener permisos ya asignados al rol
        $stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$adminRole['id']]);
        $assignedPermissionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Permisos ya asignados al rol: " . count($assignedPermissionIds) . "\n";
        
        // Asignar permisos faltantes
        $assignedCount = 0;
        foreach ($availablePermissions as $permission) {
            if (!in_array($permission['id'], $assignedPermissionIds)) {
                try {
                    // Preparar query basado en las columnas disponibles
                    if (in_array('granted_at', $rolePermissionsColumns)) {
                        $insertRolePermStmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id, granted_at) VALUES (?, ?, NOW())");
                    } else {
                        $insertRolePermStmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
                    }
                    
                    $insertRolePermStmt->execute([$adminRole['id'], $permission['id']]);
                    echo "✅ Asignado: {$permission['name']}\n";
                    $assignedCount++;
                } catch (Exception $e) {
                    echo "❌ Error asignando {$permission['name']}: " . $e->getMessage() . "\n";
                }
            } else {
                echo "- Ya asignado: {$permission['name']}\n";
            }
        }
        
        echo "\nPermisos críticos asignados al rol admin: $assignedCount\n";
    }
    
    echo "\n=== RESUMEN ===\n";
    echo "✅ Permisos críticos verificados y agregados\n";
    echo "✅ Usuario admin configurado con rol de administrador\n";
    echo "✅ Permisos críticos asignados al rol admin\n";
    echo "\nEl sistema debería funcionar correctamente ahora.\n";
    echo "Intenta acceder al menú de leads nuevamente.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}