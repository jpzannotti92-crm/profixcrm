<?php
// Dev endpoint: asegura que el rol 'admin' (o fallback 'super_admin') tenga permisos básicos
// Permisos: roles.view y desks.view

// Cargar .env de forma segura en PHP < 8.2 sin Composer
if (PHP_VERSION_ID >= 80200 && file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../../');
        if (method_exists($dotenv, 'overload')) { $dotenv->overload(); } else { $dotenv->load(); }
    }
} else {
    $envFile = __DIR__ . '/../../../.env';
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

require_once __DIR__ . '/../../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

// Crea el permiso si no existe y devuelve su ID
function ensurePermission(PDO $pdo, string $name, ?string $code = null, ?string $description = null): int {
    $stmt = $pdo->prepare('SELECT id FROM permissions WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) { return (int)$row['id']; }
    if ($code && $description) {
        $ins = $pdo->prepare('INSERT INTO permissions (code, name, description) VALUES (?, ?, ?)');
        $ins->execute([$code, $name, $description]);
    } elseif ($code) {
        $ins = $pdo->prepare('INSERT INTO permissions (code, name) VALUES (?, ?)');
        $ins->execute([$code, $name]);
    } else {
        $ins = $pdo->prepare('INSERT INTO permissions (name) VALUES (?)');
        $ins->execute([$name]);
    }
    return (int)$pdo->lastInsertId();
}

header('Content-Type: application/json');
// CORS básico para desarrollo
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:3000';
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

try {
    $db = Connection::getInstance()->getConnection();

    // Determinar rol objetivo: admin primero, si no, super_admin
    $roleStmt = $db->prepare("SELECT id, name FROM roles WHERE name IN ('admin','super_admin') ORDER BY name='admin' DESC, name='super_admin' DESC LIMIT 1");
    $roleStmt->execute();
    $role = $roleStmt->fetch();
    if (!$role) { throw new Exception("No existe rol 'admin' ni 'super_admin'"); }
    $roleId = (int)$role['id'];

    // Lista ampliada de permisos de navegación (visibilidad de módulos)
    $requiredPerms = [
        ['name' => 'dashboard.view', 'code' => 'dashboard_view', 'desc' => 'Ver Dashboard'],
        ['name' => 'leads.view', 'code' => 'leads_view', 'desc' => 'Ver Leads'],
        ['name' => 'users.view', 'code' => 'users_view', 'desc' => 'Ver Usuarios'],
        ['name' => 'roles.view', 'code' => 'roles_view', 'desc' => 'Ver Roles'],
        ['name' => 'desks.view', 'code' => 'desks_view', 'desc' => 'Ver Escritorios'],
        ['name' => 'manage_states', 'code' => 'manage_states', 'desc' => 'Gestionar Estados'],
        ['name' => 'trading_accounts.view', 'code' => 'trading_accounts_view', 'desc' => 'Ver Cuentas de Trading'],
        ['name' => 'reports.view', 'code' => 'reports_view', 'desc' => 'Ver Reportes'],
        ['name' => 'deposits_withdrawals.view', 'code' => 'deposits_withdrawals_view', 'desc' => 'Ver Depósitos/Retiros']
    ];

    // Asegurar que existan y recolectar IDs
    $permIds = [];
    foreach ($requiredPerms as $p) {
        $pid = ensurePermission($db, $p['name'], $p['code'], $p['desc']);
        $permIds[$p['name']] = $pid;
    }

    // Detectar columnas de role_permissions
    $columnsStmt = $db->query("DESCRIBE role_permissions");
    $rpColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
    $hasActive = in_array('active', $rpColumns, true);
    $hasCreatedAt = in_array('created_at', $rpColumns, true);

    $insertSql = null;
    if ($hasActive && $hasCreatedAt) {
        $insertSql = "INSERT INTO role_permissions (role_id, permission_id, active, created_at) VALUES (?, ?, 1, NOW())";
    } elseif ($hasActive) {
        $insertSql = "INSERT INTO role_permissions (role_id, permission_id, active) VALUES (?, ?, 1)";
    } elseif ($hasCreatedAt) {
        $insertSql = "INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())";
    } else {
        $insertSql = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
    }

    $inserted = [];
    foreach ($requiredPerms as $perm) {
        $name = $perm['name'];
        if (!isset($permIds[$name])) { continue; }
        $permId = $permIds[$name];
        $existsStmt = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?");
        $existsStmt->execute([$roleId, $permId]);
        $exists = $existsStmt->fetchColumn() > 0;
        if (!$exists) {
            $ins = $db->prepare($insertSql);
            $ins->execute([$roleId, $permId]);
            $inserted[] = $name;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Permisos verificados/asignados al rol',
        'role' => $role['name'],
        'inserted' => $inserted,
        'role_id' => $roleId,
        'permission_ids' => $permIds
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>