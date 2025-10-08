<?php
$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm';
$config = require 'config/database.php';

try {
    $dbConfig = $config['connections']['mysql'];
    $dsn = 'mysql:host=' . $dbConfig['host'] . ';port=' . $dbConfig['port'] . ';dbname=' . $dbConfig['database'] . ';charset=' . $dbConfig['charset'];
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    echo "=== ESTRUCTURA DE PERMISSIONS ===" . PHP_EOL;
    $stmt = $db->query('DESCRIBE permissions');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . PHP_EOL;
    }
    
    echo PHP_EOL . "=== ESTRUCTURA DE ROLE_PERMISSIONS ===" . PHP_EOL;
    $stmt = $db->query('DESCRIBE role_permissions');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . PHP_EOL;
    }
    
    echo PHP_EOL . "=== ESTRUCTURA DE DESK_USERS ===" . PHP_EOL;
    $stmt = $db->query('DESCRIBE desk_users');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . PHP_EOL;
    }
    
    echo PHP_EOL . "=== PERMISOS EXISTENTES ===" . PHP_EOL;
    $stmt = $db->query('SELECT * FROM permissions ORDER BY name');
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($permissions as $perm) {
        echo "ID: {$perm['id']} - {$perm['name']} - {$perm['description']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "=== PERMISOS POR ROL (DETALLADO) ===" . PHP_EOL;
    $stmt = $db->query('
        SELECT r.name as role_name, p.name as permission_name, p.description
        FROM roles r
        JOIN role_permissions rp ON r.id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        ORDER BY r.name, p.name
    ');
    $rolePerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $currentRole = '';
    foreach ($rolePerms as $perm) {
        if ($currentRole != $perm['role_name']) {
            echo PHP_EOL . "--- {$perm['role_name']} ---" . PHP_EOL;
            $currentRole = $perm['role_name'];
        }
        echo "  ✓ {$perm['permission_name']} - {$perm['description']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "=== ACCESO A MESAS POR USUARIO ===" . PHP_EOL;
    $stmt = $db->query('
        SELECT u.username, u.first_name, u.last_name, d.name as desk_name, d.id as desk_id
        FROM users u
        LEFT JOIN desk_users du ON u.id = du.user_id
        LEFT JOIN desks d ON du.desk_id = d.id
        ORDER BY u.username
    ');
    $userDesks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($userDesks as $user) {
        $desk = $user['desk_name'] ? $user['desk_name'] . ' (ID: ' . $user['desk_id'] . ')' : 'SIN MESA ASIGNADA';
        echo $user['username'] . ' (' . $user['first_name'] . ' ' . $user['last_name'] . ') -> ' . $desk . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}