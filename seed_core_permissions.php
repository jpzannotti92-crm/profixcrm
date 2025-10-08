<?php
// Seeder de permisos núcleo y asignación al rol 'admin'

// Carga de entorno (.env) para asegurar que la conexión use la BD correcta
if (PHP_VERSION_ID >= 80200 && file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createMutable(__DIR__);
        if (method_exists($dotenv, 'overload')) { $dotenv->overload(); } else { $dotenv->load(); }
    }
} else {
    $envFile = __DIR__ . '/.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(ltrim($line), '#') === 0) { continue; }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $k = trim($parts[0]);
                $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
                $_ENV[$k] = $v; putenv("$k=$v");
            }
        }
    }
}

require_once __DIR__ . '/src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

function colExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->query("DESCRIBE `$table`");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return in_array($column, $cols, true);
    } catch (Exception $e) {
        return false;
    }
}

try {
    $db = Connection::getInstance();
    $pdo = $db->getConnection();

    echo "=== SEED: Permisos núcleo y asignación a rol admin ===\n";

    // Normalizar códigos vacíos existentes para evitar colisiones
    try {
        $pdo->exec("UPDATE permissions SET code = REPLACE(LOWER(name), '.', '_') WHERE code IS NULL OR code = ''");
    } catch (Exception $e) { /* ignore */ }

    // Obtener rol admin
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'admin' LIMIT 1");
    $roleStmt->execute();
    $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
    if (!$roleRow) {
        throw new Exception("Rol 'admin' no encontrado");
    }
    $adminRoleId = (int)$roleRow['id'];
    echo "✓ Rol admin id: {$adminRoleId}\n";

    // Definir permisos requeridos por endpoints
    $permNames = [
        'users.view',
        'desks.view',
        'leads.view',
        'roles.view',
        'leads.view.assigned',
        'view_states',
        'manage_states'
    ];

    $hasCode = colExists($pdo, 'permissions', 'code');
    $hasDisplay = colExists($pdo, 'permissions', 'display_name');
    $hasDesc = colExists($pdo, 'permissions', 'description');

    // Insertar permisos si faltan
    foreach ($permNames as $name) {
        $existsStmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ? LIMIT 1");
        $existsStmt->execute([$name]);
        $perm = $existsStmt->fetch(PDO::FETCH_ASSOC);
        if ($perm) {
            echo "✓ Permiso ya existe: {$name} (id {$perm['id']})\n";
            $permId = (int)$perm['id'];
        } else {
            $code = str_replace(['.', ' '], '_', strtolower($name));
            if ($hasCode && $hasDisplay && $hasDesc) {
                $ins = $pdo->prepare("INSERT INTO permissions (code, name, display_name, description) VALUES (?, ?, ?, ?)");
                $ins->execute([$code, $name, $name, "Permiso auto-seed para {$name}"]);
            } elseif ($hasCode && $hasDesc) {
                $ins = $pdo->prepare("INSERT INTO permissions (code, name, description) VALUES (?, ?, ?)");
                $ins->execute([$code, $name, "Permiso auto-seed para {$name}"]);
            } elseif ($hasCode) {
                $ins = $pdo->prepare("INSERT INTO permissions (code, name) VALUES (?, ?)");
                $ins->execute([$code, $name]);
            } else {
                $ins = $pdo->prepare("INSERT INTO permissions (name) VALUES (?)");
                $ins->execute([$name]);
            }
            $permId = (int)$pdo->lastInsertId();
            if ($permId === 0) {
                // Buscar id recién insertado por nombre
                $findStmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ? LIMIT 1");
                $findStmt->execute([$name]);
                $found = $findStmt->fetch(PDO::FETCH_ASSOC);
                $permId = $found ? (int)$found['id'] : 0;
            }
            echo "+ Permiso creado: {$name} (id {$permId})\n";
        }

        // Asignar al rol admin si no está asignado
        if ($permId > 0) {
            $rpStmt = $pdo->prepare("SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?");
            $rpStmt->execute([$adminRoleId, $permId]);
            if (!$rpStmt->fetch()) {
                // Detectar columnas
                $hasGrantedBy = colExists($pdo, 'role_permissions', 'granted_by');
                if ($hasGrantedBy) {
                    $insRp = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id, granted_by) VALUES (?, ?, ?)");
                    $insRp->execute([$adminRoleId, $permId, 1]);
                } else {
                    $insRp = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                    $insRp->execute([$adminRoleId, $permId]);
                }
                echo "+ Asignado {$name} al rol admin\n";
            } else {
                echo "✓ Rol admin ya tiene {$name}\n";
            }
        }
    }

    echo "\nListo: permisos núcleo asegurados para rol admin.\n";
} catch (Exception $e) {
    echo "Error en seed_core_permissions: " . $e->getMessage() . "\n";
}

?>