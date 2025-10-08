<?php
// Asigna un rol a un usuario por username. Uso:
//   php scripts/assign_role_to_user.php username=test_front role=manager

require_once __DIR__ . '/../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

function getArg($key, $default = null) {
    // Soporta CLI (argv) y query string
    // Formato CLI: key=value
    $value = $default;
    if (!empty($_SERVER['argv'])) {
        foreach ($_SERVER['argv'] as $arg) {
            if (strpos($arg, '=') !== false) {
                [$k, $v] = explode('=', $arg, 2);
                if ($k === $key) { $value = $v; }
            }
        }
    }
    if (isset($_GET[$key])) { $value = $_GET[$key]; }
    return $value;
}

try {
    $username = getArg('username');
    $roleName = getArg('role', 'manager');
    if (!$username) { throw new Exception("Falta 'username' (ej: username=test_front)"); }

    $pdo = Connection::getInstance()->getConnection();

    echo "=== Asignar rol a usuario ===\n";
    echo "Usuario: {$username}\n";
    echo "Rol: {$roleName}\n\n";

    // Buscar usuario
    $stmt = $pdo->prepare("SELECT id, status FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { throw new Exception("Usuario '{$username}' no encontrado"); }
    $userId = (int)$user['id'];
    echo "✓ Usuario encontrado (ID {$userId}, status: {$user['status']})\n";

    // Activar usuario si está inactivo
    if (($user['status'] ?? 'inactive') !== 'active') {
        $pdo->prepare("UPDATE users SET status='active' WHERE id = ?")->execute([$userId]);
        echo "✓ Usuario activado\n";
    }

    // Buscar o crear rol
    $r = $pdo->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
    $r->execute([$roleName]);
    $role = $r->fetch(PDO::FETCH_ASSOC);
    if (!$role) {
        $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)")->execute([$roleName, ucfirst($roleName)]);
        $roleId = (int)$pdo->lastInsertId();
        echo "✓ Rol creado (ID {$roleId})\n";
    } else {
        $roleId = (int)$role['id'];
        echo "• Rol existente (ID {$roleId})\n";
    }

    // Asignar rol al usuario si no está
    $check = $pdo->prepare("SELECT 1 FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1");
    $check->execute([$userId, $roleId]);
    if (!$check->fetch()) {
        // Detectar columnas opcionales
        $colsStmt = $pdo->query("DESCRIBE user_roles");
        $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('assigned_at', $cols)) {
            $pdo->prepare("INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())")->execute([$userId, $roleId]);
        } elseif (in_array('created_at', $cols)) {
            $pdo->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())")->execute([$userId, $roleId]);
        } else {
            $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$userId, $roleId]);
        }
        echo "✓ Rol asignado al usuario\n";
    } else {
        echo "• El usuario ya tiene este rol\n";
    }

    // Garantizar permisos básicos para dashboard
    $permNames = ['dashboard.view', 'dashboard.stats'];
    foreach ($permNames as $permName) {
        $p = $pdo->prepare("SELECT id FROM permissions WHERE name = ? LIMIT 1");
        $p->execute([$permName]);
        $perm = $p->fetch(PDO::FETCH_ASSOC);
        if (!$perm) {
            $code = str_replace('.', '_', $permName);
            $pdo->prepare("INSERT INTO permissions (code, name, description) VALUES (?, ?, ?)")
                ->execute([$code, $permName, 'Permiso generado automáticamente']);
            $permId = (int)$pdo->lastInsertId();
            echo "✓ Permiso creado: {$permName}\n";
        } else { $permId = (int)$perm['id']; }

        $rp = $pdo->prepare("SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1");
        $rp->execute([$roleId, $permId]);
        if (!$rp->fetch()) {
            $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)")
                ->execute([$roleId, $permId]);
            echo "✓ Permiso '{$permName}' concedido al rol '{$roleName}'\n";
        }
    }

    echo "\nListo. Cierra sesión y vuelve a iniciar sesión para refrescar el token.\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>