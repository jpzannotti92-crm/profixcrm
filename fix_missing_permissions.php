<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== CORRIGIENDO PERMISOS FALTANTES ===\n\n";

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
    
    echo "1. VERIFICANDO PERMISOS EXISTENTES:\n";
    
    // Obtener permisos existentes
    $stmt = $pdo->query("SELECT name FROM permissions ORDER BY name");
    $existingPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Permisos existentes: " . count($existingPermissions) . "\n";
    foreach ($existingPermissions as $perm) {
        echo "- $perm\n";
    }
    
    echo "\n2. DEFINIENDO PERMISOS REQUERIDOS:\n";
    
    // Definir todos los permisos que deberían existir
    $requiredPermissions = [
        // Dashboard
        ['name' => 'dashboard.view', 'display_name' => 'Ver Dashboard', 'description' => 'Permite ver el dashboard principal', 'module' => 'dashboard', 'action' => 'view'],
        
        // Leads - Permisos básicos
        ['name' => 'leads.view', 'display_name' => 'Ver Leads', 'description' => 'Permite ver la lista de leads', 'module' => 'leads', 'action' => 'view'],
        ['name' => 'leads.view.all', 'display_name' => 'Ver Todos los Leads', 'description' => 'Permite ver todos los leads sin restricciones', 'module' => 'leads', 'action' => 'view'],
        ['name' => 'leads.view.assigned', 'display_name' => 'Ver Leads Asignados', 'description' => 'Permite ver solo los leads asignados al usuario', 'module' => 'leads', 'action' => 'view'],
        ['name' => 'leads.view.desk', 'display_name' => 'Ver Leads del Desk', 'description' => 'Permite ver leads del desk del usuario', 'module' => 'leads', 'action' => 'view'],
        ['name' => 'leads.create', 'display_name' => 'Crear Leads', 'description' => 'Permite crear nuevos leads', 'module' => 'leads', 'action' => 'create'],
        ['name' => 'leads.edit', 'display_name' => 'Editar Leads', 'description' => 'Permite editar leads existentes', 'module' => 'leads', 'action' => 'edit'],
        ['name' => 'leads.delete', 'display_name' => 'Eliminar Leads', 'description' => 'Permite eliminar leads', 'module' => 'leads', 'action' => 'delete'],
        ['name' => 'leads.assign', 'display_name' => 'Asignar Leads', 'description' => 'Permite asignar leads a otros usuarios', 'module' => 'leads', 'action' => 'assign'],
        ['name' => 'leads.import', 'display_name' => 'Importar Leads', 'description' => 'Permite importar leads desde archivos', 'module' => 'leads', 'action' => 'import'],
        ['name' => 'leads.export', 'display_name' => 'Exportar Leads', 'description' => 'Permite exportar leads a archivos', 'module' => 'leads', 'action' => 'export'],
        
        // Users
        ['name' => 'users.view', 'display_name' => 'Ver Usuarios', 'description' => 'Permite ver la lista de usuarios', 'module' => 'users', 'action' => 'view'],
        ['name' => 'users.create', 'display_name' => 'Crear Usuarios', 'description' => 'Permite crear nuevos usuarios', 'module' => 'users', 'action' => 'create'],
        ['name' => 'users.edit', 'display_name' => 'Editar Usuarios', 'description' => 'Permite editar usuarios existentes', 'module' => 'users', 'action' => 'edit'],
        ['name' => 'users.delete', 'display_name' => 'Eliminar Usuarios', 'description' => 'Permite eliminar usuarios', 'module' => 'users', 'action' => 'delete'],
        
        // Roles
        ['name' => 'roles.view', 'display_name' => 'Ver Roles', 'description' => 'Permite ver la lista de roles', 'module' => 'roles', 'action' => 'view'],
        ['name' => 'roles.create', 'display_name' => 'Crear Roles', 'description' => 'Permite crear nuevos roles', 'module' => 'roles', 'action' => 'create'],
        ['name' => 'roles.edit', 'display_name' => 'Editar Roles', 'description' => 'Permite editar roles existentes', 'module' => 'roles', 'action' => 'edit'],
        ['name' => 'roles.delete', 'display_name' => 'Eliminar Roles', 'description' => 'Permite eliminar roles', 'module' => 'roles', 'action' => 'delete'],
        
        // Desks
        ['name' => 'desks.view', 'display_name' => 'Ver Desks', 'description' => 'Permite ver la lista de desks', 'module' => 'desks', 'action' => 'view'],
        ['name' => 'desks.create', 'display_name' => 'Crear Desks', 'description' => 'Permite crear nuevos desks', 'module' => 'desks', 'action' => 'create'],
        ['name' => 'desks.edit', 'display_name' => 'Editar Desks', 'description' => 'Permite editar desks existentes', 'module' => 'desks', 'action' => 'edit'],
        ['name' => 'desks.delete', 'display_name' => 'Eliminar Desks', 'description' => 'Permite eliminar desks', 'module' => 'desks', 'action' => 'delete'],
        
        // System
        ['name' => 'system.settings', 'display_name' => 'Configuración del Sistema', 'description' => 'Permite acceder a configuración del sistema', 'module' => 'system', 'action' => 'settings'],
        ['name' => 'system.audit', 'display_name' => 'Auditoría del Sistema', 'description' => 'Permite ver logs de auditoría', 'module' => 'system', 'action' => 'audit'],
        
        // Reports
        ['name' => 'reports.view', 'display_name' => 'Ver Reportes', 'description' => 'Permite ver reportes', 'module' => 'reports', 'action' => 'view'],
        ['name' => 'reports.create', 'display_name' => 'Crear Reportes', 'description' => 'Permite crear reportes', 'module' => 'reports', 'action' => 'create'],
        ['name' => 'reports.export', 'display_name' => 'Exportar Reportes', 'description' => 'Permite exportar reportes', 'module' => 'reports', 'action' => 'export'],
        
        // Trading
        ['name' => 'trading.view', 'display_name' => 'Ver Trading', 'description' => 'Permite ver información de trading', 'module' => 'trading', 'action' => 'view'],
        ['name' => 'trading.create', 'display_name' => 'Crear Trading', 'description' => 'Permite crear registros de trading', 'module' => 'trading', 'action' => 'create'],
        ['name' => 'trading.edit', 'display_name' => 'Editar Trading', 'description' => 'Permite editar registros de trading', 'module' => 'trading', 'action' => 'edit'],
        ['name' => 'trading.delete', 'display_name' => 'Eliminar Trading', 'description' => 'Permite eliminar registros de trading', 'module' => 'trading', 'action' => 'delete'],
        
        // Transactions
        ['name' => 'transactions.view', 'display_name' => 'Ver Transacciones', 'description' => 'Permite ver transacciones', 'module' => 'transactions', 'action' => 'view'],
        ['name' => 'transactions.approve', 'display_name' => 'Aprobar Transacciones', 'description' => 'Permite aprobar transacciones', 'module' => 'transactions', 'action' => 'approve'],
        ['name' => 'transactions.process', 'display_name' => 'Procesar Transacciones', 'description' => 'Permite procesar transacciones', 'module' => 'transactions', 'action' => 'process']
    ];
    
    echo "Permisos requeridos: " . count($requiredPermissions) . "\n";
    
    echo "\n3. IDENTIFICANDO PERMISOS FALTANTES:\n";
    
    $missingPermissions = [];
    foreach ($requiredPermissions as $permission) {
        if (!in_array($permission['name'], $existingPermissions)) {
            $missingPermissions[] = $permission;
            echo "- FALTA: {$permission['name']}\n";
        }
    }
    
    if (empty($missingPermissions)) {
        echo "✅ Todos los permisos requeridos ya existen\n";
    } else {
        echo "\n4. AGREGANDO PERMISOS FALTANTES:\n";
        
        $insertStmt = $pdo->prepare("
            INSERT INTO permissions (name, display_name, description, module, action, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $addedCount = 0;
        foreach ($missingPermissions as $permission) {
            try {
                $insertStmt->execute([
                    $permission['name'],
                    $permission['display_name'],
                    $permission['description'],
                    $permission['module'],
                    $permission['action']
                ]);
                echo "✅ Agregado: {$permission['name']}\n";
                $addedCount++;
            } catch (Exception $e) {
                echo "❌ Error agregando {$permission['name']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\nPermisos agregados: $addedCount\n";
    }
    
    echo "\n5. VERIFICANDO ROLES EXISTENTES:\n";
    
    // Verificar roles existentes
    $stmt = $pdo->query("SELECT id, name FROM roles ORDER BY name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Roles existentes:\n";
    foreach ($roles as $role) {
        echo "- {$role['name']} (ID: {$role['id']})\n";
    }
    
    echo "\n6. ASIGNANDO PERMISOS AL ROL ADMIN:\n";
    
    // Buscar rol admin
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name IN ('admin', 'super_admin') LIMIT 1");
    $stmt->execute();
    $adminRole = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminRole) {
        echo "❌ No se encontró rol admin o super_admin\n";
        
        // Crear rol admin si no existe
        echo "Creando rol admin...\n";
        $stmt = $pdo->prepare("INSERT INTO roles (name, display_name, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute(['admin', 'Administrador', 'Administrador del sistema con todos los permisos']);
        $adminRoleId = $pdo->lastInsertId();
        echo "✅ Rol admin creado con ID: $adminRoleId\n";
    } else {
        $adminRoleId = $adminRole['id'];
        echo "✅ Rol admin encontrado con ID: $adminRoleId\n";
    }
    
    // Obtener todos los permisos
    $stmt = $pdo->query("SELECT id, name FROM permissions");
    $allPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener permisos ya asignados al rol admin
    $stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$adminRoleId]);
    $assignedPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Permisos ya asignados al rol admin: " . count($assignedPermissions) . "\n";
    
    // Asignar permisos faltantes al rol admin
    $insertRolePermStmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
    $assignedCount = 0;
    
    foreach ($allPermissions as $permission) {
        if (!in_array($permission['id'], $assignedPermissions)) {
            try {
                $insertRolePermStmt->execute([$adminRoleId, $permission['id']]);
                echo "✅ Asignado al admin: {$permission['name']}\n";
                $assignedCount++;
            } catch (Exception $e) {
                echo "❌ Error asignando {$permission['name']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\nPermisos asignados al rol admin: $assignedCount\n";
    
    echo "\n7. VERIFICANDO USUARIO ADMIN:\n";
    
    // Verificar usuario admin
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = 'admin'");
    $stmt->execute();
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminUser) {
        echo "❌ Usuario admin no encontrado\n";
    } else {
        echo "✅ Usuario admin encontrado (ID: {$adminUser['id']})\n";
        
        // Verificar si el usuario admin tiene el rol admin asignado
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_id = ?");
        $stmt->execute([$adminUser['id'], $adminRoleId]);
        $hasAdminRole = $stmt->fetchColumn() > 0;
        
        if (!$hasAdminRole) {
            echo "Asignando rol admin al usuario admin...\n";
            $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$adminUser['id'], $adminRoleId]);
            echo "✅ Rol admin asignado al usuario admin\n";
        } else {
            echo "✅ Usuario admin ya tiene el rol admin asignado\n";
        }
    }
    
    echo "\n=== RESUMEN ===\n";
    echo "✅ Permisos faltantes agregados: " . count($missingPermissions) . "\n";
    echo "✅ Permisos asignados al rol admin: $assignedCount\n";
    echo "✅ Usuario admin configurado correctamente\n";
    echo "\nEl sistema debería funcionar correctamente ahora.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}