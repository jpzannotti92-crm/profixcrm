<?php
// Verificar usuario leadagent en la base de datos
require_once 'vendor/autoload.php';
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Buscar el usuario leadagent
    $stmt = $conn->prepare("SELECT id, username, email, first_name, last_name, status FROM users WHERE email = ? OR username = ?");
    $stmt->execute(['leadagent@example.com', 'leadagent']);
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
        foreach ($roles as $role) {
            echo "- " . $role['name'] . " (ID: " . $role['id'] . ")\n";
        }
        
        // Verificar permisos directos
        $stmt = $conn->prepare("SELECT p.code, p.name FROM user_permissions up JOIN permissions p ON up.permission_id = p.id WHERE up.user_id = ?");
        $stmt->execute([$user['id']]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nPermisos directos:\n";
        foreach ($permissions as $perm) {
            echo "- " . $perm['code'] . " (" . $perm['name'] . ")\n";
        }
        
    } else {
        echo "✗ Usuario leadagent no encontrado\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}