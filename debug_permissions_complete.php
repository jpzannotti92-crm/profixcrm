<?php
$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm';
$config = require 'config/database.php';

try {
    $dbConfig = $config['connections']['mysql'];
    $dsn = 'mysql:host=' . $dbConfig['host'] . ';port=' . $dbConfig['port'] . ';dbname=' . $dbConfig['database'] . ';charset=' . $dbConfig['charset'];
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    echo "=== ESTRUCTURA DE permissions ===" . PHP_EOL;
    $stmt = $db->query('DESCRIBE permissions');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . ' - ' . $col['Null'] . ' - ' . $col['Key'] . ' - ' . $col['Default'] . PHP_EOL;
    }
    
    echo PHP_EOL . "=== PERMISOS DE MESA Y LEADS ===" . PHP_EOL;
    $stmt = $db->query('SELECT id, name, description FROM permissions WHERE name LIKE "%desk%" OR name LIKE "%lead%" ORDER BY name');
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($permissions as $perm) {
        echo "ID: {$perm['id']} - {$perm['name']}: {$perm['description']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "=== PERMISOS ACTUALES DE Sales Agent ===" . PHP_EOL;
    $stmt = $db->prepare('
        SELECT p.id, p.name, p.description
        FROM role_permissions rp
        JOIN roles r ON rp.role_id = r.id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE r.name = ?
        ORDER BY p.name
    ');
    $stmt->execute(['Sales Agent']);
    $currentPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($currentPerms as $perm) {
        echo "ID: {$perm['id']} - {$perm['name']}: {$perm['description']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "=== PERMISOS ACTUALES DE test_role ===" . PHP_EOL;
    $stmt->execute(['test_role']);
    $currentPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($currentPerms as $perm) {
        echo "ID: {$perm['id']} - {$perm['name']}: {$perm['description']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "=== PERMISOS QUE FALTAN PARA CREAR LEADS EN MESAS ===" . PHP_EOL;
    
    // Identificar permisos de mesa específicos que faltan
    $neededPermissions = [
        'desk.2.create' => 'Crear leads en Sales Desk',
        'desk.2.assign' => 'Asignar leads a Sales Desk',
        'desk.3.create' => 'Crear leads en Retencion',
        'desk.3.assign' => 'Asignar leads a Retencion',
        'desk.4.create' => 'Crear leads en Test Desk',
        'desk.4.assign' => 'Asignar leads a Test Desk',
        'desk.5.create' => 'Crear leads en Test Desk Pro',
        'desk.5.assign' => 'Asignar leads a Test Desk Pro'
    ];
    
    foreach ($neededPermissions as $permName => $desc) {
        $stmt = $db->prepare('SELECT id, name FROM permissions WHERE name = ?');
        $stmt->execute([$permName]);
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($perm) {
            echo "✓ $permName (ID: {$perm['id']}) - $desc" . PHP_EOL;
        } else {
            echo "✗ $permName - NO EXISTE" . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}