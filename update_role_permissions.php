<?php
require_once __DIR__ . '/src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

try {
    $db = Connection::getInstance()->getConnection();
    
    echo "=== Actualizando permisos de roles ===\n";
    
    // Definir permisos por rol
    $rolePermissions = [
        'super_admin' => [
            // Todos los permisos del sistema
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.edit_all',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'desks.view', 'desks.create', 'desks.edit', 'desks.delete',
            'leads.view', 'leads.create', 'leads.edit', 'leads.delete', 'leads.assign', 'leads.import', 'leads.export',
            'trading.view', 'trading.create', 'trading.edit', 'trading.delete',
            'trading_accounts.view', 'trading_accounts.create', 'trading_accounts.edit', 'trading_accounts.delete',
            'deposits_withdrawals.view', 'deposits_withdrawals.create', 'deposits_withdrawals.edit', 'deposits_withdrawals.delete',
            'transactions.view', 'transactions.approve', 'transactions.process',
            'reports.view', 'reports.create',
            'activities.view', 'activities.create', 'activities.edit', 'activities.delete',
            'dashboard.view', 'dashboard.stats',
            'user_permissions.view', 'user_permissions.edit',
            'instruments.view', 'instruments.create', 'instruments.edit', 'instruments.delete',
            'webtrader.access',
            'system.settings', 'system.maintenance', 'system.database', 'system.audit',
            'manage_states'
        ],
        'admin' => [
            // Permisos administrativos (sin sistema)
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.edit_all',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'desks.view', 'desks.create', 'desks.edit', 'desks.delete',
            'leads.view', 'leads.create', 'leads.edit', 'leads.delete', 'leads.assign', 'leads.import', 'leads.export',
            'trading.view', 'trading.create', 'trading.edit', 'trading.delete',
            'trading_accounts.view', 'trading_accounts.create', 'trading_accounts.edit', 'trading_accounts.delete',
            'deposits_withdrawals.view', 'deposits_withdrawals.create', 'deposits_withdrawals.edit', 'deposits_withdrawals.delete',
            'transactions.view', 'transactions.approve', 'transactions.process',
            'reports.view', 'reports.create',
            'activities.view', 'activities.create', 'activities.edit', 'activities.delete',
            'dashboard.view', 'dashboard.stats',
            'user_permissions.view', 'user_permissions.edit',
            'instruments.view', 'instruments.create', 'instruments.edit', 'instruments.delete',
            'webtrader.access',
            'manage_states'
        ],
        'manager' => [
            // Permisos de gestión
            'users.view', 'users.create', 'users.edit',
            'desks.view', 'desks.create', 'desks.edit', 'desks.delete',
            'leads.view', 'leads.create', 'leads.edit', 'leads.assign', 'leads.import', 'leads.export',
            'trading.view', 'trading.create', 'trading.edit', 'trading.delete',
            'trading_accounts.view', 'trading_accounts.create', 'trading_accounts.edit', 'trading_accounts.delete',
            'deposits_withdrawals.view', 'deposits_withdrawals.create', 'deposits_withdrawals.edit', 'deposits_withdrawals.delete',
            'transactions.view', 'transactions.approve', 'transactions.process',
            'reports.view', 'reports.create',
            'activities.view', 'activities.create', 'activities.edit',
            'dashboard.view', 'dashboard.stats',
            'user_permissions.view',
            'instruments.view', 'instruments.create', 'instruments.edit',
            'webtrader.access'
        ],
        'agent' => [
            // Permisos básicos de agente
            'leads.view', 'leads.create', 'leads.edit',
            'trading.view', 'trading.create', 'trading.edit',
            'trading_accounts.view', 'trading_accounts.create', 'trading_accounts.edit',
            'deposits_withdrawals.view', 'deposits_withdrawals.create', 'deposits_withdrawals.edit',
            'transactions.view',
            'reports.view',
            'activities.view', 'activities.create', 'activities.edit',
            'dashboard.view',
            'instruments.view',
            'webtrader.access'
        ],
        'viewer' => [
            // Solo lectura
            'users.view',
            'leads.view',
            'desks.view',
            'roles.view',
            'trading.view',
            'trading_accounts.view',
            'deposits_withdrawals.view',
            'transactions.view',
            'reports.view',
            'activities.view',
            'dashboard.view',
            'instruments.view'
        ]
    ];
    
    $db->beginTransaction();
    
    // Detectar columnas disponibles en role_permissions para evitar errores
    $columnsStmt = $db->query("DESCRIBE role_permissions");
    $rolePermColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);

    // Preparar statements según columnas disponibles
    $insertWithGranted = null;
    $insertWithCreated = null;
    $insertSimple = null;
    if (in_array('granted_by', $rolePermColumns) && in_array('granted_at', $rolePermColumns)) {
        $insertWithGranted = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, granted_by, granted_at) VALUES (?, ?, 1, NOW())");
    } elseif (in_array('created_at', $rolePermColumns)) {
        $insertWithCreated = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
    } else {
        $insertSimple = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    }

    foreach ($rolePermissions as $roleName => $permissions) {
        echo "\n--- Actualizando rol: $roleName ---\n";
        
        // Obtener ID del rol
        $stmt = $db->prepare("SELECT id FROM roles WHERE name = ?");
        $stmt->execute([$roleName]);
        $role = $stmt->fetch();
        
        if (!$role) {
            echo "❌ Rol '$roleName' no encontrado\n";
            continue;
        }
        
        $roleId = $role['id'];
        echo "✓ Rol encontrado con ID: $roleId\n";
        
        // Limpiar permisos existentes
        $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);
        echo "✓ Permisos existentes eliminados\n";
        
        // Agregar nuevos permisos
        $addedCount = 0;
        foreach ($permissions as $permissionName) {
            // Obtener ID del permiso
            $stmt = $db->prepare("SELECT id FROM permissions WHERE name = ?");
            $stmt->execute([$permissionName]);
            $permission = $stmt->fetch();
            
            if ($permission) {
                // Insertar según columnas disponibles
                if ($insertWithGranted) {
                    $insertWithGranted->execute([$roleId, $permission['id']]);
                } elseif ($insertWithCreated) {
                    $insertWithCreated->execute([$roleId, $permission['id']]);
                } else {
                    $insertSimple->execute([$roleId, $permission['id']]);
                }
                $addedCount++;
            } else {
                echo "⚠️  Permiso '$permissionName' no encontrado\n";
            }
        }
        
        echo "✓ $addedCount permisos agregados\n";
    }
    
    $db->commit();
    echo "\n✅ Actualización de permisos completada exitosamente\n";
    
} catch (Exception $e) {
    $db->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
}