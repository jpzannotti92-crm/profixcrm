<?php
$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm';
$config = require 'config/database.php';

try {
    $dbConfig = $config['connections']['mysql'];
    $dsn = 'mysql:host=' . $dbConfig['host'] . ';port=' . $dbConfig['port'] . ';dbname=' . $dbConfig['database'] . ';charset=' . $dbConfig['charset'];
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    echo "=== ESTRUCTURA DE role_permissions ===" . PHP_EOL;
    $stmt = $db->query('DESCRIBE role_permissions');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . ' - ' . $col['Null'] . ' - ' . $col['Key'] . ' - ' . $col['Default'] . PHP_EOL;
    }
    
    echo PHP_EOL . "=== CONTENIDO DE role_permissions ===" . PHP_EOL;
    $stmt = $db->query('SELECT * FROM role_permissions LIMIT 10');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        echo implode(' | ', $row) . PHP_EOL;
    }
    
    echo PHP_EOL . "=== PERMISOS DE MESA PARA Sales Agent ===" . PHP_EOL;
    $stmt = $db->prepare('
        SELECT rp.*, r.name as role_name
        FROM role_permissions rp
        JOIN roles r ON rp.role_id = r.id
        WHERE r.name = ? AND (rp.permission LIKE "%desk%" OR rp.permission LIKE "%lead%")
        ORDER BY rp.permission
    ');
    $stmt->execute(['Sales Agent']);
    $deskPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($deskPerms as $perm) {
        echo $perm['permission'] . ' - ' . $perm['description'] . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}