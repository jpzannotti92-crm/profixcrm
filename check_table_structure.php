<?php
// Verificar estructura de la tabla users
require_once 'config/config.php';

// Cargar .env manualmente
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
}

try {
    $db = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']};charset=utf8mb4",
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "=== ESTRUCTURA DE LA TABLA USERS ===\n\n";
    
    // Obtener estructura de la tabla
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columnas de la tabla users:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']}) " . ($column['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
    
    echo "\n=== USUARIOS EXISTENTES ===\n";
    
    // Obtener todos los usuarios con las columnas correctas
    $columnNames = array_column($columns, 'Field');
    $selectColumns = implode(', ', $columnNames);
    
    $stmt = $db->query("SELECT $selectColumns FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "No hay usuarios en la base de datos.\n";
    } else {
        echo "Total de usuarios: " . count($users) . "\n\n";
        foreach ($users as $user) {
            echo "ID: {$user['id']}\n";
            echo "Email: {$user['email']}\n";
            echo "Username: {$user['username']}\n";
            echo "Estado: " . ($user['status'] ?? 'activo') . "\n";
            if (isset($user['first_name']) && isset($user['last_name'])) {
                echo "Nombre: {$user['first_name']} {$user['last_name']}\n";
            }
            echo "---\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error de base de datos: " . $e->getMessage() . "\n";
}