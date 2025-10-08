<?php
// Concede el permiso 'roles.create' al rol admin/super_admin para permitir crear roles

require_once __DIR__ . '/src/Database/Connection.php';
use iaTradeCRM\Database\Connection;

function colExists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->query("DESCRIBE `$table`");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return in_array($column, $cols, true);
    } catch (Exception $e) { return false; }
}

try {
    $pdo = Connection::getInstance()->getConnection();

    // Asegurar permiso roles.create
    $name = 'roles.create';
    $code = 'roles_create';
    $desc = 'Crear roles';

    $hasCode = colExists($pdo, 'permissions', 'code');
    $hasDesc = colExists($pdo, 'permissions', 'description');

    $check = $pdo->prepare("SELECT id, code FROM permissions WHERE name = ? LIMIT 1");
    $check->execute([$name]);
    $perm = $check->fetch(PDO::FETCH_ASSOC);

    if ($perm) {
        $permId = (int)$perm['id'];
        // Si el código está vacío, fijarlo
        if ($hasCode && (!isset($perm['code']) || $perm['code'] === '')) {
            $upd = $pdo->prepare("UPDATE permissions SET code = ? WHERE id = ?");
            $upd->execute([$code, $permId]);
        }
        if ($hasDesc) {
            $upd2 = $pdo->prepare("UPDATE permissions SET description = ? WHERE id = ?");
            $upd2->execute([$desc, $permId]);
        }
    } else {
        if ($hasCode && $hasDesc) {
            $ins = $pdo->prepare("INSERT INTO permissions (code, name, description) VALUES (?, ?, ?)");
            $ins->execute([$code, $name, $desc]);
            $permId = (int)$pdo->lastInsertId();
        } elseif ($hasCode) {
            $ins = $pdo->prepare("INSERT INTO permissions (code, name) VALUES (?, ?)");
            $ins->execute([$code, $name]);
            $permId = (int)$pdo->lastInsertId();
        } else {
            $ins = $pdo->prepare("INSERT INTO permissions (name) VALUES (?)");
            $ins->execute([$name]);
            $permId = (int)$pdo->lastInsertId();
        }
    }

    // Obtener rol admin o super_admin
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name IN ('admin','super_admin') ORDER BY name='admin' DESC LIMIT 1");
    $roleStmt->execute();
    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
    if (!$role) { throw new Exception("Rol admin/super_admin no encontrado"); }
    $roleId = (int)$role['id'];

    // Vincular permiso al rol
    $permIdStmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ? LIMIT 1");
    $permIdStmt->execute([$name]);
    $permRow = $permIdStmt->fetch(PDO::FETCH_ASSOC);
    if (!$permRow) { throw new Exception("Permiso roles.create no disponible"); }
    $pid = (int)$permRow['id'];

    $existsRP = $pdo->prepare("SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?");
    $existsRP->execute([$roleId, $pid]);

    if (!$existsRP->fetch()) {
        $insRP = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        $insRP->execute([$roleId, $pid]);
    }

    echo "✓ Permiso 'roles.create' concedido al rol {$roleId}\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>