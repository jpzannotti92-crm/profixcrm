<?php
// Seeder extendido de permisos con módulos y asignación al rol admin

// Cargar .env de forma tolerante sin Composer
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(ltrim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) { $_ENV[trim($parts[0])] = trim($parts[1]); putenv(trim($parts[0]).'='.trim($parts[1])); }
    }
}

require_once __DIR__ . '/src/Database/Connection.php';
use iaTradeCRM\Database\Connection;

function tableHasColumn(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) { return false; }
}

try {
    $db = Connection::getInstance();
    $pdo = $db->getConnection();

    echo "=== SEED EXTENDIDO DE PERMISOS ===\n";
    // Normalizar códigos vacíos existentes para evitar conflictos de clave única
    try {
        $pdo->exec("UPDATE permissions SET code = CONCAT('perm_fix_', id) WHERE code IS NULL OR code = ''");
    } catch (Exception $e) {
        // continuar sin bloquear
    }

    $hasCode = tableHasColumn($pdo, 'permissions', 'code');
    $hasDisplay = tableHasColumn($pdo, 'permissions', 'display_name');
    $hasDesc = tableHasColumn($pdo, 'permissions', 'description');
    $hasModule = tableHasColumn($pdo, 'permissions', 'module');

    // Lista extendida de permisos por módulos
    $catalog = [
        // Leads
        ['name' => 'leads.view', 'display' => 'Ver Leads', 'module' => 'Leads'],
        ['name' => 'leads.view.all', 'display' => 'Ver Todos los Leads', 'module' => 'Leads'],
        ['name' => 'leads.view.assigned', 'display' => 'Ver Leads Asignados', 'module' => 'Leads'],
        ['name' => 'leads.view.desk', 'display' => 'Ver Leads de Mesas', 'module' => 'Leads'],
        ['name' => 'leads.create', 'display' => 'Crear Lead', 'module' => 'Leads'],
        ['name' => 'leads.import', 'display' => 'Importar Leads', 'module' => 'Leads'],
        ['name' => 'leads.update', 'display' => 'Actualizar Lead', 'module' => 'Leads'],
        ['name' => 'leads.assign', 'display' => 'Asignación Masiva de Leads', 'module' => 'Leads'],
        ['name' => 'leads.delete', 'display' => 'Eliminar Lead', 'module' => 'Leads'],
        ['name' => 'leads.export', 'display' => 'Exportar Leads', 'module' => 'Leads'],

        // Usuarios
        ['name' => 'users.view', 'display' => 'Ver Usuarios', 'module' => 'Usuarios'],
        ['name' => 'users.create', 'display' => 'Crear Usuario', 'module' => 'Usuarios'],
        ['name' => 'users.update', 'display' => 'Actualizar Usuario', 'module' => 'Usuarios'],
        ['name' => 'users.delete', 'display' => 'Eliminar Usuario', 'module' => 'Usuarios'],
        ['name' => 'users.manage.roles', 'display' => 'Gestionar Roles de Usuario', 'module' => 'Usuarios'],

        // Mesas
        ['name' => 'desks.view', 'display' => 'Ver Mesas', 'module' => 'Mesas'],
        ['name' => 'desks.manage', 'display' => 'Gestionar Mesas', 'module' => 'Mesas'],

        // Roles y permisos
        ['name' => 'roles.view', 'display' => 'Ver Roles', 'module' => 'Roles'],
        ['name' => 'roles.manage', 'display' => 'Gestionar Roles', 'module' => 'Roles'],
        ['name' => 'permissions.manage', 'display' => 'Gestionar Permisos', 'module' => 'Roles'],

        // Estados
        ['name' => 'states.view', 'display' => 'Ver Estados', 'module' => 'Estados'],
        ['name' => 'states.manage', 'display' => 'Gestionar Estados', 'module' => 'Estados'],

        // Configuración
        ['name' => 'config.view', 'display' => 'Ver Configuración', 'module' => 'Configuración'],
        ['name' => 'config.manage', 'display' => 'Gestionar Configuración', 'module' => 'Configuración'],

        // Dashboard y reportes
        ['name' => 'dashboard.view', 'display' => 'Ver Dashboard', 'module' => 'Dashboard'],
        ['name' => 'reports.view', 'display' => 'Ver Reportes', 'module' => 'Reportes'],
    ];

    // Insertar permisos faltantes
    foreach ($catalog as $item) {
        $name = $item['name'];
        $exists = $pdo->prepare("SELECT id FROM permissions WHERE name = ? LIMIT 1");
        $exists->execute([$name]);
        $row = $exists->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo "✓ Existe: {$name}\n";
            continue;
        }

        $code = str_replace(['.', ' '], '_', strtolower($name));
        if (!$code) { $code = 'perm_'.substr(md5($name), 0, 8); }
        $display = $item['display'];
        $desc = "Permiso extendido para {$name}";
        $module = $item['module'];
        try {
            if ($hasCode && $hasDisplay && $hasDesc && $hasModule) {
                $ins = $pdo->prepare("INSERT INTO permissions (code, name, display_name, description, module) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$code, $name, $display, $desc, $module]);
            } elseif ($hasCode && $hasDisplay && $hasDesc) {
                $ins = $pdo->prepare("INSERT INTO permissions (code, name, display_name, description) VALUES (?, ?, ?, ?)");
                $ins->execute([$code, $name, $display, $desc]);
            } elseif ($hasCode && $hasDesc) {
                $ins = $pdo->prepare("INSERT INTO permissions (code, name, description) VALUES (?, ?, ?)");
                $ins->execute([$code, $name, $desc]);
            } elseif ($hasCode) {
                $ins = $pdo->prepare("INSERT INTO permissions (code, name) VALUES (?, ?)");
                $ins->execute([$code, $name]);
            } else {
                $ins = $pdo->prepare("INSERT INTO permissions (name) VALUES (?)");
                $ins->execute([$name]);
            }
            echo "+ Insertado: {$name} (code: {$code})\n";
        } catch (Exception $e) {
            echo "⚠ No insertado: {$name} ({$e->getMessage()})\n";
        }
    }

    // Asignar todos los permisos al rol admin (o super_admin si no existe admin)
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name IN ('admin','super_admin') ORDER BY name='admin' DESC LIMIT 1");
    $roleStmt->execute();
    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
    if ($role) {
        $roleId = (int)$role['id'];
        $permIds = $pdo->query("SELECT id FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($permIds as $pid) {
            $check = $pdo->prepare("SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?");
            $check->execute([$roleId, $pid]);
            if (!$check->fetch()) {
                $insRp = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                $insRp->execute([$roleId, $pid]);
            }
        }
        echo "✓ Asignados permisos extendidos al rol {$roleId}\n";
    } else {
        echo "⚠ No se encontró rol admin/super_admin para asignar permisos.\n";
    }

    echo "Hecho.\n";
} catch (Exception $e) {
    echo "Error en seed_extended_permissions: " . $e->getMessage() . "\n";
}

?>