<?php
// Asignar rol Test Role (ID 5) al usuario leadagent (ID 7)
try {
    $host = 'localhost';
    $dbname = 'spin2pay_profixcrm';
    $username = 'root';
    $password = '';
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar que el usuario existe
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE id = 7");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "✗ Usuario leadagent (ID 7) no encontrado\n";
        exit;
    }
    
    echo "Usuario encontrado: " . $user['username'] . " (ID: " . $user['id'] . ")\n";
    
    // Verificar que el rol existe
    $stmt = $conn->prepare("SELECT id, name FROM roles WHERE id = 5");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        echo "✗ Rol Test Role (ID 5) no encontrado\n";
        exit;
    }
    
    echo "Rol encontrado: " . $role['name'] . " (ID: " . $role['id'] . ")\n";
    
    // Verificar si ya tiene el rol asignado
    $stmt = $conn->prepare("SELECT * FROM user_roles WHERE user_id = 7 AND role_id = 5");
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "⚠️  El usuario ya tiene este rol asignado\n";
    } else {
        // Asignar el rol al usuario
        $stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        $stmt->execute([7, 5]);
        echo "✓ Rol asignado exitosamente al usuario\n";
    }
    
    // Verificar permisos del rol
    $stmt = $conn->prepare("SELECT p.code, p.name FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = 5");
    $stmt->execute();
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nPermisos del rol Test Role:\n";
    foreach ($permissions as $perm) {
        echo "- " . $perm['code'] . " (" . $perm['name'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}