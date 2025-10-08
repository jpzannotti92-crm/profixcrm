<?php
$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm';
$config = require 'config/database.php';

try {
    $dbConfig = $config['connections']['mysql'];
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    echo "=== TABLAS DEL SISTEMA ===" . PHP_EOL;
    $stmt = $db->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tablas relacionadas con permisos/roles:" . PHP_EOL;
    foreach ($tables as $table) {
        if (strpos($table, 'permission') !== false || strpos($table, 'role') !== false || strpos($table, 'desk') !== false) {
            echo "- $table" . PHP_EOL;
        }
    }
    
    echo PHP_EOL . "=== ESTRUCTURA DE TABLAS CRÍTICAS ===" . PHP_EOL;
    
    // Verificar estructura de roles
    if (in_array('roles', $tables)) {
        echo PHP_EOL . "Estructura de 'roles':" . PHP_EOL;
        $stmt = $db->query('DESCRIBE roles');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  {$col['Field']} - {$col['Type']}" . PHP_EOL;
        }
    }
    
    // Verificar role_permissions
    if (in_array('role_permissions', $tables)) {
        echo PHP_EOL . "Estructura de 'role_permissions':" . PHP_EOL;
        $stmt = $db->query('DESCRIBE role_permissions');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  {$col['Field']} - {$col['Type']}" . PHP_EOL;
        }
    }
    
    // Verificar user_roles
    if (in_array('user_roles', $tables)) {
        echo PHP_EOL . "Estructura de 'user_roles':" . PHP_EOL;
        $stmt = $db->query('DESCRIBE user_roles');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  {$col['Field']} - {$col['Type']}" . PHP_EOL;
        }
    }
    
    // Verificar desks
    if (in_array('desks', $tables)) {
        echo PHP_EOL . "Estructura de 'desks':" . PHP_EOL;
        $stmt = $db->query('DESCRIBE desks');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  {$col['Field']} - {$col['Type']}" . PHP_EOL;
        }
    }
    
    // Verificar user_desks
    if (in_array('user_desks', $tables)) {
        echo PHP_EOL . "Estructura de 'user_desks':" . PHP_EOL;
        $stmt = $db->query('DESCRIBE user_desks');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  {$col['Field']} - {$col['Type']}" . PHP_EOL;
        }
    }
    
    echo PHP_EOL . "=== PERMISOS ACTUALES POR ROL ===" . PHP_EOL;
    
    // Obtener permisos por rol
    $stmt = $db->query('
        SELECT r.name as role_name, r.description, COUNT(rp.id) as permission_count
        FROM roles r
        LEFT JOIN role_permissions rp ON r.id = rp.role_id
        GROUP BY r.id, r.name, r.description
        ORDER BY r.name
    ');
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($roles as $role) {
        echo "{$role['role_name']} ({$role['description']}) - {$role['permission_count']} permisos" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}