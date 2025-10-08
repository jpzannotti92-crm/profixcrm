<?php
require_once 'vendor/autoload.php';
require_once 'src/helpers.php';

try {
    $pdo = new PDO('mysql:host=' . env('DB_HOST', 'localhost') . ';port=' . env('DB_PORT', '3306') . ';dbname=' . env('DB_NAME', 'iatrade_crm'), env('DB_USER', 'root'), env('DB_PASS', ''));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== AGREGANDO PERMISOS DE TRADING FALTANTES ===\n";
    
    // Permisos de trading que necesitamos
    $tradingPermissions = [
        ['name' => 'trading_accounts.view', 'display_name' => 'Ver Cuentas de Trading', 'description' => 'Permite ver las cuentas de trading', 'module' => 'trading', 'action' => 'view'],
        ['name' => 'trading_accounts.create', 'display_name' => 'Crear Cuentas de Trading', 'description' => 'Permite crear nuevas cuentas de trading', 'module' => 'trading', 'action' => 'create'],
        ['name' => 'trading_accounts.edit', 'display_name' => 'Editar Cuentas de Trading', 'description' => 'Permite editar cuentas de trading existentes', 'module' => 'trading', 'action' => 'edit'],
        ['name' => 'trading_accounts.delete', 'display_name' => 'Eliminar Cuentas de Trading', 'description' => 'Permite eliminar cuentas de trading', 'module' => 'trading', 'action' => 'delete'],
        ['name' => 'deposits_withdrawals.view', 'display_name' => 'Ver Depósitos/Retiros', 'description' => 'Permite ver transacciones de depósitos y retiros', 'module' => 'trading', 'action' => 'view'],
        ['name' => 'deposits_withdrawals.create', 'display_name' => 'Crear Depósitos/Retiros', 'description' => 'Permite crear nuevas transacciones', 'module' => 'trading', 'action' => 'create'],
        ['name' => 'deposits_withdrawals.edit', 'display_name' => 'Editar Depósitos/Retiros', 'description' => 'Permite editar transacciones existentes', 'module' => 'trading', 'action' => 'edit'],
        ['name' => 'deposits_withdrawals.delete', 'display_name' => 'Eliminar Depósitos/Retiros', 'description' => 'Permite eliminar transacciones', 'module' => 'trading', 'action' => 'delete'],
        ['name' => 'webtrader.access', 'display_name' => 'Acceso a WebTrader', 'description' => 'Permite acceder a la plataforma WebTrader', 'module' => 'trading', 'action' => 'access'],
    ];
    
    $addedCount = 0;
    $existingCount = 0;
    
    foreach ($tradingPermissions as $permission) {
        // Verificar si el permiso ya existe
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
        $stmt->execute([$permission['name']]);
        
        if ($stmt->fetch()) {
            echo "✓ Permiso '{$permission['name']}' ya existe\n";
            $existingCount++;
        } else {
            // Insertar el nuevo permiso
            $stmt = $pdo->prepare("INSERT INTO permissions (name, display_name, description, module, action, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $permission['name'],
                $permission['display_name'],
                $permission['description'],
                $permission['module'],
                $permission['action']
            ]);
            echo "+ Agregado permiso: {$permission['name']}\n";
            $addedCount++;
        }
    }
    
    echo "\n=== RESUMEN ===\n";
    echo "Permisos agregados: $addedCount\n";
    echo "Permisos existentes: $existingCount\n";
    
    // Asignar permisos al rol de admin
    echo "\n=== ASIGNANDO PERMISOS AL ROL ADMIN ===\n";
    
    // Obtener el rol admin
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'admin' OR name = 'administrator' LIMIT 1");
    $stmt->execute();
    $adminRole = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminRole) {
        $adminRoleId = $adminRole['id'];
        echo "Rol admin encontrado - ID: $adminRoleId\n";
        
        $assignedCount = 0;
        foreach ($tradingPermissions as $permission) {
            // Obtener ID del permiso
            $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
            $stmt->execute([$permission['name']]);
            $perm = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($perm) {
                // Verificar si ya está asignado
                $stmt = $pdo->prepare("SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?");
                $stmt->execute([$adminRoleId, $perm['id']]);
                
                if (!$stmt->fetch()) {
                    // Asignar permiso al rol
                    $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                    $stmt->execute([$adminRoleId, $perm['id']]);
                    echo "+ Asignado permiso '{$permission['name']}' al rol admin\n";
                    $assignedCount++;
                } else {
                    echo "✓ Permiso '{$permission['name']}' ya asignado al rol admin\n";
                }
            }
        }
        
        echo "Permisos asignados al admin: $assignedCount\n";
    } else {
        echo "⚠️ No se encontró el rol admin\n";
    }
    
    echo "\n✅ Proceso completado exitosamente\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}