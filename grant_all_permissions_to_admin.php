<?php
// Asigna TODOS los permisos existentes en la tabla 'permissions' al rol 'admin'
// No usa Composer; emplea la conexión propia del proyecto.

require_once __DIR__ . '/src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

function tableHasColumn($db, $table, $column) {
    $sql = "SHOW COLUMNS FROM `" . str_replace("`", "``", $table) . "` LIKE " . $db->quote($column);
    $stmt = $db->query($sql);
    return (bool)$stmt->fetch();
}

try {
    $db = Connection::getInstance()->getConnection();

    echo "=== Asignar TODOS los permisos al rol 'admin' ===\n\n";

    // 1) Obtener rol admin
    $stmt = $db->prepare("SELECT id, name FROM roles WHERE name = ? LIMIT 1");
    $stmt->execute(['admin']);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$role) {
        echo "❌ Rol 'admin' no encontrado\n";
        exit(1);
    }
    $roleId = (int)$role['id'];
    echo "✓ Rol admin encontrado (ID: {$roleId})\n";

    // 2) Obtener TODOS los permisos
    $permsStmt = $db->query("SELECT id, name FROM permissions ORDER BY name");
    $allPerms = $permsStmt->fetchAll(PDO::FETCH_ASSOC);
    $allIds = array_map(function($p){ return (int)$p['id']; }, $allPerms);
    echo "✓ Total permisos en sistema: " . count($allIds) . "\n";

    // 3) Obtener permisos ya asignados al rol
    $assignedStmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $assignedStmt->execute([$roleId]);
    $assignedIds = array_map('intval', $assignedStmt->fetchAll(PDO::FETCH_COLUMN));
    echo "✓ Permisos ya asignados al rol: " . count($assignedIds) . "\n";

    // 4) Calcular faltantes
    $assignedSet = array_flip($assignedIds);
    $missing = [];
    foreach ($allPerms as $perm) {
        $pid = (int)$perm['id'];
        if (!isset($assignedSet[$pid])) {
            $missing[] = $perm;
        }
    }
    echo "• Faltantes por asignar: " . count($missing) . "\n";
    if (!empty($missing)) {
        echo "   -> " . implode(', ', array_map(function($p){ return $p['name']; }, $missing)) . "\n";
    }

    // 5) Preparar INSERT acorde a columnas
    $columnsStmt = $db->query("DESCRIBE role_permissions");
    $rpColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);

    $sql = '';
    if (in_array('granted_by', $rpColumns, true) && in_array('granted_at', $rpColumns, true)) {
        $sql = "INSERT INTO role_permissions (role_id, permission_id, granted_by, granted_at) VALUES (?, ?, 1, NOW())";
    } elseif (in_array('created_at', $rpColumns, true)) {
        $sql = "INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())";
    } else {
        $sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
    }
    $ins = $db->prepare($sql);

    // 6) Insertar faltantes
    $added = 0;
    foreach ($missing as $perm) {
        try {
            $ins->execute([$roleId, (int)$perm['id']]);
            $added++;
        } catch (Exception $e) {
            echo "   ⚠️ Error asignando {$perm['name']}: " . $e->getMessage() . "\n";
        }
    }

    echo "\n✓ Nuevos permisos asignados: {$added}\n";

    // 7) Confirmar conteo final
    $assignedStmt->execute([$roleId]);
    $finalAssigned = count($assignedStmt->fetchAll(PDO::FETCH_COLUMN));
    echo "✓ Total asignados ahora: {$finalAssigned}\n";

    echo "\nListo. Si tu sesión estaba activa, vuelve a iniciar sesión para reflejar los cambios.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}