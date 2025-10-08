<?php
// Cargar autoloader de Composer
require_once 'vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Cargar archivos necesarios
require_once 'src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

try {
    $db = Connection::getInstance();
    
    echo "=== VERIFICACIÓN DIRECTA BASE DE DATOS ===\n";
    
    // Verificar usuario admin
    $stmt = $db->getConnection()->prepare("SELECT id, username, email, status FROM users WHERE username = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "Usuario admin no encontrado\n";
        exit();
    }
    
    echo "Usuario encontrado:\n";
    echo "ID: " . $user['id'] . "\n";
    echo "Username: " . $user['username'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Status: " . $user['status'] . "\n\n";
    
    // Verificar roles del usuario
    echo "=== ROLES DEL USUARIO ===\n";
    $stmt = $db->getConnection()->prepare("
        SELECT r.id, r.name, r.display_name 
        FROM roles r
        INNER JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $roles = $stmt->fetchAll();
    
    foreach ($roles as $role) {
        echo "- " . $role['name'] . " (ID: " . $role['id'] . ", Display: " . $role['display_name'] . ")\n";
    }
    echo "\n";
    
    // Verificar permisos del usuario
    echo "=== PERMISOS DEL USUARIO ===\n";
    $stmt = $db->getConnection()->prepare("
        SELECT DISTINCT p.id, p.name, p.display_name, p.description, p.module
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ?
        ORDER BY p.module, p.name
    ");
    $stmt->execute([$user['id']]);
    $permissions = $stmt->fetchAll();
    
    foreach ($permissions as $permission) {
        echo "- " . $permission['name'] . " (" . $permission['display_name'] . ")\n";
    }
    echo "\n";
    
    // Verificar específicamente el permiso 'roles.view'
    echo "=== VERIFICACIÓN PERMISO 'roles.view' ===\n";
    $stmt = $db->getConnection()->prepare("
        SELECT COUNT(*) as count
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ? AND p.name = 'roles.view'
    ");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch();
    
    echo "Count para 'roles.view': " . $result['count'] . "\n";
    echo "Tiene permiso: " . ($result['count'] > 0 ? 'SÍ' : 'NO') . "\n";
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'Trace: ' . $e->getTraceAsString() . "\n";
}
?>