<?php
require_once 'vendor/autoload.php';
require_once 'src/Database/Connection.php';

$db = \iaTradeCRM\Database\Connection::getInstance();

try {
    $db->getConnection()->beginTransaction();
    
    echo "=== Agregando permiso users.edit_all ===" . PHP_EOL;
    
    // 1. Agregar el permiso users.edit_all
    $stmt = $db->getConnection()->prepare("
        INSERT INTO permissions (name, display_name, description, module, action, created_at) 
        VALUES ('users.edit_all', 'Editar Todos los Usuarios', 'Editar todos los usuarios del sistema', 'users', 'edit_all', NOW())
    ");
    $stmt->execute();
    $permissionId = $db->getConnection()->lastInsertId();
    
    echo "✓ Permiso 'users.edit_all' agregado con ID: " . $permissionId . PHP_EOL;
    
    // 2. Obtener el ID del rol super_admin
    $stmt = $db->getConnection()->prepare("SELECT id FROM roles WHERE name = 'super_admin'");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        throw new Exception("Rol 'super_admin' no encontrado");
    }
    
    $roleId = $role['id'];
    echo "✓ Rol 'super_admin' encontrado con ID: " . $roleId . PHP_EOL;
    
    // 3. Asignar el permiso al rol super_admin
    $stmt = $db->getConnection()->prepare("
        INSERT INTO role_permissions (role_id, permission_id, granted_by, granted_at) 
        VALUES (?, ?, 1, NOW())
    ");
    $stmt->execute([$roleId, $permissionId]);
    
    echo "✓ Permiso 'users.edit_all' asignado al rol 'super_admin'" . PHP_EOL;
    
    $db->getConnection()->commit();
    echo PHP_EOL . "🎉 ¡Permiso agregado y asignado exitosamente!" . PHP_EOL;
    
} catch (Exception $e) {
    $db->getConnection()->rollBack();
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
}
?>