<?php
// Script de depuración para verificar permisos de usuario
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Database/Connection.php';
require_once __DIR__ . '/src/Models/User.php';

use IaTradeCRM\Database\Connection;

header('Content-Type: text/plain');

try {
    $db = Connection::getInstance()->getConnection();
    
    echo "=== DEBUG DE PERMISOS DE USUARIO ===\n\n";
    
    // Obtener el primer usuario administrador
    $stmt = $db->prepare("SELECT id, username, email, first_name, last_name, status FROM users WHERE status = 'active' ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "Usuario encontrado:\n";
        echo "- ID: {$user['id']}\n";
        echo "- Username: {$user['username']}\n";
        echo "- Email: {$user['email']}\n";
        echo "- Nombre: {$user['first_name']} {$user['last_name']}\n\n";
        
        // Crear instancia del usuario
        $userModel = new \IaTradeCRM\Models\User($db);
        $userModel->find($user['id']);
        
        // Obtener roles
        $roles = $userModel->getRoles();
        echo "ROLES DEL USUARIO:\n";
        if (empty($roles)) {
            echo "- No tiene roles asignados\n";
        } else {
            foreach ($roles as $role) {
                echo "- {$role}\n";
            }
        }
        echo "\n";
        
        // Obtener permisos
        $permissions = $userModel->getPermissions();
        echo "PERMISOS DEL USUARIO:\n";
        if (empty($permissions)) {
            echo "- No tiene permisos asignados\n";
        } else {
            foreach ($permissions as $permission) {
                echo "- {$permission}\n";
            }
        }
        echo "\n";
        
        // Verificar permisos específicos
        echo "VERIFICACIÓN DE PERMISOS ESPECÍFICOS:\n";
        $requiredPermissions = [
            'view_leads',
            'view_users', 
            'view_roles',
            'view_desks',
            'manage_states',
            'view_trading_accounts'
        ];
        
        foreach ($requiredPermissions as $permission) {
            $hasPermission = $userModel->hasPermission($permission);
            echo "- {$permission}: " . ($hasPermission ? '✅ SÍ' : '❌ NO') . "\n";
        }
        echo "\n";
        
        // Verificar nivel de acceso
        echo "NIVEL DE ACCESO:\n";
        echo "- isSuperAdmin(): " . ($userModel->isSuperAdmin() ? '✅ SÍ' : '❌ NO') . "\n";
        echo "- isAdmin(): " . ($userModel->isAdmin() ? '✅ SÍ' : '❌ NO') . "\n";
        echo "- isManager(): " . ($userModel->isManager() ? '✅ SÍ' : '❌ NO') . "\n";
        
    } else {
        echo "❌ No se encontraron usuarios activos en la base de datos\n";
    }
    
    echo "\n=== TABLAS DE PERMISOS ===\n\n";
    
    // Verificar tablas de permisos
    $tables = ['roles', 'permissions', 'role_permissions', 'user_roles'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM {$table}");
            $stmt->execute();
            $result = $stmt->fetch();
            echo "- {$table}: {$result['total']} registros\n";
        } catch (Exception $e) {
            echo "- {$table}: ❌ Error - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== ROLES DISPONIBLES ===\n\n";
    $stmt = $db->prepare("SELECT id, name, description FROM roles ORDER BY name");
    $stmt->execute();
    $roles = $stmt->fetchAll();
    
    if (empty($roles)) {
        echo "❌ No hay roles definidos en la base de datos\n";
    } else {
        foreach ($roles as $role) {
            echo "- ID: {$role['id']} | Nombre: {$role['name']} | Descripción: {$role['description']}\n";
        }
    }
    
    echo "\n=== PERMISOS DISPONIBLES ===\n\n";
    $stmt = $db->prepare("SELECT id, name, description FROM permissions ORDER BY name");
    $stmt->execute();
    $permissions = $stmt->fetchAll();
    
    if (empty($permissions)) {
        echo "❌ No hay permisos definidos en la base de datos\n";
    } else {
        foreach ($permissions as $permission) {
            echo "- ID: {$permission['id']} | Nombre: {$permission['name']} | Descripción: {$permission['description']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}