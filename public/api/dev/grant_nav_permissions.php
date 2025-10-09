<?php
// Asigna permisos básicos de navegación a roles 'admin' y 'super_admin'
// Uso temporal para producción; eliminar tras ejecutar.

header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

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

function ensureRoleHasPermission(PDO $pdo, int $roleId, int $permId): void {
    $stmt = $pdo->prepare('SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1');
    $stmt->execute([$roleId, $permId]);
    if (!$stmt->fetch()) {
        $ins = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        $ins->execute([$roleId, $permId]);
    }
}

try {
    $pdo = Connection::getInstance()->getConnection();

    // Obtener roles admin/super_admin
    $rolesStmt = $pdo->query("SELECT id, name FROM roles WHERE name IN ('admin','super_admin')");
    $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$roles) { throw new Exception("No existen roles 'admin' o 'super_admin'"); }

    // Permisos de navegación básicos (backend names usados por el mapeo del frontend)
    $perms = [
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

    $assigned = [];
    foreach ($perms as $p) {
        $pid = ensurePermission($pdo, $p['name'], $p['code'], $p['desc']);
        foreach ($roles as $role) {
            ensureRoleHasPermission($pdo, (int)$role['id'], $pid);
            $assigned[] = [$role['name'], $p['name']];
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Permisos de navegación asignados a roles admin/super_admin',
        'assigned' => $assigned
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>