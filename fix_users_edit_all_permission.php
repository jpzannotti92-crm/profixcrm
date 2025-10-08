<?php
// Script para garantizar que el permiso 'users.edit_all' exista y esté asignado a roles clave
// No depende de Composer; usa la conexión propia del proyecto.

require_once __DIR__ . '/src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

function tableHasColumn($db, $table, $column) {
    // Algunas versiones de MariaDB no soportan placeholders en SHOW COLUMNS
    $columnQuoted = $db->quote($column);
    $sql = "SHOW COLUMNS FROM `$table` LIKE $columnQuoted";
    $stmt = $db->query($sql);
    return (bool)$stmt->fetch();
}

try {
    $db = Connection::getInstance()->getConnection();

    echo "=== Fix permiso users.edit_all ===\n\n";

    // 1) Asegurar que el permiso exista en 'permissions'
    $stmt = $db->prepare("SELECT id, name FROM permissions WHERE name = ? LIMIT 1");
    $stmt->execute(['users.edit_all']);
    $perm = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($perm) {
        $permissionId = (int)$perm['id'];
        echo "✓ Permiso 'users.edit_all' ya existe (ID: {$permissionId})\n";
    } else {
        echo "• Permiso 'users.edit_all' no existe, creando...\n";

        // Detectar columnas disponibles
        $columns = [];
        $values = [];
        $placeholders = [];

        // Campos obligatorios
        $columns[] = 'name';
        $values[] = 'users.edit_all';
        $placeholders[] = '?';

        // Campos comunes opcionales
        if (tableHasColumn($db, 'permissions', 'display_name')) {
            $columns[] = 'display_name';
            $values[] = 'Editar todos los usuarios';
            $placeholders[] = '?';
        }
        if (tableHasColumn($db, 'permissions', 'code')) {
            $columns[] = 'code';
            $values[] = 'users.edit_all';
            $placeholders[] = '?';
        }
        if (tableHasColumn($db, 'permissions', 'description')) {
            $columns[] = 'description';
            $values[] = 'Permite editar y asignar desks a cualquier usuario';
            $placeholders[] = '?';
        }
        if (tableHasColumn($db, 'permissions', 'module')) {
            $columns[] = 'module';
            $values[] = 'users';
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

        // Construir SQL seguro con valores citados (evita problemas con placeholders en algunos entornos)
        $quotedValues = [];
        foreach ($values as $val) {
            $quotedValues[] = $db->quote($val);
        }
        $sql = "INSERT INTO permissions (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $quotedValues) . ")";
        $db->exec($sql);
        $permissionId = (int)$db->lastInsertId();
        echo "✓ Permiso 'users.edit_all' creado (ID: {$permissionId})\n";
    }

    // 2) Asignar el permiso al rol admin/super_admin
    $stmt = $db->prepare("SELECT id, name FROM roles WHERE name IN ('admin', 'super_admin') ORDER BY name");
    $stmt->execute();
    $adminRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($adminRoles)) {
        echo "⚠️ No se encontró rol 'admin' ni 'super_admin'. Cree uno antes de asignar el permiso.\n";
    } else {
        foreach ($adminRoles as $role) {
            // Verificar si ya está asignado
            $check = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?");
            $check->execute([(int)$role['id'], $permissionId]);
            $already = (int)$check->fetchColumn();

            if ($already) {
                echo "- Ya asignado al rol {$role['name']} (ID: {$role['id']})\n";
                continue;
            }

            // Armar INSERT según columnas disponibles
            $rpColumns = ['role_id', 'permission_id'];
            $rpValues = [(int)$role['id'], $permissionId];
            $rpPlaceholders = ['?', '?'];

            if (tableHasColumn($db, 'role_permissions', 'created_at')) {
                $rpColumns[] = 'created_at';
                $rpValues[] = date('Y-m-d H:i:s');
                $rpPlaceholders[] = '?';
            } elseif (tableHasColumn($db, 'role_permissions', 'granted_at')) {
                $rpColumns[] = 'granted_at';
                $rpValues[] = date('Y-m-d H:i:s');
                $rpPlaceholders[] = '?';
            }

            $quotedRpValues = [];
            foreach ($rpValues as $val) {
                $quotedRpValues[] = $db->quote($val);
            }
            $sql = "INSERT INTO role_permissions (`" . implode('`, `', $rpColumns) . "`) VALUES (" . implode(', ', $quotedRpValues) . ")";
            $db->exec($sql);
            echo "✓ Asignado 'users.edit_all' al rol {$role['name']} (ID: {$role['id']})\n";
        }
    }

    echo "\nListo. Vuelve a iniciar sesión si tu sesión estaba activa para que se apliquen los permisos.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}