<?php
require_once __DIR__ . '/src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

try {
    $db = Connection::getInstance();
    $pdo = $db->getConnection();

    echo "=== Diagnóstico de permisos del usuario admin ===\n";

    $userStmt = $pdo->prepare("SELECT id, username, status FROM users WHERE username = 'admin' LIMIT 1");
    $userStmt->execute();
    $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$userRow) { throw new Exception("Usuario admin no encontrado"); }

    $userId = (int)$userRow['id'];
    echo "Usuario: {$userRow['username']} (id {$userId}) estado={$userRow['status']}\n";

    // Roles
    $roles = $pdo->prepare("SELECT r.id, r.name FROM roles r INNER JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
    $roles->execute([$userId]);
    $rolesList = $roles->fetchAll(PDO::FETCH_ASSOC);
    echo "Roles: " . json_encode($rolesList) . "\n";

    // Permisos vinculados por roles
    $perms = $pdo->prepare("SELECT p.id, p.name FROM permissions p INNER JOIN role_permissions rp ON p.id = rp.permission_id INNER JOIN user_roles ur ON rp.role_id = ur.role_id WHERE ur.user_id = ? ORDER BY p.id");
    $perms->execute([$userId]);
    $permList = $perms->fetchAll(PDO::FETCH_ASSOC);
    echo "Permisos: " . json_encode($permList) . "\n";

    // Probar permisos clave con SQL directo
    $tests = ['users.view','desks.view','leads.view','roles.view','view_states','manage_states'];
    $permCheck = $pdo->prepare("SELECT COUNT(*) FROM permissions p INNER JOIN role_permissions rp ON p.id = rp.permission_id INNER JOIN user_roles ur ON rp.role_id = ur.role_id WHERE ur.user_id = ? AND p.name = ?");
    foreach ($tests as $perm) {
        $permCheck->execute([$userId, $perm]);
        $has = ($permCheck->fetchColumn() > 0) ? 'SI' : 'NO';
        echo "hasPermission('{$perm}') => {$has}\n";
    }

} catch (Exception $e) {
    echo "Error diagnóstico: " . $e->getMessage() . "\n";
}

?>