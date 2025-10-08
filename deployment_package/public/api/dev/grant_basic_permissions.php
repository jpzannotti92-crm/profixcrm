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

    // Obtener IDs de permisos requeridos
    $permStmt = $db->prepare("SELECT id, name FROM permissions WHERE name IN ('roles.view','desks.view')");
    $permStmt->execute();
    $perms = $permStmt->fetchAll();
    $permIds = [];
    foreach ($perms as $p) { $permIds[$p['name']] = (int)$p['id']; }
    if (!isset($permIds['roles.view']) || !isset($permIds['desks.view'])) {
        throw new Exception("Faltan permisos 'roles.view' o 'desks.view' en la tabla permissions");
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
    foreach (['roles.view','desks.view'] as $permName) {
        // Verificar existencia
        $existsStmt = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?");
        $existsStmt->execute([$roleId, $permIds[$permName]]);
        $exists = $existsStmt->fetchColumn() > 0;
        if (!$exists) {
            $ins = $db->prepare($insertSql);
            $ins->execute([$roleId, $permIds[$permName]]);
            $inserted[] = $permName;
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