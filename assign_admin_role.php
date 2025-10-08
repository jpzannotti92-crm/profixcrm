<?php
// Script para asegurar que el usuario 'admin' tenga asignado un rol administrativo
// No depende de Composer; utiliza la clase de conexión propia del proyecto.

require_once __DIR__ . '/src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

try {
    $db = Connection::getInstance()->getConnection();

    echo "=== Asignando rol administrativo al usuario 'admin' ===\n\n";

    // Buscar usuario admin
    $stmt = $db->prepare("SELECT id, username FROM users WHERE username = 'admin' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("Usuario 'admin' no encontrado");
    }
    $userId = (int) $user['id'];
    echo "✓ Usuario admin encontrado (ID: {$userId})\n";

    // Buscar rol preferido: admin, si no existe, super_admin
    $stmt = $db->prepare("SELECT id, name FROM roles WHERE name IN ('admin', 'super_admin') ORDER BY name = 'admin' DESC, name = 'super_admin' DESC LIMIT 1");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        throw new Exception("No se encontraron roles 'admin' o 'super_admin' en la tabla roles");
    }
    $roleId = (int) $role['id'];
    $roleName = $role['name'];
    echo "✓ Rol objetivo: {$roleName} (ID: {$roleId})\n";

    // Verificar si ya tiene el rol
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_id = ?");
    $stmt->execute([$userId, $roleId]);
    $hasRole = $stmt->fetchColumn() > 0;

    if ($hasRole) {
        echo "✓ El usuario admin ya tiene el rol '{$roleName}' asignado\n";
    } else {
        // Detectar columnas disponibles en user_roles
        $columnsStmt = $db->query("DESCRIBE user_roles");
        $userRolesColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('assigned_at', $userRolesColumns)) {
            $insertStmt = $db->prepare("INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())");
        } elseif (in_array('created_at', $userRolesColumns)) {
            $insertStmt = $db->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())");
        } else {
            $insertStmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        }

        $insertStmt->execute([$userId, $roleId]);
        echo "✅ Rol '{$roleName}' asignado al usuario admin\n";
    }

    echo "\nListo. Ahora el usuario 'admin' debe tener permisos del rol '{$roleName}'.\n";
    echo "Si el token estaba activo, cierra sesión y vuelve a iniciar sesión para renovar permisos en la sesión.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

?>