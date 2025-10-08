<?php
require_once 'vendor/autoload.php';
require_once 'src/Database/Connection.php';
use iaTradeCRM\Database\Connection;

try {
    $db = Connection::getInstance();
    $conn = $db->getConnection();
    
    // Check if manage_states permission exists
    $stmt = $conn->prepare('SELECT id FROM permissions WHERE name = ?');
    $stmt->execute(['manage_states']);
    $exists = $stmt->fetch();
    
    if (!$exists) {
        // Insert the manage_states permission
        $stmt = $conn->prepare('
            INSERT INTO permissions (name, display_name, description, module, action) 
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            'manage_states',
            'Gestionar Estados',
            'Permite gestionar estados dinámicos y transiciones',
            'states',
            'manage'
        ]);
        echo "Permiso manage_states agregado exitosamente\n";
    } else {
        echo "El permiso manage_states ya existe\n";
    }
    
    // Now assign this permission to admin role
    $stmt = $conn->prepare('SELECT id FROM roles WHERE name = ?');
    $stmt->execute(['admin']);
    $adminRole = $stmt->fetch();
    
    if ($adminRole) {
        $stmt = $conn->prepare('SELECT id FROM permissions WHERE name = ?');
        $stmt->execute(['manage_states']);
        $permission = $stmt->fetch();
        
        if ($permission) {
            // Check if role already has this permission
            $stmt = $conn->prepare('SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?');
            $stmt->execute([$adminRole['id'], $permission['id']]);
            $hasPermission = $stmt->fetch();
            
            if (!$hasPermission) {
                $stmt = $conn->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
                $stmt->execute([$adminRole['id'], $permission['id']]);
                echo "Permiso manage_states asignado al rol admin\n";
            } else {
                echo "El rol admin ya tiene el permiso manage_states\n";
            }
        }
    }
    
    // Also check current user permissions
    $stmt = $conn->prepare('
        SELECT u.id, u.username, u.role_id, r.name as role_name,
               GROUP_CONCAT(p.name) as permissions
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN role_permissions rp ON r.id = rp.role_id
        LEFT JOIN permissions p ON rp.permission_id = p.id
        WHERE u.id = 1
        GROUP BY u.id
    ');
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "Usuario actual: {$user['username']} (Rol: {$user['role_name']})\n";
        echo "Permisos: " . ($user['permissions'] ?: 'Ninguno') . "\n";
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>