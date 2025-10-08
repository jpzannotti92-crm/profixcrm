<?php
// Cargar configuración de base de datos
$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm'; // Forzar nombre correcto de BD
$config = require 'config/database.php';

try {
    // Crear conexión PDO directa
    $dbConfig = $config['connections']['mysql'];
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $stmt = $db->query('SELECT id, name, description FROM roles ORDER BY id');
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== ROLES EN EL SISTEMA ===" . PHP_EOL;
    foreach ($roles as $role) {
        echo "ID: " . $role['id'] . " - " . $role['name'] . " - " . $role['description'] . PHP_EOL;
    }
    
    // Obtener usuarios con sus roles
    echo PHP_EOL . "=== USUARIOS Y SUS ROLES ===" . PHP_EOL;
    $stmt = $db->query('
        SELECT u.id, u.username, u.email, u.first_name, u.last_name, r.name as role_name 
        FROM users u 
        LEFT JOIN user_roles ur ON u.id = ur.user_id 
        LEFT JOIN roles r ON ur.role_id = r.id 
        ORDER BY u.id
    ');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        $fullName = $user['first_name'] . ' ' . $user['last_name'];
        echo "ID: " . $user['id'] . " - " . $user['username'] . " - " . $fullName . " - Rol: " . ($user['role_name'] ?: 'Sin rol') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}