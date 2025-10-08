<?php
// Script para asegurar que el permiso 'desks.edit' exista y esté asignado a roles clave
// No depende de Composer; usa la conexión propia del proyecto.

require_once __DIR__ . '/src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

function tableHasColumn($db, $table, $column) {
    // Evitar placeholders en SHOW COLUMNS por compatibilidad con algunas versiones de MariaDB
    $sql = "SHOW COLUMNS FROM `" . str_replace("`", "``", $table) . "` LIKE " . $db->quote($column);
    $stmt = $db->query($sql);
    return (bool)$stmt->fetch();
}

try {
    $db = Connection::getInstance()->getConnection();

    echo "=== Fix permiso desks.edit ===\n\n";

    // 1) Asegurar que el permiso exista en 'permissions'
    $stmt = $db->prepare("SELECT id, name FROM permissions WHERE name = ? LIMIT 1");
    $stmt->execute(['desks.edit']);
    $perm = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($perm) {
        $permissionId = (int)$perm['id'];
        echo "✓ Permiso 'desks.edit' ya existe (ID: {$permissionId})\n";
    } else {
        echo "• Permiso 'desks.edit' no existe, creando...\n";

        // Detectar columnas disponibles y preparar INSERT dinámico
        $columns = ['name'];
        $values = ['desks.edit'];
        $placeholders = ['?'];

        if (tableHasColumn($db, 'permissions', 'display_name')) {
            $columns[] = 'display_name';
            $values[] = 'Editar escritorios';
            $placeholders[] = '?';
        }
        if (tableHasColumn($db, 'permissions', 'code')) {
            $columns[] = 'code';
            $values[] = 'desks.edit';
            $placeholders[] = '?';
        }
        if (tableHasColumn($db, 'permissions', 'description')) {
            $columns[] = 'description';
            $values[] = 'Permite editar escritorios en el sistema (asignar usuarios, cambiar datos).';
            $placeholders[] = '?';
        }
        if (tableHasColumn($db, 'permissions', 'module')) {
            $columns[] = 'module';
            $values[] = 'desks';
            $placeholders[] = '?';
        }
        if (tableHasColumn($db, 'permissions', 'action')) {
            $columns[] = 'action';
            $values[] = 'edit';
            $placeholders[] = '?';
        }
        if (tableHasColumn($db, 'permissions', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = date('Y-m-d H:i:s');
            $placeholders[] = '?';
        }

        $sql = "INSERT INTO permissions (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $ins = $db->prepare($sql);
        $ins->execute($values);

        $permissionId = (int)$db->lastInsertId();
        echo "✓ Permiso 'desks.edit' creado (ID: {$permissionId})\n";
    }

    // 2) Asegurar asignación del permiso a roles clave
    $targetRoles = ['super_admin', 'admin', 'manager'];

    foreach ($targetRoles as $roleName) {
        $stmt = $db->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
        $stmt->execute([$roleName]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            echo "• Rol '{$roleName}' no existe, omitiendo\n";
            continue;
        }

        $roleId = (int)$role['id'];

        // Verificar si ya está asignado
        $check = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?");
        $check->execute([$roleId, $permissionId]);
        $exists = (int)$check->fetchColumn() > 0;

        if ($exists) {
            echo "✓ Rol '{$roleName}' ya tiene 'desks.edit'\n";
            continue;
        }

        // Preparar INSERT según columnas disponibles
        $rpColumns = ['role_id', 'permission_id'];
        $rpValues = [$roleId, $permissionId];
        $rpPlaceholders = ['?', '?'];

        if (tableHasColumn($db, 'role_permissions', 'granted_at')) {
            $rpColumns[] = 'granted_at';
            $rpValues[] = date('Y-m-d H:i:s');
            $rpPlaceholders[] = '?';
        } elseif (tableHasColumn($db, 'role_permissions', 'created_at')) {
            $rpColumns[] = 'created_at';
            $rpValues[] = date('Y-m-d H:i:s');
            $rpPlaceholders[] = '?';
        }

        $insRP = $db->prepare(
            "INSERT INTO role_permissions (" . implode(', ', $rpColumns) . ") VALUES (" . implode(', ', $rpPlaceholders) . ")"
        );
        $insRP->execute($rpValues);
        echo "✓ Asignado 'desks.edit' a rol '{$roleName}'\n";
    }

    echo "\nListo. Si tu sesión estaba activa, vuelve a iniciar sesión para reflejar los permisos.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}