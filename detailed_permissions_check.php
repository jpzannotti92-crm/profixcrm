<?php
$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm';
$config = require 'config/database.php';

try {
    $dbConfig = $config['connections']['mysql'];
    $dsn = 'mysql:host=' . $dbConfig['host'] . ';port=' . $dbConfig['port'] . ';dbname=' . $dbConfig['database'] . ';charset=' . $dbConfig['charset'];
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    echo "=== PERMISOS POR ROL ===" . PHP_EOL;
    $stmt = $db->query('
        SELECT r.name as role_name, COUNT(rp.permission_id) as permission_count
        FROM roles r
        LEFT JOIN role_permissions rp ON r.id = rp.role_id
        GROUP BY r.id, r.name
        ORDER BY r.name
    ');
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($roles as $role) {
        echo $role['role_name'] . ': ' . $role['permission_count'] . ' permisos' . PHP_EOL;
    }
    
    echo PHP_EOL . "=== PERMISOS ESPECÍFICOS ===" . PHP_EOL;
    $stmt = $db->query('
        SELECT r.name as role_name, p.name as permission_name, p.resource, p.action
        FROM roles r
        JOIN role_permissions rp ON r.id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        ORDER BY r.name, p.resource, p.action
    ');
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($permissions as $perm) {
        echo $perm['role_name'] . ' -> ' . $perm['resource'] . '.' . $perm['action'] . ' (' . $perm['permission_name'] . ')' . PHP_EOL;
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