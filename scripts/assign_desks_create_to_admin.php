<?php
// Script sencillo para garantizar que el permiso 'desks.create' exista
// y esté asignado a los roles 'admin' y 'super_admin'. Compatible con PHP 8.0.

require_once __DIR__ . '/../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

function getColumns(PDO $pdo, string $table): array {
    try {
        $stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $table . "'");
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map('strval', $rows);
    } catch (Exception $e) {
        return [];
    }
}

try {
    $pdo = Connection::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== Asignar permiso desks.create a roles admin ===\n";

    // 1) Asegurar permiso en tabla permissions
    $permStmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ? LIMIT 1");
    $permStmt->execute(['desks.create']);
    $permissionId = $permStmt->fetchColumn();

    if (!$permissionId) {
        echo "• Permiso 'desks.create' no existe, creando...\n";
        $cols = getColumns($pdo, 'permissions');

        $columns = ['name'];
        $values = ['desks.create'];
        $placeholders = ['?'];

        if (in_array('display_name', $cols, true)) { $columns[] = 'display_name'; $values[] = 'Crear Escritorios'; $placeholders[] = '?'; }
        if (in_array('description', $cols, true)) { $columns[] = 'description'; $values[] = 'Permite crear nuevos escritorios'; $placeholders[] = '?'; }
        // Algunas instalaciones tienen columna 'code' única; usar el mismo valor que name
        if (in_array('code', $cols, true)) { $columns[] = 'code'; $values[] = 'desks.create'; $placeholders[] = '?'; }
        if (in_array('module', $cols, true)) { $columns[] = 'module'; $values[] = 'desks'; $placeholders[] = '?'; }
        if (in_array('action', $cols, true)) { $columns[] = 'action'; $values[] = 'create'; $placeholders[] = '?'; }
        if (in_array('created_at', $cols, true)) { $columns[] = 'created_at'; $values[] = date('Y-m-d H:i:s'); $placeholders[] = '?'; }

        $sql = "INSERT INTO permissions (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $ins = $pdo->prepare($sql);
        $ins->execute($values);
        $permissionId = (int)$pdo->lastInsertId();
        echo "✓ Permiso creado (ID: {$permissionId})\n";
    } else {
        $permissionId = (int)$permissionId;
        echo "✓ Permiso 'desks.create' ya existe (ID: {$permissionId})\n";
    }

    // 2) Asignar a roles admin y super_admin si existen
    $roles = ['admin', 'super_admin'];
    foreach ($roles as $roleName) {
        $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
        $roleStmt->execute([$roleName]);
        $roleId = $roleStmt->fetchColumn();
        if (!$roleId) {
            echo "• Rol '{$roleName}' no existe, omitiendo\n";
            continue;
        }
        $roleId = (int)$roleId;

        $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?");
        $existsStmt->execute([$roleId, $permissionId]);
        $exists = (int)$existsStmt->fetchColumn() > 0;
        if ($exists) {
            echo "✓ Rol '{$roleName}' ya tiene 'desks.create'\n";
            continue;
        }

        $rpCols = getColumns($pdo, 'role_permissions');
        $rpColumns = ['role_id', 'permission_id'];
        $rpValues = [$roleId, $permissionId];
        $rpPlaceholders = ['?', '?'];
        if (in_array('created_at', $rpCols, true)) { $rpColumns[] = 'created_at'; $rpValues[] = date('Y-m-d H:i:s'); $rpPlaceholders[] = '?'; }

        $insRP = $pdo->prepare(
            "INSERT INTO role_permissions (" . implode(', ', $rpColumns) . ") VALUES (" . implode(', ', $rpPlaceholders) . ")"
        );
        $insRP->execute($rpValues);
        echo "✓ Asignado 'desks.create' a rol '{$roleName}'\n";
    }

    echo "\nListo.\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}