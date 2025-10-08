<?php
require_once 'config/database.php';
require_once 'src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

try {
    $db = Connection::getInstance()->getConnection();
    
    echo "=== VERIFICACIÓN ESTADO USUARIO ADMIN ===\n\n";
    
    // 1. Verificar usuario admin
    $stmt = $db->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✅ Usuario admin encontrado:\n";
        echo "   - ID: {$user['id']}\n";
        echo "   - Username: {$user['username']}\n";
        echo "   - Email: {$user['email']}\n";
        echo "   - Status: {$user['status']}\n\n";
    } else {
        echo "❌ Usuario admin NO encontrado\n\n";
        exit(1);
    }
    
    // 2. Verificar roles del usuario
    $stmt = $db->prepare("
        SELECT r.name as role_name, r.id as role_id
        FROM user_roles ur 
        JOIN roles r ON ur.role_id = r.id 
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Roles del usuario admin:\n";
    if (empty($roles)) {
        echo "   ❌ No tiene roles asignados\n\n";
    } else {
        foreach ($roles as $role) {
            echo "   - {$role['role_name']} (ID: {$role['role_id']})\n";
        }
        echo "\n";
    }
    
    // 3. Verificar permisos del usuario
    $stmt = $db->prepare("
        SELECT DISTINCT p.name as permission_name, p.module
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ?
        ORDER BY p.module, p.name
    ");
    $stmt->execute([$user['id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "🔐 Permisos del usuario admin:\n";
    if (empty($permissions)) {
        echo "   ❌ No tiene permisos asignados\n\n";
    } else {
        $grouped = [];
        foreach ($permissions as $perm) {
            $module = $perm['module'] ?: 'general';
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $perm['permission_name'];
        }
        
        foreach ($grouped as $module => $perms) {
            echo "   📁 $module:\n";
            foreach ($perms as $perm) {
                echo "      - $perm\n";
            }
        }
        echo "\n";
    }
    
    // 4. Verificar permisos críticos específicos
    $critical_permissions = [
        'dashboard.view',
        'leads.view',
        'leads.view.all',
        'leads.view.assigned',
        'users.view',
        'desks.view'
    ];
    
    echo "🎯 Verificación de permisos críticos:\n";
    foreach ($critical_permissions as $perm) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as has_permission
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ? AND p.name = ?
        ");
        $stmt->execute([$user['id'], $perm]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $status = $result['has_permission'] > 0 ? '✅' : '❌';
        echo "   $status $perm\n";
    }
    
    echo "\n=== FIN VERIFICACIÓN ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>