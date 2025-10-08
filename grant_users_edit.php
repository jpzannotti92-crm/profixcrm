<?php
// Conceder permiso 'users.edit' al rol admin/super_admin
// Uso: C:\xampp\php\php.exe grant_users_edit.php

require_once __DIR__ . '/src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

try {
    $db = Connection::getInstance();
    $pdo = $db->getConnection();

    // Asegurar que el permiso exista en la tabla permissions
    $permName = 'users.edit';
    $permStmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ? LIMIT 1");
    $permStmt->execute([$permName]);
    $perm = $permStmt->fetch(PDO::FETCH_ASSOC);

    if (!$perm) {
        $code = 'users_edit';
        $display = 'Editar Usuarios';
        $desc = 'Permiso para editar usuarios del sistema';
        $insPerm = $pdo->prepare("INSERT INTO permissions (code, name, description) VALUES (?, ?, ?)");
        $insPerm->execute([$code, $permName, $desc]);
        $permId = (int)$pdo->lastInsertId();
        echo "✓ Permiso creado: {$permName} (id {$permId})\n";
    } else {
        $permId = (int)$perm['id'];
        echo "• Permiso existente: {$permName} (id {$permId})\n";
    }

    // Buscar rol admin o super_admin
    $roleStmt = $pdo->query("SELECT id FROM roles WHERE name IN ('admin','super_admin') ORDER BY name LIMIT 1");
    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
    if (!$role) {
        throw new Exception("No se encontró rol 'admin' o 'super_admin'");
    }
    $roleId = (int)$role['id'];
    echo "• Rol destino: id {$roleId}\n";

    // Conceder permiso si no está ya asignado
    $existsRP = $pdo->prepare("SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1");
    $existsRP->execute([$roleId, $permId]);
    if (!$existsRP->fetch()) {
        $insRP = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        $insRP->execute([$roleId, $permId]);
        echo "✓ Permiso '{$permName}' concedido al rol {$roleId}\n";
    } else {
        echo "• El rol {$roleId} ya tiene el permiso '{$permName}'\n";
    }

    echo "Hecho.\n";
    exit(0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>