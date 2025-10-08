<?php
// Verificar roles del usuario leadagent
try {
    $host = 'localhost';
    $dbname = 'spin2pay_profixcrm';
    $username = 'root';
    $password = '';
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar el usuario leadagent
    $stmt = $conn->prepare("SELECT id, username, email, first_name, last_name, status FROM users WHERE username = 'leadagent'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "=== USUARIO LEADAGENT ENCONTRADO ===\n";
        echo "ID: " . $user['id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Nombre: " . $user['first_name'] . " " . $user['last_name'] . "\n";
        echo "Estado: " . $user['status'] . "\n";
        
        // Verificar roles
        $stmt = $conn->prepare("SELECT r.name, r.id FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?");
        $stmt->execute([$user['id']]);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nRoles asignados:\n";
        if (empty($roles)) {
            echo "⚠️  No tiene roles asignados\n";
        } else {
            foreach ($roles as $role) {
                echo "- " . $role['name'] . " (ID: " . $role['id'] . ")\n";
            }
        }
        
        // Verificar permisos del rol
        if (!empty($roles)) {
            $roleIds = array_column($roles, 'id');
            $placeholders = str_repeat('?,', count($roleIds) - 1) . '?';
            $stmt = $conn->prepare("SELECT DISTINCT p.code, p.name FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id IN ($placeholders)");
            $stmt->execute($roleIds);
            $rolePermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "\nPermisos del rol:\n";
            if (empty($rolePermissions)) {
                echo "⚠️  Los roles no tienen permisos asignados\n";
            } else {
                foreach ($rolePermissions as $perm) {
                    echo "- " . $perm['code'] . " (" . $perm['name'] . ")\n";
                }
            }
        }
        
    } else {
        echo "✗ Usuario leadagent no encontrado\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}