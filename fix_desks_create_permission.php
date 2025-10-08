<?php
// Script para garantizar que el permiso 'desks.create' exista y esté asignado a roles clave
// No depende de Composer; usa la conexión propia del proyecto.

require_once __DIR__ . '/src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

function tableHasColumn($db, $table, $column) {
    $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch();
}

try {
    $db = Connection::getInstance()->getConnection();

    echo "=== Fix permiso desks.create ===\n\n";

    // 1) Asegurar que el permiso exista en 'permissions'
    $stmt = $db->prepare("SELECT id, name FROM permissions WHERE name = ? LIMIT 1");
    $stmt->execute(['desks.create']);
    $perm = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($perm) {
        $permissionId = (int)$perm['id'];
        echo "✓ Permiso 'desks.create' ya existe (ID: {$permissionId})\n";
    } else {
        echo "• Permiso 'desks.create' no existe, creando...\n";

        // Detectar columnas disponibles
        $columns = [];
        $values = [];
        $placeholders = [];

        // Campos obligatorios
        $columns[] = 'name';
        $values[] = 'desks.create';
        $placeholders[] = '?';

        // Campos comunes opcionales
        if (tableHasColumn($db, 'permissions', 'display_name')) {
            $columns[] = 'display_name';
            $values[] = 'Crear escritorios';
            $placeholders[] = '?';
        }
        if (tableHasColumn($db, 'permissions', 'description')) {
            $columns[] = 'description';
            $values[] = 'Permite crear escritorios en el sistema';
            $placeholders[] = '?';
        }
        if (tableHasColumn($db, 'permissions', 'module')) {
            $columns[] = 'module';
            $values[] = 'desks';
            $placeholders[] = '?';
        }
        if (tableHasColumn($db, 'permissions', 'action')) {
            $columns[] = 'action';
            $values[] = 'create';
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
        echo "✓ Permiso 'desks.create' creado (ID: {$permissionId})\n";
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
            echo "✓ Rol '{$roleName}' ya tiene 'desks.create'\n";
            continue;
        }

        echo "• Asignando 'desks.create' al rol '{$roleName}'...\n";

        // Construir INSERT dinámico según columnas disponibles en role_permissions
        $rpColumns = ['role_id', 'permission_id'];
        $rpValues = [$roleId, $permissionId];
        $rpPlaceholders = ['?', '?'];

        if (tableHasColumn($db, 'role_permissions', 'granted_by')) {
            $rpColumns[] = 'granted_by';
            $rpValues[] = 1; // admin por defecto
            $rpPlaceholders[] = '?';
        }
        if (tableHasColumn($db, 'role_permissions', 'granted_at')) {
            $rpColumns[] = 'granted_at';
            $rpValues[] = date('Y-m-d H:i:s');
            $rpPlaceholders[] = '?';
        }
        if (tableHasColumn($db, 'role_permissions', 'created_at')) {
            $rpColumns[] = 'created_at';
            $rpValues[] = date('Y-m-d H:i:s');
            $rpPlaceholders[] = '?';
        }

        $rpSql = "INSERT INTO role_permissions (" . implode(', ', $rpColumns) . ") VALUES (" . implode(', ', $rpPlaceholders) . ")";
        $rpIns = $db->prepare($rpSql);
        $rpIns->execute($rpValues);

        echo "✓ Asignado al rol '{$roleName}'\n";
    }

    echo "\n=== Completado ===\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

?>