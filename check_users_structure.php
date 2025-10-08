<?php
$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm';
$config = require 'config/database.php';

try {
    $dbConfig = $config['connections']['mysql'];
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    echo "=== ESTRUCTURA DE TABLA USERS ===" . PHP_EOL;
    $stmt = $db->query('DESCRIBE users');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . ' - ' . ($col['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . PHP_EOL;
    }
    
    echo PHP_EOL . "=== MUESTRA DE USUARIOS ===" . PHP_EOL;
    $stmt = $db->query('SELECT id, username, email, first_name, last_name FROM users LIMIT 3');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        echo "ID: {$user['id']} - Username: {$user['username']} - Email: {$user['email']}" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}